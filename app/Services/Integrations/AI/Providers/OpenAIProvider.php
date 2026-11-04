<?php

namespace FluentSupport\App\Services\Integrations\AI\Providers;

use FluentSupport\App\Services\Integrations\AI\BaseAIProvider;
use WP_Error;

class OpenAIProvider extends BaseAIProvider
{
    protected $completionsUrl = 'https://api.openai.com/v1/chat/completions';

    public function getProviderName(): string
    {
        return 'OpenAI';
    }

    protected function getRecommendedModel(): string
    {
        return 'gpt-6.1-sol';
    }

    public function getAvailableModels(): array
    {
        $models = [
            ['value' => 'gpt-6-astra',   'label' => 'GPT-6 Astra (Most Powerful)'],
            ['value' => 'gpt-6.1-sol',   'label' => 'GPT-6.1 Sol'],
            ['value' => 'gpt-6-sol',     'label' => 'GPT-6 Sol'],
            ['value' => 'gpt-6-luna',    'label' => 'GPT-6 Luna (Fastest)'],
            ['value' => 'gpt-5.6-sol',   'label' => 'GPT-5.6 Sol'],
            ['value' => 'gpt-5.6-terra', 'label' => 'GPT-5.6 Terra'],
            ['value' => 'gpt-5.6-luna',  'label' => 'GPT-5.6 Luna'],
            ['value' => 'gpt-5.4-mini',  'label' => 'GPT-5.4 Mini'],
            ['value' => 'gpt-5-mini',    'label' => 'GPT-5 Mini'],
        ];

        return apply_filters('fluent_support/supported_openai_models', $models);
    }

    /**
     * @param int    $ticketId
     * @param string $prompt
     * @param array  $messages
     * @return string|WP_Error
     */
    public function generateResponse(int $ticketId, string $prompt, array $messages = [])
    {
        if (empty($messages)) {
            $messages = [['role' => 'system', 'content' => $prompt]];
        }

        $payload = [
            'model'    => $this->model,
            'messages' => $messages,
        ];

        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];

        $body = $this->sendRequest($this->completionsUrl, $payload, $headers);

        if (is_wp_error($body)) {
            return $body;
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        $tokens  = ($body['usage']['prompt_tokens'] ?? 0) + ($body['usage']['completion_tokens'] ?? 0);

        $this->fireSuccessAction($ticketId, $prompt, $tokens);

        return $content;
    }

}


