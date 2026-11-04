<?php
/**
 * Handles API communication with OpenAI and Google Gemini.
 *
 * @package SME_Insights_Generator
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SIG_API_Handler class.
 */
class SIG_API_Handler {

	/**
	 * Generates content using the selected language model.
	 *
	 * @param string $prompt The prompt to send to the language model.
	 * @param string $model The selected model identifier (e.g., gpt-4, gemini-pro).
	 * @return array|WP_Error Array with 'content' and 'model_used' keys, or a WP_Error object on failure.
	 */
	public static function generate_content( $prompt, $model ) {
		$options  = get_option( 'sig_settings', array() );
		$provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'byo';

		if ( 'credits' === $provider ) {
			return self::generate_via_credits( $prompt, $model, $options );
		}

		return self::generate_with_own_keys( $prompt, $model );
	}

	/**
	 * Generates content with the site owner's own provider keys.
	 *
	 * @param string $prompt The prompt to send to the language model.
	 * @param string $model The selected model identifier.
	 * @return array|WP_Error
	 */
	private static function generate_with_own_keys( $prompt, $model ) {
		if ( strpos( $model, 'gpt' ) !== false ) {
			$result = self::generate_openai_content( $prompt, $model );
			
			if ( is_wp_error( $result ) ) {
				$error_code = $result->get_error_code();
				$error_message = $result->get_error_message();
				
				if ( strpos( $error_message, '429' ) !== false || 
					 strpos( $error_message, 'quota' ) !== false || 
					 strpos( $error_message, 'rate limit' ) !== false ||
					 strpos( $error_message, 'exceeded' ) !== false ) {
					
					$options = get_option( 'sig_settings', array() );
					$gemini_key = isset( $options['google_api_key'] ) ? $options['google_api_key'] : '';
					$enable_fallback = isset( $options['enable_auto_fallback'] ) ? $options['enable_auto_fallback'] : '1';
					
					error_log( "SIG: OpenAI quota error detected. Fallback enabled: {$enable_fallback}, Gemini key available: " . ( ! empty( $gemini_key ) ? 'yes' : 'no' ) );
					
					if ( ! empty( $gemini_key ) && '1' === $enable_fallback ) {
						error_log( 'SIG: OpenAI quota exceeded, switching to Gemini as fallback' );
						
						// Try Gemini models in order of preference
						$gemini_models = array( 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-1.5-pro-latest', 'gemini-pro' );
						foreach ( $gemini_models as $gemini_model ) {
							$gemini_result = self::generate_google_ai_content( $prompt, $gemini_model );
							if ( ! is_wp_error( $gemini_result ) ) {
								error_log( "SIG: Successfully used Gemini fallback model: {$gemini_model}" );
								return array(
									'content' => $gemini_result,
									'model_used' => $gemini_model,
									'fallback_used' => true,
								);
							}
						}
						
						return new WP_Error( 
							'sig_openai_quota_exceeded', 
							sprintf( 
								__( 'OpenAI API quota exceeded. Attempted to use Gemini as fallback but it also failed. Original error: %s', 'sme-insights-generator' ), 
								$error_message 
							) 
						);
					} else {
						if ( empty( $gemini_key ) ) {
							return new WP_Error( 
								'sig_openai_quota_exceeded', 
								sprintf( 
									__( 'OpenAI API quota exceeded. Please upgrade your OpenAI plan, reduce the number of posts per day, or configure a Google Gemini API key for automatic fallback. Original error: %s', 'sme-insights-generator' ), 
									$error_message 
								) 
							);
						} else {
							error_log( 'SIG: OpenAI quota exceeded, but automatic fallback is disabled by user settings' );
							return new WP_Error( 
								'sig_openai_quota_exceeded', 
								sprintf( 
									__( 'OpenAI API quota exceeded. Automatic fallback to Gemini is disabled in settings. Original error: %s', 'sme-insights-generator' ), 
									$error_message 
								) 
							);
						}
					}
				}
			}
			
			if ( ! is_wp_error( $result ) ) {
				return array(
					'content' => $result,
					'model_used' => $model,
					'fallback_used' => false,
				);
			}
			
			return $result;
		} elseif ( strpos( $model, 'claude' ) !== false ) {
			$result = self::generate_anthropic_content( $prompt, $model );
			if ( ! is_wp_error( $result ) ) {
				return array(
					'content' => $result,
					'model_used' => $model,
					'fallback_used' => false,
				);
			}
			return $result;
		} elseif ( strpos( $model, 'gemini' ) !== false ) {
			$result = self::generate_google_ai_content( $prompt, $model );
			if ( ! is_wp_error( $result ) ) {
				return array(
					'content' => $result,
					'model_used' => $model,
					'fallback_used' => false,
				);
			}
			return $result;
		} else {
			return new WP_Error( 'sig_invalid_model', __( 'Invalid model selected. Please choose a supported model.', 'sme-insights-generator' ) );
		}
	}

	/**
	 * Generates content using the OpenAI API.
	 *
	 * @param string $prompt The prompt to send.
	 * @param string $model The OpenAI model slug.
	 * @return string|WP_Error The generated content or a WP_Error object on failure.
	 */
	private static function generate_openai_content( $prompt, $model ) {
		$options = get_option( 'sig_settings', array() );
		$api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'sig_no_openai_key', __( 'OpenAI API Key is not set in plugin settings.', 'sme-insights-generator' ) );
		}

		// Fallback models if the selected one isn't available
		$model_alternatives = array(
			'gpt-4.1'       => array( 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo' ),
			'gpt-4.1-mini'  => array( 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4o', 'gpt-3.5-turbo' ),
			'gpt-4o'        => array( 'gpt-4o', 'gpt-4.1', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo' ),
			'gpt-4o-mini'   => array( 'gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4o', 'gpt-3.5-turbo' ),
			'gpt-4'         => array( 'gpt-4', 'gpt-4-turbo', 'gpt-4o', 'gpt-4.1', 'gpt-3.5-turbo' ),
			'gpt-4-turbo'   => array( 'gpt-4-turbo', 'gpt-4o', 'gpt-4.1', 'gpt-4', 'gpt-3.5-turbo' ),
			'gpt-3.5-turbo' => array( 'gpt-3.5-turbo', 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini' ),
		);

		$models_to_try = isset( $model_alternatives[ $model ] ) ? $model_alternatives[ $model ] : array( $model );

		if ( ! in_array( $model, $models_to_try, true ) ) {
			array_unshift( $models_to_try, $model );
		}

		$url = 'https://api.openai.com/v1/chat/completions';
		$last_error = null;

		foreach ( $models_to_try as $model_to_try ) {
			$body = array(
				'model'    => $model_to_try,
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.7,
			);

			$args = array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
				'sslverify' => true,
			);

			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );

			if ( 200 === $response_code ) {
				if ( isset( $data['choices'][0]['message']['content'] ) ) {
					if ( $model_to_try !== $model ) {
						error_log( "SIG: Used fallback model '{$model_to_try}' instead of '{$model}'" );
					}
					return trim( $data['choices'][0]['message']['content'] );
				}
				continue;
			}

			// Auth errors - no point trying other models
			if ( 401 === $response_code ) {
				$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown OpenAI API error.', 'sme-insights-generator' );
				return new WP_Error( 'sig_openai_api_error', sprintf( __( 'OpenAI API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
			}

			// Quota exceeded - return immediately to trigger fallback logic
			if ( 429 === $response_code ) {
				$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Rate limit or quota exceeded.', 'sme-insights-generator' );
				return new WP_Error( 'sig_openai_quota_exceeded', sprintf( __( 'OpenAI API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
			}

			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown OpenAI API error.', 'sme-insights-generator' );
			$last_error = new WP_Error( 'sig_openai_api_error', sprintf( __( 'OpenAI API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
		}

		if ( $last_error ) {
			return $last_error;
		}

		return new WP_Error( 'sig_openai_api_error', __( 'OpenAI API Error: None of the attempted models are available. Please check your API key and model selection.', 'sme-insights-generator' ) );
	}

	/**
	 * Generates content using the Google Gemini API.
	 *
	 * @param string $prompt The prompt to send.
	 * @param string $model The Google Gemini model identifier (e.g., gemini-pro).
	 * @return string|WP_Error The generated content or a WP_Error object on failure.
	 */
	private static function generate_google_ai_content( $prompt, $model ) {
		$options = get_option( 'sig_settings', array() );
		$api_key = isset( $options['google_api_key'] ) ? $options['google_api_key'] : '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'sig_no_google_key', __( 'Google Gemini API Key is not set in plugin settings.', 'sme-insights-generator' ) );
		}

		// Get available models from API to check compatibility
		$available_models = self::get_available_models( $api_key );
		
		// Model alternatives for fallback
		$model_alternatives = array(
			'gemini-2.5-flash'        => array( 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-pro', 'gemini-1.5-flash-latest', 'gemini-1.5-pro-latest', 'gemini-pro' ),
			'gemini-2.5-pro'          => array( 'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro-latest', 'gemini-pro' ),
			'gemini-2.0-flash'        => array( 'gemini-2.0-flash', 'gemini-2.5-flash', 'gemini-1.5-flash-latest', 'gemini-pro' ),
			'gemini-pro'              => array( 'gemini-pro', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-pro-latest', 'gemini-1.5-flash', 'gemini-1.5-flash-latest' ),
			'gemini-1.5-pro-latest'   => array( 'gemini-1.5-pro-latest', 'gemini-2.5-pro', 'gemini-1.5-pro', 'gemini-2.0-flash', 'gemini-pro' ),
			'gemini-1.5-flash-latest' => array( 'gemini-1.5-flash-latest', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-pro' ),
			'gemini-1.0-pro'          => array( 'gemini-1.0-pro', 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-pro' ),
		);

		$models_to_try = isset( $model_alternatives[ $model ] ) ? $model_alternatives[ $model ] : array( $model );
		
		if ( ! empty( $available_models ) && is_array( $available_models ) ) {
			$generate_content_models = array();
			foreach ( $available_models as $available_model ) {
				if ( isset( $available_model['name'] ) && isset( $available_model['supportedGenerationMethods'] ) ) {
					if ( in_array( 'generateContent', $available_model['supportedGenerationMethods'], true ) ) {
						$model_name = str_replace( 'models/', '', $available_model['name'] );
						$generate_content_models[] = $model_name;
					}
				}
			}
			
			if ( ! empty( $generate_content_models ) ) {
				$original_model_first = array();
				if ( in_array( $model, $generate_content_models, true ) ) {
					$original_model_first[] = $model;
				}
				$models_to_try = array_merge( $original_model_first, array_diff( $generate_content_models, $original_model_first ), $models_to_try );
				$models_to_try = array_unique( $models_to_try );
			}
		}
		
		if ( ! in_array( $model, $models_to_try, true ) ) {
			array_unshift( $models_to_try, $model );
		}

		$api_versions = array( 'v1', 'v1beta' );
		$last_error = null;

		foreach ( $api_versions as $api_version ) {
			foreach ( $models_to_try as $model_to_try ) {
				$url = "https://generativelanguage.googleapis.com/{$api_version}/models/{$model_to_try}:generateContent";

				$body = array(
					'contents' => array(
						array(
							'parts' => array(
								array(
									'text' => $prompt,
								),
							),
						),
					),
					'generationConfig' => array(
						'temperature' => 0.7,
					),
				);

				$args = array(
					'headers' => array(
						'Content-Type'   => 'application/json',
						'x-goog-api-key' => $api_key,
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 45,
					'sslverify' => true,
				);

				$response = wp_remote_post( $url, $args );

				if ( is_wp_error( $response ) ) {
					$last_error = $response;
					continue;
				}

				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
				$data          = json_decode( $response_body, true );

				if ( 200 === $response_code ) {
					if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
						return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
					}
					continue;
				}

				if ( 404 !== $response_code ) {
					$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown Google Gemini API error.', 'sme-insights-generator' );
					$last_error = new WP_Error( 'sig_google_api_error', sprintf( __( 'Google Gemini API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
				}
			}
		}

		if ( $last_error ) {
			return $last_error;
		}

		return new WP_Error( 'sig_google_api_error', __( 'Google Gemini API Error: None of the attempted models are available. Please check your API key and model selection.', 'sme-insights-generator' ) );

	}

	/**
	 * Retrieves the list of available models from Google Gemini API.
	 *
	 * @param string $api_key The Google Gemini API key.
	 * @return array|false Array of available models or false on failure.
	 */
	private static function get_available_models( $api_key ) {
		$cache_key = 'sig_gemini_models_' . md5( $api_key );
		$cached_models = get_transient( $cache_key );
		
		if ( false !== $cached_models && is_array( $cached_models ) ) {
			return $cached_models;
		}
		
		$api_versions = array( 'v1', 'v1beta' );
		$models = false;
		
		foreach ( $api_versions as $api_version ) {
			$url = "https://generativelanguage.googleapis.com/{$api_version}/models";
			
			$args = array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				),
				'timeout' => 8,
				'sslverify' => true,
			);
			
			$response = wp_remote_get( $url, $args );
			
			if ( is_wp_error( $response ) ) {
				continue;
			}
			
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );
			
			if ( 200 === $response_code && isset( $data['models'] ) && is_array( $data['models'] ) ) {
				$models = $data['models'];
				set_transient( $cache_key, $models, DAY_IN_SECONDS );
				break;
			}
		}
		
		return $models;
	}

	/**
	 * Generates content using the Anthropic Claude API.
	 *
	 * @param string $prompt The prompt to send.
	 * @param string $model The Anthropic Claude model identifier (e.g., claude-3-5-sonnet-20241022).
	 * @return string|WP_Error The generated content or a WP_Error object on failure.
	 */
	private static function generate_anthropic_content( $prompt, $model ) {
		$options = get_option( 'sig_settings', array() );
		$api_key = isset( $options['anthropic_api_key'] ) ? $options['anthropic_api_key'] : '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'sig_no_anthropic_key', __( 'Anthropic Claude API Key is not set in plugin settings.', 'sme-insights-generator' ) );
		}

		$model_alternatives = array(
			'claude-sonnet-4-5'          => array( 'claude-sonnet-4-5', 'claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-haiku-20240307' ),
			'claude-3-5-haiku-20241022'  => array( 'claude-3-5-haiku-20241022', 'claude-3-haiku-20240307', 'claude-sonnet-4-5', 'claude-3-5-sonnet-20241022' ),
			'claude-3-5-sonnet-20241022' => array( 'claude-3-5-sonnet-20241022', 'claude-sonnet-4-5', 'claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307' ),
			'claude-3-opus-20240229'     => array( 'claude-3-opus-20240229', 'claude-sonnet-4-5', 'claude-3-5-sonnet-20241022', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307' ),
			'claude-3-sonnet-20240229'   => array( 'claude-3-sonnet-20240229', 'claude-3-5-sonnet-20241022', 'claude-sonnet-4-5', 'claude-3-opus-20240229', 'claude-3-haiku-20240307' ),
			'claude-3-haiku-20240307'    => array( 'claude-3-haiku-20240307', 'claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022', 'claude-sonnet-4-5' ),
		);

		$models_to_try = isset( $model_alternatives[ $model ] ) ? $model_alternatives[ $model ] : array( $model );

		if ( ! in_array( $model, $models_to_try, true ) ) {
			array_unshift( $models_to_try, $model );
		}

		$url = 'https://api.anthropic.com/v1/messages';
		$last_error = null;

		foreach ( $models_to_try as $model_to_try ) {
			$body = array(
				'model'     => $model_to_try,
				'max_tokens' => 4096,
				'messages'  => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			);

			$args = array(
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
				'sslverify' => true,
			);

			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );

			if ( 200 === $response_code ) {
				if ( isset( $data['content'][0]['text'] ) ) {
					if ( $model_to_try !== $model ) {
						error_log( "SIG: Used fallback Claude model '{$model_to_try}' instead of '{$model}'" );
					}
					return trim( $data['content'][0]['text'] );
				}
				continue;
			}

			if ( 401 === $response_code ) {
				$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown Anthropic API error.', 'sme-insights-generator' );
				return new WP_Error( 'sig_anthropic_api_error', sprintf( __( 'Anthropic API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
			}

			if ( 429 === $response_code ) {
				$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Rate limit or quota exceeded.', 'sme-insights-generator' );
				return new WP_Error( 'sig_anthropic_quota_exceeded', sprintf( __( 'Anthropic API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
			}

			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown Anthropic API error.', 'sme-insights-generator' );
			$last_error = new WP_Error( 'sig_anthropic_api_error', sprintf( __( 'Anthropic API Error (%d): %s', 'sme-insights-generator' ), $response_code, $error_message ) );
		}

		if ( $last_error ) {
			return $last_error;
		}

		return new WP_Error( 'sig_anthropic_api_error', __( 'Anthropic API Error: None of the attempted models are available. Please check your API key and model selection.', 'sme-insights-generator' ) );
	}

	/**
	 * Optional hosted credits call. Does not charge a card and does not disable own keys.
	 *
	 * @param string $prompt Prompt text.
	 * @param string $model Selected model identifier.
	 * @param array  $options Plugin settings.
	 * @return array|WP_Error
	 */
	private static function generate_via_credits( $prompt, $model, $options ) {
		$endpoint = isset( $options['credits_endpoint'] ) ? esc_url_raw( $options['credits_endpoint'] ) : '';

		if ( '' === $endpoint ) {
			return new WP_Error(
				'sig_credits_not_configured',
				__( 'Credits are not set up yet. Choose your own API keys, or add a credits endpoint when the subscription service is ready.', 'sme-insights-generator' )
			);
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
		$token = isset( $options['credits_token'] ) ? trim( (string) $options['credits_token'] ) : '';
		if ( '' !== $token ) {
			$headers['X-Credits-Token'] = $token;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'   => $headers,
				'body'      => wp_json_encode(
					array(
						'prompt' => $prompt,
						'model'  => $model,
						'site'   => home_url(),
					)
				),
				'timeout'   => 45,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 === $code && is_array( $data ) && ! empty( $data['content'] ) ) {
			return array(
				'content'       => trim( (string) $data['content'] ),
				'model_used'    => isset( $data['model_used'] ) ? sanitize_text_field( $data['model_used'] ) : $model,
				'fallback_used' => ! empty( $data['fallback_used'] ),
			);
		}

		$message = ( is_array( $data ) && isset( $data['message'] ) ) ? sanitize_text_field( $data['message'] ) : __( 'The credits endpoint did not return content.', 'sme-insights-generator' );
		return new WP_Error( 'sig_credits_failed', $message );
	}
}
