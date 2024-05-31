<?php

namespace Ccdc\ChatBot\Filter;

use Flarum\Http\SlugManager;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Filter\FilterInterface;
# use Flarum\Search\SearchState;
use Flarum\Filter\FilterState;
# use Flarum\Search\ValidateFilterTrait;
use Flarum\Filter\ValidateFilterTrait;
use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;

class ChatBotFilter implements FilterInterface
{
    use ValidateFilterTrait;
    # protected SlugManager $slugger;
    # protected SettingsRepositoryInterface $settings;

    public function __construct(protected SettingsRepositoryInterface $settings,
        protected SlugManager $slugger
    ) {
        # $this->$settings = $settings;
    }

    public function getFilterKey(): string
    {
        return 'tag';
    }

    public function filter(FilterState $state, string|array $value, bool $negate)
    {
        $this->constrain($state->getQuery(), $value, $negate, $state->getActor());
    }

    # todo 
    protected function constrain(Builder $query, string|array $rawSlugs, bool $negate, User $actor): void
    {
        $rawSlugs = (array) $rawSlugs;

        $inputSlugs = $this->asStringArray($rawSlugs);

        foreach ($inputSlugs as $orSlugs) {
            $slugs = explode(',', $orSlugs);
            # echo("---------------------");
            # echo($slugs);
            $query->where(function (Builder $query) use ($slugs, $negate, $actor) {
                foreach ($slugs as $slug) {
                    if ($slug === 'untagged') {
                        $query->whereIn('discussions.id', function (Builder $query) {
                            $query->select('discussion_id')
                                ->from('discussion_tag');
                        }, 'or', ! $negate);
                    } else {
                        // @TODO: grab all IDs first instead of multiple queries.
                        try {
                            $id = $this->slugger->forResource(Tag::class)->fromSlug($slug, $actor)->id;
                        } catch (ModelNotFoundException) {
                            $id = null;
                        }
                        # echo("id is {$id}");
                        $actor_id = $actor->getAttribute("id");
                        $query->whereIn('discussions.id', function (Builder $query) use ($id, $actor_id) {
                            # 这个 enabledTagIds 应该放到外面， 然后 use 里包含进来就可以了， 但现在我懒得改了， 就这样吧
                            $enabledTagIds = json_decode($this->settings->get('ccdc-chatbot.enabled-tags', []), true);
                            # $enabledTagIds = json_decode($enabledTagIds, true)
                            if (in_array($id, $enabledTagIds)) {
                                $query->select('discussion_id')
                                    ->from('discussion_tag')
                                    ->where('tag_id', $id)
                                    ->where('user_id', $actor_id);
                            } else {
                                $query->select('discussion_id')
                                    ->from('discussion_tag')
                                    ->where('tag_id', $id);
                            }
                            
                        }, 'or', $negate);
                    }
                }
            });
        }
    }
}