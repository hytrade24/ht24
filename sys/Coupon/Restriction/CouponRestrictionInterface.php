<?php

interface Coupon_Restriction_CouponRestrictionInterface {

	public function getName();
	public function getDescription();
	public function isPermitted($coupon, $code);

	public function reCheckOnUsage();
}