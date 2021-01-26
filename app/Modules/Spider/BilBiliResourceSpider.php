<?php


namespace App\Modules\Spider;


use App\Http\Modules\Spider\Base\GetResourceService;
use Carbon\Carbon;

class BilBiliResourceSpider extends GetResourceService
{
    /**
     * model_type
     * 1. 视频
     */
    public function __construct()
    {
        parent::__construct(1);
    }

    public function getDataItem($id, $type, $source)
    {
        $data = $this->getClient("https://api.bilibili.com/x/web-interface/view/detail?aid={$id}");
        $row = $data['data']['View'];
        if ($source === null)
        {
            return [
                'avid' => $row['aid'],
                'bvid' => $row['bvid'],
                'username' => $row['owner']['name'],
                'poster' => $this->getImage($row['pic']),
                'title' => $row['title'],
                'visit_count' => $row['stat']['view'],
                'comment_count' => $row['stat']['reply'],
                'danmu_count' => $row['stat']['danmaku'],
                'published_at' => $row['pubdate'],
                'duration' => $row['duration']
            ];
        }

        return array_merge($source, [
            'avid' => $row['aid'],
            'bvid' => $row['bvid'],
            'visit_count' => $row['stat']['view'],
            'comment_count' => $row['stat']['reply'],
            'danmu_count' => $row['stat']['danmaku']
        ]);
    }

    public function getUserList($id, $rule)
    {
        if (!$rule)
        {
            return $this->getDefaultList($id);
        }

        $result = [];
        foreach ($rule as $row)
        {
            if ($row['type'] === 'video')
            {
                foreach ($row['channel_id'] as $cid)
                {
                    $data = $this->getChannelVideo($id, $cid, $row['source_type']);

                    $result = array_merge($result, $data);
                }
            }
        }

        return $result;
    }

    protected function getDefaultList($id)
    {
        $data = $this->getClient("https://api.bilibili.com/x/space/arc/search?mid={$id}&pn=1&ps=100");
        $list = $data['data']['list']['vlist'];

        $result = [];
        $now = Carbon::now();
        foreach ($list as $row)
        {
            if ($row['mid'] != $id)
            {
                continue;
            }

            $result[] = [
                'source_type' => 0,
                'site_type' => 1,
                'model_type' => 1,
                'model_id' => $row['aid'],
                'author_id' => $row['mid'],
                'data' => json_encode([
                    'avid' => $row['aid'],
                    'bvid' => $row['bvid'],
                    'username' => $row['author'],
                    'poster' => $this->getImage($row['pic']),
                    'title' => $row['title'],
                    'visit_count' => $row['play'],
                    'comment_count' => $row['comment'],
                    'danmu_count' => 0,
                    'published_at' => $row['created'],
                    'duration' => $this->formatDurationStr($row['length'])
                ]),
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        return $result;
    }

    protected function getChannelVideo($uid, $cid, $sourceType)
    {
        $data = $this->getClient("https://api.bilibili.com/x/space/channel/video?mid={$uid}&cid={$cid}&pn=1&ps=100&order=0");

        $list = $data['data']['list']['archives'];

        $result = [];
        $now = Carbon::now();
        foreach ($list as $row)
        {
            $result[] = [
                'source_type' => $sourceType,
                'site_type' => 1,
                'model_type' => 1,
                'model_id' => $row['aid'],
                'author_id' => $uid,
                'data' => json_encode([
                    'avid' => $row['aid'],
                    'bvid' => '',
                    'username' => $row['owner']['name'],
                    'poster' => $this->getImage($row['pic']),
                    'title' => $row['title'],
                    'visit_count' => $row['stat']['view'],
                    'comment_count' => $row['stat']['reply'],
                    'danmu_count' => $row['stat']['danmaku'],
                    'published_at' => $row['pubdate'],
                    'duration' => $row['duration']
                ]),
                'created_at' => $now,
                'updated_at' => $now
            ];
        }

        return $result;
    }

    protected function formatDurationStr($str)
    {
        $arr = explode(':', $str);
        if (count($arr) === 1)
        {
            return intval($arr[0]);
        }

        if (count($arr) === 2)
        {
            return 60 * intval($arr[0]) + intval($arr[1]);
        }

        if (count($arr) === 3)
        {
            return 3600 * intval($arr[0]) + 60 * intval($arr[1]) + intval($arr[2]);
        }

        return 0;
    }
}
