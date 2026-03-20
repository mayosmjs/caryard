<?php namespace Majos\Location\Models;

use Model;

class Province extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    public $table = 'majos_location_provinces';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'code', 'country_id'];

    public $rules = [
        'name' => 'required',
        'country_id' => 'required',
    ];

    public $belongsTo = [
        'country' => ['Majos\Location\Models\Country']
    ];

    public $hasMany = [
        'cities' => ['Majos\Location\Models\City']
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
