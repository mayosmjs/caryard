<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMajosCaryardColors extends Migration
{
    public function up()
    {
        Schema::rename('majos_caryard_', 'majos_caryard_colors');
        Schema::table('majos_caryard_colors', function($table)
        {
            $table->string('id', 36)->change();
        });
    }
    
    public function down()
    {
        Schema::rename('majos_caryard_colors', 'majos_caryard_');
        Schema::table('majos_caryard_', function($table)
        {
            $table->string('id', 36)->change();
        });
    }
}
