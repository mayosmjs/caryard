<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMajosCaryardVehiclesAddOptions extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_vehicles', function($table)
        {
            $table->text('options')->nullable();
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_vehicles', function($table)
        {
            $table->dropColumn('options');
        });
    }
}
