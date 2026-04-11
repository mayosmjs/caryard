<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMajosCaryardColors extends Migration
{
    public function up()
    {
        // Check if base table exists (from v1.0.14), rename it
        if (Schema::hasTable('majos_caryard_')) {
            Schema::rename('majos_caryard_', 'majos_caryard_colors');
        } else {
            // Direct create if base table doesn't exist
            Schema::create('majos_caryard_colors', function($table)
            {
                $table->increments('id');
                $table->string('name');
                $table->string('slug')->nullable()->index();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
            });
        }
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_colors');
    }
}
