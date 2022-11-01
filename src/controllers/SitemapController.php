<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\seo\controllers;

use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsSavedFilter;
use skeeks\cms\models\CmsTree;
use skeeks\cms\models\Tree;
use yii\helpers\Url;
use yii\web\Controller;

/**
 * Class SitemapController
 * @package skeeks\cms\seo\controllers
 */
class SitemapController extends Controller
{
    /**
     * @return string
     */
    public function actionOnRequest()
    {
        ini_set("memory_limit", "1024M");

        $result = [];

        $this->_addTrees($result);
        $this->_addSavedFilters($result);
        $this->_addElements($result);
        $this->_addAdditional($result);


        \Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        \Yii::$app->response->headers->add('Content-Type', 'text/xml');
        return $this->renderPartial($this->action->id, [
            'data' => $result,
        ]);
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addTrees(&$data = [])
    {
        $query = CmsTree::find()->cmsSite();
        $query->andWhere([
            'and',
            [
                'redirect_tree_id' => null,
            ],
            [
                'redirect_content_element_id' => null,
            ],
            [
                'redirect_saved_filter_id' => null,
            ],
            [
                'or',
                ['redirect' => null],
                ['redirect' => ""]
            ]
        ]);

        $query->andWhere([
            'and',
            [
                'canonical_tree_id' => null,
            ],
            [
                'canonical_content_element_id' => null,
            ],
            [
                'canonical_saved_filter_id' => null,
            ],
            [
                'or',
                ['canonical_link' => null],
                ['canonical_link' => ""]
            ]
        ]);
        
        $query->andWhere([
                'is_index' => 1,
        ]);

        if (\Yii::$app->seo->treeTypeIds) {
            $query->andWhere(['tree_type_id' => \Yii::$app->seo->treeTypeIds]);
        }

        $trees = $query->orderBy(['level' => SORT_ASC, 'priority' => SORT_ASC])->all();

        if ($trees) {
            /**
             * @var Tree $tree
             */
            foreach ($trees as $tree) {
                //if (!$tree->redirect && !$tree->redirect_tree_id) {
                    $tmp = [
                        "loc"     => $tree->absoluteUrl,
                        "lastmod" => $this->_lastMod($tree),
                    ];

                    if (\Yii::$app->seo->is_sitemap_priority) {
                        $tmp['priority'] = $this->_calculatePriority($tree);
                    }

                    $data[] = $tmp;
                //}
            }
        }


        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addSavedFilters(&$data = [])
    {
        $query = CmsSavedFilter::find()->cmsSite();
        $savedFilters = $query->all();

        if ($savedFilters) {
            /**
             * @var CmsSavedFilter $savedFilter
             */
            foreach ($savedFilters as $savedFilter) {
                $tmp = [
                    "loc"     => $savedFilter->absoluteUrl,
                    "lastmod" => $this->_lastMod($savedFilter),
                ];

                if (\Yii::$app->seo->is_sitemap_priority) {
                    $tmp['priority'] = '0.8';
                }

                $data[] = $tmp;
            }
        }


        return $this;
    }

    /**
     * @param Tree $model
     * @return string
     */
    private function _lastMod($model)
    {
        $string = date("c", $model->updated_at);

        if (\Yii::$app->seo->sitemap_min_date && \Yii::$app->seo->sitemap_min_date > $model->updated_at) {
            $string = date("c", \Yii::$app->seo->sitemap_min_date);
        }

        return $string;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addElements(&$data = [])
    {
        $query = CmsContentElement::find()
            ->cmsSite()->active()
            ->innerJoinWith("cmsContent as cmsContent")
            ->andWhere(['cmsContent.is_have_page' => 1])
        ;
            //->joinWith('cmsTree')
            //->andWhere([Tree::tableName().'.cms_site_id' => \Yii::$app->skeeks->site->id])
        ;

        //if (\Yii::$app->seo->activeContentElem) {
            //$query->andWhere([CmsContentElement::tableName().'.active' => 'Y']);
        //}

        if (\Yii::$app->seo->contentIds) {
            $query->andWhere(['content_id' => \Yii::$app->seo->contentIds]);
        }

        $elements = $query->orderBy(['updated_at' => SORT_DESC, 'priority' => SORT_ASC])->all();

        //Добавление элементов в карту
        if ($elements) {
            /**
             * @var CmsContentElement $model
             */
            foreach ($elements as $model) {
                $tmp = [
                    "loc"     => $model->absoluteUrl,
                    "lastmod" => $this->_lastMod($model),
                ];

                if (\Yii::$app->seo->is_sitemap_priority) {
                    $tmp['priority'] = '0.8';
                }

                $data[] = $tmp;
            }
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function _addAdditional(&$data = [])
    {
        $data[] = [
            'loc' => Url::to(['/cms/cms/index'], true),
        ];

        return $this;
    }

    /**
     * @param Tree $model
     * @return string
     */
    private function _calculatePriority($model)
    {
        $priority = '0.4';
        if ($model->level == 0) {
            $priority = '1.0';
        } else if ($model->level == 1) {
            $priority = '0.8';
        } else if ($model->level == 2) {
            $priority = '0.7';
        } else if ($model->level == 3) {
            $priority = '0.6';
        } else if ($model->level == 4) {
            $priority = '0.5';
        }

        return $priority;
    }
}
