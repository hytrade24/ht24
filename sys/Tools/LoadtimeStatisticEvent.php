<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 11:58
 */

class Tools_LoadtimeStatisticEvent {

    private $type;
    private $name;
    private $timeStart;
    private $timeEnd;
    private $arDetails;

    function __construct($type, $name, $arDetails = array()) {
        $this->type = $type;
        $this->name = $name;
        $this->timeStart = microtime(true);
        $this->timeEnd = false;
        $this->arDetails = $arDetails;
    }

    function __destruct() {
        $this->finish();
    }

    public function finish() {
        if ($this->timeEnd === false) {
            $this->timeEnd = microtime(true);
        }
        return false;
    }

    public function getType() {
        return $this->type;
    }

    public function getName() {
        return $this->name;
    }

    public function getTimeStart() {
        return $this->timeStart;
    }

    public function getTimeEnd() {
        return $this->timeEnd;
    }

    public function getDetails() {
        return $this->arDetails;
    }

    public function setDetails($arDetails) {
        $this->arDetails = $arDetails;
    }

    public function updateDetails($arDetails) {
        $this->arDetails = array_merge($this->arDetails, $arDetails);
    }

    public function serialize($finish = true) {
        if ($finish) {
            $this->finish();
        }
        return array(
            "type"      => $this->getType(),
            "name"      => $this->getName(),
            "duration"  => $this->getTimeEnd() - $this->getTimeStart(),
            "timeStart" => $this->getTimeStart(),
            "timeEnd"   => $this->getTimeEnd(),
            "details"   => $this->getDetails()
        );
    }

} 