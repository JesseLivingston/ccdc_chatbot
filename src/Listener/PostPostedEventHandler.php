<?php

namespace Ccdc\ChatBot\Listener;

use Carbon\Carbon;
use Ccdc\ChatBot\CloseAI;
use OpenAI\Factory;
use OpenAI\Client as OpenAIClient;
# use Flarum\Discussion\Event\Started;

#
use Flarum\Post\Event\Posted;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

# use Flarum\Tags\Content\Tag;

use GuzzleHttp\Client as HttpClient;
use Evoware\OllamaPHP\OllamaClient;

use WpOrg\Requests\Requests as WpRequests;

# require 'vendor/autoload.php';
use Elastic\Elasticsearch\ClientBuilder as ESClientBuilder;
use Elastic\Elasticsearch\Helper\Iterators\SearchHitIterator;
use Elastic\Elasticsearch\Helper\Iterators\SearchResponseIterator;

use Illuminate\Support\Facades\Log;

class PostPostedEventHandler
{
    use DispatchEventsTrait;

    protected $events;

    protected SettingsRepositoryInterface $settings;

    # protected CloseAI $closeAI;

    # public function __construct(Dispatcher $events, SettingsRepositoryInterface $settings, CloseAI $close_ai)
    public function __construct(Dispatcher $events, SettingsRepositoryInterface $settings)
    {
        $this->events = $events;
        $this->settings = $settings;
        # $this->close_ai = $close_ai;
    }

    /**
     * @throws PermissionDeniedException
     */
    public function handle(Posted $event): void
    {
        # 总开关
        if (! $this->settings->get('ccdc-chatbot.enable_on_discussion_started', true)) {
            return;
        }

        $discussion = $event->post->discussion;
        $actor = $event->actor;
        $enabledTagIds = $this->settings->get('ccdc-chatbot.enabled-tags', []);

        # 特定标签
        if ($enabledTagIds = json_decode($enabledTagIds, true)) {
            # $discussion = $event->post->discussion;

            $tagIds = Arr::pluck($discussion->tags, 'id');

            if (! array_intersect($enabledTagIds, $tagIds)) {
                return;
            }
            $tag_names = Arr::pluck($discussion->tags, "name");
            
        }

        # 用哪个用户回复
        if ($chatBotId = $this->settings->get('ccdc-chatbot.user_prompt')) {
            # 
            $code_tags = array_values(array_filter($tag_names, function($k) {
                return stripos($k, 'code') !== false; // 使用 stripos 忽略大小写
            }));
            if (empty($code_tags)) {
                $this->chat_as_tom($discussion->lastPost->content, $chatBotId, $discussion->id);
            } else {
                # 代码问题就不用查 es 了
                $code_prompt_template = $this->settings->get("ccdc-chatbot.code_prompt_template");
                $default_code_template = <<<EOT
作为一个成熟的程序员， 根据用户的问题
{{question}}
生成相应的代码
EOT;
                $code_prompt_template = empty($code_prompt_template) ? $default_code_template : $code_prompt_template;
                $template_params = ["question" => $discussion->lastPost->content];
                $prompt = strtr($code_prompt_template, $template_params);
                $this->chat_with_llm($prompt, $chatBotId, $discussion->id);
            }
        }
    }

    private function search_vector(string $post_content): ?string {
        $embedding_mode = $this->settings->get("ccdc-chatbot.embedding_mode");

        if (strtolower($embedding_mode) == "ollama") {
            $server_url = $this->settings->get("ccdc-chatbot.server_url");
            $ollama_client = new OllamaClient(new HttpClient(["base_uri" => "http://localhost:11434/api/", 
                                                            "timeout" => 30]));
            $ollama_client->setModel("shaw/dmeta-embedding-zh");                                     
            # $ollama_client = new OllamaClient(new HttpClient());
            return $ollama_client->generateEmbeddings($post_content, modelName: "shaw/dmeta-embedding-zh");
        } else {
            $openAIClient = new Factory().withBaseUri($server_url).withApiKey($api_key).make();
            return $openAIClient->embeddings()->create([
                'input' => $post_content
            ]);
        }
    }

    private function chat_with_llm(string $prompt, $chat_bot_id, $discussion_id): ?String {
        $prompt_messages[] = array("role" => "user", "content" => $prompt);

        $server_url = $this->settings->get("ccdc-chatbot.server_url");
        $model = $this->settings->get("ccdc-chatbot.model");
        $api_key = $this->settings->get('ccdc-chatbot.api_key');
        try {
            $factory = new Factory();
            $openAIClient = $factory->withBaseUri($server_url)->withApiKey($api_key)->make();
            $result = $openAIClient->chat()->create([
                'model' => $model,
                'messages' => $prompt_messages
            ]);
    
            $result_content = $result->choices[0]->message->content;
            $result_content = $result_content . "\n\n\n-----------------\n" . $prompt;
            $post = CommentPost::reply(
                $discussion_id,
                $result_content,
                $chat_bot_id,
                null,
            );
            $post->created_at = Carbon::now();
            $post->save();
        } catch (Exception $e) {
            $post = CommentPost::reply(
                $discussion_id,
                "调用 LLM 时出错",
                $chat_bot_id,
                null,
            );
            $post->created_at = Carbon::now();
            $post->save();
        }
        return "OK";
    }

    private function chat_as_tom(string $prompt):?String {
        $es_server_url = $this->settings->get("ccdc-chatbot.elasticsearch_url");
        $es_username = $this->settings->get("ccdc-chatbot.elasticsearch_username");
        $es_password = $this->settings->get("ccdc-chatbot.elasticsearch_password");
        $es_api_key = $this->settings->get("ccdc-chatbot.elasticsearch_api_key");
        
        $prompt_vector = $this->search_vector($prompt);
        echo "Type of embeddings: " . gettype($prompt_vector) . "\n";
        # echo "Generated Embeddings: " . json_encode($prompt_vector) . "\n";
        $es_client = ESClientBuilder::create()
                    ->setHosts(array($es_server_url))
                    ->setBasicAuthentication($es_username, $es_password)
                    ->build();
        # "field": "title_vector", "k": 1, "num_candidates": 10000, "query_vector": question_vector
        $knowledge_arr = [];
        foreach(["title", "texts"] as $field) {
            $field_params = ["index" => "val_info",
                            "body" => [
                                # "query" => [
                                    "knn" => [
                                        "field" => $field, 
                                        "k" => 1,
                                        "vector" => $prompt_vector
                                        ]
                                    ]
                                #]
                            ];
            $field_results = $es_client->knnSearch($field_params);
            
            foreach($field_results["hits"]["hits"] as $hit) {
                # print_r(array_keys($hit["_source"]));
                # Log::info("问题: $user_post");
                Log::info("ElasticSearch 结果: $hit");
                $hit_source = $hit["_source"];
                
                if (array_key_exists("texts", $hit_source)) {
                    $knowledge_arr[] = $hit["_source"]["texts"];
                }
            }
        }
        $knowledge = implode("\n", $knowledge);
        $common_prompt_template = $this->settings->get("ccdc-chatbot.common_prompt_template");
        $default_common_template = <<<EOT
根据以下信息回答问题:
{{knowledge}}
问题: {{question}}
EOT;    
        $common_prompt_template = empty($common_prompt_template) ? $default_common_template : $common_prompt_template;
                
        $template_params = ["knowledge" => $knowledge, "question" => $discussion->lastPost->content];
        
        $prompt = strtr($common_prompt_template, $template_params);
        $this->chat_with_llm($prompt);
    }
}
