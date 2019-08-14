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
class Putler_PutlerConnector_Adminhtml_PutlerConnectorController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Return some checking result
     *
     * @return void
     */
    public function checkAction() {
    	
    	$token = $this->getRequest()->getParam('api_token');
    	$api_token = implode(",",array_map('trim',(array_unique(array_filter(explode(",",trim($token))))))); // to save only unique token keys
    	$email = trim($this->getRequest()->getParam('email'));
    	if($api_token != '' && $email != ''){
	        $putler_connector = Mage::getModel('putler_putlerconnector/PutlerConnector', $email, $api_token);
	        $putler_connector = Mage::getModel('putler_putlerconnector/PutlerConnector');
	        $result = $putler_connector->validateApiInfo($email, $api_token);
	        
	        if($result ['ACK'] == 'Success') {
				if (!empty($result['Unauthorized_tokens'])) {
					$response = array('ACK' => "FAILURE", 'MESSAGE' => "{$result['Unauthorized_tokens']} token(s) are unauthorized.");
				} else {
					$config = new Mage_Core_Model_Config();
					$config->saveConfig('putlersettings/general/putler_email', $email);
					$config->saveConfig('putlersettings/general/putler_api_token', $api_token);
		        	$orders = $putler_connector->getOrders();
		        	$result = $putler_connector->postOrdersToPutler($orders);
		        	if($result) {
		        		$response = array('ACK' => 'SUCCESS', 'MESSAGE' => 'Settings Saved Successfully');
		        	}
				}
			} else {
				$response = array('ACK(' => 'FAILURE', 'MESSAGE' => 'Validation failed. Please Check Your Putler Email Address And API Token');
			}
    	} else {
    		$response = array('ACK(' => 'FAILURE', 'MESSAGE' => 'Your Putler Email Address And API Token Should Not Be Blank');
    	}
        
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($response));
    }
}