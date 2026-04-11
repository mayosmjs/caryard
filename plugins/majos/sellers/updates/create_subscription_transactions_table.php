<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateSubscriptionTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_sellers_subscription_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('subscription_id');
            $table->string('provider')->nullable(); // mpesa, paypal, stripe
            $table->string('transaction_id')->nullable(); // External transaction ID
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('payment_type')->nullable(); // subscription_create, subscription_renew, etc.
            $table->text('customer_details')->nullable(); // JSON - customer phone, email etc
            $table->text('metadata')->nullable(); // JSON - provider-specific data
            $table->enum('initiated_by', ['admin', 'system', 'frontend'])->default('frontend');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index('subscription_id');
            $table->index(['provider', 'status']);
            $table->index(['transaction_id']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_sellers_subscription_transactions');
    }
}