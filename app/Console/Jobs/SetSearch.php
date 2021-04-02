<?php

namespace App\Console\Jobs;

use App\Models\Bangumi;
use App\Models\Character;
use App\Models\Search;
use App\Models\User;
use App\Modules\Spider\Query;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class SetSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SetSearch';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'set search';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $users = User
            ::where('migration_state', 0)
            ->take(50)
            ->get();

        foreach ($users as $user)
        {
            Search::createSearch([
                'uuid' => $user->id,
                'text' => $user->nickname,
                'type' => 1
            ]);

            $user->update([
                'migration_state' => 1
            ]);
        }

        $bangumis = Bangumi
            ::where('migration_state', 0)
            ->take(50)
            ->get();

        foreach ($bangumis as $bangumi)
        {
            Search::createSearch([
                'uuid' => $bangumi->id,
                'text' => $bangumi->alias,
                'type' => 2
            ]);

            $bangumi->update([
                'migration_state' => 1
            ]);
        }

        $characters = Character
            ::where('migration_state', 0)
            ->take(50)
            ->get();

        foreach ($characters as $char)
        {
            Search::createSearch([
                'uuid' => $char->id,
                'text' => $char->alias,
                'type' => 3
            ]);

            $char->update([
                'migration_state' => 1
            ]);
        }
    }
}
