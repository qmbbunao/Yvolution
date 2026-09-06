<?php
/**
 * Business location and manual payment details.
 * Edit these to match your actual shop info.
 */

return [

    'location' => [
        'address'     => 'Sta. Rita, Concepcion, Tarlac, Philippines',
        // Approximate coordinates for Concepcion, Tarlac — adjust to your exact shop pin
        // by searching your address on openstreetmap.org and copying the lat/lng from the URL.
        'lat'         => 15.3167,
        'lng'         => 120.6333,
    ],

    'payment' => [
        'gcash' => [
            'number'       => '0917-000-0000',   // replace with your real GCash number
            'account_name' => 'Yvolution Custom Apparel',
        ],
        'bank' => [
            'bank_name'    => 'BDO Unibank',      // replace with your real bank
            'account_name' => 'Yvolution Custom Apparel',
            'account_number' => '0000-0000-0000',
        ],
    ],

];
