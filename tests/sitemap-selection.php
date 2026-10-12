<?php
// Actual query execution against an isolated database; no site config or writes.
require (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor/autoload.php';
require (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor/yiisoft/yii2/Yii.php';
require __DIR__.'/../src/sitemap/SitemapGenerator.php';

use skeeks\cms\seo\sitemap\SitemapGenerator;
use skeeks\cms\models\CmsTree;

function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
class SelectionFixture extends SitemapGenerator
{
    public function collections() { return $this->collectionQuery()->select('id')->orderBy('id')->column(); }
    public function filters() { return $this->savedFilterQuery()->select('cms_saved_filter.id')->orderBy('cms_saved_filter.id')->column(); }
    public function listed($tree) { return $this->isTreeListed($tree); }
    public function selection($filter) { return $this->isSavedFilterListed($filter); }
    public function secondary() { return $this->usesSecondaryTrees(); }
}
class TreeFixture extends CmsTree
{
    public $descendantIds = [];
    public function behaviors() { return []; }
    public function getDescendants($depth = null, $andSelf = false) { return CmsTree::find()->andWhere(['id' => $this->descendantIds]); }
}
new yii\console\Application(['id' => 'sitemap-selection', 'basePath' => __DIR__, 'extensions' => [],
    'components' => ['db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:']]]);
Yii::$app->errorHandler->unregister();
Yii::$app->set('cms', new class extends yii\base\Component {
    public $element_max_code_length = 100;
});
$shopSite = (object) ['is_show_product_only_quantity' => 0];
Yii::$app->set('skeeks', new class($shopSite) extends yii\base\Component {
    public $site;
    public function __construct($shopSite) { $this->site = (object) ['id' => 1, 'shopSite' => $shopSite]; parent::__construct(); }
});
Yii::$app->set('shop', new class extends yii\base\Component {
    public $stores = [], $supplierStores = [];
});
Yii::$app->set('seo', new class extends yii\base\Component {
    public $is_allow_index_adult_content = false;
});
$db = Yii::$app->db;
foreach ([
    'cms_content_element' => 'id INTEGER PRIMARY KEY, cms_site_id INTEGER, active TEXT, tree_id INTEGER, parent_content_element_id INTEGER',
    'shop_product' => 'id INTEGER PRIMARY KEY, cms_content_element_id INTEGER, product_type TEXT, offers_pid INTEGER, brand_id INTEGER, country_alpha2 TEXT',
    'cms_content_element_property' => 'element_id INTEGER, property_id INTEGER, value_enum INTEGER',
    'shop_collection' => 'id INTEGER PRIMARY KEY, is_active INTEGER',
    'shop_product2collection' => 'shop_product_id INTEGER, shop_collection_id INTEGER',
    'shop_store_product' => 'shop_product_id INTEGER, shop_store_id INTEGER, quantity REAL',
    'cms_tree' => 'id INTEGER PRIMARY KEY, active TEXT, is_index INTEGER, is_adult INTEGER, cms_site_id INTEGER, view_file TEXT',
    'cms_content_element_tree' => 'element_id INTEGER, tree_id INTEGER',
    'cms_theme' => 'id INTEGER PRIMARY KEY, cms_site_id INTEGER, is_active INTEGER, config TEXT',
    'cms_saved_filter' => 'id INTEGER PRIMARY KEY, cms_site_id INTEGER, cms_tree_id INTEGER',
] as $table => $columns) { $db->createCommand("CREATE TABLE $table ($columns)")->execute(); }
foreach ([
    [1, 1, 'Y', 10], [2, 1, 'N', 20], [3, 2, 'Y', 20], [4, 1, 'Y', 30], [5, 1, 'Y', 40],
] as $row) { $db->createCommand()->insert('cms_content_element', array_combine(['id','cms_site_id','active','tree_id'], $row))->execute(); }
foreach ([[1,1,'simple',null], [2,2,'simple',null], [3,3,'simple',null], [4,4,'offer',1], [5,5,'simple',null]] as $row) {
    $db->createCommand()->insert('shop_product', array_combine(['id','cms_content_element_id','product_type','offers_pid'], $row))->execute();
}
foreach ([1,2,3,4,5,6] as $id) { $db->createCommand()->insert('shop_collection', ['id'=>$id, 'is_active'=>$id == 6 ? 0 : 1])->execute(); }
foreach ([[1,1],[2,2],[3,3],[4,4],[1,6]] as $row) {
    $db->createCommand()->insert('shop_product2collection', ['shop_product_id'=>$row[0], 'shop_collection_id'=>$row[1]])->execute();
}
foreach ([10,20,30,40,50] as $id) {
    $db->createCommand()->insert('cms_tree', ['id'=>$id,'active'=>'Y','is_index'=>1,'is_adult'=>0,'cms_site_id'=>1,'view_file'=>'catalog'])->execute();
}
$db->createCommand()->insert('cms_content_element_tree', ['element_id'=>1,'tree_id'=>50])->execute();
$db->createCommand()->insert('cms_theme', ['id'=>1,'cms_site_id'=>1,'is_active'=>1,
    'config'=>serialize(['is_join_second_trees'=>1])])->execute();
$generator = new SelectionFixture(['includeSecondaryTreeProducts'=>false]);
check(array_map('intval', $generator->collections()) === [1,4], 'Only active collections with active site products; offers retained');
function tree($id, $view = 'catalog', $descendants = []) {
    $tree = new TreeFixture();
    $tree->setAttributes(['id'=>$id,'view_file'=>$view], false);
    $tree->populateRelation('treeType', null);
    $tree->descendantIds = $descendants;
    return $tree;
}
check($generator->listed(tree(10)), 'Populated catalog retained');
check(!$generator->listed(tree(20)), 'Inactive and other-site products do not populate catalog');
check(!$generator->listed(tree(30)), 'Offer alone does not populate default catalog');
check($generator->listed(tree(20, 'catalog', [10])), 'Descendant products retained');
check($generator->listed(tree(20, 'text')), 'Non-catalog text pages retained');
check(!$generator->listed(tree(50)), 'Secondary binding disabled');
$withSecondary = new SelectionFixture();
check($withSecondary->secondary(), 'Console reads serialized active theme config');
check($withSecondary->listed(tree(50)), 'Secondary binding enabled');
$shopSite->is_show_product_only_quantity = 1;
Yii::$app->shop->stores = [(object) ['id'=>1]];
Yii::$app->shop->supplierStores = [(object) ['id'=>2]];
check(!$generator->listed(tree(10)), 'Out-of-stock catalog excluded');
$db->createCommand()->insert('shop_store_product', ['shop_product_id'=>4,'shop_store_id'=>1,'quantity'=>1])->execute();
check($generator->listed(tree(10)), 'Offer stock makes parent visible');
$db->createCommand()->insert('shop_store_product', ['shop_product_id'=>5,'shop_store_id'=>2,'quantity'=>1])->execute();
check(!$generator->listed(tree(40)), 'Supplier stock ignored in site-only mode');
$shopSite->is_show_product_only_quantity = 2;
check($generator->listed(tree(40)), 'Supplier stock considered in mode 2');
check(array_map('intval', $generator->collections()) === [1,4], 'Collections do not inherit catalog stock filter');
foreach ([10,20,30,40] as $id) {
    $db->createCommand()->insert('cms_saved_filter', ['id'=>$id,'cms_site_id'=>1,'cms_tree_id'=>$id])->execute();
}
$db->createCommand()->update('cms_tree', ['is_index'=>0], ['id'=>20])->execute();
$db->createCommand()->update('cms_tree', ['active'=>'N'], ['id'=>30])->execute();
$db->createCommand()->update('cms_tree', ['is_adult'=>1], ['id'=>40])->execute();
check(array_map('intval', $generator->filters()) === [10], 'Saved filters respect parent noindex, activity and adult restrictions');

function selection($tree, $attributes = []) {
    return (object) array_merge(['cmsTree'=>$tree, 'shop_brand_id'=>null, 'country_alpha2'=>null,
        'cms_content_property_id'=>null, 'value_content_property_enum_id'=>null,
        'value_content_element_id'=>null], $attributes);
}
$shopSite->is_show_product_only_quantity = 0;
$db->createCommand()->update('shop_product', ['brand_id'=>7, 'country_alpha2'=>'RU'], ['id'=>1])->execute();
$db->createCommand()->update('shop_product', ['brand_id'=>8, 'country_alpha2'=>'IT'], ['id'=>5])->execute();
check($generator->selection(selection(tree(10), ['shop_brand_id'=>7])), 'Brand with a single category product retained');
check(!$generator->selection(selection(tree(40), ['shop_brand_id'=>7])), 'Brand available elsewhere does not populate this category');
check(!$generator->selection(selection(tree(20), ['shop_brand_id'=>7])), 'Inactive or foreign-site products do not populate a selection');
check($generator->selection(selection(tree(20, 'catalog', [10]), ['shop_brand_id'=>7])), 'Selection includes descendants');
check(!$generator->selection(selection(tree(50), ['shop_brand_id'=>7])), 'Selection ignores secondary binding when disabled');
check($withSecondary->selection(selection(tree(50), ['shop_brand_id'=>7])), 'Selection includes enabled secondary binding');
check($generator->selection(selection(tree(10), ['country_alpha2'=>'RU'])), 'Matching country retained');
check(!$generator->selection(selection(tree(10), ['country_alpha2'=>'IT'])), 'Country available in another category excluded');
$db->createCommand()->insert('cms_content_element_property', ['element_id'=>1, 'property_id'=>100, 'value_enum'=>200])->execute();
check($generator->selection(selection(tree(10), ['cms_content_property_id'=>100, 'value_content_property_enum_id'=>200])), 'Matching enum retained');
check(!$generator->selection(selection(tree(10), ['cms_content_property_id'=>101, 'value_content_property_enum_id'=>200])), 'Same value on another property does not match');
check(!$generator->selection(selection(tree(10), ['cms_content_property_id'=>100, 'value_content_property_enum_id'=>201])), 'Empty enum selection excluded');
check(!$generator->selection(selection(tree(40), ['cms_content_property_id'=>100, 'value_content_property_enum_id'=>200])), 'Property in another category does not match');
$db->createCommand()->insert('cms_content_element', ['id'=>6,'cms_site_id'=>1,'active'=>'Y','tree_id'=>10,'parent_content_element_id'=>1])->execute();
$db->createCommand()->insert('cms_content_element_property', ['element_id'=>6,'property_id'=>102,'value_enum'=>300])->execute();
check($generator->selection(selection(tree(10), ['cms_content_property_id'=>102, 'value_content_element_id'=>300])), 'Element selector on a child retains its parent like storefront EAV');
check(!$generator->selection(selection(tree(10), ['cms_content_property_id'=>102, 'value_content_element_id'=>301])), 'Empty element selector excluded');
$shopSite->is_show_product_only_quantity = 1;
$db->createCommand()->delete('shop_store_product', ['shop_store_id'=>1])->execute();
check(!$generator->selection(selection(tree(10), ['shop_brand_id'=>7])), 'Out-of-stock brand selection excluded');
check(!$generator->selection(selection(tree(10), ['cms_content_property_id'=>100, 'value_content_property_enum_id'=>200])), 'Out-of-stock property selection excluded');
$db->createCommand()->insert('shop_store_product', ['shop_product_id'=>4,'shop_store_id'=>1,'quantity'=>1])->execute();
check($generator->selection(selection(tree(10), ['shop_brand_id'=>7])), 'Offer stock retains brand selection');
check($generator->selection(selection(tree(10), ['cms_content_property_id'=>100, 'value_content_property_enum_id'=>200])), 'Offer stock retains property selection');
check(!$generator->selection(selection(tree(40), ['shop_brand_id'=>8])), 'Supplier stock ignored in site-only selection');
$shopSite->is_show_product_only_quantity = 2;
check($generator->selection(selection(tree(40), ['shop_brand_id'=>8])), 'Supplier stock retains selection in mode 2');
check($generator->selection(selection(tree(20, 'text'), ['shop_brand_id'=>7])), 'Custom non-catalog views retained');
check($generator->selection(selection(tree(10), ['cms_content_property_id'=>100])), 'Unknown property selectors retained conservatively');
echo "Sitemap selection checks passed.\n";
