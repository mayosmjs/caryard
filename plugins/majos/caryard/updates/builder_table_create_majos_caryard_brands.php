<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardBrands extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_brands', function($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->boolean('popular')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_brands');
    }
}
