<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once dirname(__FILE__) . '/../../classes/SmartBlogHelperTreeCategories.php';

class AdminBlogPostController extends ModuleAdminController {

    public $asso_type = 'shop';
    protected $_blog_post = null;
    public $max_file_size = null;
    public $max_image_size = null;
    private $post_format_fields;

    public function __construct() {
        $this->table = 'smart_blog_post';
        $this->className = 'SmartBlogPost';
        $this->module = 'smartblog';
        $this->lang = true;
        $this->image_dir = '../modules/smartblog/images';
        $this->context = Context::getContext();
        $this->_defaultOrderBy = 'created';
        $this->_defaultorderWay = 'DESC';
        $this->bootstrap = true;
        if (Shop::isFeatureActive())
            Shop::addTableAssociation($this->table, array('type' => 'shop'));

        $this->fields_list = array(
            'id_smart_blog_post' => array(
                'title' => $this->l('Id'),
                'width' => 50,
                'type' => 'text',
                'orderby' => true,
                'filter' => true,
                'search' => true
            ),
            'viewed' => array(
                'title' => $this->l('View'),
                'width' => 50,
                'type' => 'text',
                'lang' => true,
                'orderby' => true,
                'filter' => false,
                'search' => false
            ),
            'image' => array(
                'title' => $this->l('Image'),
                'image' => $this->image_dir,
                'orderby' => false,
                'search' => false,
                'width' => 200,
                'align' => 'center',
                'orderby' => false,
                'filter' => false,
                'search' => false
            ),
            'meta_title' => array(
                'title' => $this->l('Title'),
                'width' => 440,
                'type' => 'text',
                'lang' => true,
                'orderby' => true,
                'filter' => true,
                'search' => true
            ),
            'created' => array(
                'title' => $this->l('Posted Date'),
                'width' => 100,
                'type' => 'date',
                'lang' => true,
                'orderby' => true,
                'filter' => true,
                'search' => true
            ),
            'active' => array(
                'title' => $this->l('Status'),
                'width' => '70',
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'orderby' => true,
                'filter' => true,
                'search' => true
            )
        );
        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . 'smart_blog_post_shop sbs ON a.id_smart_blog_post=sbs.id_smart_blog_post && sbs.id_shop IN(' . implode(',', Shop::getContextListShopID()) . ')';
        $this->_select = 'sbs.id_shop';
        $this->_defaultOrderBy = 'a.id_smart_blog_post';
        $this->_defaultOrderWay = 'DESC';
        if (Shop::isFeatureActive() && Shop::getContext() != Shop::CONTEXT_SHOP) {
            $this->_group = 'GROUP BY a.smart_blog_post';
        }
        
        parent::__construct();
    }

    public function renderList() {
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        return parent::renderList();
    }

    public function setMedia() {
        parent::setMedia();
        $this->addJqueryPlugin(array('tagify', 'tablednd', 'autocomplete'));
    }

    public function initPageHeaderToolbar() {
        if (empty($this->display))
            $this->page_header_toolbar_btn['new_smart_blog_post'] = array(
                'href' => self::$currentIndex . '&addsmart_blog_post&token=' . $this->token,
                'desc' => $this->l('Add new Blog Post', null, null, false),
                'icon' => 'process-icon-new'
            );
        if ($this->display == 'edit') {
            if (($smart_blog_post = $this->loadObject(true)) && $smart_blog_post->isAssociatedToShop()) {
                // adding button for preview this smart_blog_post
                if ($url_preview = $this->getPreviewUrl($smart_blog_post))
                    $this->page_header_toolbar_btn['preview'] = array(
                        'short' => $this->l('Preview', null, null, false),
                        'href' => $url_preview,
                        'desc' => $this->l('Preview', null, null, false),
                        'target' => true,
                        'class' => 'previewUrl'
                    );



                // adding button for delete this product
                if ($this->tabAccess['delete'])
                    $this->page_header_toolbar_btn['delete'] = array(
                        'short' => $this->l('Delete', null, null, false),
                        'href' => $this->context->link->getAdminLink('AdminBlogPost') . '&id_smart_blog_post=' . (int) $smart_blog_post->id . '&deletesmart_blog_post',
                        'desc' => $this->l('Delete this Blog Post', null, null, false),
                        'confirm' => 1,
                        'js' => 'if (confirm(\'' . $this->l('Delete Blog Post?', null, true, false) . '\')){return true;}else{event.preventDefault();}'
                    );
            }
        }
        parent::initPageHeaderToolbar();
    }

    public function getPreviewUrl(SmartBlogPost $smart_blog_post) {
       
       $id_lang = Configuration::get('PS_LANG_DEFAULT', null, null, Context::getContext()->shop->id);

        if (!ShopUrl::getMainShopDomain())
            return false;

        $is_rewrite_active = (bool) Configuration::get('PS_REWRITING_SETTINGS');

        $blog_url = $preview_url = smartblog::GetSmartBlogLink('smartblog_post', array('id_post' => $smart_blog_post->id,'slug'=>$smart_blog_post->link_rewrite[$id_lang ]));

        if (!$smart_blog_post->active) {
            $admin_dir = dirname($_SERVER['PHP_SELF']);
            $admin_dir = substr($admin_dir, strrpos($admin_dir, '/') + 1);
            $preview_url .= ((strpos($preview_url, '?') === false) ? '?' : '&') . 'adtoken=' . $this->token . '&ad=' . $admin_dir . '&id_employee=' . (int) $this->context->employee->id;
        }

        return $preview_url;
    }

    public function postProcess() {

 
        if (!in_array($this->display, array('edit', 'add')))
            $this->multishop_context_group = false;
        if (Tools::isSubmit('forcedeleteImage') || (isset($_FILES['image']) && $_FILES['image']['size'] > 0) || Tools::getValue('deleteImage')) {
            $this->processForceDeleteImage();
            if (Tools::isSubmit('forcedeleteImage'))
                Tools::redirectAdmin(self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminCategories') . '&conf=7');
        }
        
        if (Tools::isSubmit('submitAddsmart_blog_post')) {
            if (Tools::getValue('id_smart_blog_post'))
                $this->processUpdate();
            else
                $this->processAdd();
        }else {
            parent::postProcess(true);
        }
    }

    /**
     * Override processAdd to change SaveAndStay button action
     * @see classes/AdminControllerCore::processAdd()
     */
    
//    public function processAdd() {
//        $object = parent::processAdd();
//        
////        $id = $this->object->id;
////        if (!empty($id)) {
////            $object = new SmartBlogPost($id);
////        }
//        
////        $this->updateTags(Language::getLanguages(false), $object);
//        date_default_timezone_set(Configuration::get('PS_TIMEZONE'));
//        $object->id_author = $this->context->employee->id;
//         
//        $object->modified = date('Y-m-d H:i:s');
//        if (strpos($object->created, '0000-00-00') !== FALSE || empty($object->created)) {
//            $object->created = date('Y-m-d H:i:s');
//        }
//        $object->update();
//        BlogCategory::updateAssocCat($object->id);
//        $this->post_format_fields = smartblog::$post_meta_fields;
//        $this->updateMetaFields($object);
//
//        Hook::exec('actionsbnewpost');
//    }
    protected function afterAdd($object)
    {

        $id = $this->object->id;
        if (!empty($id)) {
            $object = new SmartBlogPost($id);

            date_default_timezone_set(Configuration::get('PS_TIMEZONE'));
            // $object->id_author = $this->context->employee->id;
            $object->id_author = 1;

            $object->modified = date('Y-m-d H:i:s');
            if (strpos($object->created, '0000-00-00') !== FALSE || empty($object->created)) {
                $object->created = date('Y-m-d H:i:s');
            }
            $object->update();
            BlogCategory::updateAssocCat($object->id);
        $this->post_format_fields = smartblog::$post_meta_fields;
        $this->updateMetaFields($object);
        Hook::exec('actionsbnewpost');
        }
    }

//    public function processUpdate() {
//
//        $object = parent::processUpdate();
//
////        $this->updateTags(Language::getLanguages(false), $object);
//        date_default_timezone_set(Configuration::get('PS_TIMEZONE'));
//        $object->modified = date('Y-m-d H:i:s');
//        $object->update();
//        BlogCategory::updateAssocCat($object->id);
//        // clear the post when update
//        Hook::exec('actionsbupdatepost');
//    }
    
    protected function afterUpdate($object) {
        $id = $object->id;
        if (!empty($id)) {
            date_default_timezone_set(Configuration::get('PS_TIMEZONE'));
        $object->modified = date('Y-m-d H:i:s');
        $object->update();
        BlogCategory::updateAssocCat($object->id);
        // clear the post when update
        Hook::exec('actionsbupdatepost');
        }
        $res = parent::afterUpdate($object);
        $this->post_format_fields = smartblog::$post_meta_fields;
        $id_smart_blog_post = (int)Tools::getValue('id_smart_blog_post');
        $smart_blog_post = new SmartBlogPost($id_smart_blog_post);
        if (Validate::isLoadedObject($smart_blog_post)) {
//             $this->updateAccessories($object);
            $this->updateTags(Language::getLanguages(false), $object);
            $this->updateMetaFields($object);
        }

        return $res;
    }

    private function updateMetaFields($object) {

        if (isset($this->post_format_fields[$object->post_type]) && !empty($this->post_format_fields[$object->post_type])) {
            $fieldgroup = $object->post_type;
            
            foreach ($this->post_format_fields[$object->post_type] as $field) {
                if(isset($field['lang']) && $field['lang']){
                    $languages = Language::getLanguages();
                    foreach($languages as $language){
                        $fieldname = "{$fieldgroup}-{$field['name']}_{$language['id_lang']}";
                        if (Tools::isSubmit($fieldname)){
                            BlogPostMeta::updateValue($object->id, $fieldname, pSQL(Tools::getValue($fieldname)));
                        }
                    }
                }else{                    
                    if (Tools::isSubmit("{$fieldgroup}-{$field['name']}")){
                        BlogPostMeta::updateValue($object->id, "{$fieldgroup}-{$field['name']}", pSQL(Tools::getValue("{$fieldgroup}-{$field['name']}")));
                    }
                }
            }

        }
        //BlogPostMeta::updateValue()
    }

    /**
     * @param Employee $object
     *
     * @return bool
     */
    

   protected function postImage($id) {
        $ret = parent::postImage($id);
        if (isset($_FILES['image']) && isset($_FILES['image']['tmp_name']) && !empty($_FILES['image']['tmp_name'])) {
            if ($error = ImageManager::validateUpload($_FILES['image'], 4000000))
                return $this->displayError($this->l('Invalid image'));
            else {

                $path = _PS_MODULE_DIR_ . 'smartblog/images/' . $id . '.' . $this->imageType;

                $tmp_name = tempnam(_PS_TMP_IMG_DIR_, 'PS');
                if (!$tmp_name)
                    return false;



                if (!move_uploaded_file($_FILES['image']['tmp_name'], $tmp_name))
                    return false;


                // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
                if (!ImageManager::checkImageMemoryLimit($tmp_name))
                    $this->errors[] = Tools::displayError('Due to memory limit restrictions, this image cannot be loaded. Please increase your memory_limit value via your server\'s configuration settings. ');

                // Copy new image
                if (empty($this->errors) && !ImageManager::resize($tmp_name, $path, (int) $width, (int) $height, ($ext ? $ext : $this->imageType)))
                    $this->errors[] = Tools::displayError('An error occurred while uploading the image.');

                if (count($this->errors))
                    return false;
                if ($this->afterImageUpload()) {
                    unlink($tmp_name);
                    //  return true;
                }

                $posts_types = BlogImageType::GetImageAllType('post');
                foreach ($posts_types as $image_type) {
                    $dir = _PS_MODULE_DIR_ . 'smartblog/images/' . $id . '-' . stripslashes($image_type['type_name']) . '.jpg';
                    if (file_exists($dir))
                        unlink($dir);
                }
                foreach ($posts_types as $image_type) {
                    ImageManager::resize($path, _PS_MODULE_DIR_ . 'smartblog/images/' . $id . '-' . stripslashes($image_type['type_name']) . '.jpg', (int) $image_type['width'], (int) $image_type['height']
                    );
                }
            }
        }
        return $ret;
    }


    public function ajaxProcessGetCategoryTree() {
        $root_category = BlogCategory::getRootCategory();
        $category = pSQL(Tools::getValue('category', $root_category['id_category']));
        $full_tree = pSQL(Tools::getValue('fullTree', 0));
        $use_check_box = pSQL(Tools::getValue('useCheckBox', 1));
        $selected = Tools::getValue('selected', array());
        $id_tree = pSQL(Tools::getValue('type'));
        $input_name = str_replace(array('[', ']'), '', Tools::getValue('inputName', null));

        $tree = new SmartBlogHelperTreeCategories('subtree_associated_categories');
        $tree->setTemplate('subtree_associated_categories.tpl')
                ->setUseCheckBox($use_check_box)
                ->setUseSearch(false)
                ->setIdTree($id_tree)
                ->setSelectedCategories($selected)
                ->setFullTree($full_tree)
                ->setChildrenOnly(true)
                ->setNoJS(true)
                ->setRootCategory($category);

        if ($input_name) {
            $tree->setInputName($input_name);
        }

        die($tree->render());
    }

    public function renderForm() {
        $this->post_format_fields = smartblog::$post_meta_fields;
        if (!($obj = $this->loadObject(true)))
            return;

        $shops = false;
        if (Shop::isFeatureActive())
            $shops = Shop::getShops();


        if ($shops)
            foreach ($shops as $key => $shop)
                if (!$obj->isAssociatedToShop($shop['id_shop']))
                    unset($shops[$key]);



        $languages = Language::getLanguages(false);
        //Added From Old 


        /*         * ************* featured image ****************** */

        $featured_image = _MODULE_SMARTBLOG_DIR_ . $obj->id . '.jpg';
        $image_url = ImageManager::thumbnail($featured_image, $this->table . '_' . pSQL(Tools::getvalue('id_smart_blog_post')) . '.jpg', 200, 'jpg', true, true);
        $image_size = file_exists($featured_image) ? filesize($featured_image) / 1000 : false;

        /*         * ************* featured image ****************** */

        // image gallary
        $id_smart_blog_post = (int) Tools::getValue('id_smart_blog_post');

        $images = SmartBlogGallaryImage::getImages($this->context->language->id, $id_smart_blog_post);


        foreach ($images as $k => $image) {

            $images[$k] = new SmartBlogGallaryImage($image['id_smart_blog_gallary_images']);
        }

 

        //start sdsimage type
        $image_uploader = new HelperImageUploader('file');
        $image_uploader->setMultiple(!(Tools::getUserBrowser() == 'Apple Safari' && Tools::getUserPlatform() == 'Windows'))
                ->setUseAjax(true)->setUrl(
                Context::getContext()->link->getAdminLink('AdminSmartBlogAjax') . '&ajax=1&id_smart_blog_post=' . (int) Tools::getvalue('id_smart_blog_post')
                . '&action=addGallaryImage');


        // test code

        $root = BlogCategory::getRootCategory();
        $default_category = $root['id_smart_blog_category'];
        if (!Tools::isSubmit('id_smart_blog_post')) {
            $selected_cat = BlogCategory::getCategoryInformations(Tools::getValue('categoryBox', array($default_category)), $this->default_form_language);
        } else {
            if (Tools::isSubmit('categoryBox')) {
                $selected_cat = BlogCategory::getCategoryInformations(Tools::getValue('categoryBox', array($default_category)), $this->default_form_language);
            } else {
                $selected_cat = BlogCategory::getPostCategoriesFull((int) Tools::getValue('id_smart_blog_post'), $this->default_form_language);
            }
        }

        $categories = array();
        foreach ($selected_cat as $key => $category) {
            $categories[] = $key;
        }
        $tree = new SmartBlogHelperTreeCategories('smartblog-associated-categories-tree', 'Associated categories');
        $tree->setTemplate('tree_associated_categories.tpl')
                ->setHeaderTemplate('tree_associated_header.tpl')
                ->setRootCategory((int) $root['id_category'])
                ->setUseCheckBox(true)
                ->setUseSearch(false)
                ->setSelectedCategories($categories);
        // end test code


        $temp_employees = Employee::getEmployees();
        $employees = array();
        foreach ($temp_employees as $employee) {
            $employee['fullname'] = $employee['firstname'] . ' ' . $employee['lastname'];
            $employee['id_select'] = $employee['id_employee'];
            $employees[] = $employee;
        }


        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Blog Post'),
            ),
            'input' => array(
//                array(
//                    'type' => 'hidden',
//                    'name' => 'post_type',
//                    'default_value' => 0,
//                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Blog Title'),
                    'name' => 'meta_title',
                    'id' => 'name',
                    'class' => 'copyMeta2friendlyURL',
                    'size' => 60,
                    'required' => true,
                    'desc' => $this->l('Enter Your Blog Post Title'),
                    'lang' => true,
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Author'),
                    'name' => 'id_author',
                    'options' => array(
                        'query' => $employees,
                        'id' => 'id_select',
                        'name' => 'fullname',
                    )
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Description'),
                    'name' => 'content',
                    'lang' => true,
                    'rows' => 10,
                    'cols' => 62,
                    'class' => 'rte',
                    'autoload_rte' => true,
                    'desc' => $this->l('Enter Your Post Description')
                ),
                array(
                    'type' => 'file',
                    'label' => $this->l('Featured Image:'),
                    'name' => 'image',
                    'display_image' => true,
                    'image' => $image_url ? $image_url : false,
                    'size' => $image_size,
                    'delete_url' => self::$currentIndex . '&' . $this->identifier . '=' . pSQL(Tools::getvalue('id_smart_blog_post')) . '&token=' . $this->token . '&deleteImage=1',
                    'hint' => $this->l('Upload a feature image from your computer.'),
                ),
                array(
                    'type' => 'html',
                    'label' => $this->l('Blog Categories'),
                    'name' => 'id_category_big',
                    'required' => true,
                    'html_content' => $tree->render(),
                    'desc' => $this->l('Select Your Parent Category')
                ),
//                array(
//                    'type' => 'select',
//                    'label' => $this->l('Blog Category'),
//                    'name' => 'id_category',
//                    'options' => array(
//                        'query' => BlogCategory::getCategory(),
//                        'id' => 'id_smart_blog_category',
//                        'name' => 'meta_title'
//                    ),
//                    'desc' => $this->l('Select Your Parent Category')
//                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Meta Keyword'),
                    'name' => 'meta_keyword',
                    'lang' => true,
                    'size' => 60,
                    'required' => false,
                    'desc' => $this->l('Enter Your Post Meta Keyword. Separated by comma(,)')
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Short Description'),
                    'name' => 'short_description',
                    'rows' => 10,
                    'cols' => 62,
                    'lang' => true,
                    'required' => true,
                    'desc' => $this->l('Enter Your Post Short Description')
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Meta Description'),
                    'name' => 'meta_description',
                    'rows' => 10,
                    'cols' => 62,
                    'lang' => true,
                    'required' => false,
                    'desc' => $this->l('Enter Your Post Meta Description')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Link Rewrite'),
                    'name' => 'link_rewrite',
                    'size' => 60,
                    'lang' => true,
                    'required' => false,
                    'desc' => $this->l('Enetr Your Post Slug. Use In SEO Friendly URL')
                ),
                array(
                    'type' => 'tags',
                    'label' => $this->l('Tag'),
                    'name' => 'tags',
                    'size' => 60,
                    'lang' => true,
                    'required' => false,
                    'hint' => array(
                        $this->l('To add "tags" click in the field, write something, and then press "Enter."'),
                        $this->l('Invalid characters:') . ' &lt;&gt;;=#{}'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Comment Status'),
                    'name' => 'comment_status',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                    'desc' => $this->l('You Can Enable or Disable Your Comments')
                ),
                array(
                    'label' => $this->l('Published Date'),
                    'name' => 'created',
                    'title' => $this->l('Published date'),
                    'type' => 'datetime'
                ),
                array(
                    'type' => 'associations',
                    'label' => $this->l('Related Product(s)'),
                    'name' => 'associations',
                    'size' => 60,
                    'lang' => true,
                    'required' => false,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Status'),
                    'name' => 'active',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                ), array(
                    'type' => 'switch',
                    'label' => $this->l('Is Featured?'),
                    'name' => 'is_featured',
                    'required' => false,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                )
            ),
            'gallary' => array(
                'images' => array(
                    'count' => count($images),
                    'max_image_size' => $this->max_image_size / 1024 / 1024,
                    'table' => $this->table,
                    'images' => $images,
                    'id_smart_blog_post' => (int) Tools::getValue('id_smart_blog_post'),
                    'object' => $this->object,
                    'image_uploader' => $image_uploader->render()
                )
            ),
        );

        $this->fields_form['input'][] = array(
            'type' => 'radio',
            'label' => $this->l('Post Format'),
            'name' => 'post_type',
            'required' => true,
            'values' => array(
                array(
                    'id' => 'post_type_default',
                    'value' => '',
                    'label' => '<i class="icon-home"></i> ' . $this->l('None'),
                ),
                array(
                    'id' => 'post_type_gallery',
                    'value' => 'gallery',
                    'label' => '<i class="icon-picture"></i> ' . $this->l('Gallery')
                ),
                array(
                    'id' => 'post_type_audio',
                    'value' => 'audio',
                    'label' => '<i class="icon-music"></i> ' . $this->l('Audio')
                ),
                array(
                    'id' => 'post_type_video',
                    'value' => 'video',
                    'label' => '<i class="icon-film"></i> ' . $this->l('Video')
                ),
                array(
                    'id' => 'post_type_quote',
                    'value' => 'quote',
                    'label' => '<i class="icon-quote-left"></i> ' . $this->l('Quote')
                ),
                array(
                    'id' => 'post_type_link',
                    'value' => 'link',
                    'label' => '<i class="icon-link"></i> ' . $this->l('Link')
                ),
            )
        );


        $this->fields_form['input'][] = array(
            'type' => 'html',
            'label' => $this->l('Post Format Fields'),
            'name' => 'post_format_fields',
            'html_content' => $this->AdminMetaFields(),
            'desc' => $this->l('Set exclusive fields for the post format')
        );
        if (Shop::isFeatureActive()) {
            $this->fields_form['input'][] = array(
                'type' => 'shop',
                'label' => $this->l('Shop association:'),
                'name' => 'checkBoxShopAsso',
            );
        }

        if (!($SmartBlogPost = $this->loadObject(true)))
            return;

        $this->fields_form['submit'] = array(
            'title' => $this->l('Save'),
        );

        $image = ImageManager::thumbnail(_MODULE_SMARTBLOG_DIR_ . $SmartBlogPost->id_smart_blog_post . '.jpg', $this->table . '_' . (int) $SmartBlogPost->id_smart_blog_post . '.' . $this->imageType, 350, $this->imageType, true);

        $this->fields_value = array(
            'image' => $image ? $image : false,
            'size' => $image ? filesize(_MODULE_SMARTBLOG_DIR_ . $SmartBlogPost->id_smart_blog_post . '.jpg') / 1000 : false
        );
        if (Tools::getvalue('id_smart_blog_post') != '' && Tools::getvalue('id_smart_blog_post') != NULL) {
            foreach (Language::getLanguages(false) as $lang) {
                $this->fields_value['tags'][(int) $lang['id_lang']] = SmartBlogPost::getProductTagsBylang((int) Tools::getvalue('id_smart_blog_post'), (int) $lang['id_lang']);
            }
        }

        $this->tpl_form_vars['PS_ALLOW_ACCENTED_CHARS_URL'] = (int) Configuration::get('PS_ALLOW_ACCENTED_CHARS_URL');


        // related product using Accessories block

        $accessories = SmartBlogPost::getAccessoriesLight($this->context->language->id, $SmartBlogPost->id_smart_blog_post);

        if ($post_accessories = Tools::getValue('inputAccessories')) {
            $post_accessories_tab = explode('-', $post_accessories);
            foreach ($post_accessories_tab as $accessory_id)
                if (!$this->haveThisAccessory($accessory_id, $accessories) && $accessory = Product::getAccessoryById($accessory_id))
                    $accessories[] = $accessory;
        }
        $this->tpl_form_vars['accessories'] = $accessories;




        /* image gallary */
        $this->tpl_form_vars['images'] = $images;
        $this->tpl_form_vars['image_uploader'] = $image_uploader->render();


        $this->tpl_form_vars['max_image_size'] = $this->max_image_size / 1024 / 1024;
        $this->tpl_form_vars['languages'] = $languages;
        $this->tpl_form_vars['iso_lang'] = $languages[0]['iso_code'];
        $this->tpl_form_vars['imageType'] = 'home-small';
        $this->tpl_form_vars['id_smart_blog_post'] = (int) Tools::getValue('id_smart_blog_post');
        $this->tpl_form_vars['default_language'] = (int) Configuration::get('PS_LANG_DEFAULT');
        $this->tpl_form_vars['table'] = $this->table;
        $this->tpl_form_vars['token'] = Tools::getAdminTokenLite('AdminBlogPost');
        $this->tpl_form_vars['gallary_path'] = _MODULE_DIR_ . 'smartblog/gallary/';

        // $this->tpl_form_vars['token_book'] = Tools::getAdminTokenLite('AdminBook');

        return parent::renderForm();
    }

    private function AdminMetaFields() {
        $context = Context::getContext();
        $context->smarty->assign(
                array(
                    'id_smart_blog_post' => (int) Tools::getValue('id_smart_blog_post'),
                    'meta_fields' => $this->post_format_fields
                )
        );
        return $context->smarty->fetch(dirname(__FILE__) . '/../../views/templates/admin/meta-fields.tpl');
    }

    public function initToolbar() { 
       $imgsrc =  __PS_BASE_URI__ . 'modules/smartblog/images/ad.png';
         $this->context->smarty->assign(array(
            'src' =>$imgsrc,
             'height'=>'',
             'width'=>'',
        ));
        parent::initToolbar();
    }

    public function processForceDeleteImage() {
        $blog_post = $this->loadObject(true);

        if (Validate::isLoadedObject($blog_post)) {

            $this->deleteImage($blog_post->id_smart_blog_post);
        }
    }

    public function deleteImage($id_smart_blog_post = 1) {

        if (!$id_smart_blog_post)
            return false;


        // Delete base image
        if (file_exists(_MODULE_SMARTBLOG_DIR_ . '/' . $id_smart_blog_post . '.jpg'))
            unlink($this->image_dir . '/' . $id_smart_blog_post . '.jpg');
        else
            return false;

        // now we need to delete the image type of post

        $files_to_delete = array();

        // Delete auto-generated images
        $image_types = BlogImageType::GetImageAllType('post');
        foreach ($image_types as $image_type)
            $files_to_delete[] = $this->image_dir . '/' . $id_smart_blog_post . '-' . $image_type['type_name'] . '.jpg';

        // Delete tmp images
        $files_to_delete[] = _PS_TMP_IMG_DIR_ . 'smart_blog_post_' . $id_smart_blog_post . '.jpg';
        $files_to_delete[] = _PS_TMP_IMG_DIR_ . 'smart_blog_post_mini_' . $id_smart_blog_post . '.jpg';

        foreach ($files_to_delete as $file)
            if (file_exists($file) && !@unlink($file))
                return false;

        return true;
    }

    public function updateTags($languages, $post) {
        $tag_success = true;
        if (!SmartBlogPost::deleteTagsForProduct((int) $post->id))
            $this->errors[] = Tools::displayError('An error occurred while attempting to delete previous tags.');
        foreach ($languages as $language)
            if ($value = pSQL(Tools::getValue('tags_' . $language['id_lang'])))
                $tag_success &= SmartBlogPost::addTags($language['id_lang'], (int) $post->id, $value);

        if (!$tag_success)
            $this->errors[] = Tools::displayError('An error occurred while adding tags.');
        return $tag_success;
    }

    /**
     * Update product accessories
     *
     * @param object $product Product
     */
    public function updateAccessories($id_smart_blog_post) {
        $SmartBlogPost = new SmartBlogPost($id_smart_blog_post);

        $SmartBlogPost->deleteAccessories($id_smart_blog_post);

        if ($accessories = Tools::getValue('inputAccessories')) {
            $accessories_id = array_unique(explode('-', $accessories));
            if (count($accessories_id)) {
                array_pop($accessories_id);
                $SmartBlogPost->changeAccessories($accessories_id);
            }
        }
    }

    public function ajaxProcessDeleteGallaryImage() {
        $this->display = 'content';
        $res = true;

        if (!($id_smart_blog_gallary_images = (int) Tools::getValue('id_smart_blog_gallary_images')))
            $this->jsonError(Tools::displayError('An error occurred (the image not exists).'));


        /* Delete product image */

        $image = new SmartBlogGallaryImage($id_smart_blog_gallary_images);
        $this->content['id'] = $image->id;

        //print_r($image);
        $res &= $image->delete();


        if (file_exists(_PS_TMP_IMG_DIR_ . 'smart_blog_post_' . $image->id_smart_blog_gallary_images . '.jpg'))
            $res &= @unlink(_PS_TMP_IMG_DIR_ . 'smart_blog_post_' . $image->id_smart_blog_gallary_images . '.jpg');
        if (file_exists(_PS_TMP_IMG_DIR_ . 'smart_blog_post_mini_' . $image->id_smart_blog_gallary_images . '_' . $this->context->shop->id . '.jpg'))
            $res &= @unlink(_PS_TMP_IMG_DIR_ . 'smart_blog_post_mini_' . $image->id_smart_blog_gallary_images . '_' . $this->context->shop->id . '.jpg');

        //{"status" : "ok","confirmations" : ["The image was successfully deleted."],"informations" : [],"error" : [],"warnings" : [],"content" : {"id":62}}
        if ($res)
            die(Tools::jsonEncode(
                            array('status' => "ok",
                                "confirmations" => "The image was successfully deleted.",
                                "informations" => "",
                                "error" => "",
                                "warnings" => "",
                                "content" => array('id' => $id_smart_blog_gallary_images)
                            )
                    )
            );
        // $this->jsonConfirmation($this->_conf[7]);
        else
            $this->jsonError(Tools::displayError('An error occurred while attempting to delete the product image.'));
    }

    public function ajaxProcessUpdateGallaryImagePosition() {
        $res = false;
        if ($json = Tools::getValue('json')) {
            $res = true;
            $json = Tools::stripslashes(pSQL($json));
            $images = Tools::jsonDecode($json, true);
            foreach ($images as $id => $position) {
                $img = new SmartBlogGallaryImage((int) $id);
                $img->position = (int) $position;
                $res &= $img->update();
            }
        }
        if ($res)
            $this->jsonConfirmation($this->_conf[25]);
        else
            $this->jsonError(Tools::displayError('An error occurred while attempting to move this picture.'));
    }

}
