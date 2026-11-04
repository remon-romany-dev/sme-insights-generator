<?php
/**
 * Manages the WordPress cron job scheduling for content generation.
 *
 * @package SME_Insights_Generator
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIG_Cron_Manager class.
 */
class SIG_Cron_Manager {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'sig_generate_content_event';

	/**
	 * Singleton instance.
	 *
	 * @var SIG_Cron_Manager
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return SIG_Cron_Manager
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'generate_content_task' ) );
		add_action( 'wp_ajax_sig_run_now', array( $this, 'handle_run_now_ajax' ) );
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_interval' ) );
		
		add_action( 'init', array( $this, 'maybe_run_cron_on_page_load' ), 999 );
	}

	/**
	 * Schedules the main cron job.
	 */
	public function schedule_cron_job() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$options = get_option( 'sig_settings', array() );
			$interval_minutes = isset( $options['interval_between_batches'] ) ? absint( $options['interval_between_batches'] ) : 1440;
			
			if ( $interval_minutes === 60 ) {
				$interval_name = 'hourly';
			} elseif ( $interval_minutes === 1440 ) {
				$interval_name = 'daily';
			} else {
				$interval_name = 'sig_custom_' . $interval_minutes . '_minutes';
			}
			
			wp_schedule_event( time(), $interval_name, self::CRON_HOOK );
		}
	}

	/**
	 * Clears the main cron job.
	 */
	public function clear_cron_job() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Reschedules the cron job after a run.
	 *
	 * @param int $interval_minutes The interval in minutes.
	 */
	private function reschedule_after_run( $interval_minutes ) {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		
		if ( $interval_minutes === 60 ) {
			$interval_name = 'hourly';
		} elseif ( $interval_minutes === 1440 ) {
			$interval_name = 'daily';
		} else {
			$interval_name = 'sig_custom_' . $interval_minutes . '_minutes';
			add_filter( 'cron_schedules', array( $this, 'add_custom_cron_interval' ) );
		}
		
		$next_run_time = time() + ( $interval_minutes * 60 );
		$scheduled = wp_schedule_event( $next_run_time, $interval_name, self::CRON_HOOK );
		
		if ( false === $scheduled ) {
			error_log( 'SIG Cron: Failed to reschedule with wp_schedule_event. Trying wp_schedule_single_event...' );
			$scheduled = wp_schedule_single_event( $next_run_time, self::CRON_HOOK );
			if ( false === $scheduled ) {
				error_log( 'SIG Cron: CRITICAL - Failed to reschedule cron job completely!' );
			} else {
				error_log( 'SIG Cron: WARNING - Rescheduled using single event (not recurring). Next run: ' . date( 'Y-m-d H:i:s', $next_run_time ) );
			}
		} else {
			error_log( 'SIG Cron: Successfully rescheduled recurring event. Next run: ' . date( 'Y-m-d H:i:s', $next_run_time ) . ', Interval: ' . $interval_minutes . ' minutes' );
		}
	}

	/**
	 * Reschedules the cron job based on the new scheduling settings.
	 *
	 * @param array|int $settings The new scheduling settings array or posts_per_day (for backward compatibility).
	 */
	public function reschedule_cron_job( $settings ) {
		$this->clear_cron_job();
		
		if ( is_int( $settings ) ) {
			$settings = array( 'posts_per_day' => $settings );
		}
		
		$interval_minutes = isset( $settings['interval_between_batches'] ) ? absint( $settings['interval_between_batches'] ) : 1440;
		
		if ( $interval_minutes === 60 ) {
			$interval_name = 'hourly';
		} elseif ( $interval_minutes === 1440 ) {
			$interval_name = 'daily';
		} else {
			$interval_name = 'sig_custom_' . $interval_minutes . '_minutes';
			add_filter( 'cron_schedules', array( $this, 'add_custom_cron_interval' ) );
		}
		
		$scheduled = wp_schedule_event( time(), $interval_name, self::CRON_HOOK );
		
		if ( false === $scheduled ) {
			error_log( 'SIG Cron: Failed to schedule cron job in reschedule_cron_job(). Trying single event...' );
			$scheduled = wp_schedule_single_event( time(), self::CRON_HOOK );
			if ( false === $scheduled ) {
				error_log( 'SIG Cron: CRITICAL - Failed to schedule cron job completely in reschedule_cron_job()!' );
			} else {
				error_log( 'SIG Cron: Scheduled using single event in reschedule_cron_job().' );
			}
		} else {
			error_log( 'SIG Cron: Successfully scheduled cron job. Interval: ' . $interval_minutes . ' minutes.' );
		}
	}

	/**
	 * Nudge WordPress cron without generating posts inside the page request.
	 *
	 * Generation stays on the cron hook. spawn_cron() returns immediately.
	 */
	public function maybe_run_cron_on_page_load() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( ! is_admin() && ! is_front_page() ) {
			return;
		}

		$next_run = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $next_run || $next_run > time() ) {
			return;
		}

		if ( get_transient( 'sig_cron_running_lock' ) ) {
			return;
		}

		spawn_cron();
	}

	/**
	 * Adds custom cron interval.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function add_custom_cron_interval( $schedules ) {
		$options = get_option( 'sig_settings', array() );
		$interval_minutes = isset( $options['interval_between_batches'] ) ? absint( $options['interval_between_batches'] ) : 1440;
		
		if ( $interval_minutes !== 60 && $interval_minutes !== 1440 ) {
			$interval_name = 'sig_custom_' . $interval_minutes . '_minutes';
			
			if ( ! isset( $schedules[ $interval_name ] ) ) {
				$schedules[ $interval_name ] = array(
					'interval' => $interval_minutes * 60,
					'display'  => sprintf( __( 'Every %d minutes', 'sme-insights-generator' ), $interval_minutes ),
				);
			}
		}
		
		return $schedules;
	}

	/**
	 * Main cron task for content generation.
	 * 
	 * Generates posts based on configured batch settings.
	 */
	public function generate_content_task() {
		$lock_key = 'sig_cron_running_lock';
		$lock_time = 600;
		
		if ( get_transient( $lock_key ) ) {
			error_log( 'SIG Cron: Task is already running. Skipping duplicate execution.' );
			return;
		}
		
		set_transient( $lock_key, true, $lock_time );

		try {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
		
		error_log( 'SIG Cron: Task started at ' . date( 'Y-m-d H:i:s' ) );
		
		$options = get_option( 'sig_settings', array() );
		$posts_per_day = isset( $options['posts_per_day'] ) ? absint( $options['posts_per_day'] ) : 1;
		$posts_per_batch = isset( $options['posts_per_batch'] ) ? absint( $options['posts_per_batch'] ) : 1;
		$interval_between_posts = isset( $options['interval_between_posts'] ) ? absint( $options['interval_between_posts'] ) : 5;
		$interval_between_batches = isset( $options['interval_between_batches'] ) ? absint( $options['interval_between_batches'] ) : 1440;

		error_log( 'SIG Cron: Settings - posts_per_day: ' . $posts_per_day . ', posts_per_batch: ' . $posts_per_batch . ', interval: ' . $interval_between_batches . ' minutes' );

		$today_posts_count = $this->count_today_posts();
		error_log( 'SIG Cron: Posts generated today: ' . $today_posts_count );
		
		$posts_remaining = max( 0, $posts_per_day - $today_posts_count );
		$posts_to_generate = min( $posts_per_batch, $posts_remaining );

		error_log( 'SIG Cron: Posts to generate in this batch: ' . $posts_to_generate );

		if ( $posts_to_generate <= 0 ) {
			error_log( 'SIG Cron: Daily post limit reached. Skipping generation.' );
			return;
		}
		$success_count = 0;
		$error_count = 0;
		
		for ( $i = 0; $i < $posts_to_generate; $i++ ) {
			$result = SIG_Post_Creator::create_ai_post();

			if ( 'error' === $result['status'] ) {
				$error_count++;
				error_log( 'SIG Cron Error: ' . $result['message'] );
				
				if ( strpos( $result['message'], 'API' ) !== false && $i < $posts_to_generate - 1 ) {
					sleep( min( 30, $interval_between_posts * 2 ) );
				}
			} else {
				$success_count++;
				error_log( 'SIG Cron Success: ' . $result['message'] );
			}

			if ( $i < $posts_to_generate - 1 && $interval_between_posts > 0 ) {
				sleep( $interval_between_posts );
			}
		}
		
		if ( $posts_to_generate > 0 ) {
			error_log( sprintf( 'SIG Cron Batch Summary: %d posts attempted, %d succeeded, %d failed.', $posts_to_generate, $success_count, $error_count ) );
		}
		
		error_log( 'SIG Cron: Task completed at ' . date( 'Y-m-d H:i:s' ) );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Counts how many posts were generated today.
	 *
	 * @return int Number of posts generated today.
	 */
	private function count_today_posts() {
		$cache_key = 'sig_today_posts_count_' . date( 'Y-m-d' );
		$cached_count = get_transient( $cache_key );
		
		if ( false !== $cached_count ) {
			return absint( $cached_count );
		}
		
		$today_start = strtotime( 'today midnight' );
		$today_end = strtotime( 'tomorrow midnight' );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'date_query'     => array(
				array(
					'after'     => date( 'Y-m-d H:i:s', $today_start ),
					'before'    => date( 'Y-m-d H:i:s', $today_end ),
					'inclusive' => true,
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_sig_generated',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		$count = $query->found_posts;
		wp_reset_postdata();
		
		$cache_ttl = $today_end - time();
		if ( $cache_ttl > 0 ) {
			set_transient( $cache_key, $count, $cache_ttl );
		}
		
		return $count;
	}

	/**
	 * Handles the AJAX request for the "Run Now" button.
	 */
	public function handle_run_now_ajax() {
		check_ajax_referer( 'sig_run_now_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'sme-insights-generator' ) ) );
		}

		$result = SIG_Post_Creator::create_ai_post();

		if ( 'success' === $result['status'] ) {
			wp_send_json_success( array( 'message' => wp_kses_post( $result['message'] ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html( $result['message'] ) ) );
		}
	}
}
