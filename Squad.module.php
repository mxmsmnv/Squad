<?php namespace ProcessWire;

/**
 * Squad - AI Integration Module for ProcessWire
 *
 * Connect your ProcessWire site to AI providers: Anthropic, OpenAI, Google, xAI, OpenRouter.
 * Manage multiple API keys, test connections, and use AI in your templates.
 *
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 * @version 1.6.1
 * @see https://github.com/mxmsmnv/Squad
 */

require_once(__DIR__ . '/SquadProvider.php');
require_once(__DIR__ . '/SquadCache.php');
require_once(__DIR__ . '/SquadKeys.php');

class Squad extends WireData implements Module, ConfigurableModule {

    public const MAX_VISION_IMAGES = 4;
    public const MAX_VISION_IMAGE_BYTES = 8 * 1024 * 1024;
    public const MAX_VISION_TOTAL_BYTES = 20 * 1024 * 1024;
    public const MAX_VISION_IMAGE_DIMENSION = 12000;
    public const MAX_VISION_IMAGE_PIXELS = 32000000;

    /**
     * Module information
     */
    public static function getModuleInfo() {
        return [
            'title'    => 'Squad',
            'version'  => '1.6.1',
            'summary'  => __('AI integration for ProcessWire. Supports Anthropic, OpenAI, Google, xAI, and OpenRouter.'),
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon'     => 'brain',
            'singular' => true,
            'autoload' => true,
            'requires' => ['ProcessWire>=3.0.210', 'PHP>=8.1'],
        ];
    }

    /**
     * Supported providers configuration
     */
    const PROVIDERS = [
        'anthropic' => [
            'label'       => 'Anthropic (Claude)',
            'icon'        => 'comment',
            'url'         => 'https://api.anthropic.com/v1/messages',
            'testUrl'     => 'https://api.anthropic.com/v1/messages',
            'docsUrl'     => 'https://docs.anthropic.com/',
            'keyPrefix'   => 'sk-ant-',
            'headerType'  => 'x-api-key',
            'extraHeaders' => [
                'anthropic-version' => '2023-06-01',
            ],
            'defaultModel' => 'claude-opus-4-8',
            'models' => [
                'claude-fable-5'    => 'Claude Fable 5',
                'claude-opus-4-8'   => 'Claude Opus 4.8',
                'claude-opus-4-7'   => 'Claude Opus 4.7',
                'claude-opus-4-6'   => 'Claude Opus 4.6',
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                'claude-haiku-4-5'  => 'Claude Haiku 4.5',
            ],
        ],
        'openai' => [
            'label'       => 'OpenAI (GPT)',
            'icon'        => 'bolt',
            'url'         => 'https://api.openai.com/v1/chat/completions',
            'testUrl'     => 'https://api.openai.com/v1/chat/completions',
            'docsUrl'     => 'https://platform.openai.com/docs/',
            'keyPrefix'   => 'sk-',
            'headerType'  => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'gpt-5.4',
            'models' => [
                'gpt-5.4'      => 'GPT-5.4',
                'gpt-5.4-mini' => 'GPT-5.4 Mini',
                'gpt-5.4-nano' => 'GPT-5.4 Nano',
                'gpt-5.2'      => 'GPT-5.2',
                'gpt-4.1'      => 'GPT-4.1',
            ],
            // embeddings
            'embedUrl'          => 'https://api.openai.com/v1/embeddings',
            'defaultEmbedModel' => 'text-embedding-3-small',
            'embedModels' => [
                'text-embedding-3-small' => 'Embedding 3 Small',
                'text-embedding-3-large' => 'Embedding 3 Large',
            ],
            // image generation
            'imageUrl'          => 'https://api.openai.com/v1/images/generations',
            'defaultImageModel' => 'gpt-image-1',
            'imageModels' => [
                'gpt-image-1' => 'GPT Image 1',
                'dall-e-3'    => 'DALL·E 3',
            ],
        ],
        'google' => [
            'label'       => 'Google (Gemini)',
            'icon'        => 'google',
            'url'         => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'testUrl'     => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
            'docsUrl'     => 'https://ai.google.dev/docs',
            'keyPrefix'   => '',
            'headerType'  => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'gemini-3-flash',
            'models' => [
                'gemini-3.1-pro-preview'   => 'Gemini 3.1 Pro Preview',
                'gemini-3-flash'           => 'Gemini 3 Flash',
                'gemini-3.1-flash-lite'    => 'Gemini 3.1 Flash Lite',
                'gemini-2.5-flash'         => 'Gemini 2.5 Flash',
            ],
            // embeddings
            'embedUrl'          => 'https://generativelanguage.googleapis.com/v1beta/openai/embeddings',
            'defaultEmbedModel' => 'gemini-embedding-001',
            'embedModels' => [
                'gemini-embedding-001' => 'Gemini Embedding 001',
                'text-embedding-004'   => 'Text Embedding 004',
            ],
        ],
        'xai' => [
            'label'       => 'xAI (Grok)',
            'icon'        => 'rocket',
            'url'         => 'https://api.x.ai/v1/chat/completions',
            'testUrl'     => 'https://api.x.ai/v1/chat/completions',
            'docsUrl'     => 'https://docs.x.ai/',
            'keyPrefix'   => 'xai-',
            'headerType'  => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'grok-4-1-fast-non-reasoning',
            'models' => [
                'grok-4.20'                   => 'Grok 4.20',
                'grok-4-1-fast-reasoning'     => 'Grok 4.1 Fast (Reasoning)',
                'grok-4-1-fast-non-reasoning' => 'Grok 4.1 Fast',
                'grok-code-fast-1'            => 'Grok Code Fast 1',
            ],
            // image generation (Imagine)
            'imageUrl'          => 'https://api.x.ai/v1/images/generations',
            'defaultImageModel' => 'grok-imagine-image',
            'imageModels' => [
                'grok-imagine-image' => 'Grok Imagine (Standard)',
            ],
        ],
        'openrouter' => [
            'label'       => 'OpenRouter',
            'icon'        => 'exchange',
            'url'         => 'https://openrouter.ai/api/v1/chat/completions',
            'testUrl'     => 'https://openrouter.ai/api/v1/chat/completions',
            'docsUrl'     => 'https://openrouter.ai/docs',
            'keyPrefix'   => 'sk-or-',
            'headerType'  => 'bearer',
            'extraHeaders' => [
                'HTTP-Referer' => '',
            ],
            'defaultModel' => 'deepseek/deepseek-v3.2',
            'models' => [
                // Amazon
                'amazon/nova-micro-v1'                      => 'Nova Micro',
                'amazon/nova-2-lite-v1'                     => 'Nova 2 Lite',
                // Anthropic
                'anthropic/claude-sonnet-4.6'               => 'Claude Sonnet 4.6 (via OR)',
                // ByteDance
                'bytedance-seed/seed-1.6'                   => 'Seed 1.6',
                // DeepSeek
                'deepseek/deepseek-v3.2'                    => 'DeepSeek V3.2',
                // Google
                'google/gemini-3-flash'                     => 'Gemini 3 Flash',
                'google/gemini-2.5-flash'                   => 'Gemini 2.5 Flash',
                // Meta
                'meta-llama/llama-4-maverick'               => 'Llama 4 Maverick',
                'meta-llama/llama-3.3-70b-instruct'         => 'Llama 3.3 70B',
                // MiniMax
                'minimax/minimax-m2.1'                      => 'MiniMax M2.1',
                // Mistral
                'mistralai/devstral-2512'                   => 'Devstral 2512',
                'mistralai/mistral-small-3.2-24b-instruct'  => 'Mistral Small 3.2 24B',
                // NVIDIA
                'nvidia/nemotron-3-nano-30b-a3b'            => 'Nemotron 3 Nano 30B',
                // OpenAI
                'openai/gpt-5.4'                            => 'GPT-5.4 (via OR)',
                // Qwen (Alibaba)
                'qwen/qwen3-max-thinking'                   => 'Qwen 3 Max Thinking',
                // Xiaomi
                'xiaomi/mimo-v2-flash'                      => 'MiMo V2 Flash',
                // xAI
                'x-ai/grok-4-1-fast'                        => 'Grok 4.1 Fast (via OR)',
                // Zhipu AI
                'z-ai/glm-4.7'                              => 'GLM 4.7',
                'z-ai/glm-5'                                => 'GLM 5',
            ],
            // OpenRouter exposes an OpenAI-compatible embeddings API as well.
            'embedUrl'          => 'https://openrouter.ai/api/v1/embeddings',
            'defaultEmbedModel' => 'openai/text-embedding-3-small',
            'embedModels' => [
                'google/gemini-embedding-2'          => 'Gemini Embedding 2 (via OR)',
                'google/gemini-embedding-001'        => 'Gemini Embedding 001 (via OR)',
                'openai/text-embedding-3-small'      => 'OpenAI Text Embedding 3 Small (via OR)',
                'openai/text-embedding-3-large'      => 'OpenAI Text Embedding 3 Large (via OR)',
                'qwen/qwen3-embedding-8b'            => 'Qwen3 Embedding 8B (via OR)',
                'baai/bge-m3'                        => 'BAAI BGE-M3 (via OR)',
            ],
        ],

        // ── Direct Chinese providers (OpenAI-compatible /chat/completions) ───────
        // Endpoints are stable; model IDs are sensible starters — verify/extend per
        // provider docs (per-key custom_model + models.json overrides both apply).
        'deepseek' => [
            'label'        => 'DeepSeek',
            'icon'         => 'compass',
            'url'          => 'https://api.deepseek.com/v1/chat/completions',
            'testUrl'      => 'https://api.deepseek.com/v1/chat/completions',
            'docsUrl'      => 'https://api-docs.deepseek.com/',
            'keyPrefix'    => 'sk-',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'deepseek-chat',
            'models' => [
                'deepseek-chat'     => 'DeepSeek V3.2 (chat)',
                'deepseek-reasoner' => 'DeepSeek V3.2 (reasoner)',
            ],
        ],
        'qwen' => [
            'label'        => 'Qwen (Alibaba)',
            'icon'         => 'cube',
            'url'          => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions',
            'testUrl'      => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions',
            'docsUrl'      => 'https://www.alibabacloud.com/help/en/model-studio/',
            'keyPrefix'    => 'sk-',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'qwen-plus',
            'models' => [
                'qwen3-max'  => 'Qwen3 Max',
                'qwen-max'   => 'Qwen Max',
                'qwen-plus'  => 'Qwen Plus',
                'qwen-turbo' => 'Qwen Turbo',
            ],
            // embeddings
            'embedUrl'          => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/embeddings',
            'defaultEmbedModel' => 'text-embedding-v4',
            'embedModels' => [
                'text-embedding-v4' => 'Text Embedding v4',
                'text-embedding-v3' => 'Text Embedding v3',
            ],
        ],
        'moonshot' => [
            'label'        => 'Moonshot (Kimi)',
            'icon'         => 'moon',
            'url'          => 'https://api.moonshot.ai/v1/chat/completions',
            'testUrl'      => 'https://api.moonshot.ai/v1/chat/completions',
            'docsUrl'      => 'https://platform.moonshot.ai/docs',
            'keyPrefix'    => 'sk-',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'kimi-k2-0905-preview',
            'models' => [
                'kimi-k2-0905-preview'  => 'Kimi K2 (0905)',
                'kimi-k2-turbo-preview' => 'Kimi K2 Turbo',
                'kimi-latest'           => 'Kimi (latest)',
                'moonshot-v1-128k'      => 'Moonshot v1 128k',
            ],
        ],
        'zhipu' => [
            'label'        => 'Zhipu (GLM)',
            'icon'         => 'bolt',
            'url'          => 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
            'testUrl'      => 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
            'docsUrl'      => 'https://docs.bigmodel.cn/',
            'keyPrefix'    => '',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'glm-4.6',
            'models' => [
                'glm-4.6'     => 'GLM-4.6',
                'glm-4.5'     => 'GLM-4.5',
                'glm-4.5-air' => 'GLM-4.5 Air',
                'glm-4-plus'  => 'GLM-4 Plus',
            ],
            // embeddings
            'embedUrl'          => 'https://open.bigmodel.cn/api/paas/v4/embeddings',
            'defaultEmbedModel' => 'embedding-3',
            'embedModels' => [
                'embedding-3' => 'Embedding-3',
            ],
        ],
        'minimax' => [
            'label'        => 'MiniMax',
            'icon'         => 'fire',
            'url'          => 'https://api.minimaxi.com/v1/text/chatcompletion_v2',
            'testUrl'      => 'https://api.minimaxi.com/v1/text/chatcompletion_v2',
            'docsUrl'      => 'https://www.minimaxi.com/document',
            'keyPrefix'    => '',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'MiniMax-M2',
            'models' => [
                'MiniMax-M2'      => 'MiniMax M2',
                'MiniMax-Text-01' => 'MiniMax Text 01',
            ],
        ],
        'yi' => [
            'label'        => '01.AI (Yi)',
            'icon'         => 'circle',
            'url'          => 'https://api.lingyiwanwu.com/v1/chat/completions',
            'testUrl'      => 'https://api.lingyiwanwu.com/v1/chat/completions',
            'docsUrl'      => 'https://platform.lingyiwanwu.com/docs',
            'keyPrefix'    => '',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'yi-lightning',
            'models' => [
                'yi-lightning' => 'Yi Lightning',
                'yi-large'     => 'Yi Large',
            ],
        ],
        'doubao' => [
            'label'        => 'Doubao (ByteDance/Volcengine)',
            'icon'         => 'cube',
            'url'          => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
            'testUrl'      => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
            'docsUrl'      => 'https://www.volcengine.com/docs/82379',
            'keyPrefix'    => '',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'doubao-1-5-pro-32k',
            'models' => [
                'doubao-1-5-pro-32k'  => 'Doubao 1.5 Pro 32k',
                'doubao-pro-32k'      => 'Doubao Pro 32k',
                'doubao-1-5-lite-32k' => 'Doubao 1.5 Lite 32k',
            ],
        ],
        'ernie' => [
            'label'        => 'Ernie (Baidu Qianfan)',
            'icon'         => 'paw',
            'url'          => 'https://qianfan.baidubce.com/v2/chat/completions',
            'testUrl'      => 'https://qianfan.baidubce.com/v2/chat/completions',
            'docsUrl'      => 'https://cloud.baidu.com/doc/qianfan-api/',
            'keyPrefix'    => '',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'ernie-4.5-turbo-128k',
            'models' => [
                'ernie-4.5-turbo-128k' => 'ERNIE 4.5 Turbo 128k',
                'ernie-4.0-turbo-8k'   => 'ERNIE 4.0 Turbo 8k',
                'ernie-4.0-8k'         => 'ERNIE 4.0 8k',
                'ernie-speed-128k'     => 'ERNIE Speed 128k',
            ],
        ],
        'hunyuan' => [
            'label'        => 'Hunyuan (Tencent)',
            'icon'         => 'shield',
            'url'          => 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions',
            'testUrl'      => 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions',
            'docsUrl'      => 'https://cloud.tencent.com/document/product/1729',
            'keyPrefix'    => '',
            'headerType'   => 'bearer',
            'extraHeaders' => [],
            'defaultModel' => 'hunyuan-turbos-latest',
            'models' => [
                'hunyuan-turbos-latest' => 'Hunyuan TurboS (latest)',
                'hunyuan-pro'           => 'Hunyuan Pro',
                'hunyuan-standard'      => 'Hunyuan Standard',
                'hunyuan-large'         => 'Hunyuan Large',
            ],
        ],
    ];

    /**
     * Default configuration
     */
    protected static $defaultConfig = [
        'providers'          => '{}', // JSON: {provider: [{key, label, model, enabled, status}]}
        'providerModels'     => '{}', // JSON: {provider: {updated, models}}
        'defaultProvider'    => 'anthropic',
        'defaultKeyIndex'    => '',
        'defaultModel'       => '',
        'systemPrompt'       => '',
        'maxTokens'          => 1024,
        'temperature'        => 0.7,
        'timeout'            => 30,
        'enableCache'        => false,
        'defaultCacheTtl'    => 'D',
        'enableLogging'      => true,
        'enableDebugLogging' => false,
        'logName'            => 'squad',
    ];

    /** @var SquadProvider[] cached provider instances */
    protected $providerInstances = [];

    /** @var SquadCache cache instance */
    protected $cache = null;

    /** @var array|null provider definitions merged with models.json */
    protected $providerDefinitions = null;

    /**
     * Initialize the module
     */
    public function init() {
        foreach (self::$defaultConfig as $key => $value) {
            if ($this->$key === null) $this->set($key, $value);
        }

        // Initialize cache
        $this->cache = new SquadCache(null, !empty($this->enableDebugLogging));
    }

    /**
     * Install — create the encrypted key table (renaming a pre-Squad table if
     * present) and, when upgrading in place from the former "AiWire" module,
     * carry over its settings.
     */
    public function ___install() {
        $this->keys()->ensureTable();
        $this->migrateFromAiWire();
    }

    /**
     * One-time settings migration from the pre-rename "AiWire" module: copy its
     * config (default provider/model, system prompt, etc.) into Squad if Squad
     * has none yet and AiWire's config still exists. Keys are NOT here — they
     * already live in the (renamed) encrypted table. Safe no-op on a fresh install.
     */
    protected function migrateFromAiWire(): void {
        try {
            $modules = $this->wire('modules');
            $mine = $modules->getModuleConfigData($this);
            if (!empty($mine['defaultProvider']) || !empty($mine['systemPrompt'])) return;

            $old = $modules->getModuleConfigData('AiWire');
            if (!is_array($old) || !$old) return;

            unset($old['providers']); // legacy plaintext key blob — never carry it over
            $merged = array_merge($mine, $old);
            $merged['providers'] = '{}';
            $modules->saveModuleConfigData($this, $merged);
            $this->log('Migrated settings from AiWire module');
        } catch (\Throwable $e) {
            $this->error('Squad: settings migration from AiWire failed: ' . $e->getMessage());
        }
    }

    /**
     * Upgrade — ensure the key table exists and migrate any plaintext keys from
     * the legacy `providers` config JSON into encrypted storage.
     */
    public function ___upgrade($fromVersion, $toVersion) {
        $this->keys()->ensureTable();
        $this->migrateKeysToTable();
    }

    /**
     * Uninstall — drop the encrypted key table.
     */
    public function ___uninstall() {
        $this->keys()->dropTable();
    }

    /**
     * Ready - handle AJAX requests and schedule cache cleanup
     */
    public function ready() {
        if ($this->wire('config')->ajax && $this->wire('input')->post('squad_action')) {
            $this->handleAjaxRequest();
        }

        // Auto-clean expired cache once per day via LazyCron
        $this->addHook('LazyCron::everyDay', $this, 'hookCleanExpiredCache');
    }

    /**
     * LazyCron hook: clean expired cache files
     */
    public function hookCleanExpiredCache(HookEvent $event) {
        $count = $this->cache->cleanExpired();
        if ($count) {
            $this->log("Cache cleanup: removed {$count} expired files");
        }
    }

    /**
     * Get provider definitions with editable model data merged from models.json.
     */
    public function getProviderDefinitions(): array {
        if ($this->providerDefinitions !== null) {
            return $this->providerDefinitions;
        }

        $providers = self::PROVIDERS;
        $modelsFile = __DIR__ . '/models.json';

        if (is_file($modelsFile) && is_readable($modelsFile)) {
            $decoded = json_decode(file_get_contents($modelsFile), true);
            if (is_array($decoded)) {
                foreach ($decoded as $providerKey => $modelConfig) {
                    if (!isset($providers[$providerKey]) || !is_array($modelConfig)) continue;

                    if (!empty($modelConfig['defaultModel']) && is_string($modelConfig['defaultModel'])) {
                        $providers[$providerKey]['defaultModel'] = $modelConfig['defaultModel'];
                    }

                    if (!empty($modelConfig['models']) && is_array($modelConfig['models'])) {
                        $models = [];
                        foreach ($modelConfig['models'] as $modelKey => $label) {
                            $modelKey = trim((string)$modelKey);
                            if ($modelKey === '') continue;
                            $models[$modelKey] = trim((string)$label) ?: $modelKey;
                        }
                        if ($models) {
                            $providers[$providerKey]['models'] = $models;
                        }
                    }
                }
            } else {
                $this->logError('models.json is invalid: ' . json_last_error_msg());
            }
        }

        $this->providerDefinitions = $providers;
        return $this->providerDefinitions;
    }

    /**
     * Get a single provider definition.
     */
    protected function getProviderDefinition(string $providerKey): ?array {
        $providers = $this->getProviderDefinitions();
        return $providers[$providerKey] ?? null;
    }

    /**
     * Get known models for a provider, preferring refreshed models over defaults.
     */
    public function getProviderModels(string $providerKey): array {
        $refreshed = json_decode($this->providerModels ?: '{}', true) ?: [];
        $models = $refreshed[$providerKey]['models'] ?? [];

        if (is_array($models) && $models) {
            return $models;
        }

        $config = $this->getProviderDefinition($providerKey);
        return is_array($config['models'] ?? null) ? $config['models'] : [];
    }

    /**
     * Get timestamp metadata for a refreshed provider model list.
     */
    public function getProviderModelsUpdated(string $providerKey): string {
        $refreshed = json_decode($this->providerModels ?: '{}', true) ?: [];
        return (string)($refreshed[$providerKey]['updated'] ?? '');
    }

    /**
     * Refresh provider models and store them in module config.
     */
    public function refreshProviderModels(string $providerKey, ?string $apiKey = null, ?int $keyIndex = null): array {
        $config = $this->getProviderDefinition($providerKey);
        if (!$config) {
            return ['success' => false, 'models' => [], 'message' => "Unknown provider: {$providerKey}"];
        }

        if ($apiKey === null && $keyIndex !== null) {
            $keys = $this->getProviderKeys($providerKey);
            $apiKey = $keys[$keyIndex]['key'] ?? null;
        }

        if ($apiKey === null || trim($apiKey) === '') {
            $provider = $this->getProvider($providerKey);
        } else {
            $resolvedKey = $this->resolveApiKey(trim($apiKey));
            if ($resolvedKey === '') {
                return ['success' => false, 'models' => [], 'message' => 'API key is empty or environment variable is not set'];
            }
            $provider = new SquadProvider($providerKey, $config, $resolvedKey, $config['defaultModel'], [
                'timeout' => (int)$this->timeout,
            ]);
        }

        if (!$provider) {
            return ['success' => false, 'models' => [], 'message' => "No active provider found for '{$providerKey}'"];
        }

        $result = $provider->fetchModels();
        if (empty($result['success']) || empty($result['models']) || !is_array($result['models'])) {
            return [
                'success' => false,
                'models'  => [],
                'message' => $result['message'] ?? 'Could not fetch models from provider.',
            ];
        }

        $providerModels = json_decode($this->providerModels ?: '{}', true) ?: [];
        $providerModels[$providerKey] = [
            'updated' => date('Y-m-d H:i:s'),
            'models'  => $result['models'],
        ];

        $configData = $this->wire('modules')->getModuleConfigData($this);
        $configData['providerModels'] = json_encode($providerModels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->wire('modules')->saveModuleConfigData($this, $configData);
        $this->set('providerModels', $configData['providerModels']);

        return [
            'success' => true,
            'models'  => $result['models'],
            'updated' => $providerModels[$providerKey]['updated'],
            'message' => 'Models refreshed.',
        ];
    }

    /**
     * Encode data for safe use in an HTML attribute.
     */
    protected function jsonAttribute(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return htmlspecialchars($json === false ? '{}' : $json, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Encode data safely for inline JavaScript.
     */
    protected function jsonScript($data): string {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        return $json === false ? 'null' : $json;
    }

    /**
     * CSRF fields for admin AJAX calls.
     */
    protected function getCsrfFields(): array {
        $csrf = $this->wire('session')->CSRF;
        return [$csrf->getTokenName() => $csrf->getTokenValue()];
    }

    protected function getCsrfFieldsJson(): string {
        return $this->jsonScript($this->getCsrfFields());
    }

    /**
     * Resolve env:NAME API key references without storing the secret in module config.
     */
    protected function resolveApiKey(string $apiKey): string {
        if (str_starts_with($apiKey, 'env:')) {
            $envName = trim(substr($apiKey, 4));
            return $envName !== '' ? (string)getenv($envName) : '';
        }

        return $apiKey;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Send a message to AI and get a response
     *
     * Cache priority:
     *   - 'cache' explicitly in $options → that value wins (TTL string/int = ON, false = OFF)
     *   - 'cache' NOT in $options → global enableCache setting applies
     *   Global OFF + code 'W' = cached. Global ON + code false = not cached.
     *
     * @param string $message User message
     * @param array $options Optional overrides:
     *   provider, model, systemPrompt, maxTokens, temperature, history, key, keyIndex
     *   cache: true|'D'|'W'|'M'|'Y'|'2D'|'3W'|int(seconds)|false — cache TTL
     *   pageId: int|Page — page context for cache (0 = global)
     * @return array ['success', 'content', 'usage', 'raw', 'cached']
     */
    public function ask(string $message, array $options = []): array {
        // ── Resolve cache: code-level override > global default ──
        if (array_key_exists('cache', $options)) {
            $cacheOption = $options['cache'];
        } elseif ($this->enableCache) {
            $cacheOption = $this->defaultCacheTtl ?: 'D';
        } else {
            $cacheOption = false;
        }

        // true → use default TTL
        if ($cacheOption === true) {
            $cacheOption = $this->defaultCacheTtl ?: 'D';
        }

        $useCache = ($cacheOption !== false && $cacheOption !== null);

        // ── Resolve page ID ──
        $pageId = $options['pageId'] ?? ($options['page'] ?? null);
        if ($pageId instanceof Page) {
            $pageId = $pageId->id;
        }
        $pageId = $pageId ? (int)$pageId : 0;

        // ── Check cache ──
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($message, $options, $pageId);
            if ($cached !== null) {
                $cached['cached'] = true;
                $this->debugLog("ask() cache hit, page={$pageId}");
                return $cached;
            }
        }

        // ── Send request ──
        $providerKey = $options['provider'] ?? $this->getDefaultProviderKey();
        $provider = $this->getProvider($providerKey, $options['key'] ?? null, $options['keyIndex'] ?? null);

        if (!$provider) {
            return $this->errorResponse("No active provider found for '{$providerKey}'");
        }

        $model       = $options['model'] ?? $provider->getModel();
        $systemPrompt = $options['systemPrompt'] ?? $this->systemPrompt;
        $maxTokens   = (int)($options['maxTokens'] ?? $this->maxTokens);
        $temperature = (float)($options['temperature'] ?? $this->temperature);
        $timeout     = isset($options['timeout']) ? (int)$options['timeout'] : null;
        $history     = $options['history'] ?? [];

        // Anthropic prompt caching: cache the (large) system prompt so repeated
        // calls reuse the cached prefix cheaply. On by default for Anthropic when
        // the system prompt is sizeable; override with options['promptCache'].
        $cachePrompt = array_key_exists('promptCache', $options)
            ? (bool)$options['promptCache']
            : ($providerKey === 'anthropic' && mb_strlen($systemPrompt) >= 3000);

        // Apply per-request timeout if specified
        if ($timeout) {
            $provider->setTimeout($timeout);
        }

        $this->debugLog("ask() provider={$providerKey} model={$model} cache=" . ($useCache ? $cacheOption : 'off') . " promptCache=" . ($cachePrompt ? 'on' : 'off'));

        try {
            $result = $provider->sendMessage($message, [
                'model'        => $model,
                'systemPrompt' => $systemPrompt,
                'maxTokens'    => $maxTokens,
                'temperature'  => $temperature,
                'history'      => $history,
                'cachePrompt'  => $cachePrompt,
            ]);

            if ($result['success']) {
                $this->log("Response from {$providerKey}/{$model} — " .
                    ($result['usage']['total_tokens'] ?? '?') . " tokens");

                // Save to cache
                if ($useCache && $this->cache) {
                    $this->cache->set($message, $options, $result, $cacheOption, $pageId);
                }
            }

            $result['cached'] = false;
            return $result;

        } catch (\Throwable $e) {
            $this->logError("ask() error: " . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Generate an image from a text prompt (Imagine).
     *
     * @param string $prompt
     * @param array $options provider, model, n, aspect, resolution, size, timeout, key, keyIndex
     * @return array ['success','url','b64','model','provider','message','raw']
     */
    public function image(string $prompt, array $options = []): array {
        $prompt = trim($prompt);
        if ($prompt === '') return $this->errorResponse('Empty image prompt.');

        $providerKey = $options['provider'] ?? $this->getDefaultImageProvider();
        if (!$providerKey) return $this->errorResponse('No image-capable provider is configured.');

        $provider = $this->getProvider($providerKey, $options['key'] ?? null, $options['keyIndex'] ?? null);
        if (!$provider) return $this->errorResponse("No active key for image provider '{$providerKey}'.");

        if (!empty($options['timeout'])) $provider->setTimeout((int)$options['timeout']);

        $def = $this->getProviderDefinition($providerKey) ?: [];
        $opts = array_merge($options, [
            'imageUrl' => $def['imageUrl'] ?? '',
            'model'    => $options['model'] ?? ($def['defaultImageModel'] ?? ''),
        ]);

        try {
            $result = $provider->generateImage($prompt, $opts);
            if (!empty($result['success'])) {
                $this->log("Image from {$providerKey}/" . ($result['model'] ?? '?'));
            } else {
                $this->logError("image() error: " . ($result['message'] ?? 'unknown'));
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logError("image() error: " . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Analyze local images or image data URLs with a multimodal chat model.
     * Remote URLs are deliberately rejected; callers control acquisition and SSRF policy.
     */
    public function vision(string $prompt, array $images, array $options = []): array {
        $prompt = trim($prompt);
        if($prompt === '') return $this->errorResponse('Empty vision prompt.');
        if(!$images) return $this->errorResponse('No vision images supplied.');
        if(count($images) > self::MAX_VISION_IMAGES) return $this->errorResponse('Too many vision images.');

        $normalized = [];
        $total = 0;
        foreach($images as $image) {
            $dataUrl = $this->visionDataUrl((string)$image, $bytes, $error);
            if($dataUrl === '') return $this->errorResponse($error ?: 'Invalid vision image.');
            $total += $bytes;
            if($total > self::MAX_VISION_TOTAL_BYTES) return $this->errorResponse('Vision images exceed the total byte limit.');
            $normalized[] = $dataUrl;
        }

        $providerKey = $options['provider'] ?? $this->getDefaultProviderKey();
        $provider = $this->getProvider($providerKey, $options['key'] ?? null, $options['keyIndex'] ?? null);
        if(!$provider) return $this->errorResponse("No active provider found for '{$providerKey}'");
        if(!empty($options['timeout'])) $provider->setTimeout((int)$options['timeout']);
        $model = $options['model'] ?? $provider->getModel();

        try {
            $result = $provider->analyzeImages($prompt, $normalized, [
                'model' => $model,
                'systemPrompt' => $options['systemPrompt'] ?? $this->systemPrompt,
                'maxTokens' => (int)($options['maxTokens'] ?? $this->maxTokens),
                'temperature' => (float)($options['temperature'] ?? 0.2),
                'detail' => $options['detail'] ?? 'high',
            ]);
            if(!empty($result['success'])) $this->log("Vision response from {$providerKey}/{$model}");
            else $this->logError('vision() error: ' . ($result['message'] ?? 'unknown'));
            return $result;
        } catch(\Throwable $e) {
            $this->logError('vision() error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    protected function visionDataUrl(string $input, ?int &$bytes = null, ?string &$error = null): string {
        $bytes = 0;
        $error = '';
        $mime = '';
        $data = '';
        if(str_starts_with($input, 'data:')) {
            if(!preg_match('#^data:(image/(?:png|jpeg|webp|gif));base64,(.+)$#s', $input, $m)) {
                $error = 'Vision data URL must be PNG, JPEG, WebP, or GIF.';
                return '';
            }
            $mime = $m[1];
            $data = base64_decode($m[2], true);
            if(!is_string($data)) { $error = 'Vision image contains invalid base64 data.'; return ''; }
        } else {
            if($input === '' || !is_file($input) || !is_readable($input)) { $error = 'Vision image file is missing or unreadable.'; return ''; }
            $size = @filesize($input);
            if($size === false || $size <= 0 || $size > self::MAX_VISION_IMAGE_BYTES) { $error = 'Vision image file exceeds the byte limit.'; return ''; }
            $info = @getimagesize($input);
            $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
            if(!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) { $error = 'Vision image must be PNG, JPEG, WebP, or GIF.'; return ''; }
            if(!$this->validVisionDimensions($info)) { $error = 'Vision image dimensions exceed the safety limit.'; return ''; }
            $data = @file_get_contents($input);
            if(!is_string($data)) { $error = 'Vision image could not be read.'; return ''; }
        }
        $bytes = strlen($data);
        if($bytes < 1 || $bytes > self::MAX_VISION_IMAGE_BYTES) { $error = 'Vision image exceeds the byte limit.'; return ''; }
        $decoded = @getimagesizefromstring($data);
        if(!is_array($decoded) || (string)($decoded['mime'] ?? '') !== $mime) { $error = 'Vision image data is invalid or does not match its MIME type.'; return ''; }
        if(!$this->validVisionDimensions($decoded)) { $error = 'Vision image dimensions exceed the safety limit.'; return ''; }
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    protected function validVisionDimensions(array $info): bool {
        $width = (int)($info[0] ?? 0);
        $height = (int)($info[1] ?? 0);
        if($width < 1 || $height < 1 || $width > self::MAX_VISION_IMAGE_DIMENSION || $height > self::MAX_VISION_IMAGE_DIMENSION) return false;
        return $width <= intdiv(self::MAX_VISION_IMAGE_PIXELS, $height);
    }

    /**
     * Run a tool-use (agentic) conversation: the model can call the tools you
     * provide, you execute them via the onTool callback, and the loop continues
     * until the model returns a final answer or maxSteps is hit.
     *
     * @param array $options
     *   - message|prompt : string, OR messages : array of role/content
     *   - systemPrompt   : string
     *   - tools          : [['name'=>, 'description'=>, 'parameters'=>JSON-schema], ...]
     *   - onTool         : callable(string $name, array $input): string|array
     *   - maxSteps       : int (default 6)
     *   - model/provider/key/keyIndex/maxTokens/temperature/timeout
     * @return array ['success','content','steps','messages','usage','message']
     */
    public function run(array $options = []): array {
        $providerKey = $options['provider'] ?? $this->getDefaultProviderKey();
        $provider = $this->getProvider($providerKey, $options['key'] ?? null, $options['keyIndex'] ?? null);
        if (!$provider) return $this->errorResponse("No active provider found for '{$providerKey}'");
        if (!empty($options['timeout'])) $provider->setTimeout((int)$options['timeout']);

        $messages = $options['messages'] ?? [];
        if (!$messages && isset($options['message'])) $messages = [['role' => 'user', 'content' => (string)$options['message']]];
        if (!$messages && isset($options['prompt'])) $messages = [['role' => 'user', 'content' => (string)$options['prompt']]];

        $onTool   = $options['onTool'] ?? null;
        $maxSteps = max(1, (int)($options['maxSteps'] ?? 6));
        $turnOpts = [
            'model'        => $options['model'] ?? $provider->getModel(),
            'systemPrompt' => $options['systemPrompt'] ?? $this->systemPrompt,
            'maxTokens'    => (int)($options['maxTokens'] ?? $this->maxTokens),
            'temperature'  => (float)($options['temperature'] ?? $this->temperature),
            'tools'        => $options['tools'] ?? [],
        ];

        $steps = 0;
        for ($i = 0; $i < $maxSteps; $i++) {
            $res = $provider->runTools($messages, $turnOpts);
            if (empty($res['success'])) return $res;
            $steps++;

            if (empty($res['tool_calls'])) {
                return ['success' => true, 'content' => $res['content'], 'steps' => $steps, 'messages' => $messages, 'usage' => $res['usage'] ?? [], 'message' => 'OK'];
            }

            // record the assistant's tool-call turn, then run each tool and feed results
            // back in the provider's own format (OpenAI 'tool' messages vs Anthropic
            // tool_result blocks).
            $messages[] = $res['assistant'];
            $results = [];
            foreach ($res['tool_calls'] as $tc) {
                $result = '';
                if (is_callable($onTool)) {
                    try { $result = $onTool($tc['name'], $tc['arguments']); }
                    catch (\Throwable $e) { $result = 'Error: ' . $e->getMessage(); }
                } else {
                    $result = "No tool handler provided for '{$tc['name']}'.";
                }
                if (is_array($result)) $result = json_encode($result);
                $results[] = ['id' => $tc['id'], 'name' => $tc['name'], 'content' => (string)$result];
            }
            foreach ($provider->formatToolResults($results) as $m) $messages[] = $m;
        }

        return ['success' => true, 'content' => '', 'steps' => $steps, 'messages' => $messages, 'message' => "Stopped after {$maxSteps} steps without a final answer."];
    }

    /**
     * The first image-capable provider that has an active key (prefers xAI).
     */
    public function getDefaultImageProvider(): ?string {
        $defs = $this->getProviderDefinitions();
        foreach (array_merge(['xai'], array_keys($defs)) as $key) {
            $def = $defs[$key] ?? null;
            if (!$def || empty($def['imageUrl'])) continue;
            if ($this->getProvider($key)) return $key;
        }
        return null;
    }

    /**
     * Create embeddings for one string or an array of strings (OpenAI-compatible).
     *
     * @param string|array $input text, or array of texts
     * @param array $options provider, model, timeout, key, keyIndex
     * @return array ['success','embeddings'=>[[float,...],...],'embedding'=>[float,...]|null,'model','provider','usage','message','raw']
     */
    public function embed($input, array $options = []): array {
        $texts = is_array($input) ? array_values($input) : [(string)$input];
        $texts = array_values(array_filter(array_map(fn($t) => (string)$t, $texts), fn($t) => $t !== ''));
        if (!$texts) return $this->errorResponse('Empty embedding input.');

        $providerKey = $options['provider'] ?? $this->getDefaultEmbedProvider();
        if (!$providerKey) return $this->errorResponse('No embedding-capable provider is configured.');

        $provider = $this->getProvider($providerKey, $options['key'] ?? null, $options['keyIndex'] ?? null);
        if (!$provider) return $this->errorResponse("No active key for embedding provider '{$providerKey}'.");

        if (!empty($options['timeout'])) $provider->setTimeout((int)$options['timeout']);

        $def = $this->getProviderDefinition($providerKey) ?: [];
        $opts = array_merge($options, [
            'embedUrl' => $def['embedUrl'] ?? '',
            'model'    => $options['model'] ?? ($def['defaultEmbedModel'] ?? ''),
        ]);

        try {
            $result = $provider->generateEmbeddings($texts, $opts);
            if (!empty($result['success'])) {
                $n = count($result['embeddings'] ?? []);
                $this->log("Embeddings from {$providerKey}/" . ($result['model'] ?? '?') . " ({$n})");
                // convenience: a single-string caller gets the lone vector directly
                $result['embedding'] = (!is_array($input) && $n === 1) ? $result['embeddings'][0] : null;
            } else {
                $this->logError("embed() error: " . ($result['message'] ?? 'unknown'));
            }
            return $result;
        } catch (\Throwable $e) {
            $this->logError("embed() error: " . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    /** The first embedding-capable provider that has an active key. */
    public function getDefaultEmbedProvider(): ?string {
        foreach ($this->getProviderDefinitions() as $key => $def) {
            if (empty($def['embedUrl'])) continue;
            if ($this->getProvider($key)) return $key;
        }
        return null;
    }

    /**
     * Send a message with automatic fallback through all enabled keys
     *
     * If the first key fails (rate limit, quota, error), tries the next enabled key.
     * Optionally falls back to other providers.
     *
     * @param string $message
     * @param array $options Same as ask(), plus 'fallbackProviders' => ['openai', 'xai']
     * @return array Same as ask(), with extra 'usedProvider' and 'usedKeyIndex'
     */
    public function askWithFallback(string $message, array $options = []): array {
        $providerKey = $options['provider'] ?? $this->getDefaultProviderKey();
        $fallbackProviders = $options['fallbackProviders'] ?? [];

        // Try all keys for the primary provider
        $result = $this->tryAllKeys($providerKey, $message, $options);
        if ($result['success']) return $result;

        // Try fallback providers
        foreach ($fallbackProviders as $fbProvider) {
            if ($fbProvider === $providerKey) continue;
            $this->debugLog("Falling back to provider: {$fbProvider}");
            $fbOptions = $options;
            $fbOptions['provider'] = $fbProvider;
            unset($fbOptions['key'], $fbOptions['keyIndex'], $fbOptions['model']);

            $result = $this->tryAllKeys($fbProvider, $message, $fbOptions);
            if ($result['success']) return $result;
        }

        return $this->errorResponse("All keys and fallback providers failed for message");
    }

    /**
     * Try all enabled keys for a provider
     *
     * @param string $providerKey
     * @param string $message
     * @param array $options
     * @return array
     */
    protected function tryAllKeys(string $providerKey, string $message, array $options): array {
        $keys = $this->getProviderKeys($providerKey);
        $lastError = '';

        foreach ($keys as $i => $keyData) {
            if (empty($keyData['enabled']) || empty($keyData['key'])) continue;

            $this->debugLog("Trying {$providerKey} key #{$i}" . ($keyData['label'] ? " ({$keyData['label']})" : ''));

            $keyOptions = $options;
            $keyOptions['provider'] = $providerKey;
            $keyOptions['key'] = $keyData['key'];
            if (!isset($keyOptions['model'])) {
                $keyOptions['model'] = trim((string)($keyData['custom_model'] ?? ''))
                    ?: trim((string)($keyData['model'] ?? ''))
                    ?: null;
            }

            $result = $this->ask($message, $keyOptions);

            if ($result['success']) {
                $result['usedProvider'] = $providerKey;
                $result['usedKeyIndex'] = $i;
                $result['usedKeyLabel'] = $keyData['label'] ?? '';
                return $result;
            }

            $lastError = $result['message'] ?? 'Unknown error';
            $this->debugLog("Key #{$i} failed: {$lastError}");
        }

        return $this->errorResponse("All keys failed for {$providerKey}: {$lastError}");
    }

    /**
     * Get responses from multiple providers in parallel (sequential, but tries all)
     *
     * @param string $message
     * @param array $providers List of provider keys ['anthropic', 'openai', 'xai']
     * @param array $options Shared options
     * @return array ['provider_key' => result, ...]
     */
    public function askMultiple(string $message, array $providers, array $options = []): array {
        $results = [];
        foreach ($providers as $pk) {
            $opts = array_merge($options, ['provider' => $pk]);
            $results[$pk] = $this->ask($message, $opts);
        }
        return $results;
    }

    /**
     * Quick shortcut: ask and return just the text
     *
     * @param string $message
     * @param array $options
     * @return string
     */
    public function chat(string $message, array $options = []): string {
        $result = $this->ask($message, $options);
        return $result['success'] ? $result['content'] : '';
    }

    /**
     * Get a provider instance
     *
     * @param string $providerKey
     * @param string|null $specificKey Use a specific API key string
     * @param int|null $keyIndex Use a specific key by index
     * @return SquadProvider|null
     */
    public function getProvider(string $providerKey, ?string $specificKey = null, ?int $keyIndex = null): ?SquadProvider {
        $config = $this->getProviderDefinition($providerKey);
        if (!$config) return null;
        $keys = $this->getProviderKeys($providerKey);

        if ($specificKey) {
            $apiKey = $specificKey;
            $model  = $config['defaultModel'];
        } elseif ($keyIndex !== null && isset($keys[$keyIndex])) {
            $apiKey = $keys[$keyIndex]['key'] ?? '';
            $model  = trim((string)($keys[$keyIndex]['custom_model'] ?? ''))
                ?: trim((string)($keys[$keyIndex]['model'] ?? ''))
                ?: $config['defaultModel'];
            if (!$apiKey) return null;
        } else {
            // Use default key index if set for this provider
            $defaultIdx = $this->defaultKeyIndex;
            if ($defaultIdx !== '' && $defaultIdx !== null && $providerKey === ($this->defaultProvider ?: 'anthropic')) {
                $idx = (int)$defaultIdx;
                if (isset($keys[$idx]) && !empty($keys[$idx]['enabled']) && !empty($keys[$idx]['key'])) {
                    $apiKey = $keys[$idx]['key'];
                    $model  = trim((string)($keys[$idx]['custom_model'] ?? ''))
                        ?: trim((string)($keys[$idx]['model'] ?? ''))
                        ?: $config['defaultModel'];
                } else {
                    // Fallback to first enabled
                    $defaultIdx = '';
                }
            }

            // Find first enabled key (fallback)
            if ($defaultIdx === '' || $defaultIdx === null) {
                $activeKey = null;
                foreach ($keys as $k) {
                    if (!empty($k['enabled'])) {
                        $activeKey = $k;
                        break;
                    }
                }
                if (!$activeKey) return null;
                $apiKey = $activeKey['key'];
                $model  = trim((string)($activeKey['custom_model'] ?? ''))
                    ?: trim((string)($activeKey['model'] ?? ''))
                    ?: $config['defaultModel'];
            }
        }

        $apiKey = $this->resolveApiKey($apiKey);
        if ($apiKey === '') return null;

        $cacheKey = $providerKey . ':' . md5($apiKey) . ':' . md5($model);
        if (!isset($this->providerInstances[$cacheKey])) {
            $this->providerInstances[$cacheKey] = new SquadProvider($providerKey, $config, $apiKey, $model, [
                'timeout' => (int)$this->timeout,
            ]);
        }

        return $this->providerInstances[$cacheKey];
    }

    /**
     * Get all configured keys for a provider
     *
     * @param string $providerKey
     * @return array
     */
    public function getProviderKeys(string $providerKey): array {
        // Encrypted table is the source of truth once any key has been migrated;
        // fall back to the legacy `providers` config JSON during the transition.
        $store = $this->keys();
        if ($store->hasAny()) {
            return $store->getProviderKeys($providerKey);
        }
        $providers = json_decode($this->providers ?: '{}', true) ?: [];
        $keys = $providers[$providerKey] ?? [];
        return is_array($keys) ? $keys : [];
    }

    /**
     * All providers' keys as a map {providerKey: [keyEntry, ...]} — decrypted.
     * Table-first with legacy-config fallback, mirroring getProviderKeys().
     */
    public function getAllProviderKeys(): array {
        $store = $this->keys();
        if ($store->hasAny()) {
            $out = [];
            foreach (array_keys($this->getProviderDefinitions()) as $pk) {
                $rows = $store->getProviderKeys($pk);
                if ($rows) $out[$pk] = $rows;
            }
            return $out;
        }
        return json_decode($this->providers ?: '{}', true) ?: [];
    }

    /** Lazily-built encrypted key store. */
    protected function keys(): SquadKeys {
        if ($this->keyStore === null) $this->keyStore = $this->wire(new SquadKeys());
        return $this->keyStore;
    }

    /** @var SquadKeys|null */
    protected $keyStore = null;

    /**
     * Move any provider keys still living in the `providers` config JSON into the
     * encrypted table, then blank the plaintext in config. Idempotent and
     * non-destructive: a key's plaintext is only cleared after it's encrypted.
     * No-op when the config field is already empty. Returns keys migrated.
     */
    public function migrateKeysToTable(): int {
        $decoded = json_decode($this->providers ?: '{}', true);
        if (!is_array($decoded) || !$decoded) return 0;

        $store = $this->keys();
        $moved = 0;
        foreach ($decoded as $pk => $entries) {
            if (!is_array($entries)) continue;
            $moved += $store->replaceProvider($pk, $entries);
        }

        // clear plaintext from config now that it's encrypted in the table
        $configData = $this->wire('modules')->getModuleConfigData($this);
        $configData['providers'] = '{}';
        $this->wire('modules')->saveModuleConfigData($this, $configData);
        $this->providers = '{}';

        if ($moved) $this->log("Migrated {$moved} provider key(s) to encrypted table");
        return $moved;
    }

    /**
     * Get the default provider key name
     *
     * @return string
     */
    public function getDefaultProviderKey(): string {
        return $this->defaultProvider ?: 'anthropic';
    }

    /**
     * Get list of all providers with their status
     *
     * @return array
     */
    public function getProvidersStatus(): array {
        $result = [];
        foreach ($this->getProviderDefinitions() as $key => $config) {
            $keys = $this->getProviderKeys($key);
            $hasActive = false;
            foreach ($keys as $k) {
                if (!empty($k['enabled'])) { $hasActive = true; break; }
            }
            $result[$key] = [
                'label'    => $config['label'],
                'active'   => $hasActive,
                'keyCount' => count($keys),
            ];
        }
        return $result;
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * Get the cache instance for direct access
     *
     * @return SquadCache
     */
    public function cache(): SquadCache {
        return $this->cache;
    }

    /**
     * Clear cache for a specific page
     *
     * @param int|Page $page Page ID or Page object
     * @return int Number of files deleted
     */
    public function clearCache(int|Page $page = 0): int {
        $pageId = ($page instanceof Page) ? $page->id : (int)$page;
        $count = $this->cache->clearPage($pageId);
        if ($count) $this->log("Cache cleared for page {$pageId}: {$count} files");
        return $count;
    }

    /**
     * Clear ALL Squad cache
     *
     * @return int Number of files deleted
     */
    public function clearAllCache(): int {
        $count = $this->cache->clearAll();
        if ($count) $this->log("All cache cleared: {$count} files");
        return $count;
    }

    /**
     * Get cache statistics
     *
     * @return array ['total_files', 'total_size', 'pages', 'expired']
     */
    public function cacheStats(): array {
        return $this->cache->getStats();
    }

    // =========================================================================
    // FIELD STORAGE
    // =========================================================================

    /**
     * Save AI result to a page field
     *
     * Stores the AI response content directly into a page text field.
     * Useful for persisting AI-generated content (SEO descriptions, summaries, translations)
     * that should survive cache expiry and be editable by editors.
     *
     * @param Page $page The page to save to
     * @param string $fieldName Field name (must be text, textarea, or CKEditor)
     * @param string|array $content String content or full ask() result array
     * @param bool $quiet Save without triggering hooks (default: true)
     * @return bool
     */
    public function saveTo(Page $page, string $fieldName, string|array $content, bool $quiet = true): bool {
        if (!$page->id) {
            $this->logError("saveTo: page has no ID");
            return false;
        }

        if (!$page->template->hasField($fieldName)) {
            $this->logError("saveTo: field '{$fieldName}' not found on template '{$page->template->name}'");
            return false;
        }

        // Extract content from result array
        $text = is_array($content) ? ($content['content'] ?? '') : $content;

        if ($text === '') {
            $this->debugLog("saveTo: empty content, skipping");
            return false;
        }

        $wasOutputFormatting = $page->of();
        $page->of(false);
        $page->set($fieldName, $text);

        try {
            if ($quiet) {
                $page->save($fieldName, ['quiet' => true]);
            } else {
                $page->save($fieldName);
            }
        } finally {
            $page->of($wasOutputFormatting);
        }

        $this->debugLog("saveTo: saved " . mb_strlen($text) . " chars to {$page->id}->{$fieldName}");
        return true;
    }

    /**
     * Load AI content from a page field
     *
     * Returns the field value if not empty, or null if the field is empty.
     * Use this to check if AI content already exists before calling ask().
     *
     * @param Page $page
     * @param string $fieldName
     * @return string|null Field content or null if empty
     */
    public function loadFrom(Page $page, string $fieldName): ?string {
        if (!$page->id || !$page->template->hasField($fieldName)) {
            return null;
        }

        $value = $page->getFormatted($fieldName);
        return ($value !== null && $value !== '') ? (string)$value : null;
    }

    /**
     * Ask AI and save the result to a page field
     *
     * Supports single field or multiple fields with different prompts.
     *
     * Single field:
     *   askAndSave($page, 'seo_desc', 'Write SEO for: ...')
     *
     * Multiple fields (same prompt, same result saved to all):
     *   askAndSave($page, ['seo_desc', 'og_description'], 'Write SEO for: ...')
     *
     * Multiple fields with different prompts (batch):
     *   askAndSave($page, [
     *       'seo_desc'    => 'Write SEO description for: ...',
     *       'ai_summary'  => 'Summarize this article: ...',
     *       'ai_keywords' => 'Extract 5 keywords from: ...',
     *   ])
     *
     * @param Page $page
     * @param string|array $fields Field name, array of field names, or [field => prompt] map
     * @param string|null $message Message (required for single/multi field, omit for batch)
     * @param array $options Same as ask(), plus:
     *   overwrite: bool (false) — always call AI even if field has content
     *   quiet: bool (true) — save without triggering hooks
     * @return array Single: same as ask() + 'source'. Batch: [field => result, ...]
     */
    public function askAndSave(Page $page, string|array $fields, ?string $message = null, array $options = []): array {
        $overwrite = $options['overwrite'] ?? false;
        $quiet = $options['quiet'] ?? true;

        // Set page context for cache
        if (!isset($options['pageId']) && !isset($options['page'])) {
            $options['pageId'] = $page->id;
        }

        // ── Case 1: Single field ──
        if (is_string($fields)) {
            return $this->_askAndSaveOne($page, $fields, $message ?? '', $options, $overwrite, $quiet);
        }

        // ── Case 2: Array of field names (same prompt → all fields) ──
        if (array_is_list($fields)) {
            $result = null;
            $results = [];

            foreach ($fields as $fieldName) {
                if (!$overwrite) {
                    $existing = $this->loadFrom($page, $fieldName);
                    if ($existing !== null) {
                        $results[$fieldName] = [
                            'success' => true,
                            'content' => $existing,
                            'usage'   => [],
                            'raw'     => [],
                            'cached'  => false,
                            'source'  => 'field',
                        ];
                        continue;
                    }
                }

                // Call AI once, reuse result for all empty fields
                if ($result === null) {
                    $result = $this->ask($message ?? '', $options);
                }

                if ($result['success']) {
                    $this->saveTo($page, $fieldName, $result, $quiet);
                    $results[$fieldName] = array_merge($result, ['source' => 'ai']);
                } else {
                    $results[$fieldName] = $result;
                }
            }

            return $results;
        }

        // ── Case 3: Associative array [field => prompt] (batch, each field gets its own prompt) ──
        $results = [];
        foreach ($fields as $fieldName => $prompt) {
            $results[$fieldName] = $this->_askAndSaveOne($page, $fieldName, $prompt, $options, $overwrite, $quiet);
        }
        return $results;
    }

    /**
     * Internal: ask and save for a single field
     */
    protected function _askAndSaveOne(Page $page, string $fieldName, string $message, array $options, bool $overwrite, bool $quiet): array {
        // Check field first
        if (!$overwrite) {
            $existing = $this->loadFrom($page, $fieldName);
            if ($existing !== null) {
                $this->debugLog("askAndSave: '{$fieldName}' has content, returning from field");
                return [
                    'success' => true,
                    'content' => $existing,
                    'usage'   => [],
                    'raw'     => [],
                    'cached'  => false,
                    'source'  => 'field',
                ];
            }
        }

        $result = $this->ask($message, $options);

        if ($result['success']) {
            $this->saveTo($page, $fieldName, $result, $quiet);
            $result['source'] = 'ai';
        }

        return $result;
    }

    /**
     * Generate multiple AI content blocks for a page
     *
     * Each block has its own prompt, field, and optional per-block settings
     * (provider, model, temperature, maxTokens, systemPrompt, cache).
     * Global options apply to all blocks unless overridden per block.
     *
     * Example — wine product page:
     *
     *   $ai->generate($page, [
     *       [
     *           'field'   => 'ai_overview',
     *           'prompt'  => "Write a detailed overview of {$page->title}...",
     *           'options' => ['maxTokens' => 500, 'temperature' => 0.5],
     *       ],
     *       [
     *           'field'   => 'ai_brand_facts',
     *           'prompt'  => "Share 3 interesting facts about the brand...",
     *           'options' => ['provider' => 'openai', 'model' => 'gpt-5.4-nano'],
     *       ],
     *       [
     *           'field'        => 'ai_review_summary',
     *           'prompt'       => "Summarize these customer reviews:\n{$reviews}",
     *           'systemPrompt' => 'You are a wine critic. Be concise.',
     *       ],
     *   ], [
     *       'temperature' => 0.7,
     *       'cache'       => 'W',
     *   ]);
     *
     * Block structure:
     *   field        (string, required) — page field to save to
     *   prompt       (string, required) — AI prompt
     *   options      (array, optional)  — per-block ask() options override
     *   systemPrompt (string, optional) — shortcut for options['systemPrompt']
     *
     * @param Page $page
     * @param array $blocks Array of block definitions
     * @param array $globalOptions Shared options for all blocks (overwrite, quiet, cache, etc.)
     * @return array ['field_name' => result, ...] where result has 'source' => 'field'|'ai'|'error'
     */
    public function generate(Page $page, array $blocks, array $globalOptions = []): array {
        $overwrite = $globalOptions['overwrite'] ?? false;
        $quiet = $globalOptions['quiet'] ?? true;
        $results = [];

        foreach ($blocks as $block) {
            $fieldName = $block['field'] ?? null;
            $prompt    = $block['prompt'] ?? null;

            if (!$fieldName || !$prompt) {
                $this->logError("generate: block missing 'field' or 'prompt'");
                continue;
            }

            // Merge: global → per-block options → per-block shortcuts
            $blockOptions = array_merge($globalOptions, $block['options'] ?? []);

            if (isset($block['systemPrompt'])) {
                $blockOptions['systemPrompt'] = $block['systemPrompt'];
            }

            // Page context
            if (!isset($blockOptions['pageId']) && !isset($blockOptions['page'])) {
                $blockOptions['pageId'] = $page->id;
            }

            // Check field first (unless overwrite)
            $blockOverwrite = $block['overwrite'] ?? $overwrite;
            if (!$blockOverwrite) {
                $existing = $this->loadFrom($page, $fieldName);
                if ($existing !== null) {
                    $this->debugLog("generate: '{$fieldName}' has content, skipping");
                    $results[$fieldName] = [
                        'success' => true,
                        'content' => $existing,
                        'usage'   => [],
                        'raw'     => [],
                        'cached'  => false,
                        'source'  => 'field',
                    ];
                    continue;
                }
            }

            // Ask AI
            $result = $this->ask($prompt, $blockOptions);

            if ($result['success']) {
                $this->saveTo($page, $fieldName, $result, $quiet);
                $result['source'] = 'ai';
            } else {
                $result['source'] = 'error';
            }

            $results[$fieldName] = $result;
        }

        return $results;
    }

    // =========================================================================
    // AJAX HANDLER
    // =========================================================================

    protected function handleAjaxRequest() {
        // Prevent PW hooks from corrupting JSON output
        ob_start();

        header('Content-Type: application/json; charset=utf-8');

        if (!$this->wire('user')->isSuperuser()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        if (!$this->wire('session')->CSRF->hasValidToken()) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $action = $this->wire('input')->post->name('squad_action');
        $result = ['success' => false, 'message' => 'Unknown action'];

        switch ($action) {
            case 'test_key':
                $result = $this->ajaxTestKey();
                break;
            case 'save_keys':
                $result = $this->ajaxSaveKeys();
                break;
            case 'test_chat':
                $result = $this->ajaxTestChat();
                break;
            case 'refresh_models':
                $result = $this->ajaxRefreshModels();
                break;
            case 'clear_cache':
                $count = $this->clearAllCache();
                $result = ['success' => true, 'message' => "Cleared {$count} cached files"];
                break;
        }

        ob_end_clean();
        echo json_encode($result);
        exit;
    }

    /**
     * Test a single API key
     */
    protected function ajaxTestKey(): array {
        $providerKey = $this->wire('input')->post->name('provider');
        $apiKey      = $_POST['api_key'] ?? '';

        if (!$providerKey || !$apiKey) {
            return ['success' => false, 'message' => 'Provider and API key are required'];
        }

        $config = $this->getProviderDefinition($providerKey);
        if (!$config) {
            return ['success' => false, 'message' => "Unknown provider: {$providerKey}"];
        }

        $apiKey = $this->resolveApiKey(trim($apiKey));
        if ($apiKey === '') {
            return ['success' => false, 'message' => 'API key is empty or environment variable is not set'];
        }

        $provider = new SquadProvider($providerKey, $config, $apiKey, $config['defaultModel'], [
            'timeout' => 15,
        ]);

        $testResult = $provider->testConnection();

        $this->debugLog("Test key for {$providerKey}: " . ($testResult['success'] ? 'OK' : 'FAIL'));

        return $testResult;
    }

    /**
     * Save provider keys via AJAX
     */
    protected function ajaxSaveKeys(): array {
        // Use raw POST to avoid PW sanitizer corrupting JSON
        $data = $_POST['keys_data'] ?? '';
        if (!$data) {
            // Fallback: try reading raw input
            $raw = file_get_contents('php://input');
            parse_str($raw, $parsed);
            $data = $parsed['keys_data'] ?? '';
        }
        if (!$data) return ['success' => false, 'message' => 'No data received'];

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) return ['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()];

        // Validate structure
        $clean = [];
        $providerDefinitions = $this->getProviderDefinitions();
        foreach ($decoded as $providerKey => $keys) {
            if (!isset($providerDefinitions[$providerKey])) continue;
            if (!is_array($keys)) continue;
            $clean[$providerKey] = [];
            foreach ($keys as $k) {
                if (!is_array($k)) continue;
                $clean[$providerKey][] = [
                    'key'     => trim((string)($k['key'] ?? '')),
                    'label'   => trim((string)($k['label'] ?? '')),
                    'model'   => trim((string)($k['model'] ?? '')),
                    'custom_model' => trim((string)($k['custom_model'] ?? '')),
                    'enabled' => !empty($k['enabled']),
                    'status'  => in_array(($k['status'] ?? ''), ['ok', 'fail', 'unknown'], true) ? $k['status'] : 'unknown',
                ];
            }
        }

        // Save into the encrypted table (source of truth), and keep the legacy
        // config field blank so no plaintext key ever lands in the DB / a dump.
        $store = $this->keys();
        foreach ($clean as $providerKey => $keys) {
            $store->replaceProvider($providerKey, $keys);
        }
        $configData = $this->wire('modules')->getModuleConfigData($this);
        $configData['providers'] = '{}';
        $this->wire('modules')->saveModuleConfigData($this, $configData);
        $this->providers = '{}';

        $this->log("Provider keys updated (encrypted store)");

        return ['success' => true, 'message' => 'Keys saved successfully'];
    }

    /**
     * Test chat via AJAX
     */
    protected function ajaxTestChat(): array {
        $providerKey = $this->wire('input')->post->name('provider');
        $message     = $this->wire('input')->post->text('message') ?: 'What is the safest city in the United States and why?';
        $model       = $_POST['model'] ?? '';
        $keyIndex    = $_POST['key_index'] ?? null;
        $temperature = $_POST['temperature'] ?? null;
        $maxTokens   = $_POST['max_tokens'] ?? null;
        $timeout     = $_POST['timeout'] ?? null;

        if (!$providerKey || !$this->getProviderDefinition($providerKey)) {
            return ['success' => false, 'message' => 'Invalid provider'];
        }

        // Get the specific key by index
        $keys = $this->getProviderKeys($providerKey);
        $specificKey = null;

        if ($keyIndex !== null && $keyIndex !== '' && isset($keys[(int)$keyIndex])) {
            $keyData = $keys[(int)$keyIndex];
            $specificKey = $keyData['key'] ?? null;
            if (!$model) {
                $model = trim((string)($keyData['custom_model'] ?? ''))
                    ?: trim((string)($keyData['model'] ?? ''))
                    ?: null;
            }
        }

        $options = ['provider' => $providerKey];
        if ($model) $options['model'] = $model;
        if ($specificKey) $options['key'] = $specificKey;
        if ($temperature !== null && $temperature !== '') $options['temperature'] = (float)$temperature;
        if ($maxTokens !== null && $maxTokens !== '') $options['maxTokens'] = (int)$maxTokens;
        if ($timeout !== null && $timeout !== '') $options['timeout'] = (int)$timeout;

        $result = $this->ask($message, $options);

        // Add cache_saved flag for UI badge
        if ($result['success'] && !($result['cached'] ?? false) && $this->enableCache) {
            $result['cache_saved'] = true;
        }

        return $result;
    }

    /**
     * Refresh provider model list via AJAX.
     */
    protected function ajaxRefreshModels(): array {
        $providerKey = $this->wire('input')->post->name('provider');
        $keyIndex = $_POST['key_index'] ?? null;
        $apiKey = $_POST['api_key'] ?? null;

        if (!$providerKey || !$this->getProviderDefinition($providerKey)) {
            return ['success' => false, 'models' => [], 'message' => 'Invalid provider'];
        }

        $keyIndex = ($keyIndex !== null && $keyIndex !== '') ? (int)$keyIndex : null;
        return $this->refreshProviderModels($providerKey, $apiKey ?: null, $keyIndex);
    }

    // =========================================================================
    // CONFIGURATION UI
    // =========================================================================

    /**
     * Module configuration fields
     */
    public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
        $modules = $this->wire('modules');
        $providerDefinitions = $this->getProviderDefinitions();

        // Sweep any plaintext keys left in the config field into the encrypted
        // table (no-op once clean), so the form always edits from encrypted store.
        $this->migrateKeysToTable();

        // ─── Provider Keys Management (main section) ─────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('API Keys & Providers');
        $fieldset->icon = 'key';
        $fieldset->description = $this->_('Manage your AI provider API keys. You can add multiple keys per provider.');

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Provider Configuration');
        $f->value = $this->renderProviderKeysUI();
        $f->collapsed = Inputfield::collapsedNever;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Default Settings ────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Default Settings');
        $fieldset->icon = 'cog';
        $fieldset->collapsed = Inputfield::collapsedYes;

        // Default provider
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'defaultProvider');
        $f->label = $this->_('Default Provider');
        $f->description = $this->_('Provider to use when none is specified in API calls.');
        foreach ($providerDefinitions as $key => $config) {
            $f->addOption($key, $config['label']);
        }
        $f->attr('value', $this->defaultProvider ?: 'anthropic');
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Default key index — proper InputfieldSelect so PW saves it
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'defaultKeyIndex');
        $f->label = $this->_('Default Key');
        $f->description = $this->_('Key to use by default. First active key is used if not set.');
        $f->columnWidth = 50;
        $f->addOption('', $this->_('— First active key —'));

        $providers = $this->getAllProviderKeys();
        $currentDefaultProvider = $this->defaultProvider ?: 'anthropic';
        $currentDefaultKey = $this->defaultKeyIndex ?? '';

        // Build JS data for all providers and populate current provider's keys
        $keyOptionsData = [];
        foreach ($providerDefinitions as $pk => $config) {
            $keys = $providers[$pk] ?? [];
            $keyOptionsData[$pk] = [];
            foreach ($keys as $i => $k) {
                if (!empty($k['enabled']) && !empty($k['key'])) {
                    $maskedKey = substr($k['key'], 0, 8) . '...' . substr($k['key'], -4);
                    $label = !empty($k['label']) ? $k['label'] : ('Key #' . ($i + 1) . ' (' . $maskedKey . ')');
                    $keyOptionsData[$pk][] = ['index' => $i, 'label' => $label];
                    // Add option for current provider
                    if ($pk === $currentDefaultProvider) {
                        $f->addOption((string)$i, $label);
                    }
                }
            }
        }
        $f->attr('value', $currentDefaultKey);
        $fieldset->add($f);

        // Store key data for JS (will be output at the end of config form)
        $this->_keyOptionsJson = $this->jsonScript($keyOptionsData);

        // System prompt
        $f = $modules->get('InputfieldTextarea');
        $f->attr('name', 'systemPrompt');
        $f->label = $this->_('Default System Prompt');
        $f->description = $this->_('System prompt sent with every request (can be overridden per call).');
        $f->attr('value', $this->systemPrompt);
        $f->attr('rows', 4);
        $f->notes = $this->_('Example: You are a helpful assistant for our website. Be concise and friendly.');
        $fieldset->add($f);

        // Max tokens
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'maxTokens');
        $f->label = $this->_('Max Tokens');
        $f->description = $this->_('Maximum number of tokens in the response.');
        $f->attr('value', (int)$this->maxTokens ?: 1024);
        $f->attr('min', 1);
        $f->attr('max', 128000);
        $f->columnWidth = 33;
        $fieldset->add($f);

        // Temperature
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'temperature');
        $f->label = $this->_('Temperature');
        $f->description = $this->_('Creativity level (0.0 = deterministic, 1.0 = creative).');
        $f->attr('value', $this->temperature ?? '0.7');
        $f->attr('type', 'number');
        $f->attr('step', '0.1');
        $f->attr('min', '0');
        $f->attr('max', '2');
        $f->columnWidth = 33;
        $fieldset->add($f);

        // Timeout
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'timeout');
        $f->label = $this->_('Timeout (seconds)');
        $f->description = $this->_('API request timeout.');
        $f->attr('value', (int)$this->timeout ?: 30);
        $f->attr('min', 5);
        $f->attr('max', 300);
        $f->columnWidth = 34;
        $fieldset->add($f);

        // Cache: enable
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableCache');
        $f->label = $this->_('Enable Cache by Default');
        $f->description = $this->_('When ON, all ask()/chat() calls are cached automatically unless explicitly disabled in code with `\'cache\' => false`. When OFF, caching only works when enabled in code with `\'cache\' => \'W\'`.');
        $f->attr('checked', $this->enableCache ? 'checked' : '');
        $f->columnWidth = 50;
        $fieldset->add($f);

        // Cache: default TTL
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'defaultCacheTtl');
        $f->label = $this->_('Default Cache Duration');
        $f->description = $this->_('Used when cache is enabled globally or when code passes `\'cache\' => true`.');
        $f->addOptions([
            'D'  => $this->_('1 Day'),
            'W'  => $this->_('1 Week'),
            '2W' => $this->_('2 Weeks'),
            'M'  => $this->_('1 Month'),
            '3M' => $this->_('3 Months'),
            '6M' => $this->_('6 Months'),
            'Y'  => $this->_('1 Year'),
        ]);
        $f->attr('value', $this->defaultCacheTtl ?: 'D');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Logging ─────────────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Logging');
        $fieldset->icon = 'file-text-o';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableLogging');
        $f->label = $this->_('Enable Logging');
        $f->attr('checked', $this->enableLogging ? 'checked' : '');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableDebugLogging');
        $f->label = $this->_('Enable Debug Logging');
        $f->description = $this->_('Log detailed request/response data (verbose).');
        $f->attr('checked', $this->enableDebugLogging ? 'checked' : '');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Cache Management ────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Cache');
        $fieldset->icon = 'database';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Cache Status');
        $f->value = $this->renderCacheUI();
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // ─── Test Chat ───────────────────────────────────────────────────
        $fieldset = $modules->get('InputfieldFieldset');
        $fieldset->label = $this->_('Test Chat');
        $fieldset->icon = 'comments';
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Quick Test');
        $f->value = $this->renderTestChatUI();
        $fieldset->add($f);

        $inputfields->add($fieldset);

        // Hidden field for providers JSON data. Kept empty on purpose: keys are
        // persisted only via "Save All Keys" → the encrypted table, so a normal
        // config-form submit never writes plaintext keys back into module config.
        $f = $modules->get('InputfieldHidden');
        $f->attr('name', 'providers');
        $f->attr('id', 'squad-providers-data');
        $f->attr('value', '{}');
        $inputfields->add($f);

        // Hidden field for refreshed provider model JSON data
        $f = $modules->get('InputfieldHidden');
        $f->attr('name', 'providerModels');
        $f->attr('id', 'squad-provider-models-data');
        $f->attr('value', $this->providerModels ?: '{}');
        $inputfields->add($f);

        return $inputfields;
    }

    /**
     * Render the provider keys management UI
     */
    protected function renderProviderKeysUI(): string {
        $providers = $this->getAllProviderKeys();
        $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=Squad';
        $providerDefinitions = $this->getProviderDefinitions();
        foreach ($providerDefinitions as $pk => &$config) {
            $config['models'] = $this->getProviderModels($pk);
            $config['modelsUpdated'] = $this->getProviderModelsUpdated($pk);
            $config['canRefreshModels'] = in_array($pk, ['openai', 'openrouter'], true);
        }
        unset($config);

        $providersJson = $this->jsonAttribute($providerDefinitions);
        $savedKeysJson = $this->jsonAttribute($providers);
        $moduleUrlAttr = htmlspecialchars($moduleUrl, ENT_QUOTES, 'UTF-8');
        $csrfFieldsJson = $this->getCsrfFieldsJson();

        $html = <<<HTML
<div id="squad-keys-app" data-providers='{$providersJson}' data-saved='{$savedKeysJson}' data-url='{$moduleUrlAttr}'>
    <style>
        #squad-keys-app { margin: 10px 0; }
        .squad-provider-section {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .squad-provider-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            user-select: none;
        }
        .squad-provider-header:hover { background: #f5f5f5; }
        .squad-provider-header h4 {
            margin: 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .squad-provider-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: normal;
        }
        .squad-badge-active { background: #d4edda; color: #155724; }
        .squad-badge-inactive { background: #f8d7da; color: #721c24; }
        .squad-badge-nokeys { background: #e2e3e5; color: #383d41; }
        .squad-provider-body { padding: 16px; }
        .squad-provider-body.collapsed { display: none; }
        .squad-key-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        .squad-key-row:hover { border-color: #999; }
        .squad-key-input {
            flex: 2;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .squad-label-input {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
        }
        .squad-custom-model-input {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .squad-model-select {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
        }
        .squad-status-icon {
            width: 24px;
            text-align: center;
            font-size: 16px;
        }
        .squad-status-ok { color: #28a745; }
        .squad-status-fail { color: #dc3545; }
        .squad-status-unknown { color: #6c757d; }
        .squad-status-testing { color: #ffc107; }
        .squad-key-actions {
            display: flex;
            gap: 4px;
        }
        .squad-btn {
            padding: 5px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .squad-btn:hover { background: #f0f0f0; border-color: #999; }
        .squad-btn-primary { background: #2196F3; color: #fff; border-color: #1976D2; }
        .squad-btn-primary:hover { background: #1976D2; }
        .squad-btn-danger { color: #dc3545; }
        .squad-btn-danger:hover { background: #dc3545; color: #fff; border-color: #dc3545; }
        .squad-btn-success { background: #28a745; color: #fff; border-color: #218838; }
        .squad-btn-success:hover { background: #218838; }
        .squad-add-key-row {
            padding: 8px 0;
        }
        .squad-enabled-toggle {
            cursor: pointer;
            font-size: 16px;
        }
        .squad-save-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
            padding: 12px 16px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
        }
        .squad-save-bar.hidden { display: none; }
        .squad-save-bar.saved {
            background: #d4edda;
            border-color: #28a745;
        }
        .squad-docs-link {
            font-size: 12px;
            color: #666;
            text-decoration: none;
        }
        .squad-docs-link:hover { color: #2196F3; }
    </style>

    <div id="squad-providers-container"></div>

    <div id="squad-save-bar" class="squad-save-bar hidden">
        <button type="button" id="squad-save-btn" class="squad-btn squad-btn-success" onclick="SquadApp.saveAll()">
            <i class="fa fa-save"></i> Save All Keys
        </button>
        <span id="squad-save-status"></span>
    </div>

    <script>
    var SquadApp = (function() {
        'use strict';

        var container, providers, savedKeys, moduleUrl;
        var csrfFields = {$csrfFieldsJson};
        var currentKeys = {};
        var dirty = false;

        function init() {
            var app = document.getElementById('squad-keys-app');
            container = document.getElementById('squad-providers-container');
            providers = JSON.parse(app.getAttribute('data-providers'));
            savedKeys = JSON.parse(app.getAttribute('data-saved'));
            moduleUrl = app.getAttribute('data-url');

            // Initialize currentKeys from saved
            for (var pk in providers) {
                currentKeys[pk] = (savedKeys[pk] || []).map(function(k) {
                    return Object.assign({}, k);
                });
            }

            render();
        }

        function render() {
            var html = '';
            for (var pk in providers) {
                html += renderProvider(pk, providers[pk]);
            }
            container.innerHTML = html;
        }

        function renderProvider(pk, config) {
            var keys = currentKeys[pk] || [];
            var activeCount = keys.filter(function(k) { return k.enabled; }).length;
            var badge = '';

            if (keys.length === 0) {
                badge = '<span class="squad-provider-badge squad-badge-nokeys">No keys</span>';
            } else if (activeCount > 0) {
                badge = '<span class="squad-provider-badge squad-badge-active"><i class="fa fa-check"></i> ' + activeCount + ' active</span>';
            } else {
                badge = '<span class="squad-provider-badge squad-badge-inactive"><i class="fa fa-times"></i> Disabled</span>';
            }

            var collapsed = keys.length === 0 ? '' : ' collapsed';

            var html = '<div class="squad-provider-section" data-provider="' + pk + '">';
            html += '<div class="squad-provider-header" onclick="SquadApp.toggleProvider(\'' + pk + '\')">';
            html += '  <h4><i class="fa fa-' + escAttr(config.icon) + '"></i> ' + escHtml(config.label) + ' ' + badge + '</h4>';
            html += '  <div><a href="' + escAttr(config.docsUrl) + '" target="_blank" class="squad-docs-link" onclick="event.stopPropagation()"><i class="fa fa-external-link"></i> Docs</a></div>';
            html += '</div>';
            html += '<div class="squad-provider-body' + collapsed + '" id="squad-body-' + pk + '">';

            // Render key rows
            for (var i = 0; i < keys.length; i++) {
                html += renderKeyRow(pk, i, keys[i], config);
            }

            // Add key button
            html += '<div class="squad-add-key-row">';
            html += '  <button type="button" class="squad-btn" onclick="SquadApp.addKey(\'' + pk + '\')">';
            html += '    <i class="fa fa-plus"></i> Add API Key';
            html += '  </button>';
            html += '</div>';
            html += '</div></div>';
            return html;
        }

        function renderKeyRow(pk, index, keyData, config) {
            var statusClass = 'squad-status-unknown';
            var statusIcon = 'fa-question-circle';
            var statusTitle = 'Not tested';

            if (keyData.status === 'ok') {
                statusClass = 'squad-status-ok';
                statusIcon = 'fa-check-circle';
                statusTitle = 'Connected';
            } else if (keyData.status === 'fail') {
                statusClass = 'squad-status-fail';
                statusIcon = 'fa-times-circle';
                statusTitle = 'Failed';
            } else if (keyData.status === 'testing') {
                statusClass = 'squad-status-testing';
                statusIcon = 'fa-spinner fa-spin';
                statusTitle = 'Testing...';
            }

            var enabledIcon = keyData.enabled ? 'fa-toggle-on squad-status-ok' : 'fa-toggle-off squad-status-unknown';
            var enabledTitle = keyData.enabled ? 'Enabled (click to disable)' : 'Disabled (click to enable)';

            // Model options
            var modelOptions = '';
            var selectedModel = keyData.model || config.defaultModel;
            var hasSelectedModel = false;
            for (var mk in config.models) {
                var selected = (selectedModel === mk) ? ' selected' : '';
                if (selected) hasSelectedModel = true;
                modelOptions += '<option value="' + escAttr(mk) + '"' + selected + '>' + escHtml(config.models[mk]) + '</option>';
            }
            if (selectedModel && !hasSelectedModel) {
                modelOptions = '<option value="' + escAttr(selectedModel) + '" selected>' + escHtml(selectedModel) + '</option>' + modelOptions;
            }

            var maskedKey = maskKey(keyData.key);
            var refreshButton = '';
            if (config.canRefreshModels) {
                refreshButton = '<button type="button" class="squad-btn" title="Refresh models" onclick="SquadApp.refreshModels(\'' + pk + '\',' + index + ')"><i class="fa fa-refresh"></i></button>';
            }

            var html = '<div class="squad-key-row" id="squad-row-' + pk + '-' + index + '">';
            html += '<span class="squad-enabled-toggle" title="' + enabledTitle + '" onclick="SquadApp.toggleEnabled(\'' + pk + '\',' + index + ')">';
            html += '  <i class="fa ' + enabledIcon + '"></i>';
            html += '</span>';
            html += '<span class="squad-status-icon ' + statusClass + '" title="' + statusTitle + '"><i class="fa ' + statusIcon + '"></i></span>';
            html += '<input type="text" class="squad-label-input" placeholder="Label (optional)" value="' + escHtml(keyData.label || '') + '" onchange="SquadApp.updateKey(\'' + pk + '\',' + index + ',\'label\',this.value)" />';
            html += '<input type="password" class="squad-key-input" placeholder="API Key" value="' + escHtml(keyData.key) + '" onchange="SquadApp.updateKey(\'' + pk + '\',' + index + ',\'key\',this.value)" />';
            html += '<select class="squad-model-select" onchange="SquadApp.updateKey(\'' + pk + '\',' + index + ',\'model\',this.value)">' + modelOptions + '</select>';
            html += '<input type="text" class="squad-custom-model-input" placeholder="Custom model (optional)" value="' + escHtml(keyData.custom_model || '') + '" onchange="SquadApp.updateKey(\'' + pk + '\',' + index + ',\'custom_model\',this.value)" />';
            html += '<div class="squad-key-actions">';
            html += refreshButton;
            html += '  <button type="button" class="squad-btn" title="Test this key" onclick="SquadApp.testKey(\'' + pk + '\',' + index + ')"><i class="fa fa-plug"></i></button>';
            html += '  <button type="button" class="squad-btn squad-btn-danger" title="Remove" onclick="SquadApp.removeKey(\'' + pk + '\',' + index + ')"><i class="fa fa-trash"></i></button>';
            html += '</div>';
            html += '</div>';
            return html;
        }

        function maskKey(key) {
            if (!key || key.length < 12) return key;
            return key.substring(0, 8) + '...' + key.substring(key.length - 4);
        }

        function escHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function escAttr(str) {
            return escHtml(str).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function toggleProvider(pk) {
            var body = document.getElementById('squad-body-' + pk);
            if (body) body.classList.toggle('collapsed');
        }

        function addKey(pk) {
            if (!currentKeys[pk]) currentKeys[pk] = [];
            currentKeys[pk].push({
                key: '',
                label: '',
                model: providers[pk].defaultModel,
                custom_model: '',
                enabled: true,
                status: 'unknown'
            });
            setDirty();
            render();

            // Expand the section
            var body = document.getElementById('squad-body-' + pk);
            if (body) body.classList.remove('collapsed');

            // Focus the new key input
            var rows = document.querySelectorAll('[id^="squad-row-' + pk + '-"]');
            if (rows.length) {
                var lastRow = rows[rows.length - 1];
                var input = lastRow.querySelector('.squad-key-input');
                if (input) input.focus();
            }
        }

        function removeKey(pk, index) {
            if (!confirm('Remove this API key?')) return;
            currentKeys[pk].splice(index, 1);
            setDirty();
            render();
        }

        function updateKey(pk, index, field, value) {
            if (currentKeys[pk] && currentKeys[pk][index]) {
                currentKeys[pk][index][field] = value;
                if (field === 'key') currentKeys[pk][index].status = 'unknown';
                setDirty();
            }
        }

        function toggleEnabled(pk, index) {
            if (currentKeys[pk] && currentKeys[pk][index]) {
                currentKeys[pk][index].enabled = !currentKeys[pk][index].enabled;
                setDirty();
                render();
            }
        }

        function testKey(pk, index) {
            var keyData = currentKeys[pk][index];
            if (!keyData || !keyData.key) {
                alert('Please enter an API key first.');
                return;
            }

            keyData.status = 'testing';
            render();

            jQuery.ajax({
                url: moduleUrl,
                type: 'POST',
                data: Object.assign({}, csrfFields, {
                    squad_action: 'test_key',
                    provider: pk,
                    api_key: keyData.key
                }),
                dataType: 'json',
                timeout: 20000
            })
            .done(function(response) {
                keyData.status = response.success ? 'ok' : 'fail';
                if (!response.success && response.message) {
                    alert(providers[pk].label + ': ' + response.message);
                }
                render();
            })
            .fail(function(xhr, status) {
                keyData.status = 'fail';
                render();
                alert('Connection test failed: ' + status);
            });
        }

        function refreshModels(pk, index) {
            var keyData = currentKeys[pk][index];
            if (!keyData || !keyData.key) {
                alert('Please enter an API key first.');
                return;
            }

            jQuery.ajax({
                url: moduleUrl,
                type: 'POST',
                data: Object.assign({}, csrfFields, {
                    squad_action: 'refresh_models',
                    provider: pk,
                    key_index: index,
                    api_key: keyData.key
                }),
                dataType: 'json',
                timeout: 30000
            })
            .done(function(response) {
                if (!response.success) {
                    alert(response.message || 'Could not refresh models.');
                    return;
                }

                providers[pk].models = response.models || {};
                providers[pk].modelsUpdated = response.updated || '';
                syncProviderModelsField(pk, response.models || {}, response.updated || '');
                render();
                alert(response.message || 'Models refreshed.');
            })
            .fail(function(xhr, status) {
                alert('Model refresh failed: ' + status);
            });
        }

        function setDirty() {
            dirty = true;
            var bar = document.getElementById('squad-save-bar');
            bar.classList.remove('hidden', 'saved');
            document.getElementById('squad-save-status').textContent = 'Unsaved changes';
            syncHiddenField();
        }

        function syncHiddenField() {
            // Keys are saved via "Save All Keys" → encrypted table; never write
            // them into the config form field (would persist plaintext in the DB).
            var hidden = document.getElementById('squad-providers-data');
            if (hidden) hidden.value = '{}';
        }

        function syncProviderModelsField(pk, models, updated) {
            var hidden = document.getElementById('squad-provider-models-data');
            if (!hidden) return;

            var data = {};
            try {
                data = JSON.parse(hidden.value || '{}');
            } catch (e) {
                data = {};
            }
            data[pk] = { updated: updated, models: models };
            hidden.value = JSON.stringify(data);
        }

        function saveAll() {
            var btn = document.getElementById('squad-save-btn');
            var status = document.getElementById('squad-save-status');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

            jQuery.ajax({
                url: moduleUrl,
                type: 'POST',
                data: Object.assign({}, csrfFields, {
                    squad_action: 'save_keys',
                    keys_data: JSON.stringify(currentKeys)
                }),
                dataType: 'json',
                timeout: 10000
            })
            .done(function(response) {
                if (response.success) {
                    dirty = false;
                    var bar = document.getElementById('squad-save-bar');
                    bar.classList.add('saved');
                    status.textContent = 'Saved!';
                    syncHiddenField();
                } else {
                    status.textContent = 'Error: ' + response.message;
                }
            })
            .fail(function() {
                status.textContent = 'Save failed — check your connection.';
            })
            .always(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> Save All Keys';
            });
        }

        // Init on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        return {
            toggleProvider: toggleProvider,
            addKey: addKey,
            removeKey: removeKey,
            updateKey: updateKey,
            toggleEnabled: toggleEnabled,
            testKey: testKey,
            refreshModels: refreshModels,
            saveAll: saveAll
        };
    })();
    </script>
</div>
HTML;

        return $html;
    }

    /**
     * Render Test Chat UI
     */
    protected function renderTestChatUI(): string {
        $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=Squad';
        $providers = $this->getAllProviderKeys();
        $providerDefinitions = $this->getProviderDefinitions();
        $moduleUrlJs = $this->jsonScript($moduleUrl);
        $csrfFieldsJson = $this->getCsrfFieldsJson();

        // Build provider -> keys mapping for JS
        $providerKeysMap = [];
        foreach ($providerDefinitions as $pk => $config) {
            $providerKeysMap[$pk] = [
                'label'  => $config['label'],
                'models' => $this->getProviderModels($pk),
                'defaultModel' => $config['defaultModel'],
                'keys'   => [],
            ];
            $keys = $providers[$pk] ?? [];
            foreach ($keys as $i => $k) {
                if (!empty($k['enabled']) && !empty($k['key'])) {
                    $maskedKey = substr($k['key'], 0, 8) . '...' . substr($k['key'], -4);
                    if (!empty($k['label'])) {
                        $displayLabel = $k['label'];
                    } else {
                        $displayLabel = 'Key #' . ($i + 1) . ' (' . $maskedKey . ')';
                    }
                    $model = trim((string)($k['custom_model'] ?? ''))
                        ?: trim((string)($k['model'] ?? ''))
                        ?: $config['defaultModel'];
                    $providerKeysMap[$pk]['keys'][] = [
                        'index'  => $i,
                        'label'  => $displayLabel,
                        'model'  => $model,
                    ];
                }
            }
        }
        $providerKeysJson = $this->jsonScript($providerKeysMap);

        $defaultProvider = $this->defaultProvider ?: 'anthropic';
        $defaultProviderJson = $this->jsonScript($defaultProvider);
        $defaultKeyJson = $this->_keyOptionsJson ?? '{}';

        return <<<HTML
<div id="squad-test-chat" style="margin: 10px 0;">
    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
        <select id="squad-test-provider" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 160px;" onchange="SquadTestUpdateSelects()">
        </select>
        <select id="squad-test-key" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;" onchange="SquadTestUpdateModel()">
        </select>
        <select id="squad-test-model" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px;">
        </select>
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
        <input type="text" id="squad-test-message" placeholder="Type a test message..." 
               value="What is the safest city in the United States and why?" 
               style="flex: 1; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px;" />
        <button type="button" class="squad-btn squad-btn-primary" id="squad-test-send-btn" onclick="SquadTestChat()">
            <i class="fa fa-paper-plane"></i> Send
        </button>
    </div>
    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; align-items: center;">
        <label style="font-size: 12px; color: #666;">
            Temperature
            <input type="number" id="squad-test-temperature" value="1" min="0" max="2" step="0.1"
                   style="width: 60px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;" />
        </label>
        <label style="font-size: 12px; color: #666;">
            Max Tokens
            <input type="number" id="squad-test-tokens" value="1024" min="1" max="32000" step="1"
                   style="width: 75px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;" />
        </label>
        <label style="font-size: 12px; color: #666;">
            Timeout
            <input type="number" id="squad-test-timeout" value="30" min="5" max="300" step="1"
                   style="width: 55px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;" />
            <span style="font-size: 11px;">sec</span>
        </label>
    </div>
    <div id="squad-test-response" style="display:none; padding: 12px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; white-space: pre-wrap; font-size: 13px;"></div>
    <div id="squad-test-cache-badge" style="display:none; margin-top: 8px; font-size: 12px; padding: 3px 10px; border-radius: 10px; font-weight: 600; width: fit-content;"></div>
</div>
<script>
var _squadTestData = {$providerKeysJson};
var _squadTestDefault = {$defaultProviderJson};
var _squadTestUrl = {$moduleUrlJs};
var _squadTestCsrf = {$csrfFieldsJson};

function SquadTestUpdateSelects() {
    var providerSel = document.getElementById('squad-test-provider');
    var keySel = document.getElementById('squad-test-key');
    var modelSel = document.getElementById('squad-test-model');
    var pk = providerSel.value;
    var data = _squadTestData[pk];

    // Update keys dropdown
    keySel.innerHTML = '';
    if (data.keys.length === 0) {
        keySel.innerHTML = '<option value="">— No active keys —</option>';
        keySel.disabled = true;
    } else {
        keySel.disabled = false;
        for (var i = 0; i < data.keys.length; i++) {
            var opt = document.createElement('option');
            opt.value = data.keys[i].index;
            opt.textContent = data.keys[i].label;
            opt.dataset.model = data.keys[i].model;
            keySel.appendChild(opt);
        }
    }

    // Update models dropdown
    SquadTestUpdateModel();
}

function SquadTestUpdateModel() {
    var providerSel = document.getElementById('squad-test-provider');
    var keySel = document.getElementById('squad-test-key');
    var modelSel = document.getElementById('squad-test-model');
    var pk = providerSel.value;
    var data = _squadTestData[pk];

    // Get selected key's model as default
    var selectedOpt = keySel.options[keySel.selectedIndex];
    var keyModel = selectedOpt ? (selectedOpt.dataset.model || data.defaultModel) : data.defaultModel;

    modelSel.innerHTML = '';
    for (var mk in data.models) {
        var opt = document.createElement('option');
        opt.value = mk;
        opt.textContent = data.models[mk];
        if (mk === keyModel) opt.selected = true;
        modelSel.appendChild(opt);
    }
}

// Init provider select
(function() {
    var providerSel = document.getElementById('squad-test-provider');
    providerSel.innerHTML = '';
    for (var pk in _squadTestData) {
        var opt = document.createElement('option');
        opt.value = pk;
        opt.textContent = _squadTestData[pk].label;
        if (pk === _squadTestDefault) opt.selected = true;
        providerSel.appendChild(opt);
    }
    SquadTestUpdateSelects();
})();

function SquadTestChat() {
    var provider = document.getElementById('squad-test-provider').value;
    var keyIndex = document.getElementById('squad-test-key').value;
    var model = document.getElementById('squad-test-model').value;
    var message = document.getElementById('squad-test-message').value;
    var temperature = document.getElementById('squad-test-temperature').value;
    var maxTokens = document.getElementById('squad-test-tokens').value;
    var timeout = document.getElementById('squad-test-timeout').value;
    var resultEl = document.getElementById('squad-test-response');
    var badgeEl = document.getElementById('squad-test-cache-badge');
    var btn = document.getElementById('squad-test-send-btn');

    badgeEl.style.display = 'none';

    if (!keyIndex && keyIndex !== '0') {
        resultEl.style.display = 'block';
        resultEl.style.background = '#f8d7da';
        resultEl.style.borderColor = '#dc3545';
        resultEl.textContent = 'No active key for this provider. Add and enable a key first.';
        return;
    }

    resultEl.style.display = 'block';
    resultEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Waiting for response...';
    resultEl.style.background = '#fff3cd';
    resultEl.style.borderColor = '#ffc107';
    btn.disabled = true;

    var startTime = Date.now();
    var ajaxTimeout = (parseInt(timeout) || 30) * 1000 + 5000; // server timeout + 5s buffer

    jQuery.ajax({
        url: _squadTestUrl,
        type: 'POST',
        data: Object.assign({}, _squadTestCsrf, {
            squad_action: 'test_chat',
            provider: provider,
            key_index: keyIndex,
            model: model,
            message: message,
            temperature: temperature,
            max_tokens: maxTokens,
            timeout: timeout
        }),
        dataType: 'json',
        timeout: ajaxTimeout
    })
    .done(function(r) {
        var elapsed = Date.now() - startTime;
        if (r.success) {
            resultEl.style.background = '#d4edda';
            resultEl.style.borderColor = '#28a745';
            var info = '\\n\\n--- ';
            if (r.usage && r.usage.total_tokens) info += 'tokens: ' + r.usage.total_tokens + ' | ';
            info += elapsed + 'ms ---';
            resultEl.textContent = r.content + info;

            if (r.cached) {
                badgeEl.textContent = '⚡ FROM CACHE (' + elapsed + 'ms)';
                badgeEl.style.background = '#d4edda';
                badgeEl.style.color = '#155724';
                badgeEl.style.display = 'inline-block';
            } else if (r.cache_saved) {
                badgeEl.textContent = '💾 SAVED TO CACHE';
                badgeEl.style.background = '#cce5ff';
                badgeEl.style.color = '#004085';
                badgeEl.style.display = 'inline-block';
            }
        } else {
            resultEl.style.background = '#f8d7da';
            resultEl.style.borderColor = '#dc3545';
            resultEl.textContent = 'Error: ' + (r.message || 'Unknown error');
        }
    })
    .fail(function(xhr, status) {
        resultEl.style.background = '#f8d7da';
        resultEl.style.borderColor = '#dc3545';
        resultEl.textContent = 'Request failed: ' + status;
    })
    .always(function() {
        btn.disabled = false;
    });
}

// Default Key selector: update options when Default Provider changes
(function() {
    var _dkData = {$defaultKeyJson};
    var _dkProvider = document.querySelector('[name=defaultProvider]');
    var _dkKey = document.querySelector('[name=defaultKeyIndex]');
    if (!_dkProvider || !_dkKey) return;
    _dkProvider.addEventListener('change', function() {
        var pk = this.value;
        var keys = _dkData[pk] || [];
        _dkKey.innerHTML = '<option value="">— First active key —</option>';
        for (var i = 0; i < keys.length; i++) {
            var opt = document.createElement('option');
            opt.value = keys[i].index;
            opt.textContent = keys[i].label;
            _dkKey.appendChild(opt);
        }
    });
})();
</script>
HTML;
    }

    /**
     * Render Cache Management UI
     */
    protected function renderCacheUI(): string {
        $stats = $this->cache->getStats();
        $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=Squad';
        $moduleUrlJs = $this->jsonScript($moduleUrl);
        $csrfFieldsJson = $this->getCsrfFieldsJson();

        $sizeFormatted = $this->formatBytes($stats['total_size']);

        return <<<HTML
<div style="margin: 10px 0;">
    <table class="AdminDataTable" style="width: auto;">
        <tr><td><strong>Cached responses</strong></td><td>{$stats['total_files']}</td></tr>
        <tr><td><strong>Cache size</strong></td><td>{$sizeFormatted}</td></tr>
        <tr><td><strong>Pages with cache</strong></td><td>{$stats['pages']}</td></tr>
        <tr><td><strong>Expired (pending cleanup)</strong></td><td>{$stats['expired']}</td></tr>
    </table>
    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
        <button type="button" class="squad-btn squad-btn-danger" id="squad-clear-cache-btn" onclick="SquadClearCache()">
            <i class="fa fa-trash"></i> Clear All Cache
        </button>
        <span id="squad-cache-status" style="font-size: 13px;"></span>
    </div>
</div>
<script>
var _squadCacheUrl = {$moduleUrlJs};
var _squadCacheCsrf = {$csrfFieldsJson};
function SquadClearCache() {
    if (!confirm('Clear all Squad cached responses?')) return;
    var btn = document.getElementById('squad-clear-cache-btn');
    var status = document.getElementById('squad-cache-status');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Clearing...';
    jQuery.ajax({
        url: _squadCacheUrl,
        type: 'POST',
        data: Object.assign({}, _squadCacheCsrf, { squad_action: 'clear_cache' }),
        dataType: 'json',
        timeout: 10000
    })
    .done(function(r) {
        status.textContent = r.message || 'Done';
        status.style.color = r.success ? '#28a745' : '#dc3545';
    })
    .fail(function() { status.textContent = 'Failed'; status.style.color = '#dc3545'; })
    .always(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-trash"></i> Clear All Cache';
    });
}
</script>
HTML;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }

    // =========================================================================
    // LOGGING HELPERS
    // =========================================================================

    /**
     * Log an informational message
     */
    public function log($message, array $options = []) {
        if (!$this->enableLogging) return;
        $this->wire('log')->save($this->logName ?: 'squad', $message, $options);
    }

    /**
     * Log an error
     */
    public function logError(string $message) {
        $this->wire('log')->save(($this->logName ?: 'squad') . '-errors', $message);
    }

    /**
     * Debug log
     */
    public function debugLog(string $message) {
        if (!$this->enableDebugLogging) return;
        $this->wire('log')->save(($this->logName ?: 'squad') . '-debug', $message);
    }

    /**
     * Return a standardized error response
     */
    protected function errorResponse(string $message): array {
        $this->logError($message);
        return [
            'success' => false,
            'content' => '',
            'message' => $message,
            'usage'   => [],
            'raw'     => [],
        ];
    }
}
