<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\seo\controllers;

use skeeks\cms\seo\models\CmsContentElement;
use skeeks\cms\seo\models\CmsSearchPhrase;
use yii\web\Controller;
use yii\web\Response;

/**
 * Class RobotsController
 * @package skeeks\cms\seo\controllers
 */
class RobotsController extends Controller
{
    /**
     * @return string
     */
    public function actionOnRequest()
    {
        echo \Yii::$app->seo->robotsContent;
        \Yii::$app->response->format = Response::FORMAT_RAW;
        \Yii::$app->response->headers->set('Content-Type', 'text/plain');
        $this->layout = false;
    }
}
