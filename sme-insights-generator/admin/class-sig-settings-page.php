<?php
/**
 * Handles the plugin's admin settings page.
 *
 * @package SME_Insights_Generator
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIG_Settings_Page class.
 */
class SIG_Settings_Page {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	private $option_group = 'sig_settings_group';

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option_name = 'sig_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'wp_ajax_sig_reschedule_cron', array( $this, 'handle_reschedule_cron_ajax' ) );
		add_action( 'wp_ajax_sig_test_cron', array( $this, 'handle_test_cron_ajax' ) );
		add_action( 'admin_notices', array( $this, 'display_settings_notices' ) );
	}

	/**
	 * Add options page.
	 */
	public function add_plugin_page() {
		add_menu_page(
			__( 'SME Insights Generator Settings', 'sme-insights-generator' ),
				__( 'Content Generator', 'sme-insights-generator' ),
			'manage_options',
			$this->option_name,
			array( $this, 'create_admin_page' ),
			'dashicons-superhero',
			6
		);
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
					// This prints out all hidden fields required for the settings page.
					settings_fields( $this->option_group );
					do_settings_sections( $this->option_name );
					submit_button();
				?>
			</form>
				<?php $this->render_cron_status(); ?>
			<?php $this->render_run_now_button(); ?>
		</div>
		<?php
	}

	/**
	 * Register and add settings.
	 */
	public function page_init() {
		register_setting(
			$this->option_group,
			$this->option_name,
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'sig_api_settings',
			__( 'API & Model Settings', 'sme-insights-generator' ),
			array( $this, 'print_api_section_info' ),
			$this->option_name
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'sme-insights-generator' ),
			array( $this, 'openai_api_key_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_field(
			'google_api_key',
			__( 'Google Gemini API Key', 'sme-insights-generator' ),
			array( $this, 'google_api_key_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_field(
			'anthropic_api_key',
			__( 'Anthropic Claude API Key', 'sme-insights-generator' ),
			array( $this, 'anthropic_api_key_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_field(
			'ai_provider',
			__( 'How to call the model', 'sme-insights-generator' ),
			array( $this, 'ai_provider_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_field(
			'credits_endpoint',
			__( 'Credits endpoint', 'sme-insights-generator' ),
			array( $this, 'credits_endpoint_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_field(
			'ai_model',
			__( 'Language Model Selection', 'sme-insights-generator' ),
			array( $this, 'ai_model_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_field(
			'enable_auto_fallback',
			__( 'Automatic Fallback to Gemini', 'sme-insights-generator' ),
			array( $this, 'enable_auto_fallback_callback' ),
			$this->option_name,
			'sig_api_settings'
		);

		add_settings_section(
			'sig_content_settings',
			__( 'Content Generation Settings', 'sme-insights-generator' ),
			array( $this, 'print_content_section_info' ),
			$this->option_name
		);

		add_settings_field(
			'content_topic',
			__( 'Topic', 'sme-insights-generator' ),
			array( $this, 'content_topic_callback' ),
			$this->option_name,
			'sig_content_settings'
		);

		add_settings_field(
			'content_keywords',
			__( 'Keywords', 'sme-insights-generator' ),
			array( $this, 'content_keywords_callback' ),
			$this->option_name,
			'sig_content_settings'
		);

		add_settings_field(
			'prompt_template',
			__( 'Custom Prompt Template', 'sme-insights-generator' ),
			array( $this, 'prompt_template_callback' ),
			$this->option_name,
			'sig_content_settings'
		);

		add_settings_field(
			'post_category',
			__( 'Post Category Name', 'sme-insights-generator' ),
			array( $this, 'post_category_callback' ),
			$this->option_name,
			'sig_content_settings'
		);

		add_settings_field(
			'post_status',
			__( 'Default Post Status', 'sme-insights-generator' ),
			array( $this, 'post_status_callback' ),
			$this->option_name,
			'sig_content_settings'
		);

		add_settings_field(
			'featured_image_url',
			__( 'Default Featured Image URL', 'sme-insights-generator' ),
			array( $this, 'featured_image_url_callback' ),
			$this->option_name,
			'sig_content_settings'
		);

		add_settings_section(
			'sig_cron_settings',
			__( 'Scheduling Settings', 'sme-insights-generator' ),
			array( $this, 'print_cron_section_info' ),
			$this->option_name
		);

		add_settings_field(
			'posts_per_day',
			__( 'Posts to Generate Per Day', 'sme-insights-generator' ),
			array( $this, 'posts_per_day_callback' ),
			$this->option_name,
			'sig_cron_settings'
		);

		add_settings_field(
			'posts_per_batch',
			__( 'Posts Per Batch', 'sme-insights-generator' ),
			array( $this, 'posts_per_batch_callback' ),
			$this->option_name,
			'sig_cron_settings'
		);

		add_settings_field(
			'interval_between_posts',
			__( 'Interval Between Posts (seconds)', 'sme-insights-generator' ),
			array( $this, 'interval_between_posts_callback' ),
			$this->option_name,
			'sig_cron_settings'
		);

		add_settings_field(
			'interval_between_batches',
			__( 'Interval Between Batches (minutes)', 'sme-insights-generator' ),
			array( $this, 'interval_between_batches_callback' ),
			$this->option_name,
			'sig_cron_settings'
		);
	}

	/**
	 * Sanitize each setting field as needed.
	 *
	 * @param array $input Contains all settings fields as array keys.
	 * @return array The sanitized input.
	 */
	public function sanitize( $input ) {
		$defaults = array(
				'openai_api_key'           => '',
				'google_api_key'           => '',
				'anthropic_api_key'        => '',
				'ai_provider'              => 'byo',
				'credits_endpoint'         => '',
				'content_topic'            => '',
				'content_keywords'         => '',
				'ai_model'                 => 'gpt-4o',
				'prompt_template'          => "Generate a complete news article for a website targeting Small and Medium Enterprises (SMEs).\n\n**Instructions:**\n\n1. The article's tone must be professional, insightful, and practical.\n\n2. The output MUST be structured as follows:\n   - The very first line must be the article title, and nothing else.\n   - The rest of the text must be the full body of the article.\n\n3. Do not include any introductory phrases like \"Here is the article:\" or \"Title:\".\n\n4. The article should be approximately 400 words long.\n\n5. The main topic for the article is: [topic]\n\n6. Focus on one of these business categories: Finance, Marketing, Technology, Growth, or Strategy.\n\n7. Cover niche topics such as: AI in Business, E-commerce Trends, Startup Funding, Green Economy, or Remote Work.",
				'post_category'            => 'Business News',
				'post_status'              => 'draft',
				'featured_image_url'       => '',
			'posts_per_day'            => 1,
			'posts_per_batch'          => 1,
			'interval_between_posts'   => 5,
			'interval_between_batches' => 1440, // 24 hours in minutes (default: 1 post per day).
			);

		$enable_auto_fallback = isset( $input['enable_auto_fallback'] ) && '1' === $input['enable_auto_fallback'] ? '1' : '0';

		$input = wp_parse_args( $input, $defaults );
		$existing_before_keys = self::get_options();

		$sanitized_input = array();

		$sanitized_input['openai_api_key']           = $this->sanitize_secret_field( $input, $existing_before_keys, 'openai_api_key', 'clear_openai_api_key' );
			$sanitized_input['google_api_key']           = $this->sanitize_secret_field( $input, $existing_before_keys, 'google_api_key', 'clear_google_api_key' );
			$sanitized_input['anthropic_api_key']        = $this->sanitize_secret_field( $input, $existing_before_keys, 'anthropic_api_key', 'clear_anthropic_api_key' );
			$provider = isset( $input['ai_provider'] ) ? sanitize_key( $input['ai_provider'] ) : 'byo';
			$sanitized_input['ai_provider']              = ( 'credits' === $provider ) ? 'credits' : 'byo';
			$sanitized_input['credits_endpoint']         = esc_url_raw( $input['credits_endpoint'] );
			$sanitized_input['content_topic']            = sanitize_text_field( $input['content_topic'] );
			$sanitized_input['content_keywords']         = sanitize_text_field( $input['content_keywords'] );
			$sanitized_input['ai_model']                 = sanitize_text_field( $input['ai_model'] );
		$sanitized_input['enable_auto_fallback']     = $enable_auto_fallback;
			$sanitized_input['prompt_template']          = wp_kses_post( $input['prompt_template'] );
			$sanitized_input['post_category']            = sanitize_text_field( $input['post_category'] );
			$sanitized_input['post_status']              = sanitize_key( $input['post_status'] );
		if ( ! in_array( $sanitized_input['post_status'], array( 'publish', 'draft' ), true ) ) {
			$sanitized_input['post_status'] = 'draft';
		}
			$sanitized_input['featured_image_url']       = esc_url_raw( $input['featured_image_url'] );
			$sanitized_input['posts_per_day']            = absint( $input['posts_per_day'] );
			$sanitized_input['posts_per_batch']          = absint( $input['posts_per_batch'] );
			$sanitized_input['interval_between_posts']   = absint( $input['interval_between_posts'] );
			$sanitized_input['interval_between_batches'] = absint( $input['interval_between_batches'] );

		if ( $sanitized_input['posts_per_day'] < 1 ) {
			$sanitized_input['posts_per_day'] = 1;
		}
		if ( $sanitized_input['posts_per_day'] > 1000 ) {
			$sanitized_input['posts_per_day'] = 1000; // Hard limit to prevent server overload.
		}
		if ( $sanitized_input['posts_per_batch'] < 1 ) {
			$sanitized_input['posts_per_batch'] = 1;
		}
		if ( $sanitized_input['posts_per_batch'] > 20 ) {
			$sanitized_input['posts_per_batch'] = 20; // Hard limit to prevent timeouts.
		}
		if ( $sanitized_input['interval_between_posts'] < 0 ) {
			$sanitized_input['interval_between_posts'] = 5;
		}
		if ( $sanitized_input['interval_between_posts'] > 300 ) {
			$sanitized_input['interval_between_posts'] = 300; // Max 5 minutes between posts.
		}
		$batch_processing_time_seconds = $sanitized_input['posts_per_batch'] * $sanitized_input['interval_between_posts'];
		$batch_processing_time_minutes = ceil( $batch_processing_time_seconds / 60 );
		$minimum_safe_interval = max( 2, $batch_processing_time_minutes + 1 ); // At least 1 minute buffer, minimum 2 minutes
		
		$original_interval = absint( $input['interval_between_batches'] );
		if ( $sanitized_input['interval_between_batches'] < $minimum_safe_interval ) {
			$sanitized_input['interval_between_batches'] = $minimum_safe_interval; // Auto-adjust to safe minimum.
			
			if ( $original_interval !== $minimum_safe_interval ) {
				set_transient( 'sig_interval_adjusted_notice', array(
					'original' => $original_interval,
					'adjusted' => $minimum_safe_interval,
					'posts_per_batch' => $sanitized_input['posts_per_batch'],
					'interval_between_posts' => $sanitized_input['interval_between_posts'],
				), 30 );
			}
		}
		if ( $sanitized_input['interval_between_batches'] > 10080 ) {
			$sanitized_input['interval_between_batches'] = 10080; // Max 7 days.
		}
		
		// Validate that posts_per_batch doesn't exceed posts_per_day.
		if ( $sanitized_input['posts_per_batch'] > $sanitized_input['posts_per_day'] ) {
			$sanitized_input['posts_per_batch'] = $sanitized_input['posts_per_day'];
		}

		$existing_options = self::get_options();
			$scheduling_changed = false;
			if ( isset( $existing_options['posts_per_day'] ) && $existing_options['posts_per_day'] !== $sanitized_input['posts_per_day'] ) {
				$scheduling_changed = true;
			}
			if ( isset( $existing_options['posts_per_batch'] ) && $existing_options['posts_per_batch'] !== $sanitized_input['posts_per_batch'] ) {
				$scheduling_changed = true;
			}
			if ( isset( $existing_options['interval_between_batches'] ) && $existing_options['interval_between_batches'] !== $sanitized_input['interval_between_batches'] ) {
				$scheduling_changed = true;
			}

		if ( $scheduling_changed ) {
		SIG_Cron_Manager::get_instance()->reschedule_cron_job( $sanitized_input );
		spawn_cron();
		}

		return $sanitized_input;
	}

	/**
	 * Get the settings option array.
	 *
	 * @return array
	 */
	public static function get_options() {
		return get_option( 'sig_settings', array() );
	}

	/**
	 * Keep a saved secret when the password field is submitted empty.
	 *
	 * @param array  $input Submitted settings.
	 * @param array  $existing Stored settings.
	 * @param string $field Secret field name.
	 * @param string $clear_field Checkbox that removes the stored secret.
	 * @return string
	 */
	private function sanitize_secret_field( $input, $existing, $field, $clear_field ) {
		if ( ! empty( $input[ $clear_field ] ) ) {
			return '';
		}

		$incoming = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		if ( '' === $incoming && ! empty( $existing[ $field ] ) ) {
			return (string) $existing[ $field ];
		}

		return $incoming;
	}


	/**
	 * Print the API section text.
	 */
	public function print_api_section_info() {
			$options = self::get_options();
			$has_openai_key = ! empty( $options['openai_api_key'] ?? '' );
			$has_gemini_key = ! empty( $options['google_api_key'] ?? '' );
			$has_claude_key = ! empty( $options['anthropic_api_key'] ?? '' );
			$enable_fallback = isset( $options['enable_auto_fallback'] ) ? $options['enable_auto_fallback'] : '1';
			$ai_model = isset( $options['ai_model'] ) ? $options['ai_model'] : 'gpt-4o';
			
			echo esc_html__( 'Enter your API keys and select the language model you wish to use for content generation.', 'sme-insights-generator' );
			
			// Display current active model.
			$model_display = $this->format_model_name( $ai_model );
			echo '<p class="description" style="color: #2271b1; margin-top: 10px; font-weight: bold;"><strong>📌 Currently Active Model:</strong> <span style="color: #00a32a;">' . esc_html( $model_display ) . '</span></p>';
			
			// Show configured API keys status.
			$configured_apis = array();
			if ( $has_openai_key ) {
				$configured_apis[] = 'OpenAI';
			}
			if ( $has_gemini_key ) {
				$configured_apis[] = 'Gemini';
			}
			if ( $has_claude_key ) {
				$configured_apis[] = 'Claude';
			}
			
			if ( ! empty( $configured_apis ) ) {
				echo '<p class="description" style="color: #00a32a; margin-top: 5px;"><strong>✓ Configured APIs:</strong> ' . esc_html( implode( ', ', $configured_apis ) ) . '</p>';
			}
			
			if ( $has_openai_key && $has_gemini_key && '1' === $enable_fallback ) {
				echo '<p class="description" style="color: #00a32a; margin-top: 10px;"><strong>✓ Automatic Fallback Enabled:</strong> If OpenAI quota is exceeded (error 429), the plugin will automatically switch to Gemini to continue generating content.</p>';
			} elseif ( $has_openai_key && $has_gemini_key && '0' === $enable_fallback ) {
				echo '<p class="description" style="color: #d63638; margin-top: 10px;"><strong>⚠ Automatic Fallback Disabled:</strong> When OpenAI quota is exceeded, content generation will fail instead of switching to Gemini.</p>';
			} elseif ( $has_openai_key && ! $has_gemini_key ) {
				echo '<p class="description" style="color: #d63638; margin-top: 10px;"><strong>⚠ Tip:</strong> Adding a Google Gemini API key enables automatic fallback if OpenAI quota is exceeded, ensuring uninterrupted content generation.</p>';
			}
		}

		/**
		 * Formats model name for display.
		 *
		 * @param string $model The model identifier.
		 * @return string Formatted model name.
		 */
		private function format_model_name( $model ) {
			$model_names = array(
				'gpt-4.1'                    => 'GPT-4.1',
				'gpt-4.1-mini'               => 'GPT-4.1 Mini',
				'gpt-4o'                     => 'GPT-4o',
				'gpt-4o-mini'                => 'GPT-4o Mini',
				'gpt-4-turbo'                => 'GPT-4 Turbo',
				'gpt-4'                      => 'GPT-4',
				'gpt-3.5-turbo'              => 'GPT-3.5 Turbo',
				'claude-sonnet-4-5'          => 'Claude Sonnet 4.5',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
				'claude-3-opus-20240229'     => 'Claude 3 Opus',
				'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku',
				'gemini-2.5-flash'           => 'Gemini 2.5 Flash',
				'gemini-2.5-pro'             => 'Gemini 2.5 Pro',
				'gemini-2.0-flash'           => 'Gemini 2.0 Flash',
				'gemini-1.5-pro-latest'      => 'Gemini 1.5 Pro',
				'gemini-1.5-flash-latest'    => 'Gemini 1.5 Flash',
				'gemini-pro'                 => 'Gemini Pro',
			);

			return isset( $model_names[ $model ] ) ? $model_names[ $model ] : $model;
	}

	/**
	 * Print the Content section text.
	 */
	public function print_content_section_info() {
		echo 'Define the prompt template and the destination for the generated posts.';
	}

	/**
	 * Print the Cron section text.
	 */
	public function print_cron_section_info() {
		echo 'Set the number of posts to be generated automatically each day.';
	}


	/**
	 * OpenAI API Key field callback.
	 */
	public function openai_api_key_callback() {
		$this->render_secret_field( 'openai_api_key', 'clear_openai_api_key', 'sk-...' );
		echo '<p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.</p>';
	}

	/**
		 * Google Gemini API Key field callback.
	 */
	public function google_api_key_callback() {
		$this->render_secret_field( 'google_api_key', 'clear_google_api_key', 'AIza...' );
		echo '<p class="description">Get your API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</p>';
		}

		/**
		 * Anthropic Claude API Key field callback.
		 */
		public function anthropic_api_key_callback() {
			$this->render_secret_field( 'anthropic_api_key', 'clear_anthropic_api_key', 'sk-ant-...' );
			echo '<p class="description">Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>.</p>';
		}

		/**
		 * Password field that does not print the stored secret.
		 *
		 * @param string $field Option key.
		 * @param string $clear_field Clear checkbox key.
		 * @param string $placeholder Empty-state placeholder.
		 */
		private function render_secret_field( $field, $clear_field, $placeholder ) {
			$options = self::get_options();
			$stored  = isset( $options[ $field ] ) ? (string) $options[ $field ] : '';
			$hint    = ( '' === $stored ) ? $placeholder : __( 'Saved. Enter a new key to replace it.', 'sme-insights-generator' );

			printf(
				'<input type="password" id="%1$s" name="%2$s[%1$s]" value="" class="regular-text" placeholder="%3$s" autocomplete="new-password" />',
				esc_attr( $field ),
				esc_attr( $this->option_name ),
				esc_attr( $hint )
			);

			if ( '' !== $stored ) {
				$tail = substr( $stored, -4 );
				echo '<p class="description">' . esc_html( sprintf( __( 'A key is saved and ends with %s. Leave the field blank to keep it.', 'sme-insights-generator' ), $tail ) ) . '</p>';
				printf(
					'<label><input type="checkbox" name="%1$s[%2$s]" value="1" /> %3$s</label>',
					esc_attr( $this->option_name ),
					esc_attr( $clear_field ),
					esc_html__( 'Clear saved key', 'sme-insights-generator' )
				);
			}
		}

		/**
		 * Provider selector. Own keys stay fully usable.
		 */
		public function ai_provider_callback() {
			$options = self::get_options();
			$value   = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'byo';
			$choices = array(
				'byo'     => __( 'Your own API keys', 'sme-insights-generator' ),
				'credits' => __( 'Credits endpoint (not billed inside this plugin)', 'sme-insights-generator' ),
			);

			printf( '<select id="ai_provider" name="%s[ai_provider]">', esc_attr( $this->option_name ) );
			foreach ( $choices as $key => $label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $key ),
					selected( $value, $key, false ),
					esc_html( $label )
				);
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Your own keys are the free path. Credits does nothing until an endpoint is saved, and it does not turn off the keys above.', 'sme-insights-generator' ) . '</p>';
		}

		/**
		 * Future subscription endpoint. Empty by default.
		 */
		public function credits_endpoint_callback() {
			$options = self::get_options();
			$value   = isset( $options['credits_endpoint'] ) ? $options['credits_endpoint'] : '';
			printf(
				'<input type="url" id="credits_endpoint" name="%s[credits_endpoint]" value="%s" class="regular-text" placeholder="https://example.com/credits" />',
				esc_attr( $this->option_name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'Leave empty. Stripe is not connected. Used only when the provider above is set to credits.', 'sme-insights-generator' ) . '</p>';
		}

		/**
		 * Language Model Selection field callback.
	 */
	public function ai_model_callback() {
		$options = self::get_options();
			$value   = isset( $options['ai_model'] ) ? $options['ai_model'] : 'gpt-4o';
		$models  = array(
				'gpt-4.1'                    => 'GPT-4.1',
				'gpt-4.1-mini'               => 'GPT-4.1 Mini',
				'gpt-4o'                     => 'GPT-4o',
				'gpt-4o-mini'                => 'GPT-4o Mini',
				'gpt-4-turbo'                => 'GPT-4 Turbo (legacy)',
				'gpt-4'                      => 'GPT-4 (legacy)',
				'gpt-3.5-turbo'              => 'GPT-3.5 Turbo (legacy)',
				'claude-sonnet-4-5'          => 'Claude Sonnet 4.5',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (legacy)',
				'claude-3-opus-20240229'     => 'Claude 3 Opus (legacy)',
				'claude-3-sonnet-20240229'   => 'Claude 3 Sonnet (legacy)',
				'claude-3-haiku-20240307'    => 'Claude 3 Haiku (legacy)',
				'gemini-2.5-flash'           => 'Gemini 2.5 Flash',
				'gemini-2.5-pro'             => 'Gemini 2.5 Pro',
				'gemini-2.0-flash'           => 'Gemini 2.0 Flash',
				'gemini-1.5-pro-latest'      => 'Gemini 1.5 Pro (legacy)',
				'gemini-1.5-flash-latest'    => 'Gemini 1.5 Flash (legacy)',
				'gemini-pro'                 => 'Gemini Pro (legacy)',
		);

		printf( '<select id="ai_model" name="%s[ai_model]">', esc_attr( $this->option_name ) );
		foreach ( $models as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

		/**
		 * Automatic Fallback to Gemini field callback.
		 */
		public function enable_auto_fallback_callback() {
			$options = self::get_options();
			$value   = isset( $options['enable_auto_fallback'] ) ? $options['enable_auto_fallback'] : '1';
			$has_openai_key = ! empty( $options['openai_api_key'] ?? '' );
			$has_gemini_key = ! empty( $options['google_api_key'] ?? '' );
			
			printf(
				'<label><input type="checkbox" id="enable_auto_fallback" name="%s[enable_auto_fallback]" value="1" %s /> %s</label>',
				esc_attr( $this->option_name ),
				checked( $value, '1', false ),
				esc_html__( 'Enable automatic fallback to Gemini when OpenAI quota is exceeded', 'sme-insights-generator' )
			);
			
			echo '<p class="description">';
			if ( $has_openai_key && $has_gemini_key ) {
				if ( '1' === $value ) {
					echo '<span style="color: #00a32a;"><strong>✓ Enabled:</strong> If OpenAI returns a quota/rate limit error (429), the plugin will automatically switch to Gemini to continue generating content.</span><br>';
					echo '<span style="color: #2271b1; margin-top: 5px; display: inline-block;"><strong>Fallback Models (in order):</strong></span><br>';
					echo '<span style="color: #2271b1;">1. Gemini 2.5 Flash</span><br>';
					echo '<span style="color: #2271b1;">2. Gemini 2.5 Pro</span><br>';
					echo '<span style="color: #2271b1;">3. Gemini 2.0 Flash, then older Gemini models</span><br>';
					echo '<span style="color: #646970; font-size: 12px; margin-top: 5px; display: inline-block;">The plugin will try each model in order until one succeeds.</span>';
				} else {
					echo '<span style="color: #d63638;"><strong>⚠ Disabled:</strong> When OpenAI quota is exceeded, content generation will fail instead of switching to Gemini.</span>';
				}
			} elseif ( $has_openai_key && ! $has_gemini_key ) {
				echo '<span style="color: #d63638;"><strong>⚠ Note:</strong> This feature requires a Google Gemini API key to be configured.</span>';
			} else {
				echo '<span style="color: #d63638;"><strong>⚠ Note:</strong> This feature requires both OpenAI and Google Gemini API keys to be configured.</span>';
			}
			echo '</p>';
	}

	/**
	 * Optional topic. Empty keeps the SME topic lists.
	 */
	public function content_topic_callback() {
		$options = self::get_options();
		$value   = isset( $options['content_topic'] ) ? $options['content_topic'] : '';
		printf(
			'<input type="text" id="content_topic" name="%s[content_topic]" value="%s" class="regular-text" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Leave empty to keep rotating the original SME topics.', 'sme-insights-generator' ) . '</p>';
	}

	/**
	 * Optional keywords appended to a custom topic.
	 */
	public function content_keywords_callback() {
		$options = self::get_options();
		$value   = isset( $options['content_keywords'] ) ? $options['content_keywords'] : '';
		printf(
			'<input type="text" id="content_keywords" name="%s[content_keywords]" value="%s" class="regular-text" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Used with Topic. If both are empty, SME categories and niches stay in use. The [topic] tag in the prompt still receives the final subject.', 'sme-insights-generator' ) . '</p>';
	}

	/**
	 * Prompt Template field callback.
	 */
	public function prompt_template_callback() {
		$options = self::get_options();
		$default_prompt = "Generate a complete news article for a website targeting Small and Medium Enterprises (SMEs).\n\n**Instructions:**\n\n1. The article's tone must be professional, insightful, and practical.\n\n2. The output MUST be structured as follows:\n   - The very first line must be the article title, and nothing else.\n   - The rest of the text must be the full body of the article.\n\n3. Do not include any introductory phrases like \"Here is the article:\" or \"Title:\".\n\n4. The article should be approximately 400 words long.\n\n5. The main topic for the article is: [topic]\n\n6. Focus on one of these business categories: Finance, Marketing, Technology, Growth, or Strategy.\n\n7. Cover niche topics such as: AI in Business, E-commerce Trends, Startup Funding, Green Economy, or Remote Work.";
		$value   = isset( $options['prompt_template'] ) ? $options['prompt_template'] : $default_prompt;

		printf(
			'<textarea id="prompt_template" name="%s[prompt_template]" rows="10" cols="80" class="large-text code">%s</textarea>',
			esc_attr( $this->option_name ),
			esc_textarea( $value )
		);
		echo '<p class="description">Use the tag <code>[topic]</code> to insert the subject of the news. The default topic will be "Latest Business News".<br>The template includes instructions for focusing on SME business categories (Finance, Marketing, Technology, Growth, Strategy) and niche topics (AI in Business, E-commerce Trends, Startup Funding, Green Economy, Remote Work).</p>';
	}

	/**
	 * Post Category Name field callback.
	 */
	public function post_category_callback() {
		$options = self::get_options();
			$value   = isset( $options['post_category'] ) ? $options['post_category'] : 'Business News';
		printf(
			'<input type="text" id="post_category" name="%s[post_category]" value="%s" class="regular-text" placeholder="e.g., News, Technology" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		echo '<p class="description">The name of the category to assign the generated posts to. If the category does not exist, it will be created.</p>';
	}

	/**
	 * Default Post Status field callback.
	 */
	public function post_status_callback() {
		$options = self::get_options();
		$value   = isset( $options['post_status'] ) ? $options['post_status'] : 'draft';
		$statuses = array(
			'publish' => 'Published',
			'draft'   => 'Draft',
		);

		printf( '<select id="post_status" name="%s[post_status]">', esc_attr( $this->option_name ) );
		foreach ( $statuses as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Default Featured Image URL field callback.
	 */
	public function featured_image_url_callback() {
		$options = self::get_options();
		$value   = isset( $options['featured_image_url'] ) ? $options['featured_image_url'] : '';
		printf(
			'<input type="url" id="featured_image_url" name="%s[featured_image_url]" value="%s" class="regular-text" placeholder="https://example.com/image.jpg" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		echo '<p class="description">Optional. If left empty, a hardcoded fallback image will be used.</p>';
	}

	/**
	 * Posts to Generate Per Day field callback.
	 */
	public function posts_per_day_callback() {
		$options = self::get_options();
		$value   = isset( $options['posts_per_day'] ) ? $options['posts_per_day'] : 1;
		$ai_model = isset( $options['ai_model'] ) ? $options['ai_model'] : 'gpt-4o';
		$has_gemini_key = ! empty( $options['google_api_key'] ?? '' );
		
		printf(
			'<input type="number" id="posts_per_day" name="%s[posts_per_day]" value="%s" class="small-text" min="1" max="1000" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		echo '<p class="description">Total number of posts to generate per day. Maximum recommended: 100-200 posts per day to avoid API rate limits.</p>';
		
		if ( $value > 50 && strpos( $ai_model, 'gpt' ) !== false ) {
			if ( $has_gemini_key ) {
				echo '<p class="description" style="color: #d63638;"><strong>⚠ Warning:</strong> Generating ' . esc_html( $value ) . ' posts/day with OpenAI may exceed your API quota. The plugin will automatically switch to Gemini if OpenAI quota is exceeded (Gemini API key is configured).</p>';
			} else {
				echo '<p class="description" style="color: #d63638;"><strong>⚠ Warning:</strong> Generating ' . esc_html( $value ) . ' posts/day with OpenAI may exceed your API quota. Consider adding a Google Gemini API key for automatic fallback, or reduce the number of posts per day.</p>';
			}
		}
		
		$today_count = $this->get_today_posts_count();
		if ( $today_count > 0 ) {
			echo '<p class="description" style="color: #2271b1;"><strong>Today\'s generated posts: ' . esc_html( $today_count ) . '</strong></p>';
		}
	}

	/**
	 * Posts Per Batch field callback.
	 */
	public function posts_per_batch_callback() {
		$options = self::get_options();
		$value   = isset( $options['posts_per_batch'] ) ? $options['posts_per_batch'] : 1;
		$posts_per_day = isset( $options['posts_per_day'] ) ? absint( $options['posts_per_day'] ) : 1;
		
		printf(
			'<input type="number" id="posts_per_batch" name="%s[posts_per_batch]" value="%s" class="small-text" min="1" max="20" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		echo '<p class="description">Number of posts to generate in each batch. For example, if set to 5, the plugin will generate 5 posts at a time. Maximum: 20 posts per batch (recommended: 1-10 for better performance).</p>';
		
		if ( $value > $posts_per_day ) {
			echo '<p class="description" style="color: #d63638;"><strong>⚠ Warning:</strong> Batch size (' . esc_html( $value ) . ') exceeds daily posts (' . esc_html( $posts_per_day ) . '). It will be automatically adjusted.</p>';
		} elseif ( $value > 10 && $posts_per_day > 20 ) {
			echo '<p class="description" style="color: #d63638;"><strong>⚠ Note:</strong> Large batch sizes may cause timeouts. Consider using smaller batches (1-10) for better reliability.</p>';
		}
	}

		/**
		 * Interval Between Posts field callback.
		 */
		public function interval_between_posts_callback() {
			$options = self::get_options();
			$value   = isset( $options['interval_between_posts'] ) ? $options['interval_between_posts'] : 5;
			printf(
				'<input type="number" id="interval_between_posts" name="%s[interval_between_posts]" value="%s" class="small-text" min="0" step="1" />',
				esc_attr( $this->option_name ),
				esc_attr( $value )
			);
			echo '<p class="description">Time in seconds to wait between each post generation within a batch. Recommended: 5-10 seconds to avoid API rate limits.</p>';
		}

		/**
		 * Interval Between Batches field callback.
		 */
	public function interval_between_batches_callback() {
		$options = self::get_options();
		$value   = isset( $options['interval_between_batches'] ) ? $options['interval_between_batches'] : 1440;
		$posts_per_day = isset( $options['posts_per_day'] ) ? absint( $options['posts_per_day'] ) : 1;
		$posts_per_batch = isset( $options['posts_per_batch'] ) ? absint( $options['posts_per_batch'] ) : 1;
		
		printf(
			'<input type="number" id="interval_between_batches" name="%s[interval_between_batches]" value="%s" class="small-text" min="1" step="1" />',
			esc_attr( $this->option_name ),
			esc_attr( $value )
		);
		
		if ( $posts_per_day > 0 && $posts_per_batch > 0 ) {
			$batches_needed = ceil( $posts_per_day / $posts_per_batch );
			$suggested_interval = $batches_needed > 0 ? floor( 1440 / $batches_needed ) : 1440;
			$suggested_interval = max( 1, min( $suggested_interval, 1440 ) ); // Between 1 and 1440 minutes.
			
			$options = self::get_options();
			$interval_between_posts = isset( $options['interval_between_posts'] ) ? absint( $options['interval_between_posts'] ) : 5;
			$batch_processing_time_seconds = $posts_per_batch * $interval_between_posts;
			$batch_processing_time_minutes = ceil( $batch_processing_time_seconds / 60 );
			$minimum_safe_interval = max( 2, $batch_processing_time_minutes + 1 ); // At least 1 minute buffer
			
			echo '<p class="description">Time in minutes to wait between batches. Default: 1440 minutes (24 hours = 1 post per day).</p>';
			
			if ( $value < $minimum_safe_interval ) {
				echo '<p class="description" style="color: #d63638;"><strong>⚠ Warning:</strong> Interval (' . esc_html( $value ) . ' minutes) is too short. Each batch of ' . esc_html( $posts_per_batch ) . ' posts will take approximately ' . esc_html( $batch_processing_time_minutes ) . ' minutes to process. Recommended minimum: ' . esc_html( $minimum_safe_interval ) . ' minutes to prevent server overload.</p>';
			}
			
			if ( $posts_per_day > $posts_per_batch && $suggested_interval < 1440 ) {
				echo '<p class="description" style="color: #2271b1;"><strong>💡 Suggested interval for ' . esc_html( $posts_per_day ) . ' posts/day: ' . esc_html( $suggested_interval ) . ' minutes</strong> (This will distribute ' . esc_html( $batches_needed ) . ' batches evenly throughout the day)</p>';
			}
		} else {
			echo '<p class="description">Time in minutes to wait between batches. Default: 1440 minutes (24 hours = 1 post per day). For example, if set to 60, a new batch will start every hour. Recommended: 30-60 minutes for higher volume generation.</p>';
		}
	}

	/**
	 * Gets the count of posts generated today.
	 *
	 * @return int Number of posts generated today.
	 */
	private function get_today_posts_count() {
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
		return $count;
	}

	/**
	 * Handles the AJAX request for rescheduling the cron job.
	 */
	public function handle_reschedule_cron_ajax() {
		check_ajax_referer( 'sig_reschedule_cron_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'sme-insights-generator' ) ) );
		}

		$options = self::get_options();
		SIG_Cron_Manager::get_instance()->reschedule_cron_job( $options );

		$next_run = wp_next_scheduled( 'sig_generate_content_event' );
		if ( $next_run ) {
			$next_run_formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run );
			wp_send_json_success( array( 
				'message' => __( 'Cron job rescheduled successfully. Next run: ', 'sme-insights-generator' ) . $next_run_formatted 
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to schedule cron job. Please check your settings.', 'sme-insights-generator' ) ) );
		}
	}

	/**
	 * Handles the AJAX request for testing the cron job.
	 */
	public function handle_test_cron_ajax() {
		check_ajax_referer( 'sig_test_cron_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'sme-insights-generator' ) ) );
		}

		// Manually trigger the cron task.
		$cron_manager = SIG_Cron_Manager::get_instance();
		$cron_manager->generate_content_task();
		
		wp_send_json_success( array( 
			'message' => __( 'Cron job test completed. Check the logs and generated posts.', 'sme-insights-generator' )
		) );
	}

	/**
	 * Render cron status and controls.
	 */
	private function render_cron_status() {
		$cron_hook = 'sig_generate_content_event';
		$next_run = wp_next_scheduled( $cron_hook );
		$options = self::get_options();
		$interval_minutes = isset( $options['interval_between_batches'] ) ? absint( $options['interval_between_batches'] ) : 1440;
		
		?>
		<hr>
		<h2><?php esc_html_e( 'Cron Job Status', 'sme-insights-generator' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'sme-insights-generator' ); ?></th>
					<td>
						<?php if ( $next_run ) : ?>
							<span style="color: #00a32a; font-weight: bold;">✓ <?php esc_html_e( 'Scheduled', 'sme-insights-generator' ); ?></span>
						<?php else : ?>
							<span style="color: #d63638; font-weight: bold;">✗ <?php esc_html_e( 'Not Scheduled', 'sme-insights-generator' ); ?></span>
							<p class="description" style="color: #d63638;">
								<strong><?php esc_html_e( 'Warning:', 'sme-insights-generator' ); ?></strong> 
								<?php esc_html_e( 'The cron job is not scheduled. Click "Reschedule Cron Job" below to activate automatic generation.', 'sme-insights-generator' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $next_run ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Next Run', 'sme-insights-generator' ); ?></th>
					<td>
						<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ); ?></strong>
						<p class="description">
							<?php 
							$time_until = $next_run - time();
							if ( $time_until > 0 ) {
								$hours = floor( $time_until / 3600 );
								$minutes = floor( ( $time_until % 3600 ) / 60 );
								printf( 
									esc_html__( 'In approximately %d hours and %d minutes', 'sme-insights-generator' ), 
									$hours, 
									$minutes 
								);
							} else {
								esc_html_e( 'Overdue - should run soon', 'sme-insights-generator' );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Interval', 'sme-insights-generator' ); ?></th>
					<td>
						<?php 
						if ( $interval_minutes >= 1440 ) {
							$days = $interval_minutes / 1440;
							printf( esc_html__( 'Every %.1f day(s)', 'sme-insights-generator' ), $days );
						} elseif ( $interval_minutes >= 60 ) {
							$hours = $interval_minutes / 60;
							printf( esc_html__( 'Every %.1f hour(s)', 'sme-insights-generator' ), $hours );
						} else {
							printf( esc_html__( 'Every %d minute(s)', 'sme-insights-generator' ), $interval_minutes );
						}
						?>
						<?php if ( $interval_minutes < 5 ) : ?>
							<p class="description" style="color: #d63638;">
								<strong>⚠ Warning:</strong> Intervals less than 5 minutes may not work reliably with WordPress cron. Consider using a real server cron job for such short intervals.
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		
		<?php
		$reschedule_nonce = wp_create_nonce( 'sig_reschedule_cron_nonce' );
		?>
		<p>
			<button type="button" id="sig-reschedule-cron" class="button button-secondary" data-nonce="<?php echo esc_attr( $reschedule_nonce ); ?>">
				<?php esc_html_e( 'Reschedule Cron Job', 'sme-insights-generator' ); ?>
			</button>
			<button type="button" id="sig-test-cron" class="button button-secondary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'sig_test_cron_nonce' ) ); ?>" style="margin-left: 10px;">
				<?php esc_html_e( 'Test Cron Job Now', 'sme-insights-generator' ); ?>
			</button>
			<span id="sig-reschedule-message" style="margin-left: 10px;"></span>
		</p>
		
		<p class="description">
			<strong><?php esc_html_e( 'Important Note:', 'sme-insights-generator' ); ?></strong> 
			<?php esc_html_e( 'WordPress cron jobs are "pseudo-cron" - they only run when someone visits your website. If your site has low traffic or you need precise timing, you should set up a real server cron job. Click "Test Cron Job Now" to manually trigger the cron task for testing.', 'sme-insights-generator' ); ?>
		</p>
		
		<?php if ( $interval_minutes < 5 ) : ?>
		<div class="notice notice-warning inline" style="margin-top: 15px; padding: 10px;">
			<p>
				<strong><?php esc_html_e( '⚠ Low Interval Warning:', 'sme-insights-generator' ); ?></strong>
				<?php esc_html_e( 'Your interval is set to less than 5 minutes. WordPress cron may not work reliably at this frequency. For intervals less than 5 minutes, you MUST set up a real server cron job. See instructions below.', 'sme-insights-generator' ); ?>
			</p>
		</div>
		<?php endif; ?>
		
		<details style="margin-top: 15px;">
			<summary style="cursor: pointer; font-weight: bold; color: #2271b1;"><?php esc_html_e( '📋 How to Set Up Real Server Cron Job (Click to expand)', 'sme-insights-generator' ); ?></summary>
			<div style="margin-top: 10px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
				<p><strong><?php esc_html_e( 'Option 1: Using cPanel Cron Jobs', 'sme-insights-generator' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'Log in to your cPanel', 'sme-insights-generator' ); ?></li>
					<li><?php esc_html_e( 'Go to "Cron Jobs" or "Advanced" → "Cron Jobs"', 'sme-insights-generator' ); ?></li>
					<li><?php esc_html_e( 'Add a new cron job with these settings:', 'sme-insights-generator' ); ?></li>
				</ol>
				<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;"><?php 
				$cron_interval = max( 1, $interval_minutes );
				$cron_url = site_url( 'wp-cron.php?doing_wp_cron' );
				echo esc_html( "*/{$cron_interval} * * * * wget -q -O - {$cron_url} >/dev/null 2>&1" );
				?></pre>
				
				<p style="margin-top: 15px;"><strong><?php esc_html_e( 'Option 2: Using SSH/Command Line', 'sme-insights-generator' ); ?></strong></p>
				<ol>
					<li><?php esc_html_e( 'SSH into your server', 'sme-insights-generator' ); ?></li>
					<li><?php esc_html_e( 'Run: crontab -e', 'sme-insights-generator' ); ?></li>
					<li><?php esc_html_e( 'Add this line (the URL below is automatically set to your website):', 'sme-insights-generator' ); ?></li>
				</ol>
				<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;"><?php 
				$cron_url = site_url( 'wp-cron.php?doing_wp_cron' );
				echo esc_html( "*/{$cron_interval} * * * * wget -q -O - {$cron_url} >/dev/null 2>&1" );
				?></pre>
				
				<p style="margin-top: 15px; color: #d63638;">
					<strong><?php esc_html_e( 'Important:', 'sme-insights-generator' ); ?></strong> 
					<?php esc_html_e( 'The URL in the command above is automatically set to your website. If you need to change the interval, replace */X with your desired minutes. For example, */5 means every 5 minutes, */60 means every hour.', 'sme-insights-generator' ); ?>
				</p>
				
				<p style="margin-top: 10px; color: #2271b1;">
					<strong><?php esc_html_e( 'Note:', 'sme-insights-generator' ); ?></strong> 
					<?php esc_html_e( 'If wget is not available on your server, you can use curl instead:', 'sme-insights-generator' ); ?>
				</p>
				<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto;"><?php 
				$cron_url = site_url( 'wp-cron.php?doing_wp_cron' );
				echo esc_html( "*/{$cron_interval} * * * * curl -s {$cron_url} >/dev/null 2>&1" );
				?></pre>
			</div>
		</details>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#sig-reschedule-cron').on('click', function() {
				var button = $(this);
				var message = $('#sig-reschedule-message');
				button.prop('disabled', true).text('<?php esc_html_e( 'Rescheduling...', 'sme-insights-generator' ); ?>');
				message.html('');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'sig_reschedule_cron',
						nonce: button.data('nonce')
					},
					success: function(response) {
						if (response.success) {
							message.html('<span style="color: #00a32a;">✓ ' + response.data.message + '</span>');
							setTimeout(function() {
								location.reload();
							}, 1500);
						} else {
							message.html('<span style="color: #d63638;">✗ ' + response.data.message + '</span>');
							button.prop('disabled', false).text('<?php esc_html_e( 'Reschedule Cron Job', 'sme-insights-generator' ); ?>');
						}
					},
					error: function() {
						message.html('<span style="color: #d63638;">✗ <?php esc_html_e( 'An error occurred. Please try again.', 'sme-insights-generator' ); ?></span>');
						button.prop('disabled', false).text('<?php esc_html_e( 'Reschedule Cron Job', 'sme-insights-generator' ); ?>');
					}
				});
			});
			
			$('#sig-test-cron').on('click', function() {
				var button = $(this);
				var message = $('#sig-reschedule-message');
				button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'sme-insights-generator' ); ?>');
				message.html('<span style="color: #2271b1;">⏳ <?php esc_html_e( 'Running cron job test... This may take a moment.', 'sme-insights-generator' ); ?></span>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'sig_test_cron',
						nonce: button.data('nonce')
					},
					timeout: 120000, // 2 minutes timeout
					success: function(response) {
						if (response.success) {
							message.html('<span style="color: #00a32a;">✓ ' + response.data.message + '</span>');
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							message.html('<span style="color: #d63638;">✗ ' + response.data.message + '</span>');
							button.prop('disabled', false).text('<?php esc_html_e( 'Test Cron Job Now', 'sme-insights-generator' ); ?>');
						}
					},
					error: function() {
						message.html('<span style="color: #d63638;">✗ <?php esc_html_e( 'An error occurred. Please check the error logs.', 'sme-insights-generator' ); ?></span>');
						button.prop('disabled', false).text('<?php esc_html_e( 'Test Cron Job Now', 'sme-insights-generator' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the "Run Now" button.
	 */
	private function render_run_now_button() {
		$options = self::get_options();
		$nonce   = wp_create_nonce( 'sig_run_now_nonce' );
		$url     = admin_url( 'admin-ajax.php' );
		?>
		<hr>
		<h2><?php esc_html_e( 'Manual Run', 'sme-insights-generator' ); ?></h2>
		<p><?php esc_html_e( 'Click the button below to immediately run the content generation process once.', 'sme-insights-generator' ); ?></p>
		<button id="sig-run-now-button" class="button button-secondary">
			<?php esc_html_e( 'Run Content Generation Now', 'sme-insights-generator' ); ?>
		</button>
		<p id="sig-run-now-status" style="margin-top: 10px;"></p>
		<script>
			jQuery(document).ready(function($) {
				$('#sig-run-now-button').on('click', function(e) {
					e.preventDefault();
					var button = $(this);
					var status = $('#sig-run-now-status');
					
					button.prop('disabled', true).text('<?php esc_html_e( 'Running...', 'sme-insights-generator' ); ?>');
					status.html('<span style="color: orange;"><?php esc_html_e( 'Starting generation process. Please wait...', 'sme-insights-generator' ); ?></span>');

					$.post(ajaxurl, {
						action: 'sig_run_now',
						nonce: '<?php echo esc_attr( $nonce ); ?>'
					}, function(response) {
						button.prop('disabled', false).text('<?php esc_html_e( 'Run Content Generation Now', 'sme-insights-generator' ); ?>');
						if (response.success) {
							status.html('<span style="color: green;">' + response.data.message + '</span>');
						} else {
							status.html('<span style="color: red;">' + response.data.message + '</span>');
						}
					}).fail(function() {
						button.prop('disabled', false).text('<?php esc_html_e( 'Run Content Generation Now', 'sme-insights-generator' ); ?>');
						status.html('<span style="color: red;"><?php esc_html_e( 'An unknown error occurred during the AJAX request.', 'sme-insights-generator' ); ?></span>');
					});
				});
			});
		</script>
		<?php
		}

	/**
	 * Display admin notices after settings are saved.
	 */
	public function display_settings_notices() {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'sig_settings' ) === false ) {
			return;
		}

		$notice = get_transient( 'sig_interval_adjusted_notice' );
		if ( $notice ) {
			delete_transient( 'sig_interval_adjusted_notice' );
			
			$batch_time_minutes = ceil( ( $notice['posts_per_batch'] * $notice['interval_between_posts'] ) / 60 );
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Settings Adjusted:', 'sme-insights-generator' ); ?></strong>
					<?php
					printf(
						esc_html__( 'The "Interval Between Batches" was automatically adjusted from %d minutes to %d minutes. This is because each batch of %d posts takes approximately %d minutes to process, and the interval must be at least %d minutes to prevent server overload.', 'sme-insights-generator' ),
						$notice['original'],
						$notice['adjusted'],
						$notice['posts_per_batch'],
						$batch_time_minutes,
						$notice['adjusted']
					);
					?>
				</p>
			</div>
			<?php
		}
	}
}
