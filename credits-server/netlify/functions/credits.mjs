/**
 * Paid credits endpoint for SME Insights Generator.
 * Does not store WordPress secrets. Call with JSON: { prompt, model, site }.
 */

const cors = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Credits-Token',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
  'Content-Type': 'application/json',
};

function env(name) {
  return (process.env[name] || '').trim();
}

function json(status, body) {
  return new Response(JSON.stringify(body), { status, headers: cors });
}

async function callOpenAI(prompt, model, apiKey) {
  const response = await fetch('https://api.openai.com/v1/chat/completions', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      model: model || env('DEFAULT_MODEL') || 'gpt-4o-mini',
      messages: [{ role: 'user', content: prompt }],
      temperature: 0.7,
    }),
  });
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data?.error?.message || `OpenAI ${response.status}`);
  }
  const content = data?.choices?.[0]?.message?.content;
  if (!content) {
    throw new Error('OpenAI returned empty content');
  }
  return { content: String(content).trim(), model_used: data.model || model };
}

async function callGemini(prompt, model, apiKey) {
  const modelId = model && model.includes('gemini') ? model : 'gemini-2.0-flash';
  const url = `https://generativelanguage.googleapis.com/v1beta/models/${modelId}:generateContent`;
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'x-goog-api-key': apiKey,
    },
    body: JSON.stringify({
      contents: [{ role: 'user', parts: [{ text: prompt }] }],
    }),
  });
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data?.error?.message || `Gemini ${response.status}`);
  }
  const content = data?.candidates?.[0]?.content?.parts?.map((p) => p.text).join('') || '';
  if (!content) {
    throw new Error('Gemini returned empty content');
  }
  return { content: content.trim(), model_used: modelId };
}

export default async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('', { status: 204, headers: cors });
  }

  if (req.method !== 'POST') {
    return json(405, { error: 'POST only' });
  }

  const token = env('CREDITS_TOKEN');
  const open = env('CREDITS_OPEN') === '1';
  const incoming =
    req.headers.get('x-credits-token') ||
    (req.headers.get('authorization') || '').replace(/^Bearer\s+/i, '');

  if (!open && (!token || incoming !== token)) {
    return json(401, { error: 'Unauthorized credits request' });
  }

  let body;
  try {
    body = await req.json();
  } catch {
    return json(400, { error: 'Invalid JSON' });
  }

  const prompt = typeof body.prompt === 'string' ? body.prompt.trim() : '';
  const model = typeof body.model === 'string' ? body.model.trim() : '';
  const site = typeof body.site === 'string' ? body.site.trim() : '';

  if (!prompt) {
    return json(400, { error: 'prompt is required' });
  }

  // Stripe billing belongs here later: look up remaining credits for `site`.
  // Until then, CREDITS_OPEN=1 is for your own testing only.

  try {
    const openai = env('OPENAI_API_KEY');
    const gemini = env('GOOGLE_API_KEY');
    let result;

    if (model.includes('gemini') && gemini) {
      result = await callGemini(prompt, model, gemini);
    } else if (openai) {
      result = await callOpenAI(prompt, model, openai);
    } else if (gemini) {
      result = await callGemini(prompt, model, gemini);
    } else {
      return json(500, { error: 'No provider API key configured on the credits server' });
    }

    return json(200, {
      content: result.content,
      model_used: result.model_used,
      site: site || null,
      fallback_used: false,
    });
  } catch (error) {
    return json(502, { error: error instanceof Error ? error.message : 'Provider failed' });
  }
};

export const config = {
  path: '/credits',
};
