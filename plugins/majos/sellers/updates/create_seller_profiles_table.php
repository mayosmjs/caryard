<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateSellerProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_sellers_profiles', function($table)
        {
                $table->string('id', 36)->primary();
                $table->unsignedInteger('division_id')->nullable()->default(null);
                $table->unsignedInteger('tenant_id')->nullable()->default(null);
                $table->boolean('is_seller')->default(0);
                $table->unsignedInteger('user_id')->default(0);
                $table->text('company_name')->nullable();
                $table->string('phone_number')->nullable();
                $table->text('address')->nullable();
                $table->text('city')->nullable();
                $table->text('country')->nullable();
                $table->text('identification_type')->nullable();
                $table->text('identification_number')->nullable();
                $table->text('tax_id')->nullable();
                $table->boolean('is_verified_seller')->default(0);
                $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('majos_sellers_profiles');
    }
}
