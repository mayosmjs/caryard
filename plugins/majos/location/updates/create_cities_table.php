<?php namespace Majos\Location\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateCitiesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_location_cities', function($table)
        {
            $table->uuid('id')->primary();
            $table->uuid('province_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_location_cities');
    }
}
