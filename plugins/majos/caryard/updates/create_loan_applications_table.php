<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateLoanApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_loan_applications', function($table)
        {
            $table->increments('id');
            $table->text('application');
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_loan_applications');
    }
}