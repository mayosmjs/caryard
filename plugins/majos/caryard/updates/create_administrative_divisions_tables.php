<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateAdministrativeDivisionsTables extends Migration
{
    public function up()
    {
        /*
         * Defines administrative level labels per tenant
         */
        Schema::create('majos_caryard_division_types', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('tenant_id');

            $table->unsignedInteger('level');
            $table->string('label');
            $table->string('label_plural')->nullable();
            $table->timestamps();

            // $table->foreign('tenant_id')
            //     ->references('id')
            //     ->on('majos_caryard_tenants')
            //     ->onDelete('cascade');
        });

        /*
         * Stores hierarchical administrative divisions
         */
        Schema::create('majos_caryard_admin_divisions', function (Blueprint $table) {
            $table->increments('id');

            // Must match tenants.id (CHAR(36))
            $table->integer('tenant_id');

            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('level')->default(1);

            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('code', 20)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('tenant_id', 'caryard_div_tenant_idx');
            $table->index('slug', 'caryard_div_slug_idx');
            $table->index('code', 'caryard_div_code_idx');
            $table->index(['tenant_id', 'level'], 'caryard_div_tenant_level_idx');
            $table->index(['tenant_id', 'parent_id'], 'caryard_div_tenant_parent_idx');

            // Foreign keys
            // $table->foreign('tenant_id', 'caryard_div_tenant_fk')
            //     ->references('id')
            //     ->on('majos_caryard_tenants')
            //     ->onDelete('cascade');

            // $table->foreign('parent_id', 'caryard_div_parent_fk')
            //     ->references('id')
            //     ->on('majos_caryard_admin_divisions')
            //     ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('majos_caryard_admin_divisions');
        Schema::dropIfExists('majos_caryard_division_types');

        Schema::enableForeignKeyConstraints();
    }
}