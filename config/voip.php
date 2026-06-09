<?php

return [
    'ari_host' => env('ASTERISK_ARI_HOST', '127.0.0.1'),
    'ari_port' => env('ASTERISK_ARI_PORT', 8088),
    'ari_user' => env('ASTERISK_ARI_USER', 'medussa'),
    'ari_pass' => env('ASTERISK_ARI_PASS', ''),

    'ami_host' => env('ASTERISK_AMI_HOST', '127.0.0.1'),
    'ami_port' => env('ASTERISK_AMI_PORT', 5038),
    'ami_user' => env('ASTERISK_AMI_USER', 'medussa'),
    'ami_pass' => env('ASTERISK_AMI_PASS', ''),
];
