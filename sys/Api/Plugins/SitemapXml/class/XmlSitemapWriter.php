<?php

class XmlSitemapWriter extends XMLWriter {
    
    private $isFinished;
    
    private $countUrls;
    private $sizeEstimate;
    
    const CHANGE_ALWAYS = "always";
    const CHANGE_HOURLY = "hourly";
    const CHANGE_DAILY = "daily";
    const CHANGE_WEEKLY = "weekly";
    const CHANGE_MONTHLY = "monthly";
    const CHANGE_YEARLY = "yearly";
    const CHANGE_NEVER = "never";

    public function __construct($xmlNamespace = "http://www.sitemaps.org/schemas/sitemap/0.9") {
        // Initialize sitemap xml document
        $this->openMemory();
        $this->setIndent(true);
        $this->setIndentString(" ");
        $this->startDocument("1.0", "UTF-8");
        // Create root element
        $this->startElement("urlset");
        $this->startAttribute("xmlns");
        $this->text($xmlNamespace);
        $this->endAttribute();
        // Store current state
        $this->isFinished = false;
        $this->countUrls = 0;
        $this->sizeEstimate = 130;
        
    }
    
    private function finish() {
        if ($this->isFinished === false) {
            $this->endElement();
            $this->endDocument();
            $this->isFinished = true;
        }
        return true;
    }

    /**
     * @param string    $location           URL to be added (e.g. "http://www.example.com/some/page.htm")             
     * @param int       $lastModification   Last modification date as an integer (as returned by the php "time()" function)
     * @param string    $changeFrequency    Change frequency as defined in this classes CHANGE_* constants
     * @param float     $priority           Priority of this url (default is 0.5)
     * @throws Exception
     */
    public function addUrl($location, $lastModification, $changeFrequency, $priority = 0.5) {
        if ($this->isFinished) {
            throw new Exception("Can't add URL after document was finished!");
        }
        // URL element (start)
        $this->startElement("url");
        // Location
        $this->startElement("loc");
        $this->text($location);
        $this->endElement();        
        // Modification date
        $this->startElement("lastmod");
        $this->text(date(DateTime::W3C, $lastModification));
        $this->endElement();        
        // Change frequency
        $this->startElement("changefreq");
        $this->text($changeFrequency);
        $this->endElement();        
        // Priority
        $this->startElement("priority");
        $this->text($priority);
        $this->endElement();
        // URL element (end)
        $this->endElement();
        
        // Update url counter
        $this->countUrls++;
        $this->sizeEstimate += (140 + strlen($location));
    }
    
    public function getCountUrls() {
        return $this->countUrls;
    }
    
    public function getSizeEstimate() {
        return $this->sizeEstimate;
    }
    
    public function getDocument() {
        if (!$this->finish()) {
            throw new Exception("Failed to finish XML-Document!");
        }
        return $this->outputMemory();
    }
    
}