<?php namespace Majos\Caryard\Models;

use Model;

class Tenant extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    public $table = 'majos_caryard_tenants';
    public $timestamps = false;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'country_code', 'currency', 'locale', 'is_active'];

    public $rules = [
        'name' => 'required'
    ];

    public $hasMany = [
        'locations' => ['Majos\Caryard\Models\Location'],
        'vehicles' => ['Majos\Caryard\Models\Vehicle']
    ];

    public $attachOne = [
        'logo' => ['System\Models\File', 'public' => true]
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
