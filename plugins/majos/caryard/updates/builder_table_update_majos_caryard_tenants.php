<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateMajosCaryardTenants extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_tenants', function($table)
        {
            // $table->boolean('is_active')->default(1);
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_tenants', function($table)
        {
            // $table->dropColumn('is_active');
        });
    }
}
