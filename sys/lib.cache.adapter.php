<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once dirname(__FILE__).'/lib.cache.php';
require_once dirname(__FILE__).'/../admin/sys/lib.perm_admin.php';
require_once dirname(__FILE__).'/lib.ad_like.php';
require_once dirname(__FILE__).'/lib.cache.template.php';
require_once dirname(__FILE__).'/lib.cache.translation.php';


/**
 * Adapter zu neu schreiben von Cache Dateien, die vom System irgendwo her benötigt werden
 *
 * @TODO
 *
 * admin_welcome_flash.tmp          zur Laufzeit neu geschrieben
 * admin_welcome_stats_age_user.tmp         zur Laufzeit neu geschrieben
 * admin_welcome_stats_new_user.tmp         zur Laufzeit neu geschrieben
 *
 * fonts.php       in admin/tpl/vorlage_auswaehlern.php verwendet -> refactor
 *
 */
class CacheAdapter {

  private $lastError = false;
  public $cacheSteps = array(
    'less' => array(
      array('function' => '_cacheVoid', 'name' => ''),
      array('function' => '_cacheLess', 'name' => 'Less/Css Cache')
    ),
    'template' => array(
      array('function' => '_cacheVoid', 'name' => ''),
      array('function' => '_cacheDelete', 'name' => 'Cache leeren'),
      array('function' => '_cacheTranslation', 'name' => 'Übersetzungen'),
      array('function' => '_cacheTemplate', 'name' => 'Template Cache'),
      array('function' => '_cacheSubTemplate', 'name' => 'Subtemplates'),
      array('function' => '_cacheContent', 'name' => 'Inhalte')
    ),
    'content' => array(
      array('function' => '_cacheVoid', 'name' => ''),
      array('function' => '_cacheFaq', 'name' => 'FAQ und Hilfe'),
      array('function' => '_cacheInfobox', 'name' => 'Infobereiche'),
      array('function' => '_cacheAdCountPerCategory', 'name' => 'Marktplatz Kategorien (Artikel-Anzahl)'),
      array('function' => '_cacheKat', 'name' => 'Marktplatz Kategorien'),
      array('function' => '_cacheKatPerm', 'name' => 'Kategorieberechtigungen'),
      array('function' => '_cacheSecondaryKat', 'name' => 'Weitere Kategorien'),
      array('function' => '_cacheNav', 'name' => 'Navigationsstruktur'),
      array('function' => '_cacheUrls', 'name' => 'SEO-Urls'),
      array('function' => '_cachePagePerm', 'name' => 'Seitenberechtigungen'),
      array('function' => '_cacheLang', 'name' => 'Sprachen'),
      array('function' => '_cacheOption', 'name' => 'Marktplatz Einstellungen'),
      array('function' => '_cacheAdLikes', 'name' => 'Anzeigen Empfehlungen'),
      array('function' => '_cacheManufacturesSearchbox', 'name' => 'Hersteller Suchbox'),
      array('function' => '_cacheSubTemplate', 'name' => 'Subtemplates'),
      array('function' => '_cacheContent', 'name' => 'Inhalte'),
    ),
    'market_ads' => array(
      array('function' => '_cacheAdLikes', 'name' => 'Anzeigen Empfehlungen'),
      array('function' => '_cacheAdVolatile', 'name' => 'Anzeigen Cache')
    ),
    'all' => array(
      array('function' => '_cacheVoid', 'name' => ''),
      array('function' => '_cacheDelete', 'name' => 'Cache leeren'),
      array('function' => '_cacheTranslation', 'name' => 'Übersetzungen'),
      array('function' => '_cacheTemplate', 'name' => 'Template Cache'),
      array('function' => '_cacheSubTemplate', 'name' => 'Subtemplates'),
      array('function' => '_cacheContent', 'name' => 'Inhalte'),
      array('function' => '_cacheLess', 'name' => 'Less/Css Cache'),
      array('function' => '_cacheFaq', 'name' => 'FAQ und Hilfe'),
      array('function' => '_cacheInfobox', 'name' => 'Infobereiche'),
      array('function' => '_cacheAdCountPerCategory', 'name' => 'Marktplatz Kategorien (Artikel-Anzahl)'),
      array('function' => '_cacheKat', 'name' => 'Marktplatz Kategorien'),
      array('function' => '_cacheKatPerm', 'name' => 'Kategorieberechtigungen'),
      array('function' => '_cacheSecondaryKat', 'name' => 'Weitere Kategorien'),
      array('function' => '_cacheNav', 'name' => 'Navigationsstruktur'),
      array('function' => '_cacheUrls', 'name' => 'SEO-Urls'),
      array('function' => '_cachePagePerm', 'name' => 'Seitenberechtigungen'),
      array('function' => '_cacheLang', 'name' => 'Sprachen'),
      array('function' => '_cacheOption', 'name' => 'Marktplatz Einstellungen'),
      array('function' => '_cacheAdLikes', 'name' => 'Anzeigen Empfehlungen'),
      array('function' => '_cacheManufacturesSearchbox', 'name' => 'Hersteller Suchbox'),
    )
  );

	public function __construct() {

    }

    public function cacheAll()
    {
        // Clear all caches
        $this->_cacheDelete();
        $this->_cacheTranslation();
        $this->_cacheTemplate();
        $this->_cacheSubTemplate();
		$this->_cacheContent();
        $this->_cacheLess();
        $this->_cacheFaq();
        $this->_cacheInfobox();
        $this->_cacheAdCountPerCategory();
        $this->_cacheKat();
        $this->_cacheKatPerm();
        $this->_cacheNav();
        $this->_cacheUrls();
        $this->_cachePagePerm();
        $this->_cacheLang();
        $this->_cacheOption();
        $this->_cacheAdLikes();
        $this->_cacheManufacturesSearchbox();
    }

    public function cacheInstall()
    {
        // Clear all caches
        $this->_cacheDelete();
        $this->_cacheTranslation();
        $this->_cacheTemplate();
        $this->_cacheSubTemplate();
		$this->_cacheContent();
        $this->_cacheFaq();
        $this->_cacheInfobox();
        $this->_cacheAdCountPerCategory();
        $this->_cacheKat();
        $this->_cacheKatPerm();
        $this->_cacheNav();
        $this->_cacheUrls();
        $this->_cachePagePerm();
        $this->_cacheLang();
        $this->_cacheOption();
        $this->_cacheAdLikes();
        $this->_cacheManufacturesSearchbox();
    }

    public function cacheTemplate() {
        $this->_cacheTemplate();
    }
    
    public function cacheLess() {
        $this->_cacheLess();
    }

    public function cacheUrls() {
        $this->_cacheUrls();
    }

	public function cacheContent() {
		$this->_cacheFaq();
		$this->_cacheInfobox();
        $this->_cacheAdCountPerCategory();
		$this->_cacheKat();
		$this->_cacheKatPerm();
		$this->_cacheNav();
        $this->_cacheUrls();
		$this->_cachePagePerm();
		$this->_cacheLang();
		$this->_cacheOption();
        $this->_cacheAdLikes();
		$this->_cacheManufacturesSearchbox();
	}

	public function cacheStep($step = 0, $type) {
		if(array_key_exists($step, $this->cacheSteps[$type])) {
			$cacheStep = $this->cacheSteps[$type][$step];
			if(method_exists($this, $cacheStep['function'])) {
				call_user_func(array($this, $cacheStep['function']));

				if(array_key_exists($step + 1, $this->cacheSteps[$type])) {
					return array(
						'currentStep' => array(
                            'step' => $step,
                            'name' => $cacheStep['name'],
                            'iframe' => (array_key_exists('iframe', $cacheStep) ? $cacheStep['iframe'] : null)  
                        ),
						'countSteps' => count($this->cacheSteps[$type]),
						'nextStep' => array(
                            'step' => $step + 1,
                            'name' => $this->cacheSteps[$type][$step + 1]['name'],
                            'iframe' => (array_key_exists('iframe', $this->cacheSteps[$type][$step + 1]) ? $this->cacheSteps[$type][$step + 1]['iframe'] : null)
                        )
					);
				} else {
					return array(
                        'currentStep' => array(
                            'step' => $step,
                            'name' => $cacheStep['name'],
                            'iframe' => (array_key_exists('iframe', $cacheStep) ? $cacheStep['iframe'] : null)
                        ),
						'countSteps' => count($this->cacheSteps[$type])
					);
				}
			}
		}
		return false;
	}

	public function _cacheVoid() {

	}

	public function _cacheFaq() {
        cache_faq();
    }

	public function _cacheInfobox() {
        update_infocache();
    }

	public function _cacheKat() {
        global $db, $langval;

        $langvalBackup = $langval;

        $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
        for ($i = 0; $i < count($ar_lang); $i++) {
            $GLOBALS['langval'] = $ar_lang[$i]['BITVAL'];

            cache_kat();
        }

        $langval = $langvalBackup;
    }

	public function _cacheSecondaryKat() {
		cache_kat_vendor();
		cache_kat_request();
		cache_kat_job();
		cache_kat_events();
		cache_kat_clubs();
	}

	public function _cacheKatPerm() {

        katperm2role_rewrite(-1);
    }

	public function _cacheNav() {
        global $db, $ab_path, $langval, $s_lang;

        $langvalBackup = $langval;
        $s_langBackup = $s_lang;

        $navRoots = $db->fetch_table("SELECT ROOT FROM nav GROUP BY ROOT");

        $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
        for ($i = 0; $i < count($ar_lang); $i++) {
            $GLOBALS['langval'] = $ar_lang[$i]['BITVAL'];
            $GLOBALS['s_lang'] = $ar_lang[$i]['ABBR'];

            foreach($navRoots as $key => $value) {
                cache_nav_all($value['ROOT']);
            }
        }

        $langval = $langvalBackup;
        $s_lang = $s_langBackup;
    }

	public function _cachePagePerm() {
        cache_nav(-1);
    }


    /**
     * Erzeugt unabhängig vom aktuellen Systemzustand die folgeden Cache Dateien neu
     *
     * cache/lang.php
     * cache/lang.[SPRACHE].php
     *
     * @return void
     */
	public function _cacheLang() {
        global $db, $ab_path, $langval, $s_lang;

        $langvalBackup = $langval;
        $s_langBackup = $s_lang;


        $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
        for ($i = 0; $i < count($ar_lang); $i++) {
            $GLOBALS['langval'] = $ar_lang[$i]['BITVAL'];
            $GLOBALS['s_lang'] = $ar_lang[$i]['ABBR'];

            $s_lang = $ar_lang[$i]['ABBR'];

            $liste = $db->fetch_table($db->lang_select('lang') . 'where B_PUBLIC=1 order by BITVAL desc', 'ABBR');
            $s_code = '<?' . 'php $lang_list = ' . php_dump($liste) . '; ?' . '>';
            $fp = fopen($c_file = $ab_path.'cache/lang.' . $s_lang . '.php', 'w');
            fputs($fp, $s_code);
            fclose($fp);
            chmod($c_file, 0777);


            $tmp = array_values($liste);
            $s_code = '<?'. 'php
                if (($s_lang = $_REQUEST["lang"]) || (SESSION && ($s_lang = $_SESSION["lang"])))
                {
                  @include "cache/lang.$s_lang.php";
                  if (!$lang_list)
                    $s_lang = false;
                }
                if (!$s_lang)
                {
                  $s_lang = "'. $tmp[0]['ABBR']. '";
                  @include "cache/lang.$s_lang.php";
                }
                $langval = $lang_list[$s_lang]["BITVAL"];
                if (SESSION)
                  $_SESSION["lang"] = $s_lang;
                else
                  $ar_urlrewritevars["lang"] = $s_lang;
                ?'. '>
            ';
            $fp = fopen($c_file = $ab_path.'cache/lang.php', 'w');
            fputs($fp, $s_code);
            fclose($fp);
            chmod($c_file, 0777);

        }

        $langval = $langvalBackup;
        $s_lang = $s_langBackup;

    }


	public function _cacheOption() {
        global $db, $ab_path;

        $data = $db->fetch_table("select * from `option` order by plugin, typ");
        $ar = array ();
        foreach ($data as $row) {
            $ar[$row['plugin']][$row['typ']] = $row['value'];
        }
        $s_code = '<?'. 'php $nar_systemsettings = '. php_dump($ar, 0). '; ?'. '>';
        $fp = fopen($file_name = $ab_path.'cache/option.php', 'w');
        fputs($fp, $s_code);
        fclose ($fp);
        chmod($file_name, 0777);
    }

	public function _cacheAdLikes() {
        global $db;

        $GLOBALS['s_lang'] = 'de';

        $adLikeManagement = AdLikeManagement::getInstance($db);
        $adLikeManagement->flushCache();
    }

    public function _cacheDelete() {
        // Trigger cache event
        $eventParams = new Api_Entities_EventParamContainer(array());
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::SYSTEM_CACHE_ALL, $eventParams);
        // Cache template index
        $cachePath = $GLOBALS["ab_path"].'cache/design/';
        if (is_dir($cachePath.'_INDEX')) {
            system('rm -R ' . $cachePath.'_INDEX');
        }
    }
    
    public function _cacheAdCountPerCategory() {
	    $GLOBALS["db"]->querynow("TRUNCATE TABLE `kat_statistic`");
    }
    
    public function _cacheAdVolatile() {
        global $db;
        
        // Allow request to be canceled
        $optIgnoreUserAbort = ignore_user_abort(false);
        // Clear cache of all active ads
        $arActiveAds = array_keys($db->fetch_nar("SELECT ID_AD_MASTER FROM `ad_master` WHERE STATUS=1"));
        foreach ($arActiveAds as $adIndex => $adId) {
            Api_Entities_MarketplaceArticle::getById($adId, false)->clearVolatileCache();
        }
        // Restore original setting
        ignore_user_abort($optIgnoreUserAbort);
    }

    public function _cacheTemplate() {
        $cacheTemplate = new CacheTemplate();
        $cacheTemplate->cacheAll();
        if ($cacheTemplate->getLastError() !== false) {
            $this->lastError = $cacheTemplate->getLastError();
        }
    }
	
	public function _cacheContent() {
        require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
        $cacheAdmin = new CacheAdmin();
        $cacheAdmin->emptyCacheCategory("marketplace");
        $cacheAdmin->emptyCacheCategory("vendor");
    }

    public function _cacheSubTemplate() {
        // Subtemplate caches
        require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
        $cacheAdmin = new CacheAdmin();
        $cacheAdmin->emptyCacheCategory("subtpl");
    }

    public function _cacheLess() {
        global $db;
        $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
        for ($i = 0; $i < count($ar_lang); $i++) {
            $s_lang = $ar_lang[$i]['ABBR'];
            Design_Less_DesignCompiler::generateCss("resources/".$s_lang."/css/design.css", null, array(
                "@icon-font-path" => '"../fonts/"'
            ));
        }
    }

    public function _cacheUrls() {
        require_once $GLOBALS["ab_path"]."sys/lib.nav.url.php";
        $navUrlMan = NavUrlManagement::getInstance($GLOBALS["db"]);
        $ar_lang = $GLOBALS["db"]->fetch_table("select ID_LANG,BITVAL,ABBR from lang where B_PUBLIC=1");
        foreach ($ar_lang as $langIndex => $langDetails) {
            $navUrlMan->updateCache($langDetails["ID_LANG"]);
        }
    }

    public function _cacheTranslation() {
        $cacheTranslation = new CacheTranslation();
        $cacheTranslation->cacheAll();
    }

	public function _cacheManufacturesSearchbox() {
		global $db, $ab_path, $langval, $s_lang;

		$ar_lang = $db->fetch_table("select BITVAL,ABBR from lang where B_PUBLIC=1");
		for ($i = 0; $i < count($ar_lang); $i++) {
			if(file_exists($ab_path."cache/marktplatz/sbox_manufacture_".$ar_lang[$i]['ABBR'].".htm")) {
				unlink($ab_path."cache/marktplatz/sbox_manufacture_".$ar_lang[$i]['ABBR'].".htm");
			}
            if(file_exists($ab_path."cache/marktplatz/hdb_manufacturers_".$ar_lang[$i]['ABBR'].".htm")) {
                unlink($ab_path."cache/marktplatz/hdb_manufacturers_".$ar_lang[$i]['ABBR'].".htm");
            }
		}
	}

    /**
     * Get the last error message
     * @return  string|bool     The error message (false if no error occured)
     */
    public function getLastError() {
        return $this->lastError;
    }
}