<?php

use October\Rain\Database\Updates\Migration;

class CreateSessionSpeakerTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_session_speaker', function($table)
        {
            $table->engine = 'InnoDB';
            $table->integer('session_id')->unsigned();
            $table->integer('speaker_id')->unsigned();
            $table->primary(['session_id', 'speaker_id']);

            $table->foreign('session_id')
                ->references('id')->on('majos_conference_sessions')
                ->onDelete('cascade');
            $table->foreign('speaker_id')
                ->references('id')->on('majos_conference_speakers')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_session_speaker');
    }
}