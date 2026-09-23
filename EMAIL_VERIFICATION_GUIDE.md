# Email Verification Using PHPMailer - Implementation Guide

## Overview

This is a complete email verification system using PHPMailer integrated with Laravel for the OJT Monitoring System. The system supports:

- **Email Verification OTP** - Verify user email during registration
- **Password Reset OTP** - Send OTP for secure password resets
- **Welcome Emails** - Send welcome emails to new users
- **Document Submission Notifications** - Notify users of successful submissions

## Installation & Setup

### 1. PHPMailer Installation ✓
PHPMailer v7.1.1 has been installed via Composer:
```bash
composer require phpmailer/phpmailer
```

### 2. Database Setup ✓
The OTP table has been created with the migration:
- Email address
- OTP code (6 digits)
- OTP type (verification or password_reset)
- Expiration time (10 minutes by default)
- Usage tracking

## Configuration

### Environment Variables (.env)
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="OJT Monitoring System"
```

**For Gmail:**
- Enable 2-Step Verification
- Generate an App Password: https://myaccount.google.com/apppasswords
- Use the 16-character app password in `MAIL_PASSWORD`

### SMTP Configuration
The system uses Gmail SMTP by default:
- **Host:** smtp.gmail.com
- **Port:** 587 (TLS)
- **Encryption:** STARTTLS

## File Structure

### Created Files:

1. **`app/Services/EmailService.php`**
   - Main email service using PHPMailer
   - Methods for different email types
   - HTML email templates
   - Error handling and logging

2. **`app/Models/OTP.php`**
   - OTP model with validation methods
   - Methods to generate and verify OTP codes
   - Automatic expiration checking

3. **`database/migrations/2024_09_08_000000_create_otps_table.php`**
   - Creates the `otps` table
   - Indexes for performance

4. **Updated `app/Http/Controllers/InternAuthController.php`**
   - Added OTP methods
   - Email verification flows
   - Password reset flows

5. **Updated `routes/web.php`**
   - Added OTP-related routes
   - Password reset routes

## API Endpoints

### Email Verification

#### Send OTP
```
POST /intern/otp/send
Content-Type: application/json

{
  "email": "intern@example.com"
}

Response:
{
  "success": true,
  "message": "OTP sent to your email address.",
  "otp_fallback": "123456" // Only in development
}
```

#### Verify OTP
```
POST /intern/verify-otp
Content-Type: application/x-www-form-urlencoded

email=intern@example.com&otp=123456

Response:
Redirect to login with success message
```

#### Resend OTP
```
POST /intern/resend-otp
Content-Type: application/json

{
  "email": "intern@example.com"
}

Response:
{
  "success": true,
  "message": "New OTP sent to your email address.",
  "otp_fallback": "654321" // Only in development
}
```

### Password Reset

#### Request Password Reset OTP
```
POST /intern/password/forgot
Content-Type: application/json

{
  "email": "intern@example.com"
}

Response:
{
  "success": true,
  "message": "Password reset OTP sent to your email.",
  "otp_fallback": "123456"
}
```

#### Verify Password Reset OTP
```
POST /intern/password/verify-otp
Content-Type: application/json

{
  "email": "intern@example.com",
  "otp": "123456"
}

Response:
{
  "success": true,
  "message": "OTP verified successfully."
}
```

#### Resend Password Reset OTP
```
POST /intern/password/resend-otp
Content-Type: application/json

{
  "email": "intern@example.com"
}

Response:
{
  "success": true,
  "message": "New OTP sent to your email.",
  "otp_fallback": "654321"
}
```

#### Reset Password
```
POST /intern/password/reset
Content-Type: application/json

{
  "email": "intern@example.com",
  "otp": "123456",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}

Response:
{
  "success": true,
  "message": "Password reset successfully. You can now login with your new password."
}
```

## Usage Examples

### Using EmailService in Code

```php
<?php

use App\Services\EmailService;
use App\Models\OTP;

// Send verification OTP
$emailService = new EmailService();
$otp = OTP::createOTP('user@example.com', 'verification', 10);
$emailService->sendOTPEmail('user@example.com', $otp->otp_code, 'John Doe');

// Send password reset OTP
$otp = OTP::createOTP('user@example.com', 'password_reset', 10);
$emailService->sendPasswordResetOTPEmail('user@example.com', $otp->otp_code, 'John Doe');

// Send welcome email
$emailService->sendWelcomeEmail(
    'user@example.com',
    'John Doe',
    route('intern.login')
);

// Send document submission notification
$emailService->sendDocumentSubmissionEmail(
    'user@example.com',
    'John Doe',
    'Resume - PDF'
);

// Send custom email
$htmlBody = '<h1>Welcome!</h1><p>Your custom HTML content here.</p>';
$emailService->sendEmail(
    'user@example.com',
    'Custom Subject',
    $htmlBody,
    'John Doe'
);
```

### Working with OTP Model

```php
<?php

use App\Models\OTP;

// Create OTP (automatically invalidates old ones)
$otp = OTP::createOTP('user@example.com', 'verification', 10);

// Verify OTP code
$otp = OTP::verifyOTP('user@example.com', '123456', 'verification');
if ($otp) {
    // OTP is valid
    $otp->markAsUsed();
} else {
    // OTP is invalid or expired
}

// Find valid OTP
$otp = OTP::findValidOTP('user@example.com', 'verification');

// Generate just the code
$code = OTP::generateOTPCode(); // Returns "123456"
```

## Email Templates

The system includes pre-designed HTML email templates for:

1. **OTP Verification Email**
   - Professional design with gradient header
   - Large, easy-to-read OTP code
   - Expiration warning
   - Security notice

2. **Password Reset OTP Email**
   - Red gradient header for urgency
   - Same OTP display
   - Security warning about not sharing code

3. **Welcome Email**
   - Green gradient header
   - Feature list
   - Login button
   - Professional footer

4. **Document Submission Notification**
   - Blue gradient header
   - Success badge
   - Processing information
   - Support contact info

## Security Features

- ✓ 6-digit OTP codes (1 million possibilities)
- ✓ 10-minute expiration time (configurable)
- ✓ One-time use enforcement
- ✓ Automatic invalidation of previous OTPs
- ✓ Database indexes for performance
- ✓ STARTTLS encryption for email transport
- ✓ Hashed passwords (Argon2id)
- ✓ Error logging (never exposes sensitive info)

## Error Handling

All email methods include try-catch error handling:
- Errors are logged to `storage/logs/laravel.log`
- User-friendly error messages
- Graceful fallback handling
- Development mode OTP fallback in responses

## Development Mode

In development/local environment (`APP_ENV=local`), OTP codes are returned in API responses for testing:

```php
'otp_fallback' => env('APP_ENV') === 'local' ? $otp->otp_code : null
```

This allows easy testing without access to the email inbox.

## Production Checklist

- [ ] Enable 2FA on email account
- [ ] Generate app-specific password
- [ ] Update `.env` with correct credentials
- [ ] Test email sending before going live
- [ ] Set `APP_ENV=production`
- [ ] Disable OTP fallback in production
- [ ] Configure error logging
- [ ] Set up email rate limiting
- [ ] Monitor `storage/logs/laravel.log`

## Troubleshooting

### Emails Not Sending

1. Check `.env` file for correct credentials
2. Verify Gmail app password (not regular password)
3. Check that 2-Step Verification is enabled
4. Review logs: `tail -f storage/logs/laravel.log`
5. Test connection: `php artisan tinker` and test manually

### Connection Refused
- Check host and port settings
- Verify firewall allows port 587
- Ensure STARTTLS is supported

### Authentication Failed
- Verify email and password in `.env`
- Ensure app password is 16 characters
- No spaces in app password

### Slow Email Sending
- Consider using queue with: `QUEUE_CONNECTION=database`
- Monitor email server response times
- Check network connectivity

## Testing

To test the email functionality:

```php
<?php

use App\Services\EmailService;
use App\Models\OTP;

// Create test OTP
$otp = OTP::createOTP('test@example.com', 'verification', 10);

// Send test email
$emailService = new EmailService();
$result = $emailService->sendOTPEmail('test@example.com', $otp->otp_code, 'Test User');

// Check result
if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Email failed!";
}
```

## Future Enhancements

- SMS OTP support
- Email queue job processing
- OTP attempt rate limiting
- Multi-language email templates
- Email delivery tracking
- Webhook notifications
- Custom email branding

## Support

For issues or questions about the email verification system, check:
- Application logs: `storage/logs/laravel.log`
- PHPMailer documentation: https://github.com/PHPMailer/PHPMailer
- Laravel Mail documentation: https://laravel.com/docs/mail

---

**System Version:** 1.0  
**Last Updated:** September 8, 2024  
**PHPMailer Version:** 7.1.1
