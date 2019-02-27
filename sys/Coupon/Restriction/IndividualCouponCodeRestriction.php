<?php

/**
 * Class Coupon_Restriction_IndividualCouponCodeRestriction
 *
 * Beschränkt die Nutzung eines Gutscheins auf die einmalige Verwendung eines Gutschein Codes. Wird dieser verwendet
 * kann er nicht erneut verwendet werden.
 *
 */
class Coupon_Restriction_IndividualCouponCodeRestriction implements Coupon_Restriction_CouponRestrictionInterface {


	function __construct($restrictionValue = null, $restrictionConfig = null) {
	}

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.restriction.class.individualcode.name', null, array(), 'Indivudeller Code');
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
		global $db;

		$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);

		$usageForCoupon = $couponUsageManagement->fetchUsageForCouponAndCode($coupon, $code);

		if($usageForCoupon == null) {
			return true;
		}

		return false;
	}

	public function reCheckOnUsage() {
		return false;
	}
}