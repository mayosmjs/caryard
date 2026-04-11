<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\DivisionType;
use Majos\Caryard\Models\AdministrativeDivision;
use October\Rain\Database\Updates\Seeder;

class SeedTanzaniaDivisions extends Seeder
{
    public function run()
    {
        $tenant = Tenant::where('country_code', 'TZ')->first();
        if (!$tenant) return;

        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 1],
            ['label' => 'Region', 'label_plural' => 'Regions']
        );
        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 2],
            ['label' => 'District/City', 'label_plural' => 'Districts/Cities']
        );

        $regions = [
            'Dar es Salaam' => ['code' => 'DAR', 'districts' => [
                'Ilala', 'Kinondoni', 'Temeke', 'Ubungo', 'Kigamboni',
            ]],
            'Arusha' => ['code' => 'ARU', 'districts' => [
                'Arusha City', 'Arusha District', 'Karatu', 'Longido', 'Meru', 'Monduli', 'Ngorongoro',
            ]],
            'Dodoma' => ['code' => 'DOD', 'districts' => [
                'Dodoma City', 'Bahi', 'Chamwino', 'Chemba', 'Kondoa', 'Kongwa', 'Mpwapwa',
            ]],
            'Mwanza' => ['code' => 'MWZ', 'districts' => [
                'Nyamagana', 'Ilemela', 'Kwimba', 'Magu', 'Misungwi', 'Sengerema', 'Ukerewe',
            ]],
            'Morogoro' => ['code' => 'MOR', 'districts' => [
                'Morogoro Municipal', 'Kilombero', 'Kilosa', 'Mvomero', 'Ulanga', 'Gairo', 'Malinyi',
            ]],
            'Mbeya' => ['code' => 'MBY', 'districts' => [
                'Mbeya City', 'Chunya', 'Mbarali', 'Rungwe', 'Busokelo',
            ]],
            'Tanga' => ['code' => 'TAN', 'districts' => [
                'Tanga City', 'Handeni', 'Kilindi', 'Korogwe', 'Lushoto', 'Muheza', 'Pangani', 'Mkinga',
            ]],
            'Kilimanjaro' => ['code' => 'KIL', 'districts' => [
                'Moshi Municipal', 'Moshi District', 'Hai', 'Mwanga', 'Rombo', 'Same', 'Siha',
            ]],
            'Iringa' => ['code' => 'IRG', 'districts' => [
                'Iringa Municipal', 'Iringa District', 'Kilolo', 'Mufindi',
            ]],
            'Kagera' => ['code' => 'KGR', 'districts' => [
                'Bukoba Municipal', 'Bukoba District', 'Biharamulo', 'Karagwe', 'Kyerwa', 'Muleba', 'Ngara', 'Missenyi',
            ]],
            'Mara' => ['code' => 'MAR', 'districts' => [
                'Musoma Municipal', 'Musoma District', 'Bunda', 'Butiama', 'Rorya', 'Serengeti', 'Tarime',
            ]],
            'Pwani' => ['code' => 'PWN', 'districts' => [
                'Kibaha', 'Bagamoyo', 'Chalinze', 'Kisarawe', 'Mafia', 'Mkuranga', 'Rufiji',
            ]],
            'Singida' => ['code' => 'SNG', 'districts' => [
                'Singida Municipal', 'Ikungi', 'Iramba', 'Manyoni', 'Mkalama',
            ]],
            'Tabora' => ['code' => 'TAB', 'districts' => [
                'Tabora Municipal', 'Igunga', 'Kaliua', 'Nzega', 'Sikonge', 'Urambo',
            ]],
            'Zanzibar' => ['code' => 'ZNZ', 'districts' => [
                'Zanzibar City', 'Magharibi A', 'Magharibi B', 'Kaskazini A', 'Kaskazini B', 'Kusini',
            ]],
            'Lindi' => ['code' => 'LND', 'districts' => [
                'Lindi Municipal', 'Kilwa', 'Liwale', 'Nachingwea', 'Ruangwa',
            ]],
            'Mtwara' => ['code' => 'MTW', 'districts' => [
                'Mtwara Municipal', 'Masasi', 'Nanyumbu', 'Newala', 'Tandahimba',
            ]],
            'Rukwa' => ['code' => 'RUK', 'districts' => [
                'Sumbawanga Municipal', 'Sumbawanga District', 'Kalambo', 'Nkasi',
            ]],
            'Shinyanga' => ['code' => 'SHY', 'districts' => [
                'Shinyanga Municipal', 'Kahama', 'Kishapu', 'Msalala', 'Ushetu',
            ]],
            'Kigoma' => ['code' => 'KGM', 'districts' => [
                'Kigoma-Ujiji', 'Kasulu', 'Kibondo', 'Kakonko', 'Uvinza', 'Buhigwe',
            ]],
            'Geita' => ['code' => 'GTA', 'districts' => [
                'Geita', 'Bukombe', 'Chato', 'Mbogwe', 'Nyang\'hwale',
            ]],
            'Katavi' => ['code' => 'KTV', 'districts' => [
                'Mpanda', 'Mlele', 'Nsimbo',
            ]],
            'Njombe' => ['code' => 'NJM', 'districts' => [
                'Njombe', 'Ludewa', 'Makambako', 'Makete', 'Wanging\'ombe',
            ]],
            'Simiyu' => ['code' => 'SMY', 'districts' => [
                'Bariadi', 'Busega', 'Itilima', 'Maswa', 'Meatu',
            ]],
            'Songwe' => ['code' => 'SGW', 'districts' => [
                'Songwe', 'Mbozi', 'Momba', 'Tunduma',
            ]],
        ];

        foreach ($regions as $regionName => $info) {
            $region = AdministrativeDivision::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $regionName, 'level' => 1],
                ['code' => $info['code'], 'is_active' => true, 'slug' => \Str::slug($regionName)]
            );

            foreach ($info['districts'] as $index => $districtName) {
                AdministrativeDivision::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'parent_id' => $region->id, 'name' => $districtName, 'level' => 2],
                    ['is_active' => true, 'sort_order' => $index, 'slug' => \Str::slug($districtName)]
                );
            }
        }
    }
}