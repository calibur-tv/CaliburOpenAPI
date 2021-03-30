<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:31
 */

namespace App\Http\Repositories;


use App\Http\Transformers\Tag\TagResource;
use App\Models\QuestionRule;
use App\Models\Zone;
use App\User;

class TagRepository extends Repository
{
    public function item($slug, $refresh = false)
    {
        if (!$slug)
        {
            return null;
        }

        $result = $this->RedisItem("tag:{$slug}", function () use ($slug)
        {
            $tag = Zone
                ::where('slug', $slug)
                ->with('content')
                ->first();

            if (is_null($tag))
            {
                $tag = Zone
                    ::where('id', $slug)
                    ->with('content')
                    ->first();
            }

            if (is_null($tag))
            {
                return 'nil';
            }

            return new TagResource($tag);
        }, $refresh);

        if ($result === 'nil')
        {
            return null;
        }

        return $result;
    }

    public function children($slug, $page, $count = 10, $refresh = false)
    {
        $result = $this->RedisArray("tag-{$slug}-children", function () use ($slug)
        {
            $tag = Zone
                ::where('parent_slug', $slug)
                ->orderBy('activity_stat', 'desc')
                ->orderBy('pin_count', 'desc')
                ->orderBy('followers_count', 'desc')
                ->orderBy('seen_user_count', 'desc')
                ->with('content')
                ->get();

            return TagResource::collection($tag);
        }, $refresh);

        return $this->filterIdsByPage($result, $page, $count);
    }

    public function rule($slug, $refresh = false)
    {
        return $this->RedisItem("tag-join-rule:{$slug}", function () use ($slug)
        {
            return QuestionRule
                ::where('tag_slug', $slug)
                ->first();
        }, $refresh);
    }

    public function hottest($page, $take)
    {
        $result = $this->RedisArray('hottest-channel', function ()
        {
            $tag = Zone
                ::orderBy('activity_stat', 'desc')
                ->orderBy('pin_count', 'desc')
                ->orderBy('followers_count', 'desc')
                ->orderBy('seen_user_count', 'desc')
                ->with('content')
                ->take(300)
                ->get();

            return TagResource::collection($tag);
        });

        return $this->filterIdsByPage($result, $page, $take);
    }

    public function search()
    {
        $result = $this->RedisArray('tag-all-search', function ()
        {
            $tag = Zone
                ::whereIn('parent_slug', [
                    config('app.tag.bangumi'),
                    config('app.tag.topic'),
                    config('app.tag.game')
                ])
                ->with('content')
                ->get();

            return TagResource::collection($tag);
        });

        return $result;
    }

    public function bookmarks($slug, $refresh = false)
    {
        $result = $this->RedisArray("user-bookmark-tags:{$slug}", function () use ($slug)
        {
            // TODO：废弃
            return [
                'bangumi' => [],
                'game' => [],
                'topic' => [],
                'notebook' => []
            ];
        }, $refresh);

        if ($result === 'nil')
        {
            return null;
        }

        return $result;
    }

    public function receiveTagChain($slug, $result = [])
    {
        if (!$slug)
        {
            return $result;
        }
        $tag = $this->item($slug);
        if (!$tag)
        {
            return $result;
        }

        if (empty($result))
        {
            $result[] = $slug;
        }

        if (!$tag->parent_slug || $tag->parent_slug === config('app.tag.calibur'))
        {
            return $result;
        }
        $result[] = $tag->parent_slug;

        return $this->receiveTagChain($tag->parent_slug, $result);
    }
}
