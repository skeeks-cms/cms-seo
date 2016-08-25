<?php
/**
 * created by Ekilei <ekilei@rusoft.ru>
 */
?>
<?= \skeeks\cms\modules\admin\widgets\GridViewStandart::widget([
    'dataProvider'      => $dataProvider,
    'filterModel'       => $searchModel,
    'adminController'   => $controller,
    'isOpenNewWindow'   => $isOpenNewWindow ? true : false,
    'columns' => [
        [
            'class' => \skeeks\cms\grid\CreatedAtColumn::className(),
            'label' => \Yii::t('skeeks/seo','Added')
        ],
        [
            'class' => \skeeks\cms\grid\CreatedByColumn::className(),
        ],

        [
            'attribute' => 'cms_site_id',
            'class' => \skeeks\cms\grid\SiteColumn::className(),
        ],
        ['attribute' => 'file_path'],
        ['attribute' => 'active'],
    ],
]); ?>