<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class AddDivisionIdToVehicles extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_vehicles', function (Blueprint $table) {
            $table->unsignedInteger('division_id')->nullable()->after('location_id');

            $table->foreign('division_id', 'caryard_vehicles_division_fk')
                  ->references('id')
                  ->on('majos_caryard_admin_divisions')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('majos_caryard_vehicles', function (Blueprint $table) {
            $table->dropForeign('caryard_vehicles_division_fk');
            $table->dropColumn('division_id');
        });
        Schema::enableForeignKeyConstraints();
    }
}