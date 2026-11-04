<?php

namespace FluentSupport\App\Services\Integrations\AI;

use WP_Error;

abstract class BaseAIProvider
{
    protected $apiKey;
    protected $model;

    public function __construct(string $apiKey, string $model)
    {
        $this->apiKey = $apiKey;
        $this->model  = $this->resolveModel($model);
    }

    abstract public function getProviderName(): string;

    abstract public function getAvailableModels(): array;

    /**
     * The model used when "Default" is selected or the saved model is no longer offered.
     */
    abstract protected function getRecommendedModel(): string;

    public function getDefaultModel(): string
    {
        return apply_filters('fluent_support/ai_default_model', $this->getRecommendedModel(), $this->getProviderName());
    }

    /**
     * The models list for the settings page, with the "Default" option first.
     */
    public function getModelOptions(): array
    {
        $models       = $this->getAvailableModels();
        $defaultModel = $this->getDefaultModel();
        $defaultLabel = $defaultModel;

        foreach ($models as $model) {
            if ($model['value'] === $defaultModel) {
                $defaultLabel = $model['label'];
                break;
            }
        }

        array_unshift($models, [
            'value' => 'default',
            /* translators: %s: name of the AI model the plugin picks, e.g. "Claude Sonnet 5.5" */
            'label' => sprintf(__('Default (currently %s)', 'fluent-support'), $defaultLabel),
        ]);

        return $models;
    }

    private function resolveModel(string $model): string
    {
        if ($model === '' || $model === 'default') {
            return $this->getDefaultModel();
        }

        // A model removed from the list (usually retired by the provider) falls back to the default
        if (!in_array($model, array_column($this->getAvailableModels(), 'value'), true)) {
            return $this->getDefaultModel();
        }

        return $model;
    }

    /**
     * @param int    $ticketId
     * @param string $prompt
     * @param array  $messages
     * @return string|WP_Error
     */
    abstract public function generateResponse(int $ticketId, string $prompt, array $messages = []);

    public function streamResponse(int $ticketId, string $prompt, array $messages = []): void
    {
        $content = $this->generateResponse($ticketId, $prompt, $messages);

        if (is_wp_error($content)) {
            echo 'data: ' . wp_json_encode(['error' => $content->get_error_message()]) . "\n\n";
            flush();
            return;
        }

        echo 'data: ' . wp_json_encode(['choices' => [['delta' => ['content' => $content]]]]) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
    }


    /**
     * @return array|WP_Error
     */
    protected function sendRequest(string $url, array $payload, array $headers)
    {
        $timeout  = apply_filters('fs_ai_request_timeout', 60);
        $response = wp_remote_post($url, [
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body'    => wp_json_encode($payload),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true) ?? [];

        if ($code !== 200) {
            return new WP_Error($code, $this->extractErrorMessage($body));
        }

        return $body;
    }

    protected function fireSuccessAction(int $ticketId, string $prompt, int $tokens): void
    {
        do_action('fluent_support/ai_response_success', $ticketId, $prompt, $tokens, $this->getProviderName());
    }

    protected function extractErrorMessage(array $body): string
    {
        return $body['error']['message']
            ?? $body['message']
            ?? __('Unknown error occurred.', 'fluent-support');
    }
}
