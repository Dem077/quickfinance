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
        // Set the log file path to Laravel's default log file
        $logFile = storage_path('logs/mail.log');

        // Run the nohup command to start the queue listener in the background
        $command = "nohup php artisan queue:work > {$logFile} 2>&1 &";
        exec($command);

        $this->info('Queue worker started in the background. Logs are being written to '.$logFile);

        return Command::SUCCESS;
    }
}
