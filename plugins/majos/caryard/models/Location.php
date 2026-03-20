<?php namespace Majos\Caryard\Models;

use Model;

class Location extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $table = 'majos_caryard_locations';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['tenant_id', 'name', 'slug', 'parent_id', 'type'];

    public $rules = [
        'name' => 'required'
    ];

    public $belongsTo = [
        'tenant' => ['Majos\Caryard\Models\Tenant'],
        'parent' => ['Majos\Caryard\Models\Location', 'key' => 'parent_id']
    ];

    public $hasMany = [
        'children' => ['Majos\Caryard\Models\Location', 'key' => 'parent_id'],
        'vehicles' => ['Majos\Caryard\Models\Vehicle']
    ];

    public function beforeCreate()
    {
        if (empty($this->id)) {
            $this->id = (string) \Str::uuid();
        }
    }
}
