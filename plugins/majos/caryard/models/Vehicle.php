<?php namespace Majos\Caryard\Models;

use Model;

class Vehicle extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Purgeable;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_vehicles';

    protected $purgeable = ['country', 'province'];

    protected $jsonable = ['options'];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'brand_id', 'model_id', 'location_id',
        'vin_id', 'vehicleid', 'title', 'slug', 'year',
        'price', 'mileage', 'condition_id', 'fuel_type_id',
        'transmission_id', 'body_type_id', 'color_id',
        'engine_capacity_d', 'drive_type_id', 'is_active', 'seller_id', 'options'
    ];

    public $rules = [
        'title'             => 'required',
        'slug'              => 'required|unique:majos_caryard_vehicles',
        'year'              => 'required|date',
        'price'             => 'required|numeric|min:0',
        'mileage'           => 'required|numeric|min:0',
        'vin_id'            => 'required',
        'vehicleid'         => 'required',
        'brand_id'          => 'required',
        'model_id'          => 'required',
        'condition_id'      => 'required',
        'body_type_id'      => 'required',
        'color_id'          => 'required',
        'fuel_type_id'      => 'required',
        'transmission_id'   => 'required',
        'engine_capacity_d' => 'required',
        'drive_type_id'     => 'required',
        'tenant_id'         => 'required',
        'seller_id'         => 'required',
        'location_id'       => 'required',
        'options'           => 'required',
    ];

    public $belongsTo = [
        'tenant' => ['Majos\Caryard\Models\Tenant'],
        'brand' => ['Majos\Caryard\Models\Brand'],
        'vehicle_model' => ['Majos\Caryard\Models\VehicleModel', 'key' => 'model_id'],
        'location' => ['Majos\Location\Models\City'],
        'condition' => ['Majos\Caryard\Models\Condition'],
        'fuel_type' => ['Majos\Caryard\Models\FuelType'],
        'transmission' => ['Majos\Caryard\Models\Transmission'],
        'body_type' => ['Majos\Caryard\Models\BodyType'],
        'color' => ['Majos\Caryard\Models\Color'],
        'engine_capacity' => ['Majos\Caryard\Models\EngineCapacity', 'key' => 'engine_capacity_d'],
        'drive_type' => ['Majos\Caryard\Models\DriveType'],
        'seller' => ['RainLab\User\Models\User', 'key' => 'seller_id'],
    ];

    public $hasMany = [
        'favorites' => ['Majos\Caryard\Models\Favorite']
    ];

    public $attachMany = [
        'images' => ['System\Models\File', 'public' => true]
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }

    public function getCountryOptions()
    {
        return \Majos\Location\Models\Country::lists('name', 'id');
    }

    public function getProvinceOptions($value, $formData)
    {
        $countryId = isset($formData['country']) ? $formData['country'] : null;
        if (!$countryId) return [];
        return \Majos\Location\Models\Province::where('country_id', $countryId)->lists('name', 'id');
    }

    public function getLocationIdOptions($value, $formData)
    {
        $provinceId = isset($formData['province']) ? $formData['province'] : null;
        if (!$provinceId) return [];
        return \Majos\Location\Models\City::where('province_id', $provinceId)->lists('name', 'id');
    }

    public function getBrandIdOptions()
    {
        return \Majos\Caryard\Models\Brand::lists('name', 'id');
    }

    public function getModelIdOptions($value, $formData)
    {
        $brandId = isset($formData['brand_id']) ? $formData['brand_id'] : $this->brand_id;

        if (!$brandId) {
            return [];
        }

        return \Majos\Caryard\Models\VehicleModel::where('brand_id', $brandId)->lists('name', 'id');
    }

    public static function getCategorizedOptions()
    {
        return [
            'Safety & Security' => [
                'blind_spot' => 'Blind Spot Monitoring',
                'lane_departure' => 'Lane Departure Warning',
                'lane_keep' => 'Lane Keep Assist',
                'adaptive_cruise' => 'Adaptive Cruise Control',
                'night_vision' => 'Night Vision System',
                'heads_up' => 'Heads-Up Display (HUD)',
                'parking_sensors' => 'Parking Sensors (F/R)',
                'camera_360' => '360° Surround Camera',
                'isofix' => 'ISOFIX Child Seat Mounts',
                'brake_assist' => 'Brake Assist',
                'stability_control' => 'Electronic Stability Control (ESC)',
                'traction_control' => 'Traction Control',
                'tyre_pressure' => 'Tyre Pressure Monitoring',
                'alarm' => 'Alarm System',
                'immobilizer' => 'Engine Immobilizer'
            ],
            'Comfort & Convenience' => [
                'climate_dual' => 'Dual-Zone Climate Control',
                'climate_tri' => 'Tri-Zone Climate Control',
                'heated_seats_f' => 'Heated Seats (Front)',
                'heated_seats_r' => 'Heated Seats (Rear)',
                'ventilated_seats' => 'Ventilated/Cooled Seats',
                'memory_seats' => 'Memory Driver Seat',
                'power_seats' => 'Power Adjustable Seats',
                'heated_wheel' => 'Heated Steering Wheel',
                'keyless_entry' => 'Keyless Entry & Start',
                'remote_start' => 'Remote Engine Start',
                'adaptive_suspension' => 'Adaptive/Air Suspension',
                'drive_modes' => 'Selectable Drive Modes',
                'paddle_shifters' => 'Paddle Shifters',
                'power_tailgate' => 'Power Tailgate/Trunk',
                'soft_close' => 'Soft Close Doors'
            ],
            'Technology & Multimedia' => [
                'apple_carplay' => 'Apple CarPlay',
                'android_auto' => 'Android Auto',
                'wireless_charging' => 'Wireless Phone Charging',
                'wifi_hotspot' => 'Built-in Wi-Fi Hotspot',
                'bluetooth' => 'Bluetooth Connectivity',
                'usb_c' => 'USB-C Charging Ports',
                'premium_audio' => 'Premium Surround Sound',
                'navigation' => 'Satellite Navigation System',
                'voice_control' => 'Voice Command System',
                'rear_entertainment' => 'Rear Seat Entertainment Screens',
                'digital_cockpit' => 'Full Digital Instrument Cluster'
            ],
            'Interior & Exterior' => [
                'sunroof_panoramic' => 'Panoramic Sunroof',
                'sunroof_standard' => 'Standard Sunroof',
                'led_headlights' => 'LED/Matrix Headlights',
                'adaptive_lights' => 'Adaptive High Beam',
                'rain_sensors' => 'Rain Sensing Wipers',
                'folding_mirrors' => 'Power Folding Mirrors',
                'dimming_mirrors' => 'Auto-Dimming Mirrors',
                'tinted_windows' => 'Factory Tinted Windows',
                'leather_interior' => 'Full Leather Interior',
                'alcantara' => 'Alcantara/Suede Accents',
                'ambient_lighting' => 'Multi-color Ambient Lighting',
                'roof_rails' => 'Roof Rails',
                'tow_hitch' => 'Tow Hitch/Bar',
                'side_steps' => 'Side Steps/Running Boards'
            ],
            'History & Selling Points' => [
                'one_owner' => 'One Owner',
                'full_history' => 'Full Service History',
                'no_accident' => 'No Accident History',
                'new_tyres' => 'Brand New Tyres',
                'non_smoker' => 'Non-Smoker Vehicle',
                'warranty' => 'Balance of Factory Warranty',
                'low_mileage' => 'Low Mileage Certified',
                'mint' => 'Showroom/Mint Condition'
            ]
        ];
    }
}
