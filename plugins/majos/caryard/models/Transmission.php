<?php namespace Majos\Caryard\Models;

use Model;

class Transmission extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_transmissions';

    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = ['name', 'slug', 'description'];

    public $rules = [
        'name' => 'required'
    ];

    public $hasMany = [
        'vehicles' => ['Majos\Caryard\Models\Vehicle']
    ];
}
