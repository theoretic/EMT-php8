<?php
declare(strict_types=1);

/**
 * Golden-test profiles: profile name => options array for EMTypograph::setup(),
 * or a special builder marker for dynamic profiles.
 *
 * 'all-on' / 'all-off' are built dynamically from get_options_list() so the
 * profile follows the option registry automatically. 'OptAlign.layout' is a
 * string option and is excluded from boolean flipping.
 */

use EMT\EMTypograph;

return [
    'default' => [],

    'all-on' => (static function (): array {
        $opts = [];
        foreach (array_keys((new EMTypograph())->get_options_list()['all']) as $key) {
            if ($key === 'OptAlign.layout') {
                continue;
            }
            $opts[$key] = 'on';
        }
        return $opts;
    })(),

    'all-off' => (static function (): array {
        $opts = [];
        foreach (array_keys((new EMTypograph())->get_options_list()['all']) as $key) {
            if ($key === 'OptAlign.layout') {
                continue;
            }
            $opts[$key] = 'off';
        }
        return $opts;
    })(),

    'layout-class' => [
        'OptAlign.oa_oquote'  => 'on',
        'OptAlign.oa_obracket_coma' => 'on',
        'OptAlign.oa_oquote_extra' => 'on',
        'OptAlign.layout' => 'class',
    ],

    'dounicode' => [
        'Etc.unicode_convert' => 'on',
    ],
];
