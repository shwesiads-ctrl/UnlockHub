<?php

/*
| Domain lock / serial key.
| The owner's license server (separate app) signs every answer with its secret key;
| this site only holds the PUBLIC key, so answers can't be faked.
|
| Before giving the script to buyers: open the license server › Settings, copy the
| public key and paste it into 'public_key' below. While it is empty the lock is off.
*/

return [
    'server' => 'https://owner.myominaung.xyz',

    // Paste the license server's public key here (base64, from its Settings page).
    'public_key' => '',

    // Usually entered in Admin › License (or by the installer, saved as LICENSE_KEY in .env).
    'key' => env('LICENSE_KEY'),

    // A good answer is valid for 7 days (set by the server); after that the site keeps
    // working this many extra days if the server can't be reached.
    'grace_days' => 7,

    // How often a running site asks the server again.
    'check_hours' => 24,
];
