<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 18.09.15
 * Time: 11:57
 */

class Api_CurlMultiQueue {

    protected $maxConnections;
    protected $maxRetryCount;
    
    protected $curlMulti;
    protected $curlRequests;
    protected $curlRequestsPending;
    protected $curlRequestsQueued;
    protected $curlRequestsDone;
    protected $curlRequestsRetryCount;
    
    function __construct($maxConcurrentConnections = 20, $maxRequestRetryCount = 3) {
        $this->maxConnections = $maxConcurrentConnections;
        $this->maxRetryCount = $maxRequestRetryCount;
        $this->curlMulti = null;
        $this->curlRequests = array();
        $this->curlRequestsPending = array();
        $this->curlRequestsQueued = array();
        $this->curlRequestsDone = array();
        $this->curlRequestsRetryCount = array();
    }
    
    public function cleanup() {
        if ($this->curlMulti !== null) {
            foreach ($this->curlRequestsDone as $index => $curlRequest) {
                curl_multi_remove_handle($this->curlMulti, $curlRequest);
            }
            curl_multi_close($this->curlMulti);
        }
        $this->curlMulti = null;
        $this->curlRequests = array();
        $this->curlRequestsPending = array();
        $this->curlRequestsQueued = array();
        $this->curlRequestsDone = array();
        $this->curlRequestsRetryCount = array();
    }
    
    public function addRequest($curlRequest, $forceQueue = false) {
        if ($this->curlMulti === null) {
            $this->curlMulti = curl_multi_init();
        }
        $requestId = (int)$curlRequest;
        $this->curlRequests[] = $curlRequest;
        if (!$forceQueue && (count($this->curlRequestsPending) < $this->maxConnections)) {
            curl_multi_add_handle($this->curlMulti, $curlRequest);
            $this->curlRequestsPending[$requestId] = $curlRequest;
        } else {
            $this->curlRequestsQueued[$requestId] = $curlRequest;
        }
    }
    
    function getRequestsDone() {
        return $this->curlRequestsDone;
    }
    
    function execute() {
        if ($this->curlMulti === null) {
            // No request(s) added
            return true;
        }
        $active = true;
        // Process requests
        do {
       		$mrc = curl_multi_exec($this->curlMulti, $active);
       	} while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && ($mrc == CURLM_OK)) {
            curl_multi_select($this->curlMulti);
            do {
                $mrc = curl_multi_exec($this->curlMulti, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            /*
       		if (curl_multi_select($this->curlMulti) != -1) {
              // Some connection state changed
              do {
                  $mrc = curl_multi_exec($this->curlMulti, $active);
              } while ($mrc == CURLM_CALL_MULTI_PERFORM);
       		} else {
              //die("TIMEOUT!");
              // Timeout
              //return false;
              break;
          }
            */
       	}
        // Now grab the information about the completed requests
        while ($info = curl_multi_info_read($this->curlMulti)) {
            $curlRequest = $info['handle'];
            $requestId = (int)$curlRequest;
            if (!array_key_exists($requestId, $this->curlRequestsPending)) {
                die("Error - handle wasn't found in pending requests: ".$requestId." / ".var_export($this->outstanding_requests, true));
            } else if ($info["result"] !== CURLM_OK) {
                $retryCount = 0;
                if (array_key_exists($requestId, $this->curlRequestsRetryCount)) {
                    $retryCount = $this->curlRequestsRetryCount[$requestId];
                }
                if ($retryCount < $this->maxRetryCount) {
                    // Retry request
                    $curlRequestNew = curl_copy_handle($curlRequest);
                    $this->addRequest($curlRequestNew, true);
                }
            } else {
                $this->curlRequestsDone[] = $curlRequest;
            }
            unset($this->curlRequestsPending[$requestId]);
        }
        // Check for queued requests
        if (!empty($this->curlRequestsQueued)) {
            $queuedRequestIds = array_keys($this->curlRequestsQueued);
            while (!empty($this->curlRequestsQueued) && (count($this->curlRequestsPending) < $this->maxConnections)) {
                // Get next queued request
                $requestId = array_shift($queuedRequestIds);
                $curlRequest = $this->curlRequestsQueued[$requestId];
                curl_multi_add_handle($this->curlMulti, $curlRequest);
                $this->curlRequestsPending[$requestId] = $curlRequest;
                unset($this->curlRequestsQueued[$requestId]);
            }
            #var_dump( "Done", count($this->curlRequestsDone), "Pending", count($this->curlRequestsPending), "Queued", count($this->curlRequestsQueued) );
            return $this->execute();
        }
        return true;
    }
    
}