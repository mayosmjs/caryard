<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class AddCurrencySymbolToTenants extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_tenants', function (Blueprint $table) {
            $table->string('currency_symbol', 10)->nullable()->after('currency');
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_tenants', function (Blueprint $table) {
            $table->dropColumn('currency_symbol');
        });
    }
}
