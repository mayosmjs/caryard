<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\DivisionType;
use Majos\Caryard\Models\AdministrativeDivision;
use October\Rain\Database\Updates\Seeder;

class SeedEthiopiaDivisions extends Seeder
{
    public function run()
    {
        $tenant = Tenant::where('country_code', 'ET')->first();
        if (!$tenant) return;

        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 1],
            ['label' => 'Region', 'label_plural' => 'Regions']
        );
        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 2],
            ['label' => 'City/Zone', 'label_plural' => 'Cities/Zones']
        );

        $regions = [
            'Addis Ababa' => ['code' => 'AA', 'cities' => [
                'Bole', 'Kirkos', 'Arada', 'Yeka', 'Kolfe Keranio', 'Nifas Silk-Lafto', 'Lideta', 'Akaky Kaliti', 'Addis Ketema', 'Gulele', 'Lemi Kura',
            ]],
            'Oromia' => ['code' => 'OR', 'cities' => [
                'Adama', 'Bishoftu', 'Jimma', 'Shashamane', 'Nekemte', 'Ambo', 'Batu', 'Robe', 'Asella', 'Holeta', 'Sebeta', 'Woliso', 'Harar Road',
            ]],
            'Amhara' => ['code' => 'AM', 'cities' => [
                'Bahir Dar', 'Gondar', 'Dessie', 'Debre Markos', 'Debre Birhan', 'Kombolcha', 'Lalibela', 'Woldiya',
            ]],
            'Tigray' => ['code' => 'TG', 'cities' => [
                'Mekelle', 'Adigrat', 'Axum', 'Adwa', 'Shire', 'Wukro',
            ]],
            'SNNPR' => ['code' => 'SN', 'cities' => [
                'Hawassa', 'Arba Minch', 'Dilla', 'Sodo', 'Hosanna', 'Jinka', 'Mizan Teferi',
            ]],
            'Dire Dawa' => ['code' => 'DD', 'cities' => [
                'Dire Dawa', 'Sabian', 'Gendekore', 'Legehare',
            ]],
            'Sidama' => ['code' => 'SD', 'cities' => [
                'Hawassa (Sidama)', 'Yirgalem', 'Aleta Wendo', 'Bensa',
            ]],
            'Somali' => ['code' => 'SO', 'cities' => [
                'Jijiga', 'Gode', 'Degehabur', 'Kebri Dahar',
            ]],
            'Afar' => ['code' => 'AF', 'cities' => [
                'Semera', 'Asaita', 'Logiya', 'Dubti', 'Gewane',
            ]],
            'Benishangul-Gumuz' => ['code' => 'BG', 'cities' => [
                'Assosa', 'Bambasi', 'Kamashi', 'Metekel',
            ]],
            'Gambella' => ['code' => 'GB', 'cities' => [
                'Gambella', 'Abobo', 'Itang', 'Lare',
            ]],
            'Harari' => ['code' => 'HR', 'cities' => [
                'Harar', 'Jugol', 'Erer', 'Sofi',
            ]],
        ];

        foreach ($regions as $regionName => $info) {
            $region = AdministrativeDivision::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $regionName, 'level' => 1],
                ['code' => $info['code'], 'is_active' => true, 'slug' => \Str::slug($regionName)]
            );

            foreach ($info['cities'] as $index => $cityName) {
                AdministrativeDivision::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'parent_id' => $region->id, 'name' => $cityName, 'level' => 2],
                    ['is_active' => true, 'sort_order' => $index, 'slug' => \Str::slug($cityName)]
                );
            }
        }
    }
}