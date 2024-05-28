<?php

namespace Ccdc\ChatBot;

use Exception;
use Flarum\Settings\SettingsRepositoryInterface;
# use OpenAI as OpenAIClient;
# use OpenAI\Client;

use GuzzleHttp\Client as HttpClient;
# use Evoware\OllamaPHP\OllamaClient;

class CloseAI
{
    protected SettingsRepositoryInterface $settings;

    protected ?string $server_url = null;
    protected ?string $api_key = null;
    protected ?string $model = null;

    public ?Client $client = null;
    # public ? OllamaClient $ollamaClient = null;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
        $server_url = $this->settings->get("ccdc-chatbot.server_url");
        $this->api_key = $this->settings->get('ccdc-chatbot.api_key');
        $this->model = $this->settings->get('ccdc-chatbot.model');
        # $this->client = OpenAIClient::client($this->apiKey);
        

        $this->client = new HttpClient();
        # $this->ollamaClient = new OllamaClient(new HttpClient($server_url));
    }

    public function completions(string $content = null): ?string
    {
        # if (! $this->apiKey) {
        #     return null;
        # }

        # $maxTokens = (int) $this->settings->get('datlechin-chatgpt.max_tokens', 100);
        $model = $this->settings->get("ccdc-chatbot.model");
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ];

            $body = "{
                'messages': [
                  {
                    'content': 'You are a helpful assistant',
                    'role': 'system'
                  },
                  {
                    'content': '{$content}',
                    'role': 'user'
                  }
                ],
                'model': '{$this->model}',
                'frequency_penalty': 0,
                'max_tokens': 2048,
                'presence_penalty': 0,
                'stop': null,
                'stream': false,
                'temperature': 1,
                'top_p': 1,
                'logprobs': false,
                'top_logprobs': null
            }";
            $request = new Request('POST', '{$this->server_url}/chat/completions', $headers, $body);
            $res = $client->sendAsync($request)->wait();
            # $res_body = $res->getBody();

            return $res;
            # $result = $ollamaClient->generateCompletion($content, ['model' => $model]);
        } catch (Exception $e) {
            return "LLM 出错了";
        }

        # return $result->choices[0]->text;
        return "Unknow error";
    }
}
