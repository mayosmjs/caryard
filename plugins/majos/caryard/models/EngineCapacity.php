<?php namespace Majos\Caryard\Models;

use Model;

class EngineCapacity extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_engine_capacities';

    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = ['size', 'slug', 'description'];

    public $rules = [
        'size' => 'required|numeric',
        'slug' => 'required|unique:majos_caryard_engine_capacities',
    ];
}
