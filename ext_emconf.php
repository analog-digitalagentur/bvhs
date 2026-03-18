<?php

$EM_CONF['bvhs'] = [
    'title' => 'bvhs',
    'description' => 'ViewHelpers for TYPO3',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Bo\\Bvhs\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'author' => 'Boris Schauer',
    'author_email' => 'me@bschauer.de',
    'author_company' => 'bschauer, analog',
    'version' => '2.0.0',
];
