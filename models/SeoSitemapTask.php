<?php
/**
 * created by Ekilei <ekilei@rusoft.ru>
 */
namespace skeeks\cms\seo\models;

use skeeks\cms\models\CmsContentType;
use skeeks\cms\models\CmsSite;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%seo_sitemap_task}}".
 *
 * @property integer $id
 * @property integer $created_by
 * @property integer $updated_by
 * @property integer $created_at
 * @property integer $updated_at
 *
 * @property integer $cms_site_id
 * @property string $file_path
 * @property integer $is_tree
 * @property string $content_ids
 * @property integer $active
 *
 * @property User $processedBy
 * @property CmsContent $content
 * @property User $createdBy
 * @property CmsContentElement $element
 * @property CmsSite $siteCode
 * @property CmsSite $site
 */

class SeoSitemapTask extends \skeeks\cms\models\Core
{
    public $verifyCode;

    CONST ON    = 1;
    CONST OFF   = 0;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%seo_sitemap_task}}';
    }

    static public function getActive()
    {
        return [
            self::ON    => \Yii::t('skeeks/seo',"On"),
            self::OFF   => \Yii::t('skeeks/seo',"Off"),
        ];
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['file_path','content_ids'],'string'],
            [['cms_site_id','is_tree','active'],'integer'],
            [['file_path'],'unique'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => \Yii::t('skeeks/seo', 'ID'),
            'created_by' => \Yii::t('skeeks/seo', 'Created By'),
            'updated_by' => \Yii::t('skeeks/seo', 'Updated By'),
            'created_at' => \Yii::t('skeeks/seo', 'Created At'),
            'updated_at' => \Yii::t('skeeks/seo', 'Updated At'),
            'cms_site_id' => \Yii::t('skeeks/seo', 'Site'),
            'file_path' => \Yii::t('skeeks/seo', 'File Path'),
            'is_tree' => \Yii::t('skeeks/seo', 'Tree'),
            'content_ids' => \Yii::t('skeeks/seo', 'Contents'),
            'active' => \Yii::t('skeeks/seo', 'Active'),
        ];
    }


}