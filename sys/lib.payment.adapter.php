<?php
/* ###VERSIONSBLOCKINLCUDE### */


class PaymentAdapterManagement {
	private static $db;
	private static $instance = null;

	const STATUS_ENABLED = 1;
 	const STATUS_DISABLED = 0;

	const USER_STATUS_ENABLED = 1;
 	const USER_STATUS_DISABLED = 0;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return PaymentAdapterManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function fetchAllByParam($param) {
        global $langval;
        $db = $this->getDb();

        $language = ($param['BF_LANG'] > 0 ? $param['BF_LANG'] : $langval);
        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " a.ID_PAYMENT_ADAPTER ";

		if(isset($param['STATUS']) && $param['STATUS'] != null) { $sqlWhere .= " AND a.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
		if(isset($param['STATUS_USER']) && $param['STATUS_USER'] != null) { $sqlWhere .= " AND a.STATUS_USER = '".mysql_real_escape_string($param['STATUS_USER'])."' "; }

        if(isset($param['LIMIT']) && $param['LIMIT'] != null) { if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; } }
        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }


        $query = "
            SELECT
                a.*, s.V1 as NAME
            FROM
                payment_adapter a
            ".$sqlJoin."
            JOIN `string_payment_adapter` s ON s.S_TABLE='payment_adapter' AND s.FK=a.ID_PAYMENT_ADAPTER
                AND s.BF_LANG=if(a.BF_LANG_PAYMENT_ADAPTER & " . $language . ", " . $language . ", 1 << floor(log(a.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
            WHERE
                true
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY a.ID_PAYMENT_ADAPTER
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ";

        $result = $db->fetch_table($query);

        return $result;
    }

    public function countByParam($param) {
        $db = $this->getDb();


        $sqlLimit = "";
        $sqlWhere = " ";
        $sqlJoin = "";
        $sqlOrder = " a.ID_PAYMENT_ADAPTER ";

        if(isset($param['STATUS']) && $param['STATUS'] != null) { $sqlWhere .= " AND a.STATUS = '".mysql_real_escape_string($param['STATUS'])."' "; }
		if(isset($param['STATUS_USER']) && $param['STATUS_USER'] != null) { $sqlWhere .= " AND a.STATUS_USER = '".mysql_real_escape_string($param['STATUS_USER'])."' "; }

        $query = ("
            SELECT
                SQL_CALC_FOUND_ROWS a.ID_PAYMENT_ADAPTER
            FROM
                payment_adapter a
            ".$sqlJoin."
            WHERE
                1 = 1
                ".($sqlWhere?' '.$sqlWhere:'')."
            GROUP BY a.ID_PAYMENT_ADAPTER
        ");

        $result = $db->querynow($query);
        $count = $db->fetch_atom("SELECT FOUND_ROWS()");

        return $count;
    }

    public function fetchById($paymentAdapterId) {
        global $langval;
        return $this->getDb()->fetch1("
            SELECT a.*, s.V1 as NAME
            FROM payment_adapter a
            JOIN `string_payment_adapter` s ON s.S_TABLE='payment_adapter' AND s.FK=a.ID_PAYMENT_ADAPTER
                AND s.BF_LANG=if(a.BF_LANG_PAYMENT_ADAPTER & " . $langval . ", " . $langval . ", 1 << floor(log(a.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
            WHERE a.ID_PAYMENT_ADAPTER = '".(int)$paymentAdapterId."'");
    }

    public function fetchByAdapterName($adapterName) {
        global $langval;
        return $this->getDb()->fetch1("
            SELECT a.*, s.V1 as NAME
            FROM payment_adapter a
            JOIN `string_payment_adapter` s ON s.S_TABLE='payment_adapter' AND s.FK=a.ID_PAYMENT_ADAPTER
                AND s.BF_LANG=if(a.BF_LANG_PAYMENT_ADAPTER & " . $langval . ", " . $langval . ", 1 << floor(log(a.BF_LANG_PAYMENT_ADAPTER+0.5)/log(2)))
            WHERE a.ADAPTER_NAME = '".mysql_real_escape_string($adapterName)."'");
    }

    public function fetchConfigurationById($paymentAdapterId) {
        $paymentAdapter = $this->fetchById($paymentAdapterId);

        if($paymentAdapter['CONFIG'] != NULL) {
            $tmpFilename = tempnam(sys_get_temp_dir(), 'EBIZ');
            file_put_contents($tmpFilename, $paymentAdapter['CONFIG']);
            $ini =  parse_ini_file($tmpFilename, true);
            unlink($tmpFilename);

            return $ini;
        } else {
            return array();
        }
    }

    public function validate($invoice, $isNewObject = true) {
        return true;
    }

	/**
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