<?php namespace Majos\Conference\Classes;

class SpeakerPopupData
{
    public static function make($speaker, $defaultImage = '/plugins/majos/conference/assets/images/default-speaker.svg')
    {
        if (!$speaker) {
            return [];
        }

        return [
            'full_name' => (string) $speaker->full_name,
            'title' => (string) $speaker->title,
            'company' => (string) $speaker->company,
            'country' => (string) $speaker->country,
            'biography' => (string) $speaker->biography,
            'email' => (string) $speaker->email,
            'linkedin_url' => (string) $speaker->linkedin_url,
            'website_url' => (string) $speaker->website_url,
            'is_featured' => (bool) $speaker->is_featured,
            'expertise' => static::getExpertise($speaker),
            'image_url' => static::getImageUrl($speaker, $defaultImage),
        ];
    }

    public static function encode($speaker, $defaultImage = '/plugins/majos/conference/assets/images/default-speaker.svg')
    {
        return json_encode(
            static::make($speaker, $defaultImage),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );
    }

    public static function getImageUrl($speaker, $defaultImage = '/plugins/majos/conference/assets/images/default-speaker.svg')
    {
        if ($speaker && $speaker->photo_file) {
            $url = $speaker->photo_file->getThumb(700, 700, ['mode' => 'crop']);
            return url($url);
        }

        return url($defaultImage);
    }

    public static function getExpertise($speaker)
    {
        if (!$speaker || !$speaker->expertise) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $speaker->expertise))));
    }
}
