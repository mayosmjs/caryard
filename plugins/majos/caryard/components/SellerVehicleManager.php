<?php namespace Majos\Caryard\Components;

use Cms\Classes\ComponentBase;
use Majos\Caryard\Models\Vehicle;
use RainLab\User\Models\User;
use Auth;
use Flash;
use Validator;
use ValidationException;
use Exception;

class SellerVehicleManager extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Seller Vehicle Manager',
            'description' => 'Allows sellers to manage (CRUD) their vehicles and view statistics.'
        ];
    }

    public function defineProperties()
    {
        return [
            'perPage' => [
                'title'       => 'Items per page',
                'description' => 'Number of vehicles to load at a time',
                'default'     => 10,
                'type'        => 'string',
                'validationPattern' => '^[0-9]+$',
                'validationMessage' => 'Per page must be a number',
            ],
        ];
    }

    public function onRun()
    {
        $this->addCss('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        $this->prepareVars();
    }

    protected function prepareVars()
    {
        $perPage = (int) $this->property('perPage', 10);
        $this->page['perPage'] = $perPage;
        $this->page['stats'] = $this->getStats();
        $this->page['vehicles'] = $this->listVehicles(1, $perPage);
        $this->page['currentPage'] = 1;
        $this->page['hasMore'] = $this->page['vehicles']->count() >= $perPage;
        $this->page['user'] = Auth::getUser();
        $this->page['filterBrands'] = \Majos\Caryard\Models\Brand::orderBy('name')->lists('name', 'id');
        $this->page['filterConditions'] = \Majos\Caryard\Models\Condition::lists('name', 'id');
        $this->page['filterStatuses'] = ['1' => 'Active', '0' => 'Inactive'];
        $this->page['filters'] = [];
        $this->page['deletedVehicles'] = $this->getDeletedVehicles();
        
        // Get subscription info for the seller
        $user = Auth::getUser();
        if ($user) {
            $sellerProfile = \Majos\Sellers\Models\SellerProfile::where('user_id', $user->id)->first();
            if ($sellerProfile) {
                $subscriptionService = new \Majos\Sellers\Classes\SubscriptionService();
                $subscription = $subscriptionService->getActiveSubscription($sellerProfile);
                $this->page['subscriptionStatus'] = $subscription ? $subscription->status : null;
                $this->page['subscriptionActive'] = $subscription ? $subscription->isActive() : false;
                
                // Get vehicle limit
                $currentCount = \Majos\Caryard\Models\Vehicle::where('seller_id', $user->id)->count();
                $canAddMore = $subscriptionService->canAddVehicle($sellerProfile, $currentCount);
                $vehicleLimit = $subscriptionService->getVehicleLimit($sellerProfile);
                
                $this->page['canAddVehicle'] = $canAddMore;
                $this->page['vehicleLimit'] = $vehicleLimit;
                $this->page['currentVehicleCount'] = $currentCount;
                
                // Get tenant name
                $tenant = $sellerProfile->tenant;
                $this->page['tenantName'] = $tenant ? $tenant->name : '';
            } else {
                $this->page['subscriptionStatus'] = null;
                $this->page['subscriptionActive'] = false;
                $this->page['canAddVehicle'] = false;
                $this->page['vehicleLimit'] = 0;
                $this->page['currentVehicleCount'] = 0;
            }
        }
    }

    public function listVehicles($page = 1, $perPage = 10, $filters = [])
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return collect([]);

        $query = Vehicle::where('seller_id', $user->id);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('vehicleid', 'like', "%{$search}%")
                  ->orWhere('vin_id', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (!empty($filters['condition_id'])) {
            $query->where('condition_id', $filters['condition_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (int) $filters['is_active']);
        }

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', (float) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', (float) $filters['price_max']);
        }

        if (!empty($filters['year_from'])) {
            $query->where('year', '>=', $filters['year_from']);
        }
        if (!empty($filters['year_to'])) {
            $query->where('year', '<=', $filters['year_to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
    }

    public function getStats()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return [];
        // Assuming status/origin are columns, adjust according to your actual DB schema
        $query = Vehicle::where('seller_id', $user->id);
        
        return [
            'active' => (clone $query)->where('is_active', 1)->count(),
            'inactive' => (clone $query)->where('is_active', 0)->count(),
            'total' => $query->count(),
        ];
    }

    public function onFilterVehicles()
    {
        $perPage = (int) $this->property('perPage', 10);
        $filters = post('filter', []);
        $vehicles = $this->listVehicles(1, $perPage, $filters);

        return [
            '#vehicle-list' => $this->renderPartial('@_list', [
                'vehicles'    => $vehicles,
                'currentPage' => 1,
                'perPage'     => $perPage,
                'hasMore'     => $vehicles->count() >= $perPage,
                'filters'     => $filters,
                'filterBrands'     => \Majos\Caryard\Models\Brand::orderBy('name')->lists('name', 'id'),
                'filterConditions' => \Majos\Caryard\Models\Condition::lists('name', 'id'),
                'filterStatuses'   => ['1' => 'Active', '0' => 'Inactive'],
                'deletedVehicles'   => $this->getDeletedVehicles(),
            ])
        ];
    }

    public function onLoadMore()
    {
        $perPage = (int) $this->property('perPage', 10);
        $page = (int) post('page', 2);
        $filters = post('filter', []);
        $vehicles = $this->listVehicles($page, $perPage, $filters);

        return [
            '@#vehicle-table-body' => $this->renderPartial('@_list_rows', [
                'vehicles' => $vehicles,
            ]),
            '@#vehicle-card-list' => $this->renderPartial('@_list_cards', [
                'vehicles' => $vehicles,
            ]),
            '#load-more-wrapper' => $this->renderPartial('@_load_more_button', [
                'currentPage' => $page,
                'perPage'     => $perPage,
                'hasMore'     => $vehicles->count() >= $perPage,
                'filters'     => $filters,
            ]),
        ];
    }

 public function onLoadForm()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        $vehicleId = post('id');
        $vehicle = null;

        if ($vehicleId) {
            $vehicle = Vehicle::where('id', $vehicleId)->where('seller_id', $user->id)->first();
            if (!$vehicle) {
                throw new Exception('Vehicle not found or access denied.');
            }
        }

        // Pre-load dependent dropdowns when editing an existing vehicle
        $models = [];
        $divisionTypes = collect();
        $rootDivisions = [];
        $divisionAncestors = [];
        $divisionLevelOptions = []; // [level => [id => name]] for pre-populating all dropdowns

        if ($vehicle) {
            if ($vehicle->brand_id) {
                $models = \Majos\Caryard\Models\VehicleModel::where('brand_id', $vehicle->brand_id)
                    ->orderBy('name')
                    ->lists('name', 'id');
            }

            // Load division hierarchy for pre-selecting cascading dropdowns
            if ($vehicle->division_id && $vehicle->division) {
                $div = $vehicle->division;
                $tenantId = $div->tenant_id;
                $divisionTypes = \Majos\Caryard\Models\DivisionType::forTenant($tenantId);
                $rootDivisions = \Majos\Caryard\Models\AdministrativeDivision::getRootOptions($tenantId);

                // Build ancestors: [level => id]
                $node = $div;
                while ($node) {
                    $divisionAncestors[$node->level] = $node->id;
                    $node = $node->parent;
                }
                ksort($divisionAncestors);

                // Build options for each level
                $divisionLevelOptions[1] = $rootDivisions;
                foreach ($divisionAncestors as $lvl => $ancestorId) {
                    $nextLvl = $lvl + 1;
                    if ($divisionTypes->has($nextLvl)) {
                        $divisionLevelOptions[$nextLvl] = \Majos\Caryard\Models\AdministrativeDivision::where('parent_id', $ancestorId)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    }
                }
            }
        }

        // Get tenant for division types — from seller_profile
        $tenantId = null;
        if ($user->seller_profile && $user->seller_profile->tenant_id) {
            $tenant = \Majos\Caryard\Models\Tenant::find($user->seller_profile->tenant_id);
            if ($tenant) {
                $tenantId = $tenant->id;
                if ($divisionTypes->isEmpty()) {
                    $divisionTypes = \Majos\Caryard\Models\DivisionType::forTenant($tenantId);
                    $rootDivisions = \Majos\Caryard\Models\AdministrativeDivision::getRootOptions($tenantId);
                    $divisionLevelOptions[1] = $rootDivisions;
                }
            }
        }

        // Only pass static lists for small dropdowns to save overhead
        return [
            '#vehicle-modal-container' => $this->renderPartial('@_modal', [
                'vehicle' => $vehicle,
                'brands' => \Majos\Caryard\Models\Brand::orderBy('name')->lists('name', 'id'),
                'models' => $models,
                'conditions' => \Majos\Caryard\Models\Condition::lists('name', 'id'),
                'bodyTypes' => \Majos\Caryard\Models\BodyType::lists('name', 'id'),
                'colors' => \Majos\Caryard\Models\Color::lists('name', 'id'),
                'fuelTypes' => \Majos\Caryard\Models\FuelType::lists('name', 'id'),
                'transmissions' => \Majos\Caryard\Models\Transmission::lists('name', 'id'),
                'engineCapacities' => \Majos\Caryard\Models\EngineCapacity::lists('size', 'id'),
                'driveTypes' => \Majos\Caryard\Models\DriveType::lists('name', 'id'),
                'categorizedOptions' => \Majos\Caryard\Models\Vehicle::getCategorizedOptions(),
                'divisionTypes'        => $divisionTypes,
                'rootDivisions'        => $rootDivisions,
                'divisionAncestors'    => $divisionAncestors,
                'divisionLevelOptions' => $divisionLevelOptions,
                'tenantId'             => $tenantId,
                'tenantCurrency'       => $tenant ? $tenant->currency : 'USD',
            ])
        ];
    }

    public function onCreateVehicle()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        try {
            // Check subscription status and vehicle limit
            $subscriptionService = new \Majos\Sellers\Classes\SubscriptionService();
            $sellerProfile = \Majos\Sellers\Models\SellerProfile::where('user_id', $user->id)->first();
            
            if ($sellerProfile) {
                // Get current vehicle count
                $currentCount = Vehicle::where('seller_id', $user->id)->count();
                
                // Check if seller can add more vehicles
                if (!$subscriptionService->canAddVehicle($sellerProfile, $currentCount)) {
                    $plan = $subscriptionService->getCurrentPlan($sellerProfile);
                    $limit = $plan ? $plan->vehicle_limit : 0;
                    
                    if ($limit > 0) {
                        Flash::error("You have reached your vehicle limit of {$limit}. Please upgrade your subscription to add more vehicles.");
                    } else {
                        Flash::error('You need an active subscription to list vehicles. Please subscribe first.');
                    }
                    return;
                }
            }

            $data = post();

            $rules = [
                'title'             => 'required|min:5|max:100',
                'price'             => 'required|numeric|min:0',
                'mileage'           => 'numeric|min:0',
                'year'              => 'date',
                'brand_id'          => 'required|exists:majos_caryard_brands,id',
                'model_id'          => 'required|exists:majos_caryard_models,id',
                'condition_id'      => 'exists:majos_caryard_conditions,id',
                'body_type_id'      => 'exists:majos_caryard_body_types,id',
                'color_id'          => 'exists:majos_caryard_colors,id',
                'fuel_type_id'      => 'exists:majos_caryard_fuel_types,id',
                'transmission_id'   => 'exists:majos_caryard_transmissions,id',
                'engine_capacity_d' => 'exists:majos_caryard_engine_capacities,id',
                'drive_type_id'     => 'exists:majos_caryard_drive_types,id',
                'vin_id'            => 'unique:majos_caryard_vehicles,vin_id',
                'vehicleid'         => 'unique:majos_caryard_vehicles,vehicleid',
            ];

            $validation = Validator::make($data, $rules);
            if ($validation->fails()) {
                throw new ValidationException($validation);
            }

            $vehicle = new Vehicle();
            
            // Map All Fields (BOPLA Protection)
            $fields = [
                'title', 'price', 'year', 'mileage', 'brand_id', 'model_id', 
                'condition_id', 'body_type_id', 'color_id', 'fuel_type_id', 
                'transmission_id', 'engine_capacity_d', 'drive_type_id', 
                'vin_id', 'vehicleid', 'division_id'
            ];

            foreach ($fields as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $vehicle->{$field} = $data[$field];
                }
            }

            $vehicle->options   = $data['options'] ?? [];
            $vehicle->is_active = isset($data['is_active']) ? 1 : 0;

            $vehicle->save();

            // Handle Image Uploads
            if (\Input::hasFile('images')) {
                foreach (\Input::file('images') as $file) {
                    if ($file) $vehicle->images()->create(['data' => $file]);
                }
            }

            Flash::success('Vehicle added successfully!');
        } catch (Exception $ex) {
            if ($ex instanceof ValidationException) throw $ex;
            Flash::error($ex->getMessage());
            return;
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats'),
            '#vehicle-modal-container' => '',
            '#deleted-vehicles-section' => $this->renderPartial('@_deleted_vehicles', [
                'deletedVehicles' => $this->getDeletedVehicles()
            ])
        ];
    }

    public function onUpdateVehicle()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;
        
        try {
            // OWASP BOLA: Ensure vehicle belongs to seller
            $vehicle = Vehicle::where('id', post('id'))->where('seller_id', $user->id)->first();
            if (!$vehicle) {
                throw new Exception('Access denied or vehicle not found.');
            }

            $data = post();

            $rules = [
                'title'             => 'required|min:5|max:100',
                'price'             => 'required|numeric|min:0',
                'mileage'           => 'numeric|min:0',
                'year'              => 'date',
                'brand_id'          => 'required|exists:majos_caryard_brands,id',
                'model_id'          => 'required|exists:majos_caryard_models,id',
                'condition_id'      => 'exists:majos_caryard_conditions,id',
                'body_type_id'      => 'exists:majos_caryard_body_types,id',
                'color_id'          => 'exists:majos_caryard_colors,id',
                'fuel_type_id'      => 'exists:majos_caryard_fuel_types,id',
                'transmission_id'   => 'exists:majos_caryard_transmissions,id',
                'engine_capacity_d' => 'exists:majos_caryard_engine_capacities,id',
                'drive_type_id'     => 'exists:majos_caryard_drive_types,id',
                'vin_id'            => 'unique:majos_caryard_vehicles,vin_id,' . $vehicle->id,
                'vehicleid'         => 'unique:majos_caryard_vehicles,vehicleid,' . $vehicle->id,
            ];

            $validation = Validator::make($data, $rules);
            if ($validation->fails()) {
                throw new ValidationException($validation);
            }

            // Map All Fields (BOPLA Protection)
            $fields = [
                'title', 'price', 'year', 'mileage', 'brand_id', 'model_id', 
                'condition_id', 'body_type_id', 'color_id', 'fuel_type_id', 
                'transmission_id', 'engine_capacity_d', 'drive_type_id', 
                'vin_id', 'vehicleid', 'division_id'
            ];

            foreach ($fields as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $vehicle->{$field} = $data[$field];
                }
            }

            $vehicle->options   = $data['options'] ?? [];
            $vehicle->is_active = isset($data['is_active']) ? 1 : 0;

            $vehicle->save();

            // Handle Image Uploads
            if (\Input::hasFile('images')) {
                foreach (\Input::file('images') as $file) {
                    if ($file) $vehicle->images()->create(['data' => $file]);
                }
            }

            Flash::success('Vehicle updated successfully!');
        } catch (Exception $ex) {
            if ($ex instanceof ValidationException) throw $ex;
            \Log::error('Vehicle update error: ' . $ex->getMessage());
            Flash::error($ex->getMessage());
            return;
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats'),
            '#vehicle-modal-container' => ''
        ];
    }

    public function onDeleteVehicle()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        try {
            // OWASP BOLA is ensured by checking seller_id matches user->id
            $vehicle = Vehicle::where('id', post('id'))->where('seller_id', $user->id)->first();
            if ($vehicle) {
                $vehicle->delete(); // Soft delete (sets deleted_at)
                Flash::success('Vehicle moved to trash. You can restore it later.');
            } else {
                throw new Exception('Vehicle not found or access denied.');
            }
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats'),
            '#deleted-vehicles-section' => $this->renderPartial('@_deleted_vehicles', [
                'deletedVehicles' => $this->getDeletedVehicles()
            ])
        ];
    }

    /**
     * AJAX: Delete multiple vehicles (soft delete).
     */
    public function onDeleteMultipleVehicles()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        try {
            $vehicleIds = post('vehicle_ids', []);
            
            if (empty($vehicleIds)) {
                throw new Exception('No vehicles selected for deletion.');
            }

            if (!is_array($vehicleIds)) {
                $vehicleIds = [$vehicleIds];
            }

            // Validate and delete only vehicles belonging to this seller
            $vehicles = Vehicle::where('seller_id', $user->id)
                ->whereIn('id', $vehicleIds)
                ->get();
            
            $count = 0;
            foreach ($vehicles as $vehicle) {
                $vehicle->delete();
                $count++;
            }

            Flash::success($count . ' vehicle(s) deleted successfully. You can restore them from the trash.');
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats'),
            '#deleted-vehicles-section' => $this->renderPartial('@_deleted_vehicles', [
                'deletedVehicles' => $this->getDeletedVehicles()
            ])
        ];
    }

    /**
     * AJAX: Restore multiple deleted vehicles.
     */
    public function onRestoreMultipleVehicles()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        try {
            $vehicleIds = post('vehicle_ids', []);
            
            if (empty($vehicleIds)) {
                throw new Exception('No vehicles selected for restoration.');
            }

            if (!is_array($vehicleIds)) {
                $vehicleIds = [$vehicleIds];
            }

            // Restore only vehicles belonging to this seller (include trashed)
            $count = Vehicle::withTrashed()
                ->where('seller_id', $user->id)
                ->whereIn('id', $vehicleIds)
                ->update(['deleted_at' => null]);

            Flash::success($count . ' vehicle(s) restored successfully.');
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats'),
            '#deleted-vehicles-section' => $this->renderPartial('@_deleted_vehicles', [
                'deletedVehicles' => $this->getDeletedVehicles()
            ])
        ];
    }

    /**
     * AJAX: Permanently delete multiple vehicles.
     */
    public function onPermanentlyDeleteVehicles()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        try {
            $vehicleIds = post('vehicle_ids', []);
            
            if (empty($vehicleIds)) {
                throw new Exception('No vehicles selected for permanent deletion.');
            }

            if (!is_array($vehicleIds)) {
                $vehicleIds = [$vehicleIds];
            }

            // Permanently delete only vehicles belonging to this seller (include trashed)
            $count = Vehicle::withTrashed()
                ->where('seller_id', $user->id)
                ->whereIn('id', $vehicleIds)
                ->forceDelete();

            Flash::success($count . ' vehicle(s) permanently deleted.');
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats'),
            '#deleted-vehicles-section' => $this->renderPartial('@_deleted_vehicles', [
                'deletedVehicles' => $this->getDeletedVehicles()
            ])
        ];
    }

    /**
     * Get deleted vehicles for the current seller.
     */
    public function getDeletedVehicles()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return collect([]);

        return Vehicle::with(['brand', 'vehicle_model'])
            ->where('seller_id', $user->id)
            ->onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->get();
    }

    /**
     * AJAX: Change status (active/inactive) for multiple vehicles.
     */
    public function onChangeMultipleVehicleStatus()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) return;

        try {
            $vehicleIds = post('vehicle_ids', []);
            $status = post('status', '');
            
            if (empty($vehicleIds)) {
                throw new Exception('No vehicles selected for status change.');
            }

            if (!is_array($vehicleIds)) {
                $vehicleIds = [$vehicleIds];
            }

            // Validate status
            if ($status !== '0' && $status !== '1') {
                throw new Exception('Invalid status value. Use 0 for inactive/draft or 1 for active.');
            }

            // Update only vehicles belonging to this seller
            $count = Vehicle::where('seller_id', $user->id)
                ->whereIn('id', $vehicleIds)
                ->update(['is_active' => (int) $status]);

            $statusText = $status == '1' ? 'active' : 'inactive/draft';
            Flash::success($count . ' vehicle(s) marked as ' . $statusText . '.');
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        $this->prepareVars();
        return [
            '#vehicle-list' => $this->renderPartial('@_list'),
            '#vehicle-stats' => $this->renderPartial('@_stats')
        ];
    }

    public function onSearchBrands()
    {
        $q = post('q', '');
        return [
            'results' => \Majos\Caryard\Models\Brand::where('name', 'like', "%$q%")
                ->take(200)
                ->get()
                ->map(function($i){ 
                    return [
                        'id' => $i->id, 
                        'text' => $i->name,
                        'logo' => $i->logo_file ? $i->logo_file->getThumb(100, 100, ['mode' => 'crop']) : null
                    ]; 
                })->toArray()
        ];
    }

    public function onSearchModels()
    {
        $q = post('q', '');
        $brand_id = post('brand_id');
        $query = \Majos\Caryard\Models\VehicleModel::where('name', 'like', "%$q%");
        if ($brand_id) $query->where('brand_id', $brand_id);
        return [
            'results' => $query->take(200)->get()->map(function($i){ return ['id'=>$i->id, 'text'=>$i->name]; })->toArray()
        ];
    }

    /** Returns HTML <option> list for models filtered by brand_id */
    public function onLoadModels()
    {
        $brand_id = post('brand_id');
        $models = [];
        if ($brand_id) {
            $models = \Majos\Caryard\Models\VehicleModel::where('brand_id', $brand_id)
                ->orderBy('name')
                ->lists('name', 'id');
        }
        return [
            '#modelSelect' => $this->renderPartial('@_model_options', ['models' => $models])
        ];
    }

    /**
     * AJAX: Cascading division dropdown — works like onLoadModels.
     * Finds which level was changed, loads children, and replaces the next <select>.
     */
    public function onLoadDivisionChildren()
    {
        $user = Auth::getUser();
        $tenantId = ($user && $user->seller_profile) ? $user->seller_profile->tenant_id : null;
        $divisionTypes = $tenantId
            ? \Majos\Caryard\Models\DivisionType::forTenant($tenantId)
            : collect();
        $maxLevel = $divisionTypes->count();

        // Find which level was posted and get its value
        $result = [];
        for ($level = 1; $level <= $maxLevel; $level++) {
            $parentId = post('division_level_' . $level);
            if ($parentId && $level < $maxLevel) {
                $nextLevel = $level + 1;
                $children = \Majos\Caryard\Models\AdministrativeDivision::where('parent_id', (int)$parentId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray();

                $label = $divisionTypes->has($nextLevel) ? $divisionTypes[$nextLevel]->label : 'Option';

                $result['#divisionLevel' . $nextLevel] = $this->renderPartial('@_division_options', [
                    'options'    => $children,
                    'label'      => $label,
                    'selectedId' => null,
                ]);

                // Clear all levels deeper than the next
                for ($clear = $nextLevel + 1; $clear <= $maxLevel; $clear++) {
                    $clearLabel = $divisionTypes->has($clear) ? $divisionTypes[$clear]->label : 'Option';
                    $result['#divisionLevel' . $clear] = $this->renderPartial('@_division_options', [
                        'options'    => [],
                        'label'      => $clearLabel,
                        'selectedId' => null,
                    ]);
                }
                break;
            }
        }

        return $result;
    }

    /* ═══════════════════════════════════════════════════
     *  Administrative Division Cascading Handlers
     * ═══════════════════════════════════════════════════ */

    /**
     * AJAX: Get the division type labels for a tenant (what each level is called).
     * Returns e.g. {1: {label: "County", label_plural: "Counties"}, 2: {label: "Town", ...}}
     */
    public function onLoadDivisionTypes()
    {
        $tenantId = post('tenant_id', '');
        if (!$tenantId) return ['types' => [], 'maxLevel' => 0];

        $types = \Majos\Caryard\Models\DivisionType::forTenant($tenantId);

        return [
            'types'    => $types->map(function ($t) {
                return ['label' => $t->label, 'label_plural' => $t->label_plural];
            })->toArray(),
            'maxLevel' => $types->count(),
        ];
    }

    /**
     * AJAX: Get root-level divisions (level 1) for a tenant.
     */
    public function onLoadRootDivisions()
    {
        $tenantId = post('tenant_id', '');
        if (!$tenantId) return ['options' => []];

        return [
            'options' => \Majos\Caryard\Models\AdministrativeDivision::getRootOptions($tenantId)
        ];
    }

    /**
     * AJAX: Get child divisions for a given parent division.
     */
    public function onLoadChildDivisions()
    {
        $parentId = (int) post('parent_id');
        if ($parentId <= 0) return ['options' => []];

        $options = \Majos\Caryard\Models\AdministrativeDivision::where('parent_id', $parentId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return ['options' => $options];
    }

    /**
     * AJAX: Delete a single image from a vehicle's gallery.
     *
     * OWASP BOLA:  vehicle must belong to the authenticated seller (seller_id check).
     * OWASP BOPLA: only vehicle_id and image_id are read from post(); no mass-assignment.
     */
    public function onDeleteImage()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) {
            throw new \ApplicationException('Access denied.');
        }

        // Sanitise inputs — accept only positive integers
        $vehicleId = (int) post('vehicle_id');
        $imageId   = (int) post('image_id');

        if ($vehicleId <= 0 || $imageId <= 0) {
            throw new \ApplicationException('Invalid parameters.');
        }

        // BOLA: vehicle must belong to this seller
        $vehicle = Vehicle::where('id', $vehicleId)
            ->where('seller_id', $user->id)
            ->first();

        if (!$vehicle) {
            throw new \ApplicationException('Vehicle not found or access denied.');
        }

        // Scope image lookup to this vehicle's relation (prevents IDOR on image_id)
        $image = $vehicle->images()->where('id', $imageId)->first();

        if (!$image) {
            throw new \ApplicationException('Image not found or does not belong to this vehicle.');
        }

        // Remove thumbnails from disk, then delete the DB record + original file
        $image->deleteThumbs();
        $image->delete();

        Flash::success('Image deleted.');

        // Return the refreshed gallery section so the frontend stays in sync
        return [
            '#existingGalleryWrapper' => $this->renderPartial('@_gallery', [
                'vehicle' => $vehicle->fresh(),
            ]),
        ];
    }

    /**
     * AJAX: Reorder a vehicle's gallery images via drag-and-drop.
     *
     * OWASP BOLA:  vehicle must belong to the authenticated seller.
     * OWASP BOPLA: only vehicle_id and image_ids[] are read; sort_order is the only property written.
     */
    public function onReorderImages()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) {
            throw new \ApplicationException('Access denied.');
        }

        $vehicleId = (int) post('vehicle_id');
        if ($vehicleId <= 0) {
            throw new \ApplicationException('Invalid vehicle.');
        }

        // BOLA: vehicle must belong to this seller
        $vehicle = Vehicle::where('id', $vehicleId)
            ->where('seller_id', $user->id)
            ->first();

        if (!$vehicle) {
            throw new \ApplicationException('Vehicle not found or access denied.');
        }

        $ids = post('image_ids', []);
        if (!is_array($ids)) {
            throw new \ApplicationException('Invalid image order data.');
        }

        // Only update sort_order (BOPLA) for images that belong to this vehicle
        foreach ($ids as $index => $id) {
            $safeId = (int) $id;
            if ($safeId <= 0) continue;

            $vehicle->images()->where('id', $safeId)->update([
                'sort_order' => (int) $index,
            ]);
        }

        return ['status' => 'success'];
    }

    /**
     * Load loan settings modal
     */
    public function onLoadLoanSettingsModal()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) {
            throw new \ApplicationException('Access denied.');
        }

        // Get or create loan settings for this user
        $loanSettings = \Majos\Caryard\Models\SellerLoanSettings::forUser($user->id);

        // Pass data to the view
        $this->page['loanSettings'] = [
            'loan_enabled' => $loanSettings->loan_enabled,
            'loan_terms' => $loanSettings->getTermsArray(),
            'loan_annual_rate' => $loanSettings->loan_annual_rate,
            'loan_min_down_payment_percent' => $loanSettings->loan_min_down_payment_percent,
            'loan_max_down_payment_percent' => $loanSettings->loan_max_down_payment_percent,
        ];

        // Return the modal HTML
        $this->page['loanSettings'] = [
            'loan_enabled' => $loanSettings->loan_enabled,
            'loan_terms' => $loanSettings->getTermsArray(),
            'loan_annual_rate' => $loanSettings->loan_annual_rate,
            'loan_min_down_payment_percent' => $loanSettings->loan_min_down_payment_percent,
            'loan_max_down_payment_percent' => $loanSettings->loan_max_down_payment_percent,
        ];
        
        // Use the component's renderPartial method with full path
        $modalHtml = $this->renderPartial('@_loan_settings_modal', [
            'loanSettings' => $this->page['loanSettings']
        ]);

        return [
            '#loan-settings-modal-container' => $modalHtml
        ];
    }

    /**
     * Save loan settings
     */
    public function onSaveLoanSettings()
    {
        $user = Auth::getUser();
        if (!$user || !$this->isSeller($user)) {
            throw new \ApplicationException('Access denied.');
        }

        // Get the loan settings (or create new)
        $loanSettings = \Majos\Caryard\Models\SellerLoanSettings::forUser($user->id);

        // Get form data
        $loanEnabled = post('loan_enabled', false);
        $loanTerms = post('loan_terms', []);
        $loanAnnualRate = post('loan_annual_rate', 0);
        $loanMinDownPayment = post('loan_min_down_payment_percent', 0);
        $loanMaxDownPayment = post('loan_max_down_payment_percent', 0);

        // Validate inputs - no negative or zero values allowed
        $errors = [];

        if ($loanAnnualRate < 0) {
            $errors[] = 'Interest rate cannot be negative.';
        }

        if ($loanMinDownPayment < 0) {
            $errors[] = 'Minimum deposit percentage cannot be negative.';
        }

        if ($loanMaxDownPayment < 0) {
            $errors[] = 'Maximum deposit percentage cannot be negative.';
        }

        if ($loanMinDownPayment > 100) {
            $errors[] = 'Minimum deposit percentage cannot exceed 100%.';
        }

        if ($loanMaxDownPayment > 100) {
            $errors[] = 'Maximum deposit percentage cannot exceed 100%.';
        }

        if (!empty($errors)) {
            return [
                '#loan-settings-validation' => [
                    'class' => 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg',
                    'html' => '<p class="text-sm text-red-600">' . implode(' ', $errors) . '</p>'
                ]
            ];
        }

        // If no terms selected, use default
        if (empty($loanTerms)) {
            $loanTerms = [3, 6, 12, 18, 24, 36, 48, 60, 72, 84, 96];
        }

        // Make sure terms are integers
        $loanTerms = array_map('intval', $loanTerms);

        // Update settings
        $loanSettings->loan_enabled = !empty($loanEnabled);
        $loanSettings->loan_terms = json_encode($loanTerms);
        $loanSettings->loan_annual_rate = (float) $loanAnnualRate;
        $loanSettings->loan_min_down_payment_percent = (float) $loanMinDownPayment;
        $loanSettings->loan_max_down_payment_percent = (float) $loanMaxDownPayment;
        $loanSettings->save();

        Flash::success('Loan settings saved successfully!');

        // Close the modal after successful save
        return [
            'status' => 'success',
            'loan_enabled' => $loanSettings->loan_enabled,
            '#loan-settings-modal-container' => ''
        ];
    }

    public function isSeller($user)
    {
    
        return $user && 
               $user->seller_profile && 
               optional($user->seller_profile)->is_verified_seller;
    }
}
