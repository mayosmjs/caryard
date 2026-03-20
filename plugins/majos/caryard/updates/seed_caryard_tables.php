<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Color;
use Majos\Caryard\Models\DriveType;
use Majos\Caryard\Models\Condition;
use Majos\Caryard\Models\FuelType;
use Majos\Caryard\Models\Transmission;
use Majos\Caryard\Models\EngineCapacity;
use Majos\Caryard\Models\BodyType;
use Majos\Caryard\Models\Brand;
use Majos\Caryard\Models\VehicleModel;
use October\Rain\Database\Updates\Seeder;

class SeedCaryardTables extends Seeder
{
    public function run()
    {
        // Colors
        $colors = ['Red', 'Blue', 'Black', 'White', 'Silver', 'Grey', 'Green', 'Yellow', 'Brown'];
        foreach ($colors as $color) {
            Color::create(['name' => $color, 'slug' => str_slug($color), 'description' => '']);
        }

        // Drive Types
        $driveTypes = ['2WD', '4WD', 'AWD', 'FWD', 'RWD'];
        foreach ($driveTypes as $dt) {
            DriveType::create(['name' => $dt, 'slug' => str_slug($dt), 'description' => '']);
        }

        // Conditions
        $conditions = ['New', 'Used Local', 'Used Foreign'];
        foreach ($conditions as $cond) {
            Condition::create(['name' => $cond, 'slug' => str_slug($cond)]);
        }

        // Fuel Types
        $fuelTypes = ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Plug-in Hybrid'];
        foreach ($fuelTypes as $fuel) {
            FuelType::create(['name' => $fuel, 'slug' => str_slug($fuel)]);
        }

        // Transmissions
        $transmissions = ['Automatic', 'Manual', 'CVT', 'Semi-Automatic'];
        foreach ($transmissions as $trans) {
            Transmission::create(['name' => $trans, 'slug' => str_slug($trans)]);
        }

        // Engine Capacities
        $capacities = [1000, 1200, 1300, 1500, 1600, 1800, 2000, 2400, 2500, 3000, 3500, 4000];
        foreach ($capacities as $size) {
            EngineCapacity::create([
                'size' => $size,
                'slug' => $size . 'cc',
                'description' => ''
            ]);
        }

        // Body Types
        $bodyTypes = ['Sedan', 'Hatchback', 'SUV', 'Station Wagon', 'Pickup', 'Van', 'Coupe', 'Convertible'];
        foreach ($bodyTypes as $bt) {
            BodyType::create(['name' => $bt, 'slug' => str_slug($bt)]);
        }

        // Brands and Models
        $brands = [
            'Subaru' => ['Outback', 'Levorg', 'XV', 'Forester', 'Impreza', 'Legacy', 'BRZ', 'WRX'],
            'Toyota' => ['Corolla', 'Camry', 'RAV4', 'Land Cruiser', 'Hilux', 'Fortuner', 'Harrier', 'Prado', 'Mark X', 'Yaris'],
            'Honda' => ['Civic', 'Accord', 'CR-V', 'Fit', 'HR-V', 'Vezel'],
            'Nissan' => ['X-Trail', 'Note', 'Navara', 'Juke', 'Qashqai', 'Patrol', 'Serena'],
            'Mazda' => ['CX-5', 'CX-3', 'Demio', 'Axela', 'Atenza'],
            'Volkswagen' => ['Golf', 'Polo', 'Tiguan', 'Touareg', 'Passat'],
            'Mercedes-Benz' => ['C-Class', 'E-Class', 'S-Class', 'GLE', 'GLC'],
            'BMW' => ['3 Series', '5 Series', 'X3', 'X5', '1 Series'],
            'Audi' => ['A3', 'A4', 'Q5', 'Q7'],
            'Ford' => ['Ranger', 'Everest', 'Focus', 'Mustang'],
        ];

        foreach ($brands as $brandName => $models) {
            $brand = Brand::create(['name' => $brandName, 'description' => '', 'logo' => '']);
            foreach ($models as $modelName) {
                VehicleModel::create([
                    'name' => $modelName,
                    'brand_id' => $brand->id,
                    'description' => ''
                ]);
            }
        }
    }
}
