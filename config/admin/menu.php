<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */
return
[
    'other' =>
    [
        'items' =>
        [
            [
                "label"     => \Yii::t('skeeks/seo', "Seo"),
                "img"       => ['\skeeks\cms\seo\assets\CmsSeoAsset', 'icons/seo.png'],

                'items' =>
                [
                    [
                        "label" => \Yii::t('skeeks/seo', "Settings"),
                        "url"   => ["cms/admin-settings", "component" => 'skeeks\cms\seo\CmsSeoComponent'],
                        "img"       => ['skeeks\cms\assets\CmsAsset', 'images/icons/settings-big.png'],
                        "activeCallback"       => function($adminMenuItem)
                        {
                            return (bool) (\Yii::$app->request->getUrl() == $adminMenuItem->getUrl());
                        },
                    ],
                ],
            ],
        ]
    ]
];