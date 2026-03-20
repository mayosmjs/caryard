<?php namespace Majos\Caryard\Models;

use Model;

class VehicleModel extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Sluggable;

    protected $slugs = ['slug' => 'name'];

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_models';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'slug', 'brand_id', 'description'];

    public $rules = [
        'name' => 'required',
        'brand_id' => 'required'
    ];

    public $belongsTo = [
        'brand' => ['Majos\Caryard\Models\Brand']
    ];

    public $hasMany = [
        'vehicles' => ['Majos\Caryard\Models\Vehicle']
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
