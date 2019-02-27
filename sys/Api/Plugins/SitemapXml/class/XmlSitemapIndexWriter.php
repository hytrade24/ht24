<?php

class XmlSitemapIndexWriter extends XMLWriter {
    
    private $isFinished;

    public function __construct($xmlNamespace = "http://www.sitemaps.org/schemas/sitemap/0.9") {
        // Initialize sitemap xml document
        $this->openMemory();
        $this->setIndent(true);
        $this->setIndentString(" ");
        $this->startDocument("1.0", "UTF-8");
        // Create root element
        $this->startElement("sitemapindex");
        $this->startAttribute("xmlns");
        $this->text($xmlNamespace);
        $this->endAttribute();
        // Store current state
        $this->isFinished = false;
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
     * @throws Exception
     */
    public function addSitemap($location, $lastModification) {
        if ($this->isFinished) {
            throw new Exception("Can't add URL after document was finished!");
        }
        // URL element (start)
        $this->startElement("sitemap");
        // Location
        $this->startElement("loc");
        $this->text($location);
        $this->endElement();        
        // Modification date
        $this->startElement("lastmod");
        $this->text(date(DateTime::W3C, $lastModification));
        $this->endElement();        
        // URL element (end)
        $this->endElement();
    }
    
    public function getDocument() {
        if (!$this->finish()) {
            throw new Exception("Failed to finish XML-Document!");
        }
        return $this->outputMemory();
    }
    
}