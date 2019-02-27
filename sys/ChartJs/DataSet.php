<?php

class ChartJs_DataSet implements JsonSerializable {
    
  protected static $arOptionsDefault = array(
      "fill"                      => false,
      "lineTension"               => 0.1,
      "spanGaps"                  => false
  );  
  
  private $colorBack;
  private $colorBorder;
  private $customKeys;
  private $label;
  private $arData;
  private $arOptions;
  
  public function __construct($backgroundColor = null, $borderColor = null, $label = null, $arData = array(), $arOptions = array()) {
    $this->colorBack = $backgroundColor;
    $this->colorBorder = $borderColor;
    $this->customKeys = false;
    $this->label = $label;
    $this->arData = $arData;
    $this->arOptions = $arOptions;
  }
  
  public function addData($value, $index = null) {
    if ($index === null) {
      $this->arData[] = $value;
    } else {
      if (!array_key_exists($index, $this->arData) && ($index != count($this->arData))) {
        $this->customKeys = true;
      }
      $this->arData[$index] = $value;
    }
    return $this;
  }
  
  public function getData($key = null) {
    if ($key === null) {
      return $this->arData;
    } else {
      return (array_key_exists($key, $this->arData) ? $this->arData[$key] : null);
    }
  }
  
  public function hasCustomKeys() {
    return $this->customKeys;
  }
  
  public function setBackgroundColor($color) {
    $this->colorBack = $color;
    return $this;
  }
  
  public function setBorderColor($color) {
    $this->colorBorder = $color;
    return $this;
  }
  
  public function setLabel($color) {
    $this->label = $color;
    return $this;
  }
  
  public function setData($arData, $customKeys = null) {
    $this->arData = $arData;
    if ($customKeys !== null) {
      $this->customKeys = $customKeys;
    }
    return $this;
  }
  
  public function setOption($optionName, $optionValue, $merge = false) {
    if ($merge && is_array($optionValue) && array_key_exists($optionName, $this->arOptions) && is_array($this->arOptions[$optionName])) {
      $this->arOptions[$optionName] = array_merge($this->arOptions[$optionName], $optionValue);
    } else {
      $this->arOptions[$optionName] = $optionValue;
    }
    return $this;
  }
  
  public function setOptions($arOptions, $merge = true) {
    if ($merge) {
      $this->arOptions = array_merge($this->arOptions, $arOptions);
    } else {
      $this->arOptions = $arOptions;
    }
    return $this;
  }

  /**
   * (PHP 5 &gt;= 5.4.0)<br/>
   * Specify data which should be serialized to JSON
   * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
   * @return mixed data which can be serialized by <b>json_encode</b>,
   * which is a value of any type other than a resource.
   */
  function jsonSerialize() {
    $arResult = self::$arOptionsDefault;
    if ($this->label !== null) {
      $arResult["label"] = $this->label;
    }
    if ($this->colorBack !== null) {
      $arResult["backgroundColor"] = $this->colorBack;
      $arResult["pointBackgroundColor"] = $this->colorBack;
      $arResult["pointHoverBackgroundColor"] = $this->colorBack;
    }
    if ($this->colorBorder !== null) {
      $arResult["borderColor"] = $this->colorBorder;
      $arResult["pointBorderColor"] = $this->colorBorder;
      $arResult["pointHoverBackgroundColor"] = $this->colorBorder;
      $arResult["pointHoverBorderColor"] = $this->colorBorder;
    }
    $arResult = array_merge($arResult, $this->arOptions);
    $arResult["data"] = $this->arData;
    return $arResult;
  }
}