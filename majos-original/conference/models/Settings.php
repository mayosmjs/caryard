<?php namespace Majos\Conference\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Settings extends Model
{
    use Validation;

    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'majos_conference_settings';

    public $settingsFields = 'fields.yaml';

    public $rules = [
        'submission_deadline' => 'required|date',
        'max_file_size_kb' => 'required|numeric|min:100|max:20480',
        'turnstile_site_key' => 'nullable|string|max:255',
        'turnstile_secret_key' => 'nullable|string|max:255',
        'unique_email' => 'required|boolean',
        'token_expiry_days' => 'required|numeric|min:1|max:365',
        'notification_email' => 'nullable|email',
    ];

    protected $jsonable = [];

    protected $dates = ['submission_deadline'];

    public function initSettingsData()
    {
        return [
            'submission_deadline' => '2027-06-30 23:59:59',
            'max_file_size_kb' => 5120,
            'turnstile_site_key' => '',
            'turnstile_secret_key' => '',
            'unique_email' => true,
            'token_expiry_days' => 30,
            'notification_email' => '',
        ];
    }
}
