<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardFavorites extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_favorites', function($table)
        {
            $table->integer('vehicle_id');
            $table->integer('user_id');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_favorites');
    }
}
