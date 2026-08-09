<?php namespace Majos\Conference\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddReviewFieldsToSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::table('majos_conference_submissions', function ($table) {
            $table->unsignedInteger('reviewer_id')->nullable()->after('decided_by');
            $table->timestamp('assigned_at')->nullable()->after('reviewer_id');
            // reviewer_decision: 'approved' or 'rejected'
            $table->string('reviewer_decision', 20)->nullable()->after('assigned_at');
            $table->text('reviewer_feedback')->nullable()->after('reviewer_decision');
            $table->timestamp('reviewed_at')->nullable()->after('reviewer_feedback');
            $table->text('admin_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down()
    {
        Schema::table('majos_conference_submissions', function ($table) {
            $table->dropColumn([
                'reviewer_id',
                'assigned_at',
                'reviewer_decision',
                'reviewer_feedback',
                'reviewed_at',
                'admin_notes',
            ]);
        });
    }
}
