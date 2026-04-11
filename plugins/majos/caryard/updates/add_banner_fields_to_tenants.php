<?php namespace Majos\Caryard\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class AddBannerFieldsToTenants extends Migration
{
    public function up()
    {
        Schema::table('majos_caryard_tenants', function (Blueprint $table) {
            // Only add columns if they don't exist
            // Note: banner_image is handled via attachOne relationship in system_files table
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_title')) {
                $table->string('banner_title')->nullable();
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_subtitle')) {
                $table->string('banner_subtitle')->nullable();
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_tag')) {
                $table->string('banner_tag')->nullable();
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_description')) {
                $table->text('banner_description')->nullable();
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_button_text')) {
                $table->string('banner_button_text')->nullable();
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_button_url')) {
                $table->string('banner_button_url')->nullable();
            }
            if (!Schema::hasColumn('majos_caryard_tenants', 'banner_enabled')) {
                $table->boolean('banner_enabled')->default(true);
            }
        });
    }

    public function down()
    {
        Schema::table('majos_caryard_tenants', function (Blueprint $table) {
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_title')) {
                $table->dropColumn('banner_title');
            }
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_subtitle')) {
                $table->dropColumn('banner_subtitle');
            }
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_tag')) {
                $table->dropColumn('banner_tag');
            }
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_description')) {
                $table->dropColumn('banner_description');
            }
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_button_text')) {
                $table->dropColumn('banner_button_text');
            }
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_button_url')) {
                $table->dropColumn('banner_button_url');
            }
            if (Schema::hasColumn('majos_caryard_tenants', 'banner_enabled')) {
                $table->dropColumn('banner_enabled');
            }
        });
    }
}
