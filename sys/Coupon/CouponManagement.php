<?php


class Coupon_CouponManagement {

	private static $db;
	private static $instance = null;

	const STATUS_ENABLED = 1;
	const STATUS_DISABLED = 0;

	public static $targetTypes = array('PACKET', 'MEMBERSHIP', 'PROVISION');

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return Coupon_CouponManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
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


		if(isset($param['ID_COUPON']) && $param['ID_COUPON'] != NULL) {
			$sqlWhere .= " AND c.ID_COUPON = '".mysql_real_escape_string($param['ID_COUPON'])."' ";
		}
		if(isset($param['TYPE']) && $param['TYPE'] != NULL) {
			$sqlWhere .= " AND c.TYPE = '".mysql_real_escape_string($param['TYPE'])."' ";
		}
		if(isset($param['COUPON_NAME']) && $param['COUPON_NAME'] != NULL) {
			$sqlWhere .= " AND c.COUPON_NAME LIKE '%".mysql_real_escape_string($param['COUPON_NAME'])."%' ";
		}
		if(isset($param['STATUS']) && $param['STATUS'] != NULL) {
			$sqlWhere .= " AND c.STATUS = '".mysql_real_escape_string($param['STATUS'])."' ";
		}

		if(isset($param['COUPON_CODE']) && $param['COUPON_CODE'] != NULL) {
			$sqlWhere .= " AND cc.CODE = '".mysql_real_escape_string($param['COUPON_CODE'])."' ";
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
		$sqlOrder = " c.ID_COUPON ";
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
			$sqlFields = "c.ID_COUPON,";
		} else {
			$sqlFields = "
				c.*,
				(SELECT COUNT(*) FROM coupon_code c_cc WHERE c_cc.FK_COUPON = c.ID_COUPON) as NUMBER_OF_CODES,
				(SELECT COUNT(*) FROM coupon_code_usage c_ccu JOIN coupon_code c_cc ON c_ccu.FK_COUPON_CODE = c_cc.ID_COUPON_CODE WHERE c_cc.FK_COUPON = c.ID_COUPON) as NUMBER_OF_USAGES
			";
		}

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".trim($sqlFields, " \t\r\n,")."
			FROM coupon c
			LEFT JOIN coupon_code cc ON cc.FK_COUPON = c.ID_COUPON
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY c.ID_COUPON
				".($sqlOrder?'ORDER BY '.$sqlOrder:'')."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";

		return $query;
	}


	/**
	 * Process to activate
	 *
	 * @param $code
	 *
	 * @throws Exception
	 */
	public function useCouponCode($code) {
		$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($this->getDb());

		$this->tryAndTestCouponCode($code);

		$coupon = $this->fetchCouponByCode($code);
		$couponType = $this->getCouponType($coupon);

		$couponUsage = $couponUsageManagement->activateCouponCode($coupon, $code);
		$couponType->onActivation($coupon, $couponUsage);

		$result = new stdClass();
		$result->couponUsage = $couponUsage;
		$result->message = $couponType->getMessageOnActivation();

		return $result;
	}

	/**
	 * @param $code
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function tryAndTestCouponCode($code) {
		$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($this->getDb());

		if (trim($code) == '') {
			throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.code.not.found', NULL, array(), 'Es konnte kein Gutschein für diesen Code gefunden werden'));
		}

		$coupon = $this->fetchCouponByCode($code);
		if (!$coupon) {
			throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.code.not.found', NULL, array(), 'Es konnte kein Gutschein für diesen Code gefunden werden'));
		}

		if (!$this->checkCouponRestrictions($coupon, $code)) {
			throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.code.not.valid', NULL, array(), 'Der eingegebene Code ist leider ungültig'));
		}

		$couponType = $this->getCouponType($coupon);
		if (!$couponType) {
			throw new Exception(Translation::readTranslation('marketplace', 'coupon.error.coupon.type.not.found', NULL, array(), 'Es konnte kein Gutschein geladen werden'));
		}

		return true;
	}


	/**
	 * @param string $code
	 */
	public function fetchCouponByCode($code) {
		$db = $this->getDb();

		$coupon = $db->fetch1("
			SELECT
				c.*
			FROM coupon c
			JOIN coupon_code cc ON cc.FK_COUPON = c.ID_COUPON
			WHERE
				c.STATUS = 1 AND cc.CODE = '".mysql_real_escape_string($code)."'
		");

		return $coupon;
	}

	/**
	 * @param string $code
	 */
	public function fetchById($couponId) {
		$db = $this->getDb();

		$coupon = $db->fetch1("
			SELECT
				c.*
			FROM coupon c
			WHERE
			 c.ID_COUPON = '".mysql_real_escape_string($couponId)."'
		");

		return $coupon;
	}

	/**
	 * @param $coupon
	 *
	 * @return Coupon_Type_CouponTypeInterface|null
	 */
	public function getCouponType($coupon) {
		$couponType = $coupon['TYPE'];

		if(class_exists($couponType) && in_array('Coupon_Type_CouponTypeInterface', class_implements($couponType))) {
			/** @var Coupon_Type_CouponTypeInterface $couponRestrictionClass */
			$couponTypeClass = new $couponType(unserialize($coupon['TYPE_CONFIG']));
			return $couponTypeClass;
		}

		return null;
	}

	public function getAllCouponTypes() {
		return array('Coupon_Type_AbsoluteValueCouponType', 'Coupon_Type_PercentageValueCouponType');
	}

	public function checkCouponRestrictions($coupon, $code) {
		$couponRestrictions = $this->getDb()->fetch_table("SELECT * FROM coupon_restriction WHERE FK_COUPON = '".(int)$coupon['ID_COUPON']."'");

		return $this->checkCouponRestrictionList($coupon, $code, $couponRestrictions);
	}

	public function checkCouponRestrictionList($coupon, $code, $couponRestrictions) {

		if($couponRestrictions == null) {
			return true;
		} else {
			$isPermitted = true;

			foreach($couponRestrictions as $key => $restriction) {
				$restrictionType = $restriction['RESTRICTION_TYPE'];
				if(class_exists($restrictionType) && in_array('Coupon_Restriction_CouponRestrictionInterface', class_implements($restrictionType))) {
					/** @var Coupon_Restrictrion_CouponRestrictionInterface $couponRestrictionClass */
					$couponRestrictionClass = new $restrictionType($restriction['VALUE'], unserialize($restriction['RESTRICTION_CONFIG']));
					if(!$couponRestrictionClass->isPermitted($coupon, $code)) {
						$isPermitted = false;
					}
				}

			}

			return $isPermitted;
		}
	}


	/**
	 * @param $restriction
	 *
	 * @return Coupon_Restrictrion_CouponRestrictionInterface
	 */
	public function getRestrictionType($restriction) {
		$restrictionType = $restriction['RESTRICTION_TYPE'];
		if(class_exists($restrictionType) && in_array('Coupon_Restriction_CouponRestrictionInterface', class_implements($restrictionType))) {
			/** @var Coupon_Restrictrion_CouponRestrictionInterface $couponRestrictionClass */
			return new $restrictionType($restriction['VALUE'], unserialize($restriction['RESTRICTION_CONFIG']));
		}

		return null;
	}

	public function generateCouponCodes($number = 10, $lenght = 5) {

	}

	public function deleteById($couponId) {

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