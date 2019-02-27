<?php

require_once $ab_path."admin/sys/tabledef.php";
require_once $ab_path."sys/lib.cache.admin.php";

class ManufacturerDatabaseStructureManagement {
	private static $db;
	private static $instance = null;

	static $masterFieldsNotUsed = array('FK_USER', 'STATUS',  'STAMP_START', 'STAMP_END','STAMP_CREATE', 'STAMP_DEACTIVATE',
			'ZIP', 'CITY', 'FK_COUNTRY', 'STREET', 'LATITUDE', 'LONGITUDE', 'FK_PRODUCT', 'VERSANDKOSTEN', 'MWST', 'MENGE',
			'AD_AGB', 'AD_WIDERRUF', 'TRADE', 'AUTOBUY', 'ONLY_COLLECT', 'IMPORT_IDENTIFIER', 'LU_LAUFZEIT', 'AUTOCONFIRM', 'BF_CONSTRAINTS',
			'VERSANDOPTIONEN', 'VERSANDKOSTEN_INFO', 'COMMENTS_ALLOWED', 'IS_VARIANT', 'MOQ', 'PSEUDOPREIS', 'B_PSEUDOPREIS_DISCOUNT', 'ADMINISTRATIVE_AREA_LEVEL_1',
			'VERKAUFSOPTIONEN', 'DELETED', 'BASISPREIS_PREIS', 'BASISPREIS_MENGE', 'BASISPREIS_EINHEIT', 'FK_ARTICLE_EXT',
			'LIEFERUNG');

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ManufacturerDatabaseStructureManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}


	public function createHdbTableFromArticleTable($tableDefId) {
		$tableDef = $this->getDb()->fetch1("SELECT * FROM table_def WHERE ID_TABLE_DEF = '".(int)$tableDefId."'");

		$tableDefName = $tableDef['T_NAME'];
		$hdbTableName = 'hdb_table_'.$tableDefName;

		$primarytableDefClomun = 'ID_'.strtoupper($tableDefName);
		$primaryHdbTableColumn = 'ID_HDB_TABLE_'.strtoupper($tableDefName);

		$this->getDb()->querynow("CREATE TABLE IF NOT EXISTS `".mysql_real_escape_string($hdbTableName)."` LIKE `".mysql_real_escape_string($tableDefName)."`");
		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."`  CHANGE `".$primarytableDefClomun."` `".$primaryHdbTableColumn."` BIGINT(20)  UNSIGNED  NOT NULL  AUTO_INCREMENT;");

		foreach(self::$masterFieldsNotUsed as $key => $fieldName) {
			$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` DROP COLUMN `".mysql_real_escape_string($fieldName)."`");
		}

		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD COLUMN `DATA_USER` LONGTEXT  AFTER `".$primaryHdbTableColumn."`");
		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD COLUMN `FULL_PRODUKTNAME` varchar(255) default NULL AFTER `".$primaryHdbTableColumn."`");
		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD COLUMN `CONFIRMED` tinyint(1) unsigned NOT NULL  AFTER `".$primaryHdbTableColumn."`");
		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD COLUMN `FK_TABLE_DEF` BIGINT(20) UNSIGNED NOT NULL AFTER `".$primaryHdbTableColumn."`");

		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD INDEX `FULL_PRODUKTNAME` (`FULL_PRODUKTNAME`)");
		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD INDEX `FK_VIEW` (`FK_MAN`,`FULL_PRODUKTNAME`)");
		$this->getDb()->querynow("ALTER TABLE `".mysql_real_escape_string($hdbTableName)."` ADD INDEX `FK_TABLE_DEF` (`FK_TABLE_DEF`)");


		$this->flushCache();
	}

	public function deleteHdbTableByTableDefId($tableDefName) {
		$hdbTableName = 'hdb_table_'.$tableDefName;
		$this->getDb()->querynow("DROP TABLE `".mysql_real_escape_string($hdbTableName)."`");

		$this->flushCache();

		return true;
	}

	public function updateHdbTableFieldChange($field) {
		$table = new tabledef();
		$table->getTable($field['table']);
		$db = $this->getDb();

		$sql = $table->ar_field_types[$field['F_TYP']]['SQL'];

		if(empty($field['ID_FIELD_DEF'])) {
			$sql = str_replace("#FIELD#", $field['SQL_FIELD'], $sql);
			$sql = str_replace("`#FIELD2#`", "", $sql);
		} else {
			$sql = str_replace(" ADD ", " CHANGE ", $sql);
			$sql = str_replace("#FIELD#", $field['F_NAME'], $sql);
			$sql = str_replace("`#FIELD2#`", $field['F_NAME'], $sql);
		}


		if($field['table'] == 'artikel_master') {
			$tabledefs = $db->fetch_table("SELECT * FROM table_def");
		} else {
			$tabledefs = $db->fetch_table("SELECT * FROM table_def WHERE T_NAME = '".mysql_real_escape_string($field['table'])."'");
		}

		foreach($tabledefs as $key => $tabledef) {
			$filterTableName = 'hdb_table_'.$tabledef['T_NAME'];

			$filterSql = $sql;
			$filterSql = str_replace("#TABLE#", $filterTableName, $filterSql);

			$db->querynow($filterSql);
		}

		$this->flushCache();
	}

	public function deleteHdbTableField($fieldname, $tablename) {
		$db = $this->getDb();
		if($tablename == 'artikel_master') {
			$tabledefs = $db->fetch_table("SELECT * FROM table_def");
		} else {
			$tabledefs = $db->fetch_table("SELECT * FROM table_def WHERE T_NAME = '".mysql_real_escape_string($tablename)."'");
		}

		foreach($tabledefs as $key => $tabledef) {
			$filterTableName = 'hdb_table_'.$tabledef['T_NAME'];
			$db->querynow("ALTER TABLE ".$filterTableName." DROP COLUMN ".$fieldname);
		}

		$this->flushCache();
	}



	protected function flushCache() {
		$cacheAdmin = new CacheAdmin($this->getDb());
		$cacheAdmin->emptyCache('hdb_producttype_config');
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