<?php namespace Majos\Location\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddSlugsToLocationTables extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('majos_location_countries', 'slug')) {
            Schema::table('majos_location_countries', function($table) {
                $table->string('slug')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('majos_location_provinces', 'slug')) {
            Schema::table('majos_location_provinces', function($table) {
                $table->string('slug')->nullable()->index();
            });
        }
        if (!Schema::hasColumn('majos_location_cities', 'slug')) {
            Schema::table('majos_location_cities', function($table) {
                $table->string('slug')->nullable()->index();
            });
        }
    }

    public function down()
    {
        Schema::table('majos_location_countries', function($table) {
            $table->dropColumn('slug');
        });
        Schema::table('majos_location_provinces', function($table) {
            $table->dropColumn('slug');
        });
        Schema::table('majos_location_cities', function($table) {
            $table->dropColumn('slug');
        });
    }
}
