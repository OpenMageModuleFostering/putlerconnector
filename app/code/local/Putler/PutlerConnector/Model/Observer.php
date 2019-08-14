<?php
/**
 * PutlerConnector
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.

 * @category    Putler Mod
 * @package     Putler_PutlerConnector
 * @author      Putler Core Team
 * @copyright   Copyright (c) 2012 Atwix (http://www.putler.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Putler_PutlerConnector_Model_Observer {
	
	/**
     * Subscribw the user, during checkout.
     * 
     * @return  void
     */
	public function postOrderDataToPutler($observer)
	{
		
		$order = $observer->getOrder();
    	if($order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE){

    		$email = Mage::getStoreConfig('putlersettings/general/putler_email');
    		$api_token = Mage::getStoreConfig('putlersettings/general/putler_api_token');
    		
    		if($email != '' && $api_token != '') {
				$putler_connector = Mage::getModel ( 'putler_putlerconnector/PutlerConnector');
				$order = $observer->getEvent ()->getOrder ();
				$orders_data = $putler_connector->preapreOrdersTosend ( array($order) );
				$result = $putler_connector->postOrdersToPutler ( $orders_data );
				if ($result) {
					Mage::log( 'Data have been sent successfully to putler' );
				}
    		} else {
    			Mage::log( 'Putler email and api token are blank' );
    		}    		
    	}
	}
	
}