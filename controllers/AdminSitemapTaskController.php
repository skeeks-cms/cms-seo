<?php
/**
 * created by Ekilei <ekilei@rusoft.ru>
 */
namespace skeeks\cms\seo\controllers;

use skeeks\cms\modules\admin\controllers\AdminModelEditorController;
use skeeks\cms\seo\models\SeoSitemapTask;
use skeeks\modules\cms\form2\models\Form2Form;
use yii\helpers\ArrayHelper;

class AdminSitemapTaskController extends AdminModelEditorController
{
    public function init()
    {
        $this->name                     = \Yii::t('skeeks/seo',"Managing Sitemap Task");
        $this->modelShowAttribute       = "id";
        $this->modelClassName           = SeoSitemapTask::className();

        parent::init();
    }

    public function actions()
    {
        return ArrayHelper::merge(parent::actions(),
            [

            ]
        );
    }
}