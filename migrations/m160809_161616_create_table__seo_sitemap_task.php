<?php
class m160809_161616_create_table__seo_sitemap_task extends yii\db\Migration
{
    const TABLE_NAME = '{{%seo_sitemap_task}}';
    public function up()
    {
        $tableOptions = null;
        $tableExist = $this->db->getTableSchema(self::TABLE_NAME, true);
        if ($tableExist)
        {
            return true;
        }
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';
        }
        $this->createTable(self::TABLE_NAME, [
            'id'                    => $this->primaryKey(),
            'created_by'            => $this->integer(),
            'updated_by'            => $this->integer(),
            'created_at'            => $this->integer(),
            'updated_at'            => $this->integer(),

            'cms_site_id'           => $this->integer()->notNull(),
            'file_path'             => $this->string(255)->defaultValue("sitemap.xml")->notNull()->unique(),
            'is_tree'               => $this->integer(1)->unsigned()->notNull()->defaultValue(1)->comment('0-off,1-on'),
            'content_ids'           => $this->text(),
            'active'                => $this->integer(1)->unsigned()->notNull()->defaultValue(1)->comment('0-off,1-on'),
        ], $tableOptions);

        $this->addForeignKey(
            'seo_sitemap_task__created_by', self::TABLE_NAME,
            'created_by', '{{%cms_user}}', 'id', 'SET NULL', 'SET NULL'
        );

        $this->addForeignKey(
            'seo_sitemap_task__updated_by', self::TABLE_NAME,
            'updated_by', '{{%cms_user}}', 'id', 'SET NULL', 'SET NULL'
        );

        $this->addForeignKey(
            'seo_sitemap_task__cms_site_id', self::TABLE_NAME,
            'cms_site_id', '{{%cms_site}}', 'id', 'RESTRICT', 'RESTRICT'
        );
    }
    public function down()
    {
        $this->dropIndex('seo_sitemap_task__created_by', self::TABLE_NAME);
        $this->dropIndex('seo_sitemap_task__updated_by', self::TABLE_NAME);
        $this->dropIndex('seo_sitemap_task__cms_site_id', self::TABLE_NAME);
        $this->dropTable(self::TABLE_NAME);
    }
}