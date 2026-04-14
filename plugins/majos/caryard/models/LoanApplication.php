<?php namespace Majos\Caryard\Models;

use Model;

class LoanApplication extends Model
{
    public $table = 'majos_caryard_loan_applications';
    
    protected $fillable = ['application', 'tenant_id', 'vehicle_id', 'status'];
    
    protected $casts = [
        'tenant_id' => 'integer',
        'vehicle_id' => 'integer',
    ];

    
    public function getApplicationDataAttribute()
    {
        return json_decode($this->application, true);
    }
    
    public $belongsTo = [
        'tenant' => [Tenant::class],
        'vehicle' => [Vehicle::class],
    ];
}