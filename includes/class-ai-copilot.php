<?php
/**
 * AI Copilot Handler Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agency_Nexus_AI_Copilot {

	/**
	 * Generate context-aware AI text and content across any provider.
	 *
	 * @param string $prompt  The instruction or query for the AI.
	 * @param string $context Internal identifier for feature scoping.
	 * @return string Improved text or JSON suggestion list.
	 */
	public static function generate( $prompt, $context = '' ) {
		$enabled = get_option( 'an_ai_enabled', 'no' ) === 'yes';
		$provider = get_option( 'an_ai_provider', 'local' );
		$model = get_option( 'an_ai_model', 'gpt-4o' );

		if ( ! $enabled || 'local' === $provider ) {
			return self::local_fallback( $prompt, $context );
		}

		switch ( $provider ) {
			case 'openai':
				$api_key = get_option( 'an_openai_key' );
				if ( empty( $api_key ) ) {
					error_log( 'Agency Nexus AI Error: OpenAI API key is missing.' );
					break;
				}

				$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $api_key
					],
					'body'    => json_encode( [
						'model'       => $model ? $model : 'gpt-4o',
						'messages'    => [
							[ 'role' => 'system', 'content' => 'You are a professional agency operations copilot. Assist with ' . $context ],
							[ 'role' => 'user', 'content' => $prompt ]
						],
						'temperature' => 0.7
					] ),
					'timeout' => 15
				] );

				if ( is_wp_error( $response ) ) {
					error_log( 'Agency Nexus AI API Request Error (OpenAI): ' . $response->get_error_message() );
					break;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $code ) {
					error_log( 'Agency Nexus AI API Response Code Error (OpenAI): ' . $code . ' - ' . wp_remote_retrieve_body( $response ) );
					break;
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					error_log( 'Agency Nexus AI Error (OpenAI): Failed to parse JSON response. ' . json_last_error_msg() );
					break;
				}

				if ( isset( $body['choices'][0]['message']['content'] ) ) {
					return trim( $body['choices'][0]['message']['content'] );
				}
				break;

			case 'gemini':
				$api_key = get_option( 'an_gemini_key' );
				if ( empty( $api_key ) ) {
					error_log( 'Agency Nexus AI Error: Gemini API key is missing.' );
					break;
				}

				$model_name = $model ? $model : 'gemini-pro';
				$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;
				$response = wp_remote_post( $url, [
					'headers' => [ 'Content-Type' => 'application/json' ],
					'body'    => json_encode( [
						'contents' => [
							[
								'parts' => [
									[ 'text' => $prompt . "\n\nContext details: " . $context ]
								]
							]
						]
					] ),
					'timeout' => 15
				] );

				if ( is_wp_error( $response ) ) {
					error_log( 'Agency Nexus AI API Request Error (Gemini): ' . $response->get_error_message() );
					break;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $code ) {
					error_log( 'Agency Nexus AI API Response Code Error (Gemini): ' . $code . ' - ' . wp_remote_retrieve_body( $response ) );
					break;
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					error_log( 'Agency Nexus AI Error (Gemini): Failed to parse JSON response. ' . json_last_error_msg() );
					break;
				}

				if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
					return trim( $body['candidates'][0]['content']['parts'][0]['text'] );
				}
				break;

			case 'claude':
				$api_key = get_option( 'an_claude_key' );
				if ( empty( $api_key ) ) {
					error_log( 'Agency Nexus AI Error: Claude API key is missing.' );
					break;
				}

				$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
					'headers' => [
						'Content-Type'      => 'application/json',
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01'
					],
					'body'    => json_encode( [
						'model'     => $model ? $model : 'claude-3-opus-20240229',
						'max_tokens'=> 1000,
						'messages'  => [
							[ 'role' => 'user', 'content' => $prompt . "\n\nContext: " . $context ]
						]
					] ),
					'timeout' => 15
				] );

				if ( is_wp_error( $response ) ) {
					error_log( 'Agency Nexus AI API Request Error (Claude): ' . $response->get_error_message() );
					break;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $code ) {
					error_log( 'Agency Nexus AI API Response Code Error (Claude): ' . $code . ' - ' . wp_remote_retrieve_body( $response ) );
					break;
				}

				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					error_log( 'Agency Nexus AI Error (Claude): Failed to parse JSON response. ' . json_last_error_msg() );
					break;
				}

				if ( isset( $body['content'][0]['text'] ) ) {
					return trim( $body['content'][0]['text'] );
				}
				break;
		}

		// Fallback to offline / mock generator
		return self::local_fallback( $prompt, $context );
	}

	/**
	 * High-quality built-in context-aware offline mock generator.
	 *
	 * @param string $prompt  The instruction or query.
	 * @param string $context Internal scoping context.
	 * @return string Context-specific professional content or JSON suggestions.
	 */
	private static function local_fallback( $prompt, $context ) {
		if ( strpos( strtolower($context), 'time_suggest' ) !== false || strpos( strtolower($prompt), 'time block' ) !== false ) {
			return json_encode([
				[
					'title' => 'Deep Work: Core Engineering',
					'desc'  => 'Your mental energy is peak between 9am-12pm. Dedicate this block to high-complexity development.',
					'type'  => 'deep_work',
					'start' => date('Y-m-d 09:00:00'),
					'end'   => date('Y-m-d 11:30:00'),
					'icon'  => '💻'
				],
				[
					'title' => 'Admin & Client Sync',
					'desc'  => 'Batch administrative reviews, responses, and social hub messages in the afternoon.',
					'type'  => 'shallow_work',
					'start' => date('Y-m-d 14:00:00'),
					'end'   => date('Y-m-d 15:00:00'),
					'icon'  => '📩'
				],
				[
					'title' => 'Strategic Recharge',
					'desc'  => 'Mandatory offline break to avoid burnout and keep productivity levels sustainable.',
					'type'  => 'break',
					'start' => date('Y-m-d 11:30:00'),
					'end'   => date('Y-m-d 12:00:00'),
					'icon'  => '🍹'
				]
			]);
		}

		if ( 'task_sop_brief' === $context ) {
			return "### 📋 SOP Task Brief: " . esc_html($prompt) . "\n" .
				"**Fulfillment Objective:** Execute this deliverable with zero friction and high compliance.\n\n" .
				"#### 1. Implementation Steps\n" .
				"- **Setup & Pre-requisites:** Gather any branding guidelines, client parameters, and assets before starting.\n" .
				"- **Core Execution:** Focus without distractions (use Focus Mode) to carry out the core " . esc_html($prompt) . " requirements.\n" .
				"- **Validation:** Self-review your work to ensure it aligns perfectly with agency quality control standards.\n\n" .
				"#### 2. Definition of Done\n" .
				"- [ ] Fully validated and tested with zero errors.\n" .
				"- [ ] Pushed to the client portal and moved to 'Review' status.\n" .
				"- [ ] Actual labor hours logged accurately.";
		}

		if ( strpos( strtolower($context), 'content_batch' ) !== false || strpos( strtolower($prompt), 'batch' ) !== false ) {
			return "How to Scale a Solo Freelance Agency to 6-Figures\nReduce Client Friction with Automated Portal Workflows\nSlaying the Overhead Beast: Why Tool Consolidation is Key";
		}

		if ( strpos( strtolower($context), 'proposal' ) !== false || strpos( strtolower($context), 'scope' ) !== false ) {
			return "### 🚀 Project Scope & Strategic Proposal\n\n**Prepared for:** Potential Client\n**Goal:** Launching a high-performance web platform and conversion funnel to achieve 3x lead volume.\n\n#### 1. Core Deliverables\n- **Responsive Web Platform:** Branded, responsive layout with modular Inter design system.\n- **Sales Funnel Integration:** Connecting landing pages with automated Lead Capture.\n- **True ROI Dashboard Tracking:** Enabling automated profit tracking.\n\n#### 2. Pricing & Investment\n- **Project Fee:** $5,000.00\n- **Buffer Period:** 5 Days\n\n*Generated securely via Nexus AI Copilot.*";
		}

		if ( strpos( strtolower($context), 'message' ) !== false ) {
			return "Hi there! Thank you for reaching out. I have reviewed your request, and our agency team will get back to you with the draft deliverables shortly. Let us know if you need anything else in the meantime!";
		}

		if ( strpos( strtolower($context), 'payment_reminder' ) !== false ) {
			return "Subject: Friendly Reminder: Outstanding Invoice " . esc_html($prompt) . "\n\n" .
				"Hi {{client_name}},\n\n" .
				"Hope you are having a wonderful week!\n\n" .
				"This is a friendly reminder that invoice **" . esc_html($prompt) . "** is currently outstanding. If you have already settled this, please disregard this message! Otherwise, you can easily review and settle it securely online via your client portal here:\n\n" .
				"[View Outstanding Invoice]\n\n" .
				"Thank you so much for your support and partnership!\n\n" .
				"Warmly,\n" .
				"{{Your_Name}}";
		}

		// Generic improve content fallback
		return "AI Copilot Response: Based on your prompt '" . esc_html($prompt) . "', we suggest optimizing your agency operations, consolidating your tooling, and automating client communication via Agency Nexus dashboards.";
	}
}
