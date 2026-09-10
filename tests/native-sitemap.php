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
    protected function itemGroups() {
        yield 'fixture' => (function () {
            for ($i = 0; $i < 5; $i++) { yield ['loc' => 'https://example.test/page-'.$i]; }
        })();
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
    check(count(glob($temporary.'/sitemaps/generated-*')) === 1, 'Cancelled generation removed');
    $reporter->cancel = true;
    try { $handler->run(new JobContext(), $reporter); throw new RuntimeException('Pre-start cancellation ignored'); }
    catch (JobCancelledException $expected) {}
    $generator->generate(static function ($message) {});
    check(count(glob($temporary.'/sitemaps/generated-*')) === 2, 'Legacy CLI callback supported');

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
