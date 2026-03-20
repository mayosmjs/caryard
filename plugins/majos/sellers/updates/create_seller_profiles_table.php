<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateSellerProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_sellers_profiles', function($table)
        {
            $table->uuid('id')->primary();
            $table->integer('user_id')->unsigned()->index();
            $table->uuid('tenant_id')->nullable();
            $table->string('company_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            
            // Geographic Hierarchy
            $table->uuid('country_id')->nullable();
            $table->uuid('province_id')->nullable();
            $table->uuid('city_id')->nullable();
            
            $table->string('identification_type')->nullable();
            $table->string('identification_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->boolean('is_verified_seller')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_sellers_profiles');
    }
}
