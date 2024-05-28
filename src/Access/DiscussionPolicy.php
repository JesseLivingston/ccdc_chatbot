<?php

namespace Ccdc\ChatBot\Access;

use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class DiscussionPolicy extends AbstractPolicy
{
    protected SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function useChatBotAssistant(User $actor, Discussion $discussion): bool
    {
        return $actor->hasPermission('discussion.useChatBotAssistant');
    }
}
