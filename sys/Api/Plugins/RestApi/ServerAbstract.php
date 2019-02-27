<?php

abstract class Api_Plugins_RestApi_ServerAbstract implements Api_Plugins_RestApi_ServerInterface {
    
    protected   $statusCode;
    protected   $statusMessage;
    
    public function __construct() {
        $this->statusCode = 404;
        $this->statusMessage = "Not found";
    }
    
    protected function setStatus($statusCode, $statusMessage) {
        $this->statusCode = $statusCode;
        $this->statusMessage = $statusMessage;
    }
    
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public function getStatusMessage() {
        return $this->statusMessage;
    }
    
}