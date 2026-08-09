<?php namespace Majos\Conference\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateReviewersTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_reviewers', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique()->index();
            $table->string('token', 80)->unique()->index();
            $table->string('password_hash');
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Null out any stale reviewer_id values that pointed at backend users
        // so that fresh assignments using the new Reviewer model start clean.
        \DB::table('majos_conference_submissions')
            ->whereNotNull('reviewer_id')
            ->update([
                'reviewer_id'       => null,
                'assigned_at'       => null,
                'reviewer_decision' => null,
                'reviewer_feedback' => null,
                'reviewed_at'       => null,
                'status'            => 'submitted',
            ]);
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_reviewers');
    }
}
