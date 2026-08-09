<?php namespace Majos\Conference\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateVerificationCodesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_verification_codes', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('email')->index();
            $table->string('code', 10);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_verification_codes');
    }
}
