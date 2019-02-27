<?php

/**
 * Class Coupon_Restriction_OnlyOneCouponPerUserRestriction
 *
 * Beschränkt die Nutzung eines Gutscheins auf einen pro User
 *
 */
class Coupon_Restriction_OnlyOneCouponPerUserRestriction implements Coupon_Restriction_CouponRestrictionInterface {


	function __construct($restrictionValue = null, $restrictionConfig = null) {
	}

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.restriction.class.onlyoneperuser.name', null, array(), 'Auf einen Gutschein pro User limitiert');
	}

	public function getDescription() {
		return '';
	}


	/**
	 * @param $coupon
	 *
	 * @return bool
	 */
	public function isPermitted($coupon, $code) {
		global $db, $uid;


		if($uid == null) {
			return true;
		}
		
		if ($coupon['USAGE_STATE'] == Coupon_CouponUsageManagement::USAGE_STATE_ACTIVATED) {
			return true;
		}

		$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
		$usageForCoupon = $couponUsageManagement->fetchUsageForCouponAndUser($coupon, $uid);

		if($usageForCoupon == null) {
			return true;
		}

		return false;
	}


	public function reCheckOnUsage() {
		return false;
	}
}