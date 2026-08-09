<?php namespace Majos\Conference\Models;

use Model;
use Backend\Auth\User;

/**
 * PaperSubmission Model
 */
class PaperSubmission extends Model
{
    use \October\Rain\Database\Traits\Validation;
    
    public $table = 'majos_conference_paper_submissions';

    public $rules = [
        'email'             => 'required|email|max:255',
        'full_name'         => 'required|string|min:2|max:150',
        'phone_number'      => 'required|string|min:5|max:30',
        'country'           => 'required|string|min:2|max:100',
        'original_filename' => 'required|string|max:255',
        'stored_filename'   => 'required|string|max:255',
        'reference_number'  => 'required|string|max:20|unique:majos_conference_paper_submissions',
        'submission_date'   => 'required|date',
        'status'            => 'required|in:pending,verified,completed,rejected',
        'verified_at'       => 'required|date',
    ];

    public $dates = [
        'verified_at',
        'submission_date',
        'created_at',
        'updated_at'
    ];

    // Relationships
    public $belongsTo = [
        'user' => 'Backend\Models\User'
    ];
}