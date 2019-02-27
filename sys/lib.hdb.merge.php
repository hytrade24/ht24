<?php
require_once $ab_path.'sys/lib.hdb.php';


class ManufacturerMergeManagement {
	private static $db;
	private static $manufactuerDatabaseManagement = null;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ManufacturerMergeManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	//// Manufacturer Merge

	public function startManufacturerMerge() {
		$_SESSION['HDB_MERGE_MANUFACTURER_SESSION'] = 1;
		$_SESSION['HDB_MERGE_MANUFACTURER_DATA'] = array();

		return true;
	}

	public function cancelManufacturerMerge() {
		$_SESSION['HDB_MERGE_MANUFACTURER_SESSION'] = 0;
		$_SESSION['HDB_MERGE_MANUFACTURER_DATA'] = array();

		return true;
	}

	public function isActiveManufacturerMerge() {
		return $_SESSION['HDB_MERGE_MANUFACTURER_SESSION'] == 1;
	}

	public function getManufacturerMergeData() {
		return $_SESSION['HDB_MERGE_MANUFACTURER_DATA'];
	}

	public function hasManufacturerMergeData() {
		return count($_SESSION['HDB_MERGE_MANUFACTURER_DATA']) > 0;
	}

	public function addManufacturerMergeData($hdbManufacturer) {
		$_SESSION['HDB_MERGE_MANUFACTURER_DATA'][$hdbManufacturer['ID_MAN']] = $hdbManufacturer;
	}

	public function removeManufacturerMergeData($hdbManufacturerId) {
		unset($_SESSION['HDB_MERGE_MANUFACTURER_DATA'][$hdbManufacturerId]);
	}

	public function runManufacturerMerge($mainEntryId) {
		$hdbManufacturers = $this->getManufacturerMergeData();
		$mainManufacturer = null;
		foreach($hdbManufacturers as $key => $entry) {
			if($key == $mainEntryId) {
				$mainManufacturer = $entry;
				unset($hdbManufacturers[$key]);
			}
		}

		if($mainManufacturer == null) {
			return false;
		}
		foreach($hdbManufacturers as $key => $hdbManufacturer) {

			$productTypes = $this->getManufacturerDatabaseManagement()->fetchAllProductTypes();
			foreach($productTypes as $productTypeKey => $productType) {
				$products = $this->getManufacturerDatabaseManagement()->fetchAllByParam($productType['HDB_TABLE'], array('FK_MAN' => $hdbManufacturer['ID_MAN']));
				foreach($products as $productKey => $product) {
					$this->getManufacturerDatabaseManagement()->saveProduct($product['ID_HDB_PRODUCT'], $product['HDB_TABLE'], array(
						'FK_MAN' => $mainManufacturer['ID_MAN']
					));
				}
			}

			$this->getManufacturerDatabaseManagement()->deleteManufacturerById($hdbManufacturer['ID_MAN']);
		}

		$this->cancelManufacturerMerge();
		return true;
	}

	///// Product


	public function startProductMerge($hdbTable) {
		$_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_SESSION'] = 1;
		$_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_DATA'] = array();

		return true;
	}

	public function cancelProductMerge($hdbTable) {
		$_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_SESSION'] = 0;
		$_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_DATA'] = array();

		return true;
	}

	public function isActiveProductMerge($hdbTable) {
		return $_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_SESSION'] == 1;
	}

	public function getProductMergeData($hdbTable) {
		return $_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_DATA'];
	}

	public function hasProductMergeData($hdbTable) {
		return count($_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_DATA']) > 0;
	}

	public function addProductMergeData($product, $hdbTable) {
		$_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_DATA'][$product['ID_HDB_PRODUCT']] = $product;
	}

	public function removeProductMergeData($productId, $hdbTable) {
		unset($_SESSION['HDB_MERGE_PRODUCT_'.strtoupper($hdbTable).'_DATA'][$productId]);
	}

	public function runProductMerge($mainEntryId, $hdbTable) {
		$hdbProducts = $this->getProductMergeData($hdbTable);
		$mainProduct = null;
		foreach($hdbProducts as $key => $product) {
			if($key == $mainEntryId) {
				$mainProduct = $product;
				unset($hdbProducts[$key]);
			}
		}

		if($mainProduct == null) {
			return false;
		}

		foreach($hdbProducts as $key => $hdbProduct) {

			$this->getManufacturerDatabaseManagement()->mergeProduct($hdbProduct['ID_HDB_PRODUCT'], $mainProduct['ID_HDB_PRODUCT'], $hdbTable);
			$this->getManufacturerDatabaseManagement()->deleteProduct($hdbProduct['ID_HDB_PRODUCT'], $hdbTable);
		}

		$this->cancelProductMerge($hdbTable);

		return true;
	}


	/**
	 * @return ManufacturerDatabaseManagement
	 */
	public function getManufacturerDatabaseManagement() {
		if(self::$manufactuerDatabaseManagement == null) {
			self::$manufactuerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($this->getDb());
		}

		return self::$manufactuerDatabaseManagement;
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