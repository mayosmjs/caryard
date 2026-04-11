<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMajosCaryardVehiclesUuids extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_vehicles', function($table)
        {
            // $table->char('tenant_id', 36)->nullable()->change();
            // $table->char('brand_id', 36)->nullable()->change();
            // $table->char('model_id', 36)->nullable()->change();
            // $table->char('condition_id', 36)->nullable()->change();
            // $table->char('fuel_type_id', 36)->nullable()->change();
            // $table->char('transmission_id', 36)->nullable()->change();
            // $table->char('body_type_id', 36)->nullable()->change();
            // $table->char('color_id', 36)->nullable()->change();
            // $table->char('engine_capacity_d', 36)->nullable()->change();
            // $table->char('drive_type_id', 36)->nullable()->change();
            // $table->char('location_id', 36)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_vehicles', function($table)
        {
            // $table->char('tenant_id')->nullable()->change();
            // $table->char('brand_id')->nullable()->change();
            // $table->char('model_id')->nullable()->change();
            // $table->char('condition_id')->nullable()->change();
            // $table->char('fuel_type_id')->nullable()->change();
            // $table->char('transmission_id')->nullable()->change();
            // $table->char('body_type_id')->nullable()->change();
            // $table->char('color_id')->nullable()->change();
            // $table->char('engine_capacity_d')->nullable()->change();
            // $table->char('drive_type_id')->nullable()->change();
            // $table->char('location_id')->nullable()->change();
        });
    }
}
