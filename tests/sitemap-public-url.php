<?php
require '/app/vendor/autoload.php';require '/app/vendor/yiisoft/yii2/Yii.php';
new yii\console\Application(['id'=>'sitemap-url-test','basePath'=>__DIR__,'extensions'=>[],
'components'=>['urlManager'=>['class'=>yii\web\UrlManager::class,'hostInfo'=>'https://fallback.test','baseUrl'=>'/shop'],
'skeeks'=>new class extends yii\base\Component{public $site;} ]]);
class UrlFixture extends skeeks\cms\seo\sitemap\SitemapGenerator{public function url(){return $this->publicUrl('sitemap.xml');}}
$f=new UrlFixture();
foreach([''=>'https://fallback.test/shop/sitemap.xml','/'=>'https://fallback.test/shop/sitemap.xml','https://site.test'=>'https://site.test/shop/sitemap.xml'] as $url=>$expected){
 Yii::$app->skeeks->site=(object)['url'=>$url];
 if($f->url()!==$expected)throw new RuntimeException('Wrong host resolution');
}
Yii::$app->skeeks->site=(object)['url'=>''];Yii::$app->urlManager->hostInfo='';
try{$f->url();throw new LogicException('Missing host accepted');}catch(yii\base\InvalidConfigException $e){}
echo "OK sitemap URL: site host, fallback and missing-host rejection\n";