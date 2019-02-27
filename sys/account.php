<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 *
 * @version 1.0
 * @author Benjamin Schmalenberger
 *
 */
class account
{
	/**
	 * Id of current user
	 * @var int
	 */
	private $id_user;

	/**
	 * @access private
	 * @var object
	 */
	private static $instance = null;

	/**
	 * current DB/SQL Object
	 * @var object
	 */
	private $db;

	/**
	 * current antries from users account
	 * @var array
	 */
	public $ar_entries;

	/**
	 * ID of current invoice
	 * @var int
	 */
	private $id_invoice;

	/**
	 * array with data of current invoice
	 * @var array
	 */
	public $ar_invoice;

	/**
	 *
	 * @var int
	 */
	public $all_invoices;

	/**
	 *
	 * @var array
	 */
	public $ar_invoices;

	public $ar_status_text = array
		(
			-4 => '<span class="error">gel&ouml;scht</span>',
			-3 => '<span class="error">Storniert</span>',
			-2 => '<span class="error">Inkasso</span>',
			-1 => '<span class="error">AUSGEBUCHT</span>',
			0 => 'neu/nicht gestellt',
			1 => 'gestellt/offen',
			5 => '<span class="ok">Bezahlt</span>'
		);

	/**
	 * Dummy Function
	 */
	private function __clone()
	{
		die("__clone is not accepted!");
	}	//__clone()

	/**
	 * constructor
	 * @param int $id_user
	 * @return none;
	 */
	private function __construct($id_user)
	{
		$this->id_user = (int)$id_user;
		$this->ar_entries = array();
		$this->db = NULL;
	}	// __construct()

	/**
	 * Destructor. Has to check Todos
	 * @return NONE
	 */
	public function __destruct()
	{
		#TODO: do we need this function?
	}

	/**
	 * error handling function
	 * @param string $str
	 * return boolean
	 */
	private function error($str)
	{
		die("Error in account class: ".$str);
		return false;
	}	// error()

	/**
	 * singleton function
	 * @access public
	 * @return object
	 */
	public static function getInstance($uid)
	{
		if(self::$instance === null)
		{
			self::$instance = new account($uid);
		}
		return self::$instance;
	}	// getInstance()

	/**
	 * gets current account balance
	 * @return double balance
	 */
	public function get_balance()
	{
		$query = "
			SELECT
				SUM(AMOUNT)
			FROM
				account
			WHERE
				FK_USER = ".$this->id_user;

		$res = $this->query($query);
		$value = mysql_fetch_row($res);
		$value = $this->round($value[0]);
		return $value;
	}	// get_balance()

	/**
	 *
	 * @return unknown_type
	 */
	public function get_open()
	{
		$query = "SELECT
			SUM(BRUTTO)
		FROM
			invoice
		WHERE
			FK_USER=".$this->id_user." AND STAMP_CANCLE IS NULL
			AND STATUS>0 AND (USER_STATUS=0 AND PAY_STATUS=0)";
		$res = $this->query($query);
		$value = mysql_fetch_row($res);
		$value = $this->round($value[0]);
		return $this->round($value);
	}	// get_open()

	/**
	 * adds a new entry to account
	 * @param double $amount
	 * @param string $desc
	 * @param string|date $stamp
	 * @return boolean
	 */
	public function add_entry($amount, $desc, $stamp = NULL)
	{
		//
		return true;
	}	// add-entry()

	/**
	 * deletes an entry from account
	 * @param int $id_entry
	 * @return boolean
	 */
	public function delete_entry($id_entry)
	{
		//
	}	// delete_entry()

	/**
	 * gets n entries from account
	 * @return int
	 */
	public function get_all_entries()
	{
		$query = "
			SELECT
				COUNT(*)
			FROM
				account
			WHERE
				FK_USER=".$this->id_user;
		$res = $this->query($query);
		$ar = mysql_fetch_row($res);
		return $ar[0];
	}	// get_all_entries()

	/**
	 * gets entries from current accout
	 * @param int $limit
	 * @param int $n
	 * @return array
	 */
	public function get_entries($limit, $n)
	{
		$return = array();
		$limit = (int)$limit;
		$n = (int)$n;

		$query = "
			SELECT
				DSC, STAMP_BOOK, AMOUNT
			FROM
				account
			WHERE
				FK_USER = ".$this->id_user."
			ORDER BY
				STAMP_BOOK DESC
			LIMIT ".$limit.", ".$n;
		$res = $this->query($query);
		while($row = mysql_fetch_assoc($res))
		{
			$return[] = $row;
		}
		return $return;
	}	// get_entries()

	/**
	 * gets an invoice
	 * @param $id_invoice
	 * @return array
	 */
	public function get_invoice($id_invoice)
	{
		$this->id_invoice = (int)$id_invoice;
		$this->ar_invoice = array();

		$query = "
			SELECT
				ID_INVOICE,
				STATUS,
				USER_STATUS,
				STAMP_DELIVERY,
				STAMP_ADJUST,
				STAMP_PAY_UNTIL,
				STAMP_PAYMENT,
				DISCOUNT,
				NETTO,
				TAXES,
				BRUTTO
			FROM
				invoice
			WHERE
				FK_USER=".$this->id_user."
				AND ID_INVOICE=".$this->id_invoice;

		$res = $this->query($query);
		$ar_invoice = mysql_fetch_assoc($res);

		$ar_posten = $this->get_items($this->id_invoice);

		$this->ar_invoice = array_merge($ar_invoice, $ar_posten);

		return $this->ar_invoice;
	}	// get_invoice()

	/**
	 * gets all invoices of current user
	 * @param int $limit
	 * @param int $n
	 * @param boolean $calc
	 * @return array
	 */
	public function get_invoices($limit, $n, $calc = false, $show_open = true, $show_paid = false) {
		$res = $this->query("
			SELECT
				count(*)
			FROM
				invoice
			WHERE
				STATUS>0 AND FK_USER=".$this->id_user.
				($show_open ? " AND (USER_STATUS=0 AND PAY_STATUS=0)" : "").
				($show_paid ? " AND (USER_STATUS>0 OR PAY_STATUS=1)" : ""));
		$tmp = mysql_fetch_row($res);
		$this->all_invoices = $tmp[0];

		$query = "
			SELECT
				ID_INVOICE,
				STATUS,
				USER_STATUS,
				STAMP_DELIVERY,
				STAMP_ADJUST,
				STAMP_PAY_UNTIL,
				STAMP_PAYMENT,
				DISCOUNT,
				NETTO,
				TAXES,
				BRUTTO
			FROM
				invoice
			WHERE
				STATUS>0 AND FK_USER=".$this->id_user.
				($show_open ? " AND (USER_STATUS=0 AND PAY_STATUS=0)" : "").
				($show_paid ? " AND (USER_STATUS>0 OR PAY_STATUS=1)" : "")."
			ORDER BY
				STAMP_DELIVERY DESC,
				STAMP_CREATE DESC
			LIMIT
				".$limit.", ".$n;
		$invoices = array();

		$res = $this->query($query);
		while($row = mysql_fetch_assoc($res))
		{
			if($calc)
			{
				$ar = $this->get_items($row['ID_INVOICE'], true);
				$row = array_merge($row, $ar);
			}
			$row['STATUS_TEXT'] = $this->ar_status_text[$row['STATUS']];
			$invoices[] = $row;
		}

		$this->ar_invoices = $invoices;
		return $invoices;
	}	// get_invoices()

	/**
	 * gets all items to $id_invoice
	 * @param int $id_invoice
	 * @return array [SUM] (Brutto value) [ITEMS] array
	 */
	protected function get_items($id_invoice)
	{
		$items = array('SUM' => 0, 'SUM_NETTO' => 0,
			'ITEMS' => array(), 'TAXES' => array());

		$query = "
			SELECT
				TXT,
				STAMP_FROM,
				STAMP_TO,
				QUANTITY,
				NETTO,
				TAX,
				BRUTTO
			FROM
				article
			WHERE
				FK_INVOICE = ".$id_invoice."
			ORDER BY
				STAMP_UPDATE DESC,
				STAMP_INSERT DESC";

		$res = $this->query($query);
		$pos = 1;
		while($row = mysql_fetch_assoc($res))
		{
			$row['POS'] = $pos++;
			if($row['QUANTITY'] > 1)
			{
				$row['NETTO_ALL'] = $row['NETTO']*$row['QUANTITY'];
				$row['BRUTTO_ALL'] = $row['BRUTTO']*$row['QUANTITY'];
				$row['TAX_ALL'] = $row['TAX']*$row['QUANTITY'];
			}
			else
			{
				$row['NETTO_ALL'] = $row['NETTO'];
				$row['BRUTTO_ALL'] = $row['BRUTTO'];
				$row['TAX_ALL'] = $row['TAX'];
			}
			$items['TAXES'][$row['TAX']] += ($row['BRUTTO_ALL']-$row['NETTO_ALL']);
			$items['SUM'] = $items['SUM']+$row['BRUTTO_ALL'];
			$items['SUM_NETTO'] = $items['SUM_NETTO']+$row['NETTO_ALL'];
			foreach($row as $key => $value)
			{
				if(is_numeric($value))
				{
					$row[$key] = $this->round($value);
				}
			}
			$items['ITEMS'][] = $row;
		}
		return $items;
	}	// get_items()

	/**
	 * rounds an double value
	 * @param double $value
	 * @return float
	 */
	public function round($value)
	{
		if(!is_numeric($value))
		{
			return 0.00;
		}
		else
		{
			return round($value, 2);
		}
	}	// round()

	/**
	 * excutes sql query
	 * @param string|query $str
	 * @return array
	 */
	private function query($str)
	{
		if(is_null($this->db))
		{
			global $db;
			$this->db = &$db;
		}
		$res =  $this->db->querynow($str);
		if(empty($res['str_error']))
		{
			return $res['rsrc'];
		}
		else
		{
			$this->error($res['str_error']);
		}
	}	// query()
}

?>