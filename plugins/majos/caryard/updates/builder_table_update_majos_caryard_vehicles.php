<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMajosCaryardVehicles extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_vehicles', function($table)
        {
            $table->boolean('is_active')->default(1);
            $table->integer('seller_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_vehicles', function($table)
        {
            $table->dropColumn('is_active');
            $table->dropColumn('seller_id');
        });
    }
}
