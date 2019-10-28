<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_hofff_language_relations_page'] = [
    'config' => [
        'sql' => [
            'keys' => [
                'item_id,related_item_id' => 'primary',
                'related_item_id' => 'index',
            ],
        ],
    ],
    'fields' => [
        'item_id'         => ['sql' => 'int(10) unsigned NOT NULL'],
        'related_item_id' => ['sql' => 'int(10) unsigned NOT NULL'],
    ],
];
