<?php namespace Majos\Conference\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreatePaperSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_paper_submissions', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('email')->index();
            $table->string('verification_code', 10)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('full_name');
            $table->string('phone_number');
            $table->string('country');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('reference_number', 20)->unique()->index();
            $table->dateTime('submission_date');
            $table->enum('status', ['pending', 'verified', 'completed', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_paper_submissions');
    }
}