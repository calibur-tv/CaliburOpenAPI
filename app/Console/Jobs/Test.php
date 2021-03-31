<?php

namespace App\Console\Jobs;

use Illuminate\Support\Facades\Redis;
use Illuminate\Console\Command;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Test';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test job';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $counter = Redis::GET('cron_counter');
        if ($counter)
        {
            Redis::SET('cron_counter', intval($counter) + 1);
        }
        else
        {
            Redis::SET('cron_counter', 1);
        }
        return true;
    }
}
