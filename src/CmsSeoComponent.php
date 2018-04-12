<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 30.03.2015
 */

namespace skeeks\cms\seo;

use skeeks\cms\backend\BackendComponent;
use skeeks\cms\base\Component;
use skeeks\cms\helpers\StringHelper;
use skeeks\cms\seo\vendor\CanUrl;
use yii\base\ActionEvent;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\Widget;
use yii\base\WidgetEvent;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\View;
use yii\widgets\ActiveForm;
use yii\widgets\BaseListView;
use yii\widgets\LinkPager;
use yii\widgets\ListView;

/**
 * @property CanUrl $canUrl;
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CmsSeoComponent extends Component implements BootstrapInterface
{
    /**
     * длина ключевых слов
     * @var int
     */
    public $maxKeywordsLength = 1000;
    /**
     * @var int минимальная длина слова которая попадет в списко ключевых слов
     */
    public $minKeywordLenth = 8;
    /**
     * @var array
     */
    public $keywordsStopWords = [];
    /**
     * @var bool
     */
    public $enableKeywordsGenerator = true;



    /**
     * @var string
     */
    public $robotsContent = "User-agent: *";


    /**
     * Содержимое счетчиков
     * @var string
     */
    public $countersContent = "";


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
    public $sitemap_min_date;

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
        "h1" => 6,
        "h2" => 4,
        "h3" => 3,
        "h5" => 2,
        "h6" => 2,
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
    public $isRedirectNotFoundHttpException = true;




    /**
     * Можно задать название и описание компонента
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name' => \Yii::t('skeeks/seo', 'Seo'),
        ]);
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['enableKeywordsGenerator', 'minKeywordLenth', 'maxKeywordsLength', 'activeContentElem', 'activeTree'], 'integer'],
            ['robotsContent', 'string'],
            ['countersContent', 'string'],
            [['contentIds', 'treeTypeIds'], 'safe'],
            ['sitemap_min_date', 'integer'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'enableKeywordsGenerator' => \Yii::t('skeeks/seo', 'Automatic generation of keywords'),
            'minKeywordLenth' => \Yii::t('skeeks/seo', 'The minimum length of the keyword'),
            'maxKeywordsLength' => \Yii::t('skeeks/seo', 'Length keywords'),
            'robotsContent' => 'Robots.txt',
            'countersContent' => \Yii::t('skeeks/seo', 'Codes counters'),
            'activeTree' => \Yii::t('skeeks/seo', 'Active flag to tree'),
            'activeContentElem' => \Yii::t('skeeks/seo', 'Active flag to contents element'),
            'contentIds' => \Yii::t('skeeks/cms', 'Elements of content'),
            'sitemap_min_date' => \Yii::t('skeeks/seo', 'Минимальная дата обновления ссылки'),
            'treeTypeIds' => \Yii::t('skeeks/seo', 'Types of tree'),
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'enableKeywordsGenerator' => \Yii::t('skeeks/seo', 'If the page is not specified keywords, they will generate is for her, according to certain rules automatically'),
            'minKeywordLenth' => \Yii::t('skeeks/seo', 'The minimum length of the keyword, which is listed by the key (automatic generation)'),
            'maxKeywordsLength' => \Yii::t('skeeks/seo', 'The maximum length of the string of keywords (automatic generation)'),
            'robotsContent' => \Yii::t('skeeks/seo', 'This value is added to the automatically generated file robots.txt, in the case where it is not physically created on the server'),
            'contentIds' => \Yii::t('skeeks/seo', 'If nothing is selected, then all'),
            'treeTypeIds' => \Yii::t('skeeks/seo', 'If nothing is selected, then all'),
            'sitemap_min_date'         => \Yii::t('skeeks/exportShopYandexMarket',
                'Если будет задан этот параметр, то ни в одной ссылке не будет указано даты обновления меньше этой. Используется для переиндексации всех страниц.'),

        ]);
    }


    public function renderConfigForm(ActiveForm $form)
    {
        echo $form->fieldSet(\Yii::t('skeeks/seo', 'Keywords'));

        echo $form->field($this, 'enableKeywordsGenerator')->checkbox(\Yii::$app->formatter->booleanFormat);

        echo $form->field($this, 'minKeywordLenth');
        echo $form->field($this, 'maxKeywordsLength');


        echo $form->fieldSetEnd();

        echo $form->fieldSet(\Yii::t('skeeks/seo', 'Indexing'));
        echo $form->field($this, 'robotsContent')->textarea(['rows' => 7]);
        echo $form->fieldSetEnd();

        echo $form->fieldSet(\Yii::t('skeeks/seo', 'Codes counters'));
        echo $form->field($this, 'countersContent')->textarea(['rows' => 20]);
        echo $form->fieldSetEnd();

        echo $form->fieldSet(\Yii::t('skeeks/seo', 'Sitemap settings'));
        echo $form->field($this, 'activeContentElem')->checkbox(\Yii::$app->formatter->booleanFormat);
        echo $form->field($this, 'activeTree')->checkbox(\Yii::$app->formatter->booleanFormat);


        echo $form->fieldSelectMulti($this, 'contentIds', \skeeks\cms\models\CmsContent::getDataForSelect());
        /*echo $form->fieldSelectMulti($this, 'createdBy')->widget(
            \skeeks\cms\modules\admin\widgets\formInputs\SelectModelDialogUserInput::className()
        );*/

        echo $form->fieldSelectMulti($this, 'treeTypeIds', \yii\helpers\ArrayHelper::map(
            \skeeks\cms\models\CmsTreeType::find()->all(), 'id', 'name'
        ));

        echo $form->field($this, 'sitemap_min_date')->widget(\kartik\datecontrol\DateControl::classname(), [
            'type' => \kartik\datecontrol\DateControl::FORMAT_DATETIME,
        ]);

        echo $form->fieldSetEnd();

    }


    public function init()
    {
        parent::init();
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
            if ($this->enableKeywordsGenerator && !BackendComponent::getCurrent()) {
                if (!\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax) {
                    $this->_generateBeforeOutputPage($e->sender);
                }
            }

        });


        $application->on(Application::EVENT_BEFORE_REQUEST, function (Event $e) {

            /**
             * Добавление метатегов в постраничной навигации
             */
            if ($this->registerLinkTags) {
                \Yii::$container->set('yii\widgets\LinkPager', [
                    'registerLinkTags' => true
                ]);
            }

            /**
             * Убирает page=1, делает чистый урл в постричной новигации на первую страницу
             */
            \Yii::$container->set('yii\data\Pagination', [
                'forcePageParam' => $this->forcePageParam
            ]);
        });


        /**
         * Стандартная инициализация canurl
         */
        Event::on(Controller::class, Controller::EVENT_BEFORE_ACTION, function (ActionEvent $e) {
            $this->_initDefaultCanUrl();

            /**
             * Редирект 404 ошибок
             */
            if (\Yii::$app->controller->uniqueId == 'cms/error') {
                if (\Yii::$app->getErrorHandler()->exception instanceof NotFoundHttpException && $this->isRedirectNotFoundHttpException) {
                    \Yii::$app->response->redirect(Url::home());
                    \Yii::$app->response->getHeaders()->setDefault('X-Skeeks-Seo-Not-Found', "isRedirectNotFoundHttpException=true");
                    \Yii::$app->end();
                    return;
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

        $application->view->on(View::EVENT_END_PAGE, function ($e) {
            if ($this->_isTrigerEventCanUrl()) {
                if ($this->canUrl) {
                    $this->canUrl->event_end_page($e);
                }

            }
        });
    }

    protected function _initDefaultCanUrl() {

        /**
         * Канурл может быть отключен вовсе
         */
        if ($this->canUrl === false) {
            return false;
        }

        /**
         * Хост может быть не указан, тогда будет взят из запроса
         */
        if (!$this->canUrl->host) {
            $this->canUrl->host = \Yii::$app->request->hostName;
        }

        /**
         * Аналогично со схемой
         */
        if (!$this->canUrl->scheme) {
            $this->canUrl->scheme = \Yii::$app->request->isSecureConnection ? "https" : "http";
        }


        if (\Yii::$app->requestedRoute) {
            $requestedUrl = Url::to(ArrayHelper::merge(["/" . \Yii::$app->requestedRoute],
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
        if (!in_array(\Yii::$app->controller->uniqueId, [
            'cms/tree',
            'cms/content-element',
            'savedFilters/saved-filters',
        ])) {
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
        
        \Yii::info('_addCanurlParams: ' . $r->getName());
        
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
                    } elseif($currentPage != 1) {
                        $this->canUrl->ADDimportant_params([$pagination->pageParam => null]);
                    }
                } else {
                   $this->canUrl->ADDimportant_params([$pagination->pageParam => null]);
                }
            }
            
            
            
            \Yii::info('_addCanurlParams: totalCount ' . $pagination->totalCount);
            \Yii::info('_addCanurlParams: pageParam ' . $pagination->pageParam);
            \Yii::info('_addCanurlParams: pageCount ' . $pagination->pageCount);
            
        }
        
    }

    public function _isTrigerEventCanUrl()
    {
        if (\Yii::$app->controller && in_array(\Yii::$app->controller->uniqueId, [
            'cms/tree',
            'cms/content-element',
            'savedFilters/saved-filters',
        ])) {
            return true;
        }

        return false;
    }

    protected function _generateBeforeOutputPage(\yii\web\View $view)
    {
        $content = ob_get_contents();

        if (!isset($view->metaTags['keywords'])) {
            $view->registerMetaTag([
                "name" => 'keywords',
                "content" => $this->_getKeywordsByContent($content)
            ], 'keywords');
        }

        \Yii::$app->response->content = $content;
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
                'class' => 'skeeks\cms\seo\vendor\CanUrl'
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
                if (strlen($result) > $this->maxKeywordsLength) break;

                $count++;
                if ($count > 1) {
                    $result .= ', ' . StringHelper::strtolower($word);
                } else
                    $result .= StringHelper::strtolower($word);
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
                        $contentNewResult .= " " . $contentNew;
                    }
                }
            }
        }

        return $contentNewResult . $content;
    }

}
