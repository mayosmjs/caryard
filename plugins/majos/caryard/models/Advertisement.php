<?php namespace Majos\Caryard\Models;

use Model;

/**
 * Advertisement Model
 */
class Advertisement extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'majos_caryard_advertisements';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'title' => 'required|max:255',
        'tenant' => 'required',
        'image' => 'required',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'tenant' => ['Majos\Caryard\Models\Tenant']
    ];

    public $attachOne = [];

    /**
     * Scope for active advertisements
     */
    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get tenant options for dropdown
     */
    public function getTenantOptions()
    {
        return Tenant::where('is_active', true)->lists('name', 'id');
    }
}
