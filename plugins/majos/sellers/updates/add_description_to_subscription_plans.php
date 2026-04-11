<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class AddDescriptionToSubscriptionPlans extends Migration
{
    public function up()
    {
        Schema::table('majos_sellers_subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('majos_sellers_subscription_plans', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('majos_sellers_subscription_plans', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
}
