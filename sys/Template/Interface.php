<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 23.05.16
 * Time: 09:31
 */

interface Template_Interface {

    public function addVariable($name, $value);
    public function addVariables($values, $prefix);
    public function getVariable($name);
    public function getVariables();
    
    public function render();
    
}