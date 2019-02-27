<?php

/**
 * Class Coupon_Restriction_DateRestriction
 *
 * Beschränkt die Nutzung eines Gutscheins auf einen vorgegebenen Zeitraum, innerhalb dessen der Gutschein gültig ist
 *
 */
class Coupon_Restriction_DateRestriction extends Coupon_Restriction_AbstractRestriction implements Coupon_Restriction_CouponRestrictionInterface {

	protected $fromDate = null;
	protected $toDate = null;

	function __construct($restrictionValue = null, $restrictionConfig = array()) {
		if(isset($restrictionConfig['VALID_FROM']) && !empty($restrictionConfig['VALID_FROM'])) {
			$this->fromDate = new DateTime($restrictionConfig['VALID_FROM']);
		}

		if(isset($restrictionConfig['VALID_TO']) && !empty($restrictionConfig['VALID_TO'])) {
			$this->toDate = new DateTime($restrictionConfig['VALID_TO']);
		}
	}

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.restriction.class.daterestriction.name', null, array(), 'An Zeitraum gebunden');
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

		$now = new DateTime();


		if($this->fromDate != null && ($this->fromDate > $now)) {
			return false;
		}

		if($this->toDate != null && ($this->toDate < $now)) {
			return false;
		}

		return true;
	}

	public function reCheckOnUsage() {
		return true;
	}
}