<?php
/* ###VERSIONSBLOCKINLCUDE### */

class CalendarEventGalleryManagement {
    private static $db;
    private static $instance = null;

    /**
     * Singleton
     *
     * @param ebiz_db $db
     * @return CalendarEventGalleryManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::setDb($db);

        return self::$instance;
    }

    /**
     * Fügt ein neues Event Bild ein
     *
     * @param $calendarEventGalleryFilename
     * @param $calendarEventId
     * @return void
     */
    public function insertFile($calendarEventGalleryName, $calendarEventGalleryFilename, $calendarEventId) {
        global $nar_systemsettings;
        $db = $this->getDb();

        $countGallery = $this->countCalendarEventGalleryByCalendarEventId($calendarEventId);

        if ($countGallery >= $nar_systemsettings['USER']['EVENT_GALLERY_MAX_IMAGES']) {
            return false;
        } else {
            return $db->update("calendar_event_gallery", array('FK_CALENDAR_EVENT' => $calendarEventId, 'FILENAME' => $calendarEventGalleryFilename, 'NAME' => $calendarEventGalleryName));
        }
    }

    /**
     * Fügt ein neues Veranstaltungs Video ein
     *
     * @param $youtubeId
     * @param $calendarEventId
     * @return void
     */
    public function insertVideo($calendarEventGalleryName, $youtubeId, $calendarEventId) {
        global $nar_systemsettings;
        $db = $this->getDb();

        $countGallery = $this->countCalendarEventGalleryByCalendarEventId($calendarEventId);

        if ($countGallery >= $nar_systemsettings['USER']['EVENT_GALLERY_MAX_IMAGES']) {
            return false;
        } else {
            return $db->update("calendar_event_gallery_video", array('FK_CALENDAR_EVENT' => $calendarEventId, 'YOUTUBEID' => $youtubeId, 'NAME' => $calendarEventGalleryName));
        }
    }

    /**
     * Löscht ein Veranstaltungs Bild
     *
     * @param int $calendarEventGalleryId
     * @param int $calendarEventId
     * @return void
     */
    public function deleteById($calendarEventGalleryId, $calendarEventId) {
        $db = $this->getDb();

        if ($this->existCalendarEventGalleryByCalendarEventId($calendarEventGalleryId, $calendarEventId)) {
            $db->querynow("
                DELETE
                    g
                FROM
                    calendar_event_gallery g, calendar_event ce
                WHERE
                    ce.ID_CALENDAR_EVENT = g.FK_CALENDAR_EVENT
                    AND ce.ID_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
                    AND g.ID_CALENDAR_EVENT_GALLERY = '" . mysql_real_escape_string($calendarEventGalleryId) . "'
            ");

            return true;
        }
    }

    /**
     * Löscht ein Veranstaltungs Video
     *
     * @param int $calendarEventGalleryVideoId
     * @param int $calendarEventId
     * @return void
     */
    public function deleteVideoById($calendarEventGalleryVideoId, $calendarEventId) {
        $db = $this->getDb();

        if ($this->existCalendarEventGalleryVideoByCalendarEventId($calendarEventGalleryVideoId, $calendarEventId)) {
            $db->querynow("
                DELETE
                    g
                FROM
                    calendar_event_gallery_video g, calendar_event ce
                WHERE
					ce.ID_CALENDAR_EVENT = g.FK_CALENDAR_EVENT
                    AND ce.ID_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
                    AND g.ID_CALENDAR_EVENT_GALLERY_VIDEO = '" . mysql_real_escape_string($calendarEventGalleryVideoId) . "'
            ");


            return true;
        }
    }

    /**
     * Holt ein Veranstaltungs Bild anhand ID und Event ID
     *
     * @param $calendarEventGalleryId
     * @param $calendarEventId
     * @return array
     */
    public function fetchById($calendarEventGalleryId, $calendarEventId) {
        $db = $this->getDb();

        $result = $db->fetch1("
            SELECT g.* FROM calendar_event_gallery g
            JOIN calendar_event ce ON ce.ID_CALENDAR_EVENT = g.FK_CALENDAR_EVENT
            WHERE
                ce.ID_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
                AND g.ID_CALENDAR_EVENT_GALLERY = '" . mysql_real_escape_string($calendarEventGalleryId) . "'
        ");

        return $result;
    }

    /**
     * Holt alle Veranstaltungsbilder Bilder einer Veranstaltung
     *
     * @param $calendarEventId
     * @return array
     */
    public function fetchAllByCalendarEventId($calendarEventId) {
        $db = $this->getDb();

        $result = $db->fetch_table("
            SELECT g.* FROM calendar_event_gallery g
            WHERE
                g.FK_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
        ");

        return $result;
    }

    /**
     * Holt alle Veranstaltugsvideos einer Veranstaltung
     *
     * @param $calendarEventId
     * @return array
     */
    public function fetchAllVideosByCalendarEventId($calendarEventId) {
        $db = $this->getDb();

        $result = $db->fetch_table("
                SELECT g.* FROM calendar_event_gallery_video g
                WHERE
                    g.FK_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
            ");

        return $result;
    }

    /**
     * Prüft ob ein Veranstaltungs Bild mit der Id $calendarEventGalleryId existiert, das zu der
     * Veranstaltung mit der Id $calendarEventId gehört
     *
     * @param $calendarEventGalleryId
     * @param $calendarEventId
     * @return bool
     */
    private function existCalendarEventGalleryByCalendarEventId($calendarEventGalleryId, $calendarEventId) {
        $db = $this->getDb();

        $result = $db->fetch_atom("
            SELECT COUNT(*) FROM calendar_event_gallery g
            WHERE
                g.ID_CALENDAR_EVENT_GALLERY = '" . mysql_real_escape_string($calendarEventGalleryId) . "'
                AND g.FK_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
        ");

        return ($result > 0);
    }

    /**
     * Prüft ob ein Veranstaltungs Video mit der Id $calendarEventGalleryVideoId existiert, das zu der
     * Veranstaltung mit der Id $calendarEtId gehört
     *
     * @param $calendarEventGalleryVideoId
     * @param $calendarEventId
     * @return bool
     */
    private function existCalendarEventGalleryVideoByCalendarEventId($calendarEventGalleryVideoId, $calendarEventId) {
        $db = $this->getDb();

        $result = $db->fetch_atom("
            SELECT COUNT(*) FROM calendar_event_gallery_video g
            WHERE
                g.ID_CALENDAR_EVENT_GALLERY_VIDEO = '" . mysql_real_escape_string($calendarEventGalleryVideoId) . "'
                AND g.FK_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'
        ");

        return ($result > 0);
    }

    private function countCalendarEventGalleryByCalendarEventId($calendarEventId) {
        $db = $this->getDb();

        $countImages = $db->fetch_atom("SELECT COUNT(*) FROM calendar_event_gallery WHERE FK_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'");
        $countVideo = $db->fetch_atom("SELECT COUNT(*) FROM calendar_event_gallery_video WHERE FK_CALENDAR_EVENT = '" . mysql_real_escape_string($calendarEventId) . "'");

        return ($countImages + $countVideo);
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