# AFRIRPA Conference Plugin - Refactored

## Overview

This plugin handles abstract submissions for AFRIRPA 2027 conference. It has been refactored following OctoberCMS best practices with enhanced security, better UX, and a structured multi-step submission form.

## Features

### Multi-Step Submission Form
- **Step 1**: Author details (name, email, affiliation, institution, contact)
- **Step 2**: Paper details (title, authors, presentation type, category, abstract, keywords)
- **Step 3**: File upload (PDF only, size validated)
- **Step 4**: Review and submit with bot protection (Cloudflare Turnstile)

### Security Enhancements
- CSRF protection on all forms
- Rate limiting (5 attempts per hour for submissions, 8 for password attempts)
- Secure password hashing (bcrypt)
- Token-based edit links with expiration (configurable days)
- File validation (MIME type + extension)
- Input sanitization
- Comprehensive logging of security events
- Protection against common OWASP risks

### Admin Backend
- List view with status badges and statistics
- Detailed preview formatted like a research paper
- Accept/Reject decision workflow with confirmation
- Automatic email notifications for decisions
- Activity logging

### User Features
- Edit submissions via tokenized URL
- Password-protected access
- Email confirmation with edit link and password
- Clear deadline enforcement

## Configuration

### Backend Settings
Navigate to **Settings → Conference** in the backend to configure:

- **Submission Deadline**: The cutoff date for submissions
- **Max File Size (KB)**: Maximum PDF upload size (default 5120 KB = 5 MB)
- **Turnstile Site Key**: Cloudflare Turnstile site key for bot protection
- **Turnstile Secret Key**: Cloudflare Turnstile secret key
- **Unique Email**: Prevent multiple submissions from same email
- **Token Expiry Days**: How long edit links remain valid (default 30 days)
- **Notification Email**: Optional admin notification address

### Component Properties
The `conferenceRegistration` and `conferenceEditSubmission` components can be configured to either use global settings or override with page-specific values.

**Global Settings Mode** (recommended):
```
[conferenceRegistration]
useGlobalSettings = 1
editPage = "submission-edit"
```

**Override Mode** (for special cases):
```
[conferenceRegistration]
deadline = "2027-06-30 23:59:59"
maxFileSizeKb = "5120"
turnstileSiteKey = "your-key"
uniqueEmail = 1
```

## File Structure

```
plugins/majos/conference/
├── Plugin.php                      # Plugin registration
├── models/
│   ├── Submission.php              # Submission model with validation
│   ├── Settings.php                # Settings model for backend config
│   └── fields.yaml                 # Settings form fields
├── components/
│   ├── Registration.php            # Frontend submission component
│   └── EditSubmission.php          # Frontend edit component
├── controllers/
│   ├── Submissions.php             # Backend controller
│   ├── Settings.php               # Settings controller
│   ├── submissions/
│   │   ├── index.htm              # List view wrapper
│   │   ├── _list.php              # List partial with stats
│   │   └── preview.htm            # Detailed preview view
│   └── settings/
│       └── index.htm              # Settings page
├── views/
│   └── mail/
│       ├── submission_received.htm # Confirmation email
│       └── decision_notice.htm     # Accept/Reject email
└── updates/
    └── create_submissions_table.php # Database migration
```

## Database Schema

The `majos_conference_submissions` table stores:

- **Token**: Unique edit token (64 chars, unique)
- **Password Hash**: Bcrypt hashed password
- **Token Expires At**: Edit link expiration timestamp
- **Status**: submitted/accepted/rejected
- **Author Info**: name, email, affiliation, institution, etc.
- **Paper Details**: title, authors, abstract, keywords, category
- **File**: Attached PDF via `paper_file` relation
- **Metadata**: IP address, timestamps, decision info

## Multi-Step Form Implementation

The form uses progressive disclosure with client-side validation:

1. **Step Navigation**: Users click "Continue" to proceed, "Back" to return
2. **Validation**: Each step validates required fields before proceeding
3. **File Check**: Step 3 validates PDF type and size client-side
4. **Review**: Step 4 shows summary of all data before final submission
5. **Bot Protection**: Turnstile challenge on final step
6. **Server-side**: All data re-validated on submission

## Email Templates

### Submission Confirmation
Sent immediately after successful submission. Contains:
- Submission details
- Edit link with token
- Generated password
- Expiry date

### Decision Notice
Sent when admin accepts/rejects. Contains:
- Decision status (accepted/rejected)
- Submission details
- Next steps information

## Security Best Practices Applied

1. **CSRF Protection**: All forms include `csrf_token()`
2. **Rate Limiting**: Laravel's RateLimiter prevents brute force
3. **File Validation**: Both extension and MIME type checked
4. **Input Sanitization**: `strip_tags()` and `trim()` on all inputs
5. **SQL Injection Prevention**: Using Eloquent ORM
6. **XSS Prevention**: Output escaped with `e()` in views
7. **IDOR Protection**: Token-based edit links, session authentication
8. **Password Security**: Bcrypt hashing via `Hash::make()`
9. **Token Expiration**: Edit links expire after configurable days
10. **Logging**: All security events logged for audit

## Usage

### Frontend Pages

**Registration Page** (`/submit`):
```html
[conferenceRegistration]
useGlobalSettings = 1
editPage = "submission-edit"
```

**Edit Page** (`/submission/edit/:token`):
```html
[conferenceEditSubmission]
useGlobalSettings = 1
```

### Backend

- **Submissions List**: `/backend/majos/conference/submissions`
- **Submission Preview**: `/backend/majos/conference/submissions/preview/:id`
- **Settings**: `/backend/majos/conference/settings`

## Testing Checklist

- [ ] Submit a complete abstract through all 4 steps
- [ ] Verify email receipt with edit link and password
- [ ] Edit submission using token and password
- [ ] Attempt to edit with wrong password (should fail)
- [ ] Attempt to edit after token expiry (should fail)
- [ ] Admin: view list, filter by status
- [ ] Admin: view submission details
- [ ] Admin: accept submission (check email sent)
- [ ] Admin: reject submission (check email sent)
- [ ] Test rate limiting (multiple rapid submissions)
- [ ] Test file size limit (try >5MB PDF)
- [ ] Test file type validation (try non-PDF)
- [ ] Test Turnstile bot protection
- [ ] Test deadline enforcement (set past deadline)
- [ ] Test unique email constraint

## Maintenance

### Clearing Expired Tokens
Consider adding a scheduled task to clean up expired submissions:

```php
Submission::where('token_expires_at', '<', now())
    ->where('status', 'submitted')
    ->delete();
```

### Email Configuration
Configure mail settings in `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@afrirpa.org
MAIL_FROM_NAME="AFRIRPA 2027"
```

### Turnstile Setup
1. Create account at https://turnstile.cloudflare.com/
2. Add site with your domain
3. Copy site key and secret key to plugin settings
4. Enable on the registration page

## Changelog

### v2.0 (Refactored)
- Implemented multi-step form with progressive disclosure
- Added global settings via backend configuration
- Enhanced security with rate limiting, logging, token expiry
- Improved admin UI with formatted preview and status badges
- Better email templates with HTML formatting
- Proper OctoberCMS conventions and best practices
- Comprehensive validation and error handling

### v1.0 (Original)
- Basic submission form
- Simple edit functionality
- Admin list and preview
- Email notifications

## Support

For issues or questions, contact the development team or create an issue in the repository.
