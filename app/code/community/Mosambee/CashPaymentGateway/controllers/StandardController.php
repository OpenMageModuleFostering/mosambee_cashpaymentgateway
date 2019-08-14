<?php
 class Mosambee_CashPaymentGateway_StandardController extends Mage_Core_Controller_Front_Action {    
    // The redirectAction is triggered when someone places an order
	public function redirectAction() { 
		// Retrieve order
		$order = new Mage_Sales_Model_Order();
		$customcard['order_id'] = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$order->loadByIncrementId( $customcard['order_id']);
		
		// Get payment gateway config data
		$customcard['action'] = Mage::getStoreConfig('payment/cashpaymentgateway_checkout/submit_url');
		$customcard['merchant_id'] = Mage::getStoreConfig('payment/cashpaymentgateway_checkout/merchant_id');
		$customcard['merchant_password'] = Mage::getStoreConfig('payment/cashpaymentgateway_checkout/merchant_password');
		
		$customcard['total_amount'] = round( $order->base_grand_total, 2 );		
		$customcard['redirect_url'] = Mage::getBaseUrl().'customcard/standard/response';
        // signature genration 
		$customcard['request_signature'] = Mage::helper('cashpaymentgateway')->getHashSignature($customcard['merchant_id'],$customcard['merchant_password']);

        // Retrieve order details
		$billingAddress = $order->getBillingAddress();
		$billingData = $billingAddress->getData();
		$shippingAddress = $order->getShippingAddress();
		if ($shippingAddress)
			$shippingData = $shippingAddress->getData();
		
		$customcard['billing_cust_name'] = $billingData['firstname'] . ' ' . $billingData['lastname'];
		$customcard['billing_cust_address'] = $billingAddress->street;
		$customcard['billing_cust_state'] = $billingAddress->region;
		$customcard['billing_cust_country'] = Mage::getModel('directory/country')->load($billingAddress->country_id)->getName();
		$customcard['billing_city'] = $billingAddress->city;
		$customcard['billing_zip'] = $billingAddress->postcode;
		$customcard['billing_cust_tel'] = $billingAddress->telephone;
		$customcard['billing_cust_email'] = $order->customer_email;
		
		if ($shippingAddress){
			$customcard['delivery_cust_name'] = $shippingData['firstname'] . ' ' . $shippingData['lastname'];
			$customcard['delivery_cust_address'] = $shippingAddress->street;
			$customcard['delivery_cust_state'] = $shippingAddress->region;
			$customcard['delivery_cust_country'] = Mage::getModel('directory/country')->load($shippingAddress->country_id)->getName();
			$customcard['delivery_city'] = $shippingAddress->city;
			$customcard['delivery_cust_tel'] = $shippingAddress->telephone;	
			$customcard['delivery_zip'] = $shippingAddress->postcode;
		}
		
		else {
			$customcard['delivery_cust_name'] = '';
			$customcard['delivery_cust_address'] = '';
			$customcard['delivery_cust_state'] = '';
			$customcard['delivery_cust_country'] = '';
			$customcard['delivery_cust_tel'] = '';
			$customcard['delivery_city'] = '';
			$customcard['delivery_zip'] = '';
		}
							
		// Add data to registry so it's accessible in the view file
		Mage::register('customcard', $customcard);
		
		// Render layout
		$this->loadLayout();
		$block = $this->getLayout()->createBlock('Mage_Core_Block_Template','customcard', array('template' => 'cashpaymentgateway/redirect.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
		$this->renderLayout();
	}
	// The response action is triggered when your gateway sends back a response after processing the customer's payment
	public function responseAction() {
		if($this->getRequest()->isPost()) {			  
		  if(isset($_POST['merchant_id'])&&isset($_POST['result'])&&isset($_POST['order_id'])&&isset($_POST['gateway_signature'])&&$_POST['result']!= null) {	
		        // gateway post data
				$validated = $_POST['result']; 
				$orderId = $_POST['order_id']; 
		        $g_Sign = $_POST['gateway_signature']; 
				$merchant_id = $_POST['merchant_id'];							
                $merchant_password = Mage::getStoreConfig('payment/cashpaymentgateway_checkout/merchant_password');			    
			    // signature to validate gateway response
			    $s_Sign = Mage::helper('cashpaymentgateway')->getHashSignature($merchant_id.$orderId,$merchant_password);			    
			    // validation of signature
				if(Mage::helper('cashpaymentgateway')->validateSignature($s_Sign,$g_Sign)){				    
					if($validated==="success"){
						// Payment was successful, so update the order's state, send order email and move to the success page
						$order = Mage::getModel('sales/order');
						$order->loadByIncrementId($orderId);
						$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Mosambee.Cash Receive Wallet Payment Successfully.');	               		    
               		    // exception handling for order email				
						try{
							$order->sendNewOrderEmail();
							$order->setEmailSent(true);
						}catch(Exception $e){
							Mage::logException($e);
						}											
						// check for invoice
						if(!$order->canInvoice()) {
				            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
				        }
				        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
				        // check for order quantity
				        if(!$invoice->getTotalQty()) {
				            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
				        }	
				        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
				        $invoice->addComment('Invoice genrated automatically by Mosambee.Cash');
				        $invoice->register();
				        $transactionSave = Mage::getModel('core/resource_transaction')
				                           ->addObject($invoice)
				                           ->addObject($order);
				        $transactionSave->save();
				        // Exception Handling for invoice email
				        try {
				            $invoice->sendEmail(true);
				        } catch (Exception $e) {
				            Mage::logException($e);
				            Mage::getSingleton('core/session')->addError($this->__('Unable to send the invoice email.'));
				        }					     
                        // save the order
                        $order->save();
						Mage::getSingleton('checkout/session')->unsQuoteId();
						Mage::getSingleton('core/session')->addSuccess('Thank You for choosing Mosambee.Cash wallet payment!');
						Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
					}
					// if payment not success cancel order
					else{					   	
					   	Mage::getSingleton('core/session')->addError('Mosambee.Cash has declined the wallet payment!');
					   	$this->cancelAction();						
					}
		        }
		        // cancel order if signature not match
				else{		            
		           Mage::getSingleton('core/session')->addError('Something goes wrong, Please try again!');
		           $this->cancelAction();
		        }				
		  }
		  else{
		  	Mage::getSingleton('core/session')->addError('Gateway request parameter error, Please try again!'); 
		  	$this->cancelAction();
		  }
		}
		// if response is not post
		else{		  
		  Mage::getSingleton('core/session')->addError('Gateway request method not validated, Please try again!'); 
		  $this->cancelAction();		  
		}
	}		
	// The cancel action is triggered when an order is to be cancelled
	public function cancelAction() {
        $session = Mage::getSingleton('checkout/session');
        $cart = Mage::getSingleton('checkout/cart');
        
        if($session->getLastRealOrderId()) {
           $incrementId = $session->getLastRealOrderId();
           if (empty($incrementId)) {
                $this->_redirect('checkout/cart');
                return;
           }
           $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
           $session->getQuote()->setIsActive(false)->save();
           $session->clear();
           try{
             $order->setActionFlag(Mage_Sales_Model_Order::ACTION_FLAG_CANCEL, true);
             $order->cancel()->save();
           }catch (Mage_Core_Exception $e) {
             Mage::logException($e);
           }           
           $items = $order->getItemsCollection();
           foreach ($items as $item) {
             try {
                $cart->addOrderItem($item);
             }catch (Mage_Core_Exception $e) {
                $session->addError($this->__($e->getMessage()));
                Mage::logException($e);
                continue;
             }
           }
           $cart->save();
        }
        $this->_redirect('checkout/cart');
    }
}