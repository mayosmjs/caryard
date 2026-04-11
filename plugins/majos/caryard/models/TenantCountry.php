<?php namespace Majos\Caryard\Models;

use Model;

/**
 * Links a Tenant to the countries it operates in.
 * A tenant can serve multiple countries; one is marked is_primary.
 */
class TenantCountry extends Model
{
    public $table = 'majos_caryard_tenant_countries';

    protected $fillable = ['tenant_id', 'country_code', 'is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];
}