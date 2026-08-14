<?php
return [
    'components' => [
        'cmsAgent' => [
            'commands' => [
                'seo/sitemap/generate' => [
                    'class'    => \skeeks\cms\agent\CmsAgent::class,
                    'name'     => ['skeeks/seo', 'Generate sitemap.xml'],
                    'interval' => 3600 * 12,
                ],
            ],
        ],
    ],
];
