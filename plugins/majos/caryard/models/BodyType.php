<?php namespace Majos\Caryard\Models;

use Model;

class BodyType extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_body_types';

    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = ['name', 'slug'];

    public $rules = [
        'name' => 'required'
    ];

    public $hasMany = [
        'vehicles' => ['Majos\Caryard\Models\Vehicle']
    ];
}

