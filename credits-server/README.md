# Credits server

This is the paid path. The free plugin keeps working with the user's own API keys.

## What it does

The plugin sends `prompt`, `model`, and `site` to the credits endpoint.
This server checks that the site has remaining posts, calls the AI provider with your keys, and returns `{ "content": "...", "model_used": "..." }`.

## Files

- `netlify/functions/credits.mjs` — the endpoint
- `.env.example` — copy to Netlify environment variables
- `netlify.toml` — deploy config

## Deploy

1. Create a Netlify site from this folder.
2. Set `OPENAI_API_KEY` or `GOOGLE_API_KEY` in Netlify.
3. Set `CREDITS_TOKEN` to a long random string.
4. Deploy.
5. In the plugin, choose Credits and paste:
   `https://YOUR-SITE.netlify.app/.netlify/functions/credits`

Stripe is not connected yet. Until Stripe is added, set `CREDITS_OPEN=1` for testing only.
For paid use, add Stripe Checkout and store remaining credits per site URL before you turn this on for customers.
