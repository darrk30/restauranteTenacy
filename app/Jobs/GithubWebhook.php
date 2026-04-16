<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GithubWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        putenv('HOME=/home/tukipu'); 
        putenv('PATH=/usr/local/bin:/usr/bin:/bin');
        shell_exec('dploy deploy master');
    }
}
