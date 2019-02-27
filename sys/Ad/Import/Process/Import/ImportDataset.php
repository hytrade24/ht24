<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 15.06.15
 * Time: 11:14
 */

class Ad_Import_Process_Import_ImportDataset {
    
    private $arDataset;
    private $process;
    
    public function __construct($arData = array(), Ad_Import_Process_Process $process) {
        $this->arDataset = $arData;
        $this->process = $process;
    }

    /**
     * Get the whole content of the current dataset
     * @return assoc
     */
    public function getContent() {
        return $this->arDataset;            
    }

    /**
     * Get the preset used to import the dataset
     * @return Ad_Import_Process_Process
     */
    public function getProcess() {
        return $this->process;
    }

    /**
     * Get the preset used to import the dataset
     * @return Ad_Import_Preset_AbstractPreset
     */
    public function getPreset() {
        return $this->process->getPreset();
    }
    
    /**
     * Get a specific value from the dataset
     * @param $name     Name of the value to get
     * @return mixed    Value of the given dataset
     */
    public function getValue($name) {
        return (array_key_exists($name, $this->arDataset) ?  $this->arDataset[$name] : null);
    }

    /**
     * Completely overwrite the data stored in the current dataset.
     * @param $arData   New data to be stored.
     * @return bool     True on success.
     */
    public function setContent($arData) {
        $this->arDataset = $arData;
        return true;
    }

    /**
     * Sets a specific value for the current dataset.
     * @param $name     Name of the value to be changed.
     * @param $value    New value to be set.
     * @return bool     True on success.
     */
    public function setValue($name, $value) {
        $this->arDataset[$name] = $value;
        return true;
    }
    
}