<?php namespace Majos\Caryard\Models;

use System\Models\SettingModel;

/**
 * Settings Model
 *
 * @package majos.caryard
 * @author Majos
 * 
 * Settings are stored in the system_settings table automatically
 * This model extends SettingModel for native OctoberCMS settings support
 */
class Settings extends SettingModel
{
    /**
     * @var string The database table used by the model.
     */
    protected $table = 'system_settings';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'site_name',
        'site_tagline',
        'contact_email',
        'contact_phone',
        'contact_address',
        'social_facebook',
        'social_twitter',
        'social_instagram',
        'social_youtube',
        'currency_symbol',
        'currency_code',
        'maintenance_mode',
        'maintenance_message',
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'site_name' => 'required|max:255',
        'site_tagline' => 'max:500',
        'contact_email' => 'email|max:255',
        'contact_phone' => 'max:50',
        'contact_address' => 'max:1000',
        'social_facebook' => 'url|max:500',
        'social_twitter' => 'url|max:500',
        'social_instagram' => 'url|max:500',
        'social_youtube' => 'url|max:500',
        'currency_symbol' => 'max:10',
        'currency_code' => 'max:10',
    ];

    /**
     * @var array Default values
     */
    public $attributes = [
        'site_name' => 'CarYard',
        'site_tagline' => 'Your trusted car dealership',
        'currency_symbol' => '$',
        'currency_code' => 'USD',
        'maintenance_mode' => false,
        'maintenance_message' => 'We are currently performing scheduled maintenance. We will be back shortly.',
    ];

    /**
     * Returns the setting item key used by this model
     */
    public static function getFormSessionKey()
    {
        return 'majos_caryard_settings';
    }
}
