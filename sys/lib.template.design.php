<?php
/* ###VERSIONSBLOCKINLCUDE### */



class TemplateDesignManagement {
	private static $db;
    private static $langval = 128;
	private static $instance = null;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return TemplateDesignManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function fetchAllTemplates() {
        $designPath = $this->getDesignPath();

        $designs = array();
        foreach (glob($designPath . '*') as $design) {
            $designs[basename($design)] = $this->getDesignInformation(basename($design));
        }

        return $designs;
    }

    public function existDesign($designName) {
        $designPath = $this->getDesignPath();
        return file_exists($designPath.$designName);
    }

    public function getDesignInformation($designName) {
        $designPath = $this->getDesignPath();

        $info = array();
        $designFile = $designPath.$designName.'/design.xml';
        if(is_file($designFile)) {
            $xml = simplexml_load_file($designFile);

            $info['ident'] = $designName;
            $info['name'] = (string)$xml->name;
            $info['description'] = (string)$xml->description;
        }

        return $info;
    }


    public  function getDesignPath() {
        global $ab_path;

        return $ab_path.'design/';
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


    public function getLangval() {
        return self::$langval;
    }
    public function setLangval($langval) {
        self::$langval = $langval;
    }

	private function __construct() {
	}
	private function __clone() {
	}
}
