<?php


namespace App\Http\Repositories;


use App\Http\Transformers\LiveRoom\LiveRoomItemResource;
use App\Http\Transformers\Question\QuestionItemResource;
use App\Models\BangumiQuestion;
use App\Models\IdolVoice;
use App\Models\LiveRoom;

class LiveRoomRepository extends Repository
{
    public function item($id, $refresh = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->RedisItem("live-room-{$id}", function () use ($id)
        {
            $res = LiveRoom
                ::where('id', $id)
                ->first();

            if (is_null($res))
            {
                return 'nil';
            }

            $content = json_decode($res->content);

            $res->content = $content->content;
            $res->readers = $content->readers;

            return new LiveRoomItemResource($res);
        }, $refresh);

        if ($result === 'nil')
        {
            return null;
        }

        return $result;
    }

    public function activityIds($take, $seenIds, $refresh = false)
    {
        $ids = $this->RedisSort('live-room-activity-ids', function ()
        {
            return LiveRoom
                ::where('visit_state', 1)
                ->orderBy('updated_at', 'DESC')
                ->pluck('updated_at', 'id');

        }, ['force' => $refresh, 'is_time' => true]);

        return $this->filterIdsBySeenIds($ids, $seenIds, $take);
    }

    public function allVoice($type, $slug, $refresh = false)
    {
        $result = $this->RedisArray("live-room-voice-all:{$type}:{$slug}", function () use ($type, $slug)
        {
            $list = IdolVoice
                ::where('from_type', $type)
                ->when($slug, function ($query) use ($slug)
                {
                    return $query->where('from_slug', $slug);
                })
                ->get()
                ->toArray();

            $result = [];
            foreach ($list as $item)
            {
                $result[] = json_encode([
                    'id' => $item['id'],
                    'src' => $item['src'],
                    'meta' => $item['meta'],
                    'text' => $item['text'],
                    'from_type' => $item['from_type'],
                    'from_slug' => $item['from_slug']
                ]);
            }

            return $result;
        }, $refresh);

        $repository = $type == '0' ? new IdolRepository() : new UserRepository();

        return array_map(function ($item) use ($repository)
        {
            $res = json_decode($item);

            $user = $repository->item($res->from_slug);

            $res->reader = [
                'id' => $user->id,
                'slug' => $user->slug,
                'name' => $user->name ?? $user->nickname,
                'avatar' => $user->avatar
            ];

            $meta = json_decode($res->meta);
            $res->duration = $meta->duration;
            $res->meta = $meta;
            $res->alias = isset($user->alias) ? implode(',', $user->alias) : '';

            return $res;
        }, $result);
    }
}
