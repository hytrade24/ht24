<?php

require_once $ab_path . "sys/lib.payment.pincode.email.php";

$payment_pincode_email = new PaymentPinCodeEmail();
$payment_pincode_email->checkAndSendPossibleEmails();