<?php

use October\Rain\Database\Updates\Migration;

class CreateSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_sessions', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('conference_day_id')->unsigned()->nullable();
            $table->integer('venue_id')->unsigned()->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('session_type')->nullable();
            $table->string('track')->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->foreign('conference_day_id')
                ->references('id')->on('majos_conference_days')
                ->onDelete('set null');
            $table->foreign('venue_id')
                ->references('id')->on('majos_conference_venues')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_sessions');
    }
}