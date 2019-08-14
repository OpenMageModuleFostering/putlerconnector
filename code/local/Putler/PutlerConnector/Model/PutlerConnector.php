<?php

class Putler_PutlerConnector_Model_PutlerConnector {
	
	var $api_url = 'http://api.putler.com/inbound/';
	var $email_address = '';
	var $api_token = '';
	
	var $text_domain = 'putler_connector';
	
	var $version = 1.0;
	private $name = 'magento';
	
	function __construct() {
		
	}
	function validateApiInfo($email, $token) {
		// Validate with API server
		
		$this->api_token = $token;
		$this->email_address = $email;
		$post_data = array ('action' => 'validate', 'headers' => array ('Authorization' => 'Basic ' . base64_encode ( $email . ':' . $token ), 'User-Agent' => 'Putler Connector/' . $this->version ) );
		$result = $this->makeRequest ( $post_data );
		
		if ($result ['ACK'] == 'Success') {
			return true;
		}
		
		//TODO: Check errors if any
		return false;
	}
	
	function postOrdersToPutler($orders) {
		
		
		if (empty ( $orders )) {
			return true;
		}
		
		$oid = ob_start ();
		$fp = fopen ( 'php://output', 'a+' );
		foreach ( ( array ) $orders as $index => $row ) {
			if ($index == 0) {
				fputcsv ( $fp, array_keys ( $row ) );
			}
			fputcsv ( $fp, $row );
		}
		fclose ( $fp );
		$csv_data = ob_get_clean ();
		if (ob_get_clean () > 0) {
			ob_end_clean ();
		}
		
		
		$post_data = array ('headers' => array ('Content-Type' => 'text/csv', 'Authorization' => 'Basic ' . base64_encode ( $this->email_address . ':' . $this->api_token ), 'User-Agent' => 'Putler Connector/' . $this->version ), 'timeout' => 120, 'body' => $csv_data );
		
		$result = $this->makeRequest ( $post_data, true );
		
		//TODO: Check if result contains error
		$server_response_default = array ('ACK' => 'Failure', 'MESSAGE' => 'Unknown Response', $this->text_domain );
		$server_response = $result;
		$server_response = array_merge ( $server_response_default, $server_response );
		
		if ($server_response ['ACK'] == "Success") {
			return true;
		}
		
		return false;
	
		//TODO: Throw an error if any
	}
	
	function makeRequest($post_data, $raw_data = false) {
		/*  Prepare a data to send on klawoo */
		
		
		$client = new Zend_Http_Client ( $this->api_url );
		if (isset ( $post_data ['headers'] )) {
			$headers = $post_data ['headers'];
			$client->setHeaders ( $headers );
		}
		
		$data = array();
		if (isset ( $post_data ['body'] )) {
			$data = $post_data ['body'];	
		}
		
		if($raw_data) {
			$client->setRawData($data);
		} else {
			$client->setParameterPost ( $data );
			$client->setParameterPost ( 'action', $post_data ['action'] );	
		}
		
		
		$result = $client->request ( Zend_Http_Client::POST );
		$response = json_decode ( $result->getBody (), true );
		
		return $response;
	}
	
	function getOrders() {
		// Note: Only fetch Completed Orders for now. Other Statuses 
		$orders = Mage::getModel ( 'sales/order' )->getCollection ()->addAttributeToSelect ( "*" )->addAttributeToFilter ( 'status', array ('complete') );
		$orders_data = $this->preapreOrdersTosend ( $orders );
		return $orders_data;
	}
	
	function preapreOrdersTosend($orders) {
		
		foreach ( $orders as $order ) {
			//$order = Mage::getModel ( 'sales/order' );
			
			$order_id = $order->getId();
			$order_total = round ( $order->getSubtotal(), 2 );
			
			$dateInGMT = date ( 'm/d/Y', ( int ) $order->getCreatedAtStoreDate()->getTimestamp() );
			$timeInGMT = date ( 'h:i:s A', ( int ) $order->getCreatedAtStoreDate()->getTimestamp()  );
			
			
			$order_status = $order->getStatus();
			if(in_array($order_status, array('on-hold', 'pending', 'failed'))) {
				$order_status_display = 'Pending';
			} else if (in_array($order_status, array('complete', 'processing', 'refunded'))) {
				$order_status_display = 'Completed';
			} else if ($order_status == "cancelled") {
				$order_status_display = 'Cancelled';
			}
			
			$response ['Date'] = $dateInGMT;
			$response ['Time'] = $timeInGMT;
			$response ['Time_Zone'] = 'GMT';
			
			$billing_name = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
			$response ['Source'] = $this->name;
			$response ['Name'] = $billing_name;
			$response ['Type'] = 'Shopping Cart Payment Received';
			
			$response ['Status'] = ucfirst ( $order_status_display );
			
			$order_curency = $order->getOrderCurrencyCode();
			$response ['Currency'] = $order_curency;
			
			$shipping_amount = round ($order->getShippingAmount(), 2);
			$response ['Gross'] = $order->getSubtotal() + $shipping_amount;
			$response ['Fee'] = 0.00;
			$response ['Net'] = $order->getSubtotal() + $shipping_amount;
			
			$response ['From_Email_Address'] = $order->getCustomerEmail();
			$response ['To_Email_Address'] = '';
			$response ['Transaction_ID'] = $order_id;
			$response ['Counterparty_Status'] = '';
			$response ['Address_Status'] = '';
			$response ['Item_Title'] = 'Shopping Cart';
			$response ['Item_ID'] = 0; // Set to 0 for main Order Transaction row
			$response ['Shipping_and_Handling_Amount'] = ($shipping_amount > 0) ? $shipping_amount : 0.00;
			$response ['Insurance_Amount'] = '';
			
			$discount_amount = $order->getBaseDiscountAmount();
			$response ['Discount'] = ( $discount_amount > 0 ) ? round ( $discount_amount, 2 ) : 0.00;
			
			$order_tax = $order->getBaseShippingTaxAmount();
			$response ['Sales_Tax'] = ( $order_tax > 0) ? round ($order_tax, 2 ) : 0.00;
			
			$response ['Option_1_Name'] = '';
			$response ['Option_1_Value'] = '';
			$response ['Option_2_Name'] = '';
			$response ['Option_2_Value'] = '';
			
			$response ['Auction_Site'] = '';
			$response ['Buyer_ID'] = '';
			$response ['Item_URL'] = '';
			$response ['Closing_Date'] = '';
			$response ['Escrow_ID'] = '';
			$response ['Invoice_ID'] = '';
			$response ['Reference_Txn_ID'] = '';
			$response ['Invoice_Number'] = '';
			$response ['Custom_Number'] = '';
			

			$response ['Quantity'] = (int) $order->getTotalQtyOrdered();
			$response ['Receipt_ID'] = '';
			
			$response ['Balance'] = '';
			$response ['Note'] = $order->getCustomerNote();
			
			$address_info = $this->getOrderShippingInfo($order);
			
			$response ['Address_Line_1'] = $address_info['Address_Line_1'];
			$response ['Address_Line_2'] = $address_info['Address_Line_2'];
			$response ['Town_City'] = $address_info['Town_City'];
			$response ['State_Province'] = $address_info['State_Province'];
			$response ['Zip_Postal_Code'] = $address_info['Zip_Postal_Code'];
			$response ['Country'] = $address_info['Country'];
			$response ['Contact_Phone_Number'] = $address_info['Contact_Phone_Number']; 
			$response ['Subscription_ID'] = '';
			
			$transactions [] = $response;
			$parent_ids = array();
			
			foreach($order->getItemsCollection() as $item) {
				
				$item_data = $item->getData();
				
				if($item_data['parent_item_id'] != '' && !isset($item_data['has_children'])) {
					continue;
				}
				
				
				$product_options = unserialize($item_data['product_options']);
				
		        $order_item = array ();
		        $order_item ['Type'] = 'Shopping Cart Item';

				$order_item ['Item_Title'] = $item_data['name'];
				$order_item ['Item_ID'] = $item_data['sku'];
		        
		        $order_item ['Gross'] = round ( $item_data['row_total'], 2 );
		        $order_item['Quantity'] = $item_data['qty_ordered'];
//		      /  $order_item['Net'] = $item_data['row_total'];
			
		        //TODO: Handle Variations
		        if(isset($product_options['attributes_info']) && count($product_options['attributes_info']) > 0)  {
					//$product_id = $product_options['product'];
		        	//$_product = Mage::getModel('catalog/product')->load($product_id);
			        //$attributes = $_product->getTypeInstance(true)->getConfigurableAttributesAsArray($_product);
			        $attributes = $product_options['attributes_info'];
			        
			        
			        if(count($attributes > 0)) {
			        	if(count($attributes <= 2)) {
			        		$order_item ['Option_1_Name'] = (isset($attributes[0]['label'])) ? $attributes[0]['label'] : '';
							$order_item ['Option_1_Value'] = (isset($attributes[0]['label'])) ? $attributes[0]['value'] : '';
							$order_item ['Option_2_Name'] = (isset($attributes[1]['label'])) ? $attributes[1]['label'] : '';
							$order_item ['Option_2_Value'] = (isset($attributes[1]['label'])) ? $attributes[1]['value'] : '';
			        	} else {
			        	   $option_1_value_str = '';
			        	   foreach ($attributes as $attribute) {
			        	   		$option_1_value_str .=   $attribute['label'] . ':'.$attribute['value'] . ', ';
			        	   }
			        	   $order_item ['Option_1_Name'] = '';
	                       $order_item ['Option_1_Value'] = rtrim($option_1_value_str, ',');	
			        	}
			        }
		        }
		        
		        $transactions [] = array_merge ( $response, $order_item );
		        
				if ($order_status == "refunded") {
					
					$modified_timestamp = $order->getUpdatedAtStoreDate()->getTimestamp();
					
					$response ['Date'] = date ( 'm/d/Y', ( int ) $modified_timestamp );
					$response ['Time'] = date ( 'h:i:s A', ( int ) $modified_timestamp );
					
					$response ['Type'] = 'Refund';
					$response ['Status'] = 'Completed';
					$response ['Gross'] = - $order_total;
					$response ['Net'] = - $order_total;
					$response ['Transaction_ID'] = $order_id . '_R';
					$response ['Reference_Txn_ID'] = $order_id;
					
					$transactions [] = $response;
				}
		    }
		}
		return $transactions;
	}
	
	function getOrderShippingInfo($order) {
		$shippingAddress = !$order->getIsVirtual() ? $order->getShippingAddress() : null;
	    $address_line1 = "";
	    $district = "";
	    // correct for District
	    if(strpos($shippingAddress->getData("street"), "\n")){
	        $tmp = explode("\n", $shippingAddress->getData("street"));
	        $district = $tmp[1];
	        $address_line1 = $tmp[0];
	    }
	    if($address_line1 == ""){
	        $address_line1 = $shippingAddress->getData("street");
	    }
	 
	    return array(
	         "Address_Line_2" =>  $shippingAddress ? $shippingAddress->getName() : '',
	         "Address_Line_1" =>   $shippingAddress ? $shippingAddress->getData("company") : '',
	         "shipping_street" =>    $shippingAddress ? $address_line1 : '',
	         "shipping_district" =>  $shippingAddress ? $district : '',
	         "Zip_Postal_Code" =>       $shippingAddress ? $shippingAddress->getData("postcode") : '',
	         "Town_City" =>  $shippingAddress ? $shippingAddress->getData("city") : '',
	         "State_Province" =>     $shippingAddress ? $shippingAddress->getRegionCode() : '',
	         "Country" =>   $shippingAddress ? $shippingAddress->getCountry() : '',
	        "Contact_Phone_Number" => $shippingAddress ? $shippingAddress->getData("telephone") : ''
	    );
	}
	
	function getOrderLineDetails($order) {
	    $lines = array();
	    foreach($order->getItemsCollection() as $prod)
	    {
	        $line = array();
	        $_product = Mage::getModel('catalog/product')->load($prod->getProductId());
	        $line['sku'] = $_product->getSku();
	        $line['order_quantity'] = (int)$prod->getQtyOrdered();
	        $lines[] = $line;
	    }
	    return $lines;
	}

}

?>