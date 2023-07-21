<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 30.03.2015
 */

namespace skeeks\cms\seo;

use kartik\datecontrol\DateControl;
use skeeks\cms\backend\BackendComponent;
use skeeks\cms\backend\widgets\ActiveFormBackend;
use skeeks\cms\base\Component;
use skeeks\cms\helpers\StringHelper;
use skeeks\cms\seo\assets\CmsSeoAsset;
use skeeks\cms\seo\vendor\CanUrl;
use skeeks\yii2\form\elements\HtmlColBegin;
use skeeks\yii2\form\elements\HtmlColEnd;
use skeeks\yii2\form\elements\HtmlRowBegin;
use skeeks\yii2\form\elements\HtmlRowEnd;
use skeeks\yii2\form\fields\BoolField;
use skeeks\yii2\form\fields\FieldSet;
use skeeks\yii2\form\fields\HtmlBlock;
use skeeks\yii2\form\fields\NumberField;
use skeeks\yii2\form\fields\SelectField;
use skeeks\yii2\form\fields\TextareaField;
use skeeks\yii2\form\fields\WidgetField;
use yii\base\ActionEvent;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\Widget;
use yii\base\WidgetEvent;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\View;
use yii\widgets\ActiveForm;
use yii\widgets\ListView;

/**
 *
 * 'seo' => [
 * 'canUrl' => [
 * 'scheme' => 'https',
 * 'host'   => 'skeeks-travel.ru',
 * ],
 * ],
 *
 * 'seo' => [
 * 'canUrl' => [
 * 'scheme' => CmsSeoComponent::CANURL_DATA_FROM_MAIN_DOMAIN,
 * 'host'   => CmsSeoComponent::CANURL_DATA_FROM_MAIN_DOMAIN,
 * ],
 * ],
 *
 * @property CanUrl $canUrl;
 * @property array  $utms;
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CmsSeoComponent extends Component implements BootstrapInterface
{
    /**
     *
     */
    const SESSION_SEO_REQUEST_UTMS = 'session_seo_request_utms';

    /**
     *
     */
    const CANURL_DATA_FROM_MAIN_DOMAIN = '_can_url_from_main_domain';

    /**
     * Добавление title ко всем страницам сайта
     * @var string
     */
    public $title_append = "";
    /**
     * Максимадбгая длина ключевых слов
     * @var int
     */
    public $maxKeywordsLength = 1000;

    /**
     * @var int минимальная длина слова которая попадет в списко ключевых слов
     */
    public $minKeywordLenth = 8;

    /**
     * @var array ключевые слова которые не должны попадать в ключевые
     */
    public $keywordsStopWords = [];

    /**
     * @var bool включить автогенерацию ключевых слов
     */
    public $enableKeywordsGenerator = false;

    /**
     * @var bool добавлять приоритет в sitemap?
     */
    public $is_sitemap_priority = false;

    /**
     * @var bool Делать картинки webp при ресайзе?
     */
    public $is_webp = 0;

    /**
     * @var int Качество preview картинок по умолчанию
     */
    public $img_preview_quality = 75;

    /**
     * @var int Выводить микроразметку событий?
     */
    public $is_sale_event = 1;

    public $sale_event_1_name = "";
    public $sale_event_1_description = "";
    public $sale_event_1_streetAddress = "";
    public $sale_event_1_addressLocality = "";

    public $sale_event_2_name = "";
    public $sale_event_2_description = "";
    public $sale_event_2_streetAddress = "";
    public $sale_event_2_addressLocality = "";

    public $sale_event_3_name = "";
    public $sale_event_3_description = "";
    public $sale_event_3_streetAddress = "";
    public $sale_event_3_addressLocality = "";


    /**
     * @var int
     */
    public $is_mobile_webp = 1;

    /**
     * @var string если файла robots.txt нет физически, то он формируется динамически с этим содержимым
     */
    public $robotsContent = "User-agent: *";


    /**
     * Содержимое счетчиков
     * @var string
     */
    public $header_content = "";
    /**
     * Содержимое счетчиков
     * @var string
     */
    public $countersContent = "";

    /**
     * @var bool Подключить в низ страницы автоматически
     */
    public $isAutoIncludecountersContent = true;


    /**
     * @var string
     */
    public $activeTree = true;
    /**
     * @var string
     */
    public $activeContentElem = true;

    /**
     * @var int
     */
    public $sitemap_min_date = 1593591722;

    /**
     * @var string
     */
    public $contentIds = [];

    /**
     * @var string
     */
    public $treeTypeIds = [];


    /**
     * @var array
     */
    public $keywordsPriority = [
        "title" => 8,
        "h1"    => 6,
        "h2"    => 4,
        "h3"    => 3,
        "h5"    => 2,
        "h6"    => 2,
        //"b"         =>  2,
        //"strong"    =>  2,
    ]; //Учитывать следующие типы разделов

    /**
     * В виджетах ListView registerLinkTags = true по умолчанию
     * @var bool
     */
    public $registerLinkTags = true;

    /**
     * In pagionation
     * /blog?page=1 -> /blog
     * @var bool
     */
    public $forcePageParam = false;


    /**
     * false - disable canurl
     * @var bool|array
     */
    protected $_canUrl = [];

    /**
     * @var bool
     */
    public $isRedirectNotFoundHttpException = false;


    /**
     * Можно задать название и описание компонента
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name'        => \Yii::t('skeeks/seo', 'Seo'),
            'description' => 'Установка счетчиков, правка robots.txt, карта сайта',
            'image'       => [
                CmsSeoAsset::class,
                'icons/seo-icon.png',
            ],
        ]);
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [
                [
                    'enableKeywordsGenerator',
                    'is_sitemap_priority',
                    'minKeywordLenth',
                    'maxKeywordsLength',
                    //'activeContentElem', 'activeTree'
                ],
                'integer',
            ],
            ['robotsContent', 'string'],
            ['countersContent', 'string'],
            ['header_content', 'string'],
            [['contentIds', 'treeTypeIds'], 'safe'],
            ['sitemap_min_date', 'integer'],
            ['title_append', 'string'],
            ['is_webp', 'integer'],
            ['img_preview_quality', 'integer', 'min' => 10, 'max' => 100],
            ['is_sale_event', 'integer'],
            ['is_mobile_webp', 'integer'],

            ['sale_event_1_name', 'string'],
            ['sale_event_1_description', 'string'],
            ['sale_event_1_streetAddress', 'string'],
            ['sale_event_1_addressLocality', 'string'],

            ['sale_event_2_name', 'string'],
            ['sale_event_2_description', 'string'],
            ['sale_event_2_streetAddress', 'string'],
            ['sale_event_2_addressLocality', 'string'],

            ['sale_event_3_name', 'string'],
            ['sale_event_3_description', 'string'],
            ['sale_event_3_streetAddress', 'string'],
            ['sale_event_3_addressLocality', 'string'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'is_sitemap_priority'     => \Yii::t('skeeks/seo', 'Добавлять priority в sitemap?'),
            'enableKeywordsGenerator' => \Yii::t('skeeks/seo', 'Automatic generation of keywords'),
            'minKeywordLenth'         => \Yii::t('skeeks/seo', 'The minimum length of the keyword'),
            'maxKeywordsLength'       => \Yii::t('skeeks/seo', 'Length keywords'),
            'robotsContent'           => 'Robots.txt',
            'title_append'            => 'Добавление title ко всем страницам сайта',
            'countersContent'         => \Yii::t('skeeks/seo', 'Footer'),
            'header_content'          => \Yii::t('skeeks/seo', 'Head'),
            'activeTree'              => \Yii::t('skeeks/seo', 'Active flag to tree'),
            'activeContentElem'       => \Yii::t('skeeks/seo', 'Active flag to contents element'),
            'contentIds'              => \Yii::t('skeeks/cms', 'Elements of content'),
            'sitemap_min_date'        => \Yii::t('skeeks/seo', 'Минимальная дата обновления ссылки'),
            'treeTypeIds'             => \Yii::t('skeeks/seo', 'Types of tree'),
            'is_webp'                 => \Yii::t('skeeks/seo', 'Использовать .webp сжатие картинок?'),
            'img_preview_quality'     => \Yii::t('skeeks/seo', 'Качество preview картинок'),

            'is_sale_event' => \Yii::t('skeeks/seo', 'Включить микроразметку событий?'),

            'sale_event_1_name'            => \Yii::t('skeeks/seo', 'Название'),
            'sale_event_1_description'     => \Yii::t('skeeks/seo', 'Описание'),
            'sale_event_1_streetAddress'   => \Yii::t('skeeks/seo', 'Адрес'),
            'sale_event_1_addressLocality' => \Yii::t('skeeks/seo', 'Город'),


            'sale_event_2_name'            => \Yii::t('skeeks/seo', 'Название'),
            'sale_event_2_description'     => \Yii::t('skeeks/seo', 'Описание'),
            'sale_event_2_streetAddress'   => \Yii::t('skeeks/seo', 'Адрес'),
            'sale_event_2_addressLocality' => \Yii::t('skeeks/seo', 'Город'),

            'sale_event_3_name'            => \Yii::t('skeeks/seo', 'Название'),
            'sale_event_3_description'     => \Yii::t('skeeks/seo', 'Описание'),
            'sale_event_3_streetAddress'   => \Yii::t('skeeks/seo', 'Адрес'),
            'sale_event_3_addressLocality' => \Yii::t('skeeks/seo', 'Город'),

            'is_mobile_webp' => \Yii::t('skeeks/seo', 'Использовать .webp сжатие картинок в мобильном телефоне?'),
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'header_content'          => "Вставьте подвреждающие коды yandex webmaster и подобных систем. Этот код попадет между тегов head на странице.",
            'countersContent'         => \Yii::t('skeeks/seo',
                'В это поле вы можете поставить любые коды счетчиков и сторонних систем (yandex.metrics jivosite google.metrics и прочие). Они будут выведены внизу страницы, перед закрывающим тегом body'),
            'enableKeywordsGenerator' => \Yii::t('skeeks/seo', 'If the page is not specified keywords, they will generate is for her, according to certain rules automatically'),
            'minKeywordLenth'         => \Yii::t('skeeks/seo', 'The minimum length of the keyword, which is listed by the key (automatic generation)'),
            'maxKeywordsLength'       => \Yii::t('skeeks/seo', 'The maximum length of the string of keywords (automatic generation)'),
            'title_append'            => \Yii::t('skeeks/seo', 'Этот заголовок будет добавлен ко всем страницам вашего сайта. Именно добавлен после основного заголовка страницы.'),
            'robotsContent'           => \Yii::t('skeeks/seo', 'Содержимое файла robots.txt'),
            'contentIds'              => \Yii::t('skeeks/seo', 'If nothing is selected, then all'),
            'treeTypeIds'             => \Yii::t('skeeks/seo', 'If nothing is selected, then all'),
            'is_webp'                 => \Yii::t('skeeks/seo',
                'Опция для компьютеров. Внимание в старых safari не работает! Если выбрана эта опция, то все изображения на сайте будут преобразовываться и ужиматься в .webp формат'),
            'img_preview_quality'     => \Yii::t('skeeks/seo', '10% - плохое качество, но картинка мало весит; 100% - preview картинки без потери качества.'),
            'is_mobile_webp'          => \Yii::t('skeeks/seo', 'Опция работает в мобильном телефоне. Если выбрана эта опция, то все изображения на сайте будут преобразовываться и ужиматься в .webp формат'),
            'sitemap_min_date'        => \Yii::t('skeeks/seo', 'Если будет задан этот параметр, то ни в одной ссылке не будет указано даты обновления меньше этой. Используется для переиндексации всех страниц.'),

            'is_sale_event' => \Yii::t('skeeks/seo', 'Если включена микроразметка событий, то в результатах выдачи google появится дополнительный блок'),

            'sale_event_1_name'            => \Yii::t('skeeks/seo', 'Пример: 🌿 Качественные материалы'),
            'sale_event_1_description'     => \Yii::t('skeeks/seo', 'Пример: Качественные материалы'),
            'sale_event_1_streetAddress'   => \Yii::t('skeeks/seo', 'Возьмется из настроек сайта если не будет задан'),
            'sale_event_1_addressLocality' => \Yii::t('skeeks/seo', 'Возьмется из настроек сайта если не будет задан'),

            'sale_event_2_name'            => \Yii::t('skeeks/seo', 'Пример: ❤ Большой выбор'),
            'sale_event_2_description'     => \Yii::t('skeeks/seo', 'Пример: Большой выбор'),
            'sale_event_2_streetAddress'   => \Yii::t('skeeks/seo', 'Возьмется из настроек сайта если не будет задан'),
            'sale_event_2_addressLocality' => \Yii::t('skeeks/seo', 'Возьмется из настроек сайта если не будет задан'),

            'sale_event_3_name'            => \Yii::t('skeeks/seo', 'Пример: 🔔 Скидка на первый заказ'),
            'sale_event_3_description'     => \Yii::t('skeeks/seo', 'Пример: Скидка на первый заказ'),
            'sale_event_3_streetAddress'   => \Yii::t('skeeks/seo', 'Возьмется из настроек сайта если не будет задан'),
            'sale_event_3_addressLocality' => \Yii::t('skeeks/seo', 'Возьмется из настроек сайта если не будет задан'),

        ]);
    }


    /**
     * @return ActiveForm
     */
    public function beginConfigForm()
    {
        return ActiveFormBackend::begin();
    }

    /**
     * @return array
     */
    public function getConfigFormFields()
    {
        if (file_exists(\Yii::getAlias("@webroot/robots.txt"))) {
            $robotsContent = file_get_contents(\Yii::getAlias("@webroot/robots.txt"));
            $indexing = [
                'robotsContent' => [
                    'class'   => HtmlBlock::class,
                    'content' => <<<HTML
<p>Файл <b>robots.txt</b> создан на сервере. Если его удалить с серера, то настройки robots можно будет задавать в этом месте.</p>
<p>Текущее содержимое файла robots:</p>
<p><pre><code>{$robotsContent}</code></pre></p>
HTML
                    ,
                ],
            ];
        } else {
            $indexing = [
                'robotsContent' => [
                    'class'          => TextareaField::class,
                    /*'on afterRender'          => function(ViewRenderEvent $e) {
                        $e->content = <<<HTML
<p style="text-align: center;">
<a href="#" class="btn btn-secondary sx-generate-robots">Сгенерировать robots.txt</a>
</p>
HTML;

                    },*/
                    'elementOptions' => [
                        'rows' => 15,
                    ],
                ],
            ];
        }


        return [
            'counters' => [
                'class'  => FieldSet::class,
                'name'   => \Yii::t('skeeks/seo', 'Коды и счетчики'),
                'fields' => [
                    'header_content'  => [
                        'class'        => WidgetField::class,
                        'widgetClass'  => \skeeks\widget\codemirror\CodemirrorWidget::class,
                        'widgetConfig' => [
                            'preset' => 'htmlmixed',
                        ],
                    ],
                    'countersContent' => [
                        'class'        => WidgetField::class,
                        'widgetClass'  => \skeeks\widget\codemirror\CodemirrorWidget::class,
                        'widgetConfig' => [
                            'preset' => 'htmlmixed',
                        ],
                    ],
                ],
            ],


            'indexing' => [
                'class'  => FieldSet::class,
                'name'   => \Yii::t('skeeks/seo', 'Indexing'),
                'fields' => $indexing,
            ],

            'titles' => [
                'class'  => FieldSet::class,
                'name'   => \Yii::t('skeeks/seo', 'Заголовки'),
                'fields' => [
                    'title_append',
                ],
            ],

            'optimize' => [
                'class'          => FieldSet::class,
                'name'           => \Yii::t('skeeks/seo', 'Оптимизация'),
                'elementOptions' => [
                    'isOpen' => false,
                ],
                'fields'         => [
                    'img_preview_quality' => [
                        'class'  => NumberField::class,
                        'step'   => 1,
                        'append' => "%",
                    ],
                    'is_webp'             => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],
                    'is_mobile_webp'      => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],
                ],
            ],

            'saleEvent' => [
                'class'          => FieldSet::class,
                'name'           => \Yii::t('skeeks/seo', 'Микроразметка SaleEvent'),
                'elementOptions' => [
                    'isOpen' => false,
                ],
                'fields'         => [

                    [
                        'class'   => HtmlBlock::class,
                        'content' => <<<HTML
<div class="col" style="margin-top: 20px;">
<div class="alert alert-default">
    <p>Для того чтобы результаты выдачи отображаемые в поисковой системе google выглядели лучше и больше привлекали потенциального клиента, можно включить эту микроразметку.</p>
    <p><a href="https://skeeks.com/mikrorazmetka-saleevent-505" target="_blank">Подробнее</a></p>
</div>
</div>
HTML
    ,
                    ],

                    'is_sale_event'                => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],


                    [
                        'class'   => HtmlBlock::class,
                        'content' => <<<HTML
<div class="col" style="margin-top: 20px;">
<h3>Событие 1</h3>
</div>
HTML
    ,
                    ],


                    ['class' => HtmlRowBegin::class, 'noGutters' => true],
                    ['class' => HtmlColBegin::class],
                    'sale_event_1_name'            => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_1_description'     => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_1_streetAddress'   => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_1_addressLocality' => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlRowEnd::class],

                    [
                        'class'   => HtmlBlock::class,
                        'content' => <<<HTML
<div class="col" style="margin-top: 20px;">
<h3>Событие 2</h3>
</div>
HTML
    ,
                    ],


                    ['class' => HtmlRowBegin::class, 'noGutters' => true],
                    ['class' => HtmlColBegin::class],
                    'sale_event_2_name'            => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_2_description'     => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_2_streetAddress'   => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_2_addressLocality' => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlRowEnd::class],


                    [
                        'class'   => HtmlBlock::class,
                        'content' => <<<HTML
<div class="col" style="margin-top: 20px;">
<h3>Событие 3</h3>
</div>
HTML
    ,
                    ],


                    ['class' => HtmlRowBegin::class, 'noGutters' => true],
                    ['class' => HtmlColBegin::class],
                    'sale_event_3_name'            => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_3_description'     => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_3_streetAddress'   => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlColBegin::class],
                    'sale_event_3_addressLocality' => [],
                    ['class' => HtmlColEnd::class],
                    ['class' => HtmlRowEnd::class],
                ],
            ],

            'keywords' => [
                'class'          => FieldSet::class,
                'name'           => \Yii::t('skeeks/seo', 'Keywords'),
                'elementOptions' => [
                    'isOpen' => false,
                ],
                'fields'         => [
                    'enableKeywordsGenerator' => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],
                    'minKeywordLenth'         => [
                        'class' => NumberField::class,
                    ],
                    'maxKeywordsLength'       => [
                        'class' => NumberField::class,
                    ],
                ],
            ],

            'sitemap' => [
                'class'          => FieldSet::class,
                'name'           => \Yii::t('skeeks/seo', 'Sitemap settings'),
                'elementOptions' => [
                    'isOpen' => false,
                ],
                'fields'         => [
                    'is_sitemap_priority' => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],
                    /*'activeContentElem' => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],
                    'activeTree'        => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],*/
                    'contentIds'          => [
                        'class' => SelectField::class,
                        'items' => \skeeks\cms\models\CmsContent::getDataForSelect(),
                    ],
                    'treeTypeIds'         => [
                        'class' => SelectField::class,
                        'items' => \yii\helpers\ArrayHelper::map(
                            \skeeks\cms\models\CmsTreeType::find()->all(), 'id', 'name'
                        ),
                    ],
                    'sitemap_min_date'    => [
                        'class'        => WidgetField::class,
                        'widgetClass'  => DateControl::class,
                        'widgetConfig' => [
                            'type' => \kartik\datecontrol\DateControl::FORMAT_DATE,
                        ],
                    ],
                ],
            ],
        ];
    }


    public function bootstrap($application)
    {
        if (!$application instanceof \yii\web\Application) {
            return true;
        }

        /**
         * Генерация SEO метатегов по контенту страницы
         */
        $application->view->on(View::EVENT_END_BODY, function (Event $e) {

            /**
             * @var $view View
             */
            $view = $e->sender;

            if ($this->enableKeywordsGenerator && !BackendComponent::getCurrent()) {
                if (!\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax) {
                    $this->_autoGenerateKeywords($view);
                }
            }

            /*if (
                !BackendComponent::getCurrent()
                && !in_array(\Yii::$app->controller->module->id, ['debug', 'gii'])
                && $this->isAutoIncludecountersContent
                && !\Yii::$app->request->isPjax
                && !\Yii::$app->request->isAjax
            ) {
                if ($this->countersContent) {
                    $content = ob_get_contents();
                    //$e->sender->content = 2;
                    if (strpos($content, $this->countersContent) === false) {
                        //Для google page speed не показываем этот блок
                        if (!$this->isGooglePageSpeedRequest()) {
                            //echo str_replace("</body>", Html::tag('div', $this->countersContent, ['style' => 'display: none;', 'data-is-auto' => 'true']) . "</body>", $content);
                            echo Html::tag('div', $this->countersContent, ['style' => 'display: none;', 'data-is-auto' => 'true']);
                        }
                    }
                }
            }*/
        });


        $application->on(Application::EVENT_BEFORE_REQUEST, function (Event $e) {

            /**
             * Добавление метатегов в постраничной навигации
             */
            if ($this->registerLinkTags) {
                \Yii::$container->set('yii\widgets\LinkPager', [
                    'registerLinkTags' => true,
                ]);
            }

            /**
             * Убирает page=1, делает чистый урл в постричной новигации на первую страницу
             */
            \Yii::$container->set('yii\data\Pagination', [
                'forcePageParam' => $this->forcePageParam,
            ]);


            if (\Yii::$app->request->queryParams) {
                $utms = [];
                foreach (\Yii::$app->request->queryParams as $paramName => $paramValue) {
                    if (in_array($paramName, [
                        'from',
                        '_openstat',

                        'utm_source',
                        'utm_medium',
                        'utm_campaign',
                        'utm_content',
                        'utm_term',
                        'utm_referrer',

                        'pm_source',
                        'pm_block',
                        'pm_position',

                        'clid',
                        'yclid',
                        'ymclid',
                        'frommarket',
                        'text',
                    ])) {
                        $utms[$paramName] = $paramValue;
                    }
                }

                if ($utms) {
                    $this->setUtms($utms);
                }
            }
        });


        /**
         * Стандартная инициализация canurl
         */
        Event::on(Controller::class, Controller::EVENT_BEFORE_ACTION, function (ActionEvent $e) {
            if (\Yii::$app->urlManager->enablePrettyUrl) {
                $this->_initDefaultCanUrl();
            }


            /**
             * Редирект 404 ошибок
             */
            if (\Yii::$app->controller->uniqueId == 'cms/error') {
                if (\Yii::$app->getErrorHandler()->exception instanceof NotFoundHttpException && $this->isRedirectNotFoundHttpException && !BackendComponent::getCurrent()) {
                    \Yii::$app->response->redirect(Url::home());
                    \Yii::$app->response->getHeaders()->setDefault('X-Skeeks-Seo-Not-Found', "isRedirectNotFoundHttpException=true");
                    \Yii::$app->end();
                    return;
                }
            }

            if ($this->is_sale_event && ($this->sale_event_1_name || $this->sale_event_2_name || $this->sale_event_3_name)) {
                if (in_array(\Yii::$app->controller->action->uniqueId, ["cms/tree/view", "cms/content-element/view", "cms/saved-filter/view"])) {
                    $date1 = date("Y-m-01", strtotime("+1 month"));
                    $date2 = date("Y-m-t", strtotime("+1 month"));

                    $startDate = \Yii::$app->formatter->asDatetime(strtotime($date1), "php:c");
                    $endDate = \Yii::$app->formatter->asDatetime(strtotime($date2), "php:c");

                    $projectName = \Yii::$app->skeeks->site->name;
                    $address = \Yii::$app->skeeks->site->cmsSiteAddress;
                    $addressString = "";
                    if ($address) {
                        $addressString = $address->value;
                    }

                    $saleEvents = "";
                    if ($this->sale_event_1_name) {
                        $eventData = [
                            '@context'    => 'https://schema.org',
                            '@type'       => 'SaleEvent',
                            'name'        => $this->sale_event_1_name,
                            'description' => $this->sale_event_1_description ? $this->sale_event_1_description : $this->sale_event_1_name,
                            'startDate'   => $startDate,
                            'endDate'     => $endDate,
                            'location'    => [
                                '@context' => "https://schema.org",
                                '@type'    => "Place",
                                'name'     => $projectName,
                                'address'  => [
                                    "@type"         => "PostalAddress",
                                    "streetAddress" => $this->sale_event_1_streetAddress ? $this->sale_event_1_streetAddress : $addressString,
                                ],
                            ],
                        ];

                        if ($this->sale_event_1_addressLocality) {
                            $eventData["location"]["address"]["addressLocality"] = $this->sale_event_1_addressLocality;
                        }

                        $eventDataString = Json::encode($eventData);

                        $saleEvents .= <<<HTML
<script type="application/ld+json">
{$eventDataString}
</script>
HTML;
                    }

                    if ($this->sale_event_2_name) {
                        $eventData = [
                            '@context'    => 'https://schema.org',
                            '@type'       => 'SaleEvent',
                            'name'        => $this->sale_event_2_name,
                            'description' => $this->sale_event_2_description ? $this->sale_event_2_description : $this->sale_event_2_name,
                            'startDate'   => $startDate,
                            'endDate'     => $endDate,
                            'location'    => [
                                '@context' => "https://schema.org",
                                '@type'    => "Place",
                                'name'     => $projectName,
                                'address'  => [
                                    "@type"         => "PostalAddress",
                                    "streetAddress" => $this->sale_event_2_streetAddress ? $this->sale_event_2_streetAddress : $addressString,
                                ],
                            ],
                        ];

                        if ($this->sale_event_2_addressLocality) {
                            $eventData["location"]["address"]["addressLocality"] = $this->sale_event_2_addressLocality;
                        }

                        $eventDataString = Json::encode($eventData);

                        $saleEvents .= <<<HTML
<script type="application/ld+json">
{$eventDataString}
</script>
HTML;
                    }

                    if ($this->sale_event_3_name) {
                        $eventData = [
                            '@context'    => 'https://schema.org',
                            '@type'       => 'SaleEvent',
                            'name'        => $this->sale_event_3_name,
                            'description' => $this->sale_event_3_description ? $this->sale_event_3_description : $this->sale_event_3_name,
                            'startDate'   => $startDate,
                            'endDate'     => $endDate,
                            'location'    => [
                                '@context' => "https://schema.org",
                                '@type'    => "Place",
                                'name'     => $projectName,
                                'address'  => [
                                    "@type"         => "PostalAddress",
                                    "streetAddress" => $this->sale_event_3_streetAddress ? $this->sale_event_3_streetAddress : $addressString,
                                ],
                            ],
                        ];

                        if ($this->sale_event_3_addressLocality) {
                            $eventData["location"]["address"]["addressLocality"] = $this->sale_event_3_addressLocality;
                        }

                        $eventDataString = Json::encode($eventData);

                        $saleEvents .= <<<HTML
<script type="application/ld+json">
{$eventDataString}
</script>
HTML;
                    }

                    \Yii::$app->seo->countersContent = \Yii::$app->seo->countersContent.$saleEvents;
                }
            }
        });

        $application->on(Application::EVENT_AFTER_REQUEST, function ($e) {
            if ($this->_isTrigerEventCanUrl()) {
                if ($this->canUrl) {
                    $this->canUrl->event_after_request($e);
                }
            }
        });

        $application->view->on(View::EVENT_BEGIN_PAGE, function ($e) {
            if ($this->title_append) {
                if (!BackendComponent::getCurrent()) {
                    \Yii::$app->view->title = \Yii::$app->view->title.$this->title_append;
                }

            }
        });


        $application->response->on(\yii\web\Response::EVENT_BEFORE_SEND, function (\yii\base\Event $event) {
            $response = $event->sender;

            if ($this->isGooglePageSpeedRequest()) {
                return false;
            }

            if (BackendComponent::getCurrent()
                && !in_array(BackendComponent::getCurrent()->id, ['upaBackend'])
            ) {
                return false;
            }

            if (\Yii::$app->request->isPjax || \Yii::$app->request->isAjax) {
                return false;
            }

            $replaces = [];

            if ($this->header_content) {
                $replaces["</head>"] = "".trim(((string)$this->header_content))."</head>";
            }


            if ($this->countersContent) {
                if (is_string($response->data)) {
                    if (strpos($response->data, $this->countersContent) === false) {
                        //Для google page speed не показываем этот блок
                        if (!$this->isGooglePageSpeedRequest()) {
                            $replaces["</body>"] = Html::tag('div', $this->countersContent, ['style' => 'display: none;', 'data-is-auto' => 'true'])."</body>";
                        }
                    }
                }

            }


            if ($replaces) {
                $response->data = strtr((string)$response->data, $replaces);
            }

        });

        $application->view->on(View::EVENT_END_PAGE, function ($e) {

            if ($this->_isTrigerEventCanUrl()) {
                if ($this->canUrl) {
                    $this->canUrl->event_end_page($e);
                }

            }
        });
    }

    /**
     * @return bool
     */
    public function isGooglePageSpeedRequest()
    {
        $userAgent = \Yii::$app->request->headers->get('user-agent');
        if ($userAgent && strpos($userAgent, "Lighthouse") !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param array $utms
     * @return $this
     */
    public function setUtms($utms = [])
    {
        $utms['created_at'] = time();
        \Yii::$app->session->set(self::SESSION_SEO_REQUEST_UTMS, $utms);
        return $this;
    }

    /**
     * @return array
     */
    public function getUtms()
    {
        $utms = (array)\Yii::$app->session->get(self::SESSION_SEO_REQUEST_UTMS);
        if ($utms) {
            $created_at = ArrayHelper::getValue($utms, 'created_at');

            //Если с момента создания утм прошло более 24 часов то
            if (time() - $created_at >= 3600 * 24) {
                $utms = [];
            }
        }
        return $utms;
    }

    protected function _initDefaultCanUrl()
    {
        /**
         * Канурл может быть отключен вовсе
         */
        if ($this->canUrl === false) {
            return false;
        }

        //print_r($_SERVER);die;
        //var_dump($_SERVER['HTTPS']);die;
        //print_r(\Yii::$app->getRequest()->getAbsoluteUrl());die;
        /**
         * Хост может быть не указан, тогда будет взят из запроса
         */
        if ($this->canUrl->host == self::CANURL_DATA_FROM_MAIN_DOMAIN) {
            if (\Yii::$app->skeeks->site && \Yii::$app->skeeks->site->cmsSiteMainDomain) {
                $this->canUrl->host = \Yii::$app->skeeks->site->cmsSiteMainDomain->domain;
            } else {
                $this->canUrl->host = null;
            }
        }

        if (!$this->canUrl->host) {
            $this->canUrl->host = \Yii::$app->request->hostName;
        }

        /**
         * Аналогично со схемой
         */
        if ($this->canUrl->scheme == self::CANURL_DATA_FROM_MAIN_DOMAIN) {
            if (\Yii::$app->skeeks->site && \Yii::$app->skeeks->site->cmsSiteMainDomain) {
                $this->canUrl->scheme = \Yii::$app->skeeks->site->cmsSiteMainDomain->is_https ? "https" : "http";
            } else {
                $this->canUrl->scheme = null;
            }
        }

        if (!$this->canUrl->scheme) {
            $this->canUrl->scheme = \Yii::$app->request->isSecureConnection ? "https" : "http";
        }

        //print_r($this->canUrl);die;


        if (\Yii::$app->requestedRoute) {
            $requestedUrl = Url::to(ArrayHelper::merge(["/".\Yii::$app->requestedRoute],
                (array)\Yii::$app->request->queryParams));
            $autoPath = ArrayHelper::getValue(parse_url($requestedUrl), 'path');
            $this->canUrl->path = $autoPath;
        } else {
            if (\Yii::$app->cms->currentTree) {
                $this->canUrl->path = \Yii::$app->cms->currentTree->url;
            }
        }

        $this->canUrl->SETcore_params([]);
        $this->canUrl->SETimportant_params([]);

        Event::on(ListView::class, Widget::EVENT_AFTER_RUN, [$this, '_addCanurlParams']);
        Event::on(GridView::class, Widget::EVENT_AFTER_RUN, [$this, '_addCanurlParams']);

    }

    public function _addCanurlParams(WidgetEvent $e)
    {
        //Только для этих действий
        if (!in_array(\Yii::$app->controller->uniqueId, $this->canUrlEnableDefaultControllers)
        ) {
            return true;
        }

        $this->canUrl->ADDimportant_pnames(['ProductFilters']);
        $this->canUrl->ADDimportant_pnames(['SearchProductsModel']);
        $this->canUrl->ADDimportant_pnames(['SearchRelatedPropertiesModel']);

        /**
         * @var $sender ListView|GridView
         */
        $sender = $e->sender;
        $r = new \ReflectionClass($sender);

        /*if (YII_DEBUG === true) {
            \Yii::info('_addCanurlParams: '.$r->getName(), (new \ReflectionClass($this->canUrl))->getName());
        }*/

        if ($pagination = $sender->dataProvider->getPagination()) {

            if ($pagination->pageCount > 1) {
                $pageParam = $pagination->pageParam;
                $this->canUrl->ADDimportant_params([$pagination->pageSizeParam => null]);
                /*for ($i = 2; $i <= $pagination->pageCount; $i++) {
                    $pages[] = $i;
                }*/
                if ($currentPage = \Yii::$app->request->get($pageParam)) {
                    if ($currentPage > $pagination->pageCount && $currentPage != 1) {
                        $this->canUrl->ADDimportant_params([$pagination->pageParam => $pagination->pageCount]);
                    } elseif ($currentPage != 1) {
                        $this->canUrl->ADDimportant_params([$pagination->pageParam => null]);
                    }
                } else {
                    $this->canUrl->ADDimportant_params([$pagination->pageParam => null]);
                }
            }


            /*if (YII_DEBUG === true) {
                \Yii::info('_addCanurlParams: totalCount '.$pagination->totalCount, (new \ReflectionClass($this->canUrl))->getName());
                \Yii::info('_addCanurlParams: pageParam '.$pagination->pageParam, (new \ReflectionClass($this->canUrl))->getName());
                \Yii::info('_addCanurlParams: pageCount '.$pagination->pageCount, (new \ReflectionClass($this->canUrl))->getName());

            }*/
        }

    }

    public $canUrlEnableDefaultControllers = [
        'cms/tree',
        'cms/content-element',
        'cms/saved-filter',
        'cms/cms',
    ];

    public function _isTrigerEventCanUrl()
    {
        if (
            \Yii::$app->urlManager->enablePrettyUrl && \Yii::$app->controller && in_array(\Yii::$app->controller->uniqueId, $this->canUrlEnableDefaultControllers)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param View $view
     */
    protected function _autoGenerateKeywords(\yii\web\View $view)
    {
        $content = ob_get_contents();

        if (!isset($view->metaTags['keywords'])) {
            $view->registerMetaTag([
                "name"    => 'keywords',
                "content" => $this->_getKeywordsByContent($content),
            ], 'keywords');
        }

        //\Yii::$app->response->content = $content;
    }

    /**
     * @param $canUrl
     * @return $this
     */
    public function setCanUrl($canUrl)
    {
        $this->_canUrl = $canUrl;
        return $this;
    }

    /**
     * @return array|bool|CanUrl
     */
    public function getCanUrl()
    {
        if ($this->_canUrl === false) {
            return $this->_canUrl;
        }

        if (is_array($this->_canUrl)) {
            $this->_canUrl = ArrayHelper::merge([
                'class' => 'skeeks\cms\seo\vendor\CanUrl',
            ], $this->_canUrl);
            $this->_canUrl = \Yii::createObject($this->_canUrl);
        }

        //print_r($this->_canUrl);die;
        return $this->_canUrl;
    }

    /**
     *
     * Генерация ключевых слов
     *
     * @param string $content
     * @return string
     */
    protected function _getKeywordsByContent($content = "")
    {
        $result = "";


        $content = $this->_processPriority($content);
        if ($content) {
            //Избавляем от тегов и разбиваем в массив
            $content = preg_replace("!<script(.*?)</script>!si", "", $content);
            $content = preg_replace('/(&\w+;)|\'/', ' ', strtolower(strip_tags($content)));
            $words = preg_split('/(\s+)|([\.\,\:\(\)\"\'\!\;])/m', $content);


            foreach ($words as $n => $word) {
                if (strlen($word) < $this->minKeywordLenth ||
                    (int)$word ||
                    strpos($word, '/') !== false ||
                    strpos($word, '@') !== false ||
                    strpos($word, '_') !== false ||
                    strpos($word, '=') !== false ||
                    in_array(StringHelper::strtolower($word), $this->keywordsStopWords)
                ) {
                    unset($words[$n]);
                }
            }
            // получаем массив с числом каждого слова
            $words = array_count_values($words);
            arsort($words); // сортируем - наиболее частые - вперед
            $words = array_keys($words);

            $count = 0;
            foreach ($words as $word) {
                if (strlen($result) > $this->maxKeywordsLength) {
                    break;
                }

                $count++;
                if ($count > 1) {
                    $result .= ', '.StringHelper::strtolower($word);
                } else {
                    $result .= StringHelper::strtolower($word);
                }
            }
        }
        return $result;
    }

    /**
     *
     * Обработка текста согласно приоритетам и тегам H1 и прочим
     *
     * @param string $content
     * @return string
     */
    protected function _processPriority($content = "")
    {
        $contentNewResult = "";

        foreach ($this->keywordsPriority as $tag => $prioryty) {
            if (preg_match_all("!<{$tag}(.*?)\>(.*?)</{$tag}>!si", $content, $words)) {
                $contentNew = "";
                if (isset($words[2])) {
                    foreach ($words[2] as $num => $string) {
                        $contentNew .= $string;
                    }
                }

                if ($contentNew) {
                    for ($i = 1; $i <= $prioryty; $i++) {
                        $contentNewResult .= " ".$contentNew;
                    }
                }
            }
        }

        return $contentNewResult.$content;
    }

    /**
     * @return $this
     */
    public function setNoIndexNoFollow()
    {
        \Yii::$app->view->registerMetaTag([
            'name'    => 'robots',
            'content' => 'noindex, nofollow',
        ], "robots");

        return $this;
    }

    /**
     * @param string $canonicalUrl
     * @return $this
     */
    public function setCanonical(string $canonicalUrl)
    {
        \Yii::$app->view->registerLinkTag([
            'rel'  => 'canonical',
            'href' => $canonicalUrl,
        ], "canonical");

        return $this;
    }
}
