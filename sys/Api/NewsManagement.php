<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_NewsManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Api_NewsManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_NewsManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;
    private $tplTmp;
    private $arCategoryToNav;

    function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = (int)$langval;

        $this->tplTmp = null;
        $this->arCategoryToNav = array();
    }

    public function fetchAll($arParams) {
        $resolveUrl = (array_key_exists("RESOLVE_URL", $arParams) ? $arParams["RESOLVE_URL"] : true);
        $getPreviewImage = (array_key_exists("GET_PREVIEW_IMAGE", $arParams) ? $arParams["GET_PREVIEW_IMAGE"] : true);
        $arResult = $this->db->fetch_table(
            $this->generateFetchQuery($arParams)
        );
        foreach ($arResult as $newsIndex => $arNews) {
            if ($resolveUrl) {
                $arResult[$newsIndex]["URL"] = $this->generateNewsUrl($arNews);
            }
            if ($getPreviewImage) {
                $arPreviewElement = $this->getPreviewElementData($arNews);
                if (is_array($arPreviewElement)) {
                    $arResult[$newsIndex] = array_merge($arResult[$newsIndex], array_flatten_trader7($arPreviewElement, true, "_", "PREVIEW_"));
                } else {
                    $arResult[$newsIndex]['PREVIEW_TYPE'] = false;
                    $arResult[$newsIndex]['PREVIEW_TYPE_IMAGE'] = false;
                    $arResult[$newsIndex]['PREVIEW_TYPE_VIDEO'] = false; 
                }
            }
        }
        return $arResult;
    }
    
    public function generateNewsUrl(&$arNews, $absolute = false, $useSSL = null) {
        $categoryId = $arNews["FK_KAT"];
        $navIdent = $this->getNavIdentByCategory($categoryId);
        $tplTmp = $this->getTemporaryTemplate();
        return $tplTmp->tpl_uri_action_full(
            $navIdent.",".$arNews["ID_NEWS"].",".addnoparse(chtrans($arNews["V1"])), $useSSL
        );
    }

    public function getMediaList(&$arNews, $langval = null) {
        if ($langval === null) {
            $langval = $this->langval;
        }
        $langvalMedia = ($arNews["BF_LANG_C"] & $langval ? $langval : 1 << floor(log($arNews["BF_LANG_C"]+0.5)/log(2)));
        $arMedia = ($arNews["SER_MEDIA"] === null ? array() : unserialize($arNews["SER_MEDIA"]));
        $arImages = $arImagesLang = ($arNews["SER_IMAGES"] === null ? array() : unserialize($arNews["SER_IMAGES"]));
        if (!empty($arMedia)) {
            if (array_key_exists("images", $arMedia)) {
                $arImages = $arMedia["images"];
            }
            $arImagesLang = (!array_key_exists($langvalMedia, $arImages) || !is_array($arImages[$langvalMedia]) ? array() : $arImages[$langvalMedia]);
        }
        $arVideos = array();
        if (array_key_exists("videos", $arMedia)) {
            $arVideos = $arMedia["videos"];
        }
        $arVideosLang = (!array_key_exists($langvalMedia, $arVideos) || !is_array($arVideos[$langvalMedia]) ? array() : $arVideos[$langvalMedia]);
        $arResult = array();
        foreach ($arVideosLang as $videoIndex => $videoPath) {
            $videoCode = null;
            if (preg_match("/^https:\/\/www\.youtube\.com\/embed\/(.+)$/", $videoPath, $arVideoMatch)) {
                $videoCode = $arVideoMatch[1];
            }
            $videoServer = rand(1, 4);
            $arResult[] = array(
                "TYPE"          => "VIDEO",
                "TYPE_VIDEO"    => 1,
                "PATH"          => $videoPath,
                "SERVER"        => $videoServer,
                "CODE"          => $videoCode,
                "URL"           => "https://i".$videoServer.".ytimg.com/vi/".$videoCode."/maxresdefault.jpg"     
            );
        }
        foreach ($arImagesLang as $imageIndex => $imagePath) {
            $arResult[] = array(
                "TYPE"          => "IMAGE",
                "TYPE_IMAGE"    => 1,
                "PATH"          => $imagePath
            );
        }
        return $arResult;
    }
    
    public function getImageUrl(&$arNews, $imageIndex = 0) {
        $langvalMedia = ($arNews["BF_LANG_C"] & $this->langval ? $this->langval : 1 << floor(log($arNews["BF_LANG_C"]+0.5)/log(2)));
        $arMedia = ($arNews["SER_MEDIA"] === null ? array() : unserialize($arNews["SER_MEDIA"]));
        $arImages = $arImagesLang = ($arNews["SER_IMAGES"] === null ? array() : unserialize($arNews["SER_IMAGES"]));
        if (!empty($arMedia)) {
            if (array_key_exists("images", $arMedia)) {
                $arImages = $arMedia["images"];
            }
            $arImagesLang = (!array_key_exists($langvalMedia, $arImages) || !is_array($arImages[$langvalMedia]) ? array() : $arImages[$langvalMedia]);
        }
        if ((count($arImagesLang) <= $imageIndex) || !array_key_exists($imageIndex, $arImagesLang)) {
            // Image index not found
            return null;
        }
        return $arImagesLang[$imageIndex];
    }

    public function getVideoUrl(&$arNews, $videoIndex = 0) {
        $langvalMedia = ($arNews["BF_LANG_C"] & $this->langval ? $this->langval : 1 << floor(log($arNews["BF_LANG_C"]+0.5)/log(2)));
        $arMedia = ($arNews["SER_MEDIA"] === null ? array() : unserialize($arNews["SER_MEDIA"]));
        $arVideos = array();
        if (array_key_exists("videos", $arMedia)) {
            $arVideos = $arMedia["videos"];
        }
        $arVideosLang = (!array_key_exists($langvalMedia, $arVideos) || !is_array($arVideos[$langvalMedia]) ? array() : $arVideos[$langvalMedia]);
        if ((count($arVideosLang) <= $videoIndex) || !array_key_exists($videoIndex, $arVideosLang)) {
            // Video index not found
            return null;
        }
        return $arVideosLang[$videoIndex];
    }

    public function getPreviewElement(&$arNews, $langval = null) {
        if ($langval === null) {
            $langval = $this->langval;
        }
        $langvalMedia = ($arNews["BF_LANG_C"] & $langval ? $langval : 1 << floor(log($arNews["BF_LANG_C"]+0.5)/log(2)));
        $arMedia = ($arNews["SER_MEDIA"] === null ? array() : unserialize($arNews["SER_MEDIA"]));
        $arPreviews = array();
        if (array_key_exists("preview", $arMedia)) {
            $arPreviews = $arMedia["preview"];
        }
        if ($langval != $langvalMedia) {
            $langBaseUrlActive = "/";
            $langBaseUrlMedia = "/";
            foreach ($GLOBALS["lang_list"] as $langAbbr => $langDetail) {
                if ($langDetail["BITVAL"] == $langval) {
                    $langBaseUrlActive = $langDetail["BASE_URL"];
                } else if ($langDetail["BITVAL"] == $langvalMedia) {
                    $langBaseUrlMedia = $langDetail["BASE_URL"];
                }
            }
            if (array_key_exists($langvalMedia, $arPreviews)) {
                $arPreviews[$langvalMedia] = preg_replace("/^".preg_quote($langBaseUrlMedia, "/")."/", $langBaseUrlActive, $arPreviews[$langvalMedia]);
            }
        }
        return (!array_key_exists($langvalMedia, $arPreviews) ? null : $arPreviews[$langvalMedia]);
    }

    public function getPreviewElementData(&$arNews, $langval = null) {
        $elementPath = $this->getPreviewElement($arNews, $langval);
        if ($elementPath === null) {
            return null;
        }
        if (preg_match("/^https:\/\/www\.youtube\.com\/embed\/(.+)$/", $elementPath, $arVideoMatch)) {
            $videoServer = rand(1, 4);
            return array(
                "TYPE"          => "VIDEO",
                "TYPE_VIDEO"    => 1,
                "PATH"          => $elementPath,
                "SERVER"        => $videoServer,
                "CODE"          => $arVideoMatch[1],
                "URL"           => "https://i".$videoServer.".ytimg.com/vi/".$arVideoMatch[1]."/maxresdefault.jpg"           
            );
        }
        return array(
            "TYPE"          => "IMAGE",
            "TYPE_IMAGE"    => 1,
            "PATH"          => $elementPath          
        );
    }

    private function generateFetchQuery($arParams) {
        $limit = (array_key_exists("LIMIT", $arParams) ? (int)$arParams["LIMIT"] : 10);
        $offset = (array_key_exists("OFFSET", $arParams) ? (int)$arParams["OFFSET"] : 0);
        $arWhere = array("OK=3");
        if (array_key_exists("ID_KAT", $arParams) && ($arParams["ID_KAT"] > 0)) {
            $arSearchKat = $this->db->fetch1("SELECT LFT, RGT, ROOT FROM `kat` WHERE ID_KAT=".(int)$arParams["ID_KAT"]);
            if (is_array($arParams)) {
                $arWhere[] = "(k.LFT>=".$arSearchKat["LFT"]." AND k.RGT<=".$arSearchKat["RGT"]." AND k.ROOT=".$arSearchKat["ROOT"].")";
            }
        }
        if ($arParams["TYPES"] > 0) {
            $arTopValues = array();
            if (($arParams["TYPES"] & 1) > 0) {
                $arTopValues[] = 1;
            }
            if (($arParams["TYPES"] & 2) > 0) {
                $arTopValues[] = 0;
            }
            $arWhere[] = "B_TOP IN (".implode(", ", $arTopValues).")";
        }
        return "
		select SQL_CALC_FOUND_ROWS
			t.*, s.V1, s.V2, s.T1, u.NAME,k.LFT,
			concat(m.VORNAME,' ',m.NACHNAME) as AUTOR ,
			m.NAME as AUTORUNAME, m.CACHE,
			ks.V1 as KATNAME,
			nav.IDENT
		from
			`news` t
		left join
			string_c s on s.S_TABLE='news'
				and s.FK=t.ID_NEWS
				and s.BF_LANG=if(t.BF_LANG_C & " . $this->langval . ", " . $this->langval . ", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))" . '
		LEFT JOIN
			user u ON FK_USER=ID_USER
		LEFT JOIN
			kat k ON FK_KAT=ID_KAT
		left join
			string_kat ks on ks.S_TABLE="kat"
				and ks.FK=k.ID_KAT
				and ks.BF_LANG=if(k.BF_LANG_KAT & ' . $this->langval . ", " . $this->langval . ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))" . '
		LEFT JOIN
			user m ON t.FK_AUTOR=m.ID_USER
		LEFT JOIN modul2nav m2n on t.FK_KAT=m2n.FK and S_MODUL="news_adv" and m2n.DARSTELLUNG = "news"
	  	LEFT JOIN nav on m2n.FK_NAV=nav.ID_NAV
		WHERE
			'.implode($arWhere, " AND ").'
        GROUP BY t.ID_NEWS
        ORDER BY STAMP DESC ,ID_NEWS DESC
			LIMIT ' . $limit. ' OFFSET '.$offset;
    }

    private function getNavIdentByCategory($categoryId) {
        if (array_key_exists($categoryId, $this->arCategoryToNav)) {
            return $this->arCategoryToNav[$categoryId];
        }
        $navIdent = $this->db->fetch_atom("
            SELECT n.IDENT FROM `nav` n
            JOIN `modul2nav` mn ON n.ID_NAV=mn.FK_NAV
            JOIN `kat` k1 ON k1.ID_KAT=".$categoryId."
            JOIN `kat` k2 ON k2.ID_KAT=mn.FK AND k1.LFT BETWEEN k2.LFT AND k2.RGT
            WHERE mn.S_MODUL='news_adv'
            ORDER BY k2.LFT DESC, (mn.DARSTELLUNG='news') DESC");
        $this->arCategoryToNav[$categoryId] = $navIdent;
        return $navIdent;
    }

    private function getTemporaryTemplate() {
        if ($this->tplTmp === null) {
            $this->tplTmp = new Template("tpl/de/empty.htm");
        }
        return $this->tplTmp;
    }

} 