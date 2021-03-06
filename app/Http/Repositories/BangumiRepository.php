<?php


namespace App\Http\Repositories;


use App\Http\Transformers\Bangumi\BangumiItemResource;
use App\Models\Bangumi;

class BangumiRepository extends Repository
{
    public function item($slug, $refresh = false)
    {
        if (!$slug)
        {
            return null;
        }

        $id = slug2id($slug);

        $result = $this->RedisItem("bangumi:{$id}", function () use ($id)
        {
            $bangumi = Bangumi
                ::where('id', $id)
                ->first();

            if (is_null($bangumi))
            {
                return 'nil';
            }

            return new BangumiItemResource($bangumi);
        }, $refresh);

        if ($result === 'nil')
        {
            return null;
        }

        return $result;
    }
}
