<?php namespace Majos\Conference\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class PresentationType extends Model
{
    use Validation;

    public $table = 'majos_conference_presentation_types';

    public $rules = [
        'name' => 'required|string|max:255|unique:majos_conference_presentation_types',
    ];

    protected $fillable = ['name', 'description'];
}
