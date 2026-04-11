<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\DivisionType;
use Majos\Caryard\Models\AdministrativeDivision;
use October\Rain\Database\Updates\Seeder;

class SeedRwandaDivisions extends Seeder
{
    public function run()
    {
        $tenant = Tenant::where('country_code', 'RW')->first();
        if (!$tenant) return;

        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 1],
            ['label' => 'Province', 'label_plural' => 'Provinces']
        );
        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 2],
            ['label' => 'District', 'label_plural' => 'Districts']
        );

        $provinces = [
            'Kigali City' => ['code' => 'KGL', 'districts' => [
                'Gasabo', 'Kicukiro', 'Nyarugenge',
            ]],
            'Eastern Province' => ['code' => 'EST', 'districts' => [
                'Bugesera', 'Gatsibo', 'Kayonza', 'Kirehe', 'Ngoma', 'Nyagatare', 'Rwamagana',
            ]],
            'Northern Province' => ['code' => 'NTH', 'districts' => [
                'Burera', 'Gakenke', 'Gicumbi', 'Musanze', 'Rulindo',
            ]],
            'Southern Province' => ['code' => 'STH', 'districts' => [
                'Gisagara', 'Huye', 'Kamonyi', 'Muhanga', 'Nyamagabe', 'Nyanza', 'Nyaruguru', 'Ruhango',
            ]],
            'Western Province' => ['code' => 'WST', 'districts' => [
                'Karongi', 'Ngororero', 'Nyabihu', 'Nyamasheke', 'Rubavu', 'Rusizi', 'Rutsiro',
            ]],
        ];

        foreach ($provinces as $provinceName => $info) {
            $province = AdministrativeDivision::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $provinceName, 'level' => 1],
                ['code' => $info['code'], 'is_active' => true, 'slug' => \Str::slug($provinceName)]
            );

            foreach ($info['districts'] as $index => $districtName) {
                AdministrativeDivision::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'parent_id' => $province->id, 'name' => $districtName, 'level' => 2],
                    ['is_active' => true, 'sort_order' => $index, 'slug' => \Str::slug($districtName)]
                );
            }
        }
    }
}