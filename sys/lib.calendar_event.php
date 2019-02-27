<?php
/* ###VERSIONSBLOCKINLCUDE### */


class CalendarEventManagement {
	private static $db;
	private static $instance = NULL;

	const PRIVACY_PRIVATE = 0;
	const PRIVACY_PUBLIC = 1;

	const CATEGORY_ROOT = 7;
	const MAX_CATEGORY_PER_USER = 1;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return CalendarEventManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

	/**
	 * Allowed parameters for search
	 */
	public static function getParameterKeys() {
		return array("SEARCHCALENDAREVENT", "CATEGORY", "ZIP", "CITY", "FK_COUNTRY", "IS_CONFIRMED",
			"LATITUDE", "LONGITUDE", "LU_UMKREIS", "FK_REF", "FK_REF_TYPE", "PRIVACY", "SORT_BY", "SORT_DIR",
            "STAMP_START_GT", "STAMP_START_LT", "STAMP_END_GT");
	}


    public function adminAccept($calendarEventId) {
        $db = $this->getDb();
        $res = $db->querynow("UPDATE `calendar_event` SET MODERATED=1 WHERE ID_CALENDAR_EVENT=".(int)$calendarEventId);
        return $res["rsrc"];
    }

    public function adminAcceptUser($id_user) {
        global $db;
        $arEvents = $db->fetch_nar("SELECT ID_CALENDAR_EVENT, FK_USER FROM `calendar_event` WHERE FK_USER=".$id_user." AND MODERATED=0");
        foreach ($arEvents as $id_event => $fk_user) {
            $this->adminAccept($id_event);
        }
    }

    public function adminDecline($calendarEventId, $reason, $mail = true) {
        $db = $this->getDb();
        $res = $db->querynow("UPDATE `calendar_event` SET MODERATED=2, DECLINE_REASON='".mysql_real_escape_string($reason)."' WHERE ID_CALENDAR_EVENT=".(int)$calendarEventId);
        if ($mail) {
            // Notify user by email
            $arMailEvent = $this->fetchById($calendarEventId);
            $arMailEvent["REASON"] = (empty($reason) ? false : $reason);
            $arMailUser = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$arMailEvent["FK_USER"]);
            sendMailTemplateToUser(0, $arMailUser["ID_USER"], "MODERATE_EVENT_DECLINED", array_merge($arMailEvent, $arMailUser));
        }
        return $res["rsrc"];
    }

    public function adminDeclineUser($id_user, $reason) {
        global $db;
        $arEvents = $db->fetch_nar("SELECT ID_CALENDAR_EVENT, FK_USER FROM `calendar_event` WHERE FK_USER=".$id_user." AND MODERATED=0");
        foreach ($arEvents as $id_event => $fk_user) {
            $this->adminDecline($id_event, $reason, false);
        }
    }

	public function fetchById($calendarEventId, $userCheck = false) {
		global $langval;
        $arParams = array(
            "ID_CALENDAR_EVENT" => $calendarEventId,
            "LIMIT"				=> 1
        );
        if ($userCheck !== false) {
            $arParams['FK_USER_MOD'] = (int)$userCheck;
        }
		$query = $this->generateFetchQuery($arParams);
		$calendarEvent = $this->getDb()->fetch1($query);
		return ($calendarEvent === false ? null : $calendarEvent);
	}


	public function fetchAllByParam($param, &$all = null) {
		$db = $this->getDb();
		$query = $this->generateFetchQuery($param);
		$arResult = $db->fetch_table($query);
		if ($all !== null) {
			$all = $db->fetch_atom("SELECT FOUND_ROWS()");
		}
		return $arResult;
    }

	public function countByParam($param) {
		$db = $this->getDb();

		unset($param['LIMIT']);
		unset($param['OFFSET']);
		unset($param['SORT']);
		unset($param['SORT_DIR']);
		$param['NO_FIELDS'] = TRUE;

		$query = $this->generateFetchQuery($param);

		$db->querynow($query);
		$rowCount = $db->fetch_atom("SELECT FOUND_ROWS()");

		return $rowCount;
	}

    public function createCalendarFile($calendarEventId, $fileTarget) {
        $fileDirectory = pathinfo($fileTarget, PATHINFO_DIRNAME);
        $fileName = pathinfo($fileTarget, PATHINFO_BASENAME);
        return $this->createCalendarObject($calendarEventId)->saveCalendar($fileDirectory, $fileName);
    }

    /**
     * Creates a vcalendar object which can be used to create iCal files
     * @param int   $calendarEventId
     * @return vcalendar
     */
    protected function createCalendarObject($calendarEventId) {
        $db = $this->getDb();

        $calendarEvent = $this->fetchById($calendarEventId);
        // Domain name
        $siteDomain = str_replace('http://', '', $GLOBALS['nar_systemsettings']["SITE"]["SITEURL"]);
        // Location
        $arLocation = array();
        if (!empty($calendarEvent["LOCATION"])) $arLocation[] = $calendarEvent["LOCATION"];
        if (!empty($calendarEvent["STREET"])) $arLocation[] = $calendarEvent["STREET"];
        if (!empty($calendarEvent["ZIP"])) $arLocation[] = $calendarEvent["ZIP"];
        if (!empty($calendarEvent["CITY"])) $arLocation[] = $calendarEvent["CITY"];
        if (!empty($calendarEvent["FK_COUNTRY"])) {
            $arLocation[] = $db->fetch_atom("SELECT V1 FROM string
                    WHERE S_TABLE='country' AND BF_LANG=".$GLOBALS['langval']." AND
                        FK=".(int)$calendarEvent["FK_COUNTRY"]);
        }
        // Description
        $description = strip_tags(str_replace(
            array("<br>", "<BR>", "<br/>", "<BR/>", "<br />", "<BR />"),
            "\n",
            $calendarEvent["DESCRIPTION"]
        ));
        // URL
        $url = $this->getUrl($calendarEvent);
        // Start date
        $startTime = date_parse($calendarEvent["STAMP_START"]);
        $start = array('year' => $startTime['year'], 'month' => $startTime['month'], 'day' => $startTime['day'],
            'hour' => $startTime['hour'], 'min' => $startTime['minute'], 'sec' => $startTime['second']);
        // End date
        $endTime = date_parse($calendarEvent["STAMP_END"]);
        $end = array('year' => $endTime['year'], 'month' => $endTime['month'], 'day' => $endTime['day'],
            'hour' => $endTime['hour'], 'min' => $endTime['minute'], 'sec' => $endTime['second']);
        // Create iCal object
        require_once $GLOBALS['ab_path'].'sys/iCalcreator/iCalcreator.class.php';
        $iCalConfig = array("unique_id" => $siteDomain);
        $iCal = new vcalendar($iCalConfig);
        $iCal->setProperty( "method", "PUBLISH" );
        $iCal->setProperty( "CLASS", "PUBLIC" );
        // required of some calendar software
        $iCal->setProperty( "x-wr-calname", $calendarEvent["TITLE"] );
        $iCal->setProperty( "X-WR-CALDESC", $calendarEvent["DESCRIPTION"] );
        $iCal->setProperty( "X-WR-TIMEZONE", "Europe/Stockholm" );
        $iCalEvent = $iCal->newComponent("vevent");
        $iCalEvent->setProperty( "DTSTART", $start );
        $iCalEvent->setProperty( "DTEND", $end );
        $iCalEvent->setProperty( "LOCATION", implode(" ", $arLocation) );
        $iCalEvent->setProperty( "SUMMARY", $calendarEvent["TITLE"]);
        $iCalEvent->setProperty( "DESCRIPTION", $description );
        $iCalEvent->setProperty( "URL", $url );
        // output ics file
        return $iCal;
    }

    public function getUrl($arCalendarEvent) {
        // URL
        $urlTitle = addnoparse(chtrans($arCalendarEvent["TITLE"]));
        $url = $GLOBALS['tpl_content']->tpl_uri_action_full("calendar_events_view,".$urlTitle.",".$arCalendarEvent["ID_CALENDAR_EVENT"]);
        return $url;
    }
	
	public function getSearchHash($arSearchParameters) {
		$db = $this->getDb();
		$arResult = array("COUNT" => 0);
		$resultCount = $this->countByParam($arSearchParameters);
		if ($resultCount > 0) {
			$lifetime = time() + (60 * 60 * 24);
			$paramSer = serialize($arSearchParameters);
			$hash = substr(md5("vendor ".$paramSer), 0, 15);
			//$hash = md5(microtime());
			//$hash = substr($hash, 0, 15);
		
			$ar = array('QUERY' => $hash, 'LIFETIME' => date("Y-m-d H:i:s", $lifetime), 'S_STRING' => $paramSer, 'S_WHERE' => "");
			$id_known = $db->fetch_atom("SELECT `ID_SEARCHSTRING` FROM `searchstring` WHERE QUERY='".mysql_real_escape_string($hash)."'");
			if ($id_known > 0) $ar["ID_SEARCHSTRING"] = $id_known;
			$id = $db->update("searchstring", $ar);
		
			$arResult["COUNT"] = $resultCount;
			$arResult["HASH"] = $hash;
		}
		return $arResult;
	}
	
	public function getSearchHashSimple($month, $year, $arSearchParams = array()) {
		$dateStart = strtotime( sprintf("%d-%02d-%02d", $year, $month, 1) );
		$dateEnd = strtotime( sprintf("%d-%02d-%02d", $year, $month, date("t", $dateStart)) );
		$arSearchParams["STAMP_END_GT"] = date("Y-m-d", $dateStart);
		$arSearchParams["STAMP_START_LT"] = date("Y-m-d", $dateEnd);
		return $this->getSearchHash($arSearchParams);
	}

	public function generateFieldsQuery($param)
	{
		global $langval;
		$db = $this->getDb();

		$sqlFields = "";

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			$sqlFields = "ce.ID_CALENDAR_EVENT";
		} else {
			$sqlFields = "
				ce.*,
				(SELECT NAME FROM user WHERE ID_USER=ce.FK_USER) as USERNAME,
				if(ce.FK_REF_TYPE='club',(SELECT NAME FROM club WHERE ID_CLUB=ce.FK_REF),'') as CLUBNAME,
        		if(ce.FK_REF_TYPE='user',(SELECT NAME FROM vendor WHERE FK_USER=ce.FK_REF),'') as VENDORNAME,
				(SELECT V1 FROM string_kat WHERE S_TABLE = 'kat' AND FK = ce.FK_KAT AND BF_LANG = '".$langval."') AS KAT_NAME,
				sc.V1 AS COUNTRY, c.CODE AS COUNTRY_CODE,
				(SELECT count(*) FROM `calendar_event_signup` WHERE FK_CALENDAR_EVENT=ce.ID_CALENDAR_EVENT AND STATUS=0) as SIGNUPS_DECLINED,
				(SELECT count(*) FROM `calendar_event_signup` WHERE FK_CALENDAR_EVENT=ce.ID_CALENDAR_EVENT AND STATUS=1) as SIGNUPS_CONFIRMED,
				(SELECT count(*) FROM `calendar_event_signup` WHERE FK_CALENDAR_EVENT=ce.ID_CALENDAR_EVENT AND STATUS=2) as SIGNUPS_UNSURE,
				(SELECT AMOUNT FROM `comment_stats` WHERE `TABLE`='calendar_event' AND FK=ce.ID_CALENDAR_EVENT) as COMMENTS";
			if(isset($param['IS_SIGNED_UP'])) {
				global $uid;
				$sqlFields .= ",\n(SELECT STATUS FROM `calendar_event_signup` ces WHERE ces.FK_CALENDAR_EVENT=ce.ID_CALENDAR_EVENT AND ces.FK_USER=".(int)$uid.") AS SIGNUP_STATUS";
			}
		}

		return $sqlFields;
	}

	public function generateHavingQuery($param)
	{
		global $langval;
		$db = $this->getDb();

		$sqlHaving = [];

		if(isset($param['NO_FIELDS']) && $param['NO_FIELDS'] === TRUE) {
			if(isset($param['IS_SIGNED_UP'])) {
				global $uid;
				$sqlHaving[] = " (SELECT STATUS FROM `calendar_event_signup` ces WHERE ces.FK_CALENDAR_EVENT=ce.ID_CALENDAR_EVENT AND ces.FK_USER=".(int)$uid.") IS NOT NULL ";
			}
		} else {
			if(isset($param['IS_SIGNED_UP'])) {
				global $uid;
				$sqlHaving[] = " SIGNUP_STATUS IS NOT NULL ";
			}
		}

		return $sqlHaving;
	}

	public function generateWhereQuery($param)
	{
		global $ab_path, $langval;
		$db = $this->getDb();

		$sqlWhere = "";

		if(isset($param['ID_CALENDAR_EVENT']) && $param['ID_CALENDAR_EVENT'] != NULL) {
            $sqlWhere .= " AND ce.ID_CALENDAR_EVENT = '".mysql_real_escape_string($param['ID_CALENDAR_EVENT'])."' ";
        }
		if(isset($param['FK_USER']) && $param['FK_USER'] != NULL) {
			if (!is_array($param['FK_USER'])) {
				$sqlWhere .= " AND ce.FK_USER = '".mysql_real_escape_string($param['FK_USER'])."' ";
			} else {
				$sqlWhere .= " AND ce.FK_USER  IN (".implode(", ", $param['FK_USER']).") ";
			}
		}
		if(!empty($param['TITLE'])) {
			$sqlWhere .= " AND ce.TITLE LIKE '%".mysql_real_escape_string($param['TITLE'])."%'";
		}
		if(isset($param['FK_USER_MOD']) && $param['FK_USER_MOD'] != NULL) {
			// Matches if the user is allowed to moderate the comment
			require_once $ab_path."sys/lib.club.php";
			$clubManagement = ClubManagement::getInstance($db);
			$userClubIds = $clubManagement->getUserModClubIds((int)$param['FK_USER_MOD']);
			// Show all comments of clubs the user is owning/moderating,
			// and all personal comments of that user.
			$sqlWhere .= " AND (";
			if (!empty($userClubIds)) {
				$sqlWhere .= "(ce.FK_REF_TYPE='club' AND ce.FK_REF IN (".implode(", ", $userClubIds).")) OR ";
			}
			$sqlWhere .= "(ce.FK_REF_TYPE='user' AND ce.FK_USER=".(int)$param['FK_USER_MOD'].")";
			$sqlWhere .= ")";
			unset($param['FK_USER_OWNER']);
		}
		if(isset($param['VISIBLE_FOR_USER']) && $param['VISIBLE_FOR_USER'] != NULL) {
			require_once $ab_path."sys/lib.club.php";
			$clubManagement = ClubManagement::getInstance($db);
			$userClubIds = $clubManagement->getUserClubIds((int)$param['VISIBLE_FOR_USER']);

			$sqlWhere .= " AND ((";
			if (!empty($userClubIds)) {
				$sqlWhere .= "ce.FK_REF_TYPE='club' AND ce.FK_REF IN (".implode(", ", $userClubIds).") OR ";
			}
			$sqlWhere .= "ce.FK_USER=".(int)$param['VISIBLE_FOR_USER'].") OR ce.PRIVACY=1)";
			unset($param['FK_USER_OWNER']);
		}
		if(isset($param['FK_REF']) && $param['FK_REF'] != NULL) {
			if (is_array($param['FK_REF'])) {
				$arIds = array();
				// Escape
				foreach ($param['FK_REF'] as $index => $idEvent) {
					$arIds[] = (int)$idEvent;
				}
				$sqlWhere .= " AND ce.FK_REF IN (".implode(", ", $param['FK_REF']).") ";
			} else {
				$sqlWhere .= " AND ce.FK_REF = '".mysql_real_escape_string($param['FK_REF'])."' ";
			}
		}
		if (!empty($param['FK_REF_TYPE'])) {
            $sqlWhere .= " AND ce.FK_REF_TYPE = '".mysql_real_escape_string($param['FK_REF_TYPE'])."' ";
        }
		if (isset($param['SEARCHCALENDAREVENT']) && $param['SEARCHCALENDAREVENT'] != null) {
            $sqlWhere .= " AND ((ce.DESCRIPTION LIKE '%".mysql_real_escape_string($param['SEARCHCALENDAREVENT'])."%') OR (ce.TITLE LIKE '%".mysql_real_escape_string($param['SEARCHCALENDAREVENT'])."%')) ";
        }
        if (isset($param['LATITUDE']) && $param['LATITUDE'] != "" && isset($param['LONGITUDE']) && $param['LONGITUDE'] != "" && isset($param['LU_UMKREIS']) && $param['LU_UMKREIS'] != "" ) {
            $radius = 6368;

            $rad_b = $param['LATITUDE'];
            $rad_l = $param['LONGITUDE'];

            $rad_l = $rad_l / 180 * M_PI;
            $rad_b = $rad_b / 180 * M_PI;

            $sqlWhere .= " AND (
                    " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(ce.LATITUDE)) *
                     cos(" . $rad_b . ") * (sin(RADIANS(ce.LONGITUDE)) *
                     sin(" . $rad_l . ") + cos(RADIANS(ce.LONGITUDE)) *
                     cos(" . $rad_l . ")) - sin(RADIANS(ce.LATITUDE)) * sin(" . $rad_b . "))))
                ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $param['LU_UMKREIS']);
        }
        if(isset($param['IS_CONFIRMED']) && ($param['IS_CONFIRMED'] !== "")) {
            $sqlWhere .= " AND ce.IS_CONFIRMED = ".(int)$param['IS_CONFIRMED'];
        }
        if(isset($param['MODERATED']) && ($param['MODERATED'] !== "")) {
            $sqlWhere .= " AND ce.MODERATED = ".(int)$param['MODERATED'];
        }
		if(isset($param['PRIVACY']) && $param['PRIVACY'] !== NULL ) {
            $sqlWhere .= " AND ce.PRIVACY = '".mysql_real_escape_string($param['PRIVACY'])."' ";
        }
		if(isset($param['FK_COUNTRY']) && $param['FK_COUNTRY'] !== NULL ) {
            $sqlWhere .= " AND ce.FK_COUNTRY = ".$param['FK_COUNTRY'];
        }
		if(isset($param['STAMP_END_GT']) && $param['STAMP_END_GT'] != NULL ) {
			$sqlWhere .= " AND DATE(ce.STAMP_END) >= DATE('".mysql_real_escape_string($param['STAMP_END_GT'])."') ";
		}
		if(isset($param['STAMP_START_GT']) && $param['STAMP_START_GT'] != NULL ) {
	        $sqlWhere .= " AND DATE(ce.STAMP_START) >= DATE('".mysql_real_escape_string($param['STAMP_START_GT'])."') ";
	    }
		if(isset($param['STAMP_START_LT']) && $param['STAMP_START_LT'] != NULL ) {
			$sqlWhere .= " AND DATE(ce.STAMP_START) <= DATE('".mysql_real_escape_string($param['STAMP_START_LT'])."') ";
		}
		if(isset($param['BETWEEN_START']) && $param['BETWEEN_START'] != NULL && isset($param['BETWEEN_END']) && $param['BETWEEN_END'] != NULL) {
			$sqlWhere .= "
			AND (
				(ce.STAMP_START BETWEEN '".mysql_real_escape_string($param['BETWEEN_START'])."' AND '".mysql_real_escape_string($param['BETWEEN_END'])."')
			 	OR
				(ce.STAMP_END BETWEEN '".mysql_real_escape_string($param['BETWEEN_START'])."' AND '".mysql_real_escape_string($param['BETWEEN_END'])."')
			 	OR
				(ce.STAMP_START <= '".mysql_real_escape_string($param['BETWEEN_START'])."' AND ce.STAMP_END >= '".mysql_real_escape_string($param['BETWEEN_END'])."')
			 	)
			 ";
		}

		if(isset($param['CATEGORY']) && $param['CATEGORY'] != null) {
			$row_kat = $db->fetch1("SELECT LFT,RGT,ROOT FROM `kat` WHERE ID_KAT=" . $param['CATEGORY']);
			$ids_kats = $db->fetch_nar("
			  SELECT ID_KAT
				FROM `kat`
			  WHERE
				(LFT >= " . $row_kat["LFT"] . ") AND
				(RGT <= " . $row_kat["RGT"] . ") AND
				(ROOT = " . $row_kat["ROOT"] . ")
			");

			$sqlWhere .= " AND ce.FK_KAT IN (".mysql_real_escape_string(implode(',', array_keys($ids_kats))).") ";
		}
		return $sqlWhere;
	}

	public function generateJoinQuery($param)
	{
		global $langval;
		$db = $this->getDb();

		$sqlJoin = "
			LEFT JOIN `country` c ON ce.FK_COUNTRY=c.ID_COUNTRY
			LEFT JOIN `string` sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY
					AND sc.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))";

		return $sqlJoin;
	}

	protected function generateFetchQuery($param) {
		global $langval;
		$db = $this->getDb();

		$sqlLimit = "";
		$sqlWhere = "";
		$sqlJoin = "";
		$sqlFields = "";
		$sqlOrder = " ce.STAMP_START ";
		$sqlHaving = array();

		$sqlWhere = $this->generateWhereQuery($param);

		if(isset($param['SORT_BY']) && isset($param['SORT_DIR'])) {
			$arSortFields = array(
				"STAMP_START"	=> "ce.STAMP_START",
				"COMMENTS"		=> "COMMENTS",
				"TITLE"			=> "ce.TITLE"
			);
			$arSortDirs = array("ASC", "DESC");
			$sortBy = "ce.STAMP_START";
			$sortDir = "DESC";
			if (isset($param["SORT_BY"]) && in_array($param["SORT_BY"], array_keys($arSortFields))) {
				$sortBy = $arSortFields[ $param["SORT_BY"] ];
			}
			if (isset($param["SORT_DIR"]) && in_array($param["SORT_DIR"], $arSortDirs)) {
				$sortDir = $param["SORT_DIR"];
			}
			$sqlOrder = $sortBy." ".$sortDir;
		}
		if(isset($param['LIMIT']) && $param['LIMIT'] != NULL) {
			if(isset($param['OFFSET']) && $param['OFFSET'] != NULL) {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			} else {
				$sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' ';
			}
		}

		$sqlFields = $this->generateFieldsQuery($param);
		$sqlHaving = $this->generateHavingQuery($param);
		$sqlJoin = $this->generateJoinQuery($param);

		/*
		if(isset($param['LIMIT_DAY']) && isset($param['STAMP_END_GT'])) {
			$limit = (int)$param['LIMIT_DAY'];
			$queryCount = "
				SELECT
					SQL_CALC_FOUND_ROWS ce.ID_CALENDAR_EVENT
				FROM `calendar_event` ce
				".$sqlJoin."
				WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
				GROUP BY ce.ID_CALENDAR_EVENT
				".(empty($sqlHaving) ? "" : "HAVING ".implode(" AND ", $sqlHaving));
			$db->querynow($queryCount);
			$count_day = $db->fetch_atom("SELECT FOUND_ROWS()");

			if ($count_day > $limit) {
				$betweenStart = date("Y-m-d", strtotime($param['STAMP_END_GT'])). ' 00:00:00';
				$betweenEnd = date("Y-m-d", strtotime($param['STAMP_END_GT'])). ' 23:59:59';
				$sqlWhere .= "
					AND (
						(ce.STAMP_START BETWEEN '".mysql_real_escape_string($betweenStart)."' AND '".mysql_real_escape_string($betweenEnd)."')
						OR
						(ce.STAMP_END BETWEEN '".mysql_real_escape_string($betweenStart)."' AND '".mysql_real_escape_string($betweenEnd)."')
						OR
						(ce.STAMP_START <= '".mysql_real_escape_string($betweenStart)."' AND ce.STAMP_END >= '".mysql_real_escape_string($betweenEnd)."')
						)
					 ";
				//$sqlWhere .= " AND DATE('".$param['STAMP_START_GT']."') <= DATE(ce.STAMP_START)";
				$sqlLimit = false;
			} else {
				$sqlLimit = $limit;
			}
		}
		*/

		$query = "
			SELECT
				SQL_CALC_FOUND_ROWS
				".trim($sqlFields, " \t\r\n,")."
			FROM `calendar_event` ce
			".$sqlJoin."
			WHERE 1=1 ".($sqlWhere ? " ".$sqlWhere : "")."
			GROUP BY ce.ID_CALENDAR_EVENT
			".(empty($sqlHaving) ? "" : "HAVING ".implode(" AND ", $sqlHaving))."
			ORDER BY ".$sqlOrder."
				".($sqlLimit?'LIMIT '.$sqlLimit:'')."
		";
		return $query;
	}

	public function getCalendarEventCategoryTreeFlat($categoryId = null, $preSelectedNodes = array(), $arTreeNested = null, &$arResult = array(), $level = 0) {
		if ($arTreeNested === null) {
			$arTreeNested = $this->getCalendarEventCategoryTree($preSelectedNodes = array());
		}
		foreach ($arTreeNested as $index => $item) {
			$itemChilds = $item["children"];
			$itemActive = ($item["key"] == $categoryId);
			$itemInPath = in_array($categoryId, $item["childrenKeys"]);
			unset($item["children"]);
			$item["level"] = $level;
			$item["active"] = $itemActive;
			$item["in_path"] = $itemInPath;
			$arResult[] = $item;
			// If category (or child category) is active add children as well
			if (($categoryId !== null) && ($itemActive || $itemInPath)) {
				$this->getCalendarEventCategoryTreeFlat($categoryId, $preSelectedNodes, $itemChilds, $arResult, $level + 1);
			}
		}
		return $arResult;
	}

	public function getCalendarEventCategoryJSONTree($preSelectedNodes = array()) {
		return json_encode($this->getCalendarEventCategoryTree($preSelectedNodes));
	}

	public function getCalendarEventCategoryTree($preSelectedNodes = array()) {
		global $ab_path;
        require_once $ab_path.'sys/lib.nestedsets.php'; // Nested Sets

        $db = $this->getDb();
        $nest = new nestedsets('kat', self::CATEGORY_ROOT, false, $db);

        return $this->getCalendarEventCategoryArrayTreeRecursive(null, $nest, array(), $preSelectedNodes);
    }

	private function getCalendarEventCategoryArrayTreeRecursive($id, nestedsets $nest, $visitedNodes = array(), $preSelectedNodes = array()) {
		global $ab_path, $langval;
		require_once $ab_path.'sys/lib.shop_kategorien.php';

		$db = $this->getDb();
		$root = self::CATEGORY_ROOT;

		$rootrow = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `kat` t left join string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT and s.BF_LANG='".$langval."' where LFT=1 and ROOT='".$root."' ");

		if (!($id = (int)$id)) {
			$id = $rootrow['ID_KAT'];
			$lft = 1;
			$rgt = $rootrow['RGT'];
		} else {
			$lastresult = $db->querynow('select LFT,RGT from kat where ID_KAT=' . $id);
			list($lft, $rgt) = mysql_fetch_row($lastresult['rsrc']);
		}

		// Ahnenreihe lesen
		if ($lft == 1) {
			$ar_path = array();
			$n_level = 0;
		} else {
			$ar_path = $db->fetch_table($nest->nestQuery('and (' . $lft . ' between t.LFT and t.RGT) AND t.B_VIS = 1 ', '', '1 as is_last,1 as kidcount,1 as is_first,t.LFT=' . $lft . ' as is_current,', false), 'ID_KAT');
			$n_level = $ar_path[$id]['level'];
			$ar_path = array_values($ar_path);
		}

		// Kinder lesen
		$s_sql = $nest->nestQuery(' and (t.LFT between ' . $lft . ' and ' . $rgt . ')', '', 't.RGT-t.LFT>1 as haskids,', true);
		$s_sql = str_replace(' order by ', ' having level=' . (1 + $n_level) . ' order by ', $s_sql);
		$res = $db->querynow($s_sql);
		#echo ht(dump($res));

		if (!(int)$res['int_result']) // keine Kinder da -> kidcount der aktuellen Zeile auf 0
		{
			if ($n = count($ar_path)) $ar_path[$n - 1]['kidcount'] = 0;
		} else while ($row = mysql_fetch_assoc($res['rsrc'])) // sonst Kinder an Baum anhaengen
		{
			$row['kidcount'] = 0;
			$ar_path[] = $row;
		}

		if(is_array($ar_path) && count($ar_path) > 0) {
			$treeArray = array();
			$tplLink = new Template("tpl/".$GLOBALS['s_lang']."/empty.htm");

			foreach($ar_path as $key => $element) {
				if(!in_array($element['ID_KAT'], $visitedNodes)) {
					$visitedNodes[] = $element['ID_KAT'];
					$children = $this->getCalendarEventCategoryArrayTreeRecursive($element['ID_KAT'], $nest, $visitedNodes, $preSelectedNodes);

					$childrenKeys = array();
					foreach($children as $cKey => $child) {
						$childrenKeys = array_merge($childrenKeys, $child['childrenKeys'], array($child['key']));
					}

					$tplLink->vars['TITLE'] = $element['V1'];
					$treeArray[] = array(
						'key' => $element['ID_KAT'],
						'parentKey' => $id,
						'title' => $element['V1'],
						'link' => $tplLink->tpl_uri_action("calendar_events,".$element['ID_KAT'].",".addnoparse(chtrans($element['V1']))."|KAT_NAME={TITLE}"),
						'select' => in_array($element['ID_KAT'], $preSelectedNodes),
						'hideCheckbox' => (is_array($children) && (count($children) > 0)),
						'children' => $children,
						'childrenKeys' => $childrenKeys,
						'expand' => true
					);
				}
			}

			return $treeArray;
		} else {
			return null;
		}


	}

	public function fetchAllSearchWordsByCalendarEventId($calendarEventId, $language) {
		$db = $this->getDb();

		$calendarEvent = $this->fetchById($calendarEventId);

		if($calendarEvent !== null) {
			return $db->fetch_table("
				SELECT
					w.ID_WORDS, w.wort,
					'".mysql_real_escape_string($language)."' AS S_LANG,
					0 AS DIRTY
				FROM
					searchdb_index_".$language." i
				JOIN searchdb_words_".$language." w ON w.ID_WORDS = i.FK_WORDS
				WHERE
					S_TABLE = 'calendar_event' AND FK_ID = '".$calendarEvent['ID_CALENDAR_EVENT']."'
				ORDER BY w.wort
			");

		} else {
			return null;
		}
	}

	/**
	 * Fügt ein neues Schlagwort zu einem Event hinzu
	 *
	 * @param $searchWord
	 * @param $calendarEventId
	 * @param string $language
	 * @return bool
	 */
	public function addCalendarEventSearchWordByCalendarEventId($searchWord, $calendarEventId, $language = "de") {
		global $ab_path;
		require_once $ab_path."admin/sys/lib.search.php";

		$calendarEvent = $this->fetchById($calendarEventId);

		if($calendarEvent !== null) {
			$doSearch = new do_search($language, true);
			$doSearch->add_new_word($searchWord, $calendarEvent['ID_CALENDAR_EVENT'], "calendar_event");

			return true;
		}
	}

	/**
	 * Löscht ein Schlagwort eines Events
	 *
	 * @param $searchWord
	 * @param $calendarEventId
	 * @param string $language
	 * @return bool
	 */
	public function deleteCalendarEventSearchWordByCalendarEventId($searchWord, $calendarEventId, $language = "de") {
		global $ab_path;
		require_once $ab_path."admin/sys/lib.search.php";

		$calendarEvent = $this->fetchById($calendarEventId);

		if($calendarEvent !== null) {
			$doSearch = new do_search($language, true);
			$doSearch->delete_word_from_searchindex($searchWord, $calendarEventId, "calendar_event");

			return true;
		}
	}

	public function deleteById($id) {
		$db = $this->getDb();

		$db->querynow("DELETE FROM calendar_event WHERE ID_CALENDAR_EVENT = '" . mysql_real_escape_string($id) . "'");
		$db->querynow("DELETE FROM calendar_event_gallery WHERE FK_CALENDAR_EVENT = '" . mysql_real_escape_string($id) . "'");
		$db->querynow("DELETE FROM calendar_event_gallery_video WHERE FK_CALENDAR_EVENT = '" . mysql_real_escape_string($id) . "'");

		return TRUE;
	}

	public function confirmById($id, $state) {
		$db = $this->getDb();

		$db->querynow("UPDATE calendar_event SET IS_CONFIRMED=".(int)$state." WHERE ID_CALENDAR_EVENT = '" . mysql_real_escape_string($id) . "'");

		return TRUE;
	}

	public function getUserSignupStatus($id, $userId) {
		$db = $this->getDb();
		$status = $db->fetch_atom("SELECT STATUS FROM `calendar_event_signup` WHERE FK_CALENDAR_EVENT=".(int)$id." AND FK_USER=".(int)$userId);
		return ($status >= 0 ? $status : false);
	}

	public function userSignup($id, $userId, $status = 1) {
		global $ab_path;
		$ar_calendar_event = $this->fetchById($id);
		if (!is_array($ar_calendar_event)) {
			// Event not found
			return false;
		}
		$db = $this->getDb();
		if ($ar_calendar_event["PRIVACY"] == 0) {
			// Check rights
			switch ($ar_calendar_event["FK_REF_TYPE"]) {
				case "club":
					require_once $ab_path."sys/lib.club.php";
					$clubManagement = ClubManagement::getInstance($db);
					if (!$clubManagement->isMember($ar_calendar_event["FK_REF"], $userId)) {
						// Not a club member! Not allowed to signup for a club-exclusive event
						return false;
					}
					break;
				default:
					// Not allowed to signup for a private event
					return false;
			}
		}
		$res = $db->querynow($q = "INSERT INTO `calendar_event_signup` (FK_CALENDAR_EVENT, FK_USER, STATUS, STAMP_SIGNUP)
			VALUES (".(int)$id.", ".(int)$userId.", ".(int)$status.", '".date("Y-m-d H:i:s")."')
			ON DUPLICATE KEY UPDATE
				STATUS=".(int)$status.", STAMP_SIGNUP='".date("Y-m-d H:i:s")."'");
		return $res["rsrc"];
	}

	public function userSignupCancel($id, $userId) {
		$ar_calendar_event = $this->fetchById($id);
		if (!is_array($ar_calendar_event)) {
			// Event not found
			return false;
		}
		$db = $this->getDb();
		$db->querynow("DELETE FROM `calendar_event_signup` WHERE FK_CALENDAR_EVENT=".(int)$id." AND FK_USER=".(int)$userId);
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
