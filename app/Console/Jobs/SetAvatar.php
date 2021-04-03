<?php

namespace App\Console\Jobs;

use App\Models\Bangumi;
use App\Models\Character;
use App\Models\Search;
use App\Models\User;
use App\Modules\AliyunOSS;
use App\Modules\Spider\Query;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class SetAvatar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SetAvatar';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'set avatar';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $aliyunOSS = new AliyunOSS();
        $bangumis = Bangumi
            ::where('migration_state', '<>', 3)
            ->take(50)
            ->get();

        foreach ($bangumis as $bangumi)
        {
            $bangumi->update([
                'migration_state' => 3,
                'avatar' => $aliyunOSS->fetch($bangumi->avatar)
            ]);
        }

        $characters = Character
            ::where('migration_state', '<>', 3)
            ->take(50)
            ->get();

        foreach ($characters as $char)
        {
            $char->update([
                'migration_state' => 3,
                'avatar' => $aliyunOSS->fetch($char->avatar)
            ]);
        }
    }
}
