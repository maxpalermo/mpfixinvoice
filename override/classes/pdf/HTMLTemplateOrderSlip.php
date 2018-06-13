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
 *  @author 	PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2016 PrestaShop SA
 *  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.5
 */
class HTMLTemplateOrderSlip extends HTMLTemplateOrderSlipCore
{
    public $order;
    public $order_slip;
    public $shop_address;
    public $delivery_address;
    public $invoice_address;
    public $products;
    public $shipping;
    public $fees;
    public $taxes_breakdown;
    public $totals;
    public $discount;
    protected $taxes;
    protected $taxes_rates;

    /**
     * @param OrderSlip $order_slip
     * @param $smarty
     * @throws PrestaShopException
     */
    public function __construct(OrderSlip $order_slip, $smarty)
    {
        $this->order_slip = $order_slip;
        $this->order = new Order((int)$order_slip->id_order);
        $this->smarty = $smarty;
        // header informations
        $this->date = Tools::displayDate($this->order_slip->date_add);
        $prefix = Configuration::get('PS_CREDIT_SLIP_PREFIX', Context::getContext()->language->id);
        $this->title = sprintf(HTMLTemplateOrderSlip::l('%1$s%2$06d'), $prefix, (int)$this->order_slip->id);
        $this->shop = new Shop((int)$this->order->id_shop);
        $this->taxes_breakdown = array();
    }

    /**
     * Returns the template's HTML header
     *
     * @return string HTML header
     */
    public function getHeader()
    {
        $this->assignCommonHeaderData();
        $this->smarty->assign(array(
            'header' => HTMLTemplateOrderSlip::l('Credit slip'),
        ));

        return $this->smarty->fetch($this->getTemplate('order-slip.header'));
    }

    /**
     * Returns the template's HTML content
     *
     * @return string HTML content
     */
    public function getContent()
    {
        $this->fees = $this->getFees();
        $this->discount = $this->getDiscount();
        $this->delivery_address = $this->createDeliveryAddress();
        $this->invoice_address = $this->createInvoiceAddress();
        $this->shop_address = $this->createShopAddress();
        $this->products = $this->getOrderSlipProducs();
        $this->taxes_breakdown = $this->getTaxesBreakdown();
        $this->shipping = $this->getShipping();
        $this->totals = $this->getTotals();
        
        $this->taxes_breakdown[] = $this->fees;
        
        $this->smarty->assign(array(
            'order' => $this->order,
            'order_slip' => $this->order_slip,
            'order_details' => $this->products,
            'cart_rules' => $this->order_slip->order_slip_type == 1 ? $this->order->getCartRules($this->order_invoice->id) : false,
            'amount_choosen' => $this->order_slip->order_slip_type == 2 ? true : false,
            'delivery_address' => $this->delivery_address,
            'invoice_address' => $this->invoice_address,
            'shop_address' => $this->shop_address,
            'addresses' => array(
                'invoice' => $this->invoice_address,
                'delivery' => $this->delivery_address
            ),
            'tax_excluded_display' => $tax_excluded_display,
            'total_cart_rule' => $total_cart_rule,
            'summary_vat_number' => $this->summary_vat_number,
            'taxes_breakdown' => $this->taxes_breakdown,
            'totals' => $this->totals,
            'total_paid' => $this->order->total_paid,
            'discount' => $this->discount,
            'total_slip' => $this->order_slip->total_products_tax_incl + $this->order_slip->total_shipping_tax_incl + $this->fees['total'],
        ));
        
        $tpls = array(
            'style_tab' => $this->smarty->fetch($this->getTemplate('invoice.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('order-slip.address-tab')),
            'summary_tab' => $this->smarty->fetch($this->getTemplate('order-slip.summary-tab')),
            'product_tab' => $this->smarty->fetch($this->getTemplate('order-slip.product-tab')),
            'total_tab' => $this->smarty->fetch($this->getTemplate('order-slip.total-tab')),
            'payment_tab' => $this->smarty->fetch($this->getTemplate('order-slip.payment-tab')),
            'tax_tab' => $this->smarty->fetch($this->getTemplate('order-slip.tax-tab')),
        );
        $this->smarty->assign($tpls);

        return $this->smarty->fetch($this->getTemplate('order-slip'));
    }
    
    public function getNameProduct($id_product_attribute, $id_product)
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
    
    public function createShopAddress()
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
    
    public function createDeliveryAddress()
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
    
    public function createInvoiceAddress()
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
        if ($address->vat_number) {
            $output .= tools::strtoupper($address->vat_number) . '<br>';
        } elseif ($address->dni) {
            $output .= Tools::strtoupper($address->dni) . '<br>';
        }
        
        if ($address->vat_number) {
            $this->summary_vat_number = array(
                'label' => 'VAT',
                'value' => tools::strtoupper($address->vat_number),
            );
        } elseif ($address->dni) {
            $this->summary_vat_number = array(
                'label' => 'DNI',
                'value' => Tools::strtoupper($address->dni),
            );
        } else {
            $this->summary_vat_number = array(
                'label' => '----',
                'value' => '------------',
            );
        }
        
        return $output;
    }
    
    /**
     * Returns the template filename when using bulk rendering
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'order-slips.pdf';
    }

    /**
     * Returns the template filename
     *
     * @return string filename
     */
    public function getFilename()
    {
        return 'order-slip-'.sprintf('%06d', $this->order_slip->id).'.pdf';
    }

    /**
     * Returns the tax tab content
     *
     * @return String Tax tab html content
     */
    public function getTaxTabContent()
    {
        $address = new Address((int)$this->order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
        $tax_exempt = Configuration::get('VATNUMBER_MANAGEMENT')
                            && !empty($address->vat_number)
                            && $address->id_country != Configuration::get('VATNUMBER_COUNTRY');

        $this->smarty->assign(array(
            'tax_exempt' => $tax_exempt,
            'product_tax_breakdown' => $this->getProductTaxesBreakdown(),
            'shipping_tax_breakdown' => $this->getShippingTaxesBreakdown(),
            'order' => $this->order,
            'ecotax_tax_breakdown' => $this->order_slip->getEcoTaxTaxesBreakdown(),
            'is_order_slip' => true,
            'tax_breakdowns' => $this->getTaxBreakdown(),
            'display_tax_bases_in_breakdowns' => false
        ));

        return $this->smarty->fetch($this->getTemplate('invoice.tax-tab'));
    }

    /**
     * Returns different tax breakdown elements
     *
     * @return Array Different tax breakdown elements
     */
    public function getTaxBreakdown()
    {
        $breakdowns = array(
            'product_tax' => $this->getProductTaxesBreakdown(),
            'shipping_tax' => $this->getShippingTaxesBreakdown(),
            'ecotax_tax' => $this->order_slip->getEcoTaxTaxesBreakdown(),
        );

        foreach ($breakdowns as $type => $bd) {
            if (empty($bd)) {
                unset($breakdowns[$type]);
            }
        }

        if (empty($breakdowns)) {
            $breakdowns = false;
        }

        if (isset($breakdowns['product_tax'])) {
            foreach ($breakdowns['product_tax'] as &$bd) {
                $bd['total_tax_excl'] = $bd['total_price_tax_excl'];
            }
        }

        if (isset($breakdowns['ecotax_tax'])) {
            foreach ($breakdowns['ecotax_tax'] as &$bd) {
                $bd['total_tax_excl'] = $bd['ecotax_tax_excl'];
                $bd['total_amount'] = $bd['ecotax_tax_incl'] - $bd['ecotax_tax_excl'];
            }
        }

        return $breakdowns;
    }

    public function getProductTaxesBreakdown()
    {
        // $breakdown will be an array with tax rates as keys and at least the columns:
        // 	- 'total_price_tax_excl'
        // 	- 'total_amount'
        $breakdown = array();

        $details = $this->order->getProductTaxesDetails($this->order->products);

        foreach ($details as $row) {
            $rate = sprintf('%.3f', $row['tax_rate']);
            if (!isset($breakdown[$rate])) {
                $breakdown[$rate] = array(
                    'total_price_tax_excl' => 0,
                    'total_amount' => 0,
                    'id_tax' => $row['id_tax'],
                    'rate' =>$rate,
                );
            }

            $breakdown[$rate]['total_price_tax_excl'] += $row['total_tax_base'];
            $breakdown[$rate]['total_amount'] += $row['total_amount'];
        }

        foreach ($breakdown as $rate => $data) {
            $breakdown[$rate]['total_price_tax_excl'] = Tools::ps_round($data['total_price_tax_excl'], _PS_PRICE_COMPUTE_PRECISION_, $this->order->round_mode);
            $breakdown[$rate]['total_amount'] = Tools::ps_round($data['total_amount'], _PS_PRICE_COMPUTE_PRECISION_, $this->order->round_mode);
        }

        ksort($breakdown);

        return $breakdown;
    }

    /**
     * Returns Shipping tax breakdown elements
     *
     * @return Array Shipping tax breakdown elements
     */
    public function getShippingTaxesBreakdown()
    {
        $taxes_breakdown = array();
        $tax = new Tax();
        $tax->rate = $this->order->carrier_tax_rate;
        $tax_calculator = new TaxCalculator(array($tax));
        $customer = new Customer((int)$this->order->id_customer);
        $tax_excluded_display = Group::getPriceDisplayMethod((int)$customer->id_default_group);

        if ($tax_excluded_display) {
            $total_tax_excl = $this->order_slip->shipping_cost_amount;
            $shipping_tax_amount = $tax_calculator->addTaxes($this->order_slip->shipping_cost_amount) - $total_tax_excl;
        } else {
            $total_tax_excl = $tax_calculator->removeTaxes($this->order_slip->shipping_cost_amount);
            $shipping_tax_amount = $this->order_slip->shipping_cost_amount - $total_tax_excl;
        }

        if ($shipping_tax_amount > 0) {
            $taxes_breakdown[] = array(
                'rate' =>  $this->order->carrier_tax_rate,
                'total_amount' => $shipping_tax_amount,
                'total_tax_excl' => $total_tax_excl
            );
        }

        return $taxes_breakdown;
    }
    
    public function truncateExtraDecimals($val, $precision) {
        if ($val == 0) {
            return $val;
        }
        $pow = pow(10, $precision);
        $outpow = pow(10, $precision);
        $precise = (int)((float)$val * $pow);
        $last_cypher = (int)Tools::substr($precise, -1);
        switch ($this->round_type) {
            case 2:
                if ($last_cypher < 5) {
                    $output = Tools::substr($precise / $outpow, 0 ,-1);
                } else {
                    $round = $precise + 10;
                    $output = Tools::substr($round / $outpow, 0 ,-1);
                }
                break;
            case 3:
                if ($last_cypher < 6) {
                    $output = Tools::substr($precise / $outpow, 0 ,-1);
                } else {
                    $round = $precise + 10;
                    $output = Tools::substr($round / $outpow, 0 ,-1);
                }
                break;
            default:
                $output = $precise / $outpow;
        }
        
        return (float)$output; 
    }
    
    public function getOrderSlipProducs()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('od.product_id')
            ->select('p.reference')
            ->select('od.product_attribute_id')
            ->select('od.id_tax_rules_group')
            ->select('osd.unit_price_tax_excl')
            ->select('osd.product_quantity')
            ->select('osd.total_price_tax_excl')
            ->select('osd.unit_price_tax_incl')
            ->select('osd.total_price_tax_incl')
            ->from('order_slip_detail', 'osd')
            ->innerJoin('order_detail', 'od', 'od.id_order_detail=osd.id_order_detail')
            ->innerJoin('product', 'p', 'p.id_product=od.product_id')
            ->where('osd.id_order_slip='.(int)$this->order_slip->id)
            ->orderBy('p.reference');
        $result = $db->executeS($sql);
        if ($result) {
            foreach($result as &$row) {
                $row['unit_price_tax_excl'] = $this->applyDiscount($row['unit_price_tax_excl']);
                $row['unit_price_tax_incl'] = $this->applyDiscount($row['unit_price_tax_incl']);
                $row['total_price_tax_excl'] = $this->applyDiscount($row['total_price_tax_excl']);
                $row['total_price_tax_incl'] = $this->applyDiscount($row['total_price_tax_incl']);
                $row['name'] = $this->getNameProduct($row['product_attribute_id'], $row['product_id']);
                $row['tax_rate'] = $this->getTaxRate($row['id_tax_rules_group']);
            }
            return $result;
        } else {
            
            return array();
        }
    }
    
    public function getTaxRate($id_tax_rules_group)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql->select('t.rate')
            ->from('tax', 't')
            ->innerJoin('tax_rule', 'tr', 'tr.id_tax=t.id_tax')
            ->where('tr.id_tax_rules_group='.(int)$id_tax_rules_group);
        $tax_rate = $db->getValue($sql);
        if ($tax_rate) {
            return $tax_rate;
        } else {
            return 0;
        }
    }
    
    public function getFees()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
                ->from('mp_advpayment_fee')
                ->where('id_order = ' . (int)$this->order->id);
        $row = $db->getRow($sql);
        if ($row && $row['fee_tax_incl']>0) {
            $fees = array(
                'taxable' =>  $row['fee_tax_excl'],
                'tax_rate' => sprintf('%.3f', $row['fee_tax_rate']),
                'tax' => $row['fee_tax_incl'] - $row['fee_tax_excl'],
                'total' => $row['fee_tax_incl'],
                'label' => $this->l('Fees'),
            );
        } else {
            $fees = array(
                'taxable' =>  0,
                'tax_rate' => 0,
                'tax' => 0,
                'total' => 0,
                'label' => $this->l('Fees'),
            );
        }
        return $fees;
    }
    
    public function getShipping()
    {
        $shipping = array();
        
        if ($this->order_slip->shipping_cost > 0)
        {
            $shipping['taxable'] = $this->order_slip->total_shipping_tax_excl;
            $shipping['total'] = $this->order_slip->total_shipping_tax_incl;
            $shipping['tax_rate'] = sprintf("%.3f",22);
            $shipping['tax'] = $this->order_slip->total_shipping_tax_incl - $this->order_slip->total_shipping_tax_excl;
            $shipping['label'] = $this->l('Shipping');
            
            $this->taxes_breakdown[] = $shipping;
        } else {
            $shipping['taxable'] = 0;
            $shipping['tax'] = 0;
            $shipping['tax_rate'] = 0;
            $shipping['total'] = 0;
            $shipping['label'] = $this->l('Shipping');
        }
        
        return $shipping;
    }
    
    public function getTaxesBreakdown()
    {
        $taxes_products_breakdown = array();
        $taxes_breakdown = array();
        
        foreach ($this->products as $product) {
            $tax_rate = $product['tax_rate'];
            $idx = 'idx' . $tax_rate;
            if (isset($taxes_products_breakdown[$idx])) {
                $tax_breakdown = &$taxes_products_breakdown[$idx];
                $tax_breakdown['taxable'] += $product['total_price_tax_excl'];
                $tax_breakdown['tax'] += $product['total_price_tax_incl'] - $product['total_price_tax_excl'];
                $tax_breakdown['total'] += $product['total_price_tax_incl'];
            } else {
                $taxes_products_breakdown[$idx] = array();
                $tax_breakdown = &$taxes_products_breakdown[$idx];
                $tax_breakdown['taxable'] = $product['total_price_tax_excl'];
                $tax_breakdown['tax'] = $product['total_price_tax_incl'] - $product['total_price_tax_excl'];
                $tax_breakdown['tax_rate'] = $tax_rate;
                $tax_breakdown['total'] = $product['total_price_tax_incl'];
                $tax_breakdown['label'] = $this->l('Products');
            }
        }
        
        foreach ($taxes_products_breakdown as $tax_breakdown) {
            $taxes_breakdown[] = $tax_breakdown;
        }
        
        return $taxes_breakdown;
    }
    
    public function getTotals()
    {
        $totals = array(
            'total_products_tax_excl' => 0,
            'total_products_tax_incl' => 0,
            'total_shipping_tax_excl' => $this->shipping['taxable'],
            'total_fees_tax_excl' => $this->fees['taxable'],
            'total_order_slip_tax_excl' => 0,
            'total_taxes' => 0,
            'total_order_slip_tax_incl' => 0,
        );
        foreach ($this->products as $product) {
            $totals['total_products_tax_excl'] += $product['total_price_tax_excl'];
            $totals['total_products_tax_incl'] += $product['total_price_tax_incl'];
        }
        $totals['total_order_slip_tax_excl'] = $totals['total_products_tax_excl'] + 
                $this->order_slip->total_shipping_tax_excl +
                $this->fees['taxable'];
        $totals['total_order_slip_tax_incl'] = $totals['total_products_tax_incl'] + 
                $this->order_slip->total_shipping_tax_incl +
                $this->fees['total'];
        $totals['total_taxes'] = $totals['total_order_slip_tax_incl'] - $totals['total_order_slip_tax_excl'];
        
        return $totals;
    }
    
    public function getDiscount()
    {
        $total = $this->order_slip->total_products_tax_incl 
            + $this->order_slip->total_shipping_tax_incl
            + $this->fees['total'];
        $amount = $this->order_slip->amount;
        $discount = (($total-$amount)*100)/$total;
        return sprintf("%.3f",$discount);
    }
    
    public function applyDiscount($value)
    {
        return sprintf("%.2f", $this->truncateExtraDecimals($value * (100-$this->discount) / 100, 2));
    }
}
