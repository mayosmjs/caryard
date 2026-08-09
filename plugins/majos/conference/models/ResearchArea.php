<?php namespace Majos\Conference\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class ResearchArea extends Model
{
    use Validation;

    public $table = 'majos_conference_research_areas';

    public $rules = [
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:20',
        'sort_order' => 'nullable|integer|min:0',
    ];

    protected $fillable = ['parent_id', 'name', 'code', 'description', 'sort_order'];

    public $belongsTo = [
        'parent' => [self::class, 'key' => 'parent_id'],
    ];

    public $hasMany = [
        'children' => [self::class, 'key' => 'parent_id', 'order' => 'sort_order'],
    ];

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function getLabelAttribute()
    {
        return trim(($this->code ? $this->code . '. ' : '') . $this->name);
    }

    /**
     * rootLabels returns the display labels of the top-level research areas.
     */
    public static function rootLabels(): array
    {
        return static::roots()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn($area) => $area->label)
            ->values()
            ->all();
    }

    /**
     * topicLabels returns the display labels of every child research topic.
     */
    public static function topicLabels(): array
    {
        return static::whereNotNull('parent_id')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn($topic) => $topic->label)
            ->values()
            ->all();
    }

    /**
     * topicsByArea returns a map of top-level area label => child topic labels.
     */
    public static function topicsByArea(): array
    {
        $map = [];

        foreach (static::with('parent')->whereNotNull('parent_id')->orderBy('sort_order')->orderBy('id')->get() as $topic) {
            $areaLabel = $topic->parent ? $topic->parent->label : null;

            if ($areaLabel) {
                $map[$areaLabel][] = $topic->label;
            }
        }

        return $map;
    }

    /**
     * topicBelongsToArea verifies that the given topic label belongs to the
     * given top-level area label.
     */
    public static function topicBelongsToArea(string $topicLabel, string $areaLabel): bool
    {
        return in_array($topicLabel, static::topicsByArea()[$areaLabel] ?? [], true);
    }

    /**
     * getParentOptions is used by the backend form to list possible parents.
     */
    public function getParentOptions()
    {
        return static::roots()->orderBy('sort_order')->orderBy('id')->get()
            ->mapWithKeys(fn($area) => [$area->id => $area->label])
            ->all();
    }
}
