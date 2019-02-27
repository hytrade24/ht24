<?php

/**
 * Class Coupon_Restriction_UsergroupRestriction
 *
 * Beschränkt die Nutzung eines Gutscheins auf eine oder mehrere Benutzergruppen
 *
 */
class Coupon_Restriction_UsergroupRestriction implements Coupon_Restriction_CouponRestrictionInterface {

	protected $allowedUsergroups = null;

	function __construct($restrictionValue = null, $restrictionConfig = array()) {
		if(isset($restrictionConfig['USERGROUPS_ALLOWED']) && is_array($restrictionConfig['USERGROUPS_ALLOWED'])) {
			$this->allowedUsergroups = $restrictionConfig['USERGROUPS_ALLOWED'];
		}

	}

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.restriction.class.usergroup.name', null, array(), 'Auf Benutzergruppen beschränkt');
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
		global $db, $user;

		if(count($this->allowedUsergroups) > 0 && !in_array($user['FK_USERGROUP'], $this->allowedUsergroups)) {
			return false;
		}


		return true;
	}

	public function reCheckOnUsage() {
		return false;
	}

}