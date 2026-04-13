<?php namespace Majos\Caryard\Models;

use Model;

class Brand extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_brands';

    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = ['name', 'slug', 'description', 'logo', 'popular'];

    public $rules = [
        'name' => 'required'
    ];

    public $hasMany = [
        'vehicle_models' => ['Majos\Caryard\Models\VehicleModel'],
        'vehicles' => ['Majos\Caryard\Models\Vehicle']
    ];

    public $attachOne = [
        'logo_file' => ['System\Models\File', 'public' => true]
    ];


}
