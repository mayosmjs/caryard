<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardModels extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_models', function($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->nullable()->index();
            $table->integer('brand_id');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_models');
    }
}
