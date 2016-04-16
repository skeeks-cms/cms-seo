<?php
return [

    'bootstrap' => ['seo'],

    'components' =>
    [
        'seo' => [
            'class'     => 'skeeks\cms\seo\CmsSeoComponent',
        ],

        'i18n' => [
            'translations' =>
            [
                'skeeks/seo' => [
                    'class'             => 'yii\i18n\PhpMessageSource',
                    'basePath'          => '@skeeks/cms/seo/messages',
                    'fileMap' => [
                        'skeeks/seo' => 'main.php',
                    ],
                ]
            ]
        ],

        /*'urlManager' => [
            'rules' => [
                'search'                                => 'cmsSearch/result',
            ]
        ]*/
    ],

    'modules' =>
    [
        'seo' => [
            'class'         => 'skeeks\cms\seo\CmsSeoModule',
        ]
    ]

];