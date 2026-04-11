<?php namespace Majos\Caryard\Updates;

use Majos\Caryard\Models\Tenant;
use October\Rain\Database\Updates\Seeder;

class SeedAfricanTenants extends Seeder
{
    public function run()
    {
        $africanCountries = [
            ['name' => 'Algeria', 'country_code' => 'DZ', 'currency' => 'DZD'],
            ['name' => 'Angola', 'country_code' => 'AO', 'currency' => 'AOA'],
            ['name' => 'Benin', 'country_code' => 'BJ', 'currency' => 'XOF'],
            ['name' => 'Botswana', 'country_code' => 'BW', 'currency' => 'BWP'],
            ['name' => 'Burkina Faso', 'country_code' => 'BF', 'currency' => 'XOF'],
            ['name' => 'Burundi', 'country_code' => 'BI', 'currency' => 'BIF'],
            ['name' => 'Cabo Verde', 'country_code' => 'CV', 'currency' => 'CVE'],
            ['name' => 'Cameroon', 'country_code' => 'CM', 'currency' => 'XAF'],
            ['name' => 'Central African Republic', 'country_code' => 'CF', 'currency' => 'XAF'],
            ['name' => 'Chad', 'country_code' => 'TD', 'currency' => 'XAF'],
            ['name' => 'Comoros', 'country_code' => 'KM', 'currency' => 'KMF'],
            ['name' => 'Congo, Democratic Republic of the', 'country_code' => 'CD', 'currency' => 'CDF'],
            ['name' => 'Congo, Republic of the', 'country_code' => 'CG', 'currency' => 'XAF'],
            ['name' => 'Cote d\'Ivoire', 'country_code' => 'CI', 'currency' => 'XOF'],
            ['name' => 'Djibouti', 'country_code' => 'DJ', 'currency' => 'DJF'],
            ['name' => 'Egypt', 'country_code' => 'EG', 'currency' => 'EGP'],
            ['name' => 'Equatorial Guinea', 'country_code' => 'GQ', 'currency' => 'XAF'],
            ['name' => 'Eritrea', 'country_code' => 'ER', 'currency' => 'ERN'],
            ['name' => 'Eswatini', 'country_code' => 'SZ', 'currency' => 'SZL'],
            ['name' => 'Ethiopia', 'country_code' => 'ET', 'currency' => 'ETB'],
            ['name' => 'Gabon', 'country_code' => 'GA', 'currency' => 'XAF'],
            ['name' => 'Gambia', 'country_code' => 'GM', 'currency' => 'GMD'],
            ['name' => 'Ghana', 'country_code' => 'GH', 'currency' => 'GHS'],
            ['name' => 'Guinea', 'country_code' => 'GN', 'currency' => 'GNF'],
            ['name' => 'Guinea-Bissau', 'country_code' => 'GW', 'currency' => 'XOF'],
            ['name' => 'Kenya', 'country_code' => 'KE', 'currency' => 'KES'],
            ['name' => 'Lesotho', 'country_code' => 'LS', 'currency' => 'LSL'],
            ['name' => 'Liberia', 'country_code' => 'LR', 'currency' => 'LRD'],
            ['name' => 'Libya', 'country_code' => 'LY', 'currency' => 'LYD'],
            ['name' => 'Madagascar', 'country_code' => 'MG', 'currency' => 'MGA'],
            ['name' => 'Malawi', 'country_code' => 'MW', 'currency' => 'MWK'],
            ['name' => 'Mali', 'country_code' => 'ML', 'currency' => 'XOF'],
            ['name' => 'Mauritania', 'country_code' => 'MR', 'currency' => 'MRU'],
            ['name' => 'Mauritius', 'country_code' => 'MU', 'currency' => 'MUR'],
            ['name' => 'Morocco', 'country_code' => 'MA', 'currency' => 'MAD'],
            ['name' => 'Mozambique', 'country_code' => 'MZ', 'currency' => 'MZN'],
            ['name' => 'Namibia', 'country_code' => 'NA', 'currency' => 'NAD'],
            ['name' => 'Niger', 'country_code' => 'NE', 'currency' => 'XOF'],
            ['name' => 'Nigeria', 'country_code' => 'NG', 'currency' => 'NGN'],
            ['name' => 'Rwanda', 'country_code' => 'RW', 'currency' => 'RWF'],
            ['name' => 'Sao Tome and Principe', 'country_code' => 'ST', 'currency' => 'STN'],
            ['name' => 'Senegal', 'country_code' => 'SN', 'currency' => 'XOF'],
            ['name' => 'Seychelles', 'country_code' => 'SC', 'currency' => 'SCR'],
            ['name' => 'Sierra Leone', 'country_code' => 'SL', 'currency' => 'SLL'],
            ['name' => 'Somalia', 'country_code' => 'SO', 'currency' => 'SOS'],
            ['name' => 'South Africa', 'country_code' => 'ZA', 'currency' => 'ZAR'],
            ['name' => 'South Sudan', 'country_code' => 'SS', 'currency' => 'SSP'],
            ['name' => 'Sudan', 'country_code' => 'SD', 'currency' => 'SDG'],
            ['name' => 'Tanzania', 'country_code' => 'TZ', 'currency' => 'TZS'],
            ['name' => 'Togo', 'country_code' => 'TG', 'currency' => 'XOF'],
            ['name' => 'Tunisia', 'country_code' => 'TN', 'currency' => 'TND'],
            ['name' => 'Uganda', 'country_code' => 'UG', 'currency' => 'UGX'],
            ['name' => 'Zambia', 'country_code' => 'ZM', 'currency' => 'ZMW'],
            ['name' => 'Zimbabwe', 'country_code' => 'ZW', 'currency' => 'ZWL'],
        ];

        foreach ($africanCountries as $country) {
            Tenant::create([
                'name' => $country['name'],
                'country_code' => $country['country_code'],
                'currency' => $country['currency'],
                'slug' => $country['country_code'],
                'is_active' => false
            ]);
        }
    }
}
