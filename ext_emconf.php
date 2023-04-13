<?php

/**
 * Extension Manager/Repository config file for ext "bvhs".
 */
$EM_CONF['bvhs'] = [
    'title' => 'bvhs',
    'description' => 'ViewHelpers for TYPO3',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '*',
        ]
    ],
    'autoload' => [
        'psr-4' => [
            'Bo\\Bvhs\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Boris Schauer',
    'author_email' => 'me@bschauer.de',
    'author_company' => 'bschauer, analog',
    'version' => '1.0.0',
];
