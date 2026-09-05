# Hosted AI chat

The default `AI_PROVIDER=ollama` keeps the existing local setup.
For a provider supporting the Chat Completions request/response format,
set these variables in Render and redeploy:

```dotenv
AI_PROVIDER=chat_completions
AI_API_ENDPOINT=https://YOUR_PROVIDER/FULL_CHAT_COMPLETIONS_PATH
AI_API_KEY=YOUR_PROVIDER_KEY
AI_MODEL=YOUR_PROVIDER_MODEL
AI_TIMEOUT=45
```

Use the full HTTPS endpoint, not just the base URL. Obtain the endpoint and
model name from the selected provider's documentation. Keep the key in Render
environment settings, never in Git or browser code. Requests send chat history
and retrieved attraction context to that provider. Provider quotas and charges
are separate from Render hosting.

The adapter sends `model`, `messages` and `stream: false`, and reads
`choices[0].message.content`. Other API formats require a separate adapter.
The provider has not yet been selected or tested against a live account.

Server logs use `AI_CONFIGURATION_MISSING_OR_INVALID`, `AI_UNAVAILABLE`,
`AI_HTTP_<status>` or `AI_EMPTY_RESPONSE` to identify failures without logging
provider error bodies. The public chat displays a generic localized message.
