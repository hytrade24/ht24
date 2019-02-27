<?php
/* ###VERSIONSBLOCKINLCUDE### */


class AdExportFilterManagement {
	private static $instance = null;
    /**
     * @var array
     */
    private static $filter = array(
        'TestFilter' => "Filter Test"
    );

	/**
	 * Singleton
	 *
	 * @return AdExportFilterManagement
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

    public function getFilters() {
        $result = array();
        foreach(self::$filter as $key => $value) {
            $result[] = array('FILTERKEY' => $key, 'NAME' => $value);
        }
        return $result;
    }

    /**
     * @param $filterName
     * @param $data
     * @return array
     */
    public function applyFilter($filterName, $data) {
        if(method_exists($this, "applyFilter".$filterName)) {
            return $this->{applyFilter.$filterName}($data);
        } else {
            throw new Exception("Filter not found");
        }
    }

    /** FILTER */

    /**
     * Test Filter der nur Name und Beschreibung als Spalte exportiert
     *
     * @param $data
     * @return array
     */
    public function applyFilterTestFilter($data) {
        $result = array();

        $result = array(
            'PRODUKTNAME' => $data['PRODUKTNAME'],
            'BESCHREIBUNG' => $data['BESCHREIBUNG']
        );

        return $result;
    }


	private function __construct() {
	}
	private function __clone() {
	}
}