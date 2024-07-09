<?php

namespace Ccdc\ChatBot\Listener;


use Flarum\Discussion\Event\Saving;
use Flarum\Discussion\Discussion;
use Flarum\Tags\Tag;
use Illuminate\Contracts\Events\Dispatcher;

class DiscussionSavingEventListener {

    use DispatchEventsTrait;

    protected $events;

    protected SettingsRepositoryInterface $settings;

    public function __construct(Dispatcher $events, SettingsRepositoryInterface $settings)
    {
        $this->events = $events;
        $this->settings = $settings;
    }

    /*
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Saving::class, [$this, 'onDiscussionSaving']);
    }
    */

    public function handle(Saving $event): void
    {
        /** @var Discussion $discussion */
        $discussion = $event->discussion;

        $chat_bot_tag_ids = $this->settings->get('ccdc-chatbot.enabled-tags', []);
        // Check if any tag with "code" in its name is selected
        $user_selected_tag_ids = Arr::pluck($discussion->tags, 'id');

        if (!empty(array_intersect($chat_bot_tag_ids, $user_selected_tag_ids))) {
            if ($discussion->title === '' && $hasCodedTag) {
                $discussion->title = mb_substr($discussion->content, 0, 20, "UTF-8");
                $discussion->markAsChanged('title');
            }
        }
    }
}