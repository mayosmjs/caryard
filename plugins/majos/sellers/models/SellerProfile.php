<?php namespace Majos\Sellers\Models;

use Model;

class SellerProfile extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'majos_sellers_profiles';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'tenant_id', 'company_name', 'phone_number',
        'identification_type', 'identification_number', 'tax_id',
        'address', 'country_id', 'province_id', 'city_id', 'is_verified_seller'
    ];

    public $rules = [
        'user_id' => 'required',
        'tenant_id' => 'required',
    ];

    public $belongsTo = [
        'user' => ['RainLab\User\Models\User'],
        'tenant' => ['Majos\Caryard\Models\Tenant'],
        'country' => ['Majos\Location\Models\Country', 'key' => 'country_id'],
        'province' => ['Majos\Location\Models\Province', 'key' => 'province_id'],
        'city' => ['Majos\Location\Models\City', 'key' => 'city_id'],
    ];

    public function getProvinceIdOptions($value, $formData)
    {
        $countryId = isset($formData['country']) ? $formData['country'] : null;
        if (!$countryId) return [];
        return \Majos\Location\Models\Province::where('country_id', $countryId)->lists('name', 'id');
    }

    public function getCityIdOptions($value, $formData)
    {
        $provinceId = isset($formData['province_id']) ? $formData['province_id'] : null;
        if (!$provinceId) return [];
        return \Majos\Location\Models\City::where('province_id', $provinceId)->lists('name', 'id');
    }

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
