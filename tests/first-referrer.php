<?php
/**
 * The first external page of a visit without marks (CmsSeoComponent::_rememberFirstReferrer(), 3.1.0.5):
 * remembered for an ordinary GET from another site; not for AJAX, POST, the own host or a non-http referrer;
 * marks already in the session win and are never replaced by a later page.
 *
 * Usage: php tests/first-referrer.php (SKEEKS_APP_ROOT=/app)
 */
require (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor/autoload.php';
require (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor/yiisoft/yii2/Yii.php';

/** A session in memory: the component reads and writes only get()/set(). */
class MemorySession extends yii\web\Session
{
    public array $data = [];
    public function open() {}
    public function getIsActive() { return true; }
    public function get($key, $defaultValue = null) { return $this->data[$key] ?? $defaultValue; }
    public function set($key, $value) { $this->data[$key] = $value; }
}

new yii\web\Application(['id' => 'first-referrer-test', 'basePath' => __DIR__, 'extensions' => [],
    'components' => ['session' => ['class' => MemorySession::class], 'request' => ['cookieValidationKey' => 'test', 'hostInfo' => 'https://shop.test']]]);

$seo = (new ReflectionClass(skeeks\cms\seo\CmsSeoComponent::class))->newInstanceWithoutConstructor();
$remember = (new ReflectionMethod($seo, '_rememberFirstReferrer'));
$remember->setAccessible(true);
$checks = 0;
$visit = static function (string $referrer, string $method = 'GET', bool $ajax = false) use ($seo, $remember) {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_HOST'] = 'shop.test';
    $_SERVER['HTTP_REFERER'] = $referrer;
    if ($ajax) {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    } else {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
    Yii::$app->set('request', ['class' => yii\web\Request::class, 'cookieValidationKey' => 'test']);
    $remember->invoke($seo);
};
$first = static fn() => Yii::$app->session->data[skeeks\cms\seo\CmsSeoComponent::SESSION_SEO_REQUEST_UTMS]['sx_first_referrer'] ?? null;
$check = static function (bool $ok, string $what) use (&$checks) {
    $checks++;
    if (!$ok) {
        throw new RuntimeException('FAIL: '.$what);
    }
};

$visit('https://www.shop.test/catalog');
$check($first() === null, 'the own host is not a referrer');
$visit('javascript:alert(1)');
$check($first() === null, 'a non-http referrer is ignored');
$visit('https://yandex.ru/search/?text=tile', 'POST');
$check($first() === null, 'a POST is not an entry');
$visit('https://yandex.ru/search/?text=tile', 'GET', true);
$check($first() === null, 'an AJAX request is not an entry');
$visit('https://yandex.ru/search/?text=tile');
$check($first() === 'https://yandex.ru/search/?text=tile', 'the first external page is remembered');
$visit('https://2gis.ru/firm/1');
$check($first() === 'https://yandex.ru/search/?text=tile', 'a later page does not replace it');

Yii::$app->session->data = [skeeks\cms\seo\CmsSeoComponent::SESSION_SEO_REQUEST_UTMS => ['utm_source' => 'yandex', 'created_at' => time()]];
$visit('https://2gis.ru/firm/1');
$check($first() === null && Yii::$app->session->data[skeeks\cms\seo\CmsSeoComponent::SESSION_SEO_REQUEST_UTMS]['utm_source'] === 'yandex',
    'marks in the session win');

echo "OK first-referrer: {$checks} checks\n";
