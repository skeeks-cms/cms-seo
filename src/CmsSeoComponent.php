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
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\web\Application;
use yii\web\View;
use yii\widgets\ActiveForm;

/**
 * Class CmsSeoComponent
 * @package skeeks\cms\seo
 */
class CmsSeoComponent extends Component implements BootstrapInterface
{
    /**
     *
     * длина ключевых слов
     *
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
     * @var string
     */
    public $countersContent = "";
    /**
     * @var string
     */
    public $activeTree = true; //Содержимое счетчиков
    /**
     * @var string
     */
    public $activeContentElem = true; //Учитывать активность элемента
    /**
     * @var string
     */
    public $contentIds = []; //Учитывать активность элемента
    /**
     * @var string
     */
    public $treeTypeIds = []; //Учитывать следующие элементы контента
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
     * Добавлять тег канноникал для постраничной навигации
     * @var array
     */
    public $canonicalPageParams = ['page'];
    /**
     * В виджетах ListView registerLinkTags = true по умолчанию
     * @var bool
     */
    public $registerLinkTags = true;

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
        echo $form->fieldSetEnd();

    }

    public function bootstrap($application)
    {

        /**
         * Генерация SEO метатегов.
         * */
        \Yii::$app->view->on(View::EVENT_END_PAGE, function (Event $e) {
            if ($this->enableKeywordsGenerator && !BackendComponent::getCurrent()) {
                if (!\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax) {
                    $this->generateBeforeOutputPage($e->sender);
                }
            }

        });

        /**
         * Добавление канноникал для постранички
         */
        \Yii::$app->on(Application::EVENT_BEFORE_REQUEST, function (Event $e) {

            if ($this->canonicalPageParams && is_array($this->canonicalPageParams)) {
                foreach ($this->canonicalPageParams as $paramName) {
                    if (\Yii::$app->request->get($paramName)) {
                        \Yii::$app->view->registerLinkTag(['rel' => 'canonical', 'href' => \Yii::$app->request->hostInfo . '/' . \Yii::$app->request->pathInfo]);
                    }
                }
            }

            if ($this->registerLinkTags) {
                \Yii::$container->set('yii\widgets\ListView', [
                    'pager' =>
                        [
                            'registerLinkTags' => true
                        ]
                ]);
            }
        });
    }

    public function generateBeforeOutputPage(\yii\web\View $view)
    {
        $content = ob_get_contents();

        if (!isset($view->metaTags['keywords'])) {
            $view->registerMetaTag([
                "name" => 'keywords',
                "content" => $this->keywords($content)
            ], 'keywords');
        }

        \Yii::$app->response->content = $content;
    }

    /**
     *
     * Генерация ключевых слов
     *
     * @param string $content
     * @return string
     */
    public function keywords($content = "")
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
    public function _processPriority($content = "")
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
