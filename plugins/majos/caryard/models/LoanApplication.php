<?php namespace Majos\Caryard\Models;

use Model;

class LoanApplication extends Model
{
    public $table = 'majos_caryard_loan_applications';
    
    protected $fillable = ['application', 'tenant_id'];
    
    protected $casts = [
        'tenant_id' => 'integer',
    ];
    
    public function getApplicationDataAttribute()
    {
        return json_decode($this->application, true);
    }
    
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}