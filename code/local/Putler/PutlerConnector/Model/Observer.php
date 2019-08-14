<?php


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
				$result = $putler_connector->validateApiInfo ($email, $api_token);
				if ($result) {
			
					$order = $observer->getEvent ()->getOrder ();
					$orders_data = $putler_connector->preapreOrdersTosend ( array($order) );
					$result = $putler_connector->postOrdersToPutler ( $orders_data );
					if ($result) {
						Mage::log( 'Data have been sent successfully to putler' );
					}
				} else {
					Mage::log( 'Error while sending data to putler' );
				}
    		} else {
    			Mage::log( 'Putler email and api token are blank' );
    		}    		
    	}
	}
	
}