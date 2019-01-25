<?php
return [
    'components' => [

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
    ],
];