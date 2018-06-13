<?php
/**
 * Custom Number
 *
 *  @author    motionSeed <ecommerce@motionseed.com>
 *  @copyright 2016 motionSeed. All rights reserved.
 *  @license   https://www.motionseed.com/en/license-module.html
 */

class OrderInvoice extends OrderInvoiceCore
{

    public function getInvoiceNumberFormatted($id_lang, $id_shop = null, $type = 'INVOICE')
    {
    	$number = $this->number . '/D';
    	PrestaShopLoggerCore::addLog('getInvoiceNumberFormatted: '.$number);
    	return $number;
    }
}
