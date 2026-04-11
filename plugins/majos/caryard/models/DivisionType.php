<?php namespace Majos\Caryard\Models;

use Model;

/**
 * Maps each tenant's administrative level to a human-readable label.
 *
 * Examples:
 *   tenant_id=<kenya-uuid>, level=1, label="County",   label_plural="Counties"
 *   tenant_id=<kenya-uuid>, level=2, label="Town",     label_plural="Towns"
 *   tenant_id=<usa-uuid>,   level=1, label="State",    label_plural="States"
 */
class DivisionType extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'majos_caryard_division_types';

    protected $fillable = ['tenant_id', 'level', 'label', 'label_plural'];

    public $rules = [
        'tenant_id' => 'required',
        'level'     => 'required|integer|min:1',
        'label'     => 'required|string|max:255',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public function getTenantIdOptions()
    {
        return Tenant::where('is_active', true)->lists('name', 'id');
    }

    /**
     * Get all division types for a tenant, keyed by level.
     */
    public static function forTenant($tenantId)
    {
        return static::where('tenant_id', $tenantId)
            ->orderBy('level')
            ->get()
            ->keyBy('level');
    }

    /**
     * Get the label for a specific tenant + level.
     */
    public static function labelFor($tenantId, $level)
    {
        $type = static::where('tenant_id', $tenantId)
            ->where('level', $level)
            ->first();

        return $type ? $type->label : "Level {$level}";
    }

    /**
     * Get the max depth configured for a tenant.
     */
    public static function maxLevel($tenantId)
    {
        return (int) static::where('tenant_id', $tenantId)->max('level') ?: 1;
    }
}