<?php
use yii\db\Migration;
use yii\db\Query;

/** Preserve schedule identity, user settings and existing run history. */
class m260910_100000_native_seo_sitemap extends Migration
{
    public function safeUp()
    {
        $rows = (new Query())->from('{{%cms_agent}}')
            ->where(['name' => 'seo/sitemap/generate', 'is_system' => 1])->all($this->db);
        foreach ($rows as $row) {
            if (!empty($row['is_running'])) {
                throw new RuntimeException('Stop the running sitemap agent before migrating.');
            }
            if (!empty($row['job_type']) && $row['job_type'] !== 'seo.sitemap.generate') {
                throw new RuntimeException('Customized sitemap job type: resolve it before migrating.');
            }
            if ((new Query())->from('{{%cms_agent}}')
                ->where(['name' => 'job:seo.sitemap.generate', 'cms_site_id' => $row['cms_site_id']])
                ->exists($this->db)) {
                throw new RuntimeException('Duplicate native sitemap schedule: resolve it before migrating.');
            }
            $this->update('{{%cms_agent}}', [
                'name' => 'job:seo.sitemap.generate',
                'job_type' => 'seo.sitemap.generate',
                'job_payload' => null,
            ], ['id' => $row['id']]);
        }
    }

    public function safeDown()
    {
        echo "Native sitemap schedules cannot be reverted automatically.\n";
        return false;
    }
}
