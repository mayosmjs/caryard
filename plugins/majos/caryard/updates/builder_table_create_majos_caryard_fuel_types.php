<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardFuelTypes extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_fuel_types', function($table)
        {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_fuel_types');
    }
}
