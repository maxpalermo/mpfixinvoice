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
            $this->registerHook('displayAdminOrderContentOrder') && 
            $this->registerHook('displayAdminOrderTabOrder') && 
            $this->registerHook('displayAdminOrdersListBefore') && 
            $this->registerHook('displayAdminOrdersView') &&
            $this->registerHook('displayAdminOrder') &&
            $this->installTab('adminParentOrders', $this->adminClassName, $this->l('Invoices list'));
    }

    public function uninstall()
    {
        try {
            return parent::uninstall() && $this->uninstallTab($this->adminClassName);
        } catch (Exception $ex) {
            return true;
        }
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
     * Return the admin class name
     * @return string Admin class name
     */
    public function getAdminClassName()
    {
        return $this->adminClassName;
    }
    
    /**
     * Return the Admin Template Path
     * @return string The admin template path
     */
    public function getAdminTemplatePath()
    {
        return $this->getPath().'views/templates/admin/';
    }
    
    /**
     * Get the Id of current language
     * @return int id language
     */
    public function getIdLang()
    {
        return (int)$this->id_lang;
    }
    
    /**
     * Get the Id of current shop
     * @return int id shop
     */
    public function getIdShop()
    {
        return (int)$this->id_shop;
    }
    
    /**
     * Get The URL path of this module
     * @return string The URL of this module
     */
    public function getUrl()
    {
        return $this->_path;
    }
    
    /**
     * Return the physical path of this module
     * @return string The path of this module
     */
    public function getPath()
    {
        return $this->local_path;
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
        return $smarty->fetch($this->local_path . 'views/templates/admin/invoice.tpl');
    }

    public function hookDisplayAdminOrderContentOrder()
    {
        //return "<h1>".__FUNCTION__."</h1>";
    }

    public function hookDisplayAdminOrderTabOrder()
    {
        //return "<h1>".__FUNCTION__."</h1>";
    }

    public function hookDisplayAdminOrdersListBefore()
    {
        //return "<h1>".__FUNCTION__."</h1>";
    }

    public function hookDisplayAdminOrdersView()
    {
        //return "<h1>".__FUNCTION__."</h1>";
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
    
    /**
     * Print a message error in json format and exit
     * @param string $message Message to display
     * @param bool $error If true, print an error message
     * @param string $title Title of message
     */
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
        $id_order = 0;
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
            $id_order = $invoice->id_order;
            $result = $invoice->update();
        } else {
            $delivery = new OrderInvoiceCore($id_document[1]);
            $delivery->delivery_date = $date_document;
            $delivery->delivery_number = $number_document;
            $id_order = $delivery->id_order;
            $result = $delivery->update();
        }
        if ($result) {
            $order = new OrderCore($id_order);
            if ($result && $id_document[0]=='invoice') {
                $order->invoice_date = $date_document;
                $order->update();
            } elseif ($result && $id_document[0]=='delivery') {
                $order->delivery_date = $date_document;
                $order->update();
            }
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
        $id_order = (int)Tools::getValue('id_order');
        
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
            
            $db = Db::getInstance();
            $sql = new DbQueryCore();
            $sql->select('invoice_number')
                ->from('orders')
                ->where('id_order='.(int)$id_order);
            $id_invoice = (int)$db->getValue($sql);
            PrestaShopLoggerCore::addLog('Delete invoice: '. $id_invoice);
            
            $invoice = new OrderInvoiceCore($id_invoice);
            $invoice->date_add = '1970-01-01 00:00:00';
            $invoice->number = 0;
            $result = $invoice->update(true);
            if ($result) {
                $order = new OrderCore($invoice->id_order);
                $order->invoice_number = 0;
                $order->invoice_date = '1970-01-01 00:00:00';
                $order->update();
            }
        } else {
            $db = Db::getInstance();
            $sql = new DbQueryCore();
            $sql->select('id_order_invoice')
                ->from('order_invoice')
                ->where('id_order='.(int)$id_order);
            $id_delivery = (int)$db->getValue($sql);
            PrestaShopLoggerCore::addLog('Delete delivery: '. $id_delivery);
            
            $delivery = new OrderInvoiceCore($id_delivery);
            $delivery->delivery_date = '1970-01-01 00:00:00';
            $delivery->delivery_number = 0;
            $result = $delivery->update();
            if ($result) {
                $order = new OrderCore($delivery->id_order);
                $order->delivery_number = 0;
                $order->delivery_date = '1970-01-01 00:00:00';
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
    
    public function ajaxProcessAddNewPayment()
    {
        $id_order = (int)Tools::getValue('id_order', 0);
        $amount = (float)Tools::getvalue('amount_payment', 0);
        $date = Tools::getValue('date_payment', '');
        $transaction_id = Tools::getValue('transaction_id', '');
        $payment_method = Tools::getValue('payment_method', '');
        
        if (!$id_order || !ValidateCore::isInt($id_order)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Id order not valid.'),
                true,
                $this->l('Add new payment')
            );
        }
        if (!$date || !ValidateCore::isDate($date)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Date not valid.'),
                true,
                $this->l('Add new payment')
            );
        }
        if (!$amount || !ValidateCore::isFloat($amount)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Amount not valid.'),
                true,
                $this->l('Add new payment')
            );
        }
        if (!$payment_method) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Payment method not valid.'),
                true,
                $this->l('Add new payment')
            );
        }
        
        $order = new OrderCore($id_order);
        $payment = new OrderPaymentCore();
        $payment->amount = $amount;
        $payment->conversion_rate = $order->conversion_rate;
        $payment->date_add = $date;
        $payment->id_currency = $order->id_currency;
        $payment->order_reference = $order->reference;
        $payment->payment_method = $payment_method;
        $payment->transaction_id = $transaction_id;
        try {
            $result = $payment->add();
            if ($result) {
                $this->ajaxProcessPrintMessageResult(
                    $this->l('Operation done.'),
                    false,
                    $this->l('Add new payment')
                );
            } else {
                $this->ajaxProcessPrintMessageResult(
                    $this->l('Error adding payment: ') . Db::getInstance()->getMsgError(),
                    true,
                    $this->l('Add new payment')
                );
            }
        } catch (Exception $ex) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Error adding payment: ') . $ex->getMessage(),
                true,
                $this->l('Add new payment')
            );
        }
    }
    
    public function ajaxProcessDeleteSelectedPayment()
    {
        $id_order = (int)Tools::getValue('id_order', 0);
        $date_payment = $this->unixDate(Tools::getValue('date_payment', ''));
        if (!$id_order || !ValidateCore::isInt($id_order)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Id order not valid.'),
                true,
                $this->l('Add new payment')
            );
        }
        if (!$date_payment || !ValidateCore::isDate($date_payment)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Date not valid: ') . $date_payment,
                true,
                $this->l('Add new payment')
            );
        }
        $order = new OrderCore($id_order);
        $id_order_payment = (int)$this->getIdOrderPaymentFromDate($order->reference, $date_payment);
        if (!$id_order_payment || !ValidateCore::isInt($id_order_payment)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Cannot find order payment.'),
                true,
                $this->l('Delete payment')
            );
        }
        $result = $this->deleteOrderPayment($id_order_payment);
        if ($result) {
            $this->deleteOrderPaymentInvoice($id_order_payment);
            $this->ajaxProcessPrintMessageResult(
                $this->l('Operation done.'),
                false,
                $this->l('Delete payment')
            );
        } else {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Error delete payment: ') . $db->getMsgError(),
                true,
                $this->l('Delete payment')
            );
        }
    }
    
    public function ajaxProcessDefaultSelectedPayment()
    {
        $id_order = (int)Tools::getValue('id_order', 0);
        $date_payment = $this->unixDate(Tools::getValue('date_payment', ''));
        $id_order_invoices = Tools::getValue('id_order_invoice', array());
        if (!$id_order || !ValidateCore::isInt($id_order)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Id order not valid.'),
                true,
                $this->l('Set Default payment')
            );
        }
        if (!$date_payment || !ValidateCore::isDate($date_payment)) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Date not valid: ') . $date_payment,
                true,
                $this->l('Set Default Payment')
            );
        }
        if (empty($id_order_invoices)) {
            $id_order_invoices[] = $this->getIdOrderInvoiceFromOrder($id_order);
        }
        if ($id_order_invoices[0] == 0) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Cannot find invoice for this order.'),
                true,
                $this->l('Set Default Payment')
            );
        }
        $order = new OrderCore($id_order);
        $id_order_payment = $this->getIdOrderPaymentFromDate($order->reference, $date_payment);
        $id_order_invoice = is_array($id_order_invoices)?$id_order_invoices[0]:0;
        $result = $this->setDefaultOrderInvoicePayment($id_order, $id_order_payment, $id_order_invoice);
        if ($result) {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Operation done.'),
                false,
                $this->l('Set Default payment')
            );
        } else {
            $this->ajaxProcessPrintMessageResult(
                $this->l('Error setting default payment: ') . Db::getInstance()->getMsgError(),
                true,
                $this->l('Set Default payment')
            );
        }
    }
    
    public function getIdOrderInvoiceFromOrder($id_order)
    {
        $db=Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_invoice')
            ->from('order_invoice')
            ->where('id_order='.(int)$id_order);
        return (int)$db->getValue($sql);
    }
    
    public function setDefaultOrderInvoicePayment($id_order, $id_order_payment, $id_order_invoice)
    {
        $db = Db::getInstance();
        $db->delete(
            'order_invoice_payment',
            'id_order='.(int)$id_order
        );
        return $db->insert(
            'order_invoice_payment',
            array(
                'id_order_invoice' => $id_order_invoice,
                'id_order' => $id_order,
                'id_order_payment' => $id_order_payment,
            )
        );
    }
    
    /**
     * Get id order payment
     * @param string $reference Order reference
     * @param date $date Unix format date yyyy-mm-dd hh:mm:ss
     * @return int id_order_payment
     */
    public function getIdOrderPaymentFromDate($reference, $date)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_payment')
            ->from('order_payment')
            ->where('order_reference=\''.pSQL($reference).'\'')
            ->where('date_add=\''.pSQL($date).'\'');
        //PrestaShopLoggerCore::addLog('find payment:'.$sql->build());
        $id_order_payment = (int)$db->getValue($sql);
        return $id_order_payment;
    }
    
    /**
     * Delete payment from OrderPayment
     * @param int $id_order_payment id order payment
     * @return mixed True if success, Error message if false
     */
    public function deleteOrderPayment($id_order_payment)
    {
        $db = Db::getInstance();
        $result = $db->delete(
            'order_payment',
            'id_order_payment='.(int)$id_order_payment
        );
        if($result) {
            return true;
        } else {
            return $db->getMsgError();
        }
    }
    
    /**
     * Delete payment from OrderPayment Invoice
     * @param int $id_order_payment id order payment
     * @return mixed True if success, Error message if false
     */
    public function deleteOrderPaymentInvoice($id_order_payment)
    {
        $db = Db::getInstance();
        $result = $db->delete(
            'order_invoice_payment',
            'id_order_payment='.(int)$id_order_payment
        );
        if($result) {
            return true;
        } else {
            return $db->getMsgError();
        }
    }
    
    public function unixDate($date)
    {
        $date1 = str_replace('/', '-', $date);
        $d = array();
        
        if (preg_match('/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $date1)) {
            PrestaShopLoggerCore::addLog('Unix date:'.$date1);
            return $date1;
        } elseif (preg_match('/^(\d\d)-(\d\d)-(\d\d\d\d) (\d\d:\d\d:\d\d)$/', $date1, $d)) {
            $date1 = $d[3].'-'.$d[2].'-'.$d[1].' '.$d[4];
            PrestaShopLoggerCore::addLog('Unix date rev:'.$date1);
            return $date1;
        } else {
            PrestaShopLoggerCore::addLog('No match date:'.$date1);
            return $date1;
        }
    }
}
