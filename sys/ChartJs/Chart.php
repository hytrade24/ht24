<?php

class ChartJs_Chart implements JsonSerializable {
  
  protected static $arOptionsDefault = array(
    "maintainAspectRatio" => false,
    "scales" => array(
      "yAxes" => [
        array(
          "type"        => "linear",
          "position"    => "left",
          "ticks"       => array(
            "min" => 0
          )
        )
      ]
    )
  );  
  
  protected static $pregKey_DateDay = "/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/";
  protected static $pregKey_DateMonth = "/^[0-9]{4}\-[0-9]{2}$/";
  
  private $type;
  private $arLabels;
  private $arOptions;
  private $arDataSets;
  
  public function __construct($chartType, $arLabels = array(), $arOptions = null, $arDataSets = array()) {
    $this->type = $chartType;
    $this->arLabels = $arLabels;
    $this->arOptions = ($arOptions === null ? self::$arOptionsDefault : $arOptions);
    $this->arDataSets = $arDataSets;
  }
  
  public function addDataSet(ChartJs_DataSet $dataSet) {
    $this->arDataSets[] = $dataSet;
  }
  
  public function createDataSet($backgroundColor = null, $borderColor = null, $label = null, $arData = array()) {
    $dataSet = new ChartJs_DataSet($backgroundColor, $borderColor, $label, $arData);
    $this->addDataSet($dataSet);
    return $dataSet;
  }
  
  public function fillGaps($defaultValue = 0, $interval = null) {
    if (empty($this->arDataSets)) {
      return;
    }
    $keyFirst = array_keys($this->arDataSets[0]->getData())[0];
    if (preg_match(self::$pregKey_DateDay, $keyFirst)) {
      // Date: Y-m-d
      return $this->fillGaps_DateDay($defaultValue, $interval);
    } else if (preg_match(self::$pregKey_DateMonth, $keyFirst)) {
      // Date: Y-m
      return $this->fillGaps_DateMonth($defaultValue, $interval);
    } else if (is_numeric($keyFirst)) {
      // Number
      return $this->fillGaps_Number($defaultValue, $interval);
    }
    // Unknown key type! Do nothing!
  }
  
  protected function fillGaps_Date($timestampMin, $timestampMax, $dateFormat, $interval, $intervalDate, $defaultValue = 0) {
    #die(var_dump($timestampMin, $timestampMax, $dateFormat, $interval, $intervalDate, $defaultValue));
    /**
     * Fill gaps
     * @var int             $dataSetIndex
     * @var ChartJs_DataSet $dataSet
     */
    foreach ($this->arDataSets as $dataSetIndex => $dataSet) {
      $arDataNew = array();
      if ($interval > 0) {
        $dateTimeCur = new DateTime( date($dateFormat, $timestampMin) );
        while ($dateTimeCur->getTimestamp() <= $timestampMax) {
          $key = $dateTimeCur->format($dateFormat);
          $value = $dataSet->getData($key);
          $arDataNew[$key] = ($value !== null ? $value : $defaultValue);
          $dateTimeCur->add($intervalDate);
        }
      } else {
        $dateTimeCur = new DateTime( date($dateFormat, $timestampMax) );
        while ($dateTimeCur->getTimestamp() >= $timestampMin) {
          $key = $dateTimeCur->format($dateFormat);
          $value = $dataSet->getData($key);
          $arDataNew[$key] = ($value !== null ? $value : $defaultValue);
          $dateTimeCur->sub($intervalDate);
        }
      }
      if (empty($this->arLabels)) {
        $this->arLabels = array_keys($arDataNew);
      }
      $dataSet->setData(array_values($arDataNew), false);
    }
    return $this;
  }
  
  protected function fillGaps_DateDay($defaultValue = 0, $interval = null) {
    $timestampMin = null;
    $timestampMax = null;
    if ($interval === null) {
      $interval = 1;
    }
    $intervalDate = new DateInterval("P".abs($interval)."D");
    /**
     * Get min and max value
     * @var int             $dataSetIndex
     * @var ChartJs_DataSet $dataSet
     */
    foreach ($this->arDataSets as $dataSetIndex => $dataSet) {
      foreach ($dataSet->getData() as $dataKey => $dataValue) {
        $dataKeyTimestamp = strtotime($dataKey);
        if (($timestampMin === null) || ($dataKeyTimestamp < $timestampMin)) {
          $timestampMin = $dataKeyTimestamp;
        }
        if (($timestampMax === null) || ($dataKeyTimestamp > $timestampMax)) {
          $timestampMax = $dataKeyTimestamp;
        }
      }
    }
    // Fill gaps
    return $this->fillGaps_Date($timestampMin, $timestampMax, "Y-m-d", $interval, $intervalDate, $defaultValue);
  }
  
  protected function fillGaps_DateMonth($defaultValue = 0, $interval = null) {
    $timestampMin = null;
    $timestampMax = null;
    if ($interval === null) {
      $interval = 1;
    }
    $intervalDate = new DateInterval("P".abs($interval)."M"); 
    /**
     * Get min and max value
     * @var int             $dataSetIndex
     * @var ChartJs_DataSet $dataSet
     */
    foreach ($this->arDataSets as $dataSetIndex => $dataSet) {
      foreach ($dataSet->getData() as $dataKey => $dataValue) {
        $dataKeyTimestamp = strtotime($dataKey . "-01");
        if (($timestampMin === null) || ($dataKeyTimestamp < $timestampMin)) {
          $timestampMin = $dataKeyTimestamp;
        }
        if (($timestampMax === null) || ($dataKeyTimestamp > $timestampMax)) {
          $timestampMax = $dataKeyTimestamp;
        }
      }
    }
    // Fill gaps
    return $this->fillGaps_Date($timestampMin, $timestampMax, "Y-m", $interval, $intervalDate, $defaultValue);
  }
  
  protected function fillGaps_Number($defaultValue = 0, $interval = null) {
    $numberMin = null;
    $numberMax = null;
    if ($interval === null) {
      $interval = 1;
    }
    // Get min and max value
    foreach ($this->arDataSets as $dataSetIndex => $dataSet) {
      foreach ($dataSet->getData() as $dataKey => $dataValue) {
        if (($numberMin === null) || ($dataKey < $numberMin)) {
          $numberMin = $dataKey;
        }
        if (($numberMax === null) || ($dataKey > $numberMax)) {
          $numberMax = $dataKey;
        }
      }
    }
    /**
     * Fill gaps
     * @var int             $dataSetIndex
     * @var ChartJs_DataSet $dataSet
     */
    foreach ($this->arDataSets as $dataSetIndex => $dataSet) {
      $arDataNew = array();
      if ($interval > 0) {
        for ($key = $numberMin; $key <= $numberMax; $key += $interval) {
          $value = $dataSet->getData($key);
          $arDataNew[$key] = ($value !== null ? $value : $defaultValue);
        }
      } else {
        for ($key = $numberMax; $key >= $numberMin; $key += $interval) {
          $value = $dataSet->getData($key);
          $arDataNew[$key] = ($value !== null ? $value : $defaultValue);
        }
      }
      $dataSet->setData($arDataNew);
    }
    return $this;
  }
  
  public function getLabels() {
    return $this->arLabels;
  }
  
  public function hasCustomKeys() {
    /**
     * Fill gaps
     * @var int             $dataSetIndex
     * @var ChartJs_DataSet $dataSet
     */
    foreach ($this->arDataSets as $dataSetIndex => $dataSet) {
      if ($dataSet->hasCustomKeys()) {
        return true;
      }
    }
    return false;
  }
  
  public function setLabels($arLabels) {
    $this->arLabels = $arLabels;
  }
  
  public function setLabelsByDate($dateFormat = "Y-m-d") {
    if (empty($this->arDataSets)) {
      return;
    }
    if ($this->hasCustomKeys()) {
      $this->fillGaps();
    }
    $arDates = $this->arLabels;
    if (empty($arDates)) {
      // No labels available for conversion
      return;
    }
    if (!preg_match(self::$pregKey_DateDay, $arDates[0]) && !preg_match(self::$pregKey_DateMonth, $arDates[0])) {
      // Unknown date format!
      return;
    }
    $this->arLabels = array();
    foreach ($arDates as $dateIndex => $dateValue) {
      if (is_string($dateFormat)) {
        $this->arLabels[] = date($dateFormat, strtotime($dateValue));
      } else {
        $this->arLabels[] = $dateFormat($dateValue);
      }
    }
  }
  
  public function setOption($optionName, $optionValue, $merge = false) {
    if ($merge && is_array($optionValue) && array_key_exists($optionName, $this->arOptions) && is_array($this->arOptions[$optionName])) {
      $this->arOptions[$optionName] = array_merge($this->arOptions[$optionName], $optionValue);
    } else {
      $this->arOptions[$optionName] = $optionValue;
    }
  }
  
  public function setOptions($arOptions, $merge = true) {
    if ($merge) {
      $this->arOptions = array_merge($this->arOptions, $arOptions);
    } else {
      $this->arOptions = $arOptions;
    }
  }
  
  public function setTitle($title) {
    $this->setOption("title", array("display" => true, "text" => $title), true);
  }

  /**
   * (PHP 5 &gt;= 5.4.0)<br/>
   * Specify data which should be serialized to JSON
   * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
   * @return mixed data which can be serialized by <b>json_encode</b>,
   * which is a value of any type other than a resource.
   */
  function jsonSerialize() {
    return array(
      "type"    => $this->type,
      "data"    => array(
        "labels"    =>  $this->arLabels, 
        "datasets"  =>  $this->arDataSets
      ),
      "options" => $this->arOptions
    );
  }
}