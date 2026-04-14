<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddVehicleIdToLoanApplications extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_loan_applications', function ($table) {
            $table->unsignedInteger('vehicle_id')->nullable()->after('tenant_id');
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_loan_applications', function ($table) {
            $table->dropColumn('vehicle_id');
        });
    }
}
