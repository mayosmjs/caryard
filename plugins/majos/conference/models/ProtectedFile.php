<?php namespace Majos\Conference\Models;

use Model;
use Backend\Auth\User;
use System\Models\File;

/**
 * PaperSubmission Model
 */

class ProtectedFile extends File
{
    /**
     * Store files in storage/app/uploads/protected/submissions
     */
    public $storagePath = 'uploads/protected/submissions';
}