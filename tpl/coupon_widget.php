<?php

// Gutschein Widget

$couponTargetType = ($tpl_content->vars['COUPON_WIDGET_TARGET_TYPE'] ? $tpl_content->vars['COUPON_WIDGET_TARGET_TYPE'] : null);
$couponTargets = ($tpl_content->vars['COUPON_WIDGET_TARGETS_JSON'] ? json_decode($tpl_content->vars['COUPON_WIDGET_TARGETS_JSON']) : null);


$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
$availableCoupons = $couponUsageManagement->fetchCompatibleAndAvailableCouponUsages($uid, $couponTargetType, $couponTargets);
$tpl_content->addlist('coupon_available', $availableCoupons, 'tpl/'.$s_lang.'/coupon_widget.row_available.htm');