<?php

interface Coupon_Type_CouponTypeInterface {

	public function getName();
	public function onActivation($coupon, $couponUsage);
	public function onUsage($couponUsage, $billingItem);
	public function getMessageOnActivation();

	public function isTypeCompatibleToTarget($targetType, $target);
	public function getTargetType();
	public function getTargets();

	public function getTypeConfiguration();
}