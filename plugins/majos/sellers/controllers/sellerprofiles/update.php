<?php namespace Majos\Sellers\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;

/**
 * SellerProfiles Update Controller
 */
class Update extends Controller
{
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
    }

    public function update($id)
    {
        $this->pageTitle = 'Edit Seller Profile';
        
        $profile = \Majos\Sellers\Models\SellerProfile::findOrFail($id);
        $this->vars['model'] = $profile;
        
        return $this->makePartial('update', ['model' => $profile]);
    }

    public function onSave()
    {
        $id = post('id');
        $data = post('SellerProfile');
        
        $profile = \Majos\Sellers\Models\SellerProfile::findOrFail($id);
        $profile->fill($data);
        
        try {
            $profile->save();
            
            \Flash::success('Seller Profile updated successfully.');
            
            return Backend::redirect('majos/sellers/sellerprofiles');
        } catch (\Exception $ex) {
            \Flash::error($ex->getMessage());
            return null;
        }
    }
}