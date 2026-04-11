<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\DivisionType;
use Majos\Caryard\Models\AdministrativeDivision;
use October\Rain\Database\Updates\Seeder;

class SeedUgandaDivisions extends Seeder
{
    public function run()
    {
        $tenant = Tenant::where('country_code', 'UG')->first();
        if (!$tenant) return;

        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 1],
            ['label' => 'District', 'label_plural' => 'Districts']
        );
        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 2],
            ['label' => 'Town', 'label_plural' => 'Towns']
        );

        $districts = [
            'Kampala' => ['code' => 'KLA', 'towns' => [
                'Kampala Central', 'Nakawa', 'Makindye', 'Rubaga', 'Kawempe', 'Kololo', 'Ntinda', 'Bugolobi', 'Wandegeya', 'Kisementi',
            ]],
            'Wakiso' => ['code' => 'WAK', 'towns' => [
                'Entebbe', 'Nansana', 'Kira', 'Mukono Road', 'Wakiso Town', 'Kasangati', 'Bweyogerere', 'Abayita Ababiri', 'Kajjansi', 'Nsangi',
            ]],
            'Mukono' => ['code' => 'MUK', 'towns' => [
                'Mukono', 'Seeta', 'Lugazi', 'Katosi', 'Nakifuma', 'Goma', 'Ntenjeru',
            ]],
            'Jinja' => ['code' => 'JIN', 'towns' => [
                'Jinja', 'Bugembe', 'Kakira', 'Buwenge', 'Njeru',
            ]],
            'Mbarara' => ['code' => 'MBR', 'towns' => [
                'Mbarara', 'Kakoba', 'Nyamitanga', 'Ruti', 'Biharwe', 'Kashare',
            ]],
            'Gulu' => ['code' => 'GUL', 'towns' => [
                'Gulu', 'Laroo', 'Bardege', 'Layibi', 'Pece', 'Unyama',
            ]],
            'Lira' => ['code' => 'LIR', 'towns' => [
                'Lira', 'Ojwina', 'Adyel', 'Railway', 'Starch Factory',
            ]],
            'Mbale' => ['code' => 'MBL', 'towns' => [
                'Mbale', 'Nakaloke', 'Malukhu', 'Wanale', 'Namakwekwe', 'Nkoma',
            ]],
            'Fort Portal' => ['code' => 'FPT', 'towns' => [
                'Fort Portal', 'Kabarole', 'Rwimi', 'Kibiito', 'Kijura',
            ]],
            'Masaka' => ['code' => 'MSK', 'towns' => [
                'Masaka', 'Nyendo', 'Kimanya', 'Katwe', 'Buwunga',
            ]],
            'Soroti' => ['code' => 'SRT', 'towns' => [
                'Soroti', 'Arapai', 'Gweri', 'Kamuda',
            ]],
            'Arua' => ['code' => 'ARU', 'towns' => [
                'Arua', 'Manibe', 'Oli River', 'Pajulu', 'Adumi',
            ]],
            'Hoima' => ['code' => 'HMA', 'towns' => [
                'Hoima', 'Bujumbura', 'Kigorobya', 'Kitoba', 'Mparo',
            ]],
            'Kabale' => ['code' => 'KBL', 'towns' => [
                'Kabale', 'Katuna', 'Maziba', 'Kaharo', 'Kitumba',
            ]],
            'Kasese' => ['code' => 'KSE', 'towns' => [
                'Kasese', 'Hima', 'Kilembe', 'Katwe', 'Muhokya',
            ]],
            'Iganga' => ['code' => 'IGA', 'towns' => [
                'Iganga', 'Nakigo', 'Busembatia', 'Bugweri',
            ]],
            'Tororo' => ['code' => 'TOR', 'towns' => [
                'Tororo', 'Malaba', 'Nagongera', 'Rubongi',
            ]],
            'Mityana' => ['code' => 'MTY', 'towns' => [
                'Mityana', 'Namutamba', 'Butayunja', 'Kikandwa',
            ]],
            'Masindi' => ['code' => 'MSD', 'towns' => [
                'Masindi', 'Kigumba', 'Bujenje', 'Pakanyi',
            ]],
            'Ntungamo' => ['code' => 'NTG', 'towns' => [
                'Ntungamo', 'Rubaare', 'Itojo', 'Kayonza',
            ]],
        ];

        foreach ($districts as $districtName => $info) {
            $district = AdministrativeDivision::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $districtName, 'level' => 1],
                ['code' => $info['code'], 'is_active' => true, 'slug' => \Str::slug($districtName)]
            );

            foreach ($info['towns'] as $index => $townName) {
                AdministrativeDivision::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'parent_id' => $district->id, 'name' => $townName, 'level' => 2],
                    ['is_active' => true, 'sort_order' => $index, 'slug' => \Str::slug($townName)]
                );
            }
        }
    }
}