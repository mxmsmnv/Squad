<?php namespace ProcessWire;

/**
 * SquadProvider - Handles API communication with AI providers
 *
 * Supports Anthropic (Claude), OpenAI (GPT), xAI (Grok), and OpenRouter.
 *
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 */

class SquadProvider {

    /** @var string Provider key (anthropic, openai, google, xai, openrouter) */
    protected string $providerKey;

    /** @var array Provider configuration from Squad::PROVIDERS */
    protected array $config;

    /** @var string API key */
    protected string $apiKey;

    /** @var string Selected model */
    protected string $model;

    /** @var array Options (timeout, etc.) */
    protected array $options;

    public function __construct(string $providerKey, array $config, string $apiKey, string $model, array $options = []) {
        $this->providerKey = $providerKey;
        $this->config      = $config;
        $this->apiKey      = $apiKey;
        $this->model       = $model;
        $this->options     = array_merge([
            'timeout' => 30,
        ], $options);
    }

    /**
     * Get the selected model
     */
    public function getModel(): string {
        return $this->model;
    }

    /**
     * Set request timeout
     */
    public function setTimeout(int $seconds): void {
        $this->options['timeout'] = max(5, $seconds);
    }

    /**
     * Fetch available models from providers that expose a simple list endpoint.
     *
     * @return array ['success' => bool, 'models' => array, 'message' => string]
     */
    public function fetchModels(): array {
        if ($this->providerKey === 'openai') {
            return $this->fetchOpenAIModels();
        }

        if ($this->providerKey === 'openrouter') {
            return $this->fetchOpenRouterModels();
        }

        return [
            'success' => false,
            'models'  => [],
            'message' => 'Model refresh is not implemented for this provider.',
        ];
    }

    /**
     * Test connection by sending a minimal request
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array {
        try {
            $result = $this->sendMessage('Hi', [
                'maxTokens'    => 10,
                'temperature'  => 0,
                'systemPrompt' => '',
            ]);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => "Connected! Model: {$this->model}",
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Unknown error',
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a message and get a response
     *
     * @param string $message
     * @param array $options model, systemPrompt, maxTokens, temperature, history
     * @return array ['success', 'content', 'message', 'usage', 'raw']
     */
    public function sendMessage(string $message, array $options = []): array {
        $model       = $options['model'] ?? $this->model;
        $systemPrompt = $options['systemPrompt'] ?? '';
        $maxTokens   = (int)($options['maxTokens'] ?? 1024);
        $temperature = (float)($options['temperature'] ?? 0.7);
        $history     = $options['history'] ?? [];

        // Build request based on provider type
        if ($this->providerKey === 'anthropic') {
            return $this->sendAnthropic($message, $model, $systemPrompt, $maxTokens, $temperature, $history, !empty($options['cachePrompt']));
        }

        // OpenAI-compatible: openai, google, xai, openrouter
        return $this->sendOpenAICompatible($message, $model, $systemPrompt, $maxTokens, $temperature, $history);
    }

    /**
     * Stream text deltas from Anthropic or an OpenAI-compatible provider.
     *
     * @param callable(string):void $onDelta
     * @return array ['success','content','message','usage','raw']
     */
    public function streamMessage(string $message, callable $onDelta, array $options = []): array {
        $model = (string)($options['model'] ?? $this->model);
        $systemPrompt = (string)($options['systemPrompt'] ?? '');
        $maxTokens = (int)($options['maxTokens'] ?? 1024);
        $temperature = (float)($options['temperature'] ?? 0.7);
        $history = (array)($options['history'] ?? []);
        $messages = [];

        foreach($history as $item) {
            if(!is_array($item)) continue;
            $messages[] = [
                'role' => ($item['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'content' => (string)($item['content'] ?? ''),
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        if($this->providerKey === 'anthropic') {
            $body = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
                'stream' => true,
            ];
            if($systemPrompt !== '') {
                $body['system'] = !empty($options['cachePrompt'])
                    ? [['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]]
                    : $systemPrompt;
            }
            if($temperature > 0 && $this->anthropicAcceptsSampling($model)) {
                $body['temperature'] = $temperature;
            }
            $headers = [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'x-api-key: ' . $this->apiKey,
            ];
        } else {
            if($systemPrompt !== '') {
                array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
            }
            $body = [
                'model' => $model,
                'temperature' => $temperature,
                'messages' => $messages,
                'stream' => true,
                'stream_options' => ['include_usage' => true],
            ];
            if($this->providerKey === 'openai') $body['max_completion_tokens'] = $maxTokens;
            else $body['max_tokens'] = $maxTokens;
            $headers = [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'Authorization: Bearer ' . $this->apiKey,
            ];
        }

        foreach($this->config['extraHeaders'] ?? [] as $key => $value) {
            if($value !== '') $headers[] = "{$key}: {$value}";
        }

        return $this->curlStreamRequest(
            (string)$this->config['url'],
            $body,
            $headers,
            $onDelta,
            $this->providerKey === 'anthropic'
        );
    }

    /** Analyze one or more normalized data-URL images with a multimodal model. */
    public function analyzeImages(string $prompt, array $images, array $options = []): array {
        $model = (string)($options['model'] ?? $this->model);
        $systemPrompt = (string)($options['systemPrompt'] ?? '');
        $maxTokens = (int)($options['maxTokens'] ?? 3000);
        $temperature = (float)($options['temperature'] ?? 0.2);

        if($this->providerKey === 'anthropic') {
            $content = [];
            foreach($images as $image) {
                if(!preg_match('#^data:(image/(?:png|jpeg|webp|gif));base64,(.+)$#s', $image, $m)) continue;
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
            }
            $content[] = ['type' => 'text', 'text' => $prompt];
            $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => [['role' => 'user', 'content' => $content]]];
            if($systemPrompt !== '') $body['system'] = $systemPrompt;
            if($temperature > 0 && $this->anthropicAcceptsSampling($model)) $body['temperature'] = $temperature;
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->apiKey];
            foreach($this->config['extraHeaders'] ?? [] as $k => $v) if($v !== '') $headers[] = "{$k}: {$v}";
            $response = $this->curlRequest($this->config['url'], $body, $headers);
            if(!$response['success']) return $response;
            $data = $response['data'];
            if(isset($data['error'])) return ['success' => false, 'content' => '', 'message' => $data['error']['message'] ?? 'API error', 'usage' => [], 'raw' => $data];
            $text = '';
            foreach($data['content'] ?? [] as $block) if(($block['type'] ?? '') === 'text') $text .= (string)($block['text'] ?? '');
            return ['success' => true, 'content' => $text, 'message' => 'OK', 'usage' => $data['usage'] ?? [], 'raw' => $data];
        }

        $content = [['type' => 'text', 'text' => $prompt]];
        foreach($images as $image) $content[] = ['type' => 'image_url', 'image_url' => ['url' => $image, 'detail' => $options['detail'] ?? 'high']];
        $messages = [];
        if($systemPrompt !== '') $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        $messages[] = ['role' => 'user', 'content' => $content];
        $body = ['model' => $model, 'temperature' => $temperature, 'messages' => $messages];
        if($this->providerKey === 'openai') $body['max_completion_tokens'] = $maxTokens;
        else $body['max_tokens'] = $maxTokens;
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        foreach($this->config['extraHeaders'] ?? [] as $k => $v) if($v !== '') $headers[] = "{$k}: {$v}";
        $response = $this->curlRequest($this->config['url'], $body, $headers);
        if(!$response['success']) return $response;
        $data = $response['data'];
        if(isset($data['error'])) {
            $message = is_array($data['error']) ? ($data['error']['message'] ?? 'API error') : (string)$data['error'];
            return ['success' => false, 'content' => '', 'message' => $message, 'usage' => [], 'raw' => $data];
        }
        return [
            'success' => true,
            'content' => (string)($data['choices'][0]['message']['content'] ?? ''),
            'message' => 'OK',
            'usage' => $data['usage'] ?? [],
            'raw' => $data,
        ];
    }

    /**
     * Send request to Anthropic Messages API
     */
    /**
     * Whether an Anthropic model accepts sampling params (temperature/top_p/top_k).
     * Opus 4.7 / 4.8 and the Fable/Mythos family reject them with a 400 — they use
     * adaptive thinking instead. Sonnet 4.6, Opus 4.6 and older still accept them.
     */
    protected function anthropicAcceptsSampling(string $model): bool {
        $m = strtolower($model);
        foreach (['opus-4-7', 'opus-4-8', 'fable', 'mythos'] as $needle) {
            if (strpos($m, $needle) !== false) return false;
        }
        return true;
    }

    protected function sendAnthropic(string $message, string $model, string $systemPrompt, int $maxTokens, float $temperature, array $history, bool $cachePrompt = false): array {
        $messages = [];

        // Add history
        foreach ($history as $h) {
            $messages[] = [
                'role'    => $h['role'] ?? 'user',
                'content' => $h['content'] ?? '',
            ];
        }

        // Add current message
        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];

        // Anthropic uses 'system' as a top-level parameter. With prompt caching, send
        // it as a content block marked cache_control so the prefix is cached server-side.
        if ($systemPrompt) {
            if ($cachePrompt) {
                $body['system'] = [[
                    'type'          => 'text',
                    'text'          => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ]];
            } else {
                $body['system'] = $systemPrompt;
            }
        }

        // temperature for Anthropic — rejected (400) on Opus 4.7/4.8 and Fable/Mythos,
        // which use adaptive thinking instead of sampling params. Omit it for those.
        if ($temperature > 0 && $this->anthropicAcceptsSampling($model)) {
            $body['temperature'] = $temperature;
        }

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
        ];

        // Add extra headers
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) {
            if ($v !== '') $headers[] = "{$k}: {$v}";
        }

        $response = $this->curlRequest($this->config['url'], $body, $headers);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];

        // Check for API error
        if (isset($data['error'])) {
            return [
                'success' => false,
                'content' => '',
                'message' => $data['error']['message'] ?? 'API error',
                'usage'   => [],
                'raw'     => $data,
            ];
        }

        // Extract content
        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        // Extract usage (incl. prompt-cache tokens when caching is active)
        $usage = [];
        if (isset($data['usage'])) {
            $usage = [
                'input_tokens'          => $data['usage']['input_tokens'] ?? 0,
                'output_tokens'         => $data['usage']['output_tokens'] ?? 0,
                'total_tokens'          => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                'cache_creation_tokens' => $data['usage']['cache_creation_input_tokens'] ?? 0,
                'cache_read_tokens'     => $data['usage']['cache_read_input_tokens'] ?? 0,
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'message' => 'OK',
            'usage'   => $usage,
            'raw'     => $data,
        ];
    }

    /**
     * Send request to OpenAI-compatible API (OpenAI, Google, xAI, OpenRouter)
     */
    protected function sendOpenAICompatible(string $message, string $model, string $systemPrompt, int $maxTokens, float $temperature, array $history): array {
        $messages = [];

        // System message
        if ($systemPrompt) {
            $messages[] = [
                'role'    => 'system',
                'content' => $systemPrompt,
            ];
        }

        // History
        foreach ($history as $h) {
            $messages[] = [
                'role'    => $h['role'] ?? 'user',
                'content' => $h['content'] ?? '',
            ];
        }

        // Current message
        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        $body = [
            'model'       => $model,
            'temperature' => $temperature,
            'messages'    => $messages,
        ];

        // OpenAI GPT-5+ and o-series require max_completion_tokens
        // Others (xAI, Google, OpenRouter, older OpenAI) use max_tokens
        if ($this->providerKey === 'openai') {
            $body['max_completion_tokens'] = $maxTokens;
        } else {
            $body['max_tokens'] = $maxTokens;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        // Extra headers (e.g., OpenRouter HTTP-Referer)
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) {
            if ($v !== '') $headers[] = "{$k}: {$v}";
        }

        $response = $this->curlRequest($this->config['url'], $body, $headers);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];

        // Check for API error
        if (isset($data['error'])) {
            $errorMsg = is_array($data['error'])
                ? ($data['error']['message'] ?? json_encode($data['error']))
                : (string)$data['error'];
            return [
                'success' => false,
                'content' => '',
                'message' => $errorMsg,
                'usage'   => [],
                'raw'     => $data,
            ];
        }

        // Extract content
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Extract usage
        $usage = [];
        if (isset($data['usage'])) {
            $usage = [
                'input_tokens'  => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens'  => $data['usage']['total_tokens'] ?? 0,
            ];
        }

        return [
            'success' => true,
            'content' => $content,
            'message' => 'OK',
            'usage'   => $usage,
            'raw'     => $data,
        ];
    }

    /**
     * Generate an image via the provider's image endpoint (config['imageUrl'] or
     * options['imageUrl']). Normalizes xAI/OpenAI-style responses (data[0].url|b64_json).
     *
     * @return array ['success','url','b64','model','provider','message','raw']
     */
    public function generateImage(string $prompt, array $options = []): array {
        $imageUrl = (string)($options['imageUrl'] ?? ($this->config['imageUrl'] ?? ''));
        if ($imageUrl === '') {
            return ['success' => false, 'url' => '', 'b64' => '', 'message' => "Provider '{$this->providerKey}' has no image endpoint.", 'raw' => []];
        }
        $model = (string)($options['model'] ?? ($this->config['defaultImageModel'] ?? $this->model));

        $body = [
            'model'  => $model,
            'prompt' => $prompt,
            'n'      => (int)($options['n'] ?? 1),
        ];
        if (!empty($options['aspect']))          $body['aspect_ratio']    = $options['aspect'];
        if (!empty($options['resolution']))      $body['resolution']      = $options['resolution'];
        if (!empty($options['size']))            $body['size']            = $options['size'];
        if (!empty($options['response_format'])) $body['response_format'] = $options['response_format'];

        $headers = ['Content-Type: application/json'];
        if (($this->config['headerType'] ?? 'bearer') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        } else {
            $headers[] = 'x-api-key: ' . $this->apiKey;
        }
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) {
            if ($v !== '') $headers[] = "{$k}: {$v}";
        }

        $response = $this->curlRequest($imageUrl, $body, $headers);
        if (!$response['success']) {
            return ['success' => false, 'url' => '', 'b64' => '', 'model' => $model, 'provider' => $this->providerKey, 'message' => $response['message'], 'raw' => $response['raw'] ?? ($response['data'] ?? [])];
        }

        $item = $response['data']['data'][0] ?? null;
        if (!is_array($item)) {
            return ['success' => false, 'url' => '', 'b64' => '', 'model' => $model, 'provider' => $this->providerKey, 'message' => 'Unexpected image response.', 'raw' => $response['data']];
        }

        return [
            'success'  => true,
            'url'      => (string)($item['url'] ?? ''),
            'b64'      => (string)($item['b64_json'] ?? ''),
            'model'    => $model,
            'provider' => $this->providerKey,
            'message'  => 'OK',
            'raw'      => $response['data'],
        ];
    }

    /**
     * Create embeddings via an OpenAI-compatible /embeddings endpoint.
     *
     * @param array $texts list of strings (caller guarantees non-empty)
     * @param array $options embedUrl, model
     * @return array ['success','embeddings'=>[[float],...],'model','provider','usage','message','raw']
     */
    public function generateEmbeddings(array $texts, array $options = []): array {
        $embedUrl = (string)($options['embedUrl'] ?? ($this->config['embedUrl'] ?? ''));
        if ($embedUrl === '') {
            return ['success' => false, 'embeddings' => [], 'message' => "Provider '{$this->providerKey}' has no embeddings endpoint.", 'raw' => []];
        }
        $model = (string)($options['model'] ?? ($this->config['defaultEmbedModel'] ?? $this->model));

        $body = [
            'model' => $model,
            // single string when one input — maximally compatible across providers
            'input' => count($texts) === 1 ? $texts[0] : array_values($texts),
        ];

        $headers = ['Content-Type: application/json'];
        if (($this->config['headerType'] ?? 'bearer') === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        } else {
            $headers[] = 'x-api-key: ' . $this->apiKey;
        }
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) {
            if ($v !== '') $headers[] = "{$k}: {$v}";
        }

        $response = $this->curlRequest($embedUrl, $body, $headers);
        if (!$response['success']) {
            return ['success' => false, 'embeddings' => [], 'model' => $model, 'provider' => $this->providerKey, 'message' => $response['message'], 'raw' => $response['raw'] ?? ($response['data'] ?? [])];
        }

        $data = $response['data']['data'] ?? null;
        if (!is_array($data)) {
            return ['success' => false, 'embeddings' => [], 'model' => $model, 'provider' => $this->providerKey, 'message' => 'Unexpected embeddings response.', 'raw' => $response['data']];
        }

        // preserve input order (OpenAI returns an `index` per item)
        usort($data, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
        $embeddings = array_map(fn($d) => $d['embedding'] ?? [], $data);

        return [
            'success'    => true,
            'embeddings' => $embeddings,
            'model'      => $model,
            'provider'   => $this->providerKey,
            'usage'      => $response['data']['usage'] ?? [],
            'message'    => 'OK',
            'raw'        => $response['data'],
        ];
    }

    /**
     * One turn of a tool-use conversation (OpenAI-compatible function calling).
     * Sends messages + tool definitions; returns the assistant turn, including any
     * tool calls the model wants to make. Squad->run() drives the multi-turn loop.
     *
     * @param array $messages role/content messages (system is prepended from options)
     * @param array $options model, systemPrompt, maxTokens, temperature, tools
     * @return array ['success','content','tool_calls'=>[{id,name,arguments}],'assistant','finish_reason','usage','raw','message']
     */
    public function runTools(array $messages, array $options = []): array {
        if ($this->providerKey === 'anthropic') return $this->runToolsAnthropic($messages, $options);
        return $this->runToolsOpenAI($messages, $options);
    }

    /** One OpenAI-compatible function-calling turn. */
    protected function runToolsOpenAI(array $messages, array $options = []): array {
        $model        = $options['model'] ?? $this->model;
        $systemPrompt = (string)($options['systemPrompt'] ?? '');
        $maxTokens    = (int)($options['maxTokens'] ?? 1024);
        $temperature  = (float)($options['temperature'] ?? 0.7);
        $toolDefs     = $options['tools'] ?? [];

        $msgs = [];
        if ($systemPrompt !== '') $msgs[] = ['role' => 'system', 'content' => $systemPrompt];
        foreach ($messages as $m) $msgs[] = $m;

        $tools = [];
        foreach ($toolDefs as $t) {
            $tools[] = ['type' => 'function', 'function' => [
                'name'        => $t['name'] ?? '',
                'description'  => $t['description'] ?? '',
                'parameters'   => $t['parameters'] ?? ['type' => 'object', 'properties' => (object)[]],
            ]];
        }

        $body = ['model' => $model, 'temperature' => $temperature, 'messages' => $msgs];
        if ($tools) { $body['tools'] = $tools; $body['tool_choice'] = $options['toolChoice'] ?? 'auto'; }
        if ($this->providerKey === 'openai') $body['max_completion_tokens'] = $maxTokens;
        else $body['max_tokens'] = $maxTokens;

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) { if ($v !== '') $headers[] = "{$k}: {$v}"; }

        $response = $this->curlRequest($this->config['url'], $body, $headers);
        if (!$response['success']) return ['success' => false, 'content' => '', 'tool_calls' => [], 'message' => $response['message'], 'raw' => $response['raw'] ?? []];
        $data = $response['data'];
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? ($data['error']['message'] ?? 'API error') : (string)$data['error'];
            return ['success' => false, 'content' => '', 'tool_calls' => [], 'message' => $msg, 'raw' => $data];
        }

        $assistant = $data['choices'][0]['message'] ?? ['role' => 'assistant', 'content' => ''];
        $toolCalls = [];
        foreach ($assistant['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = [
                'id'        => $tc['id'] ?? '',
                'name'      => $tc['function']['name'] ?? '',
                'arguments' => (json_decode($tc['function']['arguments'] ?? '{}', true) ?: []),
            ];
        }
        $usage = isset($data['usage']) ? [
            'input_tokens'  => $data['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
            'total_tokens'  => $data['usage']['total_tokens'] ?? 0,
        ] : [];

        return [
            'success'       => true,
            'content'       => (string)($assistant['content'] ?? ''),
            'tool_calls'    => $toolCalls,
            'assistant'     => $assistant,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? '',
            'usage'         => $usage,
            'raw'           => $data,
            'message'       => 'OK',
        ];
    }

    /** One Anthropic tool-use turn (tool_use / tool_result blocks). */
    protected function runToolsAnthropic(array $messages, array $options = []): array {
        $model        = $options['model'] ?? $this->model;
        $systemPrompt = (string)($options['systemPrompt'] ?? '');
        $maxTokens    = (int)($options['maxTokens'] ?? 1024);
        $temperature  = (float)($options['temperature'] ?? 0.7);
        $toolDefs     = $options['tools'] ?? [];

        // messages already arrive in Anthropic shape (run() appends our $assistant
        // content blocks + formatToolResults() user blocks); the first user turn is a
        // plain string, which Anthropic also accepts.
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($systemPrompt !== '') $body['system'] = $systemPrompt;
        if ($temperature > 0 && $this->anthropicAcceptsSampling($model)) $body['temperature'] = $temperature;
        if ($toolDefs) {
            $body['tools'] = array_map(fn($t) => [
                'name'         => $t['name'] ?? '',
                'description'   => $t['description'] ?? '',
                'input_schema' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object)[]],
            ], $toolDefs);
        }

        $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->apiKey];
        foreach ($this->config['extraHeaders'] ?? [] as $k => $v) { if ($v !== '') $headers[] = "{$k}: {$v}"; }

        $response = $this->curlRequest($this->config['url'], $body, $headers);
        if (!$response['success']) return ['success' => false, 'content' => '', 'tool_calls' => [], 'message' => $response['message'], 'raw' => $response['raw'] ?? []];
        $data = $response['data'];
        if (isset($data['error'])) {
            return ['success' => false, 'content' => '', 'tool_calls' => [], 'message' => $data['error']['message'] ?? 'API error', 'raw' => $data];
        }

        $blocks = $data['content'] ?? [];
        $content = '';
        $toolCalls = [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'text') $content .= $b['text'];
            elseif (($b['type'] ?? '') === 'tool_use') {
                $toolCalls[] = ['id' => $b['id'] ?? '', 'name' => $b['name'] ?? '', 'arguments' => $b['input'] ?? []];
            }
        }
        $usage = isset($data['usage']) ? [
            'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            'total_tokens'  => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
        ] : [];

        return [
            'success'       => true,
            'content'       => $content,
            'tool_calls'    => $toolCalls,
            'assistant'     => ['role' => 'assistant', 'content' => $blocks],
            'finish_reason' => $data['stop_reason'] ?? '',
            'usage'         => $usage,
            'raw'           => $data,
            'message'       => 'OK',
        ];
    }

    /**
     * Format tool results as messages to append, in this provider's wire format.
     * @param array $results [['id'=>, 'name'=>, 'content'=>], ...]
     */
    public function formatToolResults(array $results): array {
        if ($this->providerKey === 'anthropic') {
            $blocks = [];
            foreach ($results as $r) {
                $blocks[] = ['type' => 'tool_result', 'tool_use_id' => $r['id'], 'content' => (string)$r['content']];
            }
            return $blocks ? [['role' => 'user', 'content' => $blocks]] : [];
        }
        // OpenAI-compatible: one 'tool' message per result
        return array_map(fn($r) => ['role' => 'tool', 'tool_call_id' => $r['id'], 'content' => (string)$r['content']], $results);
    }

    /**
     * Make a cURL request
     *
     * @param string $url
     * @param array $body
     * @param array $headers
     * @return array ['success', 'data', 'message', 'httpCode']
     */
    protected function curlRequest(string $url, array $body, array $headers): array {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->options['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        $errno        = curl_errno($ch);


        // cURL error
        if ($errno) {
            return [
                'success'  => false,
                'data'     => [],
                'content'  => '',
                'message'  => "cURL error ({$errno}): {$error}",
                'usage'    => [],
                'raw'      => [],
                'httpCode' => 0,
            ];
        }

        // Parse response
        $data = json_decode($responseBody, true);

        if ($data === null && $responseBody !== '') {
            return [
                'success'  => false,
                'data'     => [],
                'content'  => '',
                'message'  => "Invalid JSON response (HTTP {$httpCode})",
                'usage'    => [],
                'raw'      => ['body' => substr($responseBody, 0, 500)],
                'httpCode' => $httpCode,
            ];
        }

        // HTTP error
        if ($httpCode >= 400) {
            $errorMsg = 'HTTP ' . $httpCode;
            if (isset($data['error']['message'])) {
                $errorMsg .= ': ' . $data['error']['message'];
            } elseif (isset($data['error']) && is_string($data['error'])) {
                $errorMsg .= ': ' . $data['error'];
            }

            return [
                'success'  => false,
                'data'     => $data ?: [],
                'content'  => '',
                'message'  => $errorMsg,
                'usage'    => [],
                'raw'      => $data ?: [],
                'httpCode' => $httpCode,
            ];
        }

        return [
            'success'  => true,
            'data'     => $data,
            'message'  => 'OK',
            'httpCode' => $httpCode,
        ];
    }

    /**
     * Execute a Server-Sent Events request and normalize provider deltas.
     */
    protected function curlStreamRequest(
        string $url,
        array $body,
        array $headers,
        callable $onDelta,
        bool $anthropic
    ): array {
        $content = '';
        $usage = [];
        $buffer = '';
        $apiError = '';
        $rawErrorBody = '';
        $callbackError = null;

        $consume = function(string $line) use (
            &$content,
            &$usage,
            &$apiError,
            &$rawErrorBody,
            &$callbackError,
            $onDelta,
            $anthropic
        ): void {
            $line = trim($line);
            if($line === '' || str_starts_with($line, 'event:') || str_starts_with($line, ':')) return;
            if(!str_starts_with($line, 'data:')) {
                if(strlen($rawErrorBody) < 8192) $rawErrorBody .= $line;
                return;
            }
            $payload = trim(substr($line, 5));
            if($payload === '' || $payload === '[DONE]') return;
            $data = json_decode($payload, true);
            if(!is_array($data)) return;
            if(isset($data['error'])) {
                $apiError = is_array($data['error'])
                    ? (string)($data['error']['message'] ?? 'Provider streaming error')
                    : (string)$data['error'];
                return;
            }

            $delta = '';
            if($anthropic) {
                if(($data['type'] ?? '') === 'content_block_delta'
                    && ($data['delta']['type'] ?? '') === 'text_delta') {
                    $delta = (string)($data['delta']['text'] ?? '');
                }
                if(($data['type'] ?? '') === 'message_start') {
                    $input = (int)($data['message']['usage']['input_tokens'] ?? 0);
                    $usage['input_tokens'] = $input;
                }
                if(($data['type'] ?? '') === 'message_delta') {
                    $output = (int)($data['usage']['output_tokens'] ?? 0);
                    $usage['output_tokens'] = $output;
                }
            } else {
                $delta = (string)($data['choices'][0]['delta']['content'] ?? '');
                if(isset($data['usage']) && is_array($data['usage'])) {
                    $usage = [
                        'input_tokens' => (int)($data['usage']['prompt_tokens'] ?? 0),
                        'output_tokens' => (int)($data['usage']['completion_tokens'] ?? 0),
                        'total_tokens' => (int)($data['usage']['total_tokens'] ?? 0),
                    ];
                }
            }

            if($delta === '') return;
            $content .= $delta;
            try {
                $onDelta($delta);
            } catch(\Throwable $e) {
                $callbackError = $e;
            }
        };

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $this->options['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_WRITEFUNCTION => function($curl, string $chunk) use (&$buffer, $consume, &$callbackError): int {
                $length = strlen($chunk);
                $buffer .= str_replace("\r\n", "\n", $chunk);
                while(($position = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $position);
                    $buffer = substr($buffer, $position + 1);
                    $consume($line);
                    if($callbackError) return 0;
                }
                return $length;
            },
        ]);

        $ok = curl_exec($ch);
        if($buffer !== '') $consume($buffer);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        if($callbackError) {
            return [
                'success' => false,
                'content' => $content,
                'message' => 'Streaming callback failed: ' . $callbackError->getMessage(),
                'usage' => $usage,
                'raw' => [],
            ];
        }
        if($ok === false || $curlErrno) {
            return [
                'success' => false,
                'content' => $content,
                'message' => "cURL error ({$curlErrno}): {$curlError}",
                'usage' => $usage,
                'raw' => [],
            ];
        }
        if($httpCode >= 400 || $apiError !== '') {
            $decoded = json_decode($rawErrorBody, true);
            $message = $apiError;
            if($message === '' && is_array($decoded)) {
                $message = (string)($decoded['error']['message'] ?? $decoded['error'] ?? '');
            }
            if($message === '') $message = "HTTP {$httpCode}";
            return [
                'success' => false,
                'content' => $content,
                'message' => $message,
                'usage' => $usage,
                'raw' => $decoded ?: [],
            ];
        }

        if($anthropic) {
            $usage['total_tokens'] = (int)($usage['input_tokens'] ?? 0) + (int)($usage['output_tokens'] ?? 0);
        }

        return [
            'success' => $content !== '',
            'content' => $content,
            'message' => $content !== '' ? 'OK' : 'Provider returned an empty stream',
            'usage' => $usage,
            'raw' => [],
        ];
    }

    /**
     * Fetch OpenAI model IDs.
     */
    protected function fetchOpenAIModels(): array {
        $response = $this->curlGetRequest('https://api.openai.com/v1/models', [
            'Authorization: Bearer ' . $this->apiKey,
        ]);

        if (!$response['success']) return $response + ['models' => []];

        $models = [];
        foreach (($response['data']['data'] ?? []) as $model) {
            $id = trim((string)($model['id'] ?? ''));
            if ($id === '') continue;
            $models[$id] = $id;
        }

        ksort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'success' => !empty($models),
            'models'  => $models,
            'message' => $models ? 'Models refreshed.' : 'No models returned by OpenAI.',
            'raw'     => $response['data'],
        ];
    }

    /**
     * Fetch OpenRouter model IDs.
     */
    protected function fetchOpenRouterModels(): array {
        $response = $this->curlGetRequest('https://openrouter.ai/api/v1/models', [
            'Authorization: Bearer ' . $this->apiKey,
        ]);

        if (!$response['success']) return $response + ['models' => []];

        $models = [];
        foreach (($response['data']['data'] ?? []) as $model) {
            $id = trim((string)($model['id'] ?? ''));
            if ($id === '') continue;
            $name = trim((string)($model['name'] ?? ''));
            $models[$id] = $name !== '' ? $name : $id;
        }

        ksort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'success' => !empty($models),
            'models'  => $models,
            'message' => $models ? 'Models refreshed.' : 'No models returned by OpenRouter.',
            'raw'     => $response['data'],
        ];
    }

    /**
     * Make a GET request.
     *
     * @return array ['success', 'data', 'message', 'httpCode']
     */
    protected function curlGetRequest(string $url, array $headers): array {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->options['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        $errno        = curl_errno($ch);


        if ($errno) {
            return [
                'success'  => false,
                'data'     => [],
                'message'  => "cURL error ({$errno}): {$error}",
                'httpCode' => 0,
            ];
        }

        $data = json_decode($responseBody, true);

        if ($data === null && $responseBody !== '') {
            return [
                'success'  => false,
                'data'     => [],
                'message'  => "Invalid JSON response (HTTP {$httpCode})",
                'httpCode' => $httpCode,
            ];
        }

        if ($httpCode >= 400) {
            $errorMsg = 'HTTP ' . $httpCode;
            if (isset($data['error']['message'])) {
                $errorMsg .= ': ' . $data['error']['message'];
            } elseif (isset($data['error']) && is_string($data['error'])) {
                $errorMsg .= ': ' . $data['error'];
            }

            return [
                'success'  => false,
                'data'     => $data ?: [],
                'message'  => $errorMsg,
                'httpCode' => $httpCode,
            ];
        }

        return [
            'success'  => true,
            'data'     => $data ?: [],
            'message'  => 'OK',
            'httpCode' => $httpCode,
        ];
    }
}
