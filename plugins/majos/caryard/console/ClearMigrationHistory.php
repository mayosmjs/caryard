<?php namespace Majos\Caryard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clears the CarYard plugin migration history
 * 
 * Usage: php artisan caryard:clear-history
 */
class ClearMigrationHistory extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'caryard:clear-history';

    /**
     * @var string The console command description.
     */
    protected $description = 'Clear all migration history for the CarYard plugin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pluginCode = 'Majos.Caryard';
        
        $this->info('Clearing migration history for: ' . $pluginCode);
        
        // Clear system_plugin_history
        $historyDeleted = DB::table('system_plugin_history')
            ->where('code', $pluginCode)
            ->delete();
        
        $this->info("Deleted {$historyDeleted} records from system_plugin_history");
        
        // Also reset system_plugin_versions to allow fresh migrations
        $versionUpdated = DB::table('system_plugin_versions')
            ->where('code', $pluginCode)
            ->update(['version' => '0.0.1']);
        
        if ($versionUpdated) {
            $this->info('Reset plugin version to 0.0.1');
        }
        
        $this->info('Done! Migration history has been cleared.');
        $this->info('Run "php artisan plugin:refresh majos.caryard" to re-run all migrations.');
    }
}
