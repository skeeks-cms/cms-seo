<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link https://skeeks.com/
 * @copyright (c) 2010 SkeekS
 * @date 22.11.2017
 */
/* @var $this yii\web\View */
/* @var $panel \skeeks\cms\toolbar\pnales\ConfigPanel */
?>
<div class="sx-cms-toolbar__block">
    <a
       href="#" title="<?= \Yii::t('skeeks/seo', 'Managing project settings', [],
        \Yii::$app->admin->languageCode) ?>">
        <div class="sx-cms-toolbar__label sx-cms-toolbar__label_info">
            <img height="21" src="data:image/svg+xml;utf8;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pgo8IS0tIEdlbmVyYXRvcjogQWRvYmUgSWxsdXN0cmF0b3IgMTkuMC4wLCBTVkcgRXhwb3J0IFBsdWctSW4gLiBTVkcgVmVyc2lvbjogNi4wMCBCdWlsZCAwKSAgLS0+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgdmVyc2lvbj0iMS4xIiBpZD0iTGF5ZXJfMSIgeD0iMHB4IiB5PSIwcHgiIHZpZXdCb3g9IjAgMCA1MTIgNTEyIiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA1MTIgNTEyOyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSIgd2lkdGg9IjI0cHgiIGhlaWdodD0iMjRweCI+CjxnPgoJPGc+CgkJPHBhdGggZD0iTTAsMTQ3LjJ2MzA3LjJjMCwyMS4yMDcsMTcuMTkzLDM4LjQsMzguNCwzOC40aDQzNS4yYzIxLjIwNywwLDM4LjQtMTcuMTkzLDM4LjQtMzguNFYxNDcuMkgweiBNMTgxLjM5MywzNzAuNjA3ICAgIGwtMjkuOTg1LDIzLjk4OGwtNjAuNzk1LTc1Ljk5NWw2MC43OTUtNzUuOTk1bDI5Ljk4NSwyMy45ODhMMTM5Ljc4OCwzMTguNkwxODEuMzkzLDM3MC42MDd6IE0yMjIuMzQ1LDQ0MS42bC0zNS4wOS0xNS41OTcgICAgbDEwMi40LTIzMC40bDM1LjA5LDE1LjU5N0wyMjIuMzQ1LDQ0MS42eiBNMzYwLjU5MywzOTQuNTk2bC0yOS45ODUtMjMuOTg4bDQxLjYwNS01Mi4wMDVsLTQxLjYwNS01Mi4wMDVsMjkuOTg1LTIzLjk4OSAgICBsNjAuNzk1LDc1Ljk5NUwzNjAuNTkzLDM5NC41OTZ6IiBmaWxsPSIjRkZGRkZGIi8+Cgk8L2c+CjwvZz4KPGc+Cgk8Zz4KCQk8cGF0aCBkPSJNNDczLjYsMTkuMkgzOC40QzE3LjE5MywxOS4yLDAsMzYuMzkzLDAsNTcuNnY1MS4yaDUxMlY1Ny42QzUxMiwzNi4zOTMsNDk0LjgwNywxOS4yLDQ3My42LDE5LjJ6IE02NCw4OS42ICAgIGMtMTQuMTM5LDAtMjUuNi0xMS40NjEtMjUuNi0yNS42UzQ5Ljg2MSwzOC40LDY0LDM4LjRTODkuNiw0OS44NjEsODkuNiw2NFM3OC4xMzksODkuNiw2NCw4OS42eiBNNDczLjYsODMuMkgyNTZWNDQuOGgyMTcuNlY4My4yICAgIHoiIGZpbGw9IiNGRkZGRkYiLz4KCTwvZz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8Zz4KPC9nPgo8L3N2Zz4K" />
            <span class="sx-cms-toolbar__parent_hover_active">
            <?= \Yii::t('skeeks/seo',
                'SEO', [], \Yii::$app->admin->languageCode) ?>
            </span>
        </div>
    </a>
</div>

<?php
$this->registerJs(<<<JS
$(function () {
    if (!$("h1").length) {
        
    }    
});
JS
);
?>
