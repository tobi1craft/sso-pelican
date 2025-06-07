<?php

return [
    'issuer' => env('SSO_ISSUER'),
    'audience' => env('SSO_AUDIENCE', env('APP_URL')),
    'public_key_endpoint' => env('SSO_PUBLIC_KEY_ENDPOINT'),
];