<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateSubscriptionPlansTable extends Migration
{
    public function up()
    {
        Schema::create('majos_sellers_subscription_plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->enum('tier', ['trial', 'basic', 'premium'])->default('basic');
            $table->integer('vehicle_limit')->default(2);
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_annual', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->text('features')->nullable();
            $table->integer('trial_duration_days')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_sellers_subscription_plans');
    }
}