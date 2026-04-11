<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\DB;

class ConvertTenantIdToInteger extends Migration
{
    public function up()
    {
       return;
          
        // Skip if SQLite (for testing)
        if (config('database.default') === 'sqlite') {
            return;
        }

        // Get tenant table column type
        $tenantColumnType = Schema::getColumnType('majos_caryard_tenants', 'id');
        
        // Check if tenant ID is already integer
        if (in_array($tenantColumnType, ['integer', 'int', 'smallint', 'bigint'])) {
            // Tenant ID is already integer, check if vehicles table has integer tenant_id
            if (Schema::hasColumn('majos_caryard_vehicles', 'tenant_id')) {
                $vehicleColumnType = Schema::getColumnType('majos_caryard_vehicles', 'tenant_id');
                if (in_array($vehicleColumnType, ['string', 'char', 'varchar'])) {
                    // Convert vehicles tenant_id to integer
                    DB::statement('ALTER TABLE majos_caryard_vehicles MODIFY COLUMN tenant_id INT UNSIGNED NULL');
                }
            }
            // Check administrative divisions table
            if (Schema::hasColumn('majos_caryard_administrative_divisions', 'tenant_id')) {
                $divColumnType = Schema::getColumnType('majos_caryard_administrative_divisions', 'tenant_id');
                if (in_array($divColumnType, ['string', 'char', 'varchar'])) {
                    DB::statement('ALTER TABLE majos_caryard_administrative_divisions MODIFY COLUMN tenant_id INT UNSIGNED NULL');
                }
            }
            return;
        }

        // Get all existing tenants
        $tenants = DB::table('majos_caryard_tenants')->get();
        
        // Check if there are any tenants
        if ($tenants->isEmpty()) {
            // No tenants, just modify the column to be auto-increment
            Schema::table('majos_caryard_tenants', function (Blueprint $table) {
                $table->increments('id')->change();
            });
            return;
        }
        
        // Drop foreign keys first
        try {
            if (Schema::hasColumn('majos_caryard_vehicles', 'tenant_id')) {
                $vehicleColumnType = Schema::getColumnType('majos_caryard_vehicles', 'tenant_id');
                if (in_array($vehicleColumnType, ['string', 'char', 'varchar'])) {
                    Schema::table('majos_caryard_vehicles', function (Blueprint $table) {
                        // Drop any existing foreign key constraint
                        try { $table->dropForeign(['tenant_id']); } catch (\Exception $e) {}
                        $table->string('tenant_id', 255)->nullable()->change();
                    });
                }
            }
        } catch (\Exception $e) {
            // Foreign key might not exist or other error
        }
        
        try {
            if (Schema::hasColumn('majos_caryard_administrative_divisions', 'tenant_id')) {
                Schema::table('majos_caryard_administrative_divisions', function (Blueprint $table) {
                    try { $table->dropForeign(['tenant_id']); } catch (\Exception $e) {}
                    $table->string('tenant_id', 255)->nullable()->change();
                });
            }
        } catch (\Exception $e) {
            // Foreign key might not exist or other error
        }
        
        // Modify the tenant table column to be auto-incrementing integer
        Schema::table('majos_caryard_tenants', function (Blueprint $table) {
            $table->increments('id')->change();
        });
        
        // Now convert vehicles tenant_id to integer
        if (Schema::hasColumn('majos_caryard_vehicles', 'tenant_id')) {
            DB::statement('ALTER TABLE majos_caryard_vehicles MODIFY COLUMN tenant_id INT UNSIGNED NULL');
        }
        
        // Convert administrative_divisions tenant_id to integer
        if (Schema::hasColumn('majos_caryard_administrative_divisions', 'tenant_id')) {
            DB::statement('ALTER TABLE majos_caryard_administrative_divisions MODIFY COLUMN tenant_id INT UNSIGNED NULL');
        }
    }

    public function down()
    {
        // This migration is not reversible due to data type change
    }
}
