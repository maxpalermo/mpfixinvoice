<?php
/**
* 2007-2018 PrestaShop
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
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mpfixinvoice extends Module
{
    protected $config_form = false;
    protected $adminClassName = 'AdminMpFixInvoice';

    public function __construct()
    {
        $this->name = 'mpfixinvoice';
        $this->tab = 'administration';
        $this->version = '1.0.5';
        $this->author = 'Digital Solutions®';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Fix invoice data and number');
        $this->description = $this->l('This module reindex invoices to respect correct flow');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayInvoice') &&
            $this->registerHook('displayPDFInvoice') && 
            $this->installTab('adminParentOrders', $this->adminClassName, $this->l('Invoices list'));
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallTab($this->adminClassName);
    }
    
    public function installTab($parent, $class_name, $name, $active = 1)
    {
        // Create new admin tab
        $tab = new Tab();
        
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->name      = array();
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        
        $tab->class_name = $class_name;
        $tab->module     = $this->name;
        $tab->active     = $active;
        
        if (!$tab->add()) {
            $this->_errors[] = $this->l('Error during Tab install.');
            return false;
        }
        return true;
    }
    
    public function uninstallTab($class_name)
    {
        $id_tab = (int)Tab::getIdFromClassName($class_name);
        if ($id_tab) {
            $tab = new Tab((int)$id_tab);
            return $tab->delete();
        }
    }
    
    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
            $this->context->controller->addJqueryPlugin('growl');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }
    
    public function hookDisplayAdminOrder()
    {
        $link = new LinkCore();
        $token = Tools::encrypt($this->name);
        $ajax_url_module = $link->getModuleLink($this->name,'ajaxDispatcher') . '.php';
        $ajax_url = str_replace('module/', 'modules/', $ajax_url_module);
        $id_order = (int)Tools::getValue('id_order');
        $smarty = Context::getContext()->smarty;
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql->select('number')
                ->select('date_add')
                ->from('order_invoice')
                ->where('id_order = ' . (int)$id_order);
        $invoice = $db->getRow($sql);
        
        $smarty->assign(array(
            'id_order' => $id_order,
            'invoice_number' => $invoice['number'],
            'invoice_date' => $invoice['date_add'],
            'ajax_url' => $ajax_url,
            'token' => $token,
        ));
        return $smarty->fetch($this->local_path . 'views/templates/front/invoice.tpl');
    }
    
    public function hookDisplayInvoice()
    {
        PrestaShopLoggerCore::addLog('hookDisplayInvoice');
        PrestaShopLoggerCore::addLog(print_r(Tools::getAllValues(), 1));
    }

    public function hookDisplayPDFInvoice()
    {
        $id_order = (int)Tools::getValue('id_order');
        if ($id_order == 0) {
            PrestaShopLoggerCore::addLog('hookDisplayPDFInvoice: Invalid order id');
            return false;
        }
        $db = Db::getInstance();
        $date = date('Y-m-d h:n:s');
        $sql_invoice = "UPDATE `" . _DB_PREFIX_ . "order_invoice` SET `date_add` = '$date' where number = '0'";
        $sql_order = "UPDATE `" . _DB_PREFIX_ .  "orders` SET `invoice_date` = '$date' where invoice_number = '0'";
        $res_invoice = $db->execute($sql_invoice);
        $res_order = $db->execute($sql_order);
        
        PrestaShopLoggerCore::addLog($sql_invoice . ": " . (int)$res_invoice);
        PrestaShopLoggerCore::addLog($sql_order . ": " . (int)$res_order);
    }
    
    public function ajaxProcessFixInvoice()
    {
        $id_order = (int)Tools::getValue('id_order', 0);
        $invoice_number = (int)Tools::getValue('invoice_number', 0);
        $invoice_date = Tools::getValue('invoice_date', '');
        $errors = array();
        
        if ($id_order && $invoice_number && $invoice_date) {
            $db = Db::getInstance();
            $upd_order = 'update ' . _DB_PREFIX_ . 'orders set invoice_date = \'' . pSQL($invoice_date) 
                    . '\' where id_order = ' . (int)$id_order;
            $upd_invoice = 'update ' . _DB_PREFIX_ . 'order_invoice set date_add = \'' . pSQL($invoice_date) . '\','
                    . 'number = \'' . pSQL($invoice_number) . '\' '
                    . 'where id_order = ' . (int)$id_order;
            
            //print $upd_order . '<br>' . $upd_invoice;
            
            $res_order = $db->execute($upd_order);
            if (!$res_order) {
                $errors[] = $db->getMsgError();
            }
            
            $res_invoice = $db->execute($upd_invoice);
            if (!$res_invoice) {
                $errors[] = $db->getMsgError();
            } 
            
            if ($errors) {
                print implode('<br>', $errors);
            }
        } else {
            print $this->l('Wrong input parameters.');
        }
        exit();
    }
    
    public function ajaxProcessPrintMessageResult($message, $error = false, $title = '')
    {
        if (!$title) {
            $title = $this->l('Message');
        }
        print Tools::jsonEncode(
            array(
                'error' => $error,
                'message' => $message,
                'title' => $title,
            )
        );
        exit();
    }
    
    public function ajaxProcessGetDocumentNumber()
    {
        $document = Tools::getValue('id_document', '');
        $id_document = explode('_', $document);
        /** Check validity **/
        if (count($id_document)!=2) {
            $this->ajaxProcessPrintMessageResult($this->l('Document type not valid.'), true);
        }
        if(!in_array($id_document[0], array('invoice','delivery'))) {
            $this->ajaxProcessPrintMessageResult($this->l('Document type unknown.'), true);
        }
        if(!$id_document[1] || !ValidateCore::isInt($id_document[1])) {
            $this->ajaxProcessPrintMessageResult($this->l('Id document not valid.'), true);
        }
        if ($id_document[0]=='invoice') {
            $order_invoice = new OrderInvoiceCore($id_document[1]);
            $number = $order_invoice->number;
            $total = $order_invoice->getTotalPaid();
        }
        if ($id_document[0]=='delivery') {
            $order_delivery = new OrderInvoiceCore($id_document[1]);
            $number = $order_delivery->delivery_number;
            $total = $order_delivery->getTotalPaid();
        }
        print Tools::jsonEncode(
            array(
                'error' => false,
                'number' => $number,
                'total' => Tools::displayPrice($total),
            )
        );
        exit();
    }
    
    public function ajaxProcessEditSelectedDocument()
    {
        $input_id_document = Tools::getValue('id_document', '');
        $number_document = (int)Tools::getValue('number_document', 0);
        $date_document= Tools::getValue('date_document');
        $id_document = explode('_', $input_id_document);
        /** Check validity **/
        if (count($id_document)!=2) {
            $this->ajaxProcessPrintMessageResult($this->l('Document type not valid.'), true);
        }
        if(!in_array($id_document[0], array('invoice','delivery'))) {
            $this->ajaxProcessPrintMessageResult($this->l('Document type unknown.'), true);
        }
        if(!$id_document[1] || !ValidateCore::isInt($id_document[1])) {
            $this->ajaxProcessPrintMessageResult($this->l('Id document not valid.'), true);
        }
        if(!$number_document || !ValidateCore::isInt($number_document)) {
            $this->ajaxProcessPrintMessageResult($this->l('Number document not valid.'), true);
        }
        if(!$date_document || !ValidateCore::isDate($date_document)) {
            $this->ajaxProcessPrintMessageResult($this->l('Date document not valid.'), true);
        }
        /** Process document **/
        if ($id_document[0]=='invoice') {
            $invoice = new OrderInvoiceCore($id_document[1]);
            $invoice->date_add = $date_document;
            $invoice->number = $number_document;
            $result = $invoice->update();
        } else {
            $delivery = new OrderInvoiceCore($id_document[1]);
            $delivery->delivery_date = $date_document;
            $delivery->delivery_number = $number_document;
            $result = $delivery->update();
        }
        if ($result) {
            $this->ajaxProcessPrintMessageResult($this->l('Operation Done.'), false);
        } else {
            $this->ajaxProcessPrintMessageResult(Db::getInstance()->getMsgError(), true);
        }
        exit();
    }
    
    public function ajaxProcessDeleteSelectedDocument()
    {
        $input_id_document = Tools::getValue('id_document', '');
        $id_document = explode('_', $input_id_document);
        /** Check validity **/
        if (count($id_document)!=2) {
            $this->ajaxProcessPrintMessageResult($this->l('Document type not valid.'), true);
        }
        if(!in_array($id_document[0], array('invoice','delivery'))) {
            $this->ajaxProcessPrintMessageResult($this->l('Document type unknown.'), true);
        }
        if(!$id_document[1] || !ValidateCore::isInt($id_document[1])) {
            $this->ajaxProcessPrintMessageResult($this->l('Id document not valid.'), true);
        }
        /** Process document **/
        if ($id_document[0]=='invoice') {
            $invoice = new OrderInvoiceCore($id_document[1]);
            $invoice->date_add = '1970-01-01 00:00:00';
            $invoice->number = 0;
            $result = $invoice->update(true);
            if ($result) {
                $order = new OrderCore($invoice->id_order);
                $order->invoice_number = 0;
                $order->update();
            }
        } else {
            $delivery = new OrderInvoiceCore($id_document[1]);
            $delivery->delivery_date = '1970-01-01 00:00:00';
            $delivery->delivery_number = 0;
            $result = $delivery->update();
            if ($result) {
                $order = new OrderCore($invoice->id_order);
                $order->delivery_number = 0;
                $order->update();
            }
        }
        if ($result) {
            $this->ajaxProcessPrintMessageResult($this->l('Operation Done.'), false);
        } else {
            $this->ajaxProcessPrintMessageResult(Db::getInstance()->getMsgError(), true);
        }
        exit();
    }
    
    public function ajaxProcessDelPayment()
    {
        $id_order = (int)Tools::getValue('id_order', 0);
        $order = new OrderCore($id_order);
        $order_invoices = $order->getInvoicesCollection();
        if (count($order_invoices) > 1) {
            print $this->l('There are more than one invoice for this order. unable to fix payments');
            exit();
        }
        /**
         * @var OrderInvoiceCore $order_invoice
         */
        $order_invoice = $order_invoices[0];
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql->select('id_order_payment')
            ->from('order_payment')
            ->where('order_reference='.psql($order->reference))
            ->orderBy('id_order_payment ASC');
        $result = $db->executeS($sql);
        if (count($result)>1) {
            $payment = array_shift($result);
            foreach ($result as $row) {
                $db->delete(
                    'order_payment', 
                    'id_order_payment='.(int)$row['id_order_payment']
                );
            }
            $db->delete(
                'order_invoice_payment',
                'id_order='.(int)$order->id
            );
            $db->insert(
                'order_invoice_payment',
                array(
                    'id_order_invoice' => (int)$order_invoice->id,
                    'id_order_payment' => (int)$payment['id_order_payment'],
                    'id_order' => (int)$order->id,
                )
            );
        } else {
            print $this->l('There is only one payment. Unable to fix.');
            exit();
        }
        
        print $this->l('Payment for order ' . $order->reference . ' fixed.');
        exit();
    }
}
