# SME Insights Generator

A professional WordPress plugin to automatically generate and publish high-quality business content. This plugin integrates with OpenAI, Google Gemini, and Anthropic Claude APIs and is built following modern development practices.

## Features

- **Multiple Language Model Support**: Works with OpenAI (GPT-3.5 Turbo, GPT-4, GPT-4 Turbo, GPT-4o), Google Gemini (Gemini Pro, Gemini 1.5 Pro, Gemini 1.5 Flash), and Anthropic Claude (Claude 3 Haiku, Claude 3 Sonnet, Claude 3 Opus, Claude 3.5 Sonnet).
- **Automatic Fallback**: Automatically switches to Gemini when OpenAI quota is exceeded.
- **Automated Content Generation**: Scheduled posts via WordPress cron with customizable batch processing.
- **Batch Processing**: Generate multiple posts per batch with configurable intervals.
- **Customizable Prompts**: Define your own prompt templates with topic placeholders.
- **Dynamic Featured Images**: Automatic image selection from a curated Unsplash collection with proper alt text.
- **SEO Optimized**: Includes meta descriptions, excerpts, Open Graph tags, and Twitter Cards.
- **Intelligent HTML Formatting**: Advanced content parsing for well-structured articles.
- **Professional Admin Interface**: Easy-to-use settings page with real-time cron status.

## Technical Details

- **Object-Oriented PHP**: Built with a clean, modular, and object-oriented structure.
- **WordPress Coding Standards**: The code adheres to the official WordPress coding standards for readability and maintainability.
- **Secure**: All AJAX requests are verified with nonces, and user inputs are properly sanitized.
- **SEO Best Practices**: Automatic excerpt generation, meta descriptions, and social media tags.
- **Error Handling**: Comprehensive error handling with fallback mechanisms.

## Installation

1. Upload the `sme-insights-generator` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Content Generator' in the WordPress admin menu to configure the settings.
4. Add your API keys (OpenAI, Google Gemini, or Anthropic Claude).
5. Configure your content generation settings and schedule.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- At least one API key (OpenAI, Google Gemini, or Anthropic Claude)

---

Empty topic and keyword fields keep the original SME subject lists.

Bring-your-own API keys are the complete free path. The credits endpoint is optional and does not bill a card inside the plugin.

*Developed by Remon Romany.*
