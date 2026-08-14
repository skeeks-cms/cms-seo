<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link https://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 */

namespace skeeks\cms\seo\console\controllers;

use skeeks\cms\seo\sitemap\SitemapGenerator;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Generates a static sitemap.xml and its URL-set parts.
 */
class SitemapController extends Controller
{
    /**
     * @var int maximum number of URLs in one sitemap part
     */
    public $maxUrls = 10000;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['maxUrls']);
    }

    /**
     * Generates sitemap.xml in the public web directory.
     *
     * @return int
     */
    public function actionGenerate()
    {
        try {
            $generator = new SitemapGenerator([
                'maxUrlsPerFile' => (int) $this->maxUrls,
            ]);

            $result = $generator->generate(function ($message) {
                $this->stdout($message."\n");
            });

            $this->stdout(
                "Sitemap generated: {$result['urls']} URLs, {$result['files']} files, {$result['rootFile']}\n",
                Console::FG_GREEN
            );

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("Sitemap generation failed: {$e->getMessage()}\n", Console::FG_RED);
            \Yii::error($e, 'skeeks/seo/sitemap');

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
