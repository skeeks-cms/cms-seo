<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link https://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 */

namespace skeeks\cms\seo\sitemap;

use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsSavedFilter;
use skeeks\cms\models\CmsTree;
use skeeks\cms\shop\models\ShopBrand;
use skeeks\cms\shop\models\ShopCollection;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

/**
 * Builds a static sitemap index using the settings of the SEO component.
 */
class SitemapGenerator extends Component
{
    /**
     * @var int maximum number of URLs in one sitemap part
     */
    public $maxUrlsPerFile = 10000;

    /**
     * @var string public directory alias
     */
    public $webroot = '@root/frontend/web';

    /**
     * @var string root sitemap file name
     */
    public $rootFilename = 'sitemap.xml';

    /**
     * @var string directory containing generated sitemap parts
     */
    public $partsDirectory = 'sitemaps';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->maxUrlsPerFile = (int) $this->maxUrlsPerFile;
        if ($this->maxUrlsPerFile < 1 || $this->maxUrlsPerFile > 50000) {
            throw new InvalidConfigException('maxUrlsPerFile must be between 1 and 50000.');
        }
    }

    /**
     * @param callable|null $progress
     * @return array
     */
    public function generate(callable $progress = null)
    {
        set_time_limit(0);

        $webroot = FileHelper::normalizePath(\Yii::getAlias($this->webroot));
        if (!is_dir($webroot)) {
            throw new InvalidConfigException("Webroot does not exist: {$webroot}");
        }

        $lockFile = FileHelper::normalizePath(\Yii::getAlias('@runtime/seo-sitemap.lock'));
        $lockHandle = fopen($lockFile, 'c');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockHandle)) {
                fclose($lockHandle);
            }
            throw new \RuntimeException('Another sitemap generation is already running.');
        }

        $generation = 'generated-'.gmdate('YmdHis').'-'.getmypid().'-'.substr(md5(uniqid('', true)), 0, 8);
        $partsRoot = FileHelper::normalizePath($webroot.DIRECTORY_SEPARATOR.$this->partsDirectory);
        $generationRoot = FileHelper::normalizePath($partsRoot.DIRECTORY_SEPARATOR.$generation);
        $rootFile = FileHelper::normalizePath($webroot.DIRECTORY_SEPARATOR.$this->rootFilename);
        $published = false;

        try {
            if (!FileHelper::createDirectory($generationRoot)) {
                throw new \RuntimeException("Unable to create sitemap directory: {$generationRoot}");
            }

            $result = $this->writeParts($generationRoot, $generation, $progress);
            $rootContent = $this->renderIndex($result['index']);
            $this->publishRootFile($rootFile, $rootContent);
            $published = true;

            try {
                $this->cleanupOldGenerations($partsRoot, $generation);
            } catch (\Throwable $e) {
                \Yii::warning($e, 'skeeks/seo/sitemap');
            }

            $result['rootFile'] = $rootFile;
            unset($result['index']);

            return $result;
        } catch (\Throwable $e) {
            if (!$published && is_dir($generationRoot)) {
                FileHelper::removeDirectory($generationRoot);
            }
            throw $e;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * @param string        $generationRoot
     * @param string        $generation
     * @param callable|null $progress
     * @return array
     */
    protected function writeParts($generationRoot, $generation, callable $progress = null)
    {
        $totalUrls = 0;
        $totalFiles = 0;
        $index = [];

        foreach ($this->itemGroups() as $groupName => $items) {
            $handle = null;
            $partNumber = 0;
            $partUrls = 0;

            try {
                foreach ($items as $item) {
                    if (!$handle || $partUrls >= $this->maxUrlsPerFile) {
                        if ($handle) {
                            $this->closePart($handle);
                        }

                        $partNumber++;
                        $totalFiles++;
                        $partUrls = 0;
                        $filename = sprintf('sitemap-%s-%05d.xml', $groupName, $partNumber);
                        $filePath = FileHelper::normalizePath($generationRoot.DIRECTORY_SEPARATOR.$filename);
                        $handle = fopen($filePath, 'wb');
                        if (!$handle) {
                            throw new \RuntimeException("Unable to create sitemap part: {$filePath}");
                        }
                        $this->write($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
                        $this->write($handle, "<!-- Created by Skeeks CMS -->\n");
                        $this->write($handle, "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n");
                        $index[] = [
                            'loc'     => $this->publicUrl($this->partsDirectory.'/'.$generation.'/'.$filename),
                            'lastmod' => date('c'),
                        ];

                        if ($progress) {
                            call_user_func($progress, "Generating {$filename}");
                        }
                    }

                    $this->write($handle, $this->renderUrl($item));
                    $partUrls++;
                    $totalUrls++;
                }

                if ($handle) {
                    $this->closePart($handle);
                    $handle = null;
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }

        return [
            'urls'  => $totalUrls,
            'files' => $totalFiles,
            'index' => $index,
        ];
    }

    /**
     * @return \Generator
     */
    protected function itemGroups()
    {
        yield 'trees' => $this->treeItems();
        yield 'savedfilters' => $this->savedFilterItems();

        foreach ($this->contentIds() as $contentId) {
            yield 'content-'.$contentId => $this->contentItems($contentId);
        }

        if (class_exists(ShopBrand::class)) {
            yield 'brands' => $this->brandItems();
        }
        if (class_exists(ShopCollection::class)) {
            yield 'collections' => $this->collectionItems();
        }
    }

    /**
     * @return \Generator
     */
    protected function treeItems()
    {
        $treeQuery = CmsTree::find()->cmsSite();
        $treeQuery->andWhere([
            'and',
            ['redirect_tree_id' => null],
            ['redirect_content_element_id' => null],
            ['redirect_saved_filter_id' => null],
            ['or', ['redirect' => null], ['redirect' => '']],
        ]);
        $treeQuery->andWhere([
            'and',
            ['canonical_tree_id' => null],
            ['canonical_content_element_id' => null],
            ['canonical_saved_filter_id' => null],
            ['or', ['canonical_link' => null], ['canonical_link' => '']],
        ]);
        $treeQuery->andWhere(['is_index' => 1]);

        if (!\Yii::$app->seo->is_allow_index_adult_content) {
            $treeQuery->andWhere(['is_adult' => 0]);
        }
        if (\Yii::$app->seo->treeTypeIds) {
            $treeQuery->andWhere(['tree_type_id' => \Yii::$app->seo->treeTypeIds]);
        }

        foreach ($treeQuery->orderBy(['level' => SORT_ASC, 'priority' => SORT_ASC])->each(200) as $tree) {
            $item = [
                'loc'     => $tree->absoluteUrl,
                'lastmod' => $this->lastModified($tree),
            ];
            if (\Yii::$app->seo->is_sitemap_priority) {
                $item['priority'] = $this->treePriority($tree);
            }
            yield $item;
        }
    }

    /**
     * @return \Generator
     */
    protected function savedFilterItems()
    {
        $savedFilterQuery = CmsSavedFilter::find()->cmsSite();
        if (!\Yii::$app->seo->is_allow_index_adult_content) {
            $savedFilterQuery->joinWith('cmsTree as cmsTree')->andWhere(['cmsTree.is_adult' => 0]);
        }
        foreach ($savedFilterQuery->each(200) as $savedFilter) {
            $item = [
                'loc'     => $savedFilter->absoluteUrl,
                'lastmod' => $this->lastModified($savedFilter),
            ];
            if (\Yii::$app->seo->is_sitemap_priority) {
                $item['priority'] = '0.8';
            }
            yield $item;
        }
    }

    /**
     * @return array
     */
    protected function contentIds()
    {
        $query = $this->contentElementQuery()->select(['content_id'])->groupBy(['content_id']);
        return $query->orderBy(['content_id' => SORT_ASC])->column();
    }

    /**
     * @param int $contentId
     * @return \Generator
     */
    protected function contentItems($contentId)
    {
        $elementQuery = $this->contentElementQuery()
            ->andWhere(['content_id' => $contentId])
            ->orderBy(['updated_at' => SORT_DESC, 'priority' => SORT_ASC]);

        foreach ($elementQuery->each(200) as $element) {
            $item = [
                'loc'     => $element->absoluteUrl,
                'lastmod' => $this->lastModified($element),
            ];
            if (\Yii::$app->seo->is_sitemap_priority) {
                $item['priority'] = '0.8';
            }
            yield $item;
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    protected function contentElementQuery()
    {
        $query = CmsContentElement::find()
            ->cmsSite()
            ->active()
            ->innerJoinWith('cmsContent as cmsContent')
            ->andWhere(['cmsContent.is_have_page' => 1]);

        if (\Yii::$app->seo->contentIds) {
            $query->andWhere(['content_id' => \Yii::$app->seo->contentIds]);
        }
        if (!\Yii::$app->seo->is_allow_index_adult_content) {
            $query->andWhere(['is_adult' => 0]);
        }

        return $query;
    }

    /**
     * @return \Generator
     */
    protected function brandItems()
    {
        foreach (ShopBrand::find()->each(200) as $brand) {
            yield [
                'loc'     => $brand->absoluteUrl,
                'lastmod' => $this->lastModified($brand),
            ];
        }
    }

    /**
     * @return \Generator
     */
    protected function collectionItems()
    {
        foreach (ShopCollection::find()->each(200) as $collection) {
            yield [
                'loc'     => $collection->absoluteUrl,
                'lastmod' => $this->lastModified($collection),
            ];
        }
    }

    /**
     * @param resource $handle
     */
    protected function closePart($handle)
    {
        $this->write($handle, "</urlset>\n");
        fclose($handle);
    }

    /**
     * @param resource $handle
     * @param string   $content
     */
    protected function write($handle, $content)
    {
        $length = strlen($content);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($content, $written));
            if ($bytes === false || $bytes === 0) {
                throw new \RuntimeException('Unable to write sitemap file.');
            }
            $written += $bytes;
        }
    }

    /**
     * @param array $item
     * @return string
     */
    protected function renderUrl(array $item)
    {
        $result = "  <url>\n    <loc>".$this->escape($item['loc'])."</loc>\n";
        if (isset($item['lastmod'])) {
            $result .= "    <lastmod>".$this->escape($item['lastmod'])."</lastmod>\n";
        }
        if (isset($item['priority'])) {
            $result .= "    <priority>".$this->escape($item['priority'])."</priority>\n";
        }
        return $result."  </url>\n";
    }

    /**
     * @param array $index
     * @return string
     */
    protected function renderIndex(array $index)
    {
        $result = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $result .= "<!-- Created by Skeeks CMS -->\n";
        $result .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($index as $item) {
            $result .= "  <sitemap>\n";
            $result .= "    <loc>".$this->escape($item['loc'])."</loc>\n";
            $result .= "    <lastmod>".$this->escape($item['lastmod'])."</lastmod>\n";
            $result .= "  </sitemap>\n";
        }
        return $result."</sitemapindex>\n";
    }

    /**
     * @param string $rootFile
     * @param string $content
     */
    protected function publishRootFile($rootFile, $content)
    {
        $temporaryFile = $rootFile.'.tmp.'.getmypid().'.'.substr(md5(uniqid('', true)), 0, 8);
        if (file_put_contents($temporaryFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write sitemap index: {$temporaryFile}");
        }

        if (@rename($temporaryFile, $rootFile)) {
            return;
        }

        $backupFile = $rootFile.'.previous';
        if (is_file($backupFile)) {
            unlink($backupFile);
        }
        if (is_file($rootFile) && !rename($rootFile, $backupFile)) {
            unlink($temporaryFile);
            throw new \RuntimeException("Unable to replace sitemap index: {$rootFile}");
        }
        if (!rename($temporaryFile, $rootFile)) {
            if (is_file($backupFile)) {
                rename($backupFile, $rootFile);
            }
            throw new \RuntimeException("Unable to publish sitemap index: {$rootFile}");
        }
        if (is_file($backupFile)) {
            unlink($backupFile);
        }
    }

    /**
     * Keeps the current and immediately preceding generation.
     *
     * @param string $partsRoot
     * @param string $currentGeneration
     */
    protected function cleanupOldGenerations($partsRoot, $currentGeneration)
    {
        $directories = [];
        foreach ((array) glob($partsRoot.DIRECTORY_SEPARATOR.'generated-*', GLOB_ONLYDIR) as $directory) {
            $directories[basename($directory)] = $directory;
        }
        krsort($directories, SORT_STRING);

        $keep = [$currentGeneration => true];
        foreach ($directories as $name => $directory) {
            if (isset($keep[$name])) {
                continue;
            }
            if (count($keep) < 2) {
                $keep[$name] = true;
                continue;
            }
            FileHelper::removeDirectory($directory);
        }
    }

    /**
     * @param string $relativePath
     * @return string
     */
    protected function publicUrl($relativePath)
    {
        // In a console application Url::home() calls Application::getHomeUrl(),
        // which exists only in the web application. Use the current site's
        // main domain, just like model absolute URLs do, with URL manager as a fallback.
        $urlManager = \Yii::$app->urlManager;
        $site = \Yii::$app->has('skeeks', true) ? \Yii::$app->skeeks->site : null;
        $hostInfo = $site ? $site->url : $urlManager->getHostInfo();
        $hostInfo = rtrim((string) $hostInfo, '/');
        if (!parse_url($hostInfo, PHP_URL_HOST)) {
            throw new InvalidConfigException('The console URL manager must have an absolute hostInfo.');
        }

        $baseUrl = trim((string) $urlManager->getBaseUrl(), '/');
        $rootUrl = $baseUrl === '' ? $hostInfo : $hostInfo.'/'.$baseUrl;

        return $rootUrl.'/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    /**
     * @param object $model
     * @return string
     */
    protected function lastModified($model)
    {
        $timestamp = (int) $model->updated_at;
        if (\Yii::$app->seo->sitemap_min_date && \Yii::$app->seo->sitemap_min_date > $timestamp) {
            $timestamp = (int) \Yii::$app->seo->sitemap_min_date;
        }
        return date('c', $timestamp);
    }

    /**
     * @param object $tree
     * @return string
     */
    protected function treePriority($tree)
    {
        $priorities = [
            0 => '1.0',
            1 => '0.8',
            2 => '0.7',
            3 => '0.6',
            4 => '0.5',
        ];
        return isset($priorities[$tree->level]) ? $priorities[$tree->level] : '0.4';
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
