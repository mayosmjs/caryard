<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableDeleteUsersSellerKyc extends Migration
{
    public function up()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function($table)
            {
                $columnsToDrop = [
                    'tenant_id',
                    'is_seller',
                    'company_name',
                    'phone_number',
                    'address',
                    'city',
                    'country',
                    'identification_type',
                    'identification_number',
                    'tax_id',
                    'is_verified_seller'
                ];

                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function($table)
            {
                $table->string('tenant_id', 36)->nullable();
                $table->boolean('is_seller')->default(0);
                $table->string('company_name')->nullable();
                $table->string('phone_number')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('identification_type')->nullable();
                $table->string('identification_number')->nullable();
                $table->string('tax_id')->nullable();
                $table->boolean('is_verified_seller')->default(0);
            });
        }
    }
}
