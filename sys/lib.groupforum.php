<?php
/* ###VERSIONSBLOCKINLCUDE### */

include_once "lib.club.php";

function ProcessBody_ReplaceLink($link) {
    global $s_lang;
    if (preg_match('/(https?\:\/\/[^\s]+\/[^\s^\/]+\.(jpg|jpeg|png|gif))/im', $link[2])) {
        $tpl_image = new Template("tpl/".$s_lang."/club_forum.image.htm");
        $tpl_image->addvar("URL", $link[2]);
        return $tpl_image->process(true);
    } else {
        $tpl_link = new Template("tpl/".$s_lang."/club_forum.link.htm");
        $tpl_link->addvar("URL", $link[2]);
        return $tpl_link->process(true);
    }
}

/**
 * Class GroupForum
 */
class GroupForum
{
    const ACCESS_PUBLIC = 1;
    const ACCESS_PRIVATE = 0;
    const ANNOUNCE_ENABLED = 1;
    const ANNOUNCE_DISABLED = 0;
    const STICKY_ENABLED = 1;
    const STICKY_DISABLED = 0;
    const DISCUSSION_CLOSE = 1;
    const DISCUSSION_OPEN = 0;
    const MODERATION_REVIEWED = 1;
    const MODERATION_WAITING = 0;

    /**
     * @global ebiz_db
     */
    protected $db = null;
    /**
     * @var ClubManagement|null
     */
    protected $group = null;
    /**
     * @var int
     */
    protected $group_id = 0;
    /**
     * 0 = private
     * 1 = public
     *
     * @var int
     */
    protected $forum_public = 0;
    /**
     * 0 = Admin needs to review
     * 1 = don't need action from admin
     *
     * @var int
     */
    protected $forum_moderated = 0;

    /**
     * @var int
     */
    protected $limit_per_page = 10;

    private static $sortFields = array(
        "ANNOUNCE"      => "cd.ANNOUNCE",
        "STICKY"        => "cd.STICKY",
        "STAMP_CREATE"  => "cd.STAMP_CREATE",
        "STAMP_UPDATE"  => "cd.STAMP_UPDATE",
        "STAMP_REPLY"   => "cd.STAMP_REPLY",
        "NAME"          => "cd.NAME",
        "COMMENTS"      => "cd.COMMENTS"
    );

    private static $sortDirs = array("ASC", "DESC");

    /**
     * Replace links and images within the body
     * @param $body
     * @return mixed
     */
    public static function ProcessBody($body) {
        //$pregImages = '/(https?\:\/\/[^\s]+\/[^\s^\/]+\.(jpg|jpeg|png|gif))/im';
        $pregLinks = '/(^|\s)(https?\:\/\/[^\s]+)(\s|$)/im';
        $body = str_replace("\n", "<br />\n", $body);
        $body = preg_replace_callback($pregLinks, ProcessBody_ReplaceLink, $body);
        return $body;
    }

    /**
     * @param int $group
     */
    public function __construct($group)
    {
        global $db;

        $this->db = $db;
        $this->group = ClubManagement::getInstance($this->db);
        $this->group_id = (int)$group;
        $this->setGroupForumOptions();
    }

    /**
     * Get default options for the discussion
     */
    private function setGroupForumOptions()
    {
        $options = $this->db->fetch1("
            SELECT FORUM_PUBLIC, FORUM_MODERATED
            FROM club
            WHERE ID_CLUB = $this->group_id"
        );

        $this->forum_public = $options["FORUM_PUBLIC"];
        $this->forum_moderated = $options["FORUM_MODERATED"];
    }

    /**
     * Set current group
     *
     * @param $group
     */
    public function setGroup($group)
    {
        $this->group_id = (int)$group;
    }

    /**
     * Get thread by id
     *
     * @param int $thread
     *
     * @return array
     */
    public function getThread($thread)
    {
        $thread = (int)$thread;

        return $this->db->fetch1(
            "SELECT
                cd.*,
                u.NAME as USER_NAME, u.CACHE as USER_CACHE
            FROM club_discussion cd
            JOIN user u ON u.ID_USER=cd.FK_USER
            WHERE cd.ID_CLUB_DISCUSSION = ".$thread);
    }

    /**
     * Get comment by id
     *
     * @param int $comment
     *
     * @return array
     */
    public function getThreadComment($comment)
    {
        $comment = (int)$comment;

        return $this->db->fetch1(
            "SELECT SQL_CALC_FOUND_ROWS
                cdc.*,
                u.NAME as USER_NAME, u.CACHE as USER_CACHE
            FROM club_discussion_comment cdc
            JOIN user u ON u.ID_USER=cdc.FK_USER
            WHERE cdc.ID_CLUB_DISCUSSION_COMMENT = ".$comment);
    }

    /**
     * Get the page number the given comment is found on
     * @param int $thread
     * @param int $comment      Id of the comment (null for the last one)
     * @param int $perpage      Number of comments shown per page
     *
     * @return int
     */
    public function getThreadCommentPage($thread, $comment = null, $perpage = 10)
    {
        $thread = (int)$thread;
        $index = $this->db->fetch_atom($q="
            SELECT count(*) FROM `club_discussion_comment`
            WHERE FK_CLUB_DISCUSSION=".$thread.($comment === null ? "" : " AND ID_CLUB_DISCUSSION_COMMENT<=".(int)$comment));
        $page = floor(($index + 1) / $perpage) + 1;
        return $page;
    }

    /**
     * Get the number of threads from current group
     *
     * @return string
     */
    public function getThreadsCount()
    {
        return $this->db->fetch_atom(
            "SELECT count(*)
            FROM club_discussion
            WHERE FK_CLUB = $this->group_id"
        );
    }

    /**
     * Get the SQL-Where parts for the given search parameters
     *
     * TODO: Nach Datum suchen
     *
     * @param array $search
     *
     * @return array
     */
    private function getSearchWhere($search)
    {
        $arWhere = array();
        $arJoin = array();
        if (empty($search)) {
            // No search parameters, no where conditions
            return array($arWhere, $arJoin);
        }
        foreach ($search as $field => $value) {
            switch ($field) {
                case 'FULLTEXT':
                    // Fulltext search
                    $arWhere[] = "( MATCH (cd.NAME, cd.BODY) AGAINST ('".mysql_real_escape_string($value)."')".
                        " OR MATCH (cdc.BODY) AGAINST ('".mysql_real_escape_string($value)."') )";
                    $arJoin[] = "LEFT JOIN `club_discussion_comment` cdc ON cdc.FK_CLUB_DISCUSSION=cd.ID_CLUB_DISCUSSION";
                    break;
                default:
                    // Other search parameters
                    if (is_numeric($value)) {
                        // typecast for numeric value
                        $value = (int)$value;
                        $arWhere[] = "$field = $value";
                    } else {
                        // escape for strings and search with sql LIKE
                        $value = mysql_real_escape_string($value);
                        $arWhere[] = "$field LIKE '%$value%'";
                    }
                    break;
            }
        }
        return array($arWhere, $arJoin);
    }

    /**
     * Search all threads with the given search term
     *
     * TODO: Nach Datum suchen
     *
     * @param array $search
     *
     * @param array $sort
     * @param int $limit
     *
     * @return array
     */
    public function searchThreads($search, $sort = null, $limit = null, $offset = null, &$all = 0)
    {
        return $this->getThreads($sort, $limit, $offset, $all, $search);
    }

    /**
     * Get all threads.
     * You can sort and limit the output.
     *
     * @param array|null $sort
     * @param int|null $limit
     *
     * @return array
     */
    public function getThreads($sort = null, $limit = null, $offset = null, &$all = 0, $search = array())
    {
        if ($sort === null) {
            $sort = array("ANNOUNCE" => "DESC", "STAMP_REPLY" => "DESC", "STAMP_CREATE" => "DESC");
        }

        $query_sort = array();

        foreach ($sort as $field => $by) {
            if (array_key_exists($field, self::$sortFields) && in_array($by, self::$sortDirs)) {
                $query_sort[] = self::$sortFields[$field] . " " . mysql_real_escape_string($by);
            }
        }
        $query_sort = implode(", ", $query_sort);

        if ($limit === null) {
            $limit = $this->limit_per_page;
        }
        $limit = (int)$limit;

        if ($offset === null) {
            $offset = 0;
        }
        $offset = (int)$offset;

        list($arWhere, $arJoin) = $this->getSearchWhere($search);

        $ar_threads = $this->db->fetch_table(
            $q="SELECT SQL_CALC_FOUND_ROWS
                cd.*,
                u.NAME as USER_NAME, u.CACHE as USER_CACHE,
                ur.NAME as USER_REPLY_NAME
            FROM club_discussion cd
            JOIN user u ON u.ID_USER=cd.FK_USER
            LEFT JOIN user ur ON ur.ID_USER=cd.FK_USER_REPLY
            ".implode("\n           ", $arJoin)."
            WHERE cd.FK_CLUB = ".$this->group_id.(!empty($arWhere) ? " AND ".implode(" AND ", $arWhere) : "")."
            GROUP BY cd.ID_CLUB_DISCUSSION
            ORDER BY ".$query_sort."
            LIMIT ".$offset.", ".$limit);
        $all = $this->getFoundRows();
        return $ar_threads;
    }

    /**
     * Get result from FOUND_ROWS
     *
     * @return int
     */
    public function getFoundRows()
    {
        return $this->db->fetch_atom(
            "select FOUND_ROWS()"
        );
    }

    /**
     * Get all comments to a specific thread.
     * You can set a limit .
     *
     * @param int $thread
     * @param int|null $limit
     *
     * @return array
     */
    public function getThreadComments($thread, $limit = null, $offset = null, &$all = 0, $reverseOrder = true)
    {
        $thread = (int)$thread;

        if ($limit === null) {
            $limit = $this->limit_per_page;
        }
        $limit = (int)$limit;

        if ($offset === null) {
            $offset = 0;
        }
        $offset = (int)$offset;

        $ar_comments = $this->db->fetch_table(
            "SELECT SQL_CALC_FOUND_ROWS
                cdc.*,
                u.NAME as USER_NAME, u.CACHE as USER_CACHE
            FROM club_discussion_comment cdc
            JOIN user u ON u.ID_USER=cdc.FK_USER
            WHERE cdc.FK_CLUB_DISCUSSION = ".$thread."
            ORDER BY cdc.STAMP_CREATE ".($reverseOrder ? "DESC" : "ASC")."
            LIMIT ".$offset.", ".$limit);
        $all = $this->getFoundRows();
        return $ar_comments;
    }

    /**
     * Get the number of comments to a specific thread
     *
     * @param int $thread
     * @return string
     */
    public function getThreadCommentsCount($thread)
    {
        $thread = (int)$thread;

        return $this->db->fetch_atom(
            "SELECT count(*)
            FROM club_discussion_comment
            WHERE FK_CLUB_DISCUSSION = $thread"
        );
    }

    /**
     * Creates a new thread.
     * As standard the thread is marked as private.
     * When
     *
     * @param string $name
     * @param string $body
     * @param int $access
     *
     * @return int
     */
    public function newThread($name, $body, $access = self::ACCESS_PRIVATE)
    {
        global $uid;

        // set public only, when the group option is set to public too
        if ($access && $this->forum_public) {
            $access = self::ACCESS_PUBLIC;
        } else { // otherwise always private
            $access = self::ACCESS_PRIVATE;
        }

        $name = mysql_real_escape_string($name);
        $body = mysql_real_escape_string($body);

        $result = $this->db->querynow(
            "INSERT INTO club_discussion
                (FK_USER, FK_CLUB, NAME, BODY, PUBLIC, STAMP_CREATE, STAMP_REPLY, FK_USER_REPLY)
            VALUES
                ($uid, $this->group_id, '$name', '$body', $access, NOW(), NOW(), $uid)"
        );

        return $result['int_result'];
    }

    /**
     * Creates a new comment to a specific thread and returns the id.
     *
     * @param int $thread
     * @param string $body
     *
     * @return int
     */
    public function newComment($thread, $body)
    {
        global $uid;

        $body = mysql_real_escape_string($body);
        $thread = (int)$thread;

        $result = $this->db->querynow(
            "INSERT INTO club_discussion_comment
                (FK_CLUB_DISCUSSION, FK_USER, BODY, STAMP_CREATE)
            VALUES
                ($thread, $uid, '$body', NOW())"
        );

        $id = $result['int_result'];

        $this->updateCommentsCounter($thread, $uid);
        $this->notifyUsers($thread, $id);

        return $id;
    }

    /**
     * Update counter for comments
     *
     * @param int $thread
     * @param int $userId     Id of the latest comment's user
     * @return mixed
     */
    public function updateCommentsCounter($thread, $userId)
    {
        $thread = (int)$thread;

        return $this->db->querynow(
            "UPDATE club_discussion
            SET COMMENTS = COMMENTS + 1, FK_USER_REPLY=".$userId.", STAMP_REPLY=NOW()
            WHERE ID_CLUB_DISCUSSION = $thread"
        );
    }

    /**
     * Delete all threads and comments from the current group.
     * To switch group, see {@link setGroup()}
     * Only Moderator/Admin
     */
    public function deleteThreads()
    {
        global $uid;

        $is_moderator = $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        $threads = $this->db->fetch_table(
            "SELECT ID_CLUB_DISCUSSION
            FROM club_discussion
            WHERE FK_CLUB = $this->group_id"
        );

        foreach ($threads as $key => $data) {
            $threads[$key] = $data["ID_CLUB_DISCUSSION"];
        }

        $threads = implode(", ", $threads);

        $this->db->querynow(
            "DELETE FROM club_discussion_comment
            WHERE FK_CLUB_DISCUSSION IN ($threads)"
        );

        $this->db->querynow(
            "DELETE FROM club_discussion
            WHERE ID_CLUB_DISCUSSION IN ($threads)"
        );
    }

    /**
     * Delete thread by id
     * Only Moderator/Admin/Owner
     *
     * @param int $thread
     *
     * @return bool
     */
    public function deleteThread($thread)
    {
        global $uid;

        $is_moderator = $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        $thread = (int)$thread;

        $this->db->querynow(
            "DELETE FROM club_discussion
            WHERE ID_CLUB_DISCUSSION = $thread"
        );

        $this->db->querynow(
            "DELETE FROM club_discussion_comment
            WHERE FK_CLUB_DISCUSSION = $thread"
        );
    }

    /**
     * Delete comment by id
     * Only Moderator/Admin/Owner
     *
     * @param int $comment
     *
     * @return array|bool
     */
    public function deleteComment($comment)
    {
        global $uid;

        $is_moderator = $this->isCommentOwner(comment)
            || $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        $comment = (int)$comment;

        return $this->db->querynow(
            "DELETE FROM club_discussion_comment
            WHERE ID_CLUB_DISCUSSION_COMMENT = $comment"
        );
    }

    /**
     * Check if the user owns the comment
     *
     * @param int $comment
     *
     * @return bool
     */
    public function isCommentOwner($comment)
    {
        global $uid;

        $comment = (int)$comment;

        return $this->db->fetch_atom(
            "SELECT count(*)
            FROM club_discussion_comment
            WHERE ID_CLUB_DISCUSSION_COMMENT = $comment
                AND FK_USER = $uid"
        );
    }

    /**
     * Set the thread as public or private.
     * The thread can be set only as public, if the groups adjustment for it is on publicly set also.
     *
     * @param int $thread
     * @param bool $public
     *
     * @return mixed
     */
    public function setPublic($thread, $public = true)
    {
        return $this->updateThread(
            $thread,
            array(
                "PUBLIC" => (($public && $this->forum_public) ? 1 : 0)
            )
        );
    }

    /**
     * Update content of the thread
     * Only Moderator/Admin/Owner
     *
     * @param int $thread
     * @param array $data
     *
     * @return mixed
     */
    public function updateThread($thread, $data, $updateStamp = true)
    {
        global $uid;

        $is_moderator = $this->isThreadOwner($thread)
            || $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        $thread = (int)$thread;

        $set = array();
        if ($updateStamp) {
            $set[] = "STAMP_UPDATE = NOW()";
        }

        foreach ($data as $field => $value) {
            $value = is_numeric($value) ? (int)$value : "'".mysql_real_escape_string($value)."'";
            $set[] = "$field = $value";
        }

        $set = implode(", ", $set);

        $query = "update club_discussion "
            . "set $set "
            . "where ID_CLUB_DISCUSSION = $thread";

        return $this->db->querynow($query);
    }

    /**
     * Update content of the comment
     * Only Moderator/Admin/Owner
     *
     * @param int $comment
     * @param array $data
     *
     * @return mixed
     */
    public function updateComment($comment, $data, $updateStamp = true)
    {
        global $uid;

        $is_moderator = $this->isCommentOwner($comment)
            || $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        $comment = (int)$comment;

        $set = array();
        if ($updateStamp) {
            $set[] = "STAMP_UPDATE = NOW()";
        }

        foreach ($data as $field => $value) {
            $value = is_numeric($value) ? (int)$value : "'".mysql_real_escape_string($value)."'";
            $set[] = "$field = $value";
        }

        $set = implode(", ", $set);

        $query = "update club_discussion_comment "
            . "set $set "
            . "where ID_CLUB_DISCUSSION_COMMENT = $comment";

        return $this->db->querynow($query);
    }

    /**
     * Check if user is the thread owner.
     *
     * @param int $thread
     *
     * @return bool
     */
    public function isThreadOwner($thread)
    {
        global $uid;

        $thread = (int)$thread;

        return $this->db->fetch_atom(
            "SELECT count(*)
            FROM club_discussion
            WHERE ID_CLUB_DISCUSSION = $thread
                AND FK_USER = $uid"
        );
    }

    /**
     * set status of thread as sticky
     * Only Moderator/Admin
     *
     * @param int $thread
     * @param int $sticky
     *
     * @return mixed
     */
    public function setSticky($thread, $sticky = self::STICKY_ENABLED)
    {
        global $uid;

        $is_moderator = $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        return $this->updateThread(
            $thread,
            array(
                "STICKY" => ($sticky ? self::STICKY_ENABLED : self::STICKY_DISABLED)
            )
        );
    }

    /**
     * Set status of thread as announce
     * Only Moderator/Admin
     *
     * @param int $thread
     * @param int $announce
     *
     * @return mixed
     */
    public function setAnnounce($thread, $announce = self::ANNOUNCE_ENABLED)
    {
        global $uid;

        $is_moderator = $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        return $this->updateThread(
            $thread,
            array(
                "ANNOUNCE" => ($announce ? self::ANNOUNCE_ENABLED : self::ANNOUNCE_DISABLED)
            )
        );
    }

    /**
     * set status of thread as close
     * Only Moderator/Admin/Owner
     *
     * @param int $thread
     * @param int $closed
     *
     * @return mixed
     */
    public function setClosed($thread, $closed = self::DISCUSSION_CLOSE)
    {
        global $uid;

        $is_moderator = $this->isThreadOwner($thread)
            || $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        return $this->updateThread(
            $thread,
            array(
                "CLOSED" => ($closed ? self::DISCUSSION_CLOSE : self::DISCUSSION_OPEN)
            )
        );
    }

    /**
     * set status of thread as close
     * Only Moderator/Admin/Owner
     *
     * @param int $thread
     * @param int $closed
     *
     * @return mixed
     */
    public function setReviewed($thread, $closed = self::MODERATION_REVIEWED)
    {
        global $uid;

        $is_moderator = $this->isThreadOwner($thread)
            || $this->group->isClubModerator($this->group_id, $uid)
            || $this->group->isClubOwner($this->group_id, $uid);

        if (!$is_moderator) {
            return false;
        }

        return $this->updateThread(
            $thread,
            array(
                "REVIEWED" => ($closed ? self::MODERATION_REVIEWED : self::MODERATION_WAITING)
            )
        );
    }

    /**
     * Informs the users discussing in the given thread about a new reply
     * @param $thread
     * @param $comment
     */
    public function notifyUsers($thread, $comment) {
        global $tpl_main;
        $arThread = $this->getThread($thread);
        $arUsers = $this->db->fetch_table("
            SELECT u.*
            FROM `club_discussion_comment` cdc
            JOIN `user` u ON u.ID_USER=cdc.FK_USER AND u.ABO_FORUM=1
            WHERE cdc.FK_CLUB_DISCUSSION=".$thread."
            GROUP BY cdc.FK_USER");
        foreach ($arUsers as $arUser) {
            $mail_content = array(
                "USERNAME"      => $arUser["NAME"],
                "THREAD_ID"     => $arThread["ID_CLUB_DISCUSSION"],
                "THREAD_NAME"   => $arThread["NAME"],
                "THREAD_PAGE"   => $this->getThreadCommentPage($thread, $comment)
            );
            sendMailTemplateToUser(0, $arUser["EMAIL"], 'GROUP_FORUM_REPLY', $mail_content);
        }
    }

    /**
     * Update counter for views
     *
     * @param int $thread
     * @return mixed
     */
    public function updateViewsCounter($thread)
    {
        $thread = (int)$thread;

        return $this->db->querynow(
            "UPDATE club_discussion
            SET VIEWS = VIEWS + 1
            WHERE ID_CLUB_DISCUSSION = $thread"
        );
    }
}
