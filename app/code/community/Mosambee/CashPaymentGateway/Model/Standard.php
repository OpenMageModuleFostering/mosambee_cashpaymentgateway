<?php

class Mosambee_CashPaymentGateway_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'cashpaymentgateway_checkout';    
    protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = false;
	protected $_canUseForMultishipping  = false;
	
	// return order place redirect url (string)
	public function getOrderPlaceRedirectUrl()
	{ 
		//when you click on place order you will be redirected on this url
		return Mage::getUrl('customcard/standard/redirect', array('_secure' => true));
	}

}
