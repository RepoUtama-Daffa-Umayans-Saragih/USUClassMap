<?php

return [
    'storage_path'  => env('FOTO360P_STORAGE_PATH', storage_path('app/foto-360p')),
    'public_dir'    => 'images/foto-360p',
    'markdown_path' => env(
        'FOTO360P_MARKDOWN_PATH',
        dirname(base_path()) . DIRECTORY_SEPARATOR . 'DAFTAR_RUANGAN_DEEP_SCAN_360P.md'
    ),
];
