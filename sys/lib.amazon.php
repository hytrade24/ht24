<?php
/* ###VERSIONSBLOCKINLCUDE### */


	// Amazon Artikel-Suche
	
	// Klasse amazon
	// ------------------------------------------------------------------------------------------------------
	// Eigenschaften:
	// 		amazon->result_xml		- Das Suchergebniss als XML Objekt
	// 		amazon->results_count	- Anzahl der Suchergebnisse
	// 		amazon->results_pages	- Anzahl der Seiten
	//	Letztes Suchergebniss für Pager
	// 		amazon->search_index	- Kategorie für die Suche ('Books' / 'DVD' / 'Music' / ...)
	// 		amazon->search_keywords	- Suchbegriffe
	// 		amazon->search_page		- Ergebniss-Seite
	// ------------------------------------------------------------------------------------------------------
	// $i_results = Search($search_index, $search_keywords, $search_page = 1);
	//		Sucht in der Kategorie '$search_index' nach den Suchbegriffen '$search_keywords' und
	//		legt die Ergebnisse innerhalb der Klasse ab. (amazon->results_xml)
	//		$search_index			- Kategorie für die Suche ('Books' / 'DVD' / 'Music' / ...)
	//		$search_keywords		- Suchbegriffe
	//		$search_page			- Ergebniss-Seite
	//	Rückgabe:
	//		Als Rückgabe erhält man die Anzahl der Suchergebnisse
	// ------------------------------------------------------------------------------------------------------
	// GetPage($search_page = -1);
	//		Ruft die nächste Seite der Suchergebnisse ab oder Seite '$search_page' falls angegeben.
	//		$search_page			- Ergebniss-Seite
	// ------------------------------------------------------------------------------------------------------
	// $ar_result = FetchResult($result_id);
	// 		$result_id					- Nummer des Suchergebnisses (von 0 bis amazon->results_count-1)
	//	Rückgabe:
	//		$ar_result["ImageSmall"]	- URL zu Bild in kleiner Größe 		( 75pixel hoch)
	//		$ar_result["ImageMedium"]	- URL zu Bild in mittlerer Größe 	(160pixel hoch)
	//		$ar_result["ImageLarge"]	- URL zu Bild in großer Größe 		(500pixel hoch)
	//
	//		$ar_result["Title"]			- Kompletter Titel
	//		$ar_result["AuthorGeneral"]	- Author / Artist / Director (sollte niemals leer sein)
	//		$ar_result["Author"]		- Author
	//		$ar_result["Artist"]		- Artist
	//		$ar_result["Director"]		- Director
	//	-- Nur für Bücher
	//		$ar_result["ISBN"]			- ISBN Nummer
	//		$ar_result["Binding"]		- Buchart (Digital, Taschenbuch, ...)
	//		$ar_result["Pages"]			- Anzahl der Seiten
	//
	//		$ar_result["Price"]			- Preis
	//		$ar_result["ListingID"]		- URL zu Bild in mittlerer Größe (160pixel hoch)
	// ------------------------------------------------------------------------------------------------------
	// FetchPage(&$ar_results);
	//	Holt alle Ergebnisse für die aktuelle Seite und schreibt sie in die übergebene Variable.
	//	$ar_results[0 bis amazon->results_count-1] enthält jeweils ein Array im Format von 'FetchResult'
	//	Die Ergebnisse müssen Seite für Seite abgerufen werden!! ( -> GetPage() )
	// ------------------------------------------------------------------------------------------------------

	class amazon {
		public $KeyID;
		public $AssocTag;
		
		public function __construct($AmazonKeyID, $AssocTag) {
			$this->KeyID = $AmazonKeyID;
			$this->AssocTag = $AssocTag;
			$this->results_count = 0;
			$this->results_pages = 0;
		}
		
		public function Search($search_index, $search_keywords, $search_page = 1) {
			$this->search_index = $search_index;
			$this->search_keywords = $search_keywords;
			$this->search_page = $search_page;
			
			// Suche in Amazon
			// $search_index = 'Books' / 'DVD' / 'Music' / ...
			// $search_keywords = Suchbegriff(e)
			$search_request  = "http://ecs.amazonaws.de/onca/xml?Service=AWSECommerceService&AWSAccessKeyId=".$this->KeyID;
			$search_request .= "&AssociateTag=".$this->AssocTag."&Version=2006-09-11&Operation=ItemSearch&ResponseGroup=Medium,Offers";
			$search_request .= "&SearchIndex=".$search_index."&Keywords=".$search_keywords."&ItemPage=".$search_page;
			/*$search_session = curl_init($search_request);
			curl_setopt($session, CURLOPT_HEADER, false);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($session);
			curl_close($session); */
			$response = file_get_contents($search_request);
			$this->result_page = $search_page;
			$this->result_xml = simplexml_load_string($response);
			$this->results_count = $this->result_xml->Items->TotalResults;
			$this->results_pages = $this->result_xml->Items->TotalPages;
			return $this->results_count;
		}
		
		public function GetPage($search_page = -1) {
			if ($search_page == -1) {
				$search_page = $this->search_page + 1;
			}
			$this->Search($this->search_index, $this->search_keywords, $search_page);
		}
		
		public function FetchResult($result_id) {
			$result = array();
			if ($this->results_count >= $result_id) {
				$result_item = $this->result_xml->Items->Item[$result_id];
				$result["ImageSmall"] = $result_item->SmallImage->URL;
				$result["ImageMedium"] = $result_item->MediumImage->URL;
				$result["ImageLarge"] = $result_item->LargeImage->URL;
				
				$result["Title"] = $result_item->ItemAttributes->Title;
				$result["Author"] = $result_item->ItemAttributes->Author;
				$result["Artist"] = $result_item->ItemAttributes->Artist;
				$result["Director"] = $result_item->ItemAttributes->Director;
				if(isset($result_item->ItemAttributes->Director)){
					$result["AuthorGeneral"] = $result_item->ItemAttributes->Director;
				} elseif(isset($result_item->ItemAttributes->Author)) {
					$result["AuthorGeneral"] = $result_item->ItemAttributes->Author;
				} elseif(isset($result_item->ItemAttributes->Artist)) {
					$result["AuthorGeneral"] = $result_item->ItemAttributes->Artist;
				}
				$result["ISBN"] = $result_item->ItemAttributes->ISBN;
				$result["Binding"] = $result_item->ItemAttributes->Binding;
				$result["Pages"] = $result_item->ItemAttributes->NumberOfPages;
				
				$result["Price"] = $result_item->Offers->Offer->OfferListing->Price->FormattedPrice;
				$result["ListingID"] = $result_item->Offers->Offer->OfferListing->OfferListingId;
			}
			return $result;
		}
		public function FetchPage(&$ar_results) {
			if (!$ar_results) {
				$ar_results = array();
			}
			if ($page_end >= $this->results_count) {
				// Letzte Seite kann weniger als 10 Artikel beinhalten
				$page_end = $this->results_count - 1;
			}
			for ($result_id = 0; $result_id <= 9; $result_id++) {
				array_push($ar_results, $this->FetchResult($result_id));
			}
			return true;
		}
	}
?>