<?php

use yii\helpers\Html;
use skeeks\cms\modules\admin\widgets\form\ActiveFormUseTab as ActiveForm;
use skeeks\cms\seo\models\SeoSitemapTask;
/* @var $this yii\web\View */
/* @var $action \skeeks\cms\modules\admin\actions\modelEditor\AdminOneModelEditAction */
/* @var $model \skeeks\cms\seo\models\SeoSitemapTask */

?>

<? $form = ActiveForm::begin(); ?>

<?= $form->fieldSet(\Yii::t('skeeks/seo','Basic information')); ?>

<?= $form->field($model, 'active')->checkbox(); ?>

<?= $form->field($model, 'cms_site_id')->dropDownList(\yii\helpers\ArrayHelper::map(
    \skeeks\cms\models\CmsSite::find()->all(), 'id', 'name'
));
?>

<?= $form->field($model, 'file_path')->textInput(['maxlength' => 255]) ?>

<?= $form->field($model, 'is_tree')->checkbox(); ?>

<?= $form->fieldSelectMulti($model, 'content_ids', \skeeks\cms\models\CmsContent::getDataForSelect()); ?>



<?= $form->fieldSetEnd(); ?>

<? if (!$model->isNewRecord) : ?>
    <?= $form->fieldSet(\Yii::t('skeeks/seo','Additional information')); ?>
    <?= \yii\widgets\DetailView::widget([
        'model'         => $model,
        'attributes'    =>
            [
                [
                    'attribute'     => 'id',
                    'label'         => \Yii::t('skeeks/seo','Id'),
                ],

                [
                    'attribute' => 'created_at',
                    'value' => \Yii::$app->formatter->asDatetime($model->created_at, 'medium') . "(" . \Yii::$app->formatter->asRelativeTime($model->created_at) . ")",
                ],

            ]
    ]); ?>

    <?= $form->fieldSetEnd(); ?>



    <?= $form->fieldSet(\Yii::t('skeeks/seo','For developers')); ?>

    <div class="sx-block">
        <h3><?=\Yii::t('skeeks/seo','Additional information that may be useful in some cases, the developers.');?></h3>
        <small><?=\Yii::t('skeeks/seo','For the convenience of viewing the data, you can use the service');?>: <a href="http://jsonformatter.curiousconcept.com/#" target="_blank">http://jsonformatter.curiousconcept.com/#</a></small>
    </div>
    <hr />


    <?= \yii\widgets\DetailView::widget([
        'model'         => $model,
        'attributes'    =>
            [
                [
                    'attribute' => 'data_server',
                    'format' => 'raw',
                    'label' => 'SERVER',
                    'value' => "<textarea class='form-control' rows=\"10\">" . \yii\helpers\Json::encode($model->data_server) . "</textarea>"
                ],

                [
                    'attribute' => 'data_cookie',
                    'format' => 'raw',
                    'label' => 'COOKIE',
                    'value' => "<textarea class='form-control' rows=\"5\">" . \yii\helpers\Json::encode($model->data_cookie) . "</textarea>"
                ],

                [
                    'attribute' => 'data_session',
                    'format' => 'raw',
                    'label' => 'SESSION',
                    'value' => "<textarea class='form-control' rows=\"5\">" . \yii\helpers\Json::encode($model->data_session) . "</textarea>"
                ],

                [
                    'attribute' => 'data_request',
                    'format' => 'raw',
                    'label' => 'REQUEST',
                    'value' => "<textarea class='form-control' rows=\"10\">" . \yii\helpers\Json::encode($model->data_request) . "</textarea>"
                ],

            ]
    ]); ?>

    <?= $form->fieldSetEnd(); ?>
<? endif; ?>
<?= $form->buttonsStandart($model); ?>

<? ActiveForm::end(); ?>
