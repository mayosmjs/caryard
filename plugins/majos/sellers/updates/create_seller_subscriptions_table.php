<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateSellerSubscriptionsTableV2 extends Migration
{
    public function up()
    {
        Schema::create('majos_sellers_seller_subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('seller_id', 36);
            $table->unsignedInteger('plan_id');
            $table->enum('status', ['trialing', 'active', 'expired', 'cancelled','pending'])->default('pending');
            $table->integer('duration_days')->nullable();
            $table->string('amount')->nullable();
            $table->string('currency')->default('KES');
            $table->string('transaction_id')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('seller_id');
            $table->index('plan_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_sellers_seller_subscriptions');
    }
}