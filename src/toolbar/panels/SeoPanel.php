<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link https://skeeks.com/
 * @copyright (c) 2010 SkeekS
 * @date 23.11.2017
 */

namespace skeeks\cms\seo\toolbar\panels;

use skeeks\cms\toolbar\CmsToolbarPanel;

/**
 * Class SeoPanel
 * @package skeeks\cms\seo\toolbar\panels
 */
class SeoPanel extends CmsToolbarPanel
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return \Yii::t('skeeks/seo', 'Seo panel');
    }

    /**
     * @inheritdoc
     */
    public function getSummary()
    {
        return \Yii::$app->view->render('@skeeks/cms/seo/toolbar/panels/views/seo', ['panel' => $this]);
    }
}
