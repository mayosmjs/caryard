<?php namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Cms\Classes\Page;
use Majos\Caryard\Models\Vehicle;
use Majos\Caryard\Models\Tenant;
use Majos\Caryard\Models\Brand;
use Majos\Caryard\Models\VehicleModel;
use Majos\Caryard\Models\Condition;
use Majos\Caryard\Models\FuelType;
use Majos\Caryard\Models\Transmission;
use Majos\Caryard\Models\BodyType;
use Majos\Caryard\Models\Color;
use Majos\Caryard\Models\DriveType;
use Majos\Location\Models\Country;
use Majos\Location\Models\Province;
use Majos\Location\Models\City;
use Session;
use Event;

class VehicleList extends ComponentBase
{
    public $searchQuery;
    public $results;
    public $filterOptions = [];
    public $tenant;
    public $detailPage;

    // ── Search Parameters (all stored as slugs for clean URLs) ───────
    public $searchTerm      = false;
    public $brandSlug        = false;
    public $modelSlug        = false;
    public $conditionSlug    = false;
    public $fuelTypeSlug    = false;
    public $transmissionSlug = false;
    public $bodyTypeSlug    = false;
    public $colorSlug       = false;
    public $driveTypeSlug   = false;
    public $countrySlug     = false;  // granular location step 1
    public $provinceSlug    = false;  // granular location step 2
    public $citySlug        = false;  // granular location step 3
    public $minPrice        = false;
    public $maxPrice        = false;
    public $yearMin         = false;
    public $yearMax         = false;
    public $engineMin       = false;
    public $engineMax       = false;
    public $orderBy         = 'latest';

    // ── Pagination ─────────────────────────────────────
    public $resultsPerPage = 5;
    public $pageNumber     = 1;

    // ─────────────────────────────────────────────────────
    public function componentDetails()
    {
        return [
            'name'        => 'Vehicle List',
            'description' => 'Advanced vehicle listing with AJAX filters, GeoIP and load-more.',
        ];
    }

    public function defineProperties()
    {
        return [
            'tenant'   => ['title' => 'Tenant Slug / Country Code', 'default' => '{{ :tenant }}'],
            'brand'    => ['title' => 'Brand Slug (URL)',    'default' => '{{ :brand }}'],
            'model'    => ['title' => 'Model Slug (URL)',    'default' => '{{ :model }}'],
            'location' => ['title' => 'City Slug (URL)',     'default' => '{{ :location }}'],
            'resultsPerPage' => ['title' => 'Results Per Page', 'default' => '5', 'validationPattern' => '^[0-9]+$'],
            'detailPage' => ['title' => 'Vehicle Detail Page', 'type' => 'dropdown', 'default' => 'vehicle/detail'],
        ];
    }

    public function getDetailPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    // ── Lifecycle ─────────────────────────────────────
    public function onRun()
    {
        $this->detailPage = $this->property('detailPage');
        $this->prepareVars();
        $this->getResults();
    }

    // ── AJAX Handlers ─────────────────────────────────

    public function onFilter()
    {
        $this->pageNumber = (int) input('page', 1);
        $this->prepareVars();
        $this->getResults();
        return ['#vehicle-list' => $this->renderPartial('@_list')];
    }

    public function onChangePage()
    {
        $this->pageNumber = (int) input('page', 1);
        $this->prepareVars();
        $this->getResults();
        return ['#vehicle-list' => $this->renderPartial('@_list')];
    }

    public function onLoadMore()
    {
        $this->pageNumber = (int) input('page', 1);
        $this->prepareVars();
        $this->getResults();
        return [
            '#vehicle-cards-inner' => $this->renderPartial('@_cards'),
            'hasMore'   => $this->results->hasMorePages(),
            'nextPage'  => $this->results->currentPage() + 1,
            'total'     => $this->results->total(),
        ];
    }

    /** Returns <option> HTML for models belonging to a brand (uses slugs as values) */
    public function onGetModels()
    {
        $brandSlug = input('brand_slug');
        $brand     = $brandSlug ? Brand::where('slug', $brandSlug)->first() : null;
        $models    = $brand
            ? VehicleModel::where('brand_id', $brand->id)->orderBy('name')->get()
            : collect();

        $html = '<option value="">All Models</option>';
        foreach ($models as $m) {
            $html .= '<option value="' . e($m->slug) . '">' . e($m->name) . '</option>';
        }

        return ['html' => $html, 'count' => $models->count()];
    }

    /** Returns <option> HTML for provinces belonging to a country (uses slugs as values) */
    public function onGetProvinces()
    {
        $countrySlug = input('country_slug');
        $country     = $countrySlug ? Country::where('slug', $countrySlug)->first() : null;
        $provinces   = $country
            ? Province::where('country_id', $country->id)->orderBy('name')->get()
            : collect();

        $html = '<option value="">All Provinces / States</option>';
        foreach ($provinces as $p) {
            $html .= '<option value="' . e($p->slug) . '">' . e($p->name) . '</option>';
        }

        return ['html' => $html, 'count' => $provinces->count()];
    }

    /** Returns <option> HTML for cities belonging to a province (uses slugs as values) */
    public function onGetCities()
    {
        $provinceSlug = input('province_slug');
        $province     = $provinceSlug ? Province::where('slug', $provinceSlug)->first() : null;
        $cities       = $province
            ? City::where('province_id', $province->id)->orderBy('name')->get()
            : collect();

        $html = '<option value="">All Cities</option>';
        foreach ($cities as $c) {
            $html .= '<option value="' . e($c->slug) . '">' . e($c->name) . '</option>';
        }

        return ['html' => $html, 'count' => $cities->count()];
    }

    // ── State Preparation ─────────────────────────────
    protected function prepareVars()
    {
        $this->resolveTenantFromUrl();
        $this->resolveUrlSegments();

        $this->resultsPerPage = (int) $this->property('resultsPerPage') ?: 5;
        $this->pageNumber     = (int) input('page', $this->pageNumber ?: 1);

        $this->searchTerm       = input('search');
        $this->brandSlug        = request()->has('brand')        ? input('brand')        : $this->brandSlug;
        $this->modelSlug        = request()->has('model')        ? input('model')        : $this->modelSlug;
        $this->conditionSlug    = request()->has('condition')    ? input('condition')    : $this->conditionSlug;
        $this->fuelTypeSlug     = request()->has('fuel_type')    ? input('fuel_type')    : $this->fuelTypeSlug;
        $this->transmissionSlug = request()->has('transmission') ? input('transmission') : $this->transmissionSlug;
        $this->bodyTypeSlug     = request()->has('body_type')    ? input('body_type')    : $this->bodyTypeSlug;
        $this->colorSlug        = request()->has('color')        ? input('color')        : $this->colorSlug;
        $this->driveTypeSlug    = request()->has('drive_type')   ? input('drive_type')   : $this->driveTypeSlug;
        $this->countrySlug      = request()->has('country')      ? input('country')      : $this->countrySlug;
        $this->provinceSlug     = request()->has('province')     ? input('province')     : $this->provinceSlug;
        $this->citySlug         = request()->has('city')         ? input('city')         : $this->citySlug;
        $this->minPrice         = request()->has('price_min')    ? input('price_min')    : $this->minPrice;
        $this->maxPrice         = request()->has('price_max')    ? input('price_max')    : $this->maxPrice;
        $this->yearMin          = request()->has('year_min')     ? input('year_min')     : $this->yearMin;
        $this->yearMax          = request()->has('year_max')     ? input('year_max')     : $this->yearMax;
        $this->engineMin        = request()->has('engine_min')   ? input('engine_min')   : $this->engineMin;
        $this->engineMax        = request()->has('engine_max')   ? input('engine_max')   : $this->engineMax;
        $this->orderBy          = input('order_by', 'latest');

        $this->filterOptions  = $this->loadFilterOptions();

        $this->page['tenant']        = $this->tenant;
        $this->page['filterOptions'] = $this->filterOptions;
        $this->page['component']     = $this;
    }

    protected function resolveTenantFromUrl()
    {
        $urlSlug = $this->property('tenant');

        // 1. Try resolving from URL
        if ($urlSlug) {
            $this->tenant = Tenant::where('slug', $urlSlug)
                ->orWhere('country_code', strtoupper($urlSlug))
                ->first();
        }

        // 2. Try session
        if (!$this->tenant && $tenantId = Session::get('caryard_tenant_id')) {
            $this->tenant = Tenant::find($tenantId);
        }

        // 3. Detect via GeoIP
        if (!$this->tenant) {
            $this->tenant = $this->detectTenantByGeoIp();
        }

        // 4. Default fallback
        if (!$this->tenant) {
            $this->tenant = Tenant::where('is_active', true)->first();
        }

        if ($this->tenant) {
            Session::put('caryard_tenant_id', $this->tenant->id);

            $currentUrl = request()->fullUrl();
            $targetCC   = strtoupper($this->tenant->country_code);

            // Case A: No tenant segment in URL (e.g. /buy-car)
            // We want to redirect /buy-car to /KE/buy-car
            if (!$urlSlug) {
                // Find where the uri starts (excluding protocol and host)
                $uri = request()->getRequestUri();
                // Ensure we don't end up in an infinite loop if the URI is "/"
                if ($uri !== '/') {
                    $newUrl = url($targetCC . $uri);
                    return \Redirect::to($newUrl)->send();
                }
            }

            // Case B: URL has a slug or non-standard code (e.g. /kenya/buy-car or /ke/buy-car)
            // We want to redirect /kenya/buy-car to /KE/buy-car
            if ($urlSlug && $urlSlug !== $targetCC) {
                $newUrl = str_replace('/' . $urlSlug . '/', '/' . $targetCC . '/', $currentUrl);
                if ($newUrl !== $currentUrl) {
                    return \Redirect::to($newUrl)->send();
                }
            }
        }
    }

    protected function detectTenantByGeoIp(): ?Tenant
    {
        try {
            $ip       = request()->ip();
            $cacheKey = "geoip_{$ip}";

            if (in_array($ip, ['127.0.0.1', '::1']) ||
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }

            if ($cc = Session::get($cacheKey)) {
                return Tenant::where('country_code', $cc)->where('is_active', true)->first();
            }

            $resp = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode", false,
                stream_context_create(['http' => ['timeout' => 2]])
            );

            if ($resp) {
                $data = json_decode($resp, true);
                if (($data['status'] ?? '') === 'success') {
                    $cc = strtoupper($data['countryCode'] ?? '');
                    Session::put($cacheKey, $cc);
                    return Tenant::where('country_code', $cc)->where('is_active', true)->first();
                }
            }
        } catch (\Exception $e) {}

        return null;
    }

    protected function resolveUrlSegments()
    {
        if ($slug = $this->property('brand')) {
            $this->brandSlug = $slug;
        }
        if ($slug = $this->property('model')) {
            $this->modelSlug = $slug;
        }
        if ($slug = $this->property('location')) {
            $this->citySlug = $slug;
        }
    }

    protected function loadFilterOptions(): array
    {
        // Resolve slugs to models for pre-populating cascades
        $brand    = $this->brandSlug   ? Brand::where('slug',    $this->brandSlug)->first()   : null;
        $country  = $this->countrySlug ? Country::where('slug',  $this->countrySlug)->first()  : null;
        $province = $this->provinceSlug? Province::where('slug', $this->provinceSlug)->first(): null;

        return [
            'brands'        => Brand::orderBy('name')->get(),
            'models'        => $brand
                                ? VehicleModel::where('brand_id', $brand->id)->orderBy('name')->get()
                                : collect(),
            'conditions'    => Condition::all(),
            'fuelTypes'     => FuelType::all(),
            'transmissions' => Transmission::all(),
            'bodyTypes'     => BodyType::all(),
            'colors'        => Color::orderBy('name')->get(),
            'driveTypes'    => DriveType::all(),
            // Granular location
            'countries'     => Country::where('is_active', true)->orderBy('name')->get(),
            'provinces'     => $country
                                ? Province::where('country_id', $country->id)->orderBy('name')->get()
                                : collect(),
            'cities'        => $province
                                ? City::where('province_id', $province->id)->orderBy('name')->get()
                                : collect(),
        ];
    }

    // ── Search Orchestrator ───────────────────────────
    public function getResults()
    {
        Event::fire('majos.caryard.beforesearch', [$this]);

        $this->initSearch()
            ->filterByTenant()
            ->filterBySearchTerm()
            ->filterByBrand()
            ->filterByModel()
            ->filterByCondition()
            ->filterByFuelType()
            ->filterByTransmission()
            ->filterByBodyType()
            ->filterByColor()
            ->filterByDriveType()
            ->filterByLocation()
            ->filterByPriceRange()
            ->filterByYearRange()
            ->filterByEngineSize()
            ->applyOrder();

        Event::fire('majos.caryard.extendsearch', [$this]);
        $this->executeSearch();
        Event::fire('majos.caryard.aftersearch', [$this]);
    }

    public function initSearch()
    {
        $this->searchQuery = Vehicle::where('is_active', true);
        return $this;
    }

    public function executeSearch()
    {
        $items = $this->searchQuery
            ->with(['brand', 'vehicle_model', 'location', 'condition'])
            ->paginate($this->resultsPerPage, ['*'], 'page', $this->pageNumber);

        $this->page['results'] = $this->results = $items;
    }

    // ── Chainable Filters (all slug-based with subqueries) ───────────
    public function filterByTenant()       { if ($this->tenant)          $this->searchQuery->where('tenant_id',       $this->tenant->id);                                                                  return $this; }
    public function filterByBrand()        { if ($this->brandSlug)       $this->searchQuery->whereHas('brand',        fn($q) => $q->where('slug', $this->brandSlug));                                    return $this; }
    public function filterByModel()        { if ($this->modelSlug)       $this->searchQuery->whereHas('vehicle_model',fn($q) => $q->where('slug', $this->modelSlug));                                   return $this; }
    public function filterByCondition()    { if ($this->conditionSlug)   $this->searchQuery->whereHas('condition',    fn($q) => $q->where('slug', $this->conditionSlug));                                return $this; }
    public function filterByFuelType()     { if ($this->fuelTypeSlug)    $this->searchQuery->whereHas('fuel_type',    fn($q) => $q->where('slug', $this->fuelTypeSlug));                                 return $this; }
    public function filterByTransmission() { if ($this->transmissionSlug)$this->searchQuery->whereHas('transmission', fn($q) => $q->where('slug', $this->transmissionSlug));                           return $this; }
    public function filterByBodyType()     { if ($this->bodyTypeSlug)    $this->searchQuery->whereHas('body_type',    fn($q) => $q->where('slug', $this->bodyTypeSlug));                                 return $this; }
    public function filterByColor()        { if ($this->colorSlug)       $this->searchQuery->whereHas('color',        fn($q) => $q->where('slug', $this->colorSlug));                                    return $this; }
    public function filterByDriveType()    { if ($this->driveTypeSlug)   $this->searchQuery->whereHas('drive_type',   fn($q) => $q->where('slug', $this->driveTypeSlug));                               return $this; }

    public function filterByLocation()
    {
        // Use most granular slug available: city > province > country
        if ($this->citySlug) {
            $this->searchQuery->whereHas('location', fn($q) => $q->where('slug', $this->citySlug));
        } elseif ($this->provinceSlug) {
            $province = Province::where('slug', $this->provinceSlug)->first();
            if ($province) {
                $cityIds = City::where('province_id', $province->id)->pluck('id');
                $this->searchQuery->whereIn('location_id', $cityIds);
            }
        } elseif ($this->countrySlug) {
            $country = Country::where('slug', $this->countrySlug)->first();
            if ($country) {
                $provIds = Province::where('country_id', $country->id)->pluck('id');
                $cityIds = City::whereIn('province_id', $provIds)->pluck('id');
                $this->searchQuery->whereIn('location_id', $cityIds);
            }
        }
        return $this;
    }

    public function filterBySearchTerm()
    {
        if ($term = $this->searchTerm) {
            $this->searchQuery->where(function ($q) use ($term) {
                $q->where('title', 'LIKE', "%{$term}%")
                  ->orWhere('vin_id', 'LIKE', "%{$term}%");
            });
        }
        return $this;
    }

    public function filterByPriceRange()
    {
        if ($this->minPrice) $this->searchQuery->where('price', '>=', (float) $this->minPrice);
        if ($this->maxPrice) $this->searchQuery->where('price', '<=', (float) $this->maxPrice);
        return $this;
    }

    public function filterByYearRange()
    {
        if ($this->yearMin) $this->searchQuery->whereYear('year', '>=', (int) $this->yearMin);
        if ($this->yearMax) $this->searchQuery->whereYear('year', '<=', (int) $this->yearMax);
        return $this;
    }

    public function filterByEngineSize()
    {
        if ($this->engineMin || $this->engineMax) {
            $this->searchQuery->whereHas('engine_capacity', function($q) {
                if ($this->engineMin) $q->where('size', '>=', (int) $this->engineMin);
                if ($this->engineMax) $q->where('size', '<=', (int) $this->engineMax);
            });
        }
        return $this;
    }

    public function applyOrder()
    {
        $map = [
            'latest'     => ['created_at', 'desc'],
            'oldest'     => ['created_at', 'asc'],
            'price_asc'  => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
        ];
        [$field, $dir] = $map[$this->orderBy] ?? ['created_at', 'desc'];
        $this->searchQuery->orderBy($field, $dir);
        return $this;
    }

    // ── View Helpers ──────────────────────────────────
    /**
     * selected('brandSlug', brand.slug) → 'selected' if the stored slug matches.
     */
    public function selected($attr, $value)
    {
        return (isset($this->$attr) && (string)$this->$attr === (string)$value) ? 'selected' : '';
    }

    public function checked($attr, $key = false)
    {
        if ($key) return (is_array($this->$attr ?? null) && in_array((string)$key, array_map('strval', $this->$attr))) ? 'checked' : '';
        return (isset($this->$attr) && $this->$attr) ? 'checked' : '';
    }

    public function activeClass($attr, $value)
    {
        return (isset($this->$attr) && (string)$this->$attr === (string)$value) ? 'active' : '';
    }
}
