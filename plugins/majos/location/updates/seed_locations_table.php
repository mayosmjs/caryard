<?php namespace Majos\Location\Updates;

use Majos\Location\Models\Country;
use Majos\Location\Models\Province;
use Majos\Location\Models\City;
use October\Rain\Database\Updates\Seeder;

class SeedLocationsTable extends Seeder
{
    public function run()
    {
        $countries = [
            'Kenya' => [
                'Nairobi' => ['Nairobi City', 'Westlands', 'Kasarani'],
                'Mombasa' => ['Mombasa City', 'Nyali', 'Likoni'],
            ],
            'Nigeria' => [
                'Lagos' => ['Ikeja', 'Lekki', 'Ikorodu'],
                'Abuja' => ['Garki', 'Wuse', 'Asokoro'],
            ],
            'South Africa' => [
                'Gauteng' => ['Johannesburg', 'Pretoria', 'Soweto'],
                'Western Cape' => ['Cape Town', 'Stellenbosch', 'Paarl'],
            ]
        ];

        foreach ($countries as $countryName => $provinces) {
            $country = Country::create([
                'name' => $countryName,
                'code' => strtoupper(substr($countryName, 0, 2)),
                'is_active' => true
            ]);

            foreach ($provinces as $provinceName => $cities) {
                $province = Province::create([
                    'country_id' => $country->id,
                    'name' => $provinceName,
                    'code' => strtoupper(substr($provinceName, 0, 3)),
                ]);

                foreach ($cities as $cityName) {
                    City::create([
                        'province_id' => $province->id,
                        'name' => $cityName
                    ]);
                }
            }
        }
    }
}
