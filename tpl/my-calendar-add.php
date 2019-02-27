<?php

$id_ad_sold = (int)$ar_params[1];
if ($id_ad_sold) {
    require_once $ab_path.'sys/lib.ad_order.php';
    $adOrderManagement = AdOrderManagement::getInstance($db);
    $ar_ad_sold = $adOrderManagement->fetchItemById($id_ad_sold);
    $ar_availability = ($ar_ad_sold["SER_AVAILABILITY"] == null ? false : unserialize($ar_ad_sold["SER_AVAILABILITY"]));
    $ar_ad_sold['AVAILABILITY'] = ($ar_availability !== false);
    $ar_ad_sold['AVAILABILITY_DATE_FROM'] = (is_array($ar_availability) ? $ar_availability['DATE_FROM'] : false);
    $ar_ad_sold['AVAILABILITY_TIME_FROM'] = (is_array($ar_availability) ? $ar_availability['TIME_FROM'] : false);
    $ar_ad_sold['AVAILABILITY_DATE_TO'] = (is_array($ar_availability) ? $ar_availability['DATE_TO'] : false);
    $ar_ad_sold['USER_NAME'] = $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$ar_ad_sold["FK_USER"]);

    $tpl_content->addvars($ar_ad_sold);
} else {
    $tpl_content->addvar("not_found", 1);
}

?>