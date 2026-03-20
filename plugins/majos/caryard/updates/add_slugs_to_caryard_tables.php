<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddSlugsToCaryardTables extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('majos_caryard_brands', 'slug')) {
            Schema::table('majos_caryard_brands', function($table) {
                $table->string('slug')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('majos_caryard_models', 'slug')) {
            Schema::table('majos_caryard_models', function($table) {
                $table->string('slug')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('majos_caryard_tenants', 'slug')) {
            Schema::table('majos_caryard_tenants', function($table) {
                $table->string('slug')->nullable()->index();
            });
        }
    }

    public function down()
    {
        Schema::table('majos_caryard_brands', function($table) {
            $table->dropColumn('slug');
        });
        Schema::table('majos_caryard_models', function($table) {
            $table->dropColumn('slug');
        });
        Schema::table('majos_caryard_tenants', function($table) {
            $table->dropColumn('slug');
        });
    }
}
