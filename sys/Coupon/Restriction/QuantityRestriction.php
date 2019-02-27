<?php

/**
 * Class Coupon_Restriction_QuantityRestriction
 *
 * Beschränkt die Nutzung eines Gutscheins auf die x malige Verwendung limitiert
 *
 */
class Coupon_Restriction_QuantityRestriction implements Coupon_Restriction_CouponRestrictionInterface {

	protected $limitQuantity = 1;

	function __construct($restrictionValue = null, $restrictionConfig = null) {
		if(isset($restrictionConfig['LIMIT_QUANTITY']) && (int)$restrictionConfig['LIMIT_QUANTITY'] > 0) {
			$this->limitQuantity = (int)$restrictionConfig['LIMIT_QUANTITY'];
		}
	}

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.restriction.class.quantity.name', null, array(), 'Anzahl der Verwendung limitiert');
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

		$usageForCoupon = $couponUsageManagement->fetchUsageForCoupon($coupon);
		if($usageForCoupon == null && count($usageForCoupon) < $this->limitQuantity) {
			return true;
		}

		return false;
	}

	public function reCheckOnUsage() {
		return false;
	}


}