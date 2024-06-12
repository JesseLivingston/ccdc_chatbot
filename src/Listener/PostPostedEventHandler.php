<?php

namespace Ccdc\ChatBot\Listener;

use Carbon\Carbon;
use Ccdc\ChatBot\CloseAI;
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

use GuzzleHttp\Client as HttpClient;
use Evoware\OllamaPHP\OllamaClient;

use WpOrg\Requests\Requests as WpRequests;

# require 'vendor/autoload.php';
use Elastic\Elasticsearch\ClientBuilder as ESClientBuilder;
use Elastic\Elasticsearch\Helper\Iterators\SearchHitIterator;
use Elastic\Elasticsearch\Helper\Iterators\SearchResponseIterator;

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
        }

        # 用哪个用户回复
        if ($chatBotId = $this->settings->get('ccdc-chatbot.user_prompt')) {
            # $user = User::find($userId);
            # $discussion = $event->post->discussion;
            # $content = "Re: " . $firstPost->content;
            try {
                # TODO, search vector database
                # elasticsearch_url: https://172.20.0.2:9200/
                # elasticsearch api key: NVoyb3VJOEJsNUhza2wyWmticEs6amtzc3VFM2lSRzZGZndrRVNQWHpyQQ==
                # localhost
                $es_server_url = $this->settings->get("ccdc-chatbot.elasticsearch_url");
                $es_username = $this->settings->get("ccdc-chatbot.elasticsearch_username");
                $es_password = $this->settings->get("ccdc-chatbot.elasticsearch_password");
                $es_api_key = $this->settings->get("ccdc-chatbot.elasticsearch_api_key");
                # $es_client = ESClientBuilder::create()
                #     ->setHosts($es_server_url)
                #     ->setApiKey($es_api_key)
                #     ->build();

                # "http://122.51.186.48:9200" elastic 123456
                # "http://localhost:9200" elastic oAjf66at4kdy0LJ8=8ZG

                $es_client = ESClientBuilder::create()
                    ->setHosts(array($es_server_url))
                    ->setBasicAuthentication($es_username, $es_password)
                    ->build();

                $user_post = $discussion->lastPost->content;
                $es_query_params = [
                    "index" => "val_info",
                    "size" => 1,
                    "body"  => [
                        "query" => [
                            "multi_match" => [
                                "query" => $user_post,
                                "fields" => ["q_type", "title", "texts"]
                            ]
                        ]
                    ]
                ];

                $es_results = $es_client->search($es_query_params);
                # $pages = new SearchResponseIterator($es_client, $es_query_params);
                # $hits = new SearchHitIterator($pages);
                
                $reference_context = [];
                $prompt_messages = [];
                $prompt_messages[] = array("role" => "system", 
                        "content" => "你是个债券领域的专家，结合以下上下文回答用户的问题");
                foreach($es_results["hits"]["hits"] as $hit) {
                    # print_r(array_keys($hit["_source"]));
                    $hit_source = $hit["_source"];
                    if (array_key_exists("title", $hit_source)) {
                        $prompt_messages[] = array("role" => "user", 
                                                "content" => $hit["_source"]["title"]);
                        $reference_context[] = $hit["_source"]["title"];
                    }
                    if (array_key_exists("texts", $hit_source)) {
                        $prompt_messages[] = array("role" => "assistant", 
                                                "content" => $hit["_source"]["texts"]);
                        $reference_context[] = $hit["_source"]["texts"];
                    }
                    if (array_key_exists("answer", $hit_source)) {
                        $prompt_messages[] = array("role" => "assistant", 
                                                "content" => $hit["_source"]["answer"]);
                        $reference_context[] = $hit["_source"]["answer"];
                    }
                }
                $prompt_messages[] = array("role" => "user", "content" => $user_post);

                $server_url = $this->settings->get("ccdc-chatbot.server_url");
                $model = $this->settings->get("ccdc-chatbot.model");
                # $ollamaClient = new OllamaClient(new HttpClient(["base_url" => $server_url]));
                # $ollamaClient = new OllamaClient(new HttpClient());
# client.search(index="val_info", size=1, query={"multi_match": {"query": "中债估值在担保品业务中是怎么应用的", "fields": ["p_type", "title", "answer", "texts"]}})
            
                # $result = $ollamaClient->generateCompletion($discussion->firstPost->content, $model);
                # $content = $result->getResponse();
                $api_key = $this->settings->get('ccdc-chatbot.api_key');
                $stream_request = false;
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key
                ];
                
                $body = array("messages" => $prompt_messages, 
                                "model" => $model, 
                                "frequency_penalty" => 0,
                                "max_tokens" => 2048,
                                "presence_penalty" => 0,
                                "stop" => null,
                                "stream" => $stream_request,
                                "temperature" => 1,
                                "top_p" => 1,
                                "logprobs" => false,
                                "top_logprobs" => null);

                # $data = array("model" => $model, "prompt" => $discussion->firstPost->content);
                # $data = array("model" => $model, "prompt" => $discussion->lastPost->content);
                # $options = array();
                # $response = WpRequests::post($server_url . "/api/generate", $headers, json_encode($body));
                $response = WpRequests::post($server_url . "/chat/completions", 
                                            $headers, 
                                            json_encode($body), 
                                            array("timeout" => 30));
                # $res = $this->close_ai.completions($discussion->lastPost->content);
                if ($response->status_code == 200) {
                    if ($stream_request) {
                        $response_text = [];
                        foreach (explode("\n", $response->body) as $line) {
                            if (strpos($line, "DONE") !== false) {
                                $line_json = json_decode(substr($line, 5), true);
                                $response_text[] = $line_json->choices[0]->delta->content;
                            }
                        }
                        $llm_content = implode("", $response_text);
                    } else {
                        $llm_content = json_decode($response->body)->choices[0]->message->content;
                    }
                    
                    # $content = $server_url . "/api/generate" . " with model " . $model . ": " . implode("", $response_text);

                } else {
                    $llm_content = "LLM status code: " . $response->status_code; 
                }
                $reference_count = count($reference_context);
                if ($reference_count > 0) {
                    array_unshift($reference_context, $llm_content, "参考以下内容({$reference_count}条):\n");
                    $reply_content = join("\n------------------", $reference_context);
                } else {
                    $reply_content = $llm_content;
                }
                $post = CommentPost::reply(
                    $discussion->id,
                    $reply_content,
                    $chatBotId,
                    null,
                );
                $post->created_at = Carbon::now();
                $post->save();

            } catch (Exception $e) {
                $post = CommentPost::reply(
                    $discussion->id,
                    "调用 LLM 时出错",
                    $chatBotId,
                    null,
                );
                $post->created_at = Carbon::now();
                $post->save();
                # error_log
                return ;# "Ollama 出错了";
            }
        }
    }

    private function search_vector(string $post_content): ?string {

    }
}
