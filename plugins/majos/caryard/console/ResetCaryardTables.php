<?php namespace Majos\Caryard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;

/**
 * Resets all CarYard plugin tables
 * 
 * Usage: php artisan caryard:reset-tables
 */
class ResetCaryardTables extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'caryard:reset-tables';

    /**
     * @var string The console command description.
     */
    protected $description = 'Drop all tables that start with majos_caryard_';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $prefix = 'majos_caryard_';
        $database = config('database.connections.mysql.database');
        
        // Get all tables in the database
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $key = 'Tables_in_' . $dbName;
        
        $tablesToDrop = [];
        
        foreach ($tables as $table) {
            $tableName = $table->$key;
            if (strpos($tableName, $prefix) === 0) {
                $tablesToDrop[] = $tableName;
            }
        }
        
        if (empty($tablesToDrop)) {
            $this->info('No tables found with prefix: ' . $prefix);
            return;
        }
        
        $this->info('Found ' . count($tablesToDrop) . ' tables to drop:');
        foreach ($tablesToDrop as $table) {
            $this->line('  - ' . $table);
        }
        
        if (!$this->confirm('Do you wish to continue? This action cannot be undone.', true)) {
            $this->info('Operation cancelled.');
            return;
        }
        
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $this->info('Dropping tables...');
        
        foreach ($tablesToDrop as $table) {
            try {
                Schema::dropIfExists($table);
                $this->line('  Dropped: ' . $table);
            } catch (\Exception $e) {
                $this->error('  Failed to drop: ' . $table . ' - ' . $e->getMessage());
            }
        }
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // Also reset the plugin version
        DB::table('system_plugin_versions')
            ->where('code', 'Majos.Caryard')
            ->update(['version' => '0.0.1']);
        
        // Also clean up system_files that reference our tables
        $this->info('Cleaning up system_files...');
        DB::statement("DELETE FROM system_files WHERE attachment_type LIKE '%Caryard%'");
        
        $this->info('Done! All CarYard tables have been dropped.');
        $this->info('Plugin version reset to 0.0.1');
        $this->info('Run "php artisan october:migrate" to recreate tables.');
    }
}
