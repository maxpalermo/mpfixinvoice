<?php
/**
 * 2017 mpSOFT
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
 *  @author    mpSOFT <info@mpsoft.it>
 *  @copyright 2017 mpSOFT Massimiliano Palermo
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of mpSOFT
 */

ini_set('max_execution_time', 300); //300 seconds = 5 minutes
ini_set('post_max_size', '64M');
ini_set('upload_max_filesize', '64M');

class AdminMpFixInvoiceController extends ModuleAdminController
{
    public $id_lang;
    public $link;
    public $className;
    protected $messages;
    protected $local_path;
    protected $adminClassName;
    
    public function __construct()
    {
        $this->id_lang = (int)ContextCore::getContext()->language->id;
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->className = 'AdminMpFixInvoice';
        $this->token = Tools::getValue('token', Tools::getAdminTokenLite($this->className));
        $this->messages = array();
        $this->local_path = dirname(__FILE__) . '/../../';
        parent::__construct();
        $this->adminClassName = $this->className;
    }

    public function initContent()
    {           
        $this->smarty = Context::getContext()->smarty;
        $this->link = new LinkCore();
        $adminLink =  $this->link->getAdminLink($this->className, false);
        $token = Tools::getAdminTokenLite($this->className);
        $smartyFetch = $this->module->getPath() . 'views/templates/admin/configuration.tpl';
        $this->errors = array();
        $this->messages = array();
        $output = array();
        
        /**
         * If values have been submitted in the form, process.
         */
        $output = array();
        if (((bool)Tools::isSubmit('submitSearch')) == true) {
            $res = $this->postProcess();
            if (!$res) {
                $this->errors[] = $this->l('Error during Tag import.');
            } else {
                $output[] = $this->module->displayConfirmation($this->l('Operation done.'));
            }
        }
        $this->smarty->assign('module_dir', $this->module->getPath());
        $this->smarty->assign(array(
            'token' => $token,
            'form_link' => $adminLink,
        ));
        $output[] = $this->smarty->fetch($this->module->getPath().'views/templates/admin/configuration.tpl');
        $form =  implode('<br/>',$output);
        
        $this->content = $this->displayMessages() . $form;
        parent::initContent();
    }
    
    public function setMedia()
    {
        parent::setMedia();
        $this->addJqueryUI('ui.dialog');
        $this->addJqueryUI('ui.progressbar');
        $this->addJqueryUI('ui.draggable');
        $this->addJqueryUI('ui.effect');
        $this->addJqueryUI('ui.effect-slide');
        $this->addJqueryUI('ui.effect-fold');
        PrestaShopLoggerCore::addLog($this->module->name .  ': setMedia');
    }
    
    private function displayMessages()
    {
        $output = '';
        foreach ($this->messages as $message) {
            $output.= $this->module->displayConfirmation($message);
        }
        return $output;
    }
    
    public function postProcess()
    {
        $this->counter = 0;
        if (Tools::getValue('input_text_row_data',array())) {
            $this->importRow(Tools::getValue('input_text_row_data',array()));
        } else {
            $attachment = Tools::fileAttachment('input_file_upload');
            $content = $attachment['content'];
            
            $rows = explode(PHP_EOL, $content);
            $array = array();
            foreach($rows as $row) {
                $array[] = str_getcsv($row, ";");
            }
            
            if (count($array)>1) {
                /**
                 * CHECK RESET VALUES
                 */
                $reset = Tools::getValue("input_switch_reset_value",0);
                if ($reset) {
                    $this->resetTags();
                }
                
                $titles = $array[0];
                if (count($titles>1)) {
                    if($titles[0] == 'id_categories') {
                        $tag_array = $this->importTags($array);
                        return $this->ImportByCategories($array, $tag_array);
                    }
                } else {
                    $this->errors[] = $this->l('Bad CSV columns format.');
                    return false;
                }
            } else {
                $this->errors[] = $this->l('Bad CSV file format.');
                return false;
            }
        }
        return true;
    }
    
    private function importRow($rows)
    {
        /**
         * ROW FORMAT <id_categories>;<id_tags>
         */
        foreach ($rows as $row) {
            $columns=explode(';',$row);
            if (count($columns)!=2) {
                if(count($columns)!=0) {
                    $this->errors[] = $this->l('Error: Bad input row format.');
                }
            }
            if (count($columns)==2) {
                /**
                 * CHECK RESET VALUES
                 */
                $reset = Tools::getValue("input_switch_reset_value",0);
                if ($reset) {
                    $this->resetTags();
                }
                $array[] = str_getcsv($row, ";");
                $tag_array = $this->importTags($array);
                $res = $this->ImportByCategories($array, $tag_array);
            }
        }
    }
    
    private function resetTags()
    {
        $db = db::getInstance();
        $res1 = $db->delete('product_tag');
        $res2 = $db->delete('tag');
        if (!$res1) {
            $this->errors[] = $this->l('Error during reset product_tags.');
        }
        if (!$res2) {
            $this->errors[] = $this->l('Error during reset tags.'); 
        }
    }
    
    private function importTags($array)
    {
        $id_lang = Context::getContext()->language->id;
        $db = Db::getInstance();
        /**
         * INSERT NEW TAGS FROM CSV
         */
        foreach ($array as $row)
        {
            if (count($row)>1) {
                /**
                 * SPLIT ROW IN AN ARRAY OF TAGS
                 */
                $tagrow = explode(',',$row[1]);
                
                foreach ($tagrow as $tagname)
                {
                    $tag = Tools::strtolower($tagname);
                    $sql = 'select count(*) from ' 
                        . _DB_PREFIX_ . 'tag' 
                        . ' where id_lang = ' . (int)$id_lang
                        . ' and name = \'' . pSQL($tag) . '\'';
                    
                    if ((int)$db->getValue($sql) == 0) {
                        /**
                         * INSERT NEW TAG IN ARCHIVE
                         */
                        $res = $db->insert(
                            'tag',
                            array(
                                'id_lang' => (int)$id_lang,
                                'name' => pSQL(Tools::strtolower($tag)),
                            ),
                            false,
                            false,
                            Db::INSERT_IGNORE
                            );
                        if (!$res) {
                            $this->errors[] = sprintf($this->l('Error during tag insertion. Tag: %s'), $key);
                        } else {
                            $this->counter++;
                        }
                    }
                }
            }
        }
        $this->messages[] = sprintf(
            $this->l('Inserted %d new tags.'),
            $this->counter
        );
        
        /**
         * RELOAD TAG LIST
         */
        return $this->getTagList();
    }
    
    private function getTagList()
    {
        $id_lang = Context::getContext()->language->id;
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        /**
         * GET ALL TAGS IN ARCHIVE
         */
        $sql->select('id_tag')
        ->select('name')
        ->from('tag')
        ->where('id_lang = ' . (int)$id_lang)
        ->orderBy('name');
        
        $res = $db->executeS($sql);
        if (!$res) {
            $this->errors[] = $this->l('Error reading Tags from archive.');
            return false;
        }
        /**
         * CREATE TAG LIST ARRAY
         */
        foreach ($res as $row) {
            $tags[$row['name']] = $row['id_tag'];
        }
        return $tags;
    }
    
    private function importByCategories($array, $tag_array)
    {
        $db = Db::getInstance();
        $id_lang = Context::getContext()->language->id;
        if (count($array)>2) {
            array_shift($array);
        }
        $rows = $array;
        
        foreach ($rows as $row) {
            /**
             * GET CATEGORIES IN AN ARRAY
             */
            if ($row[0]) {
                $categories = explode(',', $row[0]);
                
                /**
                 * GET TAG LIST INTO AN ARRAY
                 */
                $tags = explode(',', $row[1]);
                /**
                 * GET PRODUCTS FROM CATEGORIES ARRAY INTO AN ARRAY
                 */
                $products = array_unique($this->getProductsByCategories($categories));
                
                foreach ($products as $id_product) {
                    foreach($tags as $tag_name) {
                        $id_tag = (int)$tag_array[$tag_name];
                        if ($id_tag) {
                            $res = $db->insert(
                                'product_tag',
                                array(
                                    'id_product' => (int)$id_product,
                                    'id_tag' => (int)$id_tag
                                ),
                                false,
                                false,
                                Db::INSERT_IGNORE
                                );
                            
                            if(!$res) {
                                $this->errors[] = sprintf(
                                    $this->l('Error during product Tag insertion. Product id: %d, Tag id: %d'),
                                    $id_product,
                                    $id_tag
                                    );
                            }
                        } else {
                            $this->errors[] = sprintf(
                                $this->l('Error during tag update. Tag %s on product %s'),
                                $tag_name,
                                $id_product
                            );
                        }
                        
                            
                    }
                }
            }
        }
        return true;
    }
    
    private function getProductsByCategories($categories)
    {
        $id_categories = array();
        foreach ($categories as $cat) {
            if ((int)$cat) {
                $id_categories[] = (int)$cat;
            }
        }
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_product')
        ->from('category_product')
        ->where('id_category in (' . pSQL(implode(',',$id_categories)) . ')');
        $res = $db->executeS($sql);
        if(!$res) {
            $this->errors[] = sprintf($this->l('Error during read database: %s'), $db->getMsgError());
            return false;
        }
        $output = array();
        foreach($res as $row) {
            $output[] = $row['id_product'];
        }
        return $output;
    }
    
    protected function processBulkDelete()
    {
        
    }
    
    protected function processBulkImport()
    {
        if (Tools::isSubmit('submitBulkupload')) {
            $rows = Tools::getValue('Box');
            foreach ($rows as $row) {
                $id_movement = (int)$row;
                $classMovement = new ClassMovement($this);
                $classMovement->getMovement($id_movement);
                $res = $classMovement->updateStock();
                
                if (!$res) {
                    $this->errors[] = sprintf(
                        $this->l('Error during stock update for product %s'),
                        $classMovement->reference
                    );
                } else {
                    $this->messages[] = sprintf(
                        $this->l('Stock available for product %s updated'),
                        $classMovement->reference
                    );
                }
            }
        }
    }
    
    public static function getIdProductFromReference($reference)
    {
        $db = Db::getInstance();
        $query = new DbQueryCore();
        $query->select('id_product')
                ->from('product')
                ->where('reference = \'' . pSQL($reference) . '\'');
        return $db->getValue($query);
    }
}
