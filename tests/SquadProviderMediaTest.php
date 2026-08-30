<?php

require_once dirname(__DIR__) . '/SquadProvider.php';

use ProcessWire\SquadProvider;

final class CapturingSquadProvider extends SquadProvider {
    public array $capturedBody = [];
    public string $capturedUrl = '';

    protected function curlBinaryRequest(string $url, array $body, array $headers, int $maxBytes): array {
        $this->capturedUrl = $url;
        $this->capturedBody = $body;
        return [
            'success' => true,
            'body' => "ID3\x04\x00\x00test-audio",
            'contentType' => 'audio/mpeg',
            'message' => 'OK',
        ];
    }
}

function assertMedia(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$openRouter = new CapturingSquadProvider('openrouter', [
    'audioUrl' => 'https://openrouter.ai/api/v1/audio/speech',
    'defaultAudioModel' => 'x-ai/grok-voice-tts-1.0',
    'defaultAudioVoice' => 'eve',
], 'test-key', 'text-model');
$result = $openRouter->generateAudio('hospital', ['instructions' => 'ignored for OpenRouter']);
assertMedia($result['success'], 'OpenRouter speech should succeed.');
assertMedia($openRouter->capturedBody['model'] === 'x-ai/grok-voice-tts-1.0', 'OpenRouter TTS model missing.');
assertMedia($openRouter->capturedBody['voice'] === 'eve', 'OpenRouter voice missing.');
assertMedia(!isset($openRouter->capturedBody['instructions']), 'OpenAI-only instructions leaked to OpenRouter.');

$xai = new CapturingSquadProvider('xai', [
    'audioUrl' => 'https://api.x.ai/v1/tts',
    'defaultAudioModel' => 'xai-tts',
    'defaultAudioVoice' => 'eve',
], 'test-key', 'text-model');
$result = $xai->generateAudio('больница', ['language' => 'ru']);
assertMedia($result['success'], 'xAI speech should succeed.');
assertMedia($xai->capturedBody['text'] === 'больница', 'xAI text missing.');
assertMedia($xai->capturedBody['voice_id'] === 'eve', 'xAI Eve voice missing.');
assertMedia($xai->capturedBody['language'] === 'ru', 'xAI language missing.');

$openAI = new CapturingSquadProvider('openai', [
    'audioUrl' => 'https://api.openai.com/v1/audio/speech',
    'defaultAudioModel' => 'gpt-4o-mini-tts',
    'defaultAudioVoice' => 'marin',
], 'test-key', 'text-model');
$result = $openAI->generateAudio('welcome', ['instructions' => 'Speak warmly.']);
assertMedia($result['success'], 'OpenAI speech should succeed.');
assertMedia($openAI->capturedBody['instructions'] === 'Speak warmly.', 'OpenAI instructions missing.');
assertMedia(base64_decode($result['audio'], true) !== false, 'Normalized audio is not valid base64.');

echo "SquadProvider media tests passed\n";
