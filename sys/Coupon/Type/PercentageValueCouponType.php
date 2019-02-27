<?php

require_once $ab_path . 'sys/lib.billing.creditnote.php';


class Coupon_Type_PercentageValueCouponType extends Coupon_Type_AbstractCouponType {

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.type.percentagevalue.name', null, array(), 'Prozentualer Wert');
	}

	public function onActivation($coupon, $couponUsage) {
		global $db, $nar_systemsettings;

		$this->setMessageOnActivation(Translation::readTranslation('marketplace', 'coupon.type.percentagevalue.onactivationmessage', null, array(), 'Der Gutschein wird mit der nächsten Buchung einer im Gutschein enthaltenen Leistung verrechnet'));
	}


	public function onUsage($couponUsage, $billingItem) {
		$couponBillingItem = array();
		$couponValue = $this->getTypeConfigurationByKey('COUPON_VALUE', 0);
		$couponValueInPercent = $couponValue/100;

		if($billingItem['PRICE'] > 0) {
			$couponBillingItem = $billingItem;

			$couponBillingItem['PRICE'] = round(-1 * $couponValueInPercent * $billingItem['PRICE'],4);
			$couponBillingItem['DESCRIPTION'] = Translation::readTranslation('marketplace', 'coupon.type.percentagevalue.billingnote', null, array('COUPON_CODE' => '"'.$couponUsage['COUPON_CODE'].'"', 'COUPON_DATE' => '"'.date('d.m.Y', strtotime($couponUsage['STAMP_ACTIVATE'])).'"'), 'Gutschein {COUPON_CODE} vom {COUPON_DATE} für ').$billingItem['DESCRIPTION'];
			$couponBillingItem['REF_TYPE'] = BillingInvoiceItemManagement::REF_TYPE_COUPON;
			$couponBillingItem['REF_FK'] = $couponUsage['ID_COUPON_CODE_USAGE'];
		}
		return $couponBillingItem;
	}


	public function isTypeCompatibleToTarget($targetType, $target) {
		$configTargetType = $this->getTypeConfigurationByKey('TARGET_TYPE', null);
		$configTargets = $this->getTypeConfigurationByKey('TARGETS', null);

		if($configTargets != null && $targetType != $configTargetType) {
			return false;
		}

		if(is_array($target) && is_array($configTargets) && (count(array_intersect($configTargets, $target)) == 0)) {
			return FALSE;
		} elseif (!is_array($target) && is_array($configTargets) && !in_array($target, $configTargets)) {
			return FALSE;
		}

		return true;
	}
}