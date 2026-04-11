<?php namespace Majos\Caryard\Models;

use Model;

class Favorite extends Model
{
    public $table = 'majos_caryard_favorites';
    public $timestamps = false;
    public $incrementing = true;

    protected $fillable = ['vehicle_id', 'user_id'];

    public $belongsTo = [
        'vehicle' => ['Majos\Caryard\Models\Vehicle']
        // user relation implies user model which usually from RainLab.User
    ];
}
