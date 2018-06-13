<?php

Class Order extends OrderCore 
{
    public static function generateReference()
    {
        $last_id = Db::getInstance()->getValue('
            SELECT MAX(id_order)
            FROM '._DB_PREFIX_.'orders');
        return "1000" . str_pad((int)$last_id + 1, 4, '0000', STR_PAD_LEFT);
    }

    /**
     * This method allows to generate first invoice of the current order
     */
    public function setInvoice($use_existing_payment = false)
    {
        PrestaShopLoggerCore::addLog('Call setInvoice() override');
        $id_invoice = $this->hasInvoice();
        if (!$id_invoice) {
        	$id_invoice = (int)$this->hasOrderInvoice();
        }
        if (!$id_invoice) {
            if ($id = (int)$this->getOrderInvoiceIdIfHasDelivery()) {
                $order_invoice = new OrderInvoice($id);
            } else {
                $order_invoice = new OrderInvoice();
            }
            $order_invoice->id_order = $this->id;
            if (!$id) {
                $order_invoice->number = 0;
            }

            // Save Order invoice

            $this->setInvoiceDetails($order_invoice);

            if (Configuration::get('PS_INVOICE')) {
                $this->setLastInvoiceNumber($order_invoice->id, $this->id_shop);
            }



            // Update order_carrier
            $id_order_carrier = Db::getInstance()->getValue('
				SELECT `id_order_carrier`
				FROM `'._DB_PREFIX_.'order_carrier`
				WHERE `id_order` = '.(int)$order_invoice->id_order.'
				AND (`id_order_invoice` IS NULL OR `id_order_invoice` = 0)');

            if ($id_order_carrier) {
                $order_carrier = new OrderCarrier($id_order_carrier);
                $order_carrier->id_order_invoice = (int)$order_invoice->id;
                $order_carrier->update();
            }

            // Update order detail
            Db::getInstance()->execute('
				UPDATE `'._DB_PREFIX_.'order_detail`
				SET `id_order_invoice` = '.(int)$order_invoice->id.'
				WHERE `id_order` = '.(int)$order_invoice->id_order);

            // Update order payment
            if ($use_existing_payment) {
                $id_order_payments = Db::getInstance()->executeS('
					SELECT DISTINCT op.id_order_payment
					FROM `'._DB_PREFIX_.'order_payment` op
					INNER JOIN `'._DB_PREFIX_.'orders` o ON (o.reference = op.order_reference)
					LEFT JOIN `'._DB_PREFIX_.'order_invoice_payment` oip ON (oip.id_order_payment = op.id_order_payment)
					WHERE (oip.id_order != '.(int)$order_invoice->id_order.' OR oip.id_order IS NULL) AND o.id_order = '.(int)$order_invoice->id_order);

                if (count($id_order_payments)) {
                    foreach ($id_order_payments as $order_payment) {
                        Db::getInstance()->execute('
							INSERT INTO `'._DB_PREFIX_.'order_invoice_payment`
							SET
								`id_order_invoice` = '.(int)$order_invoice->id.',
								`id_order_payment` = '.(int)$order_payment['id_order_payment'].',
								`id_order` = '.(int)$order_invoice->id_order);
                    }
                    // Clear cache
                    Cache::clean('order_invoice_paid_*');
                }
            }

            // Update order cart rule
            Db::getInstance()->execute('
				UPDATE `'._DB_PREFIX_.'order_cart_rule`
				SET `id_order_invoice` = '.(int)$order_invoice->id.'
				WHERE `id_order` = '.(int)$order_invoice->id_order);

            // Keep it for backward compatibility, to remove on 1.6 version
            $this->invoice_date = $order_invoice->date_add;

            if (Configuration::get('PS_INVOICE')) {
                $this->invoice_number = $this->getInvoiceNumber($order_invoice->id);
                $invoice_number = Hook::exec('actionSetInvoice', array(
                    get_class($this) => $this,
                    get_class($order_invoice) => $order_invoice,
                    'use_existing_payment' => (bool)$use_existing_payment
                ));

                if (is_numeric($invoice_number)) {
                    $this->invoice_number = (int)$invoice_number;
                } else {
                    $this->invoice_number = $this->getInvoiceNumber($order_invoice->id);
                }
            }
            $this->update();
        }
        return $this->fixInvoice();
        
    }

    public function fixInvoice()
    {
    	PrestaShopLoggerCore::addLog('Fixing invoice.');
    	//check if invoice can be created
        $id_shop = (int)Context::getContext()->shop->id;
        $address = new Address($this->id_address_invoice);
        $id_order_invoice = (int)$this->hasInvoice();
        if (!$id_order_invoice) {
        	$id_order_invoice = (int)$this->hasOrderInvoice();
        }
        PrestashopLoggerCore::addLog('Order '.$this->id.' has invoice '.$id_order_invoice);
        if (!$id_order_invoice) {
        	return false;
        }

        if ($address->vat_number) {
        	/**
        	 * There is an address with vat number, 
        	 * delete delivery and set invoice
        	 */
            $invoice = new OrderInvoice($id_order_invoice);
            $invoice->delivery_number = 0;
            $invoice->delivery_date = '1970-01-01 00:00:00';
            if ((int)$invoice->number == 0) {
            	$invoice->number = self::setLastDeliveryNumber($invoice->id, $id_shop);
            	$invoice->date_add = date('Y-m-d H:i:s');
            }
            $result = $invoice->update();
            PrestashopLoggerCore::addLog('Reset order_invoice delivery because there is a vat number: '.(int)$result);
            $this->delivery_number = 0;
            $this->delivery_date = '1970-01-01 00:00:00';
            $this->invoice_number = $id_order_invoice;
            $this->invoice_date = $invoice->date_add;
            $result = $this->update();
            PrestashopLoggerCore::addLog('Reset delivery because there is a vat number: '.(int)$result);
        } else {
        	/**
        	 * There is an address with no vat number, 
        	 * delete invoice and set delivery
        	 */
            $invoice = new OrderInvoice($id_order_invoice);
            $invoice->number = 0;
            $invoice->date_add = '1970-01-01 00:00:00';
            if ((int)$invoice->delivery_number == 0) {
            	$invoice->delivery_number = self::setLastDeliveryNumber($invoice->id, $id_shop);
            	$invoice->delivery_date = date('Y-m-d H:i:s');
            }
            $result = $invoice->update();
            PrestashopLoggerCore::addLog('Reset order_invoice invoice because there is no vat number: '.(int)$result);
            $this->invoice_number = 0;
            $this->invoice_date = '1970-01-01 00:00:00';
            $this->delivery_number = $invoice->delivery_number;
            $this->delivery_date = $invoice->delivery_date;
            $result = $this->update();
            PrestashopLoggerCore::addLog('Reset invoice because there is no vat number: '.(int)$result);
        }

        return true;
    }

    public function setDelivery()
    {
        PrestaShopLoggerCore::addLog('Overrided SetDelivery');
        // Get all invoice
        $order_invoice_collection = $this->getInvoicesCollection();
        foreach ($order_invoice_collection as $order_invoice) {
            /** @var OrderInvoice $order_invoice */
            if ($order_invoice->delivery_number) {
                continue;
            }

            // Set delivery number on invoice
            $order_invoice->delivery_number = 0;
            $order_invoice->delivery_date = date('Y-m-d H:i:s');
            // Update Order Invoice
            $order_invoice->update();
            $this->setDeliveryNumber($order_invoice->id, $this->id_shop);
            $this->delivery_number = $this->getDeliveryNumber($order_invoice->id);
        }

        // Keep it for backward compatibility, to remove on 1.6 version
        // Set delivery date
        $this->delivery_date = date('Y-m-d H:i:s');
        // Update object
        $this->update();
        return $this->fixInvoice();
    }

    /**
     * This method allows to generate first delivery slip of the current order
     */
    public function setDeliverySlip()
    {
        PrestaShopLoggerCore::addLog('Set delivery slip overrided');
        if (!$this->hasInvoice()) {
            $order_invoice = new OrderInvoice();
            $order_invoice->id_order = $this->id;
            $order_invoice->number = 0;
            $this->setInvoiceDetails($order_invoice);
            $this->delivery_date = $order_invoice->date_add;
            $this->delivery_number = $this->getDeliveryNumber($order_invoice->id);
            $this->update();
        }

        return $this->fixInvoice();
    }

    /**
     * Has invoice return true if this order has already an invoice
     *
     * @return bool
     */
    public function hasInvoice()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_invoice')
        	->from('order_invoice')
        	->where('id_order='.(int)$this->id)
        	->where('number>0');
        return (int)$db->getValue($sql);
    }

    public function hasOrderInvoice()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order_invoice')
        	->from('order_invoice')
        	->where('id_order='.(int)$this->id);
        return (int)$db->getValue($sql);
    }

    public static function setLastInvoiceNumber($order_invoice_id, $id_shop)
    {
        if (!$order_invoice_id) {
            return false;
        }

        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $year = date('Y');
        $number = (int)Configuration::get('PS_INVOICE_START_NUMBER', null, null, $id_shop);
        // If invoice start number has been set, you clean the value of this configuration
        if ($number) {
            Configuration::updateValue('PS_INVOICE_START_NUMBER', false, false, null, $id_shop);
            $number ++;
        } else {
        	if ((int)Configuration::get('PS_INVOICE_RESET')) {
        		$sql->select('max(cast(number as unsigned))')
	        		->from('order_invoice')
	        		->where('year(date_add)='.(int)$year);
        	} else {
        		$sql->select('max(cast(number as unsigned))')
	        		->from('order_invoice');
        	}
	        $number = ((int)$db->getValue($sql)) + 1;
        }
        PrestaShopLoggerCore::addLog('setLastInvoiceNumber: '.$number);
        return (int)$number;
    }

    public static function setLastDeliveryNumber($order_invoice_id, $id_shop)
    {
    	$db = Db::getInstance();
        $sql = new DbQueryCore();
        if ((int)Configuration::get('PS_INVOICE_RESET', null, null, $id_shop)) {
    		$sql->select('max(cast(delivery_number as unsigned))')
        		->from('order_invoice')
        		->where('year(delivery_date)='.(int)$year);
    	} else {
    		$sql->select('max(cast(delivery_number as unsigned))')
        		->from('order_invoice');
    	}
        $number = ((int)$db->getValue($sql)) + 1;
        PrestaShopLoggerCore::addLog('setLastDeliveryNumber: '.$number);
        return (int)$number;
    }
}
