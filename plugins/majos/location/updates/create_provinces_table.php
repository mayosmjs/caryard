<?php namespace Majos\Location\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateProvincesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_location_provinces', function($table)
        {
            $table->uuid('id')->primary();
            $table->uuid('country_id');
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_location_provinces');
    }
}
