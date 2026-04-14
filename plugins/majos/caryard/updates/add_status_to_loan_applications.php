<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddStatusToLoanApplications extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_loan_applications', function ($table) {
            $table->string('status')->default('pending')->after('vehicle_id');
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_loan_applications', function ($table) {
            $table->dropColumn('status');
        });
    }
}
