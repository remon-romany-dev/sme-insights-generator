=== SME Insights Generator ===

Contributors: remon-romany

Donate link: https://remonromany.com/

Tags: ai, content, automation, seo, openai

Requires at least: 5.0
Requires PHP: 7.4

Tested up to: 7.1

Stable tag: 1.1.0

License: GPLv2 or later

License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate and publish high-quality business content using OpenAI, Google Gemini, and Anthropic Claude APIs.

== Description ==

SME Insights Generator connects your WordPress site with powerful language models to create relevant news articles for Small and Medium Enterprises. The plugin features automatic fallback mechanisms, batch processing, and SEO optimization.

Features:

*   **Multiple AI Models**: OpenAI, Google Gemini, and Anthropic Claude. Current model ids are listed first. Older ids stay available so saved settings keep working.

*   **Automatic Fallback**: Automatically switches to Gemini when OpenAI quota is exceeded, ensuring uninterrupted content generation.

*   **Automated Publishing**: Uses WordPress Cron to schedule and publish posts automatically with customizable intervals.

*   **Batch Processing**: Generate multiple posts per batch with configurable intervals between posts and batches.

*   **Custom Prompts**: Define your own content templates with topic placeholders.

*   **Featured Images**: Automatically assigns relevant featured images from Unsplash with proper alt text for SEO.

*   **SEO Optimized**: Includes automatic excerpt generation, meta descriptions, Open Graph tags, and Twitter Cards.

*   **Admin Settings Page**: A user-friendly interface to manage all settings with real-time cron job status.

== Installation ==

1.  Upload the `sme-insights-generator` folder to the `/wp-content/plugins/` directory.

2.  Activate the plugin through the 'Plugins' menu in WordPress.

3.  Navigate to 'Content Generator' in the WordPress admin menu to configure your API keys and settings.

4.  Add at least one API key (OpenAI, Google Gemini, or Anthropic Claude).

5.  Configure your content generation settings, including posts per day, batch size, and intervals.

6.  Set your prompt template and post category preferences.

== Frequently Asked Questions ==

= Do I need all three API keys? =

No, you only need at least one API key. However, having both OpenAI and Gemini keys enables automatic fallback when OpenAI quota is exceeded.

= Can I customize the content generation schedule? =

Yes, you can configure the number of posts per day, posts per batch, and intervals between posts and batches.

= Does the plugin work with SEO plugins? =

Yes, the plugin is compatible with Yoast SEO, RankMath, and All in One SEO. It will use their meta tags if available.

= What happens if an API fails? =

If OpenAI fails due to quota limits and you have Gemini configured with fallback enabled, the plugin will automatically switch to Gemini.

== Screenshots ==

1. API keys, your own keys or a credits endpoint, and the model list.
2. Topic, keywords, and the prompt. Empty topic fields keep the SME lists.
3. The daily schedule and the cron status.

== Changelog ==

= 1.1.0 =
*   Topic and keyword fields. Empty fields keep the original SME subject lists.
*   API keys are password fields and are not printed back into the page.
*   Gemini requests send the key in a header.
*   Scheduled generation no longer runs inside the page request.
*   Cron lock is released when the daily limit is already reached.
*   Optional credits endpoint. Own API keys stay fully usable. No in-plugin payment.
*   A missing prompt now uses the built-in SME template instead of failing the scheduled run. Unset model stays GPT-4o.
*   The settings screen shows the WordPress “Settings saved.” confirmation.

= 1.0.0 =
*   Initial release of the plugin.
*   Support for OpenAI, Google Gemini, and Anthropic Claude APIs.
*   Automatic fallback mechanism.
*   Batch processing capabilities.
*   SEO optimization features.
*   Customizable prompt templates.
*   Automatic featured image assignment.
