<?php
/* ###VERSIONSBLOCKINLCUDE### */

class ClubManagement {
	private static $db;
	private static $instance = NULL;

	const ALLOW_MEMBER_REQUESTS_NOT_ALLOWED = 0;
	const ALLOW_MEMBER_REQUESTS_CONFIRMATION = 2;
	const ALLOW_MEMBER_REQUESTS_ALLOWED = 1;


	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return ClubManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
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

	/**
	 * Einladung in einen Club annehmen.
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 * @return boolean
	 */
	public function acceptInvite($clubId, $userId, $overrideRights = false) {
		global $uid;
		$db = $this->getDb();
		$id_invite = $db->fetch_atom("SELECT ID_CLUB_INVITE FROM `club_invite`
				WHERE FK_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);
		if ($id_invite > 0) {
			$db->querynow($query = "DELETE FROM `club_invite` WHERE ID_CLUB_INVITE=".(int)$id_invite);
			$this->addMember($clubId, $userId, true);
			return true;
		} else {
			// Keine Einladung gefunden
			return false;
		}
	}

	public function existInviteForUserInClub($userId, $clubId) {
		return $this->getDb()->fetch_atom("SELECT COUNT(*) FROM club_invite WHERE FK_CLUB = '".(int)$clubId."' AND FK_USER = '".(int)$userId."' ");
	}

	/**
	 * Einen User als Clubmitglied hinzufügen
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 */
	public function addMember($clubId, $userId, $overrideRights = false) {
		global $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
			$db->querynow($query = "INSERT INTO `club2user` (FK_CLUB, FK_USER, STAMP_JOIN)
					VALUES (".(int)$clubId.", ".(int)$userId.", NOW())");
			return true;
		} else {
			// NICHT DER BESITZER DES CLUBS!
			eventlog("error", "Konnte Club-Mitglied nicht hinzufügen, unzureichende Rechte!",
				"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			return false;
		}
	}

	/**
	 * Einen User als Clubmitglied hinzufügen
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 */
	public function addModerator($clubId, $userId, $overrideRights = false) {
		return $this->setModerator($clubId, $userId, 1, $overrideRights);
	}


	/**
	 * Dem Club einen neuen Suchbegriff hinzufügen
	 *
	 * @param int		$clubId
	 * @param string	$searchWord
	 * @param int		$language
	 * @return boolean
	 */
    public function addSearchWord($clubId, $searchWord, $language = 128) {
		global $uid;
		$searchWord = trim($searchWord);
		if (empty($searchWord)) return false;
        require_once ("admin/sys/lib.search.php");
		$db = $this->getDb();
        $langvalAsCode = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE BITVAL=".(int)$language);
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
            $doSearch = new do_search($langvalAsCode, true);
            $doSearch->add_new_word($searchWord, $clubId, "club");
            return true;
        }
        return false;
    }

	/**
	 * Einladung in einen Club widerrufen.
	 *
	 * @param int		$clubId
	 * @param int		$clubInviteId
	 * @return boolean
	 */
	public function cancelInvite($clubId, $clubInviteId) {
		global $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
			$ar_invite = $db->fetch1("SELECT * FROM `club_invite`
					WHERE FK_CLUB=".(int)$clubId." AND ID_CLUB_INVITE=".(int)$clubInviteId);
			if (!empty($ar_invite)) {
				$db->querynow($query = "DELETE FROM `club_invite` WHERE ID_CLUB_INVITE=".(int)$clubInviteId);
				if ($ar_invite['FK_USER'] > 0) {
					$userId = (int)$ar_invite['FK_USER'];
					$ar_user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".(int)$userId);
					$mail_content = $this->getClubById($clubId);
					foreach ($ar_user as $field => $value) {
						$mail_content['USER_'.$field] = $value;
					}
					$mail_content['MESSAGE'] = $message;
					sendMailTemplateToUser(0, $userId, 'CLUB_INVITE_REVOKED', $mail_content);
				} else {
					$mail_content = $this->getClubById($clubId);
					$mail_content['USER_NAME'] = $ar_invite['NAME'];
					$mail_content['MESSAGE'] = $message;
					sendMailTemplateToUser(0, $ar_invite['EMAIL'], 'CLUB_INVITE_REVOKED', $mail_content);
				}
				return true;
			} else {
				// Keine Einladung gefunden
				return false;
			}
		} else {
			// NICHT DER BESITZER DES CLUBS!
			eventlog("error", "Konnte Club-Einladung nicht widerrufen, unzureichende Rechte!",
				"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			return false;
		}
	}

	/**
	 * Einladung in einen Club widerrufen.
	 *
	 * @param int		$clubId
	 * @param int		$clubInviteId
	 * @return boolean
	 */
	public function countGallery($clubId) {
		global $uid;
		$db = $this->getDb();
		$count = $db->fetch_atom("SELECT count(*) FROM `club_gallery` WHERE FK_CLUB=".(int)$clubId);
		$count += $db->fetch_atom("SELECT count(*) FROM `club_gallery_video` WHERE FK_CLUB=".(int)$clubId);
		return $count;
	}

	/**
	 * Einladung in einen Club annehmen.
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 * @return boolean
	 */
	public function declineInvite($clubId, $userId, $overrideRights = false) {
		global $uid;
		$db = $this->getDb();
		$id_invite = $db->fetch_atom("SELECT ID_CLUB_INVITE FROM `club_invite`
				WHERE FK_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);
		if ($id_invite > 0) {
			$db->querynow($query = "DELETE FROM `club_invite` WHERE ID_CLUB_INVITE=".(int)$id_invite);
			return true;
		} else {
			// Keine Einladung gefunden
			return false;
		}
	}

	/**
	 * Löscht den angegebenen Club samt aller Mitglieder.
	 *
	 * @param int		$clubId
	 * @return boolean
	 */
	public function deleteClub($clubId, $overrideRights = false) {
		global $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
			if (!empty($ar_club["LOGO"])) {
				unlink('cache/club/logo/'.$ar_club["LOGO"]);
			}
			// Kommentare löschen
			require_once $ab_path."sys/lib.comment.php";
			$cmNews = CommentManagement::getInstance($db, 'club');
			$cmNews->deleteAllComments($clubId);
			// Club löschen
			$db->querynow($query = "DELETE FROM `string_club` WHERE S_TABLE='club' AND FK=".(int)$clubId);
			$db->querynow($query = "DELETE FROM `club2user` WHERE FK_CLUB=".(int)$clubId);
			$db->querynow($query = "DELETE FROM `club_invite` WHERE FK_CLUB=".(int)$clubId);
			$db->querynow($query = "DELETE FROM `club` WHERE ID_CLUB=".(int)$clubId);
			return true;
		} else {
			// NICHT DER BESITZER DES CLUBS!
			eventlog("error", "Konnte Club nicht löschen, unzureichende Rechte!",
				"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			return false;
		}
	}

	/**
	 * Löscht ein Bild aus der Gallerie des Clubs
	 *
	 * @param $clubId
	 * @param $videoId
	 * @return bool
	 */
	public function deleteFile($clubId, $imageId, $overrideRights = false) {
		global $nar_systemsettings, $uid, $ab_path;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
			$filename = $db->querynow("SELECT FILENAME FROM `club_gallery`
					WHERE ID_CLUB_GALLERY=".(int)$imageId." AND FK_CLUB=".(int)$clubId);
			$dir = $ab_path.'cache/clubs/'.(int)$clubId;
			$file = $dir.'/'.$filename;
			if (!empty($filename)) {
				$db->querynow("DELETE FROM `club_gallery` WHERE ID_CLUB_GALLERY=".(int)$imageId);
				unlink($file);
			}
			return true;
		}
		return false;
	}

	/**
	 * Löscht ein Video aus der Gallerie des Clubs
	 *
	 * @param $clubId
	 * @param $videoId
	 * @return bool
	 */
	public function deleteVideo($clubId, $videoId, $overrideRights = false) {
		global $nar_systemsettings, $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
			$db->querynow("DELETE FROM `club_gallery_video`
					WHERE ID_CLUB_GALLERY_VIDEO=".(int)$videoId." AND FK_CLUB=".(int)$clubId);
			return true;
		}
		return false;
	}

	/**
	 * Löscht den Suchbegriffeines eines Clubs
	 *
	 * @param unknown $searchWord
	 * @param unknown $userId
	 * @param string $language
	 * @return boolean
	 */
    public function deleteSearchWord($clubId, $searchWord, $language = 128) {
		global $uid;
		$searchWord = trim($searchWord);
		if (empty($searchWord)) return false;
        require_once ("admin/sys/lib.search.php");
        $db = $this->getDb();
        $langvalAsCode = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE BITVAL=".(int)$language);
        $ar_club = $this->getClubById($clubId);
        if (!empty($ar_club)) {
            $doSearch = new do_search($langvalAsCode, true);
            $doSearch->delete_word_from_searchindex($searchWord, $clubId, "club");

            return true;
        }
        return false;
    }

	/**
	 *
	 * @param unknown $param
	 * @return Ambigous <multitype:, multitype:unknown >
	 */
    public function getClubsByParams($param, $language = 128) {
        $db = $this->getDb();
        $langvalAsCode = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE BITVAL=".(int)$language);

        $sqlLimit = "";
        $sqlWhere = "";
        $sqlJoin = "";
        $sqlOrder = " c.ID_CLUB ";
        $sqlSelect = "";

        if ($param['GET_CATEGORY']) {
        	$sqlJoin .= ' LEFT JOIN club_category c_cat ON c_cat.FK_CLUB = c.ID_CLUB 
        	                LEFT JOIN string_kat str_kat ON str_kat.FK = c_cat.FK_KAT
        	                AND str_kat.BF_LANG = "'.$language.'" and str_kat.S_TABLE = "kat" ';

        	$sqlSelect .= ' str_kat.V1 as CLUB_CATEGORY, c_cat.FK_KAT, ';
        }

        if ($param['ALLOW_MEMBER_REQUESTS']) {
        	//$sqlSelect .= ' CONCAT("ALLOW_MEMBER_REQUESTS_",c.ALLOW_MEMBER_REQUESTS) as ALLOW_MEMBER_REQUESTS, ';
	        $sqlSelect .= ' c.ALLOW_MEMBER_REQUESTS, ';
        }

        if(isset($param['STATUS'])) {
            if ($param['STATUS'] != "ALL") {
                $sqlWhere .= " AND c.STATUS=".(int)$param['STATUS'];
            }
        } else {
            $sqlWhere .= " AND c.STATUS=1";
        }
        if(isset($param['SEARCHCLUB']) && $param['SEARCHCLUB'] != null) {
            if (is_array($param['SEARCHCLUB_WHAT'])) {
                $sql_searchclub = array();
                if (in_array("NAME", $param['SEARCHCLUB_WHAT'])) {
                    $sql_searchclub[] = "(c.NAME LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%')";
                }
                if (in_array("KEYWORDS", $param['SEARCHCLUB_WHAT'])) {
                    $sql_searchclub[] = "(sw.wort LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%')";
                }
                if (in_array("DESCRIPTION", $param['SEARCHCLUB_WHAT'])) {
                    $sql_searchclub[] = "(CLUB_DESCRIPTION LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%')";
                }
                $sqlWhere .= " AND (".implode(" OR ", $sql_searchclub).") ";
            } else {
                // Search title and keywords by default
                $sqlWhere .= " AND ((sw.wort LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%') OR (c.NAME LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%')) ";
            }
        }
        if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".(int)$param['CATEGORY']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= " . $row_kat["LFT"] . ") AND
                (RGT <= " . $row_kat["RGT"] . ") AND
                (ROOT = " . $row_kat["ROOT"] . ")
            ");

            $sqlJoin .= "\nLEFT JOIN club_category cc ON c.ID_CLUB = cc.FK_CLUB";
            $sqlWhere .= " AND cc.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
        }
        if(isset($param['ORT']) && $param['ORT'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (c.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR c.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%') "; }
        if(isset($param['PLZ']) && $param['PLZ'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (c.PLZ LIKE '".mysql_real_escape_string($param['PLZ'])."%') "; }
        if(isset($param['FK_COUNTRY']) && $param['FK_COUNTRY'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND c.FK_COUNTRY = '".mysql_real_escape_string($param['FK_COUNTRY'])."' "; }
        if(isset($param['FK_USER']) || isset($param['NAME_'])) {
            if(isset($param['FK_USER'])) {
                $sqlJoin .= "LEFT JOIN `club2user` cu ON cu.FK_CLUB=c.ID_CLUB AND cu.FK_USER=".(int)$param['FK_USER']."\n";
            } else if(isset($param['NAME_'])) {
                // Get matching users
                $idUsers = array_keys($db->fetch_nar("SELECT ID_USER FROM `user` WHERE NAME LIKE '%".mysql_real_escape_string($param['NAME_'])."%'"));
                $sqlJoin .= "LEFT JOIN `club2user` cu ON cu.FK_CLUB=c.ID_CLUB AND cu.FK_USER IN (".implode(", ", $idUsers).")\n";
            }
            if (isset($param['FK_USER_STATUS'])) {
                switch ($param['FK_USER_STATUS']) {
                    case 'LEADER':
                        // User is the club leader
                        $sqlWhere .= " AND (c.FK_USER=cu.FK_USER)";
                        break;
                    case 'MODERATOR':
                        // User has to be moderator or above
                        $sqlWhere .= " AND (cu.MODERATOR=1 OR c.FK_USER=cu.FK_USER)";
                        break;
                    default:
                        // User has to be member
                        $sqlWhere .= " AND cu.FK_USER IS NOT NULL";
                        break;
                }
            }
        }

        if(isset($param['LATITUDE']) && $param['LATITUDE'] != "" && isset($param['LONGITUDE']) && $param['LONGITUDE'] != "" && isset($param['LU_UMKREIS']) && $param['LU_UMKREIS'] != "" ) {
            $radius = 6368;

            $rad_b = $param['LATITUDE'];
            $rad_l = $param['LONGITUDE'];

            $rad_l = $rad_l / 180 * M_PI;
            $rad_b = $rad_b / 180 * M_PI;

            $sqlWhere .= " AND (
                    " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(c.LATITUDE)) *
                     cos(" . $rad_b . ") * (sin(RADIANS(c.LONGITUDE)) *
                     sin(" . $rad_l . ") + cos(RADIANS(c.LONGITUDE)) *
                     cos(" . $rad_l . ")) - sin(RADIANS(c.LATITUDE)) * sin(" . $rad_b . "))))
                ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $param['LU_UMKREIS']);
        }
        if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }
        if(isset($param['BF_LANG']) && $param['BF_LANG'] != null) { $language = $param['BF_LANG']; }

        if(isset($param['TOP'])) {
            // Nur Top-User
            $sqlWhere .= " AND u.TOP_USER=1 ";
        }

        if(isset($param['SORT']) && isset($param['SORT_DIR'])) { $sqlOrder = $param['SORT']." ".$param['SORT_DIR']; }


        $q = "
            SELECT
            	".$sqlSelect."
                c.CHANGED,
                c.STAMP,
                c.ID_CLUB AS CLUB_ID_CLUB,
                c.LOGO AS CLUB_LOGO,
                c.NAME AS CLUB_NAME,
                c.STATUS AS CLUB_STATUS,
                IF(c.PLZ != '', c.PLZ, u.PLZ) AS CLUB_PLZ,
                IF(c.ORT != '', c.ORT, u.ORT) AS CLUB_ORT,
                (SELECT V1 FROM string WHERE S_TABLE = 'country' AND FK = IF(c.FK_COUNTRY != '', c.FK_COUNTRY, u.FK_COUNTRY) AND BF_LANG = '".$language."') AS CLUB_COUNTRY,
                IF(c.TEL != '', c.TEL, u.TEL) AS CLUB_TEL,
                IF(c.FAX != '', c.FAX, u.FAX) AS CLUB_FAX,
                IF(c.URL != '', c.URL, u.URL) AS CLUB_URL,
                u.ID_USER AS USER_ID_USER,
                u.NAME AS USER_NAME,
                u.TOP_USER AS USER_TOP_USER,
                u.TOP_SELLER AS USER_TOP_SELLER,
                u.PROOFED AS USER_PROOFED,
                (SELECT count(*) FROM `club_gallery` where FK_CLUB = c.ID_CLUB) as COUNT_GALLERY,
                (SELECT count(*) FROM `calendar_event` where FK_REF = c.ID_CLUB and FK_REF_TYPE = 'club' AND PRIVACY = 1 AND IS_CONFIRMED = 1 AND STAMP_END >= now()) as COUNT_EVENTS,
                (SELECT count(*) FROM `comment` where FK = c.ID_CLUB and `TABLE` = 'club' and IS_PUBLIC = 1 and IS_REVIEWED = 1 and IS_CONFIRMED = 1) as COUNT_COMMENTS,
                (SELECT count(*) FROM `club2user` WHERE FK_CLUB=c.ID_CLUB) as MEMBERS,
                (SELECT T1 FROM string_club WHERE S_TABLE = 'club' AND FK = c.ID_CLUB AND BF_LANG = if(c.BF_LANG_CLUB & ".$language.", ".$language.", 1 << floor(log(c.BF_LANG_CLUB+0.5)/log(2)))) AS CLUB_DESCRIPTION,
                (SELECT substring(T1, 1, 200) FROM string_club WHERE S_TABLE = 'club' AND FK = c.ID_CLUB AND BF_LANG = if(c.BF_LANG_CLUB & ".$language.", ".$language.", 1 << floor(log(c.BF_LANG_CLUB+0.5)/log(2)))) AS CLUB_SHORT_DESCRIPTION,
                RATING
            FROM
                club c
            JOIN user u ON u.ID_USER = c.FK_USER
            LEFT JOIN searchdb_index_".$langvalAsCode." si ON (si.S_TABLE = 'club' AND si.FK_ID = c.ID_CLUB)
            LEFT JOIN searchdb_words_".$langvalAsCode." sw ON sw.ID_WORDS = si.FK_WORDS
            ".$sqlJoin."
            ".(!empty($sqlWhere) ? "WHERE ".substr($sqlWhere, 5) : '')."
            GROUP BY c.ID_CLUB
            ORDER BY ".$sqlOrder."
            ".($sqlLimit?'LIMIT '.$sqlLimit:'');
            // print_r($q);
        $result =  $db->fetch_table($q);
        return $result;
    }

    public function countClubsByParam($param, $language = 128) {
        $db = $this->getDb();
        $langvalAsCode = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE BITVAL=".(int)$language);

        $sqlLimit = "";
        $sqlWhere = "";
        $sqlJoin = "";
        $sqlOrder = " c.ID_VENDOR ";

        if(isset($param['STATUS'])) {
            if ($param['STATUS'] != "ALL") {
                $sqlWhere .= " AND c.STATUS=".(int)$param['STATUS'];
            }
        } else {
            $sqlWhere .= " AND c.STATUS=1";
        }
        if(isset($param['SEARCHCLUB']) && $param['SEARCHCLUB'] != null) { $sqlWhere .= " AND ((sw.wort LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%') OR (c.NAME LIKE '%".mysql_real_escape_string($param['SEARCHCLUB'])."%')) "; }
        if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".(int)$param['CATEGORY']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= " . $row_kat["LFT"] . ") AND
                (RGT <= " . $row_kat["RGT"] . ") AND
                (ROOT = " . $row_kat["ROOT"] . ")
            ");

            $sqlJoin .= "\nLEFT JOIN club_category cc ON c.ID_CLUB = cc.FK_CLUB";
            $sqlWhere .= " AND cc.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
        }
        if(isset($param['ORT']) && $param['ORT'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (c.ORT LIKE '".mysql_real_escape_string($param['ORT'])."%' OR c.PLZ LIKE '".mysql_real_escape_string($param['ORT'])."%') "; }
        if(isset($param['PLZ']) && $param['PLZ'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND (c.PLZ LIKE '".mysql_real_escape_string($param['PLZ'])."%') "; }
        if(isset($param['FK_COUNTRY']) && $param['FK_COUNTRY'] != null && (!isset($param['LATITUDE']) || $param['LATITUDE'] == "")) { $sqlWhere .= " AND c.FK_COUNTRY = '".mysql_real_escape_string($param['FK_COUNTRY'])."' "; }
        if(isset($param['FK_USER']) && $param['FK_USER'] != null) { $sqlWhere .= " AND c.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' "; }

        if(isset($param['LATITUDE']) && $param['LATITUDE'] != "" && isset($param['LONGITUDE']) && $param['LONGITUDE'] != "" && isset($param['LU_UMKREIS']) && $param['LU_UMKREIS'] != "" ) {
        	$radius = 6368;

        	$rad_b = $param['LATITUDE'];
        	$rad_l = $param['LONGITUDE'];

        	$rad_l = $rad_l / 180 * M_PI;
        	$rad_b = $rad_b / 180 * M_PI;

        	$sqlWhere .= " AND (
                    " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(c.LATITUDE)) *
                     cos(" . $rad_b . ") * (sin(RADIANS(c.LONGITUDE)) *
                     sin(" . $rad_l . ") + cos(RADIANS(c.LONGITUDE)) *
                     cos(" . $rad_l . ")) - sin(RADIANS(c.LATITUDE)) * sin(" . $rad_b . "))))
                ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $param['LU_UMKREIS']);
        }
        if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
        	if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }
        if(isset($param['BF_LANG']) && $param['BF_LANG'] != null) { $language = $param['BF_LANG']; }

		if(isset($param['TOP'])) {
			// Nur Top-User
			$sqlWhere .= " AND u.TOP_USER=1 ";
		}

		$q = ("
			SELECT
				SQL_CALC_FOUND_ROWS c.ID_CLUB
			FROM
				club c
			JOIN user u ON u.ID_USER = c.FK_USER
			LEFT JOIN searchdb_index_".$langvalAsCode." si ON (si.S_TABLE = 'club' AND si.FK_ID = c.ID_CLUB)
			LEFT JOIN searchdb_words_".$langvalAsCode." sw ON sw.ID_WORDS = si.FK_WORDS
			".$sqlJoin."
			".( !empty($sqlWhere) ? "WHERE ".substr($sqlWhere, 5) : '')."
			GROUP BY c.ID_CLUB");
		$x = $db->querynow($q);
		$y = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $y;
	}

	public function countClubsWhereUserIsMember($userId) {
		return $this->getDb()->fetch_atom("
			SELECT
				COUNT(*) as a
			FROM club2user c2u
			JOIN club c ON c.ID_CLUB = c2u.FK_CLUB
			WHERE
				c2u.FK_USER = '".(int)$userId."'
				AND c.STATUS = 1");
	}

	/**
	 * Liest alle Anzeigen des Clubs aus.
	 *
	 * @param int		$clubId
	 * @return array
	 */
	public function getAdsByClubId($clubId, $fk_kat = 0, $language = 128, $page = 1, $perpage = 10) {
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$offset = ($page - 1) * $perpage;
		if ($ar_club !== false) {
			$ar_users = array_keys($db->fetch_nar("SELECT FK_USER, FK_CLUB FROM `club2user` WHERE FK_CLUB=".(int)$clubId));
			$ar_ads = $db->fetch_table("
		    	SELECT
		    		SQL_CALC_FOUND_ROWS
		    		am.*,
		    		am.ID_AD_MASTER AS ID_AD,
		            am.TRADE AS product_trade,
		            (SELECT m.NAME FROM `manufacturers` m WHERE m.ID_MAN=am.FK_MAN) as MANUFACTURER,
		    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
		    		s.V1 as KAT,
		    		sc.V1 as LAND,
		    		i.SRC AS IMG_DEFAULT_SRC,
		    		i.SRC_THUMB AS IMG_DEFAULT_SRC_THUMB
		    	FROM
		    		ad_master am
		    	LEFT JOIN
					string_kat s on s.S_TABLE='kat'
					and s.FK=am.FK_KAT
					and s.BF_LANG=".(int)$language."
		    	LEFT JOIN
					string sc on sc.S_TABLE='country'
					and sc.FK=am.FK_COUNTRY
					and sc.BF_LANG=".(int)$language."
				LEFT JOIN
					ad_images i ON am.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
				WHERE
		    		am.FK_USER IN (".implode(", ", $ar_users).")
		    		AND am.STATUS&3 = 1 AND (am.DELETED=0)
		    		".($fk_kat ? 'AND am.FK_KAT = '.$fk_kat : '')."
		    	ORDER BY
		    		B_TOP DESC,
		    		STAMP_START DESC
		    	LIMIT ".$offset.", ".$perpage);
			return $ar_ads;
		} else {
			return array();
		}
	}

	/**
	 * Einen Club anhand der ID auslesen.
	 *
	 * @param int		$clubId
	 * @param int		$language
	 * @return assoc
	 */
	public function getClubById($clubId, $language = 128) {
        global $uid;
        $userIdPermission = $uid;
		$db = $this->getDb();
		return $db->fetch1("
            SELECT
                c.*, s.*,
				(SELECT count(*) FROM `club2user` WHERE FK_CLUB=c.ID_CLUB) as MEMBERS,
				(SELECT NAME FROM `user` WHERE ID_USER=c.FK_USER) as OWNER_NAME,
                IF(c.FK_USER = " . $userIdPermission . ", 1, 0) as IS_ADMIN,
                c2u.MODERATOR as IS_MODERATOR
            FROM
                club c
            JOIN
                string_club s ON s.FK = c.ID_CLUB AND s.S_TABLE='club'
				AND s.BF_LANG = if(c.BF_LANG_CLUB & ".$language.", ".$language.", 1 << floor(log(c.BF_LANG_CLUB+0.5)/log(2)))
            LEFT JOIN club2user c2u ON c2u.FK_CLUB = c.ID_CLUB AND c2u.FK_USER = " . $userIdPermission . "
            WHERE
                c.ID_CLUB = '".$clubId."'");

	}

	public function getClubModeratorsUserIds($clubId, $includeOwner = true) {
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		if (is_array($ar_club)) {
			$ar_members = array_keys(
				$db->fetch_nar("SELECT FK_USER FROM club2user WHERE FK_CLUB=".(int)$clubId." AND MODERATOR=1")
			);
			if (!in_array($ar_club["FK_USER"], $ar_members)) {
				$ar_members[] = $ar_club["FK_USER"];
			}
			return $ar_members;
		}
		return false;
	}

	/**
	 * Alle Clubs auslesen.
	 *
	 * @param int		$language
	 * @return array
	 */
	public function getClubs($language = 128, $page = 1, $perpage = 10) {
		// User ist mindestens in einem Club
		return $this->getClubsByParameters(array(), $language, $page, $perpage);
	}

	/**
	 * Clubs entsprechend der Parameter auslesen.
	 *
	 * @param int		$language
	 * @return array
	 */
	public function getClubsByParameters($ar_param = array(), $language = 128, $page = 1, $perpage = 10, &$all = null, $order_param = array()) {
        global $uid;
		// User ist mindestens in einem Club
		$userIdPermission = $uid;
		$ignoreFields = array('FK_USER_PERMISSION');

		$db = $this->getDb();

        $langvalAsCode = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE BITVAL=".(int)$language);
		if ($perpage !== null) {
			$offset = ($page - 1) * $perpage;
		}
		$where = array();

        if (isset($ar_param["FK_USER_PERMISSION"])) {
            $userIdPermission = $ar_param["FK_USER_PERMISSION"];
            unset($ar_param["FK_USER_PERMISSION"]);
        }

        if(isset($ar_param['SEARCHCLUB']) && $ar_param['SEARCHCLUB'] != null) {
            $where[] = "((sw.wort LIKE '%".mysql_real_escape_string($ar_param['SEARCHCLUB'])."%') OR (c.NAME LIKE '%".mysql_real_escape_string($ar_param['SEARCHCLUB'])."%')) ";
            unset($ar_param["SEARCHCLUB"]);
        }
        if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
            $row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=".(int)$param['CATEGORY']);
            $ids_kats = $db->fetch_nar("
              SELECT ID_KAT
                FROM `kat`
              WHERE
                (LFT >= " . $row_kat["LFT"] . ") AND
                (RGT <= " . $row_kat["RGT"] . ") AND
                (ROOT = " . $row_kat["ROOT"] . ")
            ");

            $where[] = "cc.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
            unset($ar_param["CATEGORY"]);
        }

        foreach ($ar_param as $field => $value) {
			if(!in_array($field, $ignoreFields)) {
				if (is_array($value) && !empty($value)) {
					$where[] = "c. `" . mysql_real_escape_string($field) . "` IN (" . implode(", ", $value) . ")";
				}
				else {
					$where[] = "c.`".mysql_real_escape_string($field)."` = '".mysql_real_escape_string($value)."'";
				}
			}
		}

        $order = array();

        if (is_array($order_param)) {
            foreach ($order_param as $field => $ordering) {
                $order[] = $field . ' ' . $ordering;
            }
        }

		$r = $db->fetch_table($q = "
            SELECT
                SQL_CALC_FOUND_ROWS
                c.*, s.*, sc.V1 as COUNTRY,
                IF(c.FK_USER = " . $userIdPermission . ", 1, 0) as IS_ADMIN,
                c2u.MODERATOR as IS_MODERATOR,
                c2u.STAMP_JOIN,
                (SELECT count(*) FROM `club_gallery` where FK_CLUB = c.ID_CLUB) as COUNT_GALLERY,
                (SELECT count(*) FROM `calendar_event` where FK_REF = c.ID_CLUB and FK_REF_TYPE = 'club') as COUNT_EVENTS,
                (SELECT count(*) FROM `comment` where FK = c.ID_CLUB and `TABLE` = 'club' and IS_PUBLIC = 1 and IS_REVIEWED = 1 and IS_CONFIRMED = 1) as COUNT_COMMENTS,
				(SELECT count(*) FROM `club2user` WHERE FK_CLUB=c.ID_CLUB) as MEMBERS,
				(SELECT NAME FROM `user` WHERE ID_USER=c.FK_USER) as OWNER_NAME,
                (SELECT count(*) FROM `club_member_request` WHERE FK_CLUB=c.ID_CLUB AND STATUS = 0) as MEMBER_REQUEST
            FROM
                club c
            LEFT JOIN string_club s ON s.FK = c.ID_CLUB AND s.S_TABLE='club'
				AND s.BF_LANG = if(c.BF_LANG_CLUB & ".$language.", ".$language.", 1 << floor(log(c.BF_LANG_CLUB+0.5)/log(2)))
           	LEFT JOIN string sc ON sc.FK = c.FK_COUNTRY
				AND sc.S_TABLE = 'country' AND sc.BF_LANG = '".$language."'
			LEFT JOIN club_category cc ON c.ID_CLUB = cc.FK_CLUB
            LEFT JOIN club2user c2u ON c2u.FK_CLUB = c.ID_CLUB AND c2u.FK_USER = " . (isset($ar_param["FK_USER_PERMISSION"]) ? $ar_param["FK_USER_PERMISSION"] : $uid) . "
            LEFT JOIN searchdb_index_".$langvalAsCode." si ON (si.S_TABLE = 'club' AND si.FK_ID = c.ID_CLUB)
            LEFT JOIN searchdb_words_".$langvalAsCode." sw ON sw.ID_WORDS = si.FK_WORDS
			".(!empty($where) ? "WHERE ".implode(" AND ", $where) : "")."
			GROUP BY c.ID_CLUB
            " . (!empty($order) ? 'ORDER BY ' . implode(', ', $order) : '') .
            ($perpage !== null ? " LIMIT ".$offset.", ".$perpage : ""));
        if ($all !== null) {
            $all = $db->fetch_atom("SELECT FOUND_ROWS();");
        }

        return $r;
	}

	/**
	 * Alle Clubs eines Users auslesen.
	 *
	 * @param int		$userId
	 * @param int		$language
	 * @return array
	 */
	public function getClubsByUser($userId, $language = 128, $page = 1, $perpage = 10, &$all = null, $order = array()) {
		$clubIds = $this->getUserClubIds($userId, $all);

        if (!empty($clubIds)) {
            $arr = array('ID_CLUB' => $clubIds, 'FK_USER_PERMISSION' => $userId);
        }
        else {
            $arr = array('FK_USER' => $userId, 'FK_USER_PERMISSION' => $userId);
        }

        return $this->getClubsByParameters($arr, $language, $page, $perpage, $all, $order);
	}

	/**
	 * Liest alle Bilder aus der Gallerie des angegebenen Clubs
	 * @param int $clubId	Id des clubs
	 * @return array
	 */
	public function getImagesByClubId($clubId) {
		$db = $this->getDb();
        $result = $db->fetch_table("
            SELECT g.* FROM club_gallery g
            WHERE
                g.FK_CLUB = '" . mysql_real_escape_string($clubId) . "'
        ");
        return $result;
	}

	/**
	 * Liest die Kategorien des Clubes (/dessen Mitglieder) als HTML-Block aus.
	 *
	 * @param int	$clubId
	 * @param int	$fk_kat
	 * @param int	$language
	 */
	public function getKatsByClubId($clubId, $fk_kat = 0, $language = 128) {
		global $ab_path, $s_lang;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		if ($ar_club !== false) {
			$kat_path = $ab_path.'cache/clubs/'.(int)$clubId;
			$file_name = $kat_path.'/userkat_'.(int)$fk_kat.'.'.$s_lang.'.tmp';
			$file = @filemtime($file_name);
			$now = time();
			$diff = (($now-$file)/60);
			$file = @file_get_contents($file_name);
			if(!$file || ($diff > $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CLUB_CATEGORIES'])) {
				$ar_users = array_keys($db->fetch_nar("SELECT FK_USER, FK_CLUB FROM `club2user` WHERE FK_CLUB=".(int)$clubId));
				$in = array_keys($db->fetch_nar("SELECT FK_KAT, ID_AD_MASTER FROM `ad_master`
					WHERE FK_USER IN (".implode(", ", $ar_users).") AND STATUS&3=1 AND DELETED=0
					GROUP BY FK_KAT"));

				if(count($in) > 0) {
					// Get Kats
					$kats = $db->fetch_table("
						select
							t.*,
							IF(sp.V1 IS NULL,s.V1,CONCAT(sp.V1,' > ',s.V1)) as V1,
							s.V2,
							s.T1,
							".(int)$clubId." AS FK_CLUB,
							'".mysql_escape_string($ar_club['NAME'])."' AS NAME,
							".(int)$fk_kat." AS CUR_KAT,
							(
								SELECT
									COUNT(ID_AD_MASTER)
								FROM
									ad_master
								WHERE
									ad_master.FK_USER IN (".implode(", ", $ar_users).") AND
									ad_master.FK_KAT = t.ID_KAT
									AND ad_master.STATUS&3=1 AND (adt.DELETED=0)
							)	AS ADS
						from
							`kat` t
						left join string_kat s
							on s.S_TABLE='kat' and s.FK=t.ID_KAT
							and s.BF_LANG=if(t.BF_LANG_KAT & ".$language.", ".$language.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
						left join kat tp
							on tp.ID_KAT=t.PARENT
						left join string_kat sp
							on sp.S_TABLE='kat' and sp.FK=tp.ID_KAT
							and sp.BF_LANG=if(tp.BF_LANG_KAT & ".$language.", ".$language.", 1 << floor(log(tp.BF_LANG_KAT+0.5)/log(2)))
						WHERE
							t.ID_KAT IN (".implode(",", $in).")
						ORDER BY
							s.V1");
				}
				$tpl_tmp = new Template($ab_path.'tpl/de/empty.htm');
				$tpl_tmp->tpl_text = '{own_kats}';
				$tpl_tmp->addvars($ar_club, 'CLUB_');
				$tpl_tmp->addlist("own_kats", $kats, "tpl/".$s_lang."/club.kat.htm");
				@mkdir($kat_path, 0777, true);
				@file_put_contents($file_name, $tpl_tmp->process());
				@chmod($file_name, 0777);
			}
		}
		return file_get_contents($file_name);
	}

	/**
	 * Liest alle Mitglieder des Clubs aus.
	 *
	 * @param int		$clubId
	 * @param boolean 	$includePending
	 * @return array
	 */
	public function getMembersByClubId($clubId, $includePending = false, $page = 1, $perpage = 20, $filter = array()) {
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		if ($ar_club !== false) {
			$limit = ($page - 1) * $perpage;
			if ($includePending) {
				$ar_members = $db->fetch_table("
						SELECT
							cu.FK_USER, cu.STAMP_JOIN, 1 as IS_MEMBER, cu.MODERATOR as IS_MODERATOR,
							u.NAME as USERNAME, u.VORNAME as USR_VORNAME, u.NACHNAME as USR_NACHNAME,
							u.NAME, 0 as ID_CLUB_INVITE, u.LASTACTIV as USR_LASTACTIV, u.EMAIL as USR_EMAIL
						FROM `club2user` cu
						LEFT JOIN `user` u ON cu.FK_USER=u.ID_USER
						WHERE cu.FK_CLUB=".(int)$clubId."
					UNION
						SELECT
							ci.FK_USER, ci.STAMP as STAMP_JOIN, 0 as IS_MEMBER, 0 as IS_MODERATOR,
							u.NAME as USERNAME, u.VORNAME as USR_VORNAME, u.NACHNAME as USR_NACHNAME,
							ci.NAME, ci.ID_CLUB_INVITE, ci.STAMP as USR_LASTACTIV, ci.EMAIL as USR_EMAIL
						FROM `club_invite` ci
						LEFT JOIN `user` u ON ci.FK_USER=u.ID_USER
						WHERE ci.FK_CLUB=".(int)$clubId." ".((count($filter)>0)?'AND '.implode(' AND ', $filter):'')."
					ORDER BY
						(FK_USER=".(int)$ar_club["FK_USER"].") DESC, IS_MODERATOR DESC, IS_MEMBER DESC, USERNAME ASC
					LIMIT ".(int)$limit.", ".(int)$perpage);
				return $ar_members;
			} else {
				$ar_members = $db->fetch_table("SELECT
						cu.FK_USER, cu.STAMP_JOIN, 1 as IS_MEMBER, cu.MODERATOR as IS_MODERATOR,
						u.NAME as USERNAME, u.VORNAME as USR_VORNAME, u.NACHNAME as USR_NACHNAME,
						u.FIRMA as USR_FIRMA, u.UEBER as USR_UEBER, u.CACHE as USR_CACHE,
						u.NAME, 0 as ID_CLUB_INVITE, u.LASTACTIV as USR_LASTACTIV, u.EMAIL as USR_EMAIL
					FROM `club2user` cu
					LEFT JOIN `user` u ON cu.FK_USER=u.ID_USER
					WHERE cu.FK_CLUB=".(int)$clubId." ".((count($filter)>0)?'AND '.implode(' AND ', $filter):'')."
					ORDER BY
						(cu.FK_USER=".(int)$ar_club["FK_USER"].") DESC, IS_MODERATOR DESC, USERNAME ASC
					LIMIT ".(int)$limit.", ".(int)$perpage);
				return $ar_members;
			}
		} else {
			return array();
		}
	}

	/**
	 * Alle Suchbegriffe eines Clubs auslesen.
	 *
	 * @param int	$clubId
	 * @param int	$language
	 * @return Ambigous <multitype:, multitype:unknown >
	 */
    public function getSearchWordsByClubId($clubId, $language = 128) {
        $db = $this->getDb();
        $langvalAsCode = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE BITVAL=".(int)$language);
        $ar_club = $this->getClubById($clubId);
        if (!empty($ar_club)) {
			return $db->fetch_table("
				SELECT
					w.ID_WORDS, w.wort
				FROM
					searchdb_index_".$langvalAsCode." i
				JOIN searchdb_words_".$langvalAsCode." w ON w.ID_WORDS = i.FK_WORDS
				WHERE
					S_TABLE = 'club' AND FK_ID = '".(int)$clubId."'
				ORDER BY w.wort
			");
        }
    }

	/**
	 * Alle Club-Ids eines Benutzers auslesen.
	 *
	 * @param int		$userId		Standardmäßig der aktuell eingeloggte Benutzer.
	 * @return array
	 */
	public function getUserClubIds($userId = null, &$all = null) {
		global $uid;
		if ($userId == null) $userId = $uid;

		$db = $this->getDb();
		$ar_clubs = $db->fetch_nar($query = "SELECT SQL_CALC_FOUND_ROWS FK_CLUB FROM `club2user` WHERE FK_USER=".(int)$userId);
        if ($all !== null) {
            $all = $db->fetch_atom("SELECT FOUND_ROWS();");
        }
		return (is_array($ar_clubs) ? array_keys($ar_clubs) : array());
	}

	/**
	 * Alle Ids der Clubs auslesen, in denen der angegebene Benutzer Moderator oder Gründer ist.
	 *
	 * @param int		$userId		Standardmäßig der aktuell eingeloggte Benutzer.
	 * @return array
	 */
	public function getUserModClubIds($userId = null, &$all = null) {
		global $uid;
		if ($userId == null) $userId = $uid;

		$db = $this->getDb();
		$ar_clubs = $db->fetch_nar($query = "SELECT
				SQL_CALC_FOUND_ROWS cu.FK_CLUB
			FROM `club` c
			LEFT JOIN `club2user` cu ON c.ID_CLUB=cu.FK_CLUB AND cu.FK_USER=".(int)$userId."
			WHERE c.FK_USER=cu.FK_USER OR cu.MODERATOR=1");
		if ($all !== null) {
			$all = $db->fetch_atom("SELECT FOUND_ROWS();");
		}
		return (is_array($ar_clubs) ? array_keys($ar_clubs) : array());
	}

	/**
	 * Liest alle Videos aus der Gallerie des angegebenen Clubs
	 * @param int $clubId	Id des clubs
	 * @return array
	 */
	public function getVideosByClubId($clubId) {
		$db = $this->getDb();
        $result = $db->fetch_table("
            SELECT g.* FROM club_gallery_video g
            WHERE
                g.FK_CLUB = '" . mysql_real_escape_string($clubId) . "'
        ");
        return $result;
	}

    /**
     * Fügt ein neues Anbieter Bild ein
     *
     * @param $clubGalleryFilename
     * @param $clubId
     * @return bool
     */
    public function insertFile($clubGalleryName, $clubGalleryFilename, $clubId, $overrideRights = false) {
        global $nar_systemsettings, $uid;
        $db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
	        $countGallery = $this->countGallery($clubId);

	        if ($countGallery >= $nar_systemsettings['USER']['CLUB_GALLERY_MAX_IMAGES']) {
	            return false;
	        } else {
	            return $db->update("club_gallery", array('FK_CLUB' => $clubId, 'FILENAME' => $clubGalleryFilename, 'NAME' => $clubGalleryName));
	        }
		}
    }

    /**
     * Fügt ein neues Anbieter Video ein
     *
     * @param $youtubeId
     * @param $clubId
     * @return bool
     */
    public function insertVideo($clubGalleryName, $youtubeId, $clubId, $overrideRights = false) {
        global $nar_systemsettings, $uid;
        $db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
	        $countGallery = $this->countGallery($clubId);

	        if ($countGallery >= $nar_systemsettings['USER']['CLUB_GALLERY_MAX_IMAGES']) {
	            return false;
	        } else {
	            return $db->update("club_gallery_video", array('FK_CLUB' => $clubId, 'YOUTUBEID' => $youtubeId, 'NAME' => $clubGalleryName));
	        }
		}
		return false;
    }

	/**
	 * Einen Benutzer ohne ebiz-trader Account einladen.
	 *
	 * @param int		$clubId
	 * @param string	$userName	Namen des Benutzers der eingeladen werden soll (für die Anrede)
	 * @param string	$userMail	E-Mail Adresse des Benutzers der eingeladen werden soll
	 * @param string	$message	Nachricht an den Eingeladenen
	 * @return boolean
	 */
	public function inviteMemberByMail($clubId, $userName, $userMail, $message = "") {
        global $uid;

        if (!$this->isClubModerator($clubId, $uid) && !$this->isClubOwner($clubId, $uid)) {
            return false;
        }

		$db = $this->getDb();
		$id_user = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE EMAIL='".mysql_real_escape_string($userMail)."'");
		if ($id_user > 0) {
			return $this->inviteMemberByUserId($clubId, $id_user, $message);
		}
		$id_invite = $db->fetch_atom("SELECT ID_CLUB_INVITE FROM `club_invite`
				WHERE FK_CLUB=".(int)$clubId." AND EMAIL='".mysql_real_escape_string($userMail)."'");
		if ($id_invite > 0) {
			// Benutzer hat bereits eine Einladung
			// TODO: Wirklich erfolg?
			return true;
		} else {
			$code = substr(md5(microtime()), 0, 16);
			$db->querynow($query = "INSERT INTO `club_invite` (FK_CLUB, NAME, EMAIL, CODE, MESSAGE, STAMP)
					VALUES (".(int)$clubId.", '".mysql_real_escape_string($userName)."', '".mysql_real_escape_string($userMail)."',
						'".$code."', '".mysql_real_escape_string($message)."', NOW())");
			// E-Mail senden
			$mail_content = $this->getClubById($clubId);
			$mail_content['CODE'] = $code;
			$mail_content['USERNAME'] = $userName;
			$mail_content['MESSAGE'] = $message;
			sendMailTemplateToUser(0, $userMail, 'CLUB_INVITE_MAIL', $mail_content);
			return true;
		}
	}

	/**
	 * Einen bereits angemeldeten Benutzer einladen.
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 * @param string	$message
	 * @return boolean
	 */
	public function inviteMemberByUserId($clubId, $userId, $message = "") {
        global $uid;

        if (!$this->isClubModerator($clubId, $uid) && !$this->isClubOwner($clubId, $uid)) {
            return false;
        }

		$db = $this->getDb();
		$id_invite = $db->fetch_atom("SELECT ID_CLUB_INVITE FROM `club_invite`
				WHERE FK_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);
		if ($id_invite > 0) {
			// Benutzer hat bereits eine Einladung
			// TODO: Wirklich erfolg?
			return true;
		} else {
			$ar_user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".(int)$userId);
			$db->querynow($query = "INSERT INTO `club_invite` (FK_CLUB, FK_USER, MESSAGE, STAMP)
				VALUES (".(int)$clubId.", ".(int)$userId.", '".mysql_real_escape_string($message)."', NOW())");
			// E-Mail senden
			$mail_content = $this->getClubById($clubId);
			foreach ($ar_user as $field => $value) {
				$mail_content['USER_'.$field] = $value;
			}
			$mail_content['MESSAGE'] = $message;
			sendMailTemplateToUser(0, $userId, 'CLUB_INVITE_USER', $mail_content);
			return true;
		}
	}

	/**
	 * Einen bereits angemeldeten Benutzer einladen.
	 *
	 * @param int		$clubId
	 * @param string	$userName
	 * @param string	$message
	 * @return boolean
	 */
	public function inviteMemberByUserName($clubId, $userName, $message = "") {
        global $uid;

        if (!$this->isClubModerator($clubId, $uid) && !$this->isClubOwner($clubId, $uid)) {
            return false;
        }

		$db = $this->getDb();
		$id_user = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME='".mysql_real_escape_string($userName)."'");
		if ($id_user > 0) {
			return $this->inviteMemberByUserId($clubId, $id_user, $message);
		} else {
			return false;
		}
	}

	/**
	 * Prüfen ob der Benutzer Moderationsrechte im angegebenen Club hat
	 * @param int $clubId	Id des clubs
	 * @param int $userId	Id des Users
	 * @return boolean
	 */
	public function isClubModerator($clubId, $userId) {
		$db = $this->getDb();
		$res = $db->fetch_atom("SELECT MODERATOR FROM `club2user`
				WHERE FK_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);
		return $res;
	}

    /**
     * Prüfen ob der Benutzer Moderationsrechte im angegebenen Club hat
     * @param int $clubId   Id des clubs
     * @param int $userId   Id des Users
     * @return boolean
     */
    public function isClubOwner($clubId, $userId) {
        $db = $this->getDb();
        $res = $db->fetch_atom("SELECT count(*) FROM `club`
                WHERE ID_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);

        return $res;
    }

	public function leaveClub($clubId) {
		global $uid;
		return $this->remMember($clubId, $uid, true);
	}

	public function lock($clubId) {
		if ($clubId > 0) {
			$db = $this->getDb();
			$db->querynow("UPDATE `club` SET STATUS=(STATUS|2) WHERE ID_CLUB=".(int)$clubId);
			return true;
		} else {
			return false;
		}
	}

    /**
     * Überprüfft, ob der User im Club ist.
     *
     * @param  int  $clubId    ID des Clubs
     * @param  int  $userId    ID des Users
     * @return boolean         True(1) wenn er im Club ist, andernfalls false(0)
     */
    public function isMember($clubId, $userId)
    {
        $query = 'select count(*)
                    from club2user
                    where FK_CLUB = ' . $clubId . ' AND FK_USER = ' . $userId;

        $db = $this->getDb();
        $res = $db->fetch_atom($query);

        return $res;
    }

	/**
	 * Ein Mitglied aus dem Club entfernen.
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 */
	public function remMember($clubId, $userId, $overrideRights = false) {
		global $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid) || $this->isClubModerator($clubId, $uid);
		if ($overrideRights || $regularRights) {
			if ($userId != $ar_club["FK_USER"]) {
				$db->querynow($query = "DELETE FROM `club2user` WHERE FK_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);
				return true;
			} else {
				eventlog("error", "Der Club-Besitzer kann den Club nicht verlassen!",
					"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			}
		} else {
			// NICHT DER BESITZER DES CLUBS!
			eventlog("error", "Konnte Club-Mitglied nicht entfernen, unzureichende Rechte!",
				"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			return false;
		}
	}

	/**
	 * Einen User als Clubmitglied hinzufügen
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 */
	public function remModerator($clubId, $userId, $overrideRights = false) {
		return $this->setModerator($clubId, $userId, 0, $overrideRights);
	}

	/**
	 * Neuen Club-Besitzer festlegen.
	 *
	 * @param int	$clubId
	 * @param int	$userId
	 * @return boolean
	 */
	public function setClubOwner($clubId, $userId) {
		global $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		if (($ar_club !== false) && ($ar_club["FK_USER"] == $uid)) {
			$check = $db->fetch_atom("SELECT FK_USER FROM `club2user` WHERE FK_CLUB=".(int)$clubId);
			if ($check > 0) {
				$db->querynow("UPDATE `club` SET FK_USER=".(int)$userId." WHERE ID_CLUB=".(int)$clubId);
				return true;
			} else {
				return false;
			}
		} else {
			eventlog("error", "Konnte Clubleitung nicht übertragen, unzureichende Rechte!",
				"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			return false;
		}
	}

	/**
	 * Einen User als Clubmitglied hinzufügen
	 *
	 * @param int		$clubId
	 * @param int		$userId
	 */
	public function setModerator($clubId, $userId, $value, $overrideRights = false) {
		global $uid;
		$db = $this->getDb();
		$ar_club = $this->getClubById($clubId);
		$regularRights = ($ar_club["FK_USER"] == $uid);
		if ($overrideRights || $regularRights) {
			$db->querynow($query = "UPDATE `club2user` SET MODERATOR=".($value ? 1 : 0)."
					WHERE FK_CLUB=".(int)$clubId." AND FK_USER=".(int)$userId);
			return true;
		} else {
			// NICHT DER BESITZER DES CLUBS!
			eventlog("error", "Konnte Club-Mitglied nicht zum Moderator ernennen, unzureichende Rechte!",
				"Club-ID: ".$ar_club["ID_CLUB"].", Club-Besitzer: ".$ar_club["FK_USER"].", User-ID: ".$uid);
			return false;
		}
	}

	public function unlock($clubId) {
		if ($clubId > 0) {
			$db = $this->getDb();
			$db->querynow("UPDATE `club` SET STATUS=(STATUS&1) WHERE ID_CLUB=".(int)$clubId);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Neuen Club erstellen / Bestehenden Club aktualisieren
	 *
	 * @param assoc		$ar_club
	 * @param int		$language
	 * @return bool|int
	 */
	public function update($ar_club, $language = 128) {
		global $uid, $ab_path, $nar_systemsettings;
		$db = $this->getDb();

		$userClubs = $this->getUserClubIds();
		if (false && empty($ar_club["ID_CLUB"]) && !empty($userClubs)) {  // False entfernen um beschränkung auf einen Club wieder einzufügen
			eventlog("error", "Konnte Gruppe nicht erstellen, Benutzer ist bereits Mitglied in einer Gruppe!");
			return false;
		} else {
			$id_club = false;
			$land = $db->fetch_atom("SELECT V1 FROM `string` WHERE S_TABLE='country' AND FK=".(int)$ar_club["FK_COUNTRY"]." AND BF_LANG=128");
	        $geoCoordinates = Geolocation_Generic::getGeolocationCached($ar_club["STRASSE"], $ar_club["PLZ"], $ar_club["ORT"], $land);
	        if (($geoCoordinates != false) && ($geoCoordinates  != null)) {
	        	// Erfolg! Geo-Koordinaten übernehmen
	        	$ar_club["LATITUDE"] = $geoCoordinates["LATITUDE"];
	        	$ar_club["LONGITUDE"] = $geoCoordinates["LONGITUDE"];
	        } else {
	        	eventlog("error", "Anbieter: Fehler beim Auflösen einer Adresse!", $ar_club["STRASSE"]." ".$ar_club["PLZ"]." ".$ar_club["ORT"].", ".$land);
	        }
	        $ar_club["CHANGED"] = date('Y-m-d H:i:s');
	        if(isset($ar_club['URL']) && $ar_club['URL'] != "" && !preg_match("/^https?\:\/\//", $ar_club['URL'])) { $ar_club['URL'] = 'http://'.$ar_club['URL']; }

			if ($ar_club["ID_CLUB"] > 0) {
				$ar_club_src = $this->getClubById($ar_club["ID_CLUB"], $language);
				if ($ar_club_src["FK_USER"] != $uid) {
					// NICHT DER BESITZER DES CLUBS!
					if ($this->isClubModerator($ar_club["ID_CLUB"], $uid)) {
						// Club-Besitzer kann von Moderatoren nicht verändert werden!!
						$ar_club["FK_USER"] = $ar_club_src["FK_USER"];
					} else {
						eventlog("error", "Konnte Gruppe nicht bearbeiten, unzureichende Rechte!",
							"Club-ID: ".$ar_club["ID_CLUB"].", Gruppen-Besitzer: ".$ar_club_src["FK_USER"].", User-ID: ".$uid);
						return false;
					}
				}
				if (is_array($ar_club["T1"])) {
					// Mehrere Sprachen gleichzeitig speichern
					foreach ($ar_club["T1"] as $lang_cur => $text) {
						$db->update("club", array(
							"ID_CLUB"		=> $ar_club["ID_CLUB"],
							"BF_LANG_CLUB"	=> $lang_cur,
							"T1"			=> strip_tags($text, $nar_systemsettings["MARKTPLATZ"]["HTML_ALLOWED_TAGS_GROUP"])
						));
					}
					$ar_club = array_merge($ar_club_src, $ar_club);
					unset($ar_club["BF_LANG"]);
					unset($ar_club["BF_LANG_CLUB"]);
					unset($ar_club["T1"]);
					unset($ar_club["V1"]);
					unset($ar_club["V2"]);
				} else {
                    // Strip invalid html
                    $ar_club["T1"] = strip_tags($ar_club["T1"], $nar_systemsettings["MARKTPLATZ"]["HTML_ALLOWED_TAGS_GROUP"]);
                }
				// Update status
				$ar_club["STATUS"] = ($ar_club["STATUS"] & 1) + ($ar_club_src["STATUS"] - ($ar_club_src["STATUS"] & 1));
				// Save to db
				$db->update("club", $ar_club);
				$id_club = $ar_club["ID_CLUB"];
			} else {
				$ar_club["BF_LANG_CLUB"] = $language;
				$ar_club["T1"] = $ar_club["NAME"];
				$ar_club["FK_USER"] = $uid;
                $ar_club["STAMP"] = date('Y-m-d H:i:s');
				$id_club = $db->update("club", $ar_club);
				if ($id_club > 0) {
					$this->addMember($id_club, $uid);
					return $id_club;
				}
			}
			if ($id_club !== false) {
				return $id_club;
			} else {
				eventlog("error", "Konnte Gruppe nicht erstellen, Datenbankfehler!", var_export($ar_club, true));
				return false;
			}
		}
	}

	/**
	 * Eingaben auf Fehler überprüfen.
	 *
	 * @param assoc		$ar_club
	 * @param array		$errors
	 * @return bool
	 */
	public function updateCheckFields($ar_club, &$errors) {
		if (!is_array($errors)) { $errors = array(); }
		if (empty($ar_club["NAME"])) {
			$errors[] = "MISSING_NAME";
		}
		return empty($errors);
	}

    /**
     * Return join datetime
     * @param  int $idUser id of user
     * @param  int $idClub id of club
     * @return string         date format
     */
    public function getJoinStamp($idUser, $idClub)
    {
        return $this->getDb()->fetch_atom("
            select STAMP_JOIN from club2user where FK_CLUB = " . (int)$idClub. " and FK_USER = " .(int) $idUser
        );
    }

    /**
     * Check if comments are allowed
     * @param  int  $fk id_club
     * @return boolean
     */
    public function isCommentAllowed($fk)
    {
        return $this->getDb()->fetch_atom("select ALLOW_COMMENTS from club where ID_CLUB = " . (int)$fk);
    }

}

?>
