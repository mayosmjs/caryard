<?php namespace Majos\Caryard\Models;

use Model;

/**
 * Self-referencing hierarchical model for all administrative divisions.
 *
 * Level 1 = Top-level division (State / Province / County / Region)
 * Level 2 = Sub-division (City / Town / Sub-county)
 * Level 3+ = Optional deeper nesting (Ward / Village)
 *
 * Each division belongs to a Tenant (which represents a country).
 * The human-readable name for each level comes from DivisionType.
 */
class AdministrativeDivision extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sluggable;

    public $table = 'majos_caryard_admin_divisions';

    protected $slugs = ['slug' => 'name'];

    protected $fillable = [
        'tenant_id', 'parent_id', 'level', 'name', 'slug', 'code', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'level'      => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public $rules = [
        'tenant_id' => 'required',
        'level'     => 'required|integer|min:1',
        'name'      => 'required|string|max:255',
    ];

    /* ── Relationships ───────────────────────────────── */

    public $belongsTo = [
        'tenant' => [Tenant::class],
        'parent' => [self::class, 'key' => 'parent_id'],
    ];

    public $hasMany = [
        'children' => [self::class, 'key' => 'parent_id', 'order' => 'sort_order asc, name asc'],
        'vehicles' => [Vehicle::class, 'key' => 'division_id'],
    ];

    /* ── Scopes ──────────────────────────────────────── */

    /**
     * Top-level divisions for a tenant (level 1, no parent).
     */
    public function scopeRoots($query, $tenantId = null)
    {
        $query->whereNull('parent_id')->where('level', 1);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        return $query;
    }

    /**
     * Filter by tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Filter by level.
     */
    public function scopeAtLevel($query, $level)
    {
        return $query->where('level', (int) $level);
    }

    /**
     * Only active divisions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /* ── Accessors ───────────────────────────────────── */

    /**
     * The human-readable type label for this division (e.g. "County", "Town").
     */
    public function getTypeLabelAttribute()
    {
        return DivisionType::labelFor($this->tenant_id, $this->level);
    }

    /**
     * Full path: "Nairobi County → Westlands"
     */
    public function getFullPathAttribute()
    {
        $parts = [];
        $node = $this;
        while ($node) {
            array_unshift($parts, $node->name);
            $node = $node->parent;
        }
        return implode(' → ', $parts);
    }

    /* ── Helpers ─────────────────────────────────────── */

    /**
     * Get all ancestors from root to immediate parent.
     */
    public function getAncestors()
    {
        $ancestors = collect();
        $node = $this->parent;
        while ($node) {
            $ancestors->prepend($node);
            $node = $node->parent;
        }
        return $ancestors;
    }

    /**
     * Get all descendants (recursive).
     */
    public function getDescendants()
    {
        $descendants = collect();
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }
        return $descendants;
    }

    /**
     * Get children as a dropdown list [id => name].
     */
    public function getChildrenOptions()
    {
        return $this->children()
            ->active()
            ->orderBy('name')
            ->lists('name', 'id');
    }

    /**
     * Static helper: get level-1 divisions for a tenant as dropdown options.
     */
    public static function getRootOptions($tenantId)
    {
        return static::roots($tenantId)
            ->active()
            ->orderBy('name')
            ->lists('name', 'id');
    }

    /**
     * Static helper: get children of a parent as dropdown options.
     */
    public static function getChildOptions($parentId)
    {
        return static::where('parent_id', (int) $parentId)
            ->active()
            ->orderBy('name')
            ->lists('name', 'id');
    }

    /* ── Backend form dropdown ───────────────────────── */

    /**
     * Dropdown options for parent_id field in backend forms.
     * Filters by the currently selected tenant_id.
     */
    public function getParentIdOptions($value, $formData)
    {
        $tenantId = array_get($formData, 'tenant_id', $this->tenant_id);
        if (!$tenantId) return [];

        $query = static::where('tenant_id', $tenantId)
            ->orderBy('level')
            ->orderBy('name');

        if ($this->id) {
            $query->where('id', '!=', $this->id);
        }

        return $query->get()->mapWithKeys(function ($div) {
            $prefix = str_repeat('— ', $div->level - 1);
            return [$div->id => $prefix . $div->name . ' (L' . $div->level . ')'];
        })->toArray();
    }

    public function getTenantIdOptions()
    {
        return Tenant::where('is_active', true)->lists('name', 'id');
    }

    /* ── Events ──────────────────────────────────────── */

    public function beforeCreate()
    {
        // Auto-set level based on parent
        if ($this->parent_id && !$this->level) {
            $parent = static::find($this->parent_id);
            $this->level = $parent ? $parent->level + 1 : 1;
        }

        // Inherit tenant_id from parent if not set
        if ($this->parent_id && !$this->tenant_id) {
            $parent = static::find($this->parent_id);
            if ($parent) {
                $this->tenant_id = $parent->tenant_id;
            }
        }
    }
}