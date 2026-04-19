<?php namespace Majos\Sellers\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class ReplaceLocationWithDivision extends Migration
{
    public function up()
    {
        Schema::table('majos_sellers_profiles', function (Blueprint $table) {
            // Add division_id
                $table->unsignedInteger('division_id')->nullable()->after('address');
                $table->string('tenant_id', 36)->nullable();
                $table->boolean('is_seller')->default(0);
                $table->text('company_name')->nullable();
                $table->string('phone_number')->nullable();
                $table->text('address')->nullable();
                $table->text('city')->nullable();
                $table->text('country')->nullable();
                $table->text('identification_type')->nullable();
                $table->text('identification_number')->nullable();
                $table->text('tax_id')->nullable();
                $table->boolean('is_verified_seller')->default(0);
        });
    }

    public function down()
    {
        Schema::table('majos_sellers_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('majos_sellers_profiles', 'division_id')) {
                $table->dropColumn([
                    'division_id',
                    'tenant_id',
                    'is_seller',
                    'company_name',
                    'phone_number',
                    'address',
                    'city',
                    'country',
                    'identification_type',
                    'identification_number',
                    'tax_id',
                    'is_verified_seller'
                    ]);
            }
        });
    }
}