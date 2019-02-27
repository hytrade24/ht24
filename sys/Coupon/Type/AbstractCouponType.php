<?php

abstract class Coupon_Type_AbstractCouponType implements Coupon_Type_CouponTypeInterface {

	protected $typeConfiguration = array();
	protected $messageOnActivation = '';
	protected $targetType = null;
	protected $targets;


	function __construct($typeConfiguration) {
		$this->typeConfiguration = $typeConfiguration;

		$this->targetType = $this->getTypeConfigurationByKey('TARGET_TYPE', null);
		$this->targets = $this->getTypeConfigurationByKey('TARGETS', null);
	}

	public function setCouponCodeUsageToUsedById($couponUsageId) {
		global $db;

		$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
		$couponUsageManagement->setUsageStateToUsed($couponUsageId);
	}

	/**
	 * @return array
	 */
	public function getTypeConfiguration() {
		return $this->typeConfiguration;
	}

	/**
	 * @return array
	 */
	public function getTypeConfigurationByKey($key, $default = null) {
		$value = $this->typeConfiguration[$key];
		if($value === null) {
			return $default;
		}

		return $value;
	}

	/**
	 * @return string
	 */
	public function getMessageOnActivation() {
		return $this->messageOnActivation;
	}

	/**
	 * @param string $messageOnActivation
	 *
	 * @return Coupon_Type_AbstractCouponType
	 */
	public function setMessageOnActivation($messageOnActivation) {
		$this->messageOnActivation = $messageOnActivation;

		return $this;
	}

	/**
	 * @return array|null
	 */
	public function getTargetType() {
		return $this->targetType;
	}

	/**
	 * @return array
	 */
	public function getTargets() {
		return $this->targets;
	}





}