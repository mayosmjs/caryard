<?php

use October\Rain\Database\Updates\Migration;

class CreateSpeakersTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_speakers', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('full_name');
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->string('country')->nullable();
            $table->string('expertise')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website_url')->nullable();
            $table->text('biography')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_speakers');
    }
}