<?php namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\Vehicle;
use Majos\Caryard\Models\Tenant;
use Session;

class VehicleDetail extends ComponentBase
{
    public $vehicle;
    public $tenant;

    public function componentDetails()
    {
        return [
            'name'        => 'Vehicle Detail',
            'description' => 'Displays the full details of a single vehicle.',
        ];
    }

    public function defineProperties()
    {
        return [
            'slug' => [
                'title'   => 'Vehicle Slug',
                'default' => '{{ :slug }}',
            ],
            'tenant' => [
                'title'   => 'Tenant Slug',
                'default' => '{{ :tenant }}',
            ],
        ];
    }

    public function onRun()
    {
        $this->vehicle = $this->loadVehicle();
        $this->tenant  = $this->resolveTenant();

        if (!$this->vehicle) {
            return $this->controller->run('404');
        }

        // ── Enforce Tenant-Vehicle Consistency ───────────────────────
        // If the URL tenant (e.g. /UG/) doesn't match the vehicle's tenant (e.g. KE)
        // we must redirect to the correct regional URL.
        if ($this->tenant && $this->vehicle->tenant_id != $this->tenant->id) {
            $correctTenant = $this->vehicle->tenant; // Loaded via relation in loadVehicle
            if ($correctTenant) {
                $targetCC   = strtoupper($correctTenant->country_code);
                $urlSlug    = $this->property('tenant');
                $currentUrl = request()->fullUrl();

                if ($urlSlug) {
                    $newUrl = str_replace('/' . $urlSlug . '/', '/' . $targetCC . '/', $currentUrl);
                } else {
                    $uri = request()->getRequestUri();
                    $newUrl = url($targetCC . $uri);
                }

                return \Redirect::to($newUrl)->send();
            }
        }

        $this->page['vehicle'] = $this->vehicle;
        $this->page['tenant']  = $this->tenant;
    }

    protected function loadVehicle()
    {
        $slug = $this->property('slug');
        return Vehicle::where('slug', $slug)
            ->where('is_active', true)
            ->with(['brand', 'vehicle_model', 'condition', 'fuel_type', 'transmission',
                    'body_type', 'color', 'drive_type', 'division', 'images'])
            ->first();
    }

    protected function resolveTenant()
    {
        $urlSlug = $this->property('tenant');

        // 1. Resolve from URL
        if ($urlSlug) {
            $this->tenant = Tenant::where('slug', $urlSlug)
                ->orWhere('country_code', strtoupper($urlSlug))
                ->first();
        }

        // 2. Resolve from Session
        if (!$this->tenant && $tenantId = Session::get('caryard_tenant_id')) {
            $this->tenant = Tenant::find($tenantId);
        }

        // 3. Fallback to GeoIP
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

            // Case A: Missing tenant segment
            if (!$urlSlug) {
                $uri = request()->getRequestUri();
                if ($uri !== '/') {
                    $newUrl = url($targetCC . $uri);
                    return \Redirect::to($newUrl)->send();
                }
            }

            // Case B: Non-standard tenant
            if ($urlSlug && $urlSlug !== $targetCC) {
                $newUrl = str_replace('/' . $urlSlug . '/', '/' . $targetCC . '/', $currentUrl);
                if ($newUrl !== $currentUrl) {
                    return \Redirect::to($newUrl)->send();
                }
            }
        }

        return $this->tenant;
    }

    protected function detectTenantByGeoIp(): ?Tenant
    {
        try {
            $ip       = request()->ip();
            $cacheKey = "geoip_{$ip}";

            if (in_array($ip, ['127.0.0.1', '::1'])) return null;

            if ($cc = Session::get($cacheKey)) {
                return Tenant::where('country_code', $cc)->where('is_active', true)->first();
            }

            $resp = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode", false, stream_context_create(['http' => ['timeout' => 2]]));
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
}
