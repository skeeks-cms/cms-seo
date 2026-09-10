<?php
return [
    'components' => [
        'cmsAgent' => [
            'jobs' => [
                'seo.sitemap.generate' => [
                    'jobType'  => 'seo.sitemap.generate',
                    'name'     => 'Генерация sitemap',
                    'interval' => 3600 * 12,
                ],
            ],
        ],
        'jobRegistry' => [
            'types' => [
                'seo.sitemap.generate' => [
                    'type' => 'seo.sitemap.generate',
                    'title' => 'Генерация sitemap',
                    'handler' => \skeeks\cms\seo\jobs\SitemapJobHandler::class,
                    'queue' => 'maintenance',
                    'timeout' => 7200,
                    'leaseSeconds' => 120,
                    'maxAttempts' => 1,
                    'idempotent' => false,
                    'overlapPolicy' => 'skip',
                    // All sites of this installation share one public sitemap file.
                    'resourceKey' => static function () { return 'seo:sitemap:public-root'; },
                    'dedupKey' => static function () { return 'seo:sitemap:public-root'; },
                ],
            ],
        ],
    ],
];
