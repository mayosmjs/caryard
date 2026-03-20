<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardEngineCapacities extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_engine_capacities', function($table)
        {
            $table->uuid('id')->primary();
            $table->integer('size')->index();
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_engine_capacities');
    }
}
