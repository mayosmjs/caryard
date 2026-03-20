<?php namespace Majos\Location\Models;

use Model;

class Country extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    public $table = 'majos_location_countries';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'code', 'is_active'];

    public $rules = [
        'name' => 'required',
    ];

    public $hasMany = [
        'provinces' => ['Majos\Location\Models\Province']
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
