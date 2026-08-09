# Conference Plugin for October CMS

A production-ready Event Schedule module for conference websites. Built with October CMS, featuring a robust data model with days, sessions, speakers, venues, and tracks.

## Features

- **Conference Days** - Manage multiple event days with dates and ordering
- **Sessions** - Full CRUD with time slots, types, tracks, capacity, and venues
- **Speakers** - Rich speaker profiles with photos, bios, social links, and expertise
- **Venues** - Manage locations, halls, and rooms with capacity info
- **Tracks/Categories** - Categorize sessions (AI, Cybersecurity, Energy, Policy, etc.)
- **Session Types** - Keynote, Workshop, Panel, Fireside Chat, Break, etc.
- **Multiple Speakers** - Many-to-many relationship for sessions
- **Drag & Drop Reordering** - Sessions can be reordered within each day
- **SEO-Friendly** - Slug fields for all content
- **Published/Draft Status** - Control visibility of all content
- **Timezone Support** - Proper datetime handling
- **Reusable Components** - Frontend components with filtering

## Installation

1. Upload the plugin to `plugins/majos/conference/`
2. Run migrations: `php artisan october:migrate`
3. (Optional) Run `php artisan october:up` to update database

## Backend Usage

### Navigation

After installation, you'll see a "Conference" menu in the backend with:

- **Sessions** - Manage all conference sessions
- **Speakers** - Manage speaker profiles
- **Days** - Configure conference days/dates
- **Venues** - Set up locations and rooms

### Creating Content

1. **First, create Conference Days** - Set up the dates for your event
2. **Add Venues** - Create locations/halls for sessions
3. **Add Speakers** - Fill in speaker profiles with photos and bios
4. **Create Sessions** - Assign sessions to days, venues, and speakers

### Session Management

- Use the **Reorder** tab to drag and drop sessions within the same day
- Assign multiple speakers to a session via the multi-select field
- Set session types and tracks for filtering
- Control visibility with the Published toggle

## Frontend Components

### 1. Schedule Component

Displays the full conference schedule grouped by day with filtering.

**Usage:**
```html
{% component 'schedule' %}
```

**Properties:**
- `showFilters` (bool) - Show/hide filter dropdowns (default: true)
- `showDayFilter` (bool) - Show day filter (default: true)
- `showTrackFilter` (bool) - Show track filter (default: true)
- `showTypeFilter` (bool) - Show session type filter (default: true)
- `defaultDay` (int) - Default selected day ID (0 = all days)
- `defaultTrack` (string) - Default track filter (empty = all)
- `defaultType` (string) - Default type filter (empty = all)

**Example with properties:**
```html
{% component 'schedule'
    showFilters="true"
    defaultDay="1"
    defaultTrack="ai"
%}
```

### 2. Speakers Component

Displays speaker profiles. Can show a list or a single speaker detail.

**Usage - List View:**
```html
{% component 'speakers' %}
```

**Usage - Single Speaker:**
```html
{% component 'speakers' speakerSlug="john-doe" %}
```

**Properties:**
- `speakerSlug` (string) - Slug of specific speaker to display
- `showFeaturedOnly` (bool) - Show only featured speakers
- `limit` (int) - Limit number of speakers shown
- `order` (string) - Sort by: `full_name`, `created_at`, `company`

**Examples:**
```html
{% component 'speakers' showFeaturedOnly="true" limit="10" %}
{% component 'speakers' speakerSlug="jane-smith" %}
```

### 3. SessionDetail Component

Displays a single session with full details.

**Usage:**
```html
{% component 'sessionDetail' sessionSlug="building-ai-apps" %}
```

**Properties:**
- `sessionSlug` (string) - Session slug (required)
- `sessionId` (int) - Alternative: session ID

**Example:**
```html
{% component 'sessionDetail' sessionSlug="keynote-future-of-ai" %}
```

### 4. SessionList Component

Displays a filtered list of sessions.

**Usage:**
```html
{% component 'sessionList' %}
```

**Properties:**
- `speakerSlug` (string) - Filter by speaker
- `track` (string) - Filter by track (ai, cybersecurity, energy, policy, finance, health, education, other)
- `sessionType` (string) - Filter by type (keynote, workshop, panel, fireside_chat, break, presentation, qa)
- `dayId` (int) - Filter by conference day ID
- `limit` (int) - Number of sessions to show
- `order` (string) - Sort by: `starts_at`, `sort_order`, `title`

**Examples:**
```html
{% component 'sessionList' track="ai" limit="5" %}
{% component 'sessionList' speakerSlug="john-doe" %}
{% component 'sessionList' sessionType="keynote" dayId="1" %}
```

## URL Routing

You can create dedicated pages for speakers and sessions using their slugs:

**Speaker Page:**
```yaml
url: /speakers/:slug
```

**Session Page:**
```yaml
url: /sessions/:slug
```

Then use the components with dynamic parameters:
```html
{% component 'speakers' speakerSlug=this.params.slug %}
```

## Database Schema

### Tables Created

1. **majos_conference_days** - Conference days
2. **majos_conference_venues** - Venues/locations
3. **majos_conference_speakers** - Speaker profiles
4. **majos_conference_sessions** - Sessions with foreign keys
5. **majos_conference_session_speaker** - Pivot table for many-to-many

### Model Relationships

- **Session** belongsTo **ConferenceDay**
- **Session** belongsTo **Venue** (optional)
- **Session** belongsToMany **Speaker**
- **ConferenceDay** hasMany **Session**
- **Venue** hasMany **Session**
- **Speaker** belongsToMany **Session**

## Customization

### Adding Custom Session Types

Edit the `session_type` dropdown in the Session model or create a custom field in the backend form configuration.

### Adding Custom Tracks

Modify the `tracks` array in the Schedule component or update the dropdown options in the backend form.

### Styling

The components use Bootstrap 4 classes. Override the default partials by copying them to your theme:

```
themes/your-theme/components/schedule/default.htm
themes/your-theme/components/speakers/default.htm
themes/your-theme/components/sessiondetail/default.htm
themes/your-theme/components/sessionlist/default.htm
```

## Permissions

The plugin registers the following permissions:

- `majos.conference.manage_days` - Manage conference days
- `majos.conference.manage_venues` - Manage venues
- `majos.conference.manage_speakers` - Manage speakers
- `majos.conference.manage_sessions` - Manage sessions

Assign these to backend user groups as needed.

## Best Practices

1. **Create days first** - Sessions require a day to be selected
2. **Upload speaker photos** - Recommended size: 200x200px minimum
3. **Use slugs** - For SEO-friendly URLs
4. **Mark featured speakers** - Highlight important speakers on the site
5. **Order sessions** - Use the reorder feature for custom scheduling
6. **Publish content** - Use the published toggle to control frontend visibility

## Troubleshooting

### Sessions not showing on frontend
- Check that sessions are marked as "Published"
- Verify the session date/time is set correctly
- Ensure the day is published

### Speakers not appearing in session form
- Speakers must be published to appear in the dropdown
- Check that the speaker record exists

### Migration errors
- Clear plugin cache: `php artisan cache:clear`
- Refresh: `php artisan october:refresh`

## License

This plugin is open-source and available under the MIT license.

## Support

For issues and feature requests, please use the GitHub repository.