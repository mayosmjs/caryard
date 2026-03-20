<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardTenants extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_tenants', function($table)
        {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('country_code');
            $table->string('currency');
            $table->string('locale')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('majos_caryard_tenants');
    }
}
