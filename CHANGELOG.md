# Changelog

## [1.9.1] — 2026-07-31

- Reduced the default provider timeout to 20 seconds and the connection timeout
  to 5 seconds.
- Capped provider calls from web workers at 20 seconds so PHP can return before
  the web server's FastCGI timeout; bounded CLI workers may still request a
  longer timeout explicitly.

## [1.9.0] — 2026-07-24

- Added provider-independent `webSearch` and `webSearchMaxResults` request
  options for `ask()` and `stream()`.
- Added OpenRouter web-plugin support for every routed model, native Anthropic
  web-search tools, OpenAI/xAI Responses API search and native Google Search
  grounding.
- Normalized web citations into a provider-independent `sources` result array.
- Included web-search settings in response cache keys and rejected unsupported
  direct adapters explicitly instead of silently returning an unsearched answer.

## [1.8.0] — 2026-07-23

- Added provider-independent `stream()` support for Anthropic and all
  OpenAI-compatible providers, including OpenRouter.
- Streaming callbacks receive text deltas immediately while the returned result
  retains the complete content, usage, provider, and model metadata.
- Streaming requests bypass the response cache so callers never receive a fake
  post-completion typing effect.

## [1.7.0] — 2026-07-13

- Added native AdminThemeUikit/Konkat settings UI support based on `pw-design-system` patterns.
- Reworked provider key management with theme tokens, responsive layouts, provider search and status filters, accessible disclosure controls, key visibility toggles, and clearer save/test states.
- Updated Test Chat and cache management panels to use native UIkit controls and theme-aware feedback states.

## [1.6.1] — 2026-07-12

- Vision inputs now enforce 12,000 px per-side and 32 MP decoded-dimension caps in addition to byte limits.

## [1.6.0] — 2026-07-12

- Refreshed the OpenRouter fallback catalog from the public OpenRouter model API, using `openrouter/auto` as the resilient default plus current provider aliases and named models.
- Added OpenRouter's OpenAI-compatible embeddings endpoint and a fallback embedding-model catalog, allowing Atlas to use an existing OpenRouter key.
- Added a provider-independent `vision()` API for bounded local PNG/JPEG/WebP/GIF inputs, with OpenAI-compatible and Anthropic multimodal request formats.

All notable changes to the Squad module (formerly AiWire) are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/). Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.5.1] — 2026-06-28

### Fixed
- Removed deprecated `curl_close()` calls (no-op since PHP 8.0; emitted a deprecation notice on PHP 8.5).

---

## [1.5.0] — 2026-06-25

The “AiWire → Squad” release: a rename plus a major capability expansion.

### Changed
- **Renamed the module from AiWire to Squad** — class, files, key table, admin UI, log channel. Existing installs migrate automatically on install: the key table is renamed (`aiwire_keys` → `squad_keys`) and settings are carried over, and the encryption KDF context is preserved so stored keys keep working.
- **Encrypted key storage** — provider keys moved out of the module config JSON into a dedicated `squad_keys` table, encrypted with libsodium secretbox (secret derived from a `config.php` salt). A database dump no longer exposes keys. `env:NAME` references still supported.
- **Adaptive-model aware requests** — `temperature`/sampling params are now omitted automatically for models that reject them (Claude Opus 4.7/4.8, Fable/Mythos), preventing 400 errors.
- **Model catalogue refreshed** — Anthropic set updated to current IDs (added Claude Opus 4.8 as default and Claude Fable 5; bare aliases). OpenAI/Google/xAI/OpenRouter left current.

### Added
- **`embed()`** — provider-independent embeddings for a string or a batch (OpenAI, Google, Qwen, Zhipu); `getDefaultEmbedProvider()`.
- **`image()`** — text-to-image generation (xAI Grok Imagine, OpenAI gpt-image-1 / DALL·E 3); `getDefaultImageProvider()`.
- **`run()`** — multi-step tool-use / agent loop supporting both OpenAI and Anthropic tool-calling formats.
- **Direct Chinese providers** — DeepSeek, Qwen (Alibaba DashScope), Kimi (Moonshot), GLM (Zhipu), MiniMax, Yi (01.AI), Doubao (ByteDance/Volcengine), Ernie (Baidu Qianfan), Hunyuan (Tencent), all via the OpenAI-compatible path (14 providers total).
- **Anthropic prompt caching** for large system prompts (automatic for prompts ≥ ~3 KB).

---

## [1.4.0] — 2026-05-15

### Added
- Added dynamic model refresh support for OpenAI and OpenRouter from the admin provider key UI.
- Added `providerModels` config storage for refreshed model lists, separate from static defaults and `models.json`.
- Added per-key `custom_model` support so users can enter private, preview, proxy, or newly released model IDs manually.
- Added public helpers `getProviderModels()` and `refreshProviderModels()`.

### Changed
- Model dropdowns now prefer refreshed provider models, then `models.json`, then built-in defaults.
- Runtime model resolution now prefers per-request `model`, then per-key `custom_model`, then selected key model, then provider default.

---

## [1.3.0] — 2026-05-15

### Added
- Added `models.json` as an editable provider model catalog so model IDs, labels, and defaults can be updated without changing module PHP code.
- Added support for API key references like `env:OPENAI_API_KEY` to avoid storing real secrets in ProcessWire module config.

### Fixed
- Fixed a PHP parse error in the xAI provider definition that prevented the module from loading.
- Added CSRF validation to AiWire admin AJAX actions.
- Escaped JSON embedded in admin HTML and JavaScript to avoid broken markup and reduce XSS risk.
- Included model in cached provider instances so the same key can be used with different selected models safely.
- Restored a page's previous output formatting state after `saveTo()`.

---

## [1.2.0] — 2026-04-23

### Changed
- **Anthropic models:** added Claude Opus 4.7, Claude Sonnet 4.6; default changed to `claude-sonnet-4-6-20260217`; removed deprecated Claude Sonnet 4.5
- **OpenAI models:** added GPT-5.4 family (`gpt-5.4`, `gpt-5.4-mini`, `gpt-5.4-nano`); default changed to `gpt-5.4`; removed deprecated `gpt-5-mini`, `gpt-5-nano`
- **Google models:** added Gemini 3.1 Pro Preview, Gemini 3 Flash, Gemini 3.1 Flash Lite, Gemini 2.5 Flash; default changed to `gemini-3-flash`; removed deprecated `gemini-flash-latest`, `gemini-flash-lite-latest`, `gemini-3-pro-preview`
- **xAI models:** added Grok 4.20, Grok Code Fast 1; removed deprecated Grok 3 Mini
- **OpenRouter models:** added Amazon Nova Micro/Lite, ByteDance Seed 1.6, Xiaomi MiMo V2 Flash, Zhipu AI GLM 5; sorted by company A-Z; updated Anthropic/Google/OpenAI refs to latest; total 19 models from 13 companies

### Improved
- Documentation split into README.md (overview) and DOCUMENTATION.md (full reference)
- All 25 examples now include Problem description, ProcessWire setup table, code, and Result output
- Added Table of Contents with anchor links in both README and DOCUMENTATION
- Added Result Format section explaining return arrays for all methods

---

## [1.1.0] — 2026-02-19

### Added
- **`generate()` method** — multi-block AI content generation with per-block settings (provider, model, temperature, systemPrompt, cache per block)
- Global options with per-block overrides: `generate($page, [['field' => '...', 'prompt' => '...', 'options' => [...]]], $globalOptions)`
- Each block checks field first (skip if content exists), calls AI only when needed
- Returns array keyed by field name with `source: 'ai'|'field'|'error'`
- 25 real-world usage examples based on lqrs.com (spirits/wine catalog)

---

## [1.0.0] — 2026-02-11

### Added

#### Core API
- `chat()` — simple text response, returns string
- `ask()` — full response with `success`, `content`, `usage`, `raw`, `cached`
- `askWithFallback()` — automatic fallback across keys and providers
- `askMultiple()` — same prompt to multiple providers for comparison
- `askAndSave()` — ask AI and save to page field (single or batch)
- `saveTo()` / `loadFrom()` — manual field storage

#### Providers
- 5 providers: Anthropic (Claude), OpenAI (GPT), Google (Gemini), xAI (Grok), OpenRouter (400+ models)
- Unified API across all providers via OpenAI-compatible Chat Completions endpoint
- Multiple API keys per provider with enable/disable toggle
- Default key selector per provider
- `getProvider()` — direct provider instance access
- `getProvidersStatus()` — status of all providers and keys

#### Cache
- File-based cache system (`AiWireCache`)
- TTL support: `'D'` (day), `'W'` (week), `'M'` (month), `'Y'` (year), custom like `'2W'`, `'3M'`
- Page-scoped cache keys via `pageId` option
- `clearCache($page)` — clear cache for specific page
- `clearAllCache()` — clear entire cache
- `cacheStats()` — files count, total size
- Auto-cleanup of expired cache on `LazyCron::everyHour`

#### Field Storage
- Save AI content to any ProcessWire Textarea/Text field
- Skip generation if field already has content (unless `overwrite: true`)
- Quiet save mode (no PW hooks triggered)
- Batch mode: multiple fields from one prompt or field-to-prompt mapping

#### Admin Interface
- AJAX-powered admin UI with per-provider key management
- Connection test button for each key (one-click verify)
- Test Chat panel with parameter controls (provider, model, temperature, maxTokens, timeout)
- Real-time provider status display
- Cache management UI with stats and clear buttons

#### Options
- `provider` — select provider per call
- `model` — override model per call
- `systemPrompt` — system instructions
- `maxTokens` — response length limit
- `temperature` — creativity control (0.0–2.0)
- `history` — conversation history for multi-turn chat
- `keyIndex` — use specific key by index
- `fallbackProviders` — fallback chain
- `cache` — TTL-based caching
- `pageId` — page context for cache scoping
- `timeout` — request timeout
- `overwrite` — force regeneration
- `quiet` — save without triggering hooks

#### Logging
- Standard logging via ProcessWire `wire('log')`
- Debug logging (enable in module config)
- Logs: API calls, errors, cache hits/misses, field saves

---

*← Back to [README.md](README.md) | [DOCUMENTATION.md](DOCUMENTATION.md)*
