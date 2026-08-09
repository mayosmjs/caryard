<?php

use October\Rain\Database\Updates\Migration;

class CreateVenuesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_venues', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->string('location');
            $table->text('description')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_venues');
    }
}