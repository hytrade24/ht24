<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 20.07.15
 * Time: 11:32
 */

class Api_Entities_EventParamContainer {

    protected $arParams;
    protected $dirty;
    protected $readOnly;
    
    function __construct($arParams, $readOnly = false) {
        $this->arParams = $arParams;
        $this->dirty = false;
        $this->readOnly = $readOnly;
    }

    /**
     * Get value of a parameter
     * @param $name
     * @return mixed|null
     */
    public function getParam($name) {
        if (is_array($this->arParams) && array_key_exists($name, $this->arParams)) {
            return $this->arParams[$name];
        } else {
            return NULL;
        }
    }

    /**
     * Check if the given key exists within the array of the given parameter.
     * @param $name
     * @param $key
     * @return bool
     */
    public function getParamArrayKeyExists($name, $key) {
        if (is_array($this->arParams) && array_key_exists($name, $this->arParams)) {
            return (is_array($this->arParams[$name]) && array_key_exists($key, $this->arParams[$name]));
        } else {
            return false;
        }
    }

    /**
     * Get a field of the array stored within the given parameter.
     * @param $name
     * @param $key
     * @return mixed|null
     */
    public function getParamArrayValue($name, $key) {
        if (is_array($this->arParams) && array_key_exists($name, $this->arParams)) {
            if (is_array($this->arParams[$name]) && array_key_exists($key, $this->arParams[$name])) {
                return $this->arParams[$name][$key];
            }
        }
        return null;
    }

    /**
     * Get all parameters as array
     * @return array
     */
    public function getParams() {
        return $this->arParams;
    }

    /**
     * Check if parameters have been changed
     * @return bool
     */
    public function isDirty() {
        return $this->dirty;
    }

    /**
     * Set/clear dirty flag
     * @param bool $isDirty
     */
    public function setDirty($isDirty = true) {
        $this->dirty = $isDirty;
    }

    /**
     * Set the value of the given parameter
     * @param $name
     * @param $value
     * @return bool
     */
    public function setParam($name, $value) {
        if ($this->readOnly) {
            return false;
        }
        if (is_array($this->arParams)) {
            $this->dirty = true;
            $this->arParams[$name] = $value;
            return true;
        } else {
            return false;            
        }
    }

    /**
     * Append an element to the array within the given parameters array (numeric/autoincrement index)
     * @param $nameOfArray
     * @param $value
     * @return bool
     */
    public function setParamArrayAppend($nameOfArray, $value) {
        if ($this->readOnly) {
            return false;
        }
        if (is_array($this->arParams) && is_array($this->arParams[$nameOfArray])) {
            $this->dirty = true;
            $this->arParams[$nameOfArray][] = $value;
            return true;
        } else {
            return false;            
        }
    }

    /**
     * Write a key/value pair to the array within the given parameters array
     * @param $nameOfArray
     * @param $name
     * @param $value
     * @return bool
     */
    public function setParamArrayValue($nameOfArray, $name, $value) {
        if ($this->readOnly) {
            return false;
        }
        if (is_array($this->arParams) && is_array($this->arParams[$nameOfArray])) {
            $this->dirty = true;
            $this->arParams[$nameOfArray][$name] = $value;
            return true;
        } else {
            return false;            
        }
    }

    /**
     * Set all parameters at once, replacing the current ones.
     * @param $arParams
     * @return bool
     */
    public function setParams($arParams) {
        if ($this->readOnly) {
            return false;
        }
        $this->dirty = true;
        $this->arParams = $arParams;
        return true;
    }

    /**
     * Unsets the given parameter
     * @param $name
     * @return bool
     */
    public function unsetParam($name) {
        if (is_array($this->arParams) && array_key_exists($name, $this->arParams)) {
            unset($this->arParams[$name]);
            return true;
        }
        return false;
    }

    /**
     * Unsets a key within the array of the given parameter 
     * @param $name
     */
    public function unsetParamArrayKey($name, $key) {
        if (is_array($this->arParams) && array_key_exists($name, $this->arParams)) {
            if (is_array($this->arParams[$name]) && array_key_exists($key, $this->arParams[$name])) {
                unset($this->arParams[$name][$key]);
                return true;
            }
        }
        return false;
    }
    
}