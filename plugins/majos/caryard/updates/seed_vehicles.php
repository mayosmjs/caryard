<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Vehicle;
use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\Brand;
use Majos\Caryard\Models\VehicleModel;
use Majos\Caryard\Models\Condition;
use Majos\Caryard\Models\FuelType;
use Majos\Caryard\Models\Transmission;
use Majos\Caryard\Models\BodyType;
use Majos\Caryard\Models\Color;
use Majos\Caryard\Models\EngineCapacity;
use Majos\Caryard\Models\DriveType;
use Majos\Location\Models\Country;
use Majos\Location\Models\Province;
use Majos\Location\Models\City;
use RainLab\User\Models\User;
use October\Rain\Database\Updates\Seeder;
use Illuminate\Support\Str;

class SeedVehicles extends Seeder
{
    public function run()
    {
        // 1. Ensure Default Seller/User exists
        $seller = User::first();
        if (!$seller) {
            $seller = User::create([
                'first_name' => 'Default',
                'last_name' => 'Seller',
                'email' => 'seller@example.com',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'username' => 'seller',
                'activated_at' => now()
            ]);
        }

        // 2. Ensure Tenants are Active
        $tenants = [
            'UG' => Tenant::where('country_code', 'UG')->first(),
            'KE' => Tenant::where('country_code', 'KE')->first(),
        ];

        foreach ($tenants as $code => $tenant) {
            if ($tenant) {
                $tenant->is_active = true;
                $tenant->save();
            }
        }

        // 3. Ensure Locations exist for Uganda (Kenya usually has some from SeedLocationsTable)
        $this->ensureLocations();

        // 4. Seed Vehicles
        $this->seedVehiclesForTenant($tenants['KE'], $seller);
        $this->seedVehiclesForTenant($tenants['UG'], $seller);
    }

    protected function ensureLocations()
    {
        // Ensure Uganda Country exists in Location plugin
        $uganda = Country::where('code', 'UG')->first();
        if (!$uganda) {
            $uganda = Country::create(['name' => 'Uganda', 'code' => 'UG', 'is_active' => true]);
        }

        // Ensure at least one city in Uganda
        $kampalaProvince = Province::where('country_id', $uganda->id)->where('name', 'Central')->first();
        if (!$kampalaProvince) {
            $kampalaProvince = Province::create([
                'country_id' => $uganda->id,
                'name' => 'Central',
                'code' => 'CEN'
            ]);
        }

        if (City::where('province_id', $kampalaProvince->id)->count() === 0) {
            City::create(['province_id' => $kampalaProvince->id, 'name' => 'Kampala']);
            City::create(['province_id' => $kampalaProvince->id, 'name' => 'Entebbe']);
        }

        // Ensure Kenya has cities (just in case)
        $kenya = Country::where('code', 'KE')->first();
        if ($kenya) {
            $nairobiProvince = Province::where('country_id', $kenya->id)->first();
            if ($nairobiProvince && City::where('province_id', $nairobiProvince->id)->count() === 0) {
                City::create(['province_id' => $nairobiProvince->id, 'name' => 'Nairobi']);
            }
        }
    }

    protected function seedVehiclesForTenant($tenant, $seller)
    {
        if (!$tenant) return;

        $brands = Brand::with('vehicle_models')->get();
        if ($brands->isEmpty()) return;

        $conditions = Condition::all();
        $fuelTypes = FuelType::all();
        $transmissions = Transmission::all();
        $bodyTypes = BodyType::all();
        $colors = Color::all();
        $capacities = EngineCapacity::all();
        $driveTypes = DriveType::all();
        
        // Get cities for the corresponding country
        $country = Country::where('code', $tenant->country_code)->first();
        $cities = City::whereHas('province', function($q) use ($country) {
            $q->where('country_id', $country->id);
        })->get();

        if ($cities->isEmpty()) {
            // Fallback to any city if none found for country
            $cities = City::all();
        }

        $options_pool = Vehicle::getCategorizedOptions();
        $all_options_keys = [];
        foreach ($options_pool as $cat => $opts) {
            $all_options_keys = array_merge($all_options_keys, array_keys($opts));
        }

        for ($i = 1; $i <= 10; $i++) {
            $brand = $brands->random();
            $model = $brand->vehicle_models->random();
            $city = $cities->random();
            
            $title = $brand->name . ' ' . $model->name . ' ' . rand(2015, 2024);
            $slug = Str::slug($title) . '-' . Str::random(5);

            // Random options
            $selected_options = [];
            $random_keys = (array) array_rand($all_options_keys, rand(5, 15));
            foreach ($random_keys as $key) {
                $selected_options[$all_options_keys[$key]] = true;
            }

            Vehicle::create([
                'tenant_id' => $tenant->id,
                'brand_id' => $brand->id,
                'model_id' => $model->id,
                'location_id' => $city->id,
                'seller_id' => $seller->id,
                'title' => $title,
                'slug' => $slug,
                'year' => rand(2015, 2024) . '-01-01',
                'price' => rand(1500000, 8000000),
                'mileage' => rand(10000, 150000),
                'vin_id' => strtoupper(Str::random(17)),
                'vehicleid' => strtoupper(Str::random(8)),
                'condition_id' => $conditions->random()->id,
                'fuel_type_id' => $fuelTypes->random()->id,
                'transmission_id' => $transmissions->random()->id,
                'body_type_id' => $bodyTypes->random()->id,
                'color_id' => $colors->random()->id,
                'engine_capacity_d' => $capacities->random()->id,
                'drive_type_id' => $driveTypes->random()->id,
                'is_active' => true,
                'options' => $selected_options
            ]);
        }
    }
}
