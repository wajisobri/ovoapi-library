<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Example_controller extends CI_Controller {

	public function index()
	{
		/**
		 * How to Login?
		 *
		 * Step 1:
		 * echo $this->ovo->login2FA('YOUR_PHONE_NUMBER');
		 * In this step you will get a ref ID
		 * 
		 * Step 2:
		 * echo $this->ovo->login2FAverify('YOUR_REF_ID', 'YOUR_OTP_CODE', 'YOUR_PHONE_NUMBER');
		 * In this step you will get an update Access Token
		 * 
		 * Step 3:
		 * echo $this->ovo->loginSecurityCode('YOUR_PIN_OR_SECURITY_CODE', 'YOUR_UPDATE_ACCESS_TOKEN');
		 * In this step you will get an Access Token
		 * 
		 */

		/**
		 * How to Use the Function?
		 *
		 * Step 1: Initialize OVO API Wrapper library using the standard codeigniter syntax
		 * $this->load->library('ovo', array('token' => 'YOUR_ACCESS_TOKEN'));
		 * 
		 * Step 2: To use available methods in the library 
		 * echo $this->ovo->getTransactionHistory();
		 * 
		 */
	}
}
