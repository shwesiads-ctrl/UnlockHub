<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kill-switch endpoint
    |--------------------------------------------------------------------------
    | Set KILLSWITCH_URL in your .env to a URL that returns:
    |   { "active": true }   → site operates normally
    |   { "active": false }  → site is locked (maintenance page shown)
    |
    | Leave blank (default) to disable the feature entirely.
    | The endpoint is checked at most once per hour and cached.
    | If the endpoint is unreachable the site stays online (fail-open).
    |
    */
    'url' => env('KILLSWITCH_URL', ''),
];
