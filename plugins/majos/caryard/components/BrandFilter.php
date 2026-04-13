<?php

namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\Brand;

class BrandFilter extends ComponentBase
{
    public $brands;

    public function componentDetails()
    {
        return [
            'name'        => 'Brand Filter',
            'description' => 'Display brand logos/links with optional limit',
        ];
    }

    public function defineProperties()
    {
        return [
            'limit' => [
                'title'       => 'Brand Limit',
                'description' => 'Number of brands to display (leave empty for all)',
                'type'        => 'string',
                'default'     => '',
            ],
            'orderBy' => [
                'title'       => 'Order By',
                'description' => 'Order brands by field',
                'type'        => 'dropdown',
                'default'     => 'name',
                'options'     => [
                    'name'    => 'Name',
                    'popular' => 'Popular',
                ],
            ],
            'page' => [
                'title'       => 'Buy Page',
                'description' => 'Page to link to when clicking a brand',
                'type'        => 'dropdown',
                'default'     => 'buy',
            ],
        ];
    }

    public function getPageOptions()
    {
        return \Cms\Classes\Page::lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $limit = $this->property('limit');
        $orderBy = $this->property('orderBy', 'name');

        $query = Brand::query();

     

        if ($limit) {
            $query->limit((int) $limit);
        }

        $this->brands = $query->get();
        $this->page['brands'] = $this->brands;
    }
}
