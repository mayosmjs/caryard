<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_sellers_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('subscription_id');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, paid, overdue, cancelled
            $table->text('description')->nullable();
            $table->text('metadata')->nullable(); // JSON - provider-specific data
            $table->timestamps();
            
            $table->index('transaction_id');
            $table->index('subscription_id');
            $table->index('invoice_number');
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_sellers_invoices');
    }
}