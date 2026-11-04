<?php

namespace FluentSupport\App\Services\Integrations\AI\Providers;

use FluentSupport\App\Services\Integrations\AI\BaseAIProvider;
use WP_Error;

class AnthropicProvider extends BaseAIProvider
{
    protected $messagesUrl = 'https://api.anthropic.com/v1/messages';
    protected $apiVersion  = '2023-06-01';

    public function getProviderName(): string
    {
        return 'Anthropic';
    }

    protected function getRecommendedModel(): string
    {
        return 'claude-sonnet-5-5';
    }

    public function getAvailableModels(): array
    {
        $models = [
            ['value' => 'claude-opus-5-5',   'label' => 'Claude Opus 5.5 (Most Powerful)'],
            ['value' => 'claude-sonnet-5-5', 'label' => 'Claude Sonnet 5.5'],
            ['value' => 'claude-haiku-5-5',  'label' => 'Claude Haiku 5.5 (Fastest)'],
        ];

        return apply_filters('fluent_support/supported_anthropic_models', $models);
    }

    /**
     * @param int    $ticketId
     * @param string $prompt
     * @param array  $messages
     * @return string|WP_Error
     */
    public function generateResponse(int $ticketId, string $prompt, array $messages = [])
    {
        list($systemText, $anthropicMessages) = $this->convertMessages($messages, $prompt);

        $payload = [
            'model'      => $this->model,
            'max_tokens' => 8000, // thinking tokens count against this
            'messages'   => $anthropicMessages,
        ];

        if ($systemText) {
            $payload['system'] = $systemText;
        }

        $headers = [
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
        ];

        $body = $this->sendRequest($this->messagesUrl, $payload, $headers);

        if (is_wp_error($body)) {
            return $body;
        }

        // Current models think by default, so the first block is often a thinking block
        $content = '';
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'];
            }
        }
        $tokens  = ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0);

        $this->fireSuccessAction($ticketId, $prompt, $tokens);

        return $content;
    }

    protected function extractErrorMessage(array $body): string
    {
        // Anthropic error format: { "type": "error", "error": { "type": "...", "message": "..." } }
        return $body['error']['message']
            ?? $body['message']
            ?? __('Unknown error occurred.', 'fluent-support');
    }

    /**
     * Converts OpenAI-format messages to Anthropic format.
     * Returns [systemText, messages].
     */
    private function convertMessages(array $messages, string $fallbackPrompt): array
    {
        if (empty($messages)) {
            return ['', [['role' => 'user', 'content' => $fallbackPrompt]]];
        }

        $systemText         = '';
        $anthropicMessages  = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemText = $msg['content'];
            } else {
                $anthropicMessages[] = $msg;
            }
        }

        if (empty($anthropicMessages)) {
            $anthropicMessages = [['role' => 'user', 'content' => $systemText ?: $fallbackPrompt]];
            $systemText        = '';
        }

        return [$systemText, $anthropicMessages];
    }

}

