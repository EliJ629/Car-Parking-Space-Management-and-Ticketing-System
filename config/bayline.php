<?php

return [

    /*
    | Extra plate formats, as regular expressions on the compact plate key (upper case, no spaces),
    | for special plates the Manager has verified with the LTO. The standard, legacy, vintage and
    | protocol (Executive Order No. 56, 2024) formats are built into App\Support\PlateNumber.
    | Example: '/^SENATE[0-9]{1,2}$/'
    */
    'plate_special_patterns' => array_values(array_filter(array_map('trim', explode('|', (string) env('PLATE_SPECIAL_PATTERNS', ''))))),

];
