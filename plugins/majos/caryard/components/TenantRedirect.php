<?php namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\Tenant;
use Session;
use Redirect;

class TenantRedirect extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Tenant Redirect',
            'description' => 'Detects user country via GeoIP and redirects to the appropriate market.'
        ];
    }

    public function defineProperties()
    {
        return [
            'redirectPage' => [
                'title'       => 'Redirect To Page',
                'description' => 'Where to send the user after detection (e.g. buy-car)',
                'default'     => 'buy-car',
                'type'        => 'string'
            ]
        ];
    }

    public function onRun()
    {
        $tenant = $this->resolveTenant();
        
        if ($tenant) {
            $page = $this->property('redirectPage') ?: 'buy-car';
            // Construct URL like /KE/buy-car
            return Redirect::to('/' . strtoupper($tenant->country_code) . '/' . ltrim($page, '/'));
        }
    }

    protected function resolveTenant()
    {
        // 1. Try session
        if ($tenantId = Session::get('caryard_tenant_id')) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) return $tenant;
        }

        // 2. Detect via GeoIP
        $tenant = $this->detectTenantByGeoIp();
        
        // 3. Fallback to default
        if (!$tenant) {
            $tenant = Tenant::where('is_active', true)->first();
        }

        if ($tenant) {
            Session::put('caryard_tenant_id', $tenant->id);
        }

        return $tenant;
    }

    protected function detectTenantByGeoIp(): ?Tenant
    {
        try {
            $ip       = request()->ip();
            $cacheKey = "geoip_{$ip}";

            // Skip for localhost
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
