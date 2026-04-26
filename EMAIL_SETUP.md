# Email Setup Guide for APES UDOM

This guide explains how to configure email sending for the APES UDOM system using SendGrid.

## OTP Email Flow

The system uses OTP (One-Time Password) codes for:
1. **Email Verification** - When users register, a 6-digit code is sent to verify their email
2. **Password Reset** - When users forget their password, a 6-digit code is sent to reset it

## Configuration

### 1. SendGrid Account Setup

1. Create a SendGrid account at https://sendgrid.com
2. Verify your sender identity (either single sender or domain authentication)
3. Create an API key with "Mail Send" permissions

### 2. Environment Variables

Update your `.env` file with your SendGrid credentials. There are two ways to configure SendGrid:

**Option 1: Using the 'sendgrid' mailer (recommended)**

```env
MAIL_MAILER=sendgrid
SENDGRID_HOST=smtp.sendgrid.net
SENDGRID_PORT=587
SENDGRID_ENCRYPTION=tls
SENDGRID_USERNAME=apikey
SENDGRID_API_KEY=your_sendgrid_api_key_here
MAIL_FROM_ADDRESS=noreply@em4632.codagenz.com
MAIL_FROM_NAME="${APP_NAME}"
MAIL_REPLY_TO_ADDRESS=codagenz10@gmail.com
MAIL_REPLY_TO_NAME="APES UDOM Support"
```

**Option 2: Using standard SMTP**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key_here
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@em4632.codagenz.com
MAIL_FROM_NAME="${APP_NAME}"
MAIL_REPLY_TO_ADDRESS=codagenz10@gmail.com
MAIL_REPLY_TO_NAME="APES UDOM Support"
```

**Important:** Replace `your_sendgrid_api_key_here` with your actual SendGrid API key.

### 3. Verify Configuration

After updating `.env`, clear the config cache:

```bash
php artisan config:clear
```

### 4. Test Email Sending

You can test email functionality by registering a new user or requesting a password reset.

For local testing without SendGrid, you can use the log driver:

```env
MAIL_MAILER=log
```

Emails will be written to `storage/logs/laravel.log` instead of being sent.

## Troubleshooting

### Emails Not Sending

1. **Check logs**: Look at `storage/logs/laravel.log` for email-related errors
2. **Verify config**: Run `php artisan config:show mail` to verify settings
3. **Check SendGrid dashboard**: Verify emails are being received by SendGrid
4. **Check spam folders**: Emails might land in spam/junk folders

### Queue Issues (Fixed)

Previously, emails were queued and required a queue worker. This has been fixed - emails now send synchronously (immediately) for better reliability.

### Common Errors

**"Undefined array key 'sendgrid'"**
- Ensure the `sendgrid` mailer is properly configured in `config/mail.php`
- The custom sendgrid mailer has been replaced with standard SMTP configuration

**"Failed to authenticate on SMTP server"**
- Verify your SendGrid API key is correct
- Ensure you're using `apikey` as the username (not your SendGrid username)
- Check that your API key has Mail Send permissions

**"From address does not match verified sender"**
- In SendGrid, verify your sender identity
- The MAIL_FROM_ADDRESS must match a verified sender in SendGrid

## Email Templates

Email templates are located in:
- `resources/views/emails/otp-verification.blade.php` - Email verification OTP
- `resources/views/emails/password-reset-otp.blade.php` - Password reset OTP
- `resources/views/emails/otp-verification-text.blade.php` - Plain text version

## API Endpoints

### Email Verification Flow

1. **Register** - `POST /api/v1/auth/register` - Sends verification email automatically
2. **Verify Code** - `POST /api/v1/auth/verify-email-code` - Verify with OTP code
3. **Resend Code** - `POST /api/v1/auth/verify-email/resend` - Request new code

### Password Reset Flow

1. **Request Reset** - `POST /api/v1/auth/forgot-password` - Sends reset OTP
2. **Reset Password** - `POST /api/v1/auth/reset-password` - Use OTP to reset password

## Security Features

- Codes are 6-digit numbers
- Codes expire after 10 minutes
- Codes are stored hashed in the database
- Invalid attempts clear the code immediately
- Rate limiting: 1 request per minute per user

## Changes Made

1. Removed `ShouldQueue` from OTP Mailables - emails send immediately
2. Fixed `sendPasswordResetNotification` to avoid link-based resets
3. Updated `.env.example` with proper SendGrid SMTP configuration
4. Added proper rate limiting to forgot password endpoint
