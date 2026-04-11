<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddLoanFieldsToTenants extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_tenants', function($table)
        {
            if (!Schema::hasColumn('majos_caryard_tenants', 'loan_enabled')) {
                $table->boolean('loan_enabled')->default(true)->after('banner_enabled');
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'loan_terms')) {
                $table->text('loan_terms')->nullable()->after('loan_enabled');
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'loan_default_term')) {
                $table->integer('loan_default_term')->default(24)->after('loan_terms');
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'loan_min_down_payment_percent')) {
                $table->integer('loan_min_down_payment_percent')->default(10)->after('loan_default_term');
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'loan_max_down_payment_percent')) {
                $table->integer('loan_max_down_payment_percent')->default(70)->after('loan_min_down_payment_percent');
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'loan_annual_rate')) {
                $table->decimal('loan_annual_rate', 5, 4)->default(0.1800)->after('loan_max_down_payment_percent');
            }
        });
    }
    
    public function down()
    {
        Schema::table('majos_caryard_tenants', function($table)
        {
            $columns = [
                'loan_enabled',
                'loan_terms', 
                'loan_default_term',
                'loan_min_down_payment_percent',
                'loan_max_down_payment_percent',
                'loan_annual_rate'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('majos_caryard_tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}