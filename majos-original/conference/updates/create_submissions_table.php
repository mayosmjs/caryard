<?php namespace Majos\Conference\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_submissions', function ($table) {
            $table->increments('id');
            $table->string('token', 80)->unique();
            $table->string('password_hash');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->string('author_name');
            $table->string('email')->index();
            $table->text('affiliation')->nullable();
            $table->string('department')->nullable();
            $table->string('institution')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('title');
            $table->text('authors')->nullable();
            $table->string('presentation_type')->nullable();
            $table->string('category');
            $table->text('abstract');
            $table->json('keywords')->nullable();
            $table->string('submitter_ip', 45)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->integer('decided_by')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_submissions');
    }
}
