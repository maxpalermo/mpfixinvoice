<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
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
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.5
 */
class HTMLTemplateInvoice extends HTMLTemplateInvoiceCore
{
    public $order;
    public $order_invoice;
    public $available_in_your_account = false;
    public $fees;
    public $tax_breakdowns;
    public $id_lang;
    public $id_shop;
    protected $decimals;
    protected $round_mode;
    protected $round_type;
    protected $country_default;
    protected $foreign_invoice;
    protected $products;
    protected $taxes;
    protected $taxes_rates;

    /**
     * @param OrderInvoice $order_invoice
     * @param $smarty
     * @throws PrestaShopException
     */
    public function __construct(OrderInvoice $order_invoice, $smarty, $bulk_mode = false)
    {
        $id_invoice = (int)$order_invoice->id;
        PrestaShopLoggerCore::addLog('construct Invoice Override:'.$order_invoice->id);
        $this->order_invoice = new OrderInvoice($id_invoice);
        $this->order = new Order((int)$this->order_invoice->id_order);
        $this->products = $this->order->getProducts();
        $this->smarty = $smarty;
        $id_order = (int) $this->order_invoice->id_order;
        // If shop_address is null, then update it with current one.
        // But no DB save required here to avoid massive updates for bulk PDF generation case.
        // (DB: bug fixed in 1.6.1.1 with upgrade SQL script to avoid null shop_address in old orderInvoices)
        if (!isset($this->order_invoice->shop_address) || !$this->order_invoice->shop_address) {
            $this->order_invoice->shop_address = OrderInvoice::getCurrentFormattedShopAddress((int)$this->order->id_shop);
            if (!$bulk_mode) {
                OrderInvoice::fixAllShopAddresses();
            }
        }
        // header informations
        $this->date = Tools::displayDate($order_invoice->date_add);
        $this->id_lang = (int)$this->order->id_lang;
        $this->id_shop = (int)$this->order->id_shop;
        $this->shop = new Shop((int)$this->id_shop);
        $this->decimals = (int)Configuration::get('PS_PRICE_DISPLAY_PRECISION');
        $this->round_mode = (int)Configuration::get('PS_PRICE_ROUND_MODE');
        $this->round_type = (int)Configuration::get('PS_ROUND_TYPE');
        $this->country_default = Configuration::get('PS_COUNTRY_DEFAULT');
        $this->title = $order_invoice->getInvoiceNumberFormatted((int)$this->id_lang,(int)$this->order->id_shop);
        /**
         * GET FEE
         */
        $this->fees = $this->getFee($id_order);
        $this->taxes = 0;
        $this->taxes_rates = array();
    }
    
    /**
     * Returns the template's HTML header
     *
     * @return string HTML header
     */
    public function getHeader()
    {
		$this->assignCommonHeaderData();
        $this->smarty->assign(array('header' => HTMLTemplateInvoice::l('Invoice')));

        return $this->smarty->fetch($this->getTemplate('header'));
    }
    
    /**
     * Return the template's HTML content
     * 
     * @return string HTML content
     */
    public function getContent()
    {
		$this->smarty = Context::getContext()->smarty;
        $address = new AddressCore($this->order->id_address_invoice);
        $country = new CountryCore($address->id_country);
        $this->getDetailedTaxes();
        $data = array(
            'order_invoice' => $this->order_invoice,
            'order' => $this->order,
            'shop_address' => $this->createShopAddress(),
            'delivery_address' => $this->createDeliveryAddress(),
            'invoice_address' => $this->createInvoiceAddress(),
            'addresses' => array(
                'delivery' => new AddressCore($this->order->id_address_delivery),
                'invoice' => new AddressCore($this->order->id_address_invoice),
            ),
            'carrier_row' => $this->getCarrier(),
            'products_row' => $this->getDetailProducts(),
            'cart_rules' => $this->getCartRules(),
            'totals' => $this->getTotals(),
            'taxes_row' => $this->taxes_rates,
            'taxes_amount' => $this->taxes,
            'foreign_invoice' => !$this->isLocal(),
        );
        $this->smarty->assign($data);
        
        $tpls = array(
            'style_tab' => $this->smarty->fetch($this->getTemplate('invoice.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('invoice.addresses-tab')),
            'summary_tab' => $this->smarty->fetch($this->getTemplate('invoice.summary-tab')),
            'product_tab' => $this->smarty->fetch($this->getTemplate('invoice.product-tab')),
            'tax_tab' => $this->smarty->fetch($this->getTemplate('invoice.tax-tab')),//$this->getTaxTabContent(),
            'payment_tab' => $this->smarty->fetch($this->getTemplate('invoice.payment-tab')),
            'note_tab' => $this->smarty->fetch($this->getTemplate('invoice.note-tab')),
            'total_tab' => $this->smarty->fetch($this->getTemplate('invoice.total-tab')),
            'shipping_tab' => $this->smarty->fetch($this->getTemplate('invoice.shipping-tab')),
        );
        
        $this->smarty->assign($tpls);
        $pdf = $this->smarty->fetch($this->getTemplateByCountry($country->iso_code));
        return $pdf;
    }
    
    private function getFee($id_order)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
                ->from('mp_advpayment_fee')
                ->where('id_order = ' . (int)$id_order);
        $result = $db->getRow($sql);
        if (empty($result) || !$result) {
            $order = new OrderCore((int)$id_order);
            $fees =  array(
                'id_fee' => 0,
                'id_order' => $id_order,
                'total_paid_tax_included' => $order->total_paid_tax_incl,
                'total_paid_tax_excluded' => $order->total_paid_tax_excl,
                'fee_tax_incl' => 0,
                'fee_tax_excl' => 0,
                'fee_tax_rate' => 0,
                'transaction_id' => '',
                'payment_method' => $order->module,
                'date_add' => $order->date_add,
                'date_upd' => $order->date_upd,
            );
        } else {
            $fees = $result;
        }
        return $fees;
    }
    
    private function getNameProduct($id_product_attribute, $id_product)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $id_lang = Context::getContext()->language->id;
        $sql->select('id_attribute')
            ->from('product_attribute_combination')
            ->where('id_product_attribute = ' . (int)$id_product_attribute);
        $product = new ProductCore((int)$id_product);
        $name = $product->name[(int)$id_lang];
        $attributes = $db->executeS($sql);
        foreach($attributes as $attribute) {
            $attr = new AttributeCore($attribute['id_attribute']);
            $name .= ' ' . $attr->name[(int)$id_lang];
        }
        
        return $name;
    }
    
    private function createShopAddress()
    {
        $output = array(
            Configuration::get('PS_SHOP_NAME'),
            Configuration::get('PS_SHOP_ADDR1'),
            Configuration::get('PS_SHOP_ADDR2'),
            Configuration::get('PS_SHOP_CODE') . ' ' . Configuration::get('PS_SHOP_CITY'),
            Configuration::get('PS_SHOP_STATE'),
            Tools::strtoupper(Configuration::get('PS_SHOP_COUNTRY')),
            Configuration::get('PS_SHOP_PHONE'),
        );
        
        return implode('<br>',$output);
    }
	
	private function createDeliveryAddress()
    {
        $id_address = $this->order->id_address_delivery;
        $address = new Address($id_address);
        $state = new StateCore($address->id_state);
        
        $output = '';
        if ($address->company) {
            $output .= $address->company . '<br>';
        } else {
            $output .= $address->firstname . ' ' . $address->lastname . '<br>';
        }
        if ($address->address1) {
            $output .= $address->address1 . '<br>';
        }
        if ($address->address2) {
            $output .= $address->address2 . '<br>';
        }
        $output .= $address->postcode . ' - ' . $address->city . '<br>';
        $output .= $state->name . '<br>';
        $output .= $address->country . '<br>';
        if ($address->phone_mobile && $address->phone) {
            $output .= $address->phone_mobile;
        } elseif ($address->phone_mobile) {
            $output .= $address->phone_mobile;
        } elseif ($address->phone) {
            $output .= $address->phone;
        }
        
        return $output;
    }
    
    private function createInvoiceAddress()
    {
        $id_address = $this->order->id_address_invoice;
        $address = new Address($id_address);
        $state = new StateCore($address->id_state);
        
        $output = '';
        if ($address->company) {
            $output .= $address->company . '<br>';
        } else {
            $output .= $address->firstname . ' ' . $address->lastname . '<br>';
        }
        if ($address->address1) {
            $output .= $address->address1 . '<br>';
        }
        if ($address->address2) {
            $output .= $address->address2 . '<br>';
        }
        $output .= $address->postcode . ' - ' . $address->city . '<br>';
        $output .= $state->name . '<br>';
        $output .= $address->country . '<br>';
        
        return $output;
    }
    
    private function getTaxRate($id_product)
    {
        $product = new ProductCore($id_product);
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('t.rate')
                ->from('tax', 't')
                ->innerJoin('tax_rule', 'tr', 'tr.id_tax=t.id_tax')
                ->where('tr.id_tax_rules_group = ' . (int)$product->id_tax_rules_group);
        
        $tax_rate = (float)$db->getValue($sql);
        
        if ($this->isLocal()) {
            return sprintf('%.2f', $tax_rate) . " %";
        } else {
            return 'F.C.I.';
        }
    }
    
    private function isLocal()
    {
        $def_country = Context::getContext()->country->id;
        $address = new AddressCore($this->order->id_address_invoice);
        $doc_country = $address->id_country;
        
        return ($def_country == $doc_country);
    }
    
    private function getCartRules()
    {
        $cart_rules = $this->order->getCartRules($this->order_invoice->id);
        $free_shipping = false;
        foreach ($cart_rules as $key => $cart_rule) {
            if ($cart_rule['free_shipping']) {
                $free_shipping = true;
                /**
                 * Adjust cart rule value to remove the amount of the shipping.
                 * We're not interested in displaying the shipping discount as it is already shown as "Free Shipping".
                 */
                $cart_rules[$key]['value_tax_excl'] -= $this->order_invoice->total_shipping_tax_excl;
                $cart_rules[$key]['value'] -= $this->order_invoice->total_shipping_tax_incl;

                /**
                 * Don't display cart rules that are only about free shipping and don't create
                 * a discount on products.
                 */
                if ($cart_rules[$key]['value'] == 0) {
                    unset($cart_rules[$key]);
                }
            }
        }
        
        return $cart_rules;
    }
    
    private function getCarrier()
    {
        $carrier = new CarrierCore($this->order->id_carrier);
        return array(
            'label' => $this->l('Carrier'),
            'name' => Tools::strtoupper($carrier->name),
        );
    }
    
    private function getDetailProducts()
    {
        $rows = array();
        foreach ($this->products as $product) {
            $prod = array (
                'reference' => $product['product_reference'],
                'name' => $this->getNameProduct($product['product_attribute_id'], $product['product_id']),
                'tax_rate' => $this->getTaxRate($product['product_id']),
                'price' => Tools::displayPrice($product['original_product_price']),
                'discount' => $this->getproductDiscount($product['original_product_price'], $product['unit_price_tax_excl']),
                'quantity' => $product['product_quantity'],
                'total' => Tools::displayPrice($product['total_price_tax_excl']),
            );
            $rows[] = $prod;
        }
        return $rows;
    }
    
    private function getProductDiscount($original_price, $reduction_price)
    {
        return sprintf("%.2f", (($original_price-$reduction_price) / $original_price) * 100) . " %";
    }
    
    private function addRateToIndex($tax_rate, $taxes, $amount_tax_excl)
    {
        $idx = sprintf("%.2f", $tax_rate) . "%";
        if (empty($taxes[$idx])) {
            $amount_tax = sprintf('%.2f', $amount_tax_excl * $tax_rate / 100);
            $amount_tax_incl = sprintf("%.2f", $amount_tax_excl + $amount_tax); 
            $taxes[$idx] = array(
                'tax_rate' => $idx,
                'amount_tax_excl' => $amount_tax_excl,
                'amount_tax_incl' => $amount_tax_incl,
                'amount_tax' => $amount_tax,
            );
        } else {
            $amount_tax = sprintf('%.2f', $amount_tax_excl * $tax_rate / 100);
            $amount_tax_incl = sprintf("%.2f", $amount_tax_excl + $amount_tax); 
            $taxes[$idx] = array(
                'tax_rate' => $idx,
                'amount_tax_excl' => $amount_tax_excl + $taxes[$idx]['amount_tax_excl'],
                'amount_tax_incl' => $amount_tax_incl + $taxes[$idx]['amount_tax_incl'],
                'amount_tax' => $amount_tax + + $taxes[$idx]['amount_tax'],
            );
        }
        
        return $taxes;
    }
    
    private function getDetailedTaxes()
    {
        $taxes = array();
        $this->taxes = 0;
        foreach ($this->products as $product) {
            $taxes = $this->addRateToIndex(
                $product['tax_rate'],
                $taxes,
                (float)$product['total_price_tax_excl']
            );
        }
        
        if ($this->order->total_discounts_tax_excl>0) {
            $tax_rate = '22.00';//sprintf('$.2f', ($this->order->total_discounts_tax_incl - $this->order->total_discounts_tax_excl) * 100 / $this->order->total_discounts_tax_excl);
            $amount_tax_excl = -$this->order->total_discounts_tax_excl;
            $taxes = $this->addRateToIndex(
                $tax_rate,
                $taxes,
                $amount_tax_excl
            );
        }
        
        $shipping_tax_excl = $this->order->total_shipping_tax_excl;
        $shipping_tax_rate = $this->getCarrierTaxRate($this->order->id_carrier);
        if ($shipping_tax_excl) {
            $taxes = $this->addRateToIndex($shipping_tax_rate, $taxes, $shipping_tax_excl);
        }
        
        $fee_tax_rate = sprintf("%.2f", $this->fees['fee_tax_rate']);
        $fee_amount_tax_excl = (float)$this->fees['fee_tax_excl'];
        if ($fee_amount_tax_excl) {
            $taxes = $this->addRateToIndex($fee_tax_rate, $taxes, $fee_amount_tax_excl);
        }
        
        /**
         * SET TAXES FOR EACH TAX
         */
        
        foreach ($taxes as &$row) {
            $tax_rate = $row['tax_rate'];
            $amount_tax_excl = $row['amount_tax_excl'];
            $amount_tax = sprintf("%.2f", round($amount_tax_excl  * $tax_rate / 100, 2));
            $amount_tax_incl = sprintf("%.2f", $amount_tax + $amount_tax_excl);
            $row['amount_tax'] = $amount_tax;
            $row['amount_tax_incl'] = $amount_tax_incl;
            $this->taxes += $amount_tax;
        }
        
        PrestaShopLoggerCore::addLog('Detailed taxes: '.print_r($taxes,1));
        $this->taxes_rates = $taxes;
        return $taxes;
    }
    
    private function getDetailTaxes()
    {
        $rows = array();
        
        $product_rows = array();
        foreach ($this->products as $product) {
            $idx = $product['tax_rate'] . "%";
            if (empty($product_rows)) {
                $amount_tax_excl = $product['total_price_tax_excl'];
                $amount_tax_incl = $product['total_price_tax_incl'];
                $product_rows[$idx] = array(
                    'label' => $this->l('Products'),
                    'tax_rate' => sprintf("%.2f", $product['tax_rate']) . " %",
                    'amount_tax_excl' => $amount_tax_excl,
                    'amount_tax_incl' => $amount_tax_incl,
                );
            } else {
                try {
                    $row = $product_rows[$idx];
                } catch (Exception $ex) {
                    PrestaShopLoggerCore::addLog($ex->getMessage());
                    $product_rows[$idx] = array(
                        'label' => $this->l('Products'),
                        'tax_rate' => sprintf("%.2f", $product['tax_rate']) . " %",
                        'amount_tax_excl' => 0,
                        'amount_tax_incl' => 0,
                    );
                    $row = $product_rows[$idx];
                }
                
                $amount_tax_excl = $row['amount_tax_excl'] + $product['total_price_tax_excl'];
                $amount_tax_incl = $row['amount_tax_incl'] + $product['total_price_tax_incl'];
                $product_rows[$idx] = array(
                    'label' => $this->l('Products'),
                    'tax_rate' => sprintf("%.2f", $product['tax_rate']) . " %",
                    'amount_tax_excl' => $amount_tax_excl,
                    'amount_tax_incl' => $amount_tax_incl,
                );
            }
        }
        $rows['products'] = $product_rows;
        
        if ($this->order->total_discounts_tax_excl>0) {
            $tax_rate = ($this->order->total_discounts_tax_incl - $this->order->total_discounts_tax_excl) * 100 / $this->order->total_discounts_tax_excl;
            $discount_rows = array(
                "FCI" => array(
                    'label' => $this->l('Discounts'),
                    'tax_rate' => sprintf("%.2f", $tax_rate) . " %",
                    'amount_tax_excl' => -$this->order->total_discounts_tax_excl,
                    'amount_tax_incl' => -$this->order->total_discounts_tax_incl,
                ),
            );
            $rows['discounts'] = $discount_rows;
        }
        
        $shipping = new CarrierCore($this->order->id_carrier);
        $shipping_tax_excl = $this->order->total_shipping_tax_excl;
        $shipping_tax_incl = $this->order->total_shipping_tax_incl;
        $shipping_tax = $shipping_tax_incl-$shipping_tax_excl;
        $shipping_tax_rate = $this->getCarrierTaxRate($this->order->id_carrier);
        
        if ($shipping_tax_excl>0) {
            $shipping_rows = array(
                $shipping_tax_rate."%" => array(
                    'label' => $this->l('Shipping'),
                    'tax_rate' => sprintf("%.2f", $shipping_tax_rate) . " %",
                    'amount_tax_excl' => $shipping_tax_excl,
                    'amount_tax_incl' => $shipping_tax_incl,
                ),
            );
            $rows['shipping'] = $shipping_rows;
        }
        
        $fee_rows = array();
        $idx = $this->fees['fee_tax_rate'] . "%";
        $fee_rows[$idx] = array(
            'label' => $this->l('Fees'),
            'tax_rate' => sprintf("%.2f", $this->fees['fee_tax_rate']) . " %",
            'amount_tax_excl' => $this->fees['fee_tax_excl'],
            'amount_tax_incl' => $this->fees['fee_tax_incl'],
        );
        if ($this->fees['fee_tax_excl']!=0) {
            $rows['fees'] = $fee_rows;
        }
        
        foreach ($rows as &$row) {
            foreach ($row as &$tax) {
                $tax['amount_tax'] = Tools::displayPrice($tax['amount_tax_incl'] - $tax['amount_tax_excl']);
                $tax['amount_tax_excl'] = Tools::displayPrice($tax['amount_tax_excl']);
                $tax['amount_tax_incl'] = Tools::displayPrice($tax['amount_tax_incl']);
            }
        }
        
        return $rows;
    }
    
    private function getCarrierTaxRate($id_carrier)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('t.rate')
            ->from('tax', 't')
            ->innerJoin('tax_rule', 'tr', 't.id_tax=tr.id_tax')
            ->innerJoin('carrier_tax_rules_group_shop', 'ctr', 'ctr.id_tax_rules_group=tr.id_tax_rules_group')
            ->where('ctr.id_shop='.(int)$this->id_shop)
            ->where('ctr.id_carrier='.(int)$id_carrier);
        $value = $db->getValue($sql);
        PrestaShopLoggerCore::addLog('Carrier_tax_rate:'.$sql->__toString());
        return $value;
    }
    
    private function getTotals()
    {
        $rows = array();
        if ($this->order_invoice->total_products>0) {
            $rows[] = array(
                        'label' => $this->l('Total products'),
                        'price' => Tools::displayPrice($this->order_invoice->total_products),
                        'value' => $this->order_invoice->total_products,
                        'tax_included' => $this->order_invoice->total_products_wt,
                    );
        }
        if ($this->order_invoice->total_discount_tax_excl>0) {
            $rows[] = array(
                        'label' => $this->l('Total discounts'),
                        'price' => Tools::displayPrice(-$this->order_invoice->total_discount_tax_excl),
                        'value' => -$this->order_invoice->total_discount_tax_excl,
                        'tax_included' => -$this->order_invoice->total_discount_tax_incl,
                    );
        }
        if ($this->order_invoice->total_shipping_tax_excl>0) {
            $rows[] = array(
                        'label' => $this->l('Total shippings'),
                        'price' => Tools::displayPrice($this->order_invoice->total_shipping_tax_excl),
                        'value' => $this->order_invoice->total_shipping_tax_excl,
                        'tax_included' => $this->order_invoice->total_shipping_tax_incl,
                    );
        }
        if ($this->order_invoice->total_wrapping_tax_excl>0) {
            $rows[] = array(
                        'label' => $this->l('Total wrapping'),
                        'price' => Tools::displayPrice($this->order_invoice->total_wrapping_tax_excl),
                        'value' => $this->order_invoice->total_wrapping_tax_excl,
                        'tax_included' => $this->order_invoice->total_wrapping_tax_incl,
                    );
        }
        if ($this->fees['fee_tax_excl']!=0) {
            $rows[] = array(
                        'label' => $this->fees['fee_tax_excl']>0?$this->l('Fees'):$this->l('Fee discounts'),
                        'price' => Tools::displayPrice($this->fees['fee_tax_excl']),
                        'value' => $this->fees['fee_tax_excl'],
                        'tax_included' => $this->fees['fee_tax_incl'],
                    );
        }
        
        $totals = array();
        $total_tax_excl = 0;
        $total_tax_incl = 0;
        foreach ($rows as $row) {
            $total_tax_excl+=$row['value'];
            $total_tax_incl+=$row['tax_included'];
        }
        $totals['total_tax_excl']['label'] = $this->l('Total (tax excluded)');
        $totals['total_tax_excl']['price'] = Tools::displayPrice($total_tax_excl);
        $totals['total_tax']['label'] = $this->l('Total taxes');
        $totals['total_tax']['price'] = Tools::displayPrice($this->taxes);
        $totals['total_tax_incl']['label'] = $this->l('Total (tax included)');
        $total_invoice = $total_tax_excl + $this->taxes;
        $totals['total_tax_incl']['price'] = Tools::displayPrice($total_invoice);
        
        $payment = sprintf("%.2f", $this->getPayment());
        $total_invoice_amount = sprintf("%.2f", $total_invoice);
        if ($payment>0 && ($payment != $total_invoice_amount)) {
            $difference = $payment-$total_invoice_amount;
            if ($difference<0) {
                $totals['rounds']['label'] = $this->l('Passive rounds');
                $totals['rounds']['price'] = Tools::displayPrice($difference);
            } else {
                $totals['rounds']['label'] = $this->l('Active rounds');
                $totals['rounds']['price'] = Tools::displayPrice($difference);
            }
            $totals['total_document']['label'] = $this->l('Total document');
            $totals['total_document']['price'] = Tools::displayPrice($total_invoice_amount+$difference);
            $this->taxes_rates['FCI'] = array(
                'tax_rate' => 'F.C.I.',
                'amount_tax_excl' => sprintf('%.2f', $difference),
                'amount_tax_incl' => sprintf('%.2f', $difference),
                'amount_tax' => 0
            );
        }
        
        $output = array(
            'rows' => $rows,
            'totals' => $totals,
        );
        
        return $output;
    }
	
    public function getPayment()
    {
        $payment = $this->order->getTotalPaid();
        return $payment;
    }
    
    public function round($value, $decimals = null, $round_mode = null)
    {
        return $value;
    }
    
    /**
     * Returns the invoice template associated to the country iso_code
     *
     * @param string $iso_country
     */
    protected function getTemplateByCountry($iso_country)
    {
        $file = Configuration::get('PS_INVOICE_MODEL');

        // try to fetch the iso template
        $template = $this->getTemplate($file.'.'.$iso_country);

        // else use the default one
        if (!$template) {
            $template = $this->getTemplate($file);
        }

        return $template;
    }

    /**
     * Returns the template filename when using bulk rendering
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'invoices.pdf';
    }

    /**
     * Returns the template filename
     *
     * @return string filename
     */
    public function getFilename()
    {
        $id_lang = Context::getContext()->language->id;
        $id_shop = (int)$this->order->id_shop;
        $format = '%1$s%2$06d';

        if (Configuration::get('PS_INVOICE_USE_YEAR')) {
            $format = Configuration::get('PS_INVOICE_YEAR_POS') ? '%1$s%3$s-%2$06d' : '%1$s%2$06d-%3$s';
        }

        return sprintf(
            $format,
            Configuration::get('PS_INVOICE_PREFIX', $id_lang, null, $id_shop),
            $this->order_invoice->number,
            date('Y', strtotime($this->order_invoice->date_add))
        ).'.pdf';
    }
}
