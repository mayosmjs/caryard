<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Create table for seller loan settings
 * 
 * Allows individual sellers to configure their own loan calculator parameters
 */
class CreateSellerLoanSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_seller_loan_settings', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned()->unique()->comment('Reference to users table');
            $table->boolean('loan_enabled')->default(false)->comment('Enable/disable loan estimator');
            $table->text('loan_terms')->nullable()->comment('JSON array of available loan terms in months');
            $table->integer('loan_default_term')->default(0)->comment('Default loan term in months');
            $table->decimal('loan_annual_rate', 10, 4)->default(0)->comment('Annual interest rate as decimal (e.g., 0.18 for 18%)');
            $table->decimal('loan_min_down_payment_percent', 10, 2)->default(0)->comment('Minimum down payment percentage');
            $table->decimal('loan_max_down_payment_percent', 10, 2)->default(0)->comment('Maximum down payment percentage');
            $table->timestamps();
            
            // Foreign key to users table
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // Index for faster lookups
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_caryard_seller_loan_settings');
    }
}
