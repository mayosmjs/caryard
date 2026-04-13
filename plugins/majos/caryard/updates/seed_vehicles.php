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
use Majos\Caryard\Models\AdministrativeDivision;
use RainLab\User\Models\User;
use October\Rain\Database\Updates\Seeder;
use Illuminate\Support\Str;

class SeedVehicles extends Seeder
{
    public function run()
    {
        if (!\Schema::hasTable('majos_caryard_admin_divisions')) {
            return;
        }

        $tenants = [
            'KE' => Tenant::where('country_code', 'KE')->first(),
            'UG' => Tenant::where('country_code', 'UG')->first(),
            'TZ' => Tenant::where('country_code', 'TZ')->first(),
        ];

        $sellerUsers = [];
        
        $sellerData = [
            'KE' => ['name' => 'Kenya Seller', 'email' => 'kenya@caryard.com'],
            'UG' => ['name' => 'Uganda Seller', 'email' => 'uganda@caryard.com'],
            'TZ' => ['name' => 'Tanzania Seller', 'email' => 'tanzania@caryard.com'],
        ];

        foreach ($tenants as $code => $tenant) {
            if ($tenant) {
                $tenant->is_active = true;
                $tenant->save();

                $data = $sellerData[$code];
                $seller = User::where('email', $data['email'])->first();
                
                if (!$seller) {
                    $seller = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => 'Password123',
                        'password_confirmation' => 'Password123',
                        'username' => strtolower($code) . ' seller',
                        'activated_at' => now()
                    ]);
                }
                
                $sellerUsers[$code] = $seller;
            }
        }

        $this->seedVehiclesForTenant($tenants['KE'], $sellerUsers['KE']);
        $this->seedVehiclesForTenant($tenants['UG'], $sellerUsers['UG']);
        $this->seedVehiclesForTenant($tenants['TZ'], $sellerUsers['TZ']);
    }

    protected function seedVehiclesForTenant($tenant, $seller)
    {
        if (!$tenant) return;

        $brands = Brand::with('vehicle_models')->get();
        if ($brands->isEmpty()) return;

        // Only use brands that actually have models
        $brandsWithModels = $brands->filter(function ($b) {
            return $b->vehicle_models->isNotEmpty();
        });
        if ($brandsWithModels->isEmpty()) return;

        $conditions = Condition::all();
        $fuelTypes = FuelType::all();
        $transmissions = Transmission::all();
        $bodyTypes = BodyType::all();
        $colors = Color::all();
        $capacities = EngineCapacity::all();
        $driveTypes = DriveType::all();

        // Bail if any required lookup is empty
        if ($conditions->isEmpty() || $fuelTypes->isEmpty() || $transmissions->isEmpty()
            || $bodyTypes->isEmpty() || $colors->isEmpty() || $capacities->isEmpty()
            || $driveTypes->isEmpty()) {
            return;
        }

        // Get leaf-level divisions for this tenant (level 2, fallback to level 1)
        $divisions = AdministrativeDivision::where('tenant_id', $tenant->id)
            ->where('level', 2)
            ->where('is_active', true)
            ->get();

        if ($divisions->isEmpty()) {
            $divisions = AdministrativeDivision::where('tenant_id', $tenant->id)
                ->where('level', 1)
                ->where('is_active', true)
                ->get();
        }

        if ($divisions->isEmpty()) return;

        $options_pool = Vehicle::getCategorizedOptions();
        $all_options_keys = [];
        foreach ($options_pool as $cat => $opts) {
            $all_options_keys = array_merge($all_options_keys, array_keys($opts));
        }

        for ($i = 1; $i <= 60; $i++) {
            try {
                $brand = $brandsWithModels->random();
                $model = $brand->vehicle_models->random();
                $division = $divisions->random();
                $year = rand(2015, 2024);

                $title = $year . ' ' . $brand->name . ' ' . $model->name;
                $slug = Str::slug($title) . '-' . Str::random(6);

                // Random options (at least 3)
                $selected_options = [];
                if (count($all_options_keys) > 0) {
                    $numOptions = min(count($all_options_keys), rand(3, 12));
                    $randomIndices = (array) array_rand($all_options_keys, $numOptions);
                    foreach ($randomIndices as $idx) {
                        $selected_options[$all_options_keys[$idx]] = true;
                    }
                }
                if (empty($selected_options)) {
                    $selected_options = ['bluetooth' => true];
                }

                $vehicle = new Vehicle();
                $vehicle->tenant_id = $tenant->id;
                $vehicle->brand_id = $brand->id;
                $vehicle->model_id = $model->id;
                $vehicle->division_id = $division->id;
                $vehicle->seller_id = $seller->id;
                $vehicle->title = $title;
                $vehicle->slug = $slug;
                $vehicle->year = $year . '-' . str_pad(rand(1,12), 2, '0', STR_PAD_LEFT) . '-01';
                $vehicle->price = rand(15, 80) * 100000;
                $vehicle->mileage = rand(10, 150) * 1000;
                $vehicle->vin_id = strtoupper(Str::random(17));
                $vehicle->vehicleid = strtoupper('V-' . Str::random(6));
                $vehicle->condition_id = $conditions->random()->id;
                $vehicle->fuel_type_id = $fuelTypes->random()->id;
                $vehicle->transmission_id = $transmissions->random()->id;
                $vehicle->body_type_id = $bodyTypes->random()->id;
                $vehicle->color_id = $colors->random()->id;
                $vehicle->engine_capacity_d = $capacities->random()->id;
                $vehicle->drive_type_id = $driveTypes->random()->id;
                $vehicle->is_active = true;
                $vehicle->options = $selected_options;
                $vehicle->forceSave();
            } catch (\Exception $e) {
                \Log::warning('SeedVehicles: Failed to seed vehicle #' . $i . ' for ' . $tenant->country_code . ': ' . $e->getMessage());
                continue;
            }
        }
    }
}
