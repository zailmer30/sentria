<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => true,
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'lowercase_usernames' => true,

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    'paths' => [
        'login' => null,
        'logout' => null,
        'password' => [
            'request' => null,
            'reset' => null,
            'email' => null,
            'update' => null,
            'confirm' => null,
            'confirmation' => null,
        ],
        'register' => null,
        'verification' => [
            'notice' => null,
            'verify' => null,
            'send' => null,
        ],
        'user-profile-information' => [
            'update' => null,
        ],
        'user-password' => [
            'update' => null,
        ],
        'two-factor' => [
            'login' => null,
            'enable' => null,
            'confirm' => null,
            'disable' => null,
            'qr-code' => null,
            'secret-key' => null,
            'recovery-codes' => null,
        ],
    ],

    'redirects' => [
        'login' => '/dashboard',
        'logout' => '/login',
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => null,
    ],

    /*
    | Password reset tokens use cryptographically random values (Laravel default),
    | never ULIDs. Lockout: 5 attempts / minute via the `login` rate limiter.
    | Session idle timeout: SESSION_LIFETIME (default 60 minutes).
    */
    'features' => [
        Features::resetPasswords(),
        // Registration is secretariat/admin-provisioned; no public self-signup.
        // Features::registration(),
        // Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        // Optional later: Features::twoFactorAuthentication(),
    ],

];
