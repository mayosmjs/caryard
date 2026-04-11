<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardLocations extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_locations', function($table)
        {
            $table->increments('id');
            $table->integer('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->integer('parent_id')->nullable();
            $table->string('type')->default('area');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_locations');
    }
}
