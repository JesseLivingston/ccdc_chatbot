<?php
namespace Ccdc\ChatBot\Filter;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Filter\FilterState;
use Flarum\Query\QueryCriteria;
# use Flarum\Tags\Tag;

# 从 “全部主题” 页隐藏， 而不是 “全部” 主题页隐藏
class HideChatBotTagsFromAllDiscussionsPage
{
    public function __construct(protected SettingsRepositoryInterface $settings) {
        
    }

    public function __invoke(FilterState $filter, QueryCriteria $queryCriteria)
    {
        if (count($filter->getActiveFilters()) > 0) {
            return;
        }
        $enabledTagIds = json_decode($this->settings->get('ccdc-chatbot.enabled-tags', []), true);
        $filter->getQuery()->whereNotIn('discussions.id', function ($query) use ($enabledTagIds) {
            return $query->select('discussion_id')
            ->from('discussion_tag')
            ->whereIn('tag_id', $enabledTagIds);
        });
    }
}
