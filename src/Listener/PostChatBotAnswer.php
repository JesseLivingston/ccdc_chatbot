<?php

namespace Ccdc\ChatBot\Listener;

use Carbon\Carbon;
# use Ccdc\ChatBot\CloseAI;
use Flarum\Discussion\Event\Started;

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

class PostChatBotAnswer
{
    use DispatchEventsTrait;

    protected $events;

    protected SettingsRepositoryInterface $settings;

    # protected CloseAI $closeAI;

    # public function __construct(Dispatcher $events, SettingsRepositoryInterface $settings, CloseAI $closeAI)
    public function __construct(Dispatcher $events, SettingsRepositoryInterface $settings)
    {
        $this->events = $events;
        $this->settings = $settings;
        # $this->closeAI = $closeAI;
    }

    /**
     * @throws PermissionDeniedException
     */
    public function handle(Started $event): void
    {
        # 总开关
        if (! $this->settings->get('ccdc-chatbot.enable_on_discussion_started', true)) {
            return;
        }

        $discussion = $event->discussion;
        $actor = $event->actor;
        $enabledTagIds = $this->settings->get('ccdc-chatbot.enabled-tags', []);

        # 特定标签
        if ($enabledTagIds = json_decode($enabledTagIds, true)) {
            $discussion = $event->discussion;

            $tagIds = Arr::pluck($discussion->tags, 'id');

            if (! array_intersect($enabledTagIds, $tagIds)) {
                return;
            }
        }

        # 用哪个用户回复
        if ($chatBotId = $this->settings->get('ccdc-chatbot.user_prompt')) {
            # $user = User::find($userId);
            $discussion = $event->discussion;
            # $content = "Re: " . $firstPost->content;

            $server_url = $this->settings->get("ccdc-chatbot.server_url");
            $model = $this->settings->get("ccdc-chatbot.model");
            # $ollamaClient = new OllamaClient(new HttpClient(["base_url" => $server_url]));
            $ollamaClient = new OllamaClient(new HttpClient());

            try {
                # $result = $ollamaClient->generateCompletion($discussion->firstPost->content, $model);
                # $content = $result->getResponse();
                $last_post_content = $discussion->firstPost->content;
                

                $headers = array('Content-Type' => 'application/json');
                $data = array("model" => $model, "prompt" => $discussion->firstPost->content);
                # $options = array();
                $response = WpRequests::post($server_url . "/api/generate", $headers, json_encode($data));
                if ($response->status_code == 200) {
                    $content = $server_url . "/api/generate" . " with model " . $model . ": " . $response->body;
                } else {
                    $content = "LLM status code: " . $response->status_code; 
                }
                $post = CommentPost::reply(
                    $discussion->id,
                    $content,
                    $chatBotId,
                    null,
                );
                $post->created_at = Carbon::now();
                $post->save();

            } catch (Exception $e) {
                # error_log
                return; #"Ollama 出错了";
            }
        }
    }
}
