<?php

class Coupon_CouponUsageManagement {

	private static $db;
	private static $instance = null;

	const USAGE_STATE_UNUSED = 0;
	const USAGE_STATE_ACTIVATED = 1;
	const USAGE_STATE_USED = 2;


	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return Coupon_CouponUsageManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	public function fetchCouponCodeUsageById($couponCodeUsageId) {
		$result = $this->fetchAllByParam(array('ID_COUPON_CODE_USAGE' => $couponCodeUsageId));
		if(is_array($result)) {
			return array_shift($result);
		}
		return null;
	}



	public function fetchActivatedCouponUsageByUserId($couponCodeUsageId, $userId, $targetType, $targets) {
		$couponUsage = null;

		if (isset($couponCodeUsageId) && !empty($couponCodeUsageId)) {
			$couponUsage = $this->fetchCouponCodeUsageById($couponCodeUsageId);
			if ($couponUsage == NULL || $couponUsage['FK_USER'] != $userId || $couponUsage['USAGE_STATE'] != Coupon_CouponUsageManagement::USAGE_STATE_ACTIVATED) {
				throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.not.valud', NULL, array(), 'Der Gutschein ist leider nicht mehr gültig')." (Code 1)");
			}

			if (!$this->isCouponsUsageCompatible($couponUsage, $targetType, $targets)) {
				throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.not.valud', NULL, array(), 'Der Gutschein ist leider nicht mehr gültig')." (Code 2)");
			}
		}

		return $couponUsage;
	}

	/**
	 * @param $coupon
	 *
	 * @return mixed
	 */
	public function fetchUsageForCoupon($coupon) {
		return $this->fetchAllByParam(array('FK_COUPON' => (int)$coupon['ID_COUPON']));
	}

	public function fetchUsageForCouponAndCode($coupon, $code) {
		return $this->fetchAllByParam(array('FK_COUPON' => (int)$coupon['ID_COUPON'], 'CODE' => $code));
	}

	public function fetchUsageForCouponAndUser($coupon, $userId) {
		return $this->fetchAllByParam(array('FK_COUPON' => (int)$coupon['ID_COUPON'], 'FK_USER' => $userId));
	}


	public function fetchCompatibleAndAvailableCouponUsages($userId, $targetType, $targets) {
		global $db;

		$couponManagement = Coupon_CouponManagement::getInstance($db);

		$couponUsages = $this->fetchAllByParam(array('FK_USER' => $userId, 'USAGE_STATE' => self::USAGE_STATE_ACTIVATED));
		foreach($couponUsages as $key => $couponUsage) {

			if(!$this->isCouponsUsageCompatible($couponUsage, $targetType, $targets)) {
				unset($couponUsages[$key]);
				continue;
			}

		}

		return $couponUsages;
	}

	public function isCouponsUsageCompatible($couponUsage, $targetType, $targets) {
		global $db;

		$couponManagement = Coupon_CouponManagement::getInstance($db);

		/** @var Coupon_Type_CouponTypeInterface $couponType */
		$couponType = $couponManagement->getCouponType($couponUsage);
		$couponRestrictions = unserialize($couponUsage['RESTRICTIONS']);

		if($couponRestrictions != null && !$couponManagement->checkCouponRestrictionList($couponUsage, $couponUsage['COUPON_CODE'], $couponRestrictions)) {
			return false;
		}

		if(!$couponType->isTypeCompatibleToTarget($targetType, $targets)) {
			return false;
		}

		return true;
	}


	public function fetchAllByParam($param) {
        $query = $this->generateFetchQuery($param);
        $arResult = $this->getDb()->fetch_table($query);

        return $arResult;
    }

    public function fetchQueryByParam($param) {
        $query = $this->generateFetchQuery($param);
        return $query;
    }

	public function countByParam($param) {
		$db = $this->getDb();

		unset($param['LIMIT']);
		unset($param['OFFSET']);
		unset($param['SORT']);
		unset($param['SORT_DIR']);
		$param['NO_FIELDS'] = TRUE;

		$query = $this->generateFetchQuery($param);

		$db->querynow($query);
		$rowCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $rowCount;
	}



	public function generateWhereQuery($param)
	{
		global $ab_path, $langval;
		$db = $this->getDb();

		$sqlWhere = "";


		if(isset($param['ID_COUPON_CODE_USAGE']) && $param['ID_COUPON_CODE_USAGE'] != NULL) {
			$sqlWhere .= " AND ccu.ID_COUPON_CODE_USAGE = '".mysql_real_escape_string($param['ID_COUPON_CODE_USAGE'])."' ";
		}
		if(isset($param['FK_COUPON_CODE']) && $param['FK_COUPON_CODE'] != NULL) {
			$sqlWhere .= " AND ccu.FK_COUPON_CODE = '".mysql_real_escape_string($param['FK_COUPON_CODE'])."' ";
		}
		if(isset($param['FK_COUPON']) && $param['FK_COUPON'] != NULL) {
			$sqlWhere .= " AND cc.FK_COUPON = '".mysql_real_escape_string($param['FK_COUPON'])."' ";
		}
		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) {
			$sqlWhere .= " AND ccu.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' ";
		}
		if(isset($param['USAGE_STATE']) && $param['USAGE_STATE'] != NULL) {
			$sqlWhere .= " AND ccu.USAGE_STATE = '".mysql_real_escape_string($param['USAGE_STATE'])."' ";
		}
		if(isset($param['CODE']) && $param['CODE'] != NULL) {
			$sqlWhere .= " AND ccu.CODE = '".mysql_real_escape_string($param['CODE'])."' ";
		}


		return $sqlWhere;
	}

	protected function generateFetchQuery($param) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " ccu.ID_COUPON_CODE_USAGE ";
		$sqlHaving = array();

		$sqlWhere = $this->generateWhereQuery($param);

		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) {
			if (isset($param["SORT_BY"])) {	$sortBy = $param["SORT_BY"]; }
			if (isset($param["SORT_DIR"])) { $sortDir = $param["SORT_DIR"];	}
			$sqlOrder = $sortBy." ".$sortDir;
		}
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			} else {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			}
		}

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "ccu.ID_ID_COUPON_CODE_USAGE,";
		} else {
			$sqlFields = "
				ccu.*,
				cc.CODE AS COUPON_CODE,
				ccu.USAGE_TYPE as TYPE,
				ccu.USAGE_TYPE_CONFIG as TYPE_CONFIG,
				u.NAME as USER_NAME
			";
		}



		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".trim($sqlFields, " \t\r\n,")."
			FROM coupon_code_usage ccu
			LEFT JOIN coupon_code cc ON cc.ID_COUPON_CODE = ccu.FK_COUPON_CODE
			LEFT JOIN user u ON ccu.FK_USER = u.ID_USER
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY ccu.ID_COUPON_CODE_USAGE
			    ".($sqlOrder?'ORDER BY '.$sqlOrder:'')."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}




	public function activateCouponCode($coupon, $code) {
		global $uid, $db;

		$couponManagement = Coupon_CouponManagement::getInstance($db);

		$couponCode = $this->getDb()->fetch1("SELECT * FROM coupon_code WHERE FK_COUPON = '".(int)$coupon['ID_COUPON']."' AND CODE = '".mysql_real_escape_string($code)."'");
		if($couponCode == null) {
			return false;
		}

		$usageRestriction = array();
		$couponRestrictions = $this->getDb()->fetch_table("SELECT * FROM coupon_restriction WHERE FK_COUPON = '".(int)$coupon['ID_COUPON']."'");
		foreach($couponRestrictions as $key => $couponRestriction) {
			/** @var Coupon_Restriction_CouponRestrictionInterface $restrictionClass */
			$restrictionClass = $couponManagement->getRestrictionType($couponRestriction);
			if($restrictionClass->reCheckOnUsage()) {
				$usageRestriction[] = array(
					'RESTRICTION_TYPE' => $couponRestriction['RESTRICTION_TYPE'],
					'RESTRICTION_CONFIG' => $couponRestriction['RESTRICTION_CONFIG']
				);
			}
		}


		$couponCodeUsageId = $this->getDb()->update('coupon_code_usage', array(
			'FK_COUPON_CODE' => $couponCode['ID_COUPON_CODE'],
			'FK_USER' => $uid,
			'USAGE_STATE' => self::USAGE_STATE_ACTIVATED,
			'STAMP_ACTIVATE' => date("Y-m-d H:i:s"),
			'USAGE_TYPE' => $coupon['TYPE'],
			'USAGE_TYPE_CONFIG' => $coupon['TYPE_CONFIG'],
			'COUPON_NAME' => $coupon['COUPON_NAME'],
			'COUPON_DESCRIPTION' => $coupon['COUPON_DESCRIPTION'],
			'COUPON_CODE' => $code,
			'RESTRICTIONS' => serialize($usageRestriction)
		));

		return $this->fetchCouponCodeUsageById($couponCodeUsageId);
	}


	public function useCouponForTarget($couponCodeUsage, $billingItem) {
		global $db;
		$couponManagement = Coupon_CouponManagement::getInstance($db);
		$couponType = $couponManagement->getCouponType($couponCodeUsage);

		return $couponType->onUsage($couponCodeUsage, $billingItem);
	}

	public function setUsageStateToUsed($couponCodeUsageId) {
		global $uid;

		$couponCodeUsageId = $this->getDb()->update('coupon_code_usage', array(
			'ID_COUPON_CODE_USAGE' => $couponCodeUsageId,
			'USAGE_STATE' => self::USAGE_STATE_USED,
			'STAMP_USAGE' => date("Y-m-d H:i:s")
		));

		return $couponCodeUsageId;
	}



	/*
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}

	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}

	private function __construct() {
	}
	private function __clone() {
	}
}