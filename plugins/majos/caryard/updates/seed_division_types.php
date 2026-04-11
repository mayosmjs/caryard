<?php namespace Majos\Caryard\Updates;

use Seeder;
use Majos\Caryard\Models\DivisionType;
use Majos\Caryard\Models\Tenant;

class SeedDivisionTypes extends Seeder
{
    public function run()
    {
        // Map country_code → division levels
        $definitions = [
            'KE' => [
                ['level' => 1, 'label' => 'County',     'label_plural' => 'Counties'],
                ['level' => 2, 'label' => 'Town',       'label_plural' => 'Towns'],
            ],
            'UG' => [
                ['level' => 1, 'label' => 'District',   'label_plural' => 'Districts'],
                ['level' => 2, 'label' => 'Town',       'label_plural' => 'Towns'],
            ],
            'TZ' => [
                ['level' => 1, 'label' => 'Region',     'label_plural' => 'Regions'],
                ['level' => 2, 'label' => 'District',   'label_plural' => 'Districts'],
                ['level' => 3, 'label' => 'Town',       'label_plural' => 'Towns'],
            ],
            'ZA' => [
                ['level' => 1, 'label' => 'Province',   'label_plural' => 'Provinces'],
                ['level' => 2, 'label' => 'City',       'label_plural' => 'Cities'],
            ],
            'NG' => [
                ['level' => 1, 'label' => 'State',      'label_plural' => 'States'],
                ['level' => 2, 'label' => 'LGA',        'label_plural' => 'LGAs'],
                ['level' => 3, 'label' => 'Town',       'label_plural' => 'Towns'],
            ],
            'GH' => [
                ['level' => 1, 'label' => 'Region',     'label_plural' => 'Regions'],
                ['level' => 2, 'label' => 'District',   'label_plural' => 'Districts'],
            ],
            'RW' => [
                ['level' => 1, 'label' => 'Province',   'label_plural' => 'Provinces'],
                ['level' => 2, 'label' => 'District',   'label_plural' => 'Districts'],
            ],
            'ET' => [
                ['level' => 1, 'label' => 'Region',     'label_plural' => 'Regions'],
                ['level' => 2, 'label' => 'Zone',       'label_plural' => 'Zones'],
            ],
        ];

        foreach ($definitions as $countryCode => $levels) {
            $tenant = Tenant::where('country_code', $countryCode)->first();
            if (!$tenant) continue;

            foreach ($levels as $type) {
                DivisionType::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'level' => $type['level']],
                    ['label' => $type['label'], 'label_plural' => $type['label_plural']]
                );
            }
        }
    }
}