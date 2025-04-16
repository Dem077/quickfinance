<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MailStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailstart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the queue listener in the background using nohup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Run the nohup command to start the queue listener in the background
        $command = 'nohup php artisan queue:listen &';
        exec($command);

        $this->info('Queue listener started in the background.');

        return Command::SUCCESS;
    }
}
