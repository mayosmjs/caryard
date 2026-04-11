<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddImageFieldToAdvertisements extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_advertisements', function($table)
        {
            $table->string('image')->nullable()->after('tenant_id');
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_advertisements', function($table)
        {
            $table->dropColumn('image');
        });
    }
}
