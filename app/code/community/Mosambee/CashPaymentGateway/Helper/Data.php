<?php

class Mosambee_CashPaymentGateway_Helper_Data extends Mage_Core_Helper_Abstract
{	
	// getHashSignature method to genrate signature
	function getHashSignature($message,$secret){
		return hash_hmac('sha512', $message,$secret);
	}   
	
    // validateSignature method to validate signature
	function validateSignature($m_Sign,$g_Sign){
		return hash_equals($m_Sign,$g_Sign);
	}

}