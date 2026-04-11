<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateAdvertisementsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_advertisements', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('tenant_id')->unsigned()->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_advertisements');
    }
}
