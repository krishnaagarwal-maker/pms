<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ProjectManagement Module Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the configuration settings for the ProjectManagement module.
    |
    */

    'name' => 'ProjectManagement',
    'version' => '1.0.0',
    'description' => 'ProjectManagement module for the application',
    'author' => 'Your Name',
    'email' => 'your.email@example.com',
    'website' => 'https://example.com',

    /*
    |--------------------------------------------------------------------------
    | Module Settings
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the settings for the ProjectManagement module.
    |
    */

    'settings' => [
        'enabled' => true,
        'debug' => false,
        'cache' => true,
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Dependencies
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the dependencies for the ProjectManagement module.
    |
    */

    'dependencies' => [
        // 'Core',
        // 'Auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Permissions
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the permissions for the ProjectManagement module.
    |
    */

    'permissions' => [
        'view' => 'View ProjectManagement',
        'create' => 'Create ProjectManagement',
        'edit' => 'Edit ProjectManagement',
        'delete' => 'Delete ProjectManagement',
    ],
]; 