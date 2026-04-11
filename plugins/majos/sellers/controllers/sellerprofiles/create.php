<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;

/**
 * SellerProfiles Create Controller
 */
class Create extends Controller
{
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->create();
    }

    public function create()
    {
        $this->pageTitle = 'New Seller Profile';
        return $this->makePartial('create');
    }

    public function onSave()
    {
        $data = post('SellerProfile');
        
        $profile = new \Majos\Sellers\Models\SellerProfile;
        $profile->fill($data);
        
        try {
            $profile->save();
            
            \Flash::success('Seller Profile created successfully.');
            
            return Backend::redirect('majos/sellers/sellerprofiles');
        } catch (\Exception $ex) {
            \Flash::error($ex->getMessage());
            return null;
        }
    }
}