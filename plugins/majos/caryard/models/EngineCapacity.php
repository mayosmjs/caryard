<?php namespace Majos\Caryard\Models;

use Model;

class EngineCapacity extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_engine_capacities';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['size', 'slug', 'description'];

    public $rules = [
        'size' => 'required|numeric',
        'slug' => 'required|unique:majos_caryard_engine_capacities',
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }

    public function __toString()
    {
        return $this->size . 'cc';
    }
}
