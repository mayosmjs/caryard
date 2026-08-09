<?php namespace Majos\Conference\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddApprovedPresentationTypeToSubmissions extends Migration
{
    public function up()
    {
        Schema::table('majos_conference_submissions', function ($table) {
            $table->string('approved_presentation_type')->nullable()->after('presentation_type');
        });
    }

    public function down()
    {
        Schema::table('majos_conference_submissions', function ($table) {
            $table->dropColumn('approved_presentation_type');
        });
    }
}
