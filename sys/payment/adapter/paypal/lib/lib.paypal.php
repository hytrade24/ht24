<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 * [PHP-File] lib.paypal.php
 * - Created at:		11.12.2009
 * - Last update:		20.12.2009
 *
 * @author		Jens
 * @version		0.2
 * @package		paypal
 */

class PayPal {
	private $paypal_sig;
	private $paypal_user;
	private $paypal_pass;

	private $paypal_url;
	private $paypal_url_ipn;

	public	$paypal_currency;
	public	$paypal_token;

	public	$paypal_response;

	/**
	 * Creates an new instance for paypal payments
	 *
	 * @param 	string	$api_signature	Your Paypal API-Signature
	 * @param 	string	$api_user		Your Paypal user (E-Mail address)
	 * @param 	string	$api_pass		Your Paypal password
	 * @param 	string	$currency		The currency that is used for payments ("USD","EUR", ...)
	 * @param 	boolean	$sandbox		Set this to true if you want to use the sandbox-environment
	 * @return unknown_type
	 */
	function __construct($api_signature, $api_user, $api_pass, $currency = "EUR", $sandbox = false) {
		if ($sandbox) {
			$this->paypal_url = "api-3t.sandbox.paypal.com";
			$this->paypal_url_ipn = "www.sandbox.paypal.com";
		} else {
			$this->paypal_url = "api-3t.paypal.com";
			$this->paypal_url_ipn = "ipnpb.paypal.com";
		}
		$this->paypal_acc_mail = $to_mail;
		$this->paypal_sig = $api_signature;
		$this->paypal_user = $api_user;
		$this->paypal_pass = $api_pass;

		$this->paypal_currency = $currency;
	}

	/**
	 * Sends a curl request for the given url and returns the url-encoded result as array
	 *
	 * @param	string	$url	The URL to get
	 * @return	array|boolean	An array with the response or false if the request failed
	 */
	private function SendCurlRequest($url) {
		// Send curl request
        $c = curl_init();
				curl_setopt($c, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $url);
				curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 1);
				curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($c);
        curl_close($c);

        if ($response) {
        	return $this->DecodeHttpQuery($response);
        } else {
        	return false;
        }
	}

	/**
	 * Sends a curl POST for the given url and returns the url-encoded result as array
	 *
	 * @param	string	$url	The URL to get
	 * @return	array|boolean	An array with the response or false if the request failed
	 */
	private function SendCurlPost($url, $params = array()) {
		// Send curl request
		$c = curl_init();
		curl_setopt($c, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_POSTFIELDS, $params);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($c, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, array('Connection: Close'));
        $response = curl_exec($c);
        curl_close($c);

        if ($response !== false) {
        	return $response;
        } else {
        	return false;
        }
	}

	/**
	 * @param string	$query
	 * @return array
	 */
	private function DecodeHttpQuery($query) {
		$params = array();
		$parts = explode("&", $query);
		foreach ($parts as $index => $value) {
			$param = explode("=", $value);
			$params[urldecode($param[0])] = rawurldecode($param[1]);
		}
		return $params;
	}

	/**
	 * Gets the details for the ExpressCheckout matching to $token.
	 * The result is stored as an array in {@link $paypal_response} (is false if curl request failed)
	 *
	 * @link	https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_GetExpressCheckoutDetails Paypal API-Dokumentation
	 * @uses	PayPal::$paypal_response
	 * @uses	PayPal::SendCurlRequest()
	 *
	 * @param	string	$token	The Paypal token returned by the previously called SetExpressCheckout.
	 * @return	boolean			True if request was successful, false otherwise.
	 */
	public function GetExpressCheckoutDetails($token) {
		$usrreq = array(
				"VERSION"		=> "63.0",
				"METHOD"		=> "GetExpressCheckoutDetails",
				"SIGNATURE"		=> $this->paypal_sig,
				"USER"			=> $this->paypal_user,
				"PWD"			=> $this->paypal_pass,

				"TOKEN"			=> $token,
			);
		$url = "https://".$this->paypal_url."/nvp";
		$this->paypal_response = $this->DecodeHttpQuery( $this->SendCurlPost($url, http_build_query($usrreq)) );
		
		if ($this->paypal_response["ACK"] == "Success") {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Gets the current details to a recurring profile
	 *
	 * @link	https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_GetRecurringPaymentsProfileDetails	Paypal API-Documentation
	 * @uses	PayPal::$paypal_response
	 * @uses	PayPal::SendCurlRequest()
	 *
	 * @param	string	$profile_id		The Paypal Recurring-Profile ID
	 * @return	boolean					True if request was successful, false otherwise.
	 */
	public function GetRecurringProfileDetails($profile_id) {
		$usrreq = array(
				"VERSION"		=> "63.0",
				"METHOD"		=> "GetRecurringPaymentsProfileDetails",
				"SIGNATURE"		=> $this->paypal_sig,
				"USER"			=> $this->paypal_user,
				"PWD"			=> $this->paypal_pass,

				"PROFILEID"		=> $profile_id,
			);
		$url = "https://".$this->paypal_url."/nvp";
		$this->paypal_response = $this->DecodeHttpQuery( $this->SendCurlPost($url, http_build_query($usrreq)) );
		
		if ($this->paypal_response["ACK"] == "Success") {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Gets the status for a Paypal transaction
	 *
	 * @uses	PayPal::$paypal_response
	 * @uses	PayPal::SendCurlRequest()
	 *
	 * @param	array	$ar_transaction		The transaction array returend by {@link DoExpressCheckout()}
	 * @return	boolean						True if request was successful, false otherwise.
	 */
	public function GetTransactionStatus($ar_transaction) {
		$ar_transaction["cmd"] = "_notify-validate";
		$url = "https://".$this->paypal_url."/nvp";
		$this->paypal_response = $this->DecodeHttpQuery( $this->SendCurlPost($url, http_build_query($ar_transaction)) );
		
		if ($this->paypal_response["ACK"] == "Success") {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Gets the details for a Paypal transaction.
	 *
	 * @uses	PayPal::$paypal_response
	 * @uses	PayPal::SendCurlRequest()
	 *
	 * @param	string	$id_transaction
	 * @return	boolean
	 */
	public function GetTransactionDetails($id_transaction) {
		$payment = array(
				"VERSION"		=> "63.0",
				"METHOD"		=> "GetTransactionDetails",
				"SIGNATURE"		=> $this->paypal_sig,
				"USER"			=> $this->paypal_user,
				"PWD"			=> $this->paypal_pass,

				"TRANSACTIONID"	=> $id_transaction,
			);
		$url = "https://".$this->paypal_url."/nvp";
		$this->paypal_response = $this->DecodeHttpQuery( $this->SendCurlPost($url, http_build_query($payment)) );
		
		if ($this->paypal_response["ACK"] == "Success") {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sets up an ExpressCheckout for a single payment.
	 *
	 * @link	https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_SetExpressCheckout	Paypal API-Documentation
	 * @uses	PayPal::$paypal_response
	 * @uses	PayPal::SendCurlRequest()
	 *
	 * @param	float	$amount			The amount that has to be paid
	 * @param	string	$description	Description for what is paid
	 * @param	string	$return_url		URL that is called if the ExpressCheckout was Setup successfully
	 * @param	string	$cancel_url		URL that is called if the user canceled the payment
	 * @param	array	$items			List of Items to be sold/bought
	 *
	 * @return	boolean					True if request was successful, false otherwise.
	 */
	public function SetExpressCheckoutSingle($amount, $shipping, $description, $return_url, $cancel_url, $items = array(), $sellerId = false) {
		$payment = array(
				"VERSION"							=> "63.0",
				"METHOD"							=> "SetExpressCheckout",
				"SIGNATURE"							=> $this->paypal_sig,
				"USER"								=> $this->paypal_user,
				"PWD"								=> $this->paypal_pass,

				"PAYMENTREQUEST_0_PAYMENTACTION"	=> "Sale",
				"PAYMENTREQUEST_0_AMT"				=> round($amount, 2),
				"PAYMENTREQUEST_0_SHIPPINGAMT"		=> round($shipping, 2),
				"PAYMENTREQUEST_0_CURRENCYCODE"		=> $this->paypal_currency,
				"RETURNURL"							=> $return_url,
				"CANCELURL"							=> $cancel_url,
				"NOSHIPPING"						=> 0
			);
		if ($sellerId !== false) {
			$payment["PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID"] = $sellerId;
		}

		$total = 0;
		$total_tax = 0;

		if (!empty($items)) {
			foreach ($items as $index => $item_data) {
				$payment["L_PAYMENTREQUEST_0_NAME".$index] 	= stdHtmlspecialchars(substr($item_data["TITLE"], 0, 200));
				if(isset($item_data["DESCRIPTION"])) { $payment["L_PAYMENTREQUEST_0_DESC".$index] 	= stdHtmlspecialchars(substr($item_data["DESCRIPTION"], 0, 200)); }
				$payment["L_PAYMENTREQUEST_0_AMT".$index] 	= round($item_data["PRODUCT_BRUTTO"], 2);
                if(isset($item_data["FK_PRODUCT"])) { $payment["L_PAYMENTREQUEST_0_NUMBER".$index] = $item_data["FK_PRODUCT"]; }
				$payment["L_PAYMENTREQUEST_0_QTY".$index] 	= ($item_data["COUNT"] > 0 ? $item_data["COUNT"] : 1);
				if ($item_data["PRODUCT_BRUTTO"] != $item_data["PRODUCT_NETTO"]) {
				#	$payment["L_PAYMENTREQUEST_0_TAXAMT".$index] = round($item_data["PRODUCT_BRUTTO"] - $item_data["PRODUCT_NETTO"], 2);
					$total_tax += ($item_data["PRODUCT_BRUTTO"] - $item_data["PRODUCT_NETTO"]) * $payment["L_PAYMENTREQUEST_0_QTY".$index];
				}
				$total += $item_data["PRODUCT_BRUTTO"] * $payment["L_PAYMENTREQUEST_0_QTY".$index];
			}
			$payment["PAYMENTREQUEST_0_ITEMAMT"] = round($total, 2);
			//$payment["PAYMENTREQUEST_0_TAXAMT"] = round($total_tax, 2);
		}

		#var_dump($payment);
		#eventlog("warning", "DEBUG - PayPal SetExpressCheckoutSingle parameters:", var_export($payment, true));

		//die(var_dump($payment));
		#$url = "https://".$this->paypal_url."/nvp?".http_build_query($payment);
		$url = "https://".$this->paypal_url."/nvp";
		$this->paypal_response = $this->DecodeHttpQuery( $this->SendCurlPost($url, http_build_query($payment)) );

		if (($this->paypal_response["ACK"] == "Success") || ($this->paypal_response["ACK"] == "SuccessWithWarning")) {
			$this->paypal_token = $this->paypal_response["TOKEN"];
			return true;
		} else {
			//die(ht(dump($payment)));
			eventlog("error", "PayPal SetExpressCheckoutSingle failed:", var_export($this->paypal_response, true));
			return false;
		}
	}


	/**
	 * Sends a single payment defined earlier by {@link SetExpressCheckoutSingle} or {@link SetExpressCheckoutRecurring}.
	 *
	 * @link	https://cms.paypal.com/us/cgi-bin/?&cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_DoExpressCheckoutPayment	Paypal API-Documentation
	 * @uses	PayPal::$paypal_response
	 * @uses	PayPal::SendCurlRequest()
	 *
	 * @param	string	$token		The Paypal token returned by the previously called SetExpressCheckout.
	 * @param	float	$amount		The amount that has to be paid
	 * @param	string	$payer		The UserID of the buyer
	 * @return	boolean				True if request was successful, false otherwise.
	 */
	public function DoExpressCheckout($token, $amount, $payer, $notifyUrl = false, $sellerId = false) {
		$this->paypal_token = $token;

		$usrreq = array(
				"VERSION"							=> "63.0",
				"METHOD"							=> "DoExpressCheckoutPayment",
				"SIGNATURE"							=> $this->paypal_sig,
				"USER"								=> $this->paypal_user,
				"PWD"								=> $this->paypal_pass,

				"TOKEN"								=> $this->paypal_token,
				"PAYERID"							=> $payer,

				"PAYMENTREQUEST_0_PAYMENTACTION"	=> "Sale",
				"PAYMENTREQUEST_0_CURRENCYCODE"		=> $this->paypal_currency,
				"PAYMENTREQUEST_0_AMT"				=> round($amount, 2),
			);
		if ($notifyUrl !== false) {
			$usrreq["PAYMENTREQUEST_0_NOTIFYURL"] = $notifyUrl;
		}
		if ($sellerId !== false) {
			$usrreq["PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID"] = $sellerId;
		}
		#eventlog("info", "DEBUG - PayPal DoExpressCheckout parameters:", var_export($usrreq, true));
		$url = "https://".$this->paypal_url."/nvp";
		$this->paypal_response = $this->DecodeHttpQuery( $this->SendCurlPost($url, http_build_query($usrreq)) );
		
		if (($this->paypal_response["ACK"] == "Success") || ($this->paypal_response["ACK"] == "SuccessWithWarning")) {
			return true;
		} else {
			eventlog("error", "PayPal DoExpressCheckout failed:", var_export($this->paypal_response, true));
			return false;
		}
	}



    public function setPaypalCurrency($paypal_currency) {
        $this->paypal_currency = $paypal_currency;
    }

    public function getPaypalCurrency() {
        return $this->paypal_currency;
    }

    public function setPaypalResponse($paypal_response) {
        $this->paypal_response = $paypal_response;
    }

    public function getPaypalResponse() {
        return $this->paypal_response;
    }

    public function setPaypalToken($paypal_token) {
        $this->paypal_token = $paypal_token;
    }

    public function getPaypalToken() {
        return $this->paypal_token;
    }

    public function handleIPN() {
    	global $db;
		// read the post from PayPal system and add 'cmd'
		$ar_params = $_POST;
		$ar_params["cmd"] = "_notify-validate";
		$result = $this->SendCurlPost("https://".$this->paypal_url_ipn."/cgi-bin/webscr", $ar_params);

		// assign posted variables to local variables
		$item_name = $_POST['item_name'];
		$business = $_POST['business'];
		$item_number = $_POST['item_number'];
		$payment_status = $_POST['payment_status'];
		$mc_gross = $_POST['mc_gross'];
		$payment_currency = $_POST['mc_currency'];
		$txn_id = $_POST['txn_id'];
		$receiver_email = $_POST['receiver_email'];
		$receiver_id = $_POST['receiver_id'];
		$quantity = $_POST['quantity'];
		$num_cart_items = $_POST['num_cart_items'];
		$payment_date = $_POST['payment_date'];
		$first_name = $_POST['first_name'];
		$last_name = $_POST['last_name'];
		$payment_type = $_POST['payment_type'];
		$payment_status = $_POST['payment_status'];
		$payment_gross = $_POST['payment_gross'];
		$payment_fee = $_POST['payment_fee'];
		$settle_amount = $_POST['settle_amount'];
		$memo = $_POST['memo'];
		$payer_email = $_POST['payer_email'];
		$txn_type = $_POST['txn_type'];
		$payer_status = $_POST['payer_status'];
		$address_street = $_POST['address_street'];
		$address_city = $_POST['address_city'];
		$address_state = $_POST['address_state'];
		$address_zip = $_POST['address_zip'];
		$address_country = $_POST['address_country'];
		$address_status = $_POST['address_status'];
		$item_number = $_POST['item_number'];
		$tax = $_POST['tax'];
		$option_name1 = $_POST['option_name1'];
		$option_selection1 = $_POST['option_selection1'];
		$option_name2 = $_POST['option_name2'];
		$option_selection2 = $_POST['option_selection2'];
		$for_auction = $_POST['for_auction'];
		$invoice = $_POST['invoice'];
		$custom = $_POST['custom'];
		$notify_version = $_POST['notify_version'];
		$verify_sign = $_POST['verify_sign'];
		$payer_business_name = $_POST['payer_business_name'];
		$payer_id =$_POST['payer_id'];
		$mc_currency = $_POST['mc_currency'];
		$mc_fee = $_POST['mc_fee'];
		$exchange_rate = $_POST['exchange_rate'];
		$settle_currency  = $_POST['settle_currency'];
		$parent_txn_id  = $_POST['parent_txn_id'];
		$pending_reason = $_POST['pending_reason'];
		$reason_code = $_POST['reason_code'];

		// subscription specific vars
		$subscr_id = $_POST['subscr_id'];
		$subscr_date = $_POST['subscr_date'];
		$subscr_effective  = $_POST['subscr_effective'];
		$period1 = $_POST['period1'];
		$period2 = $_POST['period2'];
		$period3 = $_POST['period3'];
		$amount1 = $_POST['amount1'];
		$amount2 = $_POST['amount2'];
		$amount3 = $_POST['amount3'];
		$mc_amount1 = $_POST['mc_amount1'];
		$mc_amount2 = $_POST['mc_amount2'];
		$mc_amount3 = $_POST['mcamount3'];
		$recurring = $_POST['recurring'];
		$reattempt = $_POST['reattempt'];
		$retry_at = $_POST['retry_at'];
		$recur_times = $_POST['recur_times'];
		$username = $_POST['username'];
		$password = $_POST['password'];

		// auction specific vars
		$for_auction = $_POST['for_auction'];
		$auction_closing_date  = $_POST['auction_closing_date'];
		$auction_multi_item  = $_POST['auction_multi_item'];
		$auction_buyer_id  = $_POST['auction_buyer_id'];

		// Query result
		if ($result === false) {
			// HTTP ERROR
			eventlog("error", "PayPal IPN error: Failed to validate transaction! (HTTP ERROR)");
		} else {
			if (strcmp($result, "VERIFIED") == 0) {
				$check_transaction = $db->fetch_atom("SELECT count(*) FROM `payment_paypal_payment_info` WHERE txnid='".mysql_escape_string($txn_id)."'");
				if ($check_transaction > 0) {
					// Duplicate transaction id
					eventlog("error", "PayPal IPN - Duplicate transaction id!", $txn_id);
					return false;
				} else {
					// New transaction
					$ar_payment_info = array(
						"paymentstatus" => $payment_status,
						"buyer_email" => $payer_email,
						"firstname" => $first_name,
						"lastname" => $last_name,
						"street" => $address_street,
						"city" => $address_city,
						"state" => $address_state,
						"zipcode" => $address_zip,
						"country" => $address_country,
						"mc_gross" => $mc_gross,
						"mc_fee" => $mc_fee,
						"memo" => $memo,
						"paymenttype" => $payment_type,
						"paymentdate" => $payment_date,
						"txnid" => $txn_id,
						"pendingreason" => $pending_reason,
						"reasoncode" => $reason_code,
						"tax" => $tax,
						"datecreation" => $fecha
					);
					$id_payment_info = $db->update("payment_paypal_payment_info", $ar_payment_info);
					if (!$id_payment_info) {
						eventlog("error", "Cart - paypal_payment_info, update failed!", var_export($ar_payment_info, true));
						return false;
					}

					if ($payment_status == "Completed") {
						// Payment confirmed
						require_once $ab_path . 'sys/lib.billing.invoice.php';
						$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
						$invoice = $billingInvoiceManagement->fetchByTransactionId($txn_id);
						if ($invoice !== false) {
							require_once $ab_path . 'sys/lib.billing.invoice.transaction.php';
							$billingInvoiceTransactionManagement = BillingInvoiceTransactionManagement::getInstance($db);

							$billingInvoiceTransactionManagement->createInvoiceTransaction(array(
									'FK_BILLING_INVOICE' => $invoice["ID_BILLING_INVOICE"],
									'TYPE' => BillingInvoiceTransactionManagement::TYPE_DEFAULT,
									'DESCRIPTION' => "PayPal",
									'TRANSACTION_ID' => $txn_id,
									'PRICE' => $mc_gross
							));
						}
					}

					if ($txn_type == "cart") {
						for ($i = 1; $i <= $num_cart_items; $i++) {
							$ar_cart_info = array(
								"txnid" => $txn_id,
								"itemnumber" => $_POST["item_number" . $i],
								"itemname" => $_POST["item_name" . $i],
								"os0" => $_POST["option_name1_" . $i],
								"on0" => $_POST["option_selection1_" . $i],
								"os1" => $_POST["option_name2_" . $i],
								"on1" => $_POST["option_selection2_" . $i],
								"quantity" => $_POST["quantity" . $i],
								"invoice" => $invoice,
								"custom" => $custom
							);
							$id_cart_info = $db->update("payment_paypal_cart_info", $ar_cart_info);
							if (!$id_cart_info) {
								eventlog("error", "Cart - paypal_cart_info, update failed!", var_export($ar_cart_info, true));
								return false;
							}
						}
					}
				}

				//subscription handling branch
				if ($txn_type == "subscr_signup" || $txn_type == "subscr_payment") {
					$ar_subscription_info = array(
						"subscr_id" => $subscr_id,
						"sub_event" => $txn_type,
						"subscr_date" => $subscr_date,
						"subscr_effective" => $subscr_effective,
						"period1" => $period1,
						"period2" => $period2,
						"period3" => $period3,
						"amount1" => $amount1,
						"amount2" => $amount2,
						"amount3" => $amount3,
						"mc_amount1" => $mc_amount1,
						"mc_amount2" => $mc_amount2,
						"mc_amount3" => $mc_amount3,
						"recurring" => $recurring,
						"reattempt" => $reattempt,
						"retry_at" => $retry_at,
						"recur_times" => $recur_times,
						"username" => $username,
						"password" => $password,
						"payment_txn_id" => $txn_id,
						"subscriber_emailaddress" => $payer_email,
						"datecreation" => $fecha
					);
					$id_subscription_info = $db->update("payment_paypal_subscription_info", $ar_subscription_info);
					if (!$id_subscription_info) {
						eventlog("error", "Cart - paypal_subscription_info, update failed!", var_export($ar_subscription_info, true));
						return false;
					}
				}
			}

		}
		return true;
    } // handleIPN

}
?>