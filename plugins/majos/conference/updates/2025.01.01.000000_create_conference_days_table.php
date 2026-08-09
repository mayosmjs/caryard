<?php

use October\Rain\Database\Updates\Migration;

class CreateConferenceDaysTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_days', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('title');
            $table->date('date');
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_days');
    }
}