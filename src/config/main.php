<?php
return [

    'bootstrap' => ['seo'],

    'components' => [
        'seo' => [
            'class' => 'skeeks\cms\seo\CmsSeoComponent',
        ],

        'i18n' => [
            'translations' => [
                'skeeks/seo' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@skeeks/cms/seo/messages',
                    'fileMap' => [
                        'skeeks/seo' => 'main.php',
                    ],
                ]
            ]
        ],

        'urlManager' => [
            'rules' => [
                'robots.txt' => '/seo/robots/on-request',
                'sitemap.xml' => '/seo/sitemap/on-request',
            ]
        ],

        'cmsToolbar' => [
            'panels' => [
                'seo' => 'skeeks\cms\seo\toolbar\panels\SeoPanel'
            ],
        ],
    ],

    'modules' => [
        'seo' => [
            'class' => 'skeeks\cms\seo\CmsSeoModule',
        ]
    ]
];