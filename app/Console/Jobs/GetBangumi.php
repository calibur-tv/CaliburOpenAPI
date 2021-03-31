<?php

namespace App\Console\Jobs;

use App\Models\Bangumi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class GetBangumi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GetBangumi';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get bangumi';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lastId = Redis::GET('cron_bgm_id') ?: 1;
        $client = new Client();
        try
        {
            $resp = $client->get('http://api.bgm.tv/subject/' . $lastId);
            $body = json_decode($resp->getBody(), true);
            if ($body['type'] != 2)
            {
                $this->incrLastId($lastId);
                return true;
            }

            $id = $body['id'];
            if (Bangumi::where('bgm_id', $id)->count())
            {
                $this->incrLastId($lastId);
                return true;
            }

            $name = $body['name_cn'] ? $body['name_cn'] : $body['name'];
            $alias = array_filter(array_unique([$body['name_cn'], $body['name']]), function ($name)
            {
                return !!$name;
            });
            $avatar = $body['images']['large'];
            $intro = trim($body['summary']);
            $publish_at = $this->formatPublish($body['air_date']);

            $bangumi = Bangumi::create([
                'title' => $name,
                'alias' => implode('|', $alias),
                'intro' => $intro,
                'avatar' => $avatar,
                'bgm_id' => $id,
                'published_at' => $publish_at
            ]);

            $bangumi->update([
                'slug' => id2slug($bangumi->id)
            ]);

            $this->incrLastId($lastId);
        }
        catch (\Exception $e)
        {
            Redis::RPUSH('cron_bgm_failed_id', $lastId);
            $this->incrLastId($lastId);
        }

        return true;
    }

    private function incrLastId($lastId)
    {
        Redis::SET('cron_bgm_id', intval($lastId) + 1);
    }

    private function formatPublish($publish)
    {
        if (!$publish)
        {
            return null;
        }
        try
        {
            if ($publish[0] === '0')
            {
                $publish = '20' . $publish;
            }
            if ($publish[0] === '1' && ($publish[3] === '-' || $publish[3] === '年'))
            {
                $publish = '19' . $publish;
            }
            $publish = str_replace('年', '-', $publish);
            $publish = str_replace('月', '-', $publish);
            $publish = str_replace('日', '', $publish);

            $ts = strtotime($publish);

            return Carbon::createFromTimestamp($ts);
        }
        catch (\Exception $e)
        {
            return null;
        }
    }
}
