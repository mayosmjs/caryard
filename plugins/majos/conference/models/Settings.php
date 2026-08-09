<?php namespace Majos\Conference\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Settings extends Model
{
    use Validation;

    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'majos_conference_settings';

    public $settingsFields = 'fields.yaml';

    public $rules = [
        'submission_deadline'  => 'required|date',
        'max_file_size_kb'     => 'required|numeric|min:100|max:20480',
        'turnstile_site_key'   => 'nullable|string|max:255',
        'turnstile_secret_key' => 'nullable|string|max:255',
        'unique_email'         => 'required|boolean',
        'token_expiry_days'    => 'required|numeric|min:1|max:365',
        'notification_email'   => 'nullable|email',
        'reviewer_portal_page' => 'nullable|string|max:255',
        'poster_template_types' => 'nullable|array',
        'poster_max_size_mb'    => 'nullable|numeric|min:1|max:100',
        'hide_speakers_section' => 'nullable|boolean',
        'hide_sponsors_section' => 'nullable|boolean',
        'hide_speakers_nav'     => 'nullable|boolean',
        'hide_sponsors_nav'     => 'nullable|boolean',
        'conference_start_date' => 'nullable|date',
        'conference_end_date'   => 'nullable|date|after_or_equal:conference_start_date',
        'contact_email'         => 'nullable|email|max:255',
        'important_dates_list'  => 'nullable|array',
        'contact_entries'       => 'nullable|array',
        'payment_instructions'  => 'nullable|string|max:1000',
        'payment_details'       => 'nullable|array',
    ];

    protected $jsonable = [];

    protected $dates = ['submission_deadline', 'conference_start_date', 'conference_end_date'];

    public $attachOne = [
        'poster_template' => ['System\Models\File'],
    ];

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->getDates(), true) && ($value === 'Invalid date' || $value === '')) {
            $value = null;
        }

        return parent::setAttribute($key, $value);
    }

    public function initSettingsData()
    {
        return [
            'submission_deadline'  => '2027-06-30 23:59:59',
            'max_file_size_kb'     => 5120,
            'turnstile_site_key'   => '',
            'turnstile_secret_key' => '',
            'unique_email'         => true,
            'token_expiry_days'    => 30,
            'notification_email'   => '',
            'reviewer_portal_page' => 'review-portal',
            'poster_template_types' => \Majos\Conference\Models\Submission::presentationTypeNames(),
            'poster_max_size_mb'    => \Majos\Conference\Models\Submission::MAX_POSTER_SIZE_MB,
            'hide_speakers_section' => false,
            'hide_sponsors_section' => false,
            'hide_speakers_nav'     => false,
            'hide_sponsors_nav'     => false,
            'conference_start_date' => '2027-02-02',
            'conference_end_date'   => '2027-02-05',
            'contact_email'         => 'afrirpa@eaarp.co.ke',
            'important_dates_list'  => [
                ['date' => '2026-08-07', 'label' => 'Abstract Submission Opens'],
                ['date' => '2026-09-30', 'label' => 'Abstract Submission Deadline', 'note' => 'Deadline'],
                ['date' => '2026-10-31', 'label' => 'Abstract Submission Extended Deadline', 'note' => 'Extended Deadline'],
            ],
            'contact_entries' => [
                ['type' => 'email', 'label' => 'Email', 'value' => 'afrirpa@eaarp.co.ke'],
                ['type' => 'phone', 'label' => 'Telephone', 'value' => '+254 000 000 000'],
            ],
            'payment_instructions' => 'Make payment using the details below and use your full name as the payment reference.',
            'payment_details' => [
                ['type' => 'bank', 'label' => 'Bank', 'value' => 'Equity Bank'],
                ['type' => 'branch', 'label' => 'Branch', 'value' => 'Nairobi'],
                ['type' => 'account', 'label' => 'Account Name', 'value' => 'AFRIRPA 7th Congress'],
                ['type' => 'account', 'label' => 'Account Number', 'value' => '0000000000'],
                ['type' => 'paybill', 'label' => 'Paybill Number', 'value' => '000000'],
                ['type' => 'paybill', 'label' => 'Paybill Account', 'value' => 'AFRIRPA'],
            ],
        ];
    }

    /**
     * contacts returns the configured contact entries (phones/emails) shown
     * in the site footer, filtering out empty or invalid rows.
     */
    public function contacts(): array
    {
        $rows = (array) $this->getSettingsValue('contact_entries', []);

        $result = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $type = strtolower(trim((string) ($row['type'] ?? '')));
            $value = trim((string) ($row['value'] ?? ''));
            if (!in_array($type, ['email', 'phone'], true) || $value === '') {
                continue;
            }
            $result[] = [
                'type'  => $type,
                'label' => trim((string) ($row['label'] ?? '')),
                'value' => $value,
            ];
        }

        return $result;
    }

    /**
     * importantDates returns the configured timeline entries as a collection
     * of arrays with date/label/note keys, filtering out empty rows.
     */
    public function importantDates(): array
    {
        $rows = (array) $this->getSettingsValue('important_dates_list', []);

        $result = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $date = trim((string) ($row['date'] ?? ''));
            if ($date === '' || $date === 'Invalid date' || empty($row['label'])) {
                continue;
            }
            $result[] = [
                'date'  => $date,
                'label' => $row['label'],
                'note'  => $row['note'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * paymentDetails returns the configured payment detail entries shown in
     * the ticket payment popup, filtering out empty rows.
     */
    public function paymentDetails(): array
    {
        $rows = (array) $this->getSettingsValue('payment_details', []);

        $result = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $result[] = [
                'type'  => strtolower(trim((string) ($row['type'] ?? 'other'))),
                'label' => $label,
                'value' => $value,
            ];
        }

        return $result;
    }

    // ── Poster template configuration ────────────────────────────────────

    /**
     * getPresentationTypeOptions is used by the poster template settings
     * checkbox list to build the presentation type option list.
     */
    public function getPresentationTypeOptions(...$args)
    {
        return array_combine(
            \Majos\Conference\Models\Submission::presentationTypeNames(),
            \Majos\Conference\Models\Submission::presentationTypeNames()
        );
    }

    public function posterTemplateTypes(): array
    {
        $types = (array) $this->getSettingsValue('poster_template_types', []);

        if (empty($types)) {
            $types = \Majos\Conference\Models\Submission::presentationTypeNames();
        }

        return array_values(array_intersect($types, \Majos\Conference\Models\Submission::presentationTypeNames()));
    }

    public function requiresPosterTemplate(string $presentationType): bool
    {
        return in_array($presentationType, $this->posterTemplateTypes(), true);
    }

    public function posterMaxSizeMb(): int
    {
        $mb = (int) $this->getSettingsValue('poster_max_size_mb', 0);

        return $mb > 0 ? $mb : \Majos\Conference\Models\Submission::MAX_POSTER_SIZE_MB;
    }

    public function posterTemplatePath(): ?string
    {
        if ($this->poster_template && $this->poster_template->getLocalPath()) {
            return $this->poster_template->getLocalPath();
        }

        $fallback = plugins_path('majos/conference/assets/templates/AFRIRPA_2027_Oral_Poster_Template.docx');

        return file_exists($fallback) ? $fallback : null;
    }

    public function posterTemplateName(): string
    {
        if ($this->poster_template) {
            return $this->poster_template->file_name;
        }

        return 'AFRIRPA_2027_Oral_Poster_Template.docx';
    }
}
