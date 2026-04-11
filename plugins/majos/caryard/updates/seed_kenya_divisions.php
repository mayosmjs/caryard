<?php namespace Majos\Caryard\Updates;

use Seeder;
use Majos\Caryard\Models\AdministrativeDivision;
use Majos\Caryard\Models\DivisionType;
use Majos\Caryard\Models\Tenant;

class SeedKenyaDivisions extends Seeder
{
    public function run()
    {
        // Find the Kenya tenant by country_code
        $tenant = Tenant::where('country_code', 'KE')->first();
        if (!$tenant) {
            // Skip if no Kenya tenant exists yet
            return;
        }

        // Ensure division types exist for Kenya tenant
        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 1],
            ['label' => 'County', 'label_plural' => 'Counties']
        );
        DivisionType::updateOrCreate(
            ['tenant_id' => $tenant->id, 'level' => 2],
            ['label' => 'Town', 'label_plural' => 'Towns']
        );

        $counties = [
            'Mombasa' => ['code' => '001', 'towns' => [
                'Mombasa CBD', 'Nyali', 'Bamburi', 'Likoni', 'Kisauni', 'Changamwe', 'Mvita', 'Tudor', 'Ganjoni', 'Miritini',
            ]],
            'Kwale' => ['code' => '002', 'towns' => [
                'Kwale', 'Ukunda', 'Diani', 'Msambweni', 'Kinango', 'Shimba Hills', 'Lunga Lunga', 'Matuga',
            ]],
            'Kilifi' => ['code' => '003', 'towns' => [
                'Kilifi', 'Malindi', 'Watamu', 'Mtwapa', 'Mariakani', 'Kaloleni', 'Rabai', 'Ganze', 'Magarini',
            ]],
            'Tana River' => ['code' => '004', 'towns' => [
                'Hola', 'Garsen', 'Bura', 'Kipini', 'Madogo', 'Wenje',
            ]],
            'Lamu' => ['code' => '005', 'towns' => [
                'Lamu', 'Mpeketoni', 'Witu', 'Faza', 'Mokowe', 'Hindi',
            ]],
            'Taita-Taveta' => ['code' => '006', 'towns' => [
                'Voi', 'Wundanyi', 'Taveta', 'Mwatate', 'Werugha', 'Sagala',
            ]],
            'Garissa' => ['code' => '007', 'towns' => [
                'Garissa', 'Dadaab', 'Ijara', 'Balambala', 'Lagdera', 'Fafi', 'Hulugho',
            ]],
            'Wajir' => ['code' => '008', 'towns' => [
                'Wajir', 'Habaswein', 'Buna', 'Griftu', 'Tarbaj', 'Eldas',
            ]],
            'Mandera' => ['code' => '009', 'towns' => [
                'Mandera', 'Elwak', 'Rhamu', 'Takaba', 'Banissa', 'Lafey',
            ]],
            'Marsabit' => ['code' => '010', 'towns' => [
                'Marsabit', 'Moyale', 'Laisamis', 'North Horr', 'Saku', 'Loiyangalani',
            ]],
            'Isiolo' => ['code' => '011', 'towns' => [
                'Isiolo', 'Merti', 'Garbatulla', 'Kinna', 'Oldonyiro',
            ]],
            'Meru' => ['code' => '012', 'towns' => [
                'Meru', 'Nkubu', 'Maua', 'Timau', 'Mikinduri', 'Chuka', 'Chogoria', 'Igembe', 'Tigania',
            ]],
            'Tharaka-Nithi' => ['code' => '013', 'towns' => [
                'Chuka', 'Chogoria', 'Marimanti', 'Kathwana', 'Gatunga', 'Mukothima',
            ]],
            'Embu' => ['code' => '014', 'towns' => [
                'Embu', 'Runyenjes', 'Siakago', 'Kiritiri', 'Ishiara', 'Kanja',
            ]],
            'Kitui' => ['code' => '015', 'towns' => [
                'Kitui', 'Mwingi', 'Mutomo', 'Kabati', 'Nuu', 'Zombe', 'Migwani', 'Matinyani',
            ]],
            'Machakos' => ['code' => '016', 'towns' => [
                'Machakos', 'Athi River', 'Kangundo', 'Tala', 'Matuu', 'Masii', 'Kathiani', 'Mwala', 'Yatta',
            ]],
            'Makueni' => ['code' => '017', 'towns' => [
                'Wote', 'Sultan Hamud', 'Emali', 'Makindu', 'Kibwezi', 'Mtito Andei', 'Nunguni', 'Tawa',
            ]],
            'Nyandarua' => ['code' => '018', 'towns' => [
                'Ol Kalou', 'Engineer', 'Njabini', 'Ndaragwa', 'Kinangop', 'Mirangine',
            ]],
            'Nyeri' => ['code' => '019', 'towns' => [
                'Nyeri', 'Karatina', 'Othaya', 'Mukurweini', 'Naro Moru', 'Mweiga', 'Tetu', 'Mathira',
            ]],
            'Kirinyaga' => ['code' => '020', 'towns' => [
                'Kerugoya', 'Kutus', 'Sagana', 'Kagio', 'Wang\'uru', 'Kagumo',
            ]],
            'Murang\'a' => ['code' => '021', 'towns' => [
                'Murang\'a', 'Kenol', 'Maragua', 'Kangema', 'Kahuro', 'Kigumo', 'Kandara', 'Gatanga',
            ]],
            'Kiambu' => ['code' => '022', 'towns' => [
                'Kiambu', 'Thika', 'Ruiru', 'Juja', 'Limuru', 'Kikuyu', 'Gatundu', 'Githunguri', 'Lari', 'Kabete',
            ]],
            'Turkana' => ['code' => '023', 'towns' => [
                'Lodwar', 'Kakuma', 'Lokichogio', 'Kalokol', 'Lokitaung', 'Lokichar',
            ]],
            'West Pokot' => ['code' => '024', 'towns' => [
                'Kapenguria', 'Makutano', 'Chepareria', 'Sigor', 'Kacheliba', 'Alale',
            ]],
            'Samburu' => ['code' => '025', 'towns' => [
                'Maralal', 'Archer\'s Post', 'Baragoi', 'Wamba', 'Suguta Marmar',
            ]],
            'Trans-Nzoia' => ['code' => '026', 'towns' => [
                'Kitale', 'Endebess', 'Kiminini', 'Saboti', 'Kwanza', 'Cherangany',
            ]],
            'Uasin Gishu' => ['code' => '027', 'towns' => [
                'Eldoret', 'Burnt Forest', 'Turbo', 'Moiben', 'Ziwa', 'Ainabkoi', 'Kesses', 'Soy',
            ]],
            'Elgeyo-Marakwet' => ['code' => '028', 'towns' => [
                'Iten', 'Kapsowar', 'Cheptongei', 'Kamariny', 'Tambach',
            ]],
            'Nandi' => ['code' => '029', 'towns' => [
                'Kapsabet', 'Nandi Hills', 'Mosoriot', 'Kobujoi', 'Kabiyet', 'Maraba',
            ]],
            'Baringo' => ['code' => '030', 'towns' => [
                'Kabarnet', 'Eldama Ravine', 'Marigat', 'Mogotio', 'Kabartonjo', 'Chemolingot',
            ]],
            'Laikipia' => ['code' => '031', 'towns' => [
                'Nanyuki', 'Rumuruti', 'Nyahururu', 'Dol Dol', 'Lamuria', 'Sipili',
            ]],
            'Nakuru' => ['code' => '032', 'towns' => [
                'Nakuru', 'Naivasha', 'Gilgil', 'Molo', 'Njoro', 'Subukia', 'Bahati', 'Rongai', 'Mai Mahiu', 'Elementaita',
            ]],
            'Narok' => ['code' => '033', 'towns' => [
                'Narok', 'Kilgoris', 'Ololulung\'a', 'Emurua Dikirr', 'Lolgorien', 'Ntulele',
            ]],
            'Kajiado' => ['code' => '034', 'towns' => [
                'Kajiado', 'Kitengela', 'Ongata Rongai', 'Ngong', 'Kiserian', 'Namanga', 'Loitokitok', 'Isinya', 'Magadi',
            ]],
            'Kericho' => ['code' => '035', 'towns' => [
                'Kericho', 'Litein', 'Londiani', 'Kipkelion', 'Sosiot', 'Sigowet', 'Fort Ternan',
            ]],
            'Bomet' => ['code' => '036', 'towns' => [
                'Bomet', 'Sotik', 'Longisa', 'Mulot', 'Sigor', 'Ndanai',
            ]],
            'Kakamega' => ['code' => '037', 'towns' => [
                'Kakamega', 'Mumias', 'Butere', 'Malava', 'Lugari', 'Navakholo', 'Likuyani', 'Lurambi', 'Shinyalu',
            ]],
            'Vihiga' => ['code' => '038', 'towns' => [
                'Mbale', 'Luanda', 'Chavakali', 'Majengo', 'Hamisi', 'Sabatia',
            ]],
            'Bungoma' => ['code' => '039', 'towns' => [
                'Bungoma', 'Webuye', 'Kimilili', 'Chwele', 'Malakisi', 'Sirisia', 'Tongaren', 'Kanduyi',
            ]],
            'Busia' => ['code' => '040', 'towns' => [
                'Busia', 'Malaba', 'Nambale', 'Funyula', 'Butula', 'Matayos', 'Port Victoria',
            ]],
            'Siaya' => ['code' => '041', 'towns' => [
                'Siaya', 'Bondo', 'Ugunja', 'Yala', 'Ukwala', 'Usenge', 'Ndori',
            ]],
            'Kisumu' => ['code' => '042', 'towns' => [
                'Kisumu', 'Ahero', 'Maseno', 'Muhoroni', 'Kombewa', 'Kondele', 'Mamboleo', 'Chemilil',
            ]],
            'Homa Bay' => ['code' => '043', 'towns' => [
                'Homa Bay', 'Oyugis', 'Kendu Bay', 'Mbita', 'Ndhiwa', 'Rachuonyo', 'Suba',
            ]],
            'Migori' => ['code' => '044', 'towns' => [
                'Migori', 'Rongo', 'Awendo', 'Kehancha', 'Isebania', 'Muhuru Bay', 'Kuria',
            ]],
            'Kisii' => ['code' => '045', 'towns' => [
                'Kisii', 'Ogembo', 'Keroka', 'Suneka', 'Marani', 'Tabaka', 'Nyamache',
            ]],
            'Nyamira' => ['code' => '046', 'towns' => [
                'Nyamira', 'Keroka', 'Ekerenyo', 'Nyansiongo', 'Manga', 'Magombo',
            ]],
            'Nairobi' => ['code' => '047', 'towns' => [
                'Nairobi CBD', 'Westlands', 'Karen', 'Langata', 'Dagoretti', 'Kasarani',
                'Roysambu', 'Embakasi', 'Ruaraka', 'Makadara', 'Kamukunji', 'Starehe',
                'Mathare', 'Kibra', 'Parklands', 'Kilimani', 'Lavington', 'South B',
                'South C', 'Donholm', 'Buruburu', 'Umoja', 'Pipeline', 'Utawala',
                'Syokimau', 'Mlolongo', 'Kahawa', 'Githurai', 'Zimmerman', 'Ruai',
            ]],
        ];

        foreach ($counties as $countyName => $data) {
            $county = AdministrativeDivision::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $countyName, 'level' => 1],
                [
                    'code'       => $data['code'],
                    'slug'       => \Str::slug($countyName),
                    'is_active'  => true,
                    'sort_order' => (int) $data['code'],
                ]
            );

            foreach ($data['towns'] as $index => $townName) {
                AdministrativeDivision::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'parent_id' => $county->id, 'name' => $townName, 'level' => 2],
                    [
                        'slug'       => \Str::slug($countyName . '-' . $townName),
                        'is_active'  => true,
                        'sort_order' => $index,
                    ]
                );
            }
        }
    }
}