<?php
global $db, $nar_systemsettings, $debug_echo, $ab_path;

require_once $ab_path . 'sys/lib.billing.service.automaticbilling.php';
require_once $ab_path . 'sys/lib.billing.service.automaticdunning.php';


// automatischer Rechnungslauf
$billingServiceAutomaticBillingManagement = BillingServiceAutomaticBillingManagement::getInstance($db);
$tmp = $nar_systemsettings['MARKTPLATZ']['INVOICE_DAYS_AUTOMATIC_BILLING'];
$daysInMonthToRun = explode(",", $tmp);

if(is_array($daysInMonthToRun) && count($daysInMonthToRun) > 0) {
    foreach($daysInMonthToRun as $key => $day) {
        $daysInMonthToRun[$key] = (int)$day;
    }

    $dayInMoth = (int)date("j");
    $inverseDayInMoth = (int)date("j") - ((int)date("t") + 1);

    if(in_array($dayInMoth, $daysInMonthToRun) || in_array($inverseDayInMoth, $daysInMonthToRun)) {
        $billingServiceAutomaticBillingManagement->runAll();
    }
}


// automatischer Mahnlauf
$billingServiceAutomaticDunningManagement = BillingServiceAutomaticDunningManagement::getInstance($db);
$billingServiceAutomaticDunningManagement->runAllOverdue();
$billingServiceAutomaticDunningManagement->runAll();