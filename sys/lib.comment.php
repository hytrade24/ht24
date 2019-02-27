<?php

class CommentManagement {


	/**
	 * An array of object instances with a maximum of one for each target table
	 * @var array	$instances;
	 */
	private static $instances = NULL;

	/**
	 * The database connection to be used
	 *
	 * @var ebiz_db	$db
	 */
	private $db;

	/**
	 * The database table the comments relate to.
	 *
	 * @var	string	$table
	 */
	private $table;

	/**
	 * List to associate the table with the corresponding URL
	 * @var array 	$target
	 */
	private $target = array(
		"ad_master" => "marktplatz_anzeige",
		"vendor" => "view_user_vendor",
		"club" => "club",
		"news" => "news",
		'calendar_event' => "calendar_events_view",
	);

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return CommentManagement
	 */
	public static function getInstance(ebiz_db $db, $table = '*') {
		if (self::$instances[$table] === NULL) {
			self::$instances[$table] = new self($db, $table);
		}

		return self::$instances[$table];
	}

	/**
	 * Gets the target table of a specific comment
	 *
	 * @param ebiz_db $db
	 * @return string
	 */
	public static function getCommentTable(ebiz_db $db, $id_comment) {
		return $db->fetch_atom("SELECT `TABLE` FROM `comment` WHERE ID_COMMENT=".(int)$id_comment);
	}

	/**
	 * Gets the url of what was commented
	 *
	 * @param ebiz_db $db
	 * @return string
	 */
	public static function getCommentTargetLink(ebiz_db $db, $id_comment) {
		$ar_comment = $db->fetch1("SELECT `TABLE`, `FK`, `FK_STR` `FK_USER_OWNER` FROM `comment` WHERE ID_COMMENT=".$id_comment);
		if ($ar_comment !== false) {
			$cm = self::getInstance($db, $ar_comment['TABLE']);
            switch ($ar_comment['TABLE']) {
                case 'news':
					if ($ar_comment['FK'] > 0) {
						$target = $cm->getTargetLink($ar_comment['FK'], ",,,,");
					} else {
						$target = $cm->getTargetLinkStr($ar_comment['FK_STR'], ",,,,");
					}
                    break;
                default:
					if ($ar_comment['FK'] > 0) {
						$target = $cm->getTargetLink($ar_comment['FK']);
					} else {
						$target = $cm->getTargetLinkStr($ar_comment['FK_STR']);
					}
                    break;
            }

			return $target;
		} else {
			return "";
		}
	}

	/**
	 * Gets the title of what was commented
	 *
	 * @param ebiz_db $db
	 * @return string
	 */
	public static function getTargetTitle(ebiz_db $db, $table, $fk) {
		global $langval;
		$title = "???";
		switch ($table) {
			case 'ad_master':
				// "{MANUFACTURER} {PRODUKTNAME}"
				$ar_ad = $db->fetch1("SELECT PRODUKTNAME FROM `ad_master` WHERE ID_AD_MASTER=".(int)$fk);
				$title = $ar_ad["PRODUKTNAME"];
				if ($ar_ad['FK_MAN'] > 0) {
					$title = $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".$ar_ad['FK_MAN']).
					" ".$title;
				}
				break;
			case 'club':
				$title = $db->fetch_atom("SELECT NAME FROM `".mysql_real_escape_string($table)."`
					WHERE ID_".mysql_real_escape_string(strtoupper($table))."=".(int)$fk);
				break;
			case 'calendar_event':
				$title = $db->fetch_atom("SELECT TITLE FROM `".mysql_real_escape_string($table)."`
					WHERE ID_".mysql_real_escape_string(strtoupper($table))."=".(int)$fk);
				break;
			case 'vendor':
				$title = $db->fetch_atom("SELECT NAME FROM `".mysql_real_escape_string($table)."`
					WHERE ID_".mysql_real_escape_string(strtoupper($table))."=".(int)$fk);
				break;
			case 'news':
				$title = $db->fetch_atom("SELECT
					s.V1
				FROM `news` n
				LEFT JOIN `string_c` s ON s.S_TABLE='news' AND s.FK=n.ID_NEWS
					AND s.BF_LANG=if(n.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(n.BF_LANG_C+0.5)/log(2)))
				WHERE ID_NEWS=".(int)$fk);
				break;
		}
		return $title;
	}

	/**
	 * Gets the title of what was commented
	 *
	 * @param ebiz_db $db
	 * @return string
	 */
	public static function getTargetTitleByFkStr(ebiz_db $db, $table, $fk_str) {
		global $langval;
		$title = "???";
		switch ($table) {
			case 'ad_master':
				// "{MANUFACTURER} {PRODUKTNAME}"
				$ar_ad = $db->fetch1("SELECT PRODUKTNAME, FK_MAN FROM `ad_master` WHERE EAN='".mysql_real_escape_string($fk_str)."'");
				$title = $ar_ad["PRODUKTNAME"];
				if ($ar_ad['FK_MAN'] > 0) {
					$title = $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".$ar_ad['FK_MAN']).
					" ".$title;
				}
				break;
		}
		return $title;
	}

	function __construct(ebiz_db $db, $table) {
		$this->db = $db;
		$this->table = $table;
	}

	/**
	 * Add an new comment for the given user. (By user-id)
	 * @param int		$id
	 * @param string	$title
	 * @param string	$comment
	 * @param int		$userId
	 * @return boolean
	 */
	function addComment($id, $title, $comment, $userId = null, $rating = null) {
		global $nar_systemsettings;
		if ($this->table == '*') {
			// Can't add comment if not on a specific table
			return false;
		}
		if ($userId === null) {
			global $uid;
			$userId = $uid;
		}
		if ($userId > 0) {
			$userIdOwner = $this->getUserOwner($id);
			$autoconfirm = ($nar_systemsettings["SITE"]["COMMENT_CONFIRM"] ? false : $this->hasUserAutoconfirmEnabled($userIdOwner));
			
			$ar_comment = array(
				"TABLE"			=> $this->table,
				"TABLE_DESC"	=> $this->getTableDescription(),
				"FK"			=> $id,
				"FK_USER"		=> $userId,
				"FK_USER_OWNER"	=> $userIdOwner,
				"RATING"		=> $rating,
				"USER_NAME"		=> $this->db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$userId),
				"IS_CONFIRMED"	=> 1,
				"IS_PUBLIC"		=> 1,
				"IS_REVIEWED"   => ($autoconfirm ? 1 : 0),
				"TITLE"			=> $title,
				"COMMENT"		=> $comment,
				"AUTOCONFIRM"	=> $autoconfirm
			);
            $result = $this->db->querynow("INSERT INTO `comment` (`TABLE`, `FK`, `FK_USER_OWNER`, `FK_USER`, `STAMP`, `RATING`, `IS_CONFIRMED`, `IS_PUBLIC`, `IS_REVIEWED`, `TITLE`, `COMMENT`)
					VALUES ('".mysql_real_escape_string($this->table)."', ".(int)$id.", ".$userIdOwner.", ".(int)$userId.", NOW(), ".($rating !== NULL ? (int)$rating : "NULL").", '".$ar_comment["IS_CONFIRMED"]."',
						'".$ar_comment["IS_PUBLIC"]."', '".$ar_comment["IS_REVIEWED"]."', '".mysql_real_escape_string($title)."', '".mysql_real_escape_string($comment)."')");
            $id_comment = $result['int_result'];
			
			if ($id_comment > 0) {
				$ar_comment['ID_COMMENT'] = $id_comment;
				$ar_comment['NAME'] = $this->db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$userIdOwner);
				sendMailTemplateToUser(0, $userIdOwner, 'COMMENT_NEW', $ar_comment);
				$this->updateCommentStats($id_comment);
				$this->generateDataAndCallWidegtPNG( $ar_comment );
				return $id_comment;
			}
			return false;
		} else {
			return false;
		}
	}

	/**
	 * Add an new comment for the given user. (By user-id)
	 * @param string	$id
	 * @param string	$title
	 * @param string	$comment
	 * @param int		$userId
	 * @return boolean
	 */
	function addCommentStr($id, $title, $comment, $userId = null, $rating = null) {
		global $nar_systemsettings;
		if ($this->table == '*') {
			// Can't add comment if not on a specific table
			return false;
		}
		if ($userId === null) {
			global $uid;
			$userId = $uid;
		}
		if ($userId > 0) {
			$userIdOwner = $this->getUserOwnerStr($id);
			$autoconfirm = ($nar_systemsettings["SITE"]["COMMENT_CONFIRM"] ? false : $this->hasUserAutoconfirmEnabled($userIdOwner));
			$ar_comment = array(
				"TABLE"			=> $this->table,
				"TABLE_DESC"	=> $this->getTableDescription(),
				"FK_STR"		=> $id,
				"FK_USER"		=> $userId,
				"FK_USER_OWNER"	=> $userIdOwner,
				"RATING"		=> $rating,
				"USER_NAME"		=> $this->db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$userId),
				"IS_CONFIRMED"	=> 1,
				"IS_PUBLIC"		=> 1,
				"IS_REVIEWED"   => ($autoconfirm ? 1 : 0),
				"TITLE"			=> $title,
				"COMMENT"		=> $comment,
				"AUTOCONFIRM"	=> $autoconfirm
			);

            $result = $this->db->querynow($q="INSERT INTO `comment` (`TABLE`, `FK_STR`, `FK_USER_OWNER`, `FK_USER`, `STAMP`, `RATING`, `IS_CONFIRMED`, `IS_PUBLIC`, `IS_REVIEWED`, `TITLE`, `COMMENT`)
					VALUES ('".mysql_real_escape_string($this->table)."', '".mysql_real_escape_string($id)."', ".$userIdOwner.", ".(int)$userId.", NOW(), ".($rating !== NULL ? (int)$rating : "NULL").", '".$ar_comment["IS_CONFIRMED"]."',
						'".$ar_comment["IS_PUBLIC"]."', '".$ar_comment["IS_REVIEWED"]."', '".mysql_real_escape_string($title)."', '".mysql_real_escape_string($comment)."')");
            $id_comment = $result['int_result'];
			if ($id_comment > 0) {
				$ar_comment['ID_COMMENT'] = $id_comment;
				$ar_comment['NAME'] = $this->db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$userIdOwner);
				sendMailTemplateToUser(0, $userIdOwner, 'COMMENT_NEW', $ar_comment);
				$this->updateCommentStats($id_comment);
				return $id_comment;
			}
			return false;
		} else {
			return false;
		}
	}

	/**
	 * Add an new comment as anonymous user. (By username and email)
	 * @param int		$id
	 * @param string	$title
	 * @param string	$comment
	 * @param string	$userName
	 * @param string	$userMail
	 * @return boolean
	 */
	function addCommentAnonymous($id, $title, $comment, $userName, $userMail, $rating = null) {
		global $nar_systemsettings;
		if ($this->table == '*') {
			// Can't add comment if not on a specific table
			return false;
		}
		$userIdOwner = $this->getUserOwner($id);
		$autoconfirm = ($nar_systemsettings["SITE"]["COMMENT_CONFIRM"] ? false : $this->hasUserAutoconfirmEnabled($userIdOwner));

		$ar_comment = array(
			"TABLE"			=> $this->table,
			"TABLE_DESC"	=> $this->getTableDescription(),
			"FK"			=> $id,
			"FK_USER_OWNER"	=> $userIdOwner,
			"RATING"		=> $rating,
			"CODE"			=> createpass(),
			"USER_NAME"		=> $userName,
			"USER_MAIL"		=> $userMail,
			"IS_CONFIRMED"	=> 0,
			"IS_PUBLIC"		=> 1,
			"IS_REVIEWED"   => ($autoconfirm ? 1 : 0),
			"TITLE"			=> $title,
			"COMMENT"		=> $comment,
			"AUTOCONFIRM"	=> $autoconfirm
		);
        $result = $this->db->querynow("INSERT INTO `comment` (`TABLE`, `FK`, `FK_USER_OWNER`, `CODE`, `USER_NAME`, `USER_MAIL`, `STAMP`, `RATING`, `IS_CONFIRMED`, `IS_PUBLIC`, `IS_REVIEWED`, `TITLE`, `COMMENT`)
				VALUES ('".mysql_real_escape_string($this->table)."', ".(int)$id.", ".$userIdOwner.", '".mysql_real_escape_string($ar_comment['CODE'])."',
					'".mysql_real_escape_string($userName)."', '".mysql_real_escape_string($userMail)."', NOW(), ".($rating !== NULL ? (int)$rating : "NULL").", '".$ar_comment["IS_CONFIRMED"]."',
					'".$ar_comment["IS_PUBLIC"]."', '".$ar_comment["IS_REVIEWED"]."', '".mysql_real_escape_string($title)."', '".mysql_real_escape_string($comment)."')");
        $id_comment = $result['int_result'];
		if ($id_comment > 0) {
			$ar_comment['ID_COMMENT'] = $id_comment;
			$ar_comment['NAME'] = $userName;
			sendMailTemplateToUser(0, $userMail, 'COMMENT_CONFIRM', $ar_comment);
			return $id_comment;
		}
		return false;
	}

	/**
	 * Add an new comment as anonymous user. (By username and email)
	 * @param int		$id
	 * @param string	$title
	 * @param string	$comment
	 * @param string	$userName
	 * @param string	$userMail
	 * @return boolean
	 */
	function addCommentAnonymousStr($id, $title, $comment, $userName, $userMail, $rating = null) {
		global $nar_systemsettings;
		if ($this->table == '*') {
			// Can't add comment if not on a specific table
			return false;
		}
		$userIdOwner = $this->getUserOwnerStr($id);
		$autoconfirm = ($nar_systemsettings["SITE"]["COMMENT_CONFIRM"] ? false : $this->hasUserAutoconfirmEnabled($userIdOwner));

		$ar_comment = array(
			"TABLE"			=> $this->table,
			"TABLE_DESC"	=> $this->getTableDescription(),
			"FK_STR"		=> $id,
			"FK_USER_OWNER"	=> $userIdOwner,
			"RATING"		=> $rating,
			"CODE"			=> createpass(),
			"USER_NAME"		=> $userName,
			"USER_MAIL"		=> $userMail,
			"IS_CONFIRMED"	=> 0,
			"IS_PUBLIC"		=> 1,
			"IS_REVIEWED"   => ($autoconfirm ? 1 : 0),
			"TITLE"			=> $title,
			"COMMENT"		=> $comment,
			"AUTOCONFIRM"	=> $autoconfirm
		);
        $result = $this->db->querynow("INSERT INTO `comment` (`TABLE`, `FK_STR`, `FK_USER_OWNER`, `CODE`, `USER_NAME`, `USER_MAIL`, `STAMP`, `RATING`, `IS_CONFIRMED`, `IS_PUBLIC`, `IS_REVIEWED`, `TITLE`, `COMMENT`)
				VALUES ('".mysql_real_escape_string($this->table)."', '".mysql_real_escape_string($id)."', ".$userIdOwner.", '".mysql_real_escape_string($ar_comment['CODE'])."',
					'".mysql_real_escape_string($userName)."', '".mysql_real_escape_string($userMail)."', NOW(), ".($rating !== NULL ? (int)$rating : "NULL").", '".$ar_comment["IS_CONFIRMED"]."',
					'".$ar_comment["IS_PUBLIC"]."', '".$ar_comment["IS_REVIEWED"]."', '".mysql_real_escape_string($title)."', '".mysql_real_escape_string($comment)."')");
        $id_comment = $result['int_result'];
		
		if ($id_comment > 0) {
			$ar_comment['ID_COMMENT'] = $id_comment;
			$ar_comment['NAME'] = $userName;
			sendMailTemplateToUser(0, $userMail, 'COMMENT_CONFIRM', $ar_comment);
			return $id_comment;
		}
		return false;
	}

    function clearCommentCache($id, $table) {
        $arCacheRelations = array("COMMENT_RATING" => 1, $table => $id);
        Api_DatabaseCacheStorage::getInstance()->deleteContentByRelations($arCacheRelations);
    }

    function clearCommentCacheStr($id, $table) {
        $arCacheRelations = array("COMMENT_RATING" => 1, $table."_".$id => 1);
        Api_DatabaseCacheStorage::getInstance()->deleteContentByRelations($arCacheRelations);
    }

	function confirmComment($idComment, $code) {
		$ar_comment = $this->fetchOneByParams(array("ID_COMMENT" => $idComment));
		if ($ar_comment !== false) {
			if ($ar_comment["CODE"] == $code) {
				$query = "UPDATE `comment` SET IS_CONFIRMED=1, CODE=NULL WHERE ID_COMMENT=".$idComment;
				$this->db->querynow($query);
				// Send E-Mail
				$ar_comment['ID_COMMENT'] = $idComment;
				$ar_comment['NAME'] = $this->db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$ar_comment['FK_USER_OWNER']);
				sendMailTemplateToUser(0, $ar_comment["FK_USER_OWNER"], 'COMMENT_NEW', $ar_comment);
				$this->updateCommentStats($idComment);
				return true;
			} else if ($ar_comment["IS_CONFIRMED"]) {
				return true;
			}
		}
		return false;
	}

	function deleteComment($idComment, $userId = null, $canDeleteOwnComment = false) {
		if ($userId === null) {
			global $uid;
			$userId = $uid;
		}
        $id_user = $this->db->fetch_atom("SELECT FK_USER FROM `comment` WHERE ID_COMMENT=".(int)$idComment);
        if (($this->isUserAdmin($idComment, $userId)) || ($canDeleteOwnComment && $userId == $id_user)) {
			$this->db->querynow("DELETE FROM `comment` WHERE ID_COMMENT=".(int)$idComment);
			$this->updateCommentStats($idComment);
			return true;
		}
		return false;
	}

	/**
	 * Delete all comments for the given id
	 * @param int	$fk
	 * @return boolean
	 */
	function deleteAllComments($fk) {
		if ($this->table == '*') {
			// Can't delete comments by fk if not on a specific table
			return false;
		}
		$this->db->querynow("DELETE FROM `comment` WHERE
				`TABLE`='".mysql_real_escape_string($this->table)."' AND `FK`=".(int)$fk);
		$this->updateCommentStatsByFk($fk);
		return true;
	}

	function fetchBuildWhere($params) {
    global $uid, $ab_path;
    // Remove sorting / limits
    unset($params["SORT_BY"]);
    unset($params["SORT_DIR"]);
    unset($params["PERPAGE"]);
    unset($params["OFFSET"]);
    if (!((int)$params["FK"] > 0)) {
      unset($params["FK"]);
    }
    // Generate where
    $tableWhere = array();
    if (trim($params['SEARCH']) != "") {
      $tableWhere[] = "c.`COMMENT` LIKE '%" . mysql_real_escape_string(trim($params['SEARCH'])) . "%'";
    }
    unset($params['SEARCH']);
    if (isset($params['TYPE'])) {
      if ($params['TYPE'] == 'ALL') {
        // Get user clubs
        require_once $ab_path . "sys/lib.club.php";
        $clubManagement = ClubManagement::getInstance($this->db);
        $userClubIds = $clubManagement->getUserModClubIds($uid);
        if (!empty($userClubIds)) {
          // Show all comments of clubs the user is owning/moderating,
          // and all personal comments of that user.
          $tableWhere[] = "(c.`TABLE`='club' AND c.`FK` IN (" . implode(", ", $userClubIds) . ")) OR c.`FK_USER_OWNER`=" . (int)$uid;
        } else {
          $tableWhere[] = "c.`FK_USER_OWNER`=" . (int)$uid;
        }
        unset($params['FK_USER_OWNER']);
      }
      if ($params['TYPE'] == 'SENT') {
        $tableWhere[] = "c.`FK_USER`=" . (int)$uid;
        unset($params['FK_USER']);
      } elseif ($params['TYPE'] == 'AD') {
        $params['TABLE'] = 'ad_master';
        $params['FK_USER_OWNER'] = (int)$uid;
      } elseif ($params['TYPE'] == 'NEWS') {
        $params['TABLE'] = 'news';
        $params['FK_USER_OWNER'] = (int)$uid;
      } elseif ($params['TYPE'] == 'VENDOR') {
        $params['FK'] = $this->db->fetch_atom("SELECT ID_VENDOR FROM `vendor` WHERE FK_USER=" . (int)$uid);
        $params['TABLE'] = 'vendor';
      } elseif ($params['TYPE'] == 'EVENTS_GROUP') {
        require_once $ab_path . "sys/lib.club.php";
        $clubManagement = ClubManagement::getInstance($this->db);
        $userClubIds = $clubManagement->getUserModClubIds($uid);
        $userEventIds = $this->db->fetch_col("SELECT ID_CALENDAR_EVENT FROM `calendar_event` WHERE FK_REF_TYPE='club' AND FK_REF IN (" . (!empty($userClubIds) ? implode(", ", $userClubIds) : "0") . ")");
        $tableWhere[] = "c.FK IN (" . (!empty($userEventIds) ? implode(", ", $userEventIds) : "0") . ")";
        $params['TABLE'] = 'calendar_event';
      } elseif ($params['TYPE'] == 'EVENTS_VENDOR') {
        $userEventIds = $this->db->fetch_col("SELECT ID_CALENDAR_EVENT FROM `calendar_event` WHERE FK_REF_TYPE='user' AND FK_REF=" . (int)$uid);
        $tableWhere[] = "c.FK IN (" . (!empty($userEventIds) ? implode(", ", $userEventIds) : "0") . ")";
        $params['TABLE'] = 'calendar_event';
      } elseif ($params['TYPE'] == 'GROUPS') {
        // Get user clubs
        require_once $ab_path . "sys/lib.club.php";
        $clubManagement = ClubManagement::getInstance($this->db);
        // Show all clubs where the user is moderator
        $userClubIds = $clubManagement->getUserModClubIds($uid);
        $tableWhere[] = "c.FK IN (" . (!empty($userClubIds) ? implode(", ", $userClubIds) : "0") . ")";
        $params['TABLE'] = 'club';
        unset($params['FK_USER_OWNER']);
      } elseif (preg_match("/^GROUP\_([0-9]+)$/", $params['TYPE'], $matches)) {
        // Get user clubs
        $userClubId = (int)$matches[1];
        $params['FK'] = $userClubId;
        $params['TABLE'] = 'club';
        unset($params['FK_USER_OWNER']);
      }
      unset($params["TYPE"]);
    }
    if (isset($params['NAME_'])) {
      $tableWhere[] = "(`user`.NAME LIKE '%" . mysql_real_escape_string($params['NAME_']) . "%'" .
        " OR user_owner.NAME LIKE '%" . mysql_real_escape_string($params['NAME_']) . "%'" .
        " OR c.USER_NAME LIKE '%" . mysql_real_escape_string($params['NAME_']) . "%'" .
        " OR USER_MAIL LIKE '%" . mysql_real_escape_string($params['NAME_']) . "%')";
      unset($params['NAME_']);
    }
    if (array_key_exists("FK_USER", $params)) {
      $tableWhere[] = "(c.FK_USER=" . (int)$params["FK_USER"] . " OR c.FK_USER_OWNER=" . (int)$params["FK_USER"] . ")";
      unset($params["FK_USER"]);
    }
    if ($params["IS_REVIEWED"] === "all") {
      unset($params["IS_REVIEWED"]);
    }
    if ($params["IS_PUBLIC"] === "all") {
      unset($params["IS_PUBLIC"]);
    }
    if ($params["RATING_MIN"] > 0) {
      $tableWhere[] = "c.RATING>=" . (int)$params["RATING_MIN"];
      unset($params["RATING_MIN"]);
    }
    if ($params["RATING_MAX"] > 0) {
      $tableWhere[] = "c.RATING<=" . (int)$params["RATING_MAX"];
      unset($params["RATING_MAX"]);
    }
    foreach ($params as $key => $value) {
      $tableWhere[] = "c.`" . mysql_real_escape_string($key) . "`='" . mysql_real_escape_string($value) . "'";
    }
		return $tableWhere;
	}

	function fetchBuildOrder($params = null) {
		$arSortFields = array(
			"STAMP" 		=> "c.STAMP",
			"IS_REVIEWED"	=> ""
		);
		$arSortDirs = array("ASC", "DESC");
		$sortBy = "c.STAMP";
		$sortDir = "DESC";
		if (isset($params["SORT_BY"]) && in_array($params["SORT_BY"], array_keys($arSortFields))) {
			$sortBy = $arSortFields[ $params["SORT_BY"] ];
		}
		if (isset($params["SORT_DIR"]) && in_array($params["SORT_DIR"], array_keys($arSortDirs))) {
			$sortDir = $params["SORT_DIR"];
		}
		return $sortBy." ".$sortDir;
	}

	function fetchCountByParams($params = null) {
		global $langval;

		if ($this->table != '*') {
			// Select only comments for the active table
			$params['TABLE'] = $this->table;
		}

		$tableWhere = $this->fetchBuildWhere($params);

		$query = "SELECT
					count(*)
				FROM `comment` c
				LEFT JOIN `user` ON `user`.ID_USER=c.FK_USER
				LEFT JOIN `user` user_owner ON user_owner.ID_USER=c.FK_USER_OWNER
				WHERE ".(empty($tableWhere) ? "1" : implode(" AND ", $tableWhere));
		$count = $this->db->fetch_atom($query);

		return $count;
	}

	function fetchOneByParams($params = null) {
		global $langval;

		if ($this->table != '*') {
			// Select only comments for the active table
			$params['TABLE'] = $this->table;
		}

		$tableWhere = $this->fetchBuildWhere($params);

		$query = "SELECT
					c.*,
					if(c.FK_USER>0, `user`.NAME, c.USER_NAME) as USER_NAME,
					if(c.FK_USER>0, `user`.EMAIL, c.USER_MAIL) as USER_MAIL,
					if(c.FK_USER_OWNER>0, user_owner.EMAIL, c.USER_MAIL) as ANSWER_USER_MAIL,
					if(c.FK_USER_OWNER>0, user_owner.NAME, c.USER_NAME) as ANSWER_USER_NAME,
					`user`.CACHE as USER_CACHE,
					user_owner.CACHE as ANSWER_USER_CACHE,
					(SELECT ts.V1 FROM `message` tm
						LEFT JOIN `string_app` ts ON ts.S_TABLE='message' AND ts.FK=tm.ID_MESSAGE
							AND ts.BF_LANG=if(tm.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(tm.BF_LANG_APP+0.5)/log(2)))
					 WHERE tm.FKT='COMMENT_TABLES' AND tm.ERR=c.`TABLE`) as TABLE_DESC
				FROM `comment` c
				LEFT JOIN `user` ON `user`.ID_USER=c.FK_USER
				LEFT JOIN `user` user_owner ON user_owner.ID_USER=c.FK_USER_OWNER
				WHERE ".(empty($tableWhere) ? "1" : implode(" AND ", $tableWhere))."
				ORDER BY c.STAMP DESC
				LIMIT 1";
		$ar_result = $this->db->fetch1($query);

		return $ar_result;
	}

	function fetchAllByParams($params = null, $limitOffset = 0, $limitCount = 10, &$all = null) {
		global $langval;
		if ($params === null) {
			// Defaults
			$params = array('IS_CONFIRMED' => 1, 'IS_PUBLIC' => 1, 'IS_REVIEWED' => 1);
		}
		if ($this->table != '*') {
			// Select only comments for the active table
			$params['TABLE'] = $this->table;
		}
		$tableWhere = $this->fetchBuildWhere($params);
		$tableOrder = $this->fetchBuildOrder($params);
		$query = "SELECT
					".($all !== null ? "SQL_CALC_FOUND_ROWS" : "")."
					c.*,
					if(c.FK_USER>0, `user`.NAME, c.USER_NAME) as USER_NAME,
					if(c.FK_USER_OWNER>0, user_owner.NAME, c.USER_NAME) as ANSWER_USER_NAME,
					`user`.CACHE as USER_CACHE,
					user_owner.CACHE as ANSWER_USER_CACHE,
					(SELECT ts.V1 FROM `message` tm
						LEFT JOIN `string_app` ts ON ts.S_TABLE='message' AND ts.FK=tm.ID_MESSAGE
							AND ts.BF_LANG=if(tm.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(tm.BF_LANG_APP+0.5)/log(2)))
					 WHERE tm.FKT='COMMENT_TABLES' AND tm.ERR=c.`TABLE`) as TABLE_DESC,
					(SELECT ts.V1 FROM `message` tm
						LEFT JOIN `string_app` ts ON ts.S_TABLE='message' AND ts.FK=tm.ID_MESSAGE
							AND ts.BF_LANG=if(tm.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(tm.BF_LANG_APP+0.5)/log(2)))
					 WHERE tm.FKT='COMMENT_TABLES' AND tm.ERR=CONCAT(c.`TABLE`, '_str')) as TABLE_DESC_STR
				FROM `comment` c
				LEFT JOIN `user` ON `user`.ID_USER=c.FK_USER
				LEFT JOIN `user` user_owner ON user_owner.ID_USER=c.FK_USER_OWNER
				WHERE ".(empty($tableWhere) ? "1" : implode(" AND ", $tableWhere))."
				ORDER BY c.STAMP DESC
				LIMIT ".(int)$limitOffset.", ".(int)$limitCount;
        #die($query);
		$ar_result = $this->db->fetch_table($query);
		if ($all !== null) {
			$all = $this->db->fetch_atom("SELECT FOUND_ROWS();");
		}
		return $ar_result;
	}

	//$this->generateDataAndCallWidegtPNG($userId);
	function generateDataAndCallWidegtPNG($ar_comment) {

		if ( $ar_comment["TABLE"] == "vendor" && $ar_comment["IS_CONFIRMED"] == 1
				&& $ar_comment["IS_PUBLIC"] == 1 && intval($ar_comment["IS_REVIEWED"]) == 1 ) {
			$userId = $ar_comment["FK_USER_OWNER"];

			$sql = "SELECT GROUP_CONCAT(value) as all_values
					FROM `option`
					WHERE `plugin` = 'MARKTPLATZ'
					AND (
						`typ` = 'ALLOW_COMMENTS_VENDOR' 
						OR 
						`typ` = 'ALLOW_COMMENTS_RATED' 
						OR 
						`typ` = 'GLOBAL_RATINGS'
					)";
			$rating_allow_options = $this->db->fetch_atom( $sql );

			$sql = 'SELECT ALLOW_COMMENTS
					FROM `usersettings`
					WHERE `FK_USER` = ' . $userId;
			$allow_comments = intval($this->db->fetch_atom( $sql ));

			if ( $rating_allow_options == "1,1,1" && ( $allow_comments & 8 ) ) {
				$rating_data = $this->fetchVendorCommentsAvgrating($userId);
				$rating_avg = $rating_data["RATING_AVG"];

				$sql = 'SELECT a.CACHE,b.NAME as VENDOR_NAME
					FROM user a
					INNER JOIN vendor b
					ON a.ID_USER = '.$userId.'
					AND b.FK_USER = a.ID_USER';
				$row = $this->db->fetch1( $sql );
				$firmdata = array(
					"NAME" => $row["VENDOR_NAME"],
					"REPORT" => "Ebiz-trader bericte",
					"rating" => $rating_avg,
					"formatted_rating" =>  number_format($rating_avg, 2, ',', ' ') . " von " . "5,0",
					"total_ratings" => $rating_data["COUNT"] . " Bewertungen"
				);
				$store_file = "cache/users/".$row["CACHE"]."/".$userId."/widget_".$GLOBALS['s_lang'].".png";

				$this->generateRatingWidgetPNG(
					$firmdata,
					$store_file,
					true
				);
			}
		}
		else {
			return;
		}
	}

	function generateRatingWidgetPNG($firmdata,$store_file,$force = false) {
		$tpl = new Template("tpl/de/empty.htm");
		$widget_file = $tpl->parseTemplateString("{uri_resource(/images/widget/widget.png)}");
		$widget_file = explode("/",$widget_file);
		unset($widget_file[0]);
		unset($widget_file[1]);
		$imgname = implode("/",$widget_file);

		$font = $tpl->parseTemplateString("{uri_resource(/fonts/widget.ttf)}");
		$font = explode("/",$font);
		unset($font[0]);
		unset($font[1]);
		$font = implode("/",$font);
		$font = $GLOBALS['ab_path'].$font;

		if (!file_exists($imgname) || $force) {
			$image_data = getimagesize($imgname);
			/* Attempt to open */
			$im = @imagecreatefrompng($imgname);

			/* See if it failed */
			if ($im) {
				// Create some colors
				$sql = "SELECT option.value
							FROM `option` 
							WHERE `plugin` = 'SITE' AND `typ` = 'WIDGET_TEXT_COLOR'";
				$hex = $this->db->fetch_atom( $sql );
				//$hex = "#ff0000";
				list($red, $green, $blue) = sscanf($hex, "#%02x%02x%02x");
				$text_color = imagecolorallocate($im, intval($red), intval($green), intval($blue));

				//The text to draw
				$text_size = 18;
				$arr = imagettfbbox($text_size,0,$font,$firmdata["NAME"]);
				while ( $image_data[0] - ($arr[2]-$arr[0]) <= 0 ) {
					$text_size -= 1;
					$arr = imagettfbbox($text_size,0,$font,$firmdata["NAME"]);
				}
				$x_axis = ($image_data[0]-($arr[2]-$arr[0]))/2;
				$y_axis = $image_data[1]*0.40;
				// Add th text
				imagettftext($im, $text_size,0,$x_axis,$y_axis,$text_color,$font,$firmdata["NAME"]);

				//The text to draw
				$text_size = 18;
				$arr = imagettfbbox($text_size,0,$font,$firmdata["REPORT"]);
				while ( $image_data[0] - ($arr[2]-$arr[0]) <= 0 ) {
					$text_size -= 1;
					$arr = imagettfbbox($text_size,0,$font,$firmdata["REPORT"]);
				}
				$x_axis = ($image_data[0]-($arr[2]-$arr[0]))/2;
				// Add th text
				$y_axis+=30;
				imagettftext($im, $text_size,0,$x_axis,$y_axis,$text_color,$font,$firmdata["REPORT"]);

				$y_axis+=15;
				$stars_image_str = $GLOBALS['ab_path'] . "gfx/big_stars_".$firmdata["rating"].".png";
				$arr = getimagesize($stars_image_str);
				$stars_image = @imagecreatefrompng($stars_image_str);
				//$x_axis = ($image_data[0]-$dest_imagex)/2;
				$x_axis = ($image_data[0]-$arr[0])/2;
				imagecopy(
					$im,
					$stars_image,//$dest_image,
					$x_axis,
					$y_axis,
					0,
					0,
					$arr[0],//$dest_imagex,
					$arr[1]//$dest_imagey
				);
				$y_axis += 40;

				//The text to draw
				$arr = imagettfbbox(18,0,$font,$firmdata["formatted_rating"]);
				$x_axis = ($image_data[0]-($arr[2]-$arr[1]))/2;
				// Add th text
				$y_axis+=30;
				imagettftext($im, 18,0,$x_axis,$y_axis,$text_color,$font,$firmdata["formatted_rating"]);

				//The text to draw
				$arr = imagettfbbox(18,0,$font,$firmdata["total_ratings"]);
				$x_axis = ($image_data[0]-($arr[2]-$arr[1]))/2;
				// Add th text
				$y_axis+=30;
				imagettftext($im, 18,0,$x_axis,$y_axis,$text_color,$font,$firmdata["total_ratings"]);

				$a = imagepng($im,$GLOBALS['ab_path'].$store_file);
			}
		}
		return $store_file;
	}

	/**
	 * Fetch all visible comments for the given fk/id.
	 *
	 * @param int	$fk
	 */
	function fetchPublicByFk($fk, $limitOffset = 0, $limitCount = 10, &$all = null) {
		return $this->fetchAllByParams(array('IS_CONFIRMED' => 1, 'IS_PUBLIC' => 1, 'IS_REVIEWED' => 1, 'FK' => $fk), $limitOffset, $limitCount, $all);
	}

	/*
	 * Fetch vendor comments avg rating
	 * */
	function fetchVendorCommentsAvgrating($userId) {
		$sql = 'SELECT FLOOR(AVG(a.RATING)) as RATING_AVG, COUNT(1) as COUNT
					FROM comment a 
					WHERE a.FK_USER_OWNER = '.$userId.'
					AND a.TABLE = "vendor"
					AND a.IS_CONFIRMED = 1
					AND a.IS_PUBLIC = 1
					AND a.IS_REVIEWED = 1';

		return $this->db->fetch1( $sql );
	}

	/**
	 * Fetch all visible comments for the given fk/id.
	 *
	 * @param int	$fk
	 */
	function fetchPublicByFkStr($fkStr, $limitOffset = 0, $limitCount = 10, &$all = null) {
		return $this->fetchAllByParams(array('IS_CONFIRMED' => 1, 'IS_PUBLIC' => 1, 'IS_REVIEWED' => 1, 'FK_STR' => $fkStr), $limitOffset, $limitCount, $all);
	}

	/**
	 * Fetch all finished comments for the given fk/id.
	 * (ALSO FETCHS COMMENTS THAT ARE NOT REVIEWED YET!)
	 *
	 * @param int	$fk
	 */
	function fetchConfirmedByFk($fk, $limitOffset = 0, $limitCount = 10, &$all = null) {
		return $this->fetchAllByParams(array('IS_CONFIRMED' => 1, 'FK' => $fk), $limitOffset, $limitCount, $all);
	}

	/**
	 * Fetch all reviewed comments for the given fk/id.
	 * (ALSO FETCHS COMMENTS THAT ARE NOT PUBLIC YET!)
	 *
	 * @param int	$fk
	 */
	function fetchReviewedByFk($fk, $limitOffset = 0, $limitCount = 10, &$all = null) {
		return $this->fetchAllByParams(array('IS_REVIEWED' => 1, 'FK' => $fk), $limitOffset, $limitCount, $all);
	}

	/**
	 * Get the description of the active table (e.g.: "news", "user")
	 *
	 * @return string
	 */
	function getTableDescription() {
		global $langval;
		$query = "SELECT ts.V1 FROM `message` tm
						LEFT JOIN `string_app` ts ON ts.S_TABLE='message' AND ts.FK=tm.ID_MESSAGE
							AND ts.BF_LANG=if(tm.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(tm.BF_LANG_APP+0.5)/log(2)))
					 WHERE tm.FKT='COMMENT_TABLES' AND tm.ERR='".mysql_real_escape_string($this->table)."'";
		return $this->db->fetch_atom($query);
	}

	/**
	 * Get the link to the target object that has been commented.
	 *
	 * @param int	$fk
	 * @return boolean|string
	 */
	function getTargetLink($fk, $extra = "", $pageIndex = null) {
		if ($this->table == '*') {
			// Can't get target link if not on a specific table
			return false;
		} else {
			$tpl_tmp = new Template("");
			switch ($this->table) {
				case 'ad_master':
					$ar_ad = $this->db->fetch1("SELECT ID_AD_MASTER, PRODUKTNAME FROM `ad_master` WHERE ID_AD_MASTER=".$fk);
					return $tpl_tmp->tpl_uri_action($this->target[$this->table] . ",".$fk.",".addnoparse(chtrans($ar_ad['PRODUKTNAME'])).$extra);
				case 'vendor':
					$ar_vendor = $this->db->fetch1("SELECT ID_VENDOR, NAME, FK_USER FROM `vendor` WHERE ID_VENDOR=".$fk);
					return $tpl_tmp->tpl_uri_action($this->target[$this->table] . ",".addnoparse(chtrans($ar_vendor['NAME'])).",".$ar_vendor["FK_USER"].$extra);
				case 'club':
					$ar_club = $this->db->fetch1("SELECT ID_CLUB, NAME FROM `club` WHERE ID_CLUB=".$fk);
					return $tpl_tmp->tpl_uri_action($this->target[$this->table] . ",".addnoparse(chtrans($ar_club['NAME'])).",".$fk.$extra);
				case 'calendar_event':
					$ar_calendar = $this->db->fetch1("SELECT ID_CALENDAR_EVENT, TITLE FROM `calendar_event` WHERE ID_CALENDAR_EVENT=".$fk);
					return $tpl_tmp->tpl_uri_action($this->target[$this->table] . ",".addnoparse(chtrans($ar_calendar['TITLE'])).",".$fk.$extra);
				case 'news':
					$ar_news = $this->db->fetch1("SELECT n.ID_NEWS, c.V1 FROM `news` n join `string_c` c on c.FK = n.ID_NEWS and c.BF_LANG = n.BF_LANG_C WHERE n.ID_NEWS=".$fk);
					return $tpl_tmp->tpl_uri_action($this->target[$this->table] . ",".$fk.",".addnoparse(chtrans($ar_news['V1'])).$extra);
			}
		}

		return false;
	}

	/**
	 * Get the link to the target object that has been commented.
	 *
	 * @param int	$fk
	 * @return boolean|string
	 */
	function getTargetLinkStr($fk, $extra = "", $pageIndex = null) {
		if ($this->table == '*') {
			// Can't get target link if not on a specific table
			return false;
		} else {
			$tpl_tmp = new Template("");
			switch ($this->table) {
				default:
					break;
			}
		}

		return false;
	}

	/**
	 * Get the owner of the item that is being commented
	 *
	 * @param int	$fk		ID of the item being commented
	 * @return string
	 */
	function getUserOwner($fk) {
		if ($this->table == '*') {
			// Can't get owner if not on a specific table
			return false;
		}
		$query = "SELECT FK_USER FROM `".mysql_real_escape_string($this->table)."`
				WHERE ID_".mysql_real_escape_string(strtoupper($this->table))."=".(int)$fk;
		return $this->db->fetch_atom($query);
	}

	/**
	 * Get the owner of the item that is being commented
	 *
	 * @param int	$fk		ID of the item being commented
	 * @return string
	 */
	function getUserOwnerStr($fk) {
		return 1;
	}

	/**
	 * Check whether the given user has administrative access to the given comment.
	 */
	function isUserAdmin($idComment, $userId) {
		if (!$userId) {
			// Not logged in
			return false;
		}
		// TODO: Replace with role check
		if ($userId == 1) {
			return true;
		}
		$idOwner = $this->db->fetch_atom("SELECT FK_USER_OWNER FROM `comment` WHERE ID_COMMENT=".(int)$idComment);
		return ($idOwner == $userId);
	}

	/**
	 * Check whether the given user has autoconfirm on.
	 */
	function hasUserAutoconfirmEnabled($userId) {
		if (!$userId) {
			// Not user id given
			return true;
		}
		$confirmManual = $this->db->fetch_atom("SELECT SET_COMMENT_MANUAL FROM `usersettings` WHERE FK_USER=".(int)$userId);
		return ($confirmManual ? false : true);
	}

	/**
	 * Set whether the comment is confirmed or not. Unconfirmed comments are not shown, comments by registered
	 * users are automatically confirmed.
	 *
	 * @param int		$idComment
	 * @param string	$confirmed
	 * @param string	$userId
	 */
	function setCommentConfirmed($idComment, $confirmed = true, $userId = null) {
		if ($userId === null) {
			global $uid;
			$userId = $uid;
		}
		if ($this->isUserAdmin($idComment, $userId)) {
			$this->db->querynow("UPDATE `comment` SET IS_CONFIRMED=".($confirmed ? '1' : '0')."
					WHERE ID_COMMENT=".(int)$idComment);
			$this->updateCommentStats($idComment);
			return true;
		}
		return false;
	}

	/**
	 * Set whether the comment is visible or not.
	 *
	 * @param int		$idComment
	 * @param string	$visible
	 * @param string	$userId
	 */
	function setCommentVisible($idComment, $visible = true, $userId = null) {
		if ($userId === null) {
			global $uid;
			$userId = $uid;
		}
		if ($this->isUserAdmin($idComment, $userId)) {
			$this->db->querynow("UPDATE `comment` SET IS_PUBLIC=".($visible ? '1' : '0')."
					WHERE ID_COMMENT=".(int)$idComment);
			$this->updateCommentStats($idComment);
			return true;
		}
		return false;
	}

	function updateComment($idComment, $ar_comment) {
		$ar_comment['ID_COMMENT'] = $idComment;
		if ($ar_comment['FK'] > 0) {
			$userIdOwner = $this->getUserOwner($ar_comment['FK']);
			$autoconfirm = ($GLOBALS["nar_systemsettings"]["SITE"]["COMMENT_CONFIRM"] ? false : $this->hasUserAutoconfirmEnabled($userIdOwner));
		} else {
			$userIdOwner = $this->getUserOwnerStr($ar_comment['FK_STR']);
			$autoconfirm = ($GLOBALS["nar_systemsettings"]["SITE"]["COMMENT_CONFIRM"] ? false : $this->hasUserAutoconfirmEnabled($userIdOwner));
		}
		$ar_comment["IS_REVIEWED"] = ($autoconfirm ? 1 : 0);
		$result = $this->db->update('comment', $ar_comment);
		$this->updateCommentStats($idComment);
		return $result;
	}

	function updateCommentStats($idComment) {
		$ar_comment = $this->db->fetch1("SELECT `TABLE`, FK, FK_STR FROM `comment` WHERE ID_COMMENT=".(int)$idComment);
		if (is_array($ar_comment)) {
			if ($ar_comment["FK"] > 0) {
				CommentManagement::getInstance($this->db, $ar_comment["TABLE"])
					->updateCommentStatsByFk($ar_comment["FK"]);
                $this->clearCommentCache($ar_comment["FK"], $ar_comment["TABLE"]);
			} else {
				CommentManagement::getInstance($this->db, $ar_comment["TABLE"])
					->updateCommentStatsByFkStr($ar_comment["FK_STR"]);
                $this->clearCommentCacheStr($ar_comment["FK_STR"], $ar_comment["TABLE"]);
			}
			return true;
		}
		return false;
	}

  function updateCommentStatsByFk($fk)
  {
    if ($this->table == '*') {
      // Can't update stats for FK without specific table
      return false;
    }
    $arStats = $this->db->fetch1("
			SELECT COUNT(*) AS COMMENT_COUNT, AVG(RATING) AS RATING_AVG, COUNT(RATING) AS RATING_COUNT 
			FROM `comment`
			WHERE `TABLE`='" . mysql_real_escape_string($this->table) . "' AND FK='" . mysql_real_escape_string($fk) . "'
				AND IS_PUBLIC=1 AND IS_CONFIRMED=1 AND IS_REVIEWED=1");
    $jsonRatings = json_encode($this->db->fetch_nar("
			SELECT RATING, COUNT(RATING) AS RATING_COUNT 
			FROM `comment`
			WHERE `TABLE`='" . mysql_real_escape_string($this->table) . "' AND FK='" . mysql_real_escape_string($fk) . "'
				AND IS_PUBLIC=1 AND IS_CONFIRMED=1 AND IS_REVIEWED=1 AND RATING IS NOT NULL
			GROUP BY RATING
			ORDER BY RATING ASC"));
    $query = "INSERT INTO `comment_stats` (`TABLE`, FK, FK_STR, AMOUNT, RATING_AVG, RATING_COUNT, RATING_JSON)
			VALUES ('" . mysql_real_escape_string($this->table) . "', " . (int)$fk . ", NULL, " .
      (int)$arStats['COMMENT_COUNT'] . ", " . (int)$arStats['RATING_AVG'] . ", " . (int)$arStats['RATING_COUNT'] . ", '" . mysql_real_escape_string($jsonRatings) . "')
			ON DUPLICATE KEY
			UPDATE AMOUNT=" . (int)$arStats['COMMENT_COUNT'] . ", RATING_AVG=" . (int)$arStats['RATING_AVG'] . ", RATING_COUNT=" . (int)$arStats['RATING_COUNT'] . ", RATING_JSON='" . mysql_real_escape_string($jsonRatings) . "'";
    $this->db->querynow($query);
    return true;
  }

  function updateCommentStatsByFkStr($fkStr)
  {
    if ($this->table == '*') {
      // Can't update stats for FK without specific table
      return false;
    }
    $arStats = $this->db->fetch1("
			SELECT COUNT(*) AS COMMENT_COUNT, AVG(RATING) AS RATING_AVG, COUNT(RATING) AS RATING_COUNT 
			FROM `comment`
			WHERE `TABLE`='" . mysql_real_escape_string($this->table) . "' AND FK_STR='" . mysql_real_escape_string($fkStr) . "'
				AND IS_PUBLIC=1 AND IS_CONFIRMED=1 AND IS_REVIEWED=1");
    $jsonRatings = json_encode($this->db->fetch_nar("
			SELECT RATING, COUNT(RATING) AS RATING_COUNT FROM `comment`
			WHERE `TABLE`='" . mysql_real_escape_string($this->table) . "' AND FK_STR='" . mysql_real_escape_string($fkStr) . "'
				AND IS_PUBLIC=1 AND IS_CONFIRMED=1 AND IS_REVIEWED=1 AND RATING IS NOT NULL
			GROUP BY RATING
			ORDER BY RATING ASC"));
    $query = "INSERT INTO `comment_stats` (`TABLE`, FK, FK_STR, AMOUNT, RATING_AVG, RATING_COUNT, RATING_JSON)
			VALUES ('" . mysql_real_escape_string($this->table) . "', NULL, '" . mysql_real_escape_string($fkStr) . "', " .
      (int)$arStats['COMMENT_COUNT'] . ", " . (int)$arStats['RATING_AVG'] . ", " . (int)$arStats['RATING_COUNT'] . ", '" . mysql_real_escape_string($jsonRatings) . "')
			ON DUPLICATE KEY
			UPDATE AMOUNT=" . (int)$arStats['COMMENT_COUNT'] . ", RATING_AVG=" . (int)$arStats['RATING_AVG'] . ", RATING_COUNT=" . (int)$arStats['RATING_COUNT'] . ", RATING_JSON='" . mysql_real_escape_string($jsonRatings) . "'";
    $this->db->querynow($query);
    return true;
  }

	/**
	 * Reply to comment and send a notification mail.
	 *
	 * @param  int 		$id      	ID of the Comment
	 * @param  string 	$comment 	the comment itself
	 * @return bool          		true on success, otherwise false
	 */
	public function replyComment($id, $comment)
	{
		if ($id > 0) {
			$res = $this->db->querynow("UPDATE comment
									SET ANSWER_COMMENT = '" . mysql_real_escape_string($comment) . "', ANSWER_STAMP = NOW()
									WHERE ID_COMMENT = " . $id);

			if($res['rsrc'] === true) {
				// get article name, id, user and email
				$ar_comment = $this->fetchOneByParams(array('ID_COMMENT' => $id));
				$ar_comment['title'] = $this->getTargetTitle($this->db, $ar_comment['TABLE'], $ar_comment['FK']);
				$ar_comment['target'] = $this->target[$ar_comment['TABLE']];
				$ar_comment['PAGE_COUNTER'] = $this->getPageForComment($id, $ar_comment['FK'], 5);

				sendMailTemplateToUser(0, $ar_comment['USER_MAIL'], 'comment_reply', $ar_comment);

				return true;
			}

			return false;
		} else {
			return false;
		}
	}

	/**
	 * The position of a comment as page counter
	 *
	 * @param  int  $idComment  ID of comment
	 * @param  int  $fk
	 * @param  int $limitCount limit per page
	 * @return int              page counter
	 */
	public function getPageForComment($idComment, $fk, $limitCount = 10)
	{
		$query = "select
					ceiling(count(*) / " . $limitCount . ")
					from comment a
					left join comment b on b.ID_COMMENT = " . $idComment . "
					where a.FK = " . $fk . " and a.STAMP >= b.STAMP  and a.TABLE = b.TABLE
					ORDER BY a.STAMP DESC";

		return $this->db->fetch_atom($query);
	}

	/**
	 * The position of a comment as page counter
	 *
	 * @param  int  $idComment  ID of comment
	 * @param  int  $fk
	 * @param  int $limitCount limit per page
	 * @return int              page counter
	 */
	public function getPageForCommentStr($idComment, $fk_str, $limitCount = 10)
	{
		$query = "select
					ceiling(count(*) / " . $limitCount . ")
					from comment a
					left join comment b on b.ID_COMMENT = " . $idComment . "
					where a.FK_STR = '" . mysql_real_escape_string($fk_str) . "' and a.STAMP >= b.STAMP  and a.TABLE = b.TABLE
					ORDER BY a.STAMP DESC";

		return $this->db->fetch_atom($query);
	}

	/**
	 * Return count for current table and id
	 * @param  int $fk ID of the table
	 * @return int     comment count
	 */
	public function getCommentCount($fk)
	{
		return $this->db->fetch_atom("
			select AMOUNT
				from comment_stats
				where `TABLE` = '" . $this->table . "' and FK = " . $fk . "
		");
	}

	/**
	 * Return count for current table and id
	 * @param  int $fk ID of the table
	 * @return int     comment count
	 */
	public function getCommentStats($fk)
	{
		$arStats = $this->db->fetch1("
			SELECT * FROM comment_stats
			WHERE `TABLE` = '" . $this->table . "' AND FK = " . $fk . " AND FK_STR IS NULL
		");
		if (is_array($arStats) && ($arStats["RATING_JSON"] === null)) {
			$this->updateCommentStatsByFk($fk);
			return $this->getCommentStats($fk);
		}
		if (!is_array($arStats)) {
			$arStats = array();
		}
		$arStatsJson = json_decode($arStats["RATING_JSON"], true);
		if (!is_array($arStatsJson)) {
			$arStatsJson = array();
		}
		$arStats = array_merge( $arStats, array_flatten($arStatsJson, true, "_", "RATING_COUNT_") );
		return $arStats;
	}

	/**
	 * Return count for current table and id
	 * @param  int $fk ID of the table
	 * @return int     comment count
	 */
	public function getCommentStatsStr($fkStr)
	{
        $arStats = $this->db->fetch1("
			SELECT * FROM comment_stats
			WHERE `TABLE` = '" . $this->table . "' AND FK IS NULL AND FK_STR = '" . mysql_real_escape_string($fkStr) . "'
		");
		if ($arStats["RATING_JSON"] === null) {
			$this->updateCommentStatsByFkStr($fkStr);
			return $this->getCommentStatsStr($fkStr);
		}
		$arStatsJson = json_decode($arStats["RATING_JSON"], true);
		if (!is_array($arStatsJson)) {
			$arStatsJson = array();
		}
		$arStats = array_merge( $arStats, array_flatten($arStatsJson, true, "_", "RATING_COUNT_") );
		return $arStats;
	}

	/**
	 * Set table for comments
	 * @param string $value table name
	 */
	public function setTable($value)
	{
		$this->table = strtolower($value);
	}

	/**
	 * Unlock comment
	 * @param  int $id
	 * @return boolean     On success true, otherwise false
	 */
	public function unlockComment($id)
	{
		$res = $this->db->querynow('update comment set IS_REVIEWED = 1 where ID_COMMENT = ' . (int)$id);
		$this->updateCommentStats($id);
		// die(print_r($res));
		return $res['rsrc'];
	}

	/**
	 * lock comment
	 * @param  int $id
	 * @return boolean     On success true, otherwise false
	 */
	public function lockComment($id)
	{
		$res = $this->db->querynow('update comment set IS_REVIEWED = 0 where ID_COMMENT = ' . (int)$id);
		$this->updateCommentStats($id);
		// die(print_r($res));
		return $res['rsrc'];
	}
}

?>
