<?php namespace Majos\Location\Models;

use Model;

class City extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    public $table = 'majos_location_cities';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'province_id'];

    public $rules = [
        'name' => 'required',
        'province_id' => 'required',
    ];

    public $belongsTo = [
        'province' => ['Majos\Location\Models\Province']
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
