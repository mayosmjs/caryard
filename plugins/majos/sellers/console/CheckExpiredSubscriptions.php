<?php namespace Majos\Sellers\Console;

use Illuminate\Console\Command;
use Majos\Sellers\Classes\SubscriptionService;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Check Expired Subscriptions Command
 * 
 * Usage: php artisan sellers:check-expired-subscriptions
 */
class CheckExpiredSubscriptions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'sellers:check-expired-subscriptions';

    /**
     * @var string The console command description.
     */
    protected $description = 'Check and expire subscriptions that have reached their expiration date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');
        
        $service = new SubscriptionService();
        $count = $service->checkExpiredSubscriptions();
        
        if ($count > 0) {
            $this->info("Successfully expired {$count} subscription(s).");
            $this->info("Vehicles for these sellers are no longer visible to the public.");
        } else {
            $this->info('No expired subscriptions found.');
        }
        
        return 0;
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions()
    {
        return [];
    }
}