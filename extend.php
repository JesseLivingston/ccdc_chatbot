<?php

/*
 * This file is part of ccdc/chatbot.
 *
 * Copyright (c) 2024 Chen Fan.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Ccdc\ChatBot;

use Ccdc\ChatBot\Access\DiscussionPolicy;
use Ccdc\ChatBot\Listener\PostChatBotAnswer;
use Ccdc\ChatBot\Listener\PostPostedEventHandler;

use Flarum\Discussion\Event\Started;
use Flarum\Post\Event\Posted;
use Flarum\Extend;
use Flarum\Frontend\Document;


return [
    
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
        # ->content(function (Document $document) { 
        #     $document->head[] = '<script>alert("Hello, world!!!")</script>';
        # }),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->default('ccdc-chatbot.model', 'qwen:4b')
        ->default('ccdc-chatbot.enable_on_discussion_started', true)
        ->default('ccdc-chatbot.max_tokens', 10000)
        ->default('ccdc-chatbot.user_prompt_badge_text', 'Assistant')
        ->serializeToForum('chatBotUserPromptId', 'ccdc-chatbot.user_prompt')
        ->serializeToForum('chatBotBadgeText', 'ccdc-chatbot.user_prompt_badge_text'),

    # (new Extend\Event())
    #     ->listen(Started::class, PostChatBotAnswer::class),

    (new Extend\Event())
        ->listen(Posted::class, PostPostedEventHandler::class),

    (new Extend\Policy())
        ->modelPolicy(Discussion::class, DiscussionPolicy::class),

];
