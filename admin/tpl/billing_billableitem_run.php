<?php

require_once $ab_path . 'sys/lib.billing.service.automaticbilling.php';

$billingServiceAutomaticBillingManagement = BillingServiceAutomaticBillingManagement::getInstance($db);

if(isset($_GET['action']) && $_GET['action'] == 'run') {
    $result = $billingServiceAutomaticBillingManagement->runAll();

    $tpl_content->addvar('RESULT', implode("\n", $result));
}