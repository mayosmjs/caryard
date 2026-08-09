<?php namespace Majos\Conference\Components\Concerns;

use Majos\Conference\Classes\SpeakerPopupData;

trait InteractsWithSpeakerPopup
{
    public function getSpeakerPopupData($speaker)
    {
        return SpeakerPopupData::encode($speaker);
    }

    public function getSpeakerImageUrl($speaker)
    {
        return SpeakerPopupData::getImageUrl($speaker);
    }

    public function getSpeakerExpertiseArray($speaker)
    {
        return SpeakerPopupData::getExpertise($speaker);
    }
}
