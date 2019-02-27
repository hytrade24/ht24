<?php

class Coupon_Type_RegisterMembershipCouponType extends Coupon_Type_AbstractCouponType {

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.type.register.membership.name', null, array(), 'Spezielle Mitgliedschaft');
	}


	public function onActivation($coupon, $couponUsage) {
		global $db, $ab_path;

		$defaultDescription = Translation::readTranslation('marketplace', 'coupon.type.register.membership.descripion', null, array('COUPON_CODE' => '"'.$couponUsage['COUPON_CODE'].'"', 'COUPON_DATE' => '"'.date('d.m.Y', strtotime($couponUsage['STAMP_ACTIVATE'])).'"' ), 'Gutschein Code {COUPON_CODE} vom {COUPON_DATE}');
		$description = $this->getTypeConfigurationByKey('DESCRIPTION', $defaultDescription);
		$description = str_replace(array('%COUPON_CODE%', '%COUPON_DATE%'), array($couponUsage['COUPON_CODE'], strtotime($couponUsage['STAMP_ACTIVATE'])), $description);

		$membershipId = $this->getTypeConfigurationByKey('COUPON_MEMBERSHIP');
		require_once $ab_path . "sys/packet_management.php";
		$packets = PacketManagement::getInstance($db);
		$membershipDetails = $packets->getFull($membershipId);

		$this->setCouponCodeUsageToUsedById($couponUsage['ID_COUPON_CODE_USAGE']);
		$this->setMessageOnActivation(Translation::readTranslation('marketplace', 'coupon.type.register.membership.onactivationmessage', null, array('MEMBERSHIP_NAME' => '"'.$membershipDetails["V1"].'"'), 'Ihnen wurde die Mitgliedschaft {htm(MEMBERSHIP_NAME)} für die Registierung freigeschaltet.'));
	}


	public function onUsage($couponUsage, $billingItem) {

	}


	public function isTypeCompatibleToTarget($targetType, $target) {
		return true;
	}
}