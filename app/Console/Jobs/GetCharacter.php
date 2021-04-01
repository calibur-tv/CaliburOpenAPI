<?php

namespace App\Console\Jobs;

use App\Models\Bangumi;
use App\Models\Character;
use App\Modules\Spider\Query;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Command;

class GetCharacter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GetCharacter';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get character';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $failedListKey = 'cron_character_failed_page2';
        $lastIdKey = 'bgm_character_last_page2';
        $lastId = Redis::GET($lastIdKey) ?: 1;
        $query = new Query();
        try
        {
            $bangumi = Bangumi::where('id', $lastId)->first();
            if (!$bangumi)
            {
                return true;
            }

            $list = $query->getBangumiCharacters($bangumi->bgm_id);

            if (empty($list))
            {
                Redis::RPUSH($failedListKey, $lastId);
                Redis::SET($lastIdKey, intval($lastId) + 1);
                return true;
            }

            foreach ($list as $character)
            {
                $item = Character::where('bgm_id', $character['bgm_id'])->first();
                if ($item)
                {
                    continue;
                }

                Character::createCharacter($character);
            }

            Redis::SET($lastIdKey, intval($lastId) + 1);
        }
        catch (\Exception $e)
        {
            Redis::RPUSH($failedListKey, $lastId);
            Redis::SET($lastIdKey, intval($lastId) + 1);
        }

        return true;
    }
}
