<?php
// Isolated filesystem + SQLite regression; never boots the site's config.
require (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor/autoload.php';
require (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor/yiisoft/yii2/Yii.php';
require __DIR__.'/../src/sitemap/SitemapGenerator.php';
require __DIR__.'/../src/jobs/SitemapJobHandler.php';

use skeeks\cms\seo\sitemap\SitemapGenerator;
use skeeks\cms\seo\jobs\SitemapJobHandler;
use skeeks\cms\job\contracts\JobReporterInterface;
use skeeks\cms\job\models\CmsJobRunArtifact;
use skeeks\cms\job\runtime\JobContext;
use skeeks\cms\job\exceptions\JobCancelledException;

function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
class FixtureSitemap extends SitemapGenerator
{
    public $urlCount = 5, $failPublicationAt = 0, $replacements = 0;
    protected function itemGroups() {
        yield 'fixture' => (function () {
            for ($i = 0; $i < $this->urlCount; $i++) { yield ['loc' => 'https://example.test/page-'.$i]; }
        })();
    }
    protected function replacePart($source, $destination) {
        if (++$this->replacements === $this->failPublicationAt) {
            throw new RuntimeException('Injected publication failure');
        }
        if (is_file($destination)) { check(simplexml_load_file($destination) !== false, 'Old live part is complete XML'); }
        parent::replacePart($source, $destination);
        check(simplexml_load_file($destination) !== false, 'Replacement is complete XML');
    }
    protected function publicUrl($path) { return 'https://example.test/'.$path; }
}
class FixtureReporter implements JobReporterInterface
{
    public $processed = 0, $success = 0, $total = 99, $result, $cancel = false, $stage;
    public function setStage(string $stage, ?string $message = null): void { $this->stage = $stage; }
    public function setTotal(?int $total): void { $this->total = $total; }
    public function advance(int $by = 1): void { $this->processed += $by; }
    public function countSuccess(int $by = 1): void { $this->success += $by; }
    public function countWarning(int $by = 1): void {}
    public function countError(int $by = 1): void {}
    public function countSkipped(int $by = 1): void {}
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
    public function itemError(string $itemType, $itemId, string $message, array $row = []): void {}
    public function heartbeat(): void {}
    public function isCancelled(): bool { return $this->cancel; }
    public function addArtifact(string $type, string $path, array $options = []): CmsJobRunArtifact { throw new RuntimeException('Unexpected artifact'); }
    public function setResult(array $result): void { $this->result = $result; }
}
$temporary = sys_get_temp_dir().'/seo-native-test-'.bin2hex(random_bytes(8));
mkdir($temporary, 0700);
new yii\console\Application(['id' => 'seo-native-test', 'basePath' => $temporary, 'runtimePath' => $temporary]);
Yii::setAlias('@runtime', $temporary);
Yii::$container->set(SitemapGenerator::class, ['class' => FixtureSitemap::class, 'webroot' => $temporary, 'maxUrlsPerFile' => 2]);
try {
    $handler = new SitemapJobHandler();
    $reporter = new FixtureReporter();
    $handler->run(new JobContext(), $reporter);
    check($reporter->processed === 5 && $reporter->success === 5, 'URL counters');
    check($reporter->total === null && $reporter->stage === 'completed', 'Unknown total and completed stage');
    check($reporter->result['files'] === 3, 'Part splitting');
    $original = file_get_contents($temporary.'/sitemap.xml');
    check(substr_count($original, '<sitemap>') === 3, 'Published index');
    $generator = Yii::createObject(SitemapGenerator::class);
    try {
        $generator->generate(null, static function ($count) {
            if ($count >= 2) { throw new JobCancelledException('Test cancellation'); }
        });
        throw new RuntimeException('Cancellation was ignored');
    } catch (JobCancelledException $expected) {}
    check(file_get_contents($temporary.'/sitemap.xml') === $original, 'Cancellation preserves published index');
    check(!is_dir($temporary.'/sitemaps/.building'), 'Cancelled staging removed');
    $reporter->cancel = true;
    try { $handler->run(new JobContext(), $reporter); throw new RuntimeException('Pre-start cancellation ignored'); }
    catch (JobCancelledException $expected) {}
    $generator->generate(static function ($message) {});
    $generator->generate();
    check(count(glob($temporary.'/sitemaps/sitemap-*.xml')) === 3, 'Repeated runs reuse stable files');
    check(count(glob($temporary.'/sitemaps/generated-*')) === 0, 'No public generation directories');
    check(strpos(file_get_contents($temporary.'/sitemap.xml'), 'generated-') === false, 'Index has stable URLs');
    $xml = new DOMDocument();
    check($xml->loadXML($original), 'Original XML index valid');
    foreach ($xml->getElementsByTagName('loc') as $loc) {
        $path = $temporary.parse_url($loc->textContent, PHP_URL_PATH);
        check(is_file($path) && simplexml_load_file($path) !== false, 'Original addresses survive repeated generation');
    }
    $generator->urlCount = 3;
    $generator->generate();
    check(substr_count(file_get_contents($temporary.'/sitemap.xml'), '<sitemap>') === 2, 'Index shrinks with data');
    check(count(simplexml_load_file($temporary.'/sitemaps/sitemap-fixture-00003.xml')->children()) === 0, 'Removed part remains empty XML');
    check(!is_dir($temporary.'/sitemaps/.building'), 'Successful staging removed');
    $lastIndex = file_get_contents($temporary.'/sitemap.xml');
    $generator->urlCount = 7;
    $generator->replacements = 0;
    $generator->failPublicationAt = 2;
    try { $generator->generate(); throw new LogicException('Publication failure ignored'); }
    catch (RuntimeException $expected) { check($expected->getMessage() === 'Injected publication failure', 'Expected publication failure'); }
    check(file_get_contents($temporary.'/sitemap.xml') === $lastIndex, 'Part publication failure preserves index');
    check(!is_dir($temporary.'/sitemaps/.building'), 'Failed publication staging removed');
    foreach (glob($temporary.'/sitemaps/sitemap-*.xml') as $path) {
        check(simplexml_load_file($path) !== false, 'Failure leaves complete live XML');
    }
    // Recover after a crash that left a partial build; old versioned paths are untouched.
    mkdir($temporary.'/sitemaps/.building');
    file_put_contents($temporary.'/sitemaps/.building/incomplete.xml', '<broken');
    mkdir($temporary.'/sitemaps/generated-legacy');
    file_put_contents($temporary.'/sitemaps/generated-legacy/keep.xml', '<urlset/>');
    $generator->failPublicationAt = 0;
    $generator->urlCount = 0;
    $generator->generate();
    check(substr_count(file_get_contents($temporary.'/sitemap.xml'), '<sitemap>') === 0, 'Empty current index');
    foreach (glob($temporary.'/sitemaps/sitemap-*.xml') as $path) {
        check(count(simplexml_load_file($path)->children()) === 0, 'Obsolete parts contain no stale URLs');
    }
    check(is_file($temporary.'/sitemaps/generated-legacy/keep.xml'), 'Legacy generations require separate migration');
    check(!is_dir($temporary.'/sitemaps/.building'), 'Crash leftovers cleaned');
    $generator->urlCount = 5;
    $generator->generate();
    check(substr_count(file_get_contents($temporary.'/sitemap.xml'), '<sitemap>') === 3, 'Parts can grow again');
    require __DIR__.'/../src/migrations/m260910_100000_native_seo_sitemap.php';
    $db = new yii\db\Connection(['dsn' => 'sqlite::memory:']);
    $db->open();
    $db->createCommand('CREATE TABLE cms_agent (id INTEGER PRIMARY KEY, cms_site_id INTEGER, name TEXT, is_system INTEGER, is_running INTEGER, job_type TEXT, job_payload TEXT, is_active INTEGER, interval INTEGER, last_exec INTEGER, next_exec INTEGER)')->execute();
    $row = ['id'=>7, 'cms_site_id'=>1, 'name'=>'seo/sitemap/generate', 'is_system'=>1, 'is_running'=>0, 'job_type'=>null, 'job_payload'=>null, 'is_active'=>0, 'interval'=>77, 'last_exec'=>100, 'next_exec'=>177];
    $db->createCommand()->insert('cms_agent', $row)->execute();
    $migration = new m260910_100000_native_seo_sitemap(['db'=>$db]);
    $migration->safeUp();
    $saved = $db->createCommand('SELECT * FROM cms_agent WHERE id=7')->queryOne();
    check($saved['name'] === 'job:seo.sitemap.generate' && $saved['job_type'] === 'seo.sitemap.generate', 'Native schedule');
    foreach (['id','cms_site_id','is_active','interval','last_exec','next_exec'] as $field) { check($saved[$field] == $row[$field], 'Preserved '.$field); }
    $db->createCommand()->insert('cms_agent', array_merge($row, ['id'=>8]))->execute();
    try { $migration->safeUp(); throw new LogicException('Duplicate accepted'); }
    catch (RuntimeException $expected) { check(strpos($expected->getMessage(), 'Duplicate') !== false, 'Duplicate guard'); }
    $db->createCommand()->update('cms_agent', ['is_running'=>1], ['id'=>8])->execute();
    try { $migration->safeUp(); throw new LogicException('Running agent accepted'); }
    catch (RuntimeException $expected) { check(strpos($expected->getMessage(), 'Stop') !== false, 'Running guard'); }
    $config = require __DIR__.'/../src/config/common.php';
    check(!isset($config['components']['cmsAgent']['commands']), 'No console schedule');
    check(isset($config['components']['cmsAgent']['jobs']['seo.sitemap.generate']), 'Native job registered');
    echo "Native sitemap checks passed.\n";
} finally {
    yii\helpers\FileHelper::removeDirectory($temporary);
}
