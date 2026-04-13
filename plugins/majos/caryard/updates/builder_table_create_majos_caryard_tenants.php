<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateMajosCaryardTenants extends Migration
{
    public function up()
    {
        Schema::create('majos_caryard_tenants', function($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country_code')->unique();
            $table->string('currency', 10);
            $table->string('currency_symbol', 10)->nullable();
            $table->string('locale')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Banner fields
            $table->string('banner_title')->nullable();
            $table->string('banner_subtitle')->nullable();
            $table->string('banner_tag')->nullable();
            $table->text('banner_description')->nullable();
            $table->string('banner_button_text')->nullable();
            $table->string('banner_button_url')->nullable();
            $table->boolean('banner_enabled')->default(true);
            
            // Loan settings
            $table->boolean('loan_enabled')->default(true);
            $table->text('loan_terms')->nullable();
            $table->integer('loan_default_term')->default(24);
            $table->integer('loan_min_down_payment_percent')->default(10);
            $table->integer('loan_max_down_payment_percent')->default(70);
            $table->decimal('loan_annual_rate', 5, 4)->default(0.1800);
            
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('majos_caryard_tenants');
        Schema::enableForeignKeyConstraints();
    }
}
