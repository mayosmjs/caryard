<?php namespace Majos\Caryard\Models;

use Model;

class Vehicle extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Purgeable;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_vehicles';

    protected $purgeable = [];

    protected $jsonable = ['options'];

    protected $fillable = [
        'tenant_id', 'brand_id', 'model_id', 'division_id',
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
        'options'           => 'required',
    ];

    public $belongsTo = [
        'tenant' => ['Majos\Caryard\Models\Tenant'],
        'brand' => ['Majos\Caryard\Models\Brand'],
        'vehicle_model' => ['Majos\Caryard\Models\VehicleModel', 'key' => 'model_id'],
        'division' => ['Majos\Caryard\Models\AdministrativeDivision', 'key' => 'division_id'],
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

    /**
     * Scope to only show active listings (subscription not expired)
     */
    public function scopeActiveListings($query)
    {
        return $query->where('is_active', true)
            ->whereHas('sellerProfile', function($subQuery) {
                $subQuery->whereHas('subscriptions', function($subsQuery) {
                    $subsQuery->whereIn('status', ['active', 'trialing'])
                        ->where('expires_at', '>', now());
                });
            });
    }

    /**
     * Scope to include all vehicles (including expired subscriptions)
     */
    public function scopeAllListings($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get seller profile relation
     */
    public function sellerProfile()
    {
        return $this->belongsTo('Majos\Sellers\Models\SellerProfile', 'seller_id', 'user_id');
    }

    /**
     * Get division options filtered by tenant (for backend form dropdowns).
     */
    public function getDivisionIdOptions($value, $formData)
    {
        $tenantId = array_get($formData, 'tenant_id', $this->tenant_id);
        if (!$tenantId) return [];

        $maxLevel = DivisionType::maxLevel($tenantId);

        return AdministrativeDivision::where('tenant_id', $tenantId)
            ->where('level', $maxLevel)
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($div) {
                $path = $div->full_path;
                return [$div->id => $path];
            })
            ->toArray();
    }

    /**
     * Location breadcrumb: "Nairobi County → Westlands"
     */
    public function getDivisionPathAttribute()
    {
        return $this->division ? $this->division->full_path : null;
    }

    /**
     * Masked VIN: shows first 4 and last 4 characters, masks the rest.
     * e.g. "JTDKN3DU5A0123456" → "JTDK*********3456"
     */
    public function getMaskedVinAttribute()
    {
        $vin = $this->vin_id;
        if (!$vin || strlen($vin) <= 8) {
            return $vin;
        }

        $start = substr($vin, 0, 4);
        $end = substr($vin, -4);
        $masked = str_repeat('*', strlen($vin) - 8);

        return $start . $masked . $end;
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

    /**
     * Check if financing is enabled for this vehicle.
     * Returns true only if BOTH tenant AND seller have loan enabled.
     * 
     * @return bool
     */
    public function isFinancingEnabled()
    {
        // Check tenant-level loan settings
        $tenantLoanEnabled = $this->tenant ? $this->tenant->isLoanEnabled() : true;
        
        // Check seller-level loan settings
        $sellerLoanEnabled = false;
        if ($this->seller_id) {
            $sellerSettings = \Majos\Caryard\Models\SellerLoanSettings::where('user_id', $this->seller_id)->first();
            $sellerLoanEnabled = $sellerSettings ? $sellerSettings->isEnabled() : false;
        }
        
        // Loan is enabled only if both tenant AND seller have it enabled
        return $tenantLoanEnabled && $sellerLoanEnabled;
    }

    /**
     * Returns all options grouped by category, with each item's enabled state.
     * Used by the vehicle detail page template.
     */
    public function getCategorizedOptionsAttribute()
    {
        $allOptions = static::getCategorizedOptions();

        // options is saved as a flat array of keys: ["power_tailgate", "apple_carplay", ...]
        $raw = $this->getAttributeValue('options');
        if (is_string($raw)) {
            $vehicleOptions = json_decode($raw, true) ?: [];
        } elseif (is_array($raw)) {
            $vehicleOptions = $raw;
        } else {
            $vehicleOptions = [];
        }

        // Normalise: could be ["key1","key2"] or {"key1":true,"key2":true}
        if (array_keys($vehicleOptions) === range(0, count($vehicleOptions) - 1)) {
            // Indexed array — values are the option keys
            $enabledKeys = array_flip($vehicleOptions);
        } else {
            // Associative array — keys with truthy values
            $enabledKeys = array_flip(array_keys(array_filter($vehicleOptions, function ($v) {
                return filter_var($v, FILTER_VALIDATE_BOOLEAN);
            })));
        }

        $result = [];

        foreach ($allOptions as $category => $items) {
            $categoryItems = [];
            foreach ($items as $key => $label) {
                $categoryItems[] = [
                    'key'     => $key,
                    'label'   => $label,
                    'enabled' => isset($enabledKeys[$key]),
                ];
            }
            $result[$category] = $categoryItems;
        }

        return $result;
    }
}
