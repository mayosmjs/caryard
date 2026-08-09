<?php

use October\Rain\Database\Updates\Migration;

class CreateCommitteesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_committees', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->string('designation');
            $table->string('committee_type');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_committees');
    }
}
