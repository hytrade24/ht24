<?php

require_once $ab_path . 'sys/lib.billing.creditnote.php';


class Coupon_Type_AbsoluteValueCouponType extends Coupon_Type_AbstractCouponType {

	public function getName() {
		return Translation::readTranslation('marketplace', 'coupon.type.absolutevalue.name', null, array(), 'Absoluter Betrag');
	}


	public function onActivation($coupon, $couponUsage) {
		global $db, $nar_systemsettings;

		$defaultDescription = Translation::readTranslation('marketplace', 'coupon.type.absolutevalue.descripion', null, array('COUPON_CODE' => '"'.$couponUsage['COUPON_CODE'].'"', 'COUPON_DATE' => '"'.date('d.m.Y', strtotime($couponUsage['STAMP_ACTIVATE'])).'"' ), 'Gutschein Code {COUPON_CODE} vom {COUPON_DATE}');
		$description = $this->getTypeConfigurationByKey('DESCRIPTION', $defaultDescription);
		$description = str_replace(array('%COUPON_CODE%', '%COUPON_DATE%'), array($couponUsage['COUPON_CODE'], strtotime($couponUsage['STAMP_ACTIVATE'])), $description);

		$value = $this->getTypeConfigurationByKey('COUPON_VALUE',0);

		$billingCreditnoteManagement = BillingCreditnoteManagement::getInstance($db);
		$billingCreditnoteManagement->createCreditnote(array(
				'FK_USER' => $couponUsage['FK_USER'],
				'DESCRIPTION' => $description,
				'PRICE' => $value,
				'FK_TAX' => $this->getTypeConfigurationByKey('FK_TAX', $nar_systemsettings['MARKTPLATZ']['TAX_DEFAULT']),
				'STATUS' => BillingCreditnoteManagement::STATUS_ACTIVE
		));


		$this->setCouponCodeUsageToUsedById($couponUsage['ID_COUPON_CODE_USAGE']);
		$this->setMessageOnActivation(Translation::readTranslation('marketplace', 'coupon.type.absolutevalue.onactivationmessage', null, array('CREDIT_AMOUNT' => '"'.round($value, 2).'"'), 'Ihnen wurde eine Gutschrift i.H.v. {CREDIT_AMOUNT} € zzgl. MwSt. in Ihrem Account hinterlegt.'));
	}


	public function onUsage($couponUsage, $billingItem) {

	}


	public function isTypeCompatibleToTarget($targetType, $target) {
		return true;
	}
}