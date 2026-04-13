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
use Illuminate\Support\Str;
use System\Models\File;

class SeedCaryardTables extends Seeder
{
    public function run()
    {
        $this->seedLookupTables();
        $this->seedBrandsAndModels();
    }

    protected function seedLookupTables()
    {
        $colors = ['Red', 'Blue', 'Black', 'White', 'Silver', 'Grey', 'Green', 'Yellow', 'Brown'];
        foreach ($colors as $color) {
            Color::firstOrCreate(['slug' => str_slug($color)], ['name' => $color, 'description' => '']);
        }

        $driveTypes = ['2WD', '4WD', 'AWD', 'FWD', 'RWD'];
        foreach ($driveTypes as $dt) {
            DriveType::firstOrCreate(['slug' => str_slug($dt)], ['name' => $dt, 'description' => '']);
        }

        $conditions = ['New', 'Used Local', 'Used Foreign'];
        foreach ($conditions as $cond) {
            Condition::firstOrCreate(['slug' => str_slug($cond)], ['name' => $cond]);
        }

        $fuelTypes = ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Plug-in Hybrid'];
        foreach ($fuelTypes as $fuel) {
            FuelType::firstOrCreate(['slug' => str_slug($fuel)], ['name' => $fuel]);
        }

        $transmissions = ['Automatic', 'Manual', 'CVT', 'Semi-Automatic'];
        foreach ($transmissions as $trans) {
            Transmission::firstOrCreate(['slug' => str_slug($trans)], ['name' => $trans]);
        }

        $capacities = [1000, 1200, 1300, 1500, 1600, 1800, 2000, 2400, 2500, 3000, 3500, 4000];
        foreach ($capacities as $size) {
            EngineCapacity::firstOrCreate(['slug' => $size . 'cc'], ['size' => $size, 'description' => '']);
        }

        $bodyTypes = ['Sedan', 'Hatchback', 'SUV', 'Station Wagon', 'Pickup', 'Van', 'Coupe', 'Convertible'];
        foreach ($bodyTypes as $bt) {
            BodyType::firstOrCreate(['slug' => str_slug($bt)], ['name' => $bt]);
        }
    }

    protected function seedBrandsAndModels()
    {
        $brandsWithModels = [
            ['name' => 'Alfa Romeo', 'logo' => 'AlfaRomeo.webp', 'popular' => false, 'models' => ['Giulia', 'Stelvio', 'Tonale', '4C']],
            ['name' => 'Aston Martin', 'logo' => 'AstonMartin.webp', 'popular' => false, 'models' => ['DB11', 'DBX', 'Vantage', 'DBS']],
            ['name' => 'Bentley', 'logo' => 'Bentley.webp', 'popular' => false, 'models' => ['Continental', 'Bentayga', 'Flying Spur', 'Mulsanne']],
            ['name' => 'BMW', 'logo' => 'BMW.webp', 'popular' => true, 'models' => ['3 Series', '5 Series', '7 Series', 'X3', 'X5', 'X7', 'M3', 'M5']],
            ['name' => 'Bugatti', 'logo' => 'Bugatti.webp', 'popular' => false, 'models' => ['Chiron', 'Veyron', 'Divo', 'Centodieci']],
            ['name' => 'Cadillac', 'logo' => 'Cadillac.webp', 'popular' => false, 'models' => ['Escalade', 'CT5', 'CT4', 'XT5', 'XT6']],
            ['name' => 'Chevrolet', 'logo' => 'Chevrolet.webp', 'popular' => true, 'models' => ['Silverado', 'Equinox', 'Tahoe', 'Corvette', 'Camaro', 'Malibu']],
            ['name' => 'Citroen', 'logo' => 'Citroen.webp', 'popular' => false, 'models' => ['C3', 'C4', 'C5 Aircross', 'Berlingo', 'DS3']],
            ['name' => 'Cupra', 'logo' => 'Cupra.webp', 'popular' => false, 'models' => ['Formentor', 'Leon', 'Ateca', 'Born']],
            ['name' => 'Ferrari', 'logo' => 'Ferrari.webp', 'popular' => false, 'models' => ['F8 Tributo', 'Roma', 'SF90 Stradale', '296 GTB', '812 Superfast']],
            ['name' => 'Fiat', 'logo' => 'Fiat.webp', 'popular' => true, 'models' => ['Panda', '500', 'Tipo', 'Punto', 'Duke', 'Fastback']],
            ['name' => 'Ford', 'logo' => 'Ford.webp', 'popular' => true, 'models' => ['Ranger', 'Everest', 'Mustang', 'Explorer', 'Bronco', 'F-150', 'Focus']],
            ['name' => 'Honda', 'logo' => 'Honda.webp', 'popular' => true, 'models' => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Pilot', 'Odyssey', 'Civic Type R']],
            ['name' => 'Hyundai', 'logo' => 'Hyundai2.webp', 'popular' => true, 'models' => ['Tucson', 'Santa Fe', 'Creta', 'Ioniq 5', 'Sonata', 'Elantra']],
            ['name' => 'Iveco', 'logo' => 'Iveco.webp', 'popular' => false, 'models' => ['Daily', 'Eurocargo', 'Stralis', 'Trakker']],
            ['name' => 'Jeep', 'logo' => 'Jeep.webp', 'popular' => true, 'models' => ['Wrangler', 'Grand Cherokee', 'Cherokee', 'Compass', 'Renegade', 'Gladiator']],
            ['name' => 'Kia', 'logo' => 'Kia.webp', 'popular' => true, 'models' => ['Sportage', 'Seltos', 'Telluride', 'Carnival', 'EV6', 'Sorento']],
            ['name' => 'Lamborghini', 'logo' => 'Lamborghini.webp', 'popular' => false, 'models' => ['Huracan', 'Urus', 'Aventador', 'Revuelto']],
            ['name' => 'Land Rover', 'logo' => 'LandRover.webp', 'popular' => true, 'models' => ['Range Rover', 'Discovery', 'Defender', 'Range Rover Sport', 'Range Rover Evoque']],
            ['name' => 'Lexus', 'logo' => 'Lexus.webp', 'popular' => true, 'models' => ['RX', 'NX', 'ES', 'LS', 'GX', 'LX', 'IS', 'LC']],
            ['name' => 'MAN', 'logo' => 'MAN.webp', 'popular' => false, 'models' => ['TGX', 'TGM', 'TGS', 'TGL', 'L2000']],
            ['name' => 'Mazda', 'logo' => 'Mazda.webp', 'popular' => true, 'models' => ['CX-5', 'CX-3', 'CX-9', 'Mazda3', 'Mazda6', 'MX-5']],
            ['name' => 'McLaren', 'logo' => 'McLaren.webp', 'popular' => false, 'models' => ['720S', '750S', 'Artura', 'GT', 'P1', 'Senna']],
            ['name' => 'Mercedes-Benz', 'logo' => 'Mercedes.webp', 'popular' => true, 'models' => ['C-Class', 'E-Class', 'S-Class', 'GLC', 'GLE', 'GLS', 'EQS', 'AMG GT']],
            ['name' => 'Mini', 'logo' => 'Mini.webp', 'popular' => false, 'models' => ['Cooper', 'Clubman', 'Countryman', 'Convertible', 'John Cooper Works']],
            ['name' => 'Mitsubishi', 'logo' => 'Mitsubishi.webp', 'popular' => true, 'models' => ['Outlander', 'Pajero', 'L200', 'Eclipse Cross', 'ASX', 'Xpander']],
            ['name' => 'Nissan', 'logo' => 'Nissan.webp', 'popular' => true, 'models' => ['X-Trail', 'Qashqai', 'Navara', 'Patrol', 'Z', 'Ariya', 'Leaf']],
            ['name' => 'Peugeot', 'logo' => 'Peugeot4.webp', 'popular' => true, 'models' => ['208', '308', '3008', '5008', '508', 'Partner', 'Rifter']],
            ['name' => 'Porsche', 'logo' => 'Porsche.webp', 'popular' => true, 'models' => ['911', 'Cayenne', 'Macan', 'Panamera', 'Taycan', 'Boxster', 'Cayman']],
            ['name' => 'Rolls-Royce', 'logo' => 'RollsRoyce.webp', 'popular' => false, 'models' => ['Phantom', 'Ghost', 'Wraith', 'Cullinan', 'Spectre']],
            ['name' => 'SEAT', 'logo' => 'Seat.webp', 'popular' => false, 'models' => ['Leon', 'Ateca', 'Arona', 'Ibiza', 'Tarraco']],
            ['name' => 'Subaru', 'logo' => 'Subaru.webp', 'popular' => true, 'models' => ['Outback', 'Forester', 'XV', 'Impreza', 'WRX', 'Legacy', 'BRZ', 'Crosstrek']],
            ['name' => 'Suzuki', 'logo' => 'Suzuki.webp', 'popular' => true, 'models' => ['Swift', 'Vitara', 'Jimny', 'S-Cross', 'Baleno', 'Ciaz', 'Ertiga']],
            ['name' => 'Tesla', 'logo' => 'Tesla.webp', 'popular' => true, 'models' => ['Model 3', 'Model Y', 'Model S', 'Model X', 'Cybertruck']],
            ['name' => 'Togg', 'logo' => 'Togg.webp', 'popular' => false, 'models' => ['T10X', 'T10S', 'T8']],
            ['name' => 'Toyota', 'logo' => 'Toyota.webp', 'popular' => true, 'models' => ['Corolla', 'Camry', 'RAV4', 'Land Cruiser', 'Hilux', 'Fortuner', 'Prado', 'Prius', 'Yaris', 'Supra', 'GR86']],
            ['name' => 'Volvo', 'logo' => 'Volvo.webp', 'popular' => true, 'models' => ['XC90', 'XC60', 'XC40', 'S90', 'S60', 'V60', 'EX30', 'EX90']],
            ['name' => 'Volkswagen', 'logo' => 'Volkswagen.webp', 'popular' => true, 'models' => ['Golf', 'Polo', 'Tiguan', 'Touareg', 'Passat', 'Arteon', 'ID.4', 'ID.Buzz']],
            ['name' => 'Isuzu', 'logo' => 'Isuzu.webp', 'popular' => true, 'models' => ['D-Max', 'MUX', 'N-Series', 'F-Series']],
            ['name' => 'Audi', 'logo' => null, 'popular' => true, 'models' => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7', 'e-tron', 'RS3', 'RS6']],
            ['name' => 'Chery', 'logo' => null, 'popular' => false, 'models' => ['Tiggo 8', 'Tiggo 7', 'Tiggo 5', 'Omoda 5', 'Jetour']],
            ['name' => 'Dodge', 'logo' => null, 'popular' => false, 'models' => ['Durango', 'Charger', 'Challenger', 'Ram 1500', 'Hornet']],
            ['name' => 'Geely', 'logo' => null, 'popular' => false, 'models' => ['Coolray', 'Azolla', 'Okavango', 'Tugella', 'Galaxy']],
            ['name' => 'GMC', 'logo' => null, 'popular' => false, 'models' => ['Sierra', 'Yukon', 'Canyon', 'Terrain', 'Acadia']],
            ['name' => 'Haval', 'logo' => null, 'popular' => false, 'models' => ['H6', 'Jolion', 'H9', 'Dargo', 'F7']],
            ['name' => 'Infiniti', 'logo' => null, 'popular' => false, 'models' => ['QX60', 'QX80', 'Q50', 'Q60']],
            ['name' => 'Jaguar', 'logo' => null, 'popular' => false, 'models' => ['F-Pace', 'E-Pace', 'I-Pace', 'XF', 'XE', 'F-Type']],
            ['name' => 'John Deere', 'logo' => null, 'popular' => false, 'models' => ['6R Series', '7R Series', '8R Series', '9R Series']],
            ['name' => 'Kenworth', 'logo' => null, 'popular' => false, 'models' => ['T680', 'T880', 'W990', 'T379']],
            ['name' => 'Lada', 'logo' => null, 'popular' => false, 'models' => ['Niva', 'Granta', 'Vesta', 'Largus', 'Niva Travel']],
            ['name' => 'Lincoln', 'logo' => null, 'popular' => false, 'models' => ['Navigator', 'Aviator', 'Corsair', 'Nautilus', 'MKZ']],
            ['name' => 'Mahindra', 'logo' => null, 'popular' => false, 'models' => ['Scorpio', 'XUV500', 'Thar', 'XUV300', 'Bolero']],
            ['name' => 'Opel', 'logo' => null, 'popular' => false, 'models' => ['Corsa', 'Astra', 'Mokka', 'Grandland', 'Insignia']],
            ['name' => 'Renault', 'logo' => null, 'popular' => false, 'models' => ['Duster', 'Kiger', 'Kwid', 'Triber', 'Captur', 'Arkana', 'Megane']],
            ['name' => 'Scania', 'logo' => null, 'popular' => false, 'models' => ['R-series', 'S-series', 'G-series', 'P-series']],
            ['name' => 'Skoda', 'logo' => null, 'popular' => false, 'models' => ['Octavia', 'Superb', 'Kodiaq', 'Karoq', 'Kamiq', 'Enyaq']],
            ['name' => 'SsangYong', 'logo' => null, 'popular' => false, 'models' => ['Tivoli', 'Korando', 'Rexton', 'Musso', 'Actyon']],
            ['name' => 'Toyota Trucks', 'logo' => null, 'popular' => false, 'models' => ['Tundra', 'Tacoma', 'Sequoia']],
            ['name' => 'Volkswagen Trucks', 'logo' => null, 'popular' => false, 'models' => ['Constellation', 'Delivery', 'Worker', 'Volksbus']],
        ];

        $logoPath = plugins_path('majos/caryard/updates/logos');

        foreach ($brandsWithModels as $brandData) {
            $brand = Brand::where('slug', Str::slug($brandData['name']))->first();
            
            if (!$brand) {
                $brand = Brand::create([
                    'name' => $brandData['name'],
                    'slug' => Str::slug($brandData['name']),
                    'description' => 'Quality ' . $brandData['name'] . ' vehicles',
                    'popular' => $brandData['popular'] ?? false,
                ]);
            } else {
                if (isset($brandData['popular'])) {
                    $brand->popular = $brandData['popular'];
                    $brand->save();
                }
            }

            if ($brandData['logo']) {
                $logoFile = $logoPath . '/' . $brandData['logo'];
                if (file_exists($logoFile) && !$brand->logo_file) {
                    $file = new File();
                    $file->fromFile($logoFile);
                    $file->is_public = true;
                    $file->save();
                    $brand->logo_file()->add($file);
                }
            }

            if (isset($brandData['models']) && !empty($brandData['models'])) {
                foreach ($brandData['models'] as $modelName) {
                    $existingModel = VehicleModel::where('brand_id', $brand->id)
                        ->where('slug', Str::slug($modelName))
                        ->first();
                    
                    if (!$existingModel) {
                        VehicleModel::create([
                            'name' => $modelName,
                            'slug' => Str::slug($modelName),
                            'brand_id' => $brand->id,
                            'description' => $modelName . ' by ' . $brandData['name'],
                        ]);
                    }
                }
            }
        }
    }
}
