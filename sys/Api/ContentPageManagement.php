<?php
/* ###VERSIONSBLOCKINLCUDE### */

class Api_ContentPageManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Api_ContentPageManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null, $langabbr = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_ContentPageManagement($db, $langval, $langabbr);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    private $langabbr;
    private $tplWrapper;

    function __construct(ebiz_db $db, $langval, $langabbr = null) {
        $this->db = $db;
        $this->langval = (int)$langval;
        $this->langabbr = $langabbr;
        if ($this->langabbr === null) {
            $this->langabbr = $GLOBALS["s_lang"];
            // Get language abbr (e.g. from langval "128" to abbr "de")
            $languageList = $GLOBALS["lang_list"];
            foreach ($languageList as $curLangIndex => $curLangDetails) {
                if ($langval == $curLangDetails["BITVAL"]) {
                    $this->langabbr = $curLangDetails["ABBR"];
                    break;
                }
            }
        }
        $this->tplWrapper = new Template($GLOBALS["ab_path"]."tpl/".$this->langabbr."/system-content-page-wrapper.htm");
        $this->tplWrapper->isTemplateCached = false;
        $this->tplWrapper->isTemplateRecursiveParsable = true;
    }

    public function cacheContentPageById( $id ) {
        $arContentPage = $this->getContentPageById($id);
        return $this->cacheContentPage($arContentPage);
    }

    public function cacheContentPage(&$arContentPage) {
        // info Cache Files schreiben
        $fp = @fopen($fname = $GLOBALS["ab_path"] . "cache/info/" . $this->langabbr . "." . $arContentPage['ID_INFOSEITE'] . ".htm", "w");
        if (!$fp) {
            die("Cache error! File unwriteable: " . $fname);
        }
        $this->tplWrapper->vars = $arContentPage;
        $this->tplWrapper->addvar("TXTTYPE_".$arContentPage["TXTTYPE"], 1);
        $this->tplWrapper->addvar("B_SYS_VAL", $arContentPage["B_SYS"] == "1" ? 1 : 0 );
        @fwrite($fp, str_replace("{T1}", $arContentPage["T1"], $this->tplWrapper->process(false)));
        @fclose($fp);
        chmod($fname, 0777);

    }

    public function getContentPageById($id) {
        $arContentPage = $this->db->fetch1("select t.*, s.V1, s.V2, s.T1
	    from `infoseite` t
		 left join string_info s on s.S_TABLE='infoseite'
		  and s.FK=t.ID_INFOSEITE and s.BF_LANG=if(t.BF_LANG_INFO & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_INFO+0.5)/log(2)))
		  where ID_INFOSEITE=".(int)$id);
        return $arContentPage;
    }

} 