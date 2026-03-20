<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardVehicles extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_vehicles', function($table)
        {
            $table->uuid('id')->primary();
            $table->integer('tenant_id');
            $table->integer('brand_id');
            $table->integer('model_id');
            $table->integer('location_id');
            $table->string('vin_id');
            $table->string('vehicleid');
            $table->string('title');
            $table->string('slug');
            $table->date('year');
            $table->decimal('price', 10, 2);
            $table->double('mileage', 10, 2);
            $table->integer('condition_id');
            $table->integer('fuel_type_id');
            $table->integer('transmission_id');
            $table->integer('body_type_id');
            $table->integer('color_id');
            $table->integer('engine_capacity_d');
            $table->integer('drive_type_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_vehicles');
    }
}
