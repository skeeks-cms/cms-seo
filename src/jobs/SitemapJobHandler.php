<?php
namespace skeeks\cms\seo\jobs;

use skeeks\cms\job\contracts\JobReporterInterface;
use skeeks\cms\job\handlers\AbstractJobHandler;
use skeeks\cms\job\exceptions\JobCancelledException;
use skeeks\cms\job\runtime\JobContext;
use skeeks\cms\seo\sitemap\SitemapGenerator;

/** Native job and manual CLI share the same sitemap generator. */
class SitemapJobHandler extends AbstractJobHandler
{
    public function run(JobContext $context, JobReporterInterface $reporter): void
    {
        $reporter->setTotal(null);
        $reporter->setStage('generate', 'Генерация sitemap');
        $processed = 0;
        $checkpoint = static function (int $urls) use ($reporter, &$processed) {
            if ($reporter->isCancelled()) {
                throw new JobCancelledException('Генерация sitemap отменена.');
            }
            $delta = $urls - $processed;
            if ($delta > 0) {
                $reporter->advance($delta);
                $reporter->countSuccess($delta);
                $processed = $urls;
            }
            $reporter->heartbeat();
        };
        $checkpoint(0);
        $result = \Yii::createObject(SitemapGenerator::class)->generate(
            static function ($message) use ($reporter) { $reporter->info($message); },
            $checkpoint
        );
        $reporter->setResult($result);
        $reporter->setStage('completed', "Карта сайта опубликована: {$result['urls']} URL, {$result['files']} файлов");
    }
}
