<?php
/**
 * Handles post creation and management.
 *
 * @package SME_Insights_Generator
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIG_Post_Creator class.
 */
class SIG_Post_Creator {

	/**
	 * Fallback image URL when selected image fails.
	 *
	 * @var string
	 */
	const FALLBACK_IMAGE_URL = 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d';
	
	/**
	 * Default prompt template.
	 *
	 * @var string
	 */
	const DEFAULT_PROMPT_TEMPLATE = "Generate a complete news article for a website targeting Small and Medium Enterprises (SMEs).\n\n**Instructions:**\n\n1. The article's tone must be professional, insightful, and practical.\n\n2. The output MUST be structured as follows:\n   - The very first line must be the article title, and nothing else.\n   - The rest of the text must be the full body of the article.\n\n3. Do not include any introductory phrases like \"Here is the article:\" or \"Title:\".\n\n4. The article should be approximately 400 words long.\n\n5. The main topic for the article is: [topic]\n\n6. Focus on one of these business categories: Finance, Marketing, Technology, Growth, or Strategy.\n\n7. Cover niche topics such as: AI in Business, E-commerce Trends, Startup Funding, Green Economy, or Remote Work.";

	/**
	 * Business images array for Unsplash.
	 *
	 * @var array
	 */
	private static $business_images = array(
		'https://images.unsplash.com/photo-1542744173-8e7e53415bb0',
		'https://images.unsplash.com/photo-1519389950473-47ba0277781c',
		'https://images.unsplash.com/photo-1551288049-bebda4e38f71',
		'https://images.unsplash.com/photo-1521737604893-d14cc237f11d',
		'https://images.unsplash.com/photo-1556761175-5973dc0f32e7',
		'https://images.unsplash.com/photo-1543269865-cbf427effbad',
		'https://images.unsplash.com/photo-1556740738-b6a63e27c4df',
		'https://images.unsplash.com/photo-1604594849809-dfedbc827105',
		'https://images.unsplash.com/photo-1517048676732-d65bc937f952',
		'https://images.unsplash.com/photo-1588196749107-c9186d364618',
	);
	
	/**
	 * Business categories for content generation.
	 *
	 * @var array
	 */
	private static $business_categories = array(
		'Finance',
		'Marketing',
		'Technology',
		'Growth',
		'Strategy',
	);
	
	/**
	 * Niche topics for content generation.
	 *
	 * @var array
	 */
	private static $niche_topics = array(
		'AI in Business',
		'E-commerce Trends',
		'Startup Funding',
		'Green Economy',
		'Remote Work',
	);

	/**
	 * Main function to generate and create a post.
	 *
	 * @return array An array containing the status and message.
	 */
	public static function create_ai_post() {
		$options = get_option( 'sig_settings', array() );

		$prompt_template = isset( $options['prompt_template'] ) ? trim( (string) $options['prompt_template'] ) : '';
		if ( '' === $prompt_template ) {
			$prompt_template = self::DEFAULT_PROMPT_TEMPLATE;
		}
		$ai_model = isset( $options['ai_model'] ) && '' !== trim( (string) $options['ai_model'] )
			? $options['ai_model']
			: 'gpt-4o';

		$topic_choice = self::resolve_topic( $options );
		$topic = $topic_choice['topic'];
		$business_category = $topic_choice['category'];
		$prompt = str_replace( '[topic]', $topic, $prompt_template );

		$ai_response = SIG_API_Handler::generate_content( $prompt, $ai_model );

		if ( is_wp_error( $ai_response ) ) {
			return array(
				'status'  => 'error',
				'message' => sprintf( __( 'Content Generation Failed: %s', 'sme-insights-generator' ), $ai_response->get_error_message() ),
			);
		}

		$content_text = is_array( $ai_response ) ? $ai_response['content'] : $ai_response;
		$model_used = is_array( $ai_response ) && isset( $ai_response['model_used'] ) ? $ai_response['model_used'] : $ai_model;
		$fallback_used = is_array( $ai_response ) && isset( $ai_response['fallback_used'] ) ? $ai_response['fallback_used'] : false;

		$parsed_content = self::parse_ai_response( $content_text );

		if ( is_wp_error( $parsed_content ) ) {
			return array(
				'status'  => 'error',
				'message' => sprintf( __( 'Failed to parse content response: %s', 'sme-insights-generator' ), $parsed_content->get_error_message() ),
			);
		}

		$post_title   = $parsed_content['title'];
		$post_content = $parsed_content['content'];

		$post_status = isset( $options['post_status'] ) ? $options['post_status'] : 'draft';
		
		$is_default_prompt = ( trim( $prompt_template ) === trim( self::DEFAULT_PROMPT_TEMPLATE ) );
		$saved_category = isset( $options['post_category'] ) ? trim( $options['post_category'] ) : '';

		if ( $topic_choice['used_sme_defaults'] && $is_default_prompt ) {
			$category_name = $business_category;
		} elseif ( '' !== $saved_category ) {
			$category_name = $saved_category;
		} else {
			$category_name = 'Business News';
		}

		$post_id = self::insert_post( $post_title, $post_content, $post_status, $category_name );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'error',
				'message' => sprintf( __( 'Failed to create WordPress post: %s', 'sme-insights-generator' ), $post_id->get_error_message() ),
			);
		}

		$image_url = isset( $options['featured_image_url'] ) && ! empty( trim( $options['featured_image_url'] ) )
			? trim( $options['featured_image_url'] )
			: self::get_unsplash_image( $post_title );
		error_log( 'SIG: Attempting to set featured image for post ID ' . $post_id . ' with URL: ' . $image_url );

		$image_result = self::set_featured_image( $post_id, $image_url );
		
		if ( is_wp_error( $image_result ) ) {
			error_log( 'SIG: Failed to set featured image for post ID ' . $post_id . ': ' . $image_result->get_error_message() );
			error_log( 'SIG: Trying fallback image for post ID ' . $post_id );
			$fallback_result = self::set_featured_image( $post_id, self::FALLBACK_IMAGE_URL );
			if ( is_wp_error( $fallback_result ) ) {
				error_log( 'SIG: Failed to set fallback image for post ID ' . $post_id . ': ' . $fallback_result->get_error_message() );
			} else {
				error_log( 'SIG: Successfully set fallback image for post ID ' . $post_id );
			}
		} else {
			error_log( 'SIG: Successfully set featured image for post ID ' . $post_id . ' (attachment ID: ' . $image_result . ')' );
		}

		$model_display = self::format_model_name( $model_used );
		$fallback_note = $fallback_used ? ' <span style="color: #d63638;">(Fallback: OpenAI quota exceeded)</span>' : '';
		
		return array(
			'status'  => 'success',
			'message' => sprintf(
				__( 'Successfully generated and created post: <a href="%1$s" target="_blank">%2$s</a><br><strong>Model used:</strong> %3$s%4$s', 'sme-insights-generator' ),
				esc_url( get_edit_post_link( $post_id, 'raw' ) ),
				esc_html( $post_title ),
				esc_html( $model_display ),
				wp_kses_post( $fallback_note )
			),
			'post_id' => $post_id,
			'model_used' => $model_used,
			'fallback_used' => $fallback_used,
		);
	}

	/**
	 * Formats model name for display.
	 *
	 * @param string $model The model identifier.
	 * @return string Formatted model name.
	 */
	/**
	 * Builds the article topic.
	 *
	 * Empty topic and keyword fields keep the original SME category and niche lists.
	 *
	 * @param array $options Plugin settings.
	 * @return array{topic:string,category:string,used_sme_defaults:bool}
	 */
	public static function resolve_topic( $options ) {
		$custom_topic = isset( $options['content_topic'] ) ? trim( (string) $options['content_topic'] ) : '';
		$keywords     = isset( $options['content_keywords'] ) ? trim( (string) $options['content_keywords'] ) : '';

		if ( '' === $custom_topic && '' === $keywords ) {
			$category = self::$business_categories[ array_rand( self::$business_categories ) ];
			$niche    = self::$niche_topics[ array_rand( self::$niche_topics ) ];
			return array(
				'topic'             => sprintf( '%s: %s', $category, $niche ),
				'category'          => $category,
				'used_sme_defaults' => true,
			);
		}

		if ( '' === $custom_topic ) {
			$custom_topic = $keywords;
			$keywords     = '';
		}

		$topic = ( '' === $keywords ) ? $custom_topic : $custom_topic . ': ' . $keywords;

		return array(
			'topic'             => $topic,
			'category'          => '',
			'used_sme_defaults' => false,
		);
	}

	/**
	 * Formats model name for display.
	 *
	 * @param string $model The model identifier.
	 * @return string Formatted model name.
	 */
	private static function format_model_name( $model ) {
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
	 * Parses the content response to extract the title and content.
	 *
	 * Assumes the first line is the title and the rest is the content.
	 *
	 * @param string $response The raw content response text.
	 * @return array|WP_Error An array with 'title' and 'content' or a WP_Error.
	 */
	private static function parse_ai_response( $response ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", $response ) ) );

		if ( empty( $lines ) ) {
			return new WP_Error( 'sig_empty_response', __( 'Content response was empty.', 'sme-insights-generator' ) );
		}

		$post_title = array_shift( $lines );
		$post_content = implode( "\n", $lines );

		if ( empty( $post_title ) || empty( $post_content ) ) {
			return new WP_Error( 'sig_malformed_response', __( 'Content format is incorrect. Ensure the title is on the first line.', 'sme-insights-generator' ) );
		}

		$formatted_content = self::format_content_html( $post_content );

		return array(
			'title'   => sanitize_text_field( $post_title ),
			'content' => wp_kses_post( $formatted_content ),
		);
	}

	/**
	 * Formats plain text content into HTML.
	 *
	 * @param string $content The raw content text.
	 * @return string Formatted HTML content.
	 */
	private static function format_content_html( $content ) {
		$plain_text = wp_strip_all_tags( $content );
		
		$plain_text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $plain_text );
		$plain_text = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $plain_text );
		
		$lines = explode( "\n", $plain_text );
		$formatted = '';
		$in_list = false;
		$current_paragraph = '';
		
		foreach ( $lines as $line ) {
			$line = trim( $line );
			
			if ( empty( $line ) ) {
				if ( $in_list ) {
					$formatted .= '</ul>' . "\n\n";
					$in_list = false;
				} elseif ( ! empty( $current_paragraph ) ) {
					$formatted .= '<p>' . $current_paragraph . '</p>' . "\n\n";
					$current_paragraph = '';
				}
				continue;
			}
			
			$is_heading = false;
			$heading_text = '';
			
			if ( preg_match( '/^\*\*(.+?)\*\*$/', $line, $matches ) ) {
				$is_heading = true;
				$heading_text = trim( $matches[1] );
			}
			elseif ( strlen( $line ) < 100 && preg_match( '/^[A-Z][^.!?]*[:\-]$/', $line ) ) {
				$is_heading = true;
				$heading_text = trim( $line, ':*' );
			}
			elseif ( strlen( $line ) < 80 && ! preg_match( '/[.!?]$/', $line ) && preg_match( '/^[A-Z]/', $line ) ) {
				$is_heading = true;
				$heading_text = $line;
			}
			
			if ( $is_heading ) {
				if ( $in_list ) {
					$formatted .= '</ul>' . "\n\n";
					$in_list = false;
				} elseif ( ! empty( $current_paragraph ) ) {
					$formatted .= '<p>' . $current_paragraph . '</p>' . "\n\n";
					$current_paragraph = '';
				}
				
				$formatted .= '<h2>' . esc_html( $heading_text ) . '</h2>' . "\n\n";
				continue;
			}
			
			if ( preg_match( '/^[-•*]\s+(.+)$/', $line, $matches ) ) {
				if ( ! empty( $current_paragraph ) ) {
					$formatted .= '<p>' . $current_paragraph . '</p>' . "\n\n";
					$current_paragraph = '';
				}
				
				if ( ! $in_list ) {
					$formatted .= '<ul>' . "\n";
					$in_list = true;
				}
				
				$formatted .= '<li>' . esc_html( $matches[1] ) . '</li>' . "\n";
				continue;
			}
			
			if ( $in_list ) {
				$formatted .= '</ul>' . "\n\n";
				$in_list = false;
			}
			
			$current_paragraph .= ( ! empty( $current_paragraph ) ? ' ' : '' ) . $line;
		}
		
		if ( $in_list ) {
			$formatted .= '</ul>' . "\n\n";
		} elseif ( ! empty( $current_paragraph ) ) {
			$formatted .= '<p>' . $current_paragraph . '</p>' . "\n\n";
		}

		if ( empty( trim( $formatted ) ) || substr_count( $formatted, '<p>' ) < 2 ) {
			$paragraphs = preg_split( '/\n\s*\n/', $plain_text, -1, PREG_SPLIT_NO_EMPTY );
			
			if ( count( $paragraphs ) <= 1 ) {
				$text = trim( $plain_text );
				$paragraphs = array();
				
				$sentence_pattern = '/([.!?]+)\s+(?=[A-Z])/';
				$sentences = preg_split( $sentence_pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );
				
				$current_para = '';
				$sentence_count = 0;
				
				foreach ( $sentences as $i => $part ) {
					if ( empty( trim( $part ) ) ) {
						continue;
					}
					
					$current_para .= $part;
					
					if ( preg_match( '/[.!?]+$/', $part ) ) {
						$sentence_count++;
						
						if ( $sentence_count >= 2 || strlen( $current_para ) > 250 ) {
							$para_text = trim( $current_para );
							if ( ! empty( $para_text ) ) {
								$paragraphs[] = $para_text;
							}
							$current_para = '';
							$sentence_count = 0;
						}
					}
				}
				
				if ( ! empty( trim( $current_para ) ) ) {
					$paragraphs[] = trim( $current_para );
				}
				
				if ( count( $paragraphs ) <= 1 && strlen( $paragraphs[0] ) > 300 ) {
					$long_text = $paragraphs[0];
					$paragraphs = array();
					
					$chunks = preg_split( '/(.{200,250}[.!?]\s+)/', $long_text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
					
					foreach ( $chunks as $chunk ) {
						$chunk = trim( $chunk );
						if ( ! empty( $chunk ) && strlen( $chunk ) > 50 ) {
							$paragraphs[] = $chunk;
						}
					}
					
					if ( count( $paragraphs ) <= 1 ) {
						$paragraphs = array();
						$chunks = str_split( $long_text, 200 );
						foreach ( $chunks as $chunk ) {
							$chunk = trim( $chunk );
							if ( ! empty( $chunk ) ) {
								$paragraphs[] = $chunk;
							}
						}
					}
				}
			}
			
			$formatted = '';
			foreach ( $paragraphs as $para ) {
				$para = trim( $para );
				if ( ! empty( $para ) ) {
					// Apply markdown formatting before escaping
					$para = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $para );
					$para = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $para );
					// Use wp_kses_post to allow safe HTML tags while escaping dangerous content
					$formatted .= '<p>' . wp_kses_post( $para ) . '</p>' . "\n\n";
				}
			}
		}

		return trim( $formatted );
	}

	/**
	 * Inserts the post into WordPress.
	 *
	 * @param string $title The post title.
	 * @param string $content The post content.
	 * @param string $status The post status.
	 * @param string $category_name The category name.
	 * @return int|WP_Error The post ID or a WP_Error object.
	 */
	private static function insert_post( $title, $content, $status, $category_name ) {
		$category_id = self::get_or_create_category( $category_name );

		if ( is_wp_error( $category_id ) ) {
			return $category_id;
		}

		$author_id = get_current_user_id();
		if ( 0 === $author_id ) {
			$admin_users = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
			$author_id = ! empty( $admin_users ) ? $admin_users[0] : 1;
		}

		$excerpt = wp_trim_words( wp_strip_all_tags( $content ), 30, '...' );

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => $content,
			'post_excerpt'  => $excerpt,
			'post_status'   => $status,
			'post_type'     => 'post',
			'post_author'   => $author_id,
			'post_category' => array( $category_id ),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_sig_generated', '1' );

		self::add_seo_metadata( $post_id, $title, $content );
		
		$cache_key = 'sig_today_posts_count_' . date( 'Y-m-d' );
		delete_transient( $cache_key );

		return $post_id;
	}

	/**
	 * Gets the category ID by name, or creates it if it doesn't exist.
	 *
	 * @param string $category_name The name of the category.
	 * @return int|WP_Error The category ID or a WP_Error object.
	 */
	private static function get_or_create_category( $category_name ) {
		$category = get_term_by( 'name', $category_name, 'category' );

		if ( $category ) {
			return $category->term_id;
		}

		$result = wp_insert_term(
			$category_name,
			'category',
			array(
				'slug' => sanitize_title( $category_name ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['term_id'];
	}

	/**
	 * Downloads an image from a URL and sets it as the featured image for a post.
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $image_url The URL of the image to download.
	 * @return int|WP_Error The attachment ID or a WP_Error object on failure.
	 */
	private static function set_featured_image( $post_id, $image_url ) {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// Validate image URL
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			error_log( 'SIG: Invalid image URL provided: ' . $image_url );
			return new WP_Error( 'sig_invalid_image_url', __( 'Invalid image URL provided.', 'sme-insights-generator' ) );
		}

		// Extract file extension from URL
		$url_path = parse_url( $image_url, PHP_URL_PATH );
		$file_extension = pathinfo( $url_path, PATHINFO_EXTENSION );
		
		if ( empty( $file_extension ) ) {
			if ( preg_match( '/\.(jpg|jpeg|png|gif|webp)(\?|$)/i', $image_url, $matches ) ) {
				$file_extension = strtolower( $matches[1] );
			} else {
				$file_extension = 'jpg';
			}
		}

		$file_array = array();
		$file_array['name'] = 'featured-image-' . $post_id . '.' . $file_extension;

		$temp_file = download_url( $image_url );

		if ( is_wp_error( $temp_file ) ) {
			$error_msg = $temp_file->get_error_message();
			error_log( 'SIG: Failed to download featured image from ' . $image_url . ': ' . $error_msg );
			return $temp_file;
		}

		$file_type = wp_check_filetype( $temp_file );
		if ( ! $file_type['type'] || strpos( $file_type['type'], 'image/' ) !== 0 ) {
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
			error_log( 'SIG: Downloaded file is not an image: ' . $image_url );
			return new WP_Error( 'sig_not_an_image', __( 'Downloaded file is not a valid image.', 'sme-insights-generator' ) );
		}

		$file_array['tmp_name'] = $temp_file;

		$attachment_id = media_handle_sideload( $file_array, $post_id, null );

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
			$error_msg = $attachment_id->get_error_message();
			error_log( 'SIG: Failed to sideload featured image: ' . $error_msg );
			return $attachment_id;
		}

		$attach_data = wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		$post_title = get_the_title( $post_id );
		$alt_text = ! empty( $post_title ) ? $post_title : __( 'Featured Image', 'sme-insights-generator' );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

		$result = set_post_thumbnail( $post_id, $attachment_id );

		if ( ! $result ) {
			error_log( 'SIG: Failed to set post thumbnail for post ID: ' . $post_id );
			return new WP_Error( 'sig_set_thumbnail_failed', __( 'Failed to set post thumbnail.', 'sme-insights-generator' ) );
		}

		return $attachment_id;
	}

	/**
	 * Retrieves an appropriate Unsplash image URL based on the post title.
	 * Uses a hash of the title to consistently select the same image for the same title.
	 *
	 * @param string $title The post title used for image selection.
	 * @return string Unsplash image URL.
	 */
	private static function get_unsplash_image( $title ) {
		$hash = crc32( $title );
		$image_index = absint( $hash ) % count( self::$business_images );
		
		return self::$business_images[ $image_index ];
	}

	/**
	 * Adds SEO metadata to the generated post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $title The post title.
	 * @param string $content The post content.
	 */
	private static function add_seo_metadata( $post_id, $title, $content ) {
		$meta_description = wp_trim_words( wp_strip_all_tags( $content ), 25, '...' );
		if ( ! empty( $meta_description ) ) {
			update_post_meta( $post_id, '_sig_meta_description', sanitize_text_field( $meta_description ) );
		}
	}

	/**
	 * Outputs SEO meta tags in wp_head.
	 */
	public static function output_seo_meta_tags() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$meta_description = get_post_meta( $post_id, '_sig_meta_description', true );
		if ( empty( $meta_description ) ) {
			return;
		}

		$title = get_the_title( $post_id );
		$featured_image = get_the_post_thumbnail_url( $post_id, 'full' );

		if ( ! empty( $meta_description ) ) {
			echo '<meta name="description" content="' . esc_attr( $meta_description ) . '" />' . "\n";
		}
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . '" />' . "\n";
		if ( ! empty( $meta_description ) ) {
			echo '<meta property="og:description" content="' . esc_attr( $meta_description ) . '" />' . "\n";
		}
		if ( $featured_image ) {
			echo '<meta property="og:image" content="' . esc_url( $featured_image ) . '" />' . "\n";
		}
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
		if ( ! empty( $meta_description ) ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $meta_description ) . '" />' . "\n";
		}
		if ( $featured_image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $featured_image ) . '" />' . "\n";
		}
	}

}
