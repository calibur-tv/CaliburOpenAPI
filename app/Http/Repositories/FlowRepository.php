<?php


namespace App\Http\Repositories;


use App\Models\Idol;
use App\Models\IdolFans;
use App\Models\LiveRoom;
use App\Models\Pin;
use Illuminate\Support\Facades\DB;

class FlowRepository extends Repository
{
    public static $pinHottestVisitKey = 'pin-hottest-visit';

    public static $from = ['index', 'bangumi', 'user'];

    public static $order = ['newest', 'activity', 'hottest'];

    public static $indexSlug = '';

    public function pinNewest($from, $slug, $take, $lastId, $isUp)
    {
        $ids = $this->RedisSort($this->flow_pin_cache_key($from, self::$order[0], $slug), function () use ($from, $slug)
        {
            return Pin
                ::whereNotNull('published_at')
                ->where('trial_type', 0)
                ->when($from === 'bangumi', function ($query) use ($slug)
                {
                    return $query->where('bangumi_slug', $slug);
                })
                ->when($from === 'user', function ($query) use ($slug)
                {
                    return $query->where('user_slug', $slug);
                })
                ->orderBy('published_at', 'DESC')
                ->pluck('published_at', 'slug');

        }, ['is_time' => true]);

        return $this->filterIdsByMaxId($ids, $lastId, $take, false, $isUp);
    }

    public function pinActivity($from, $slug, $take, $seenIds, $randId)
    {
        $ids = $this->pinActivityIds($from, $slug, $randId);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function pinHottest($from, $slug, $take, $seenIds, $randId)
    {
        $this->SortAdd(
            self::$pinHottestVisitKey,
            $this->flow_pin_cache_key($from, self::$order[2], $slug, $randId)
        );

        $ids = $this->pinHottestIds($from, $slug, $randId);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function pinActivityIds($from, $slug, $randId, $refresh = false)
    {
        return $this->RedisSort($this->flow_pin_cache_key($from, self::$order[1], $slug, $randId), function () use ($from, $slug, $randId)
        {
            return Pin
                ::whereNotNull('published_at')
                ->where('trial_type', 0)
                ->where('can_up', 1)
                ->when($randId, function ($query) use ($randId)
                {
                    return $query->where(DB::raw('id % 10'), $randId);
                })
                ->when($from === 'bangumi', function ($query) use ($slug)
                {
                    return $query->where('bangumi_slug', $slug);
                })
                ->when($from === 'user', function ($query) use ($slug)
                {
                    return $query->where('user_slug', $slug);
                })
                ->orderBy('updated_at', 'DESC')
                ->pluck('updated_at', 'slug');

        }, ['refresh' => $refresh, 'is_time' => true]);
    }

    public function pinHottestIds($from, $slug, $randId, $refresh = false)
    {
        return $this->RedisSort($this->flow_pin_cache_key($from, self::$order[2], $slug, $randId), function () use ($from, $slug, $randId)
        {
            $pins = Pin
                ::whereNotNull('published_at')
                ->where('trial_type', 0)
                ->where('can_up', 1)
                ->when($randId, function ($query) use ($randId)
                {
                    return $query->where(DB::raw('id % 10'), $randId);
                })
                ->when($from === 'bangumi', function ($query) use ($slug)
                {
                    return $query->where('bangumi_slug', $slug);
                })
                ->when($from === 'user', function ($query) use ($slug)
                {
                    return $query->where('user_slug', $slug);
                })
                ->select('slug', 'visit_count', 'comment_count', 'like_count', 'mark_count', 'reward_count', 'published_at')
                ->take(500)
                ->get()
                ->toArray();

            // https://segmentfault.com/a/1190000004253816
            $result = [];
            $i = 0.1;

            foreach ($pins as $pin)
            {
                $result[$pin['slug']] = (
                        log(($pin['visit_count'] + 1), 10) * 4 +
                        log(($pin['comment_count'] * 4 + 1), M_E) +
                        log(($pin['like_count'] * 2 + $pin['mark_count'] * 3 + $pin['reward_count'] * 10 + 1), 10)
                    ) / pow(((time() - strtotime($pin['published_at'])) + 1), $i);
            }

            return $result;
        }, ['refresh' => $refresh]);
    }

    public function pinTrial($from, $slug, $take)
    {
        $result = Pin
            ::where('trial_type', '<>', 0)
            ->orderBy('trial_type', 'DESC')
            ->orderBy('id', 'ASC')
            ->when($from === 'bangumi', function ($query) use ($slug)
            {
                return $query->where('bangumi_slug', $slug);
            })
            ->when($from === 'user', function ($query) use ($slug)
            {
                return $query->where('user_slug', $slug);
            })
            ->take($take)
            ->pluck('slug')
            ->toArray();

        $total = Pin
            ::where('trial_type', '<>', 0)
            ->when($from === 'bangumi', function ($query) use ($slug)
            {
                return $query->where('bangumi_slug', $slug);
            })
            ->when($from === 'user', function ($query) use ($slug)
            {
                return $query->where('user_slug', $slug);
            })
            ->count();

        return [
            'result' => $result,
            'total' => $total,
            'no_more' => true
        ];
    }

    public function createPin($pinSlug, $bangumiSlug, $userSlug, $type = null, $time = 0)
    {
        $slugs = [];
        if (is_array($type))
        {
            if (isset($type['index']) && $type['index'])
            {
                $slugs[] = self::$indexSlug;
            }
            if (isset($type['bangumi']) && $type['bangumi'])
            {
                $slugs[] = $bangumiSlug;
            }
            if (isset($type['user']) && $type['user'])
            {
                $slugs[] = $userSlug;
            }
        }
        else
        {
            $slugs = [self::$indexSlug, $bangumiSlug, $userSlug];
        }
        foreach ($slugs as $i => $slug)
        {
            $this->SortAdd($this->flow_pin_cache_key(self::$from[$i], self::$order[0], $slug), $pinSlug, $time);
            $this->SortAdd($this->flow_pin_cache_key(self::$from[$i], self::$order[1], $slug), $pinSlug);
        }
    }

    public function updatePin($pinSlug, $bangumiSlug, $userSlug, $type = null)
    {
        $slugs = [];
        if (is_array($type))
        {
            if (isset($type['index']) && $type['index'])
            {
                $slugs[] = self::$indexSlug;
            }
            if (isset($type['bangumi']) && $type['bangumi'])
            {
                $slugs[] = $bangumiSlug;
            }
            if (isset($type['user']) && $type['user'])
            {
                $slugs[] = $userSlug;
            }
        }
        else
        {
            $slugs = [self::$indexSlug, $bangumiSlug, $userSlug];
        }
        foreach ($slugs as $i => $slug)
        {
            $this->SortAdd($this->flow_pin_cache_key(self::$from[$i], self::$order[1], $slug), $pinSlug);
        }
    }

    public function deletePin($pinSlug, $bangumiSlug, $userSlug, $type = null)
    {
        $slugs = [];
        if (is_array($type))
        {
            if (isset($type['index']) && $type['index'])
            {
                $slugs[] = self::$indexSlug;
            }
            if (isset($type['bangumi']) && $type['bangumi'])
            {
                $slugs[] = $bangumiSlug;
            }
            if (isset($type['user']) && $type['user'])
            {
                $slugs[] = $userSlug;
            }
        }
        else
        {
            $slugs = [self::$indexSlug, $bangumiSlug, $userSlug];
        }
        $randId = substr((string)slug2id($pinSlug), -1);
        foreach (self::$order as $order)
        {
            foreach ($slugs as $i => $slug)
            {
                $id = $order === 'newest' ? 0 : $randId;
                $this->SortRemove(
                    $this->flow_pin_cache_key(
                        self::$from[$i],
                        $order,
                        $slug,
                        $id
                    ),
                    $pinSlug
                );
            }
        }
    }

    public function idolNewest($lastId, $take, $refresh = false)
    {
        $list = $this->RedisSort($this->flow_idol_cache_key(self::$order[0], self::$indexSlug), function ()
        {
            return Idol
                ::where('is_newbie', 1)
                ->orderBy('market_price', 'DESC')
                ->orderBy('stock_price', 'DESC')
                ->pluck('market_price', 'slug')
                ->toArray();

        }, ['force' => $refresh]);

        return $this->filterIdsByMaxId($list, $lastId, $take);
    }

    public function idolActivity($from, $slug, $take, $seenIds)
    {
        $ids = $this->RedisSort($this->flow_idol_cache_key(self::$order[1], $slug), function () use ($from, $slug)
        {
            if ($from === 'user')
            {
                return IdolFans
                    ::where('user_slug', $slug)
                    ->orderBy('updated_at', 'DESC')
                    ->pluck('updated_at', 'idol_slug')
                    ->toArray();
            }

            return Idol
                ::when($from === 'bangumi', function ($query) use ($slug)
                {
                    return $query->where('bangumi_slug', $slug);
                })
                ->orderBy('updated_at', 'DESC')
                ->pluck('updated_at', 'slug')
                ->toArray();

        }, ['is_time' => true]);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function idolHottest($from, $slug, $take, $seenIds)
    {
        $ids = $this->RedisSort($this->flow_idol_cache_key(self::$order[2], $slug), function () use ($from, $slug)
        {
            if ($from === 'user')
            {
                return IdolFans
                    ::where('user_slug', $slug)
                    ->orderBy('coin_count', 'DESC')
                    ->pluck('coin_count', 'idol_slug')
                    ->toArray();
            }

            return Idol
                ::when($from === 'bangumi', function ($query) use ($slug)
                {
                    return $query->where('bangumi_slug', $slug);
                })
                ->orderBy('market_price', 'DESC')
                ->orderBy('stock_price', 'DESC')
                ->pluck('market_price', 'slug')
                ->toArray();
        });

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function liveNewest($from, $id, $take, $lastId)
    {
        $ids = $this->RedisSort($this->flow_live_cache_key($from, self::$order[0], $id), function () use ($from, $id)
        {
            return LiveRoom
                ::where('visit_state', 1)
                ->when($from === 'user', function ($query) use ($id)
                {
                    return $query->where('author_id', $id);
                })
                ->orderBy('id', 'DESC')
                ->pluck('id', 'id');
        });

        return $this->filterIdsByMaxId($ids, $lastId, $take);
    }

    public function liveActivity($from, $id, $take, $seenIds)
    {
        $ids = $this->RedisSort($this->flow_live_cache_key($from, self::$order[1], $id), function () use ($from, $id)
        {
            return LiveRoom
                ::where('visit_state', 1)
                ->when($from === 'user', function ($query) use ($id)
                {
                    return $query->where('author_id', $id);
                })
                ->orderBy('updated_at', 'DESC')
                ->pluck('updated_at', 'id');

        }, ['is_time' => true]);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    private function flow_idol_cache_key(string $order, string $slug)
    {
        return "flow-idol:{$order}-{$slug}}";
    }

    private function flow_live_cache_key(string $from, string $order, string $slug, $randId = 0)
    {
        return "flow-live-{$order}:{$from}-{$slug}-{$randId}";
    }

    private function flow_pin_cache_key(string $from, string $order, string $slug, $randId = 0)
    {
        return "flow-pin-{$order}:{$from}-{$slug}-{$randId}";
    }
}
