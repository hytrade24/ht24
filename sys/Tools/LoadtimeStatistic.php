<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 11:58
 */

class Tools_LoadtimeStatistic {

    private static $instance = null;

    /**
     * @return Tools_LoadtimeStatistic
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Tools_LoadtimeStatistic();
        }
        return self::$instance;
    }

    private $arLoadEvents;
    private $suppress;
    private $requestEvent;

    function __construct() {
        $this->arLoadEvents = array();
        $this->suppress = false;
        $this->requestEvent = $this->createEvent("Request", $_SERVER["REQUEST_URI"], $_SERVER);
    }

    function __destruct() {
        $this->finish();
    }

    /**
     * Removes all recorded events from the session and returns them
     * @return mixed
     */
    public function flushEvents($suppressCurrentRequest = true) {
        if ($suppressCurrentRequest) {
            $this->suppress = true;
        }
        $arEvents = (array_key_exists("ebizToolsLoadEvents", $_SESSION) ? $_SESSION["ebizToolsLoadEvents"] : array());
        $_SESSION["ebizToolsLoadEvents"] = array();
        return $arEvents;
    }

    /**
     * Creates a new event starting now
     * @param string    $type
     * @param string    $name
     * @param array     $arDetails
     * @return Tools_LoadtimeStatisticEvent
     */
    public function createEvent($type, $name, $arDetails = array()) {
        $event = new Tools_LoadtimeStatisticEvent($type, $name, $arDetails);
        $this->arLoadEvents[] = $event;
        return $event;
    }

    /**
     * Prevents this request from being tracked
     */
    public function suppress() {
        $this->suppress = true;
    }

    public function finish() {
        if (!$this->suppress && !empty($this->arLoadEvents)) {
            $arSkipShort = array("DB"); // Skip extemely short database queries
            // Finish request event
            $this->requestEvent->finish();
            // Serialize events
            $arEvents = array();
            foreach ($this->arLoadEvents as $event) {
                if (in_array($event->getType(), $arSkipShort) && (($event->getTimeEnd() - $event->getTimeStart()) < 0.01)) {
                    continue;
                }
                $arEvents[] = $event->serialize();
            }
            // Write to session
            if (!array_key_exists("ebizToolsLoadEvents", $_SESSION)) {
                $_SESSION["ebizToolsLoadEvents"] = array();
            }
            $_SESSION["ebizToolsLoadEvents"][] = $arEvents;
            // Clear events
            $this->arLoadEvents = array();
        }
    }
    
} 