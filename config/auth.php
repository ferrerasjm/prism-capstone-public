<?php

use App\Models\User;

return [

    'bootstrap_admin' => [
        'email' => env('PRISM_ADMIN_EMAIL'),
        'password' => env('PRISM_ADMIN_PASSWORD'),
    ],

    'login_otp' => [
        'expire' => (int) env('LOGIN_OTP_EXPIRE', 10),
        'resend_cooldown' => (int) env('LOGIN_OTP_RESEND_COOLDOWN', 60),
        'console' => (bool) env('LOGIN_OTP_CONSOLE', env('APP_DEBUG', false)),
        // Kill switch for actually emailing the OTP, independent of MAIL_MAILER
        // and any cached config. Turn this off during testing so users aren't
        // bombarded with OTP emails on every login; the code still gets shown
        // on the verification page (see AuthController::startLoginOtpChallenge)
        // so login keeps working without email delivery.
        'mail_enabled' => (bool) env('LOGIN_OTP_MAIL_ENABLED', true),
        // Temporary escape hatch for accounts whose registered email inbox
        // isn't reachable (e.g. a placeholder seeded address). These emails
        // still go through the full OTP challenge, they just get the code
        // shown on the verification page instead of only emailed. Comma-separated.
        'reveal_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOGIN_OTP_REVEAL_EMAILS', ''))
        ))),
    ],

    'password_otp' => [
        'expire' => (int) env('PASSWORD_OTP_EXPIRE', 10),
        'resend_cooldown' => (int) env('PASSWORD_OTP_RESEND_COOLDOWN', 60),
        'max_attempts' => (int) env('PASSWORD_OTP_MAX_ATTEMPTS', 5),
        'console' => (bool) env('PASSWORD_OTP_CONSOLE', env('APP_DEBUG', false)),
        // Same kill switch pattern as login_otp.mail_enabled: off during local dev so
        // changing your password doesn't require a working mail inbox, on for tests.
        'mail_enabled' => (bool) env('PASSWORD_OTP_MAIL_ENABLED', true),
    ],

    'mobile_otp' => [
        // Gradual-rollout kill switch: while off, POST /api/auth/login keeps
        // returning {access_token, user} directly, same as before OTP existed.
        'enabled' => (bool) env('MOBILE_OTP_ENABLED', false),
        'expire' => (int) env('MOBILE_OTP_EXPIRE', 5),
        'resend_cooldown' => (int) env('MOBILE_OTP_RESEND_COOLDOWN', 30),
        'max_attempts' => (int) env('MOBILE_OTP_MAX_ATTEMPTS', 5),
        'mail_enabled' => (bool) env('MOBILE_OTP_MAIL_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'account_setup' => [
        'expire' => env('AUTH_ACCOUNT_SETUP_EXPIRE', 4320),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
