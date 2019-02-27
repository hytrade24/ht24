<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 *
 * @author Philipp Kiszka <info@o-dyn.de>
 * @name Gmapper
 * @version 1.2
 * @link http://gmapper.ajax-info.de
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
class gmap
{
    // Google Maps API Key
    var $key;
    var $maptyp;
    /**
     * Der Konstruktor nimmt den Google Maps API Key als Parameter entgegen.
     * Dies ist die empfohlene Methode, den API Key einzubinden.
     *
     * @param string $mapkey Google Maps API Key
     */
    function gmap($mapkey = "ABQIAAAAG8LYGXNItDua8E05WUM8NRQEWiWiNfAAkhiAI6mrfOZ5slJ_cBRmvAweX3zZex2K-zkkawpN5F1tzA")
    {
        $this->key = $mapkey;
    }
    /**
     * Erzeugt JS Code im HEAD Bereich der Seite.
     * DEPRECATED:
     * API Key sollte jetzt im Konstruktor der Klasse übergeben werden.
     * Beispiel:
     * $karte = new gmap("API KEY");
     *
     * @param string $key Google Maps API Key
     * @return bool
     */
    function headjs($key = "")
    {
        if (!$key)
        {
            $key = $this->key;
        }
        echo "<script src=\"http://maps.google.com/maps?file=api&amp;v=2.x&amp;key=$key\" type=\"text/javascript\"></script>\n
			  <style type=\"text/css\">\n
	    	  v\:* {\n
	      	  behavior:url(#default#VML);\n
	    	  }\n
	          </style>";
        if ($key)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Platziert die Karte
     *
     * @param int $width Breite der Karte
     * @param int $height Höhe der Karte
     * @return bool
     */
    function mapdiv($height, $width)
    {
        $width = $width . "px";
        $height = $height . "px";
        echo "<div id = \"gmapper_map\" style = \"width:$width;height:$height;\" ></div>";
        if ($height and $width)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Erzeugt die Kartendarstellung
     *
     * @param int $zoom Initiale Zoomstufe
     * @param float $lat Initialer Breitengrad
     * @param float $lng Initialer Längengrad
     * @param string $typ Kartentyp (normal,satellit,hybrid) Default: normal
     * @param int $min_zoom Minimal mögliche Zoomstufe
     * @param int $max_zoom Maximal Mögliche Zoomstufe
     * @param string $navcontrol Typ der Kartennavigation (small,large) Default: large
     * @param int $overview Schaltet die Übersichtskarte an oder aus.
     * @param int $mousezoom Schaltet den Mouserad Zoom an oder aus.
     * @param int $tilecheck Schaltet den Check nach Tile-Grafiken beim Zoomen an und aus (Default: an)
     * @return bool
     */
    function map($zoom, $lat, $lng, $typ = "normal", $min_zoom = 0, $max_zoom = 0, $navcontrol = "large", $overview = 0, $mousezoom = 1, $tilecheck = 1)
    {
        $this->maptyp = $typ;
        $lat2 = $lat - 0.1;
        if ($typ == "normal")
        {
            $typstr = "G_NORMAL_MAP";
        } elseif ($typ == "satellit")
        {
            $typstr = "G_SATELLITE_MAP";
        } elseif ($typ == "hybrid")
        {
            $typstr = "G_HYBRID_MAP";
        } elseif ($typ == "physical")
        {
            $typstr = "G_PHYSICAL_MAP";
        } elseif ($typ == "moon_elevation")
        {
            $typstr = "G_MOON_ELEVATION_MAP";
        } elseif ($typ == "moon")
        {
            $typstr = "G_MOON_VISIBLE_MAP";
        } elseif ($typ == "mars_elevation")
        {
            $typstr = "G_MARS_ELEVATION_MAP";
        } elseif ($typ == "mars")
        {
            $typstr = "G_MARS_VISIBLE_MAP";
        } elseif ($typ == "mars_infrared")
        {
            $typstr = "G_MARS_INFRARED_MAP";
        } elseif ($typ == "nightsky")
        {
            $typstr = "G_SKY_VISIBLE_MAP";
        }
        if ($navcontrol == "large")
        {
            $controlstr = "GLargeMapControl()";
        } elseif ($navcontrol == "small")
        {
            $controlstr = "GSmallMapControl()";
        }
        echo "<script type=\"text/javascript\">\n
        //<![CDATA[\n
		var map = new GMap2(document.getElementById(\"gmapper_map\"));\n
		var geocoder = new GClientGeocoder();\n";
        if ($typ == "physical")
        {
            echo "map.addMapType(G_PHYSICAL_MAP); ";
        }
        if ($mousezoom)
        {
            echo "map.enableScrollWheelZoom();\n
			    var mapdiv = document.getElementById(\"gmapper_map\");\n
   				GEvent.addDomListener(mapdiv, \"DOMMouseScroll\", wheelevent);\n
    			mapdiv.onmousewheel = wheelevent;\n	";
        }
        if ($overview)
        {
            echo "map.addControl(new GOverviewMapControl());\n";
        }
        echo "
		map.addControl(new $controlstr);\n
		map.addControl(new GMapTypeControl());\n";
        if ($min_zoom > 0 and $max_zoom > 0)
        {
            echo "
		var mt = map.getMapTypes();
      	for (var i=0; i<mt.length; i++) {
        mt[i].getMinimumResolution = function() {return $min_zoom;}
        mt[i].getMaximumResolution = function() {return $max_zoom;}
      }";
        }
        echo "
		//var mycontrol = new GHierarchicalMapTypeControl();
		var mycontrol = new GMapTypeControl();
		map.addControl(mycontrol);\n
		map.setCenter(new GLatLng( $lat,$lng),$zoom);\n
		new GKeyboardHandler(map);\n
		map.enableDoubleClickZoom();\n
		map.enableContinuousZoom();\n
		map.setMapType($typstr);

		\n";
        if ($tilecheck == 1)
        {
            echo "var interval = setInterval(\"tilechk()\",500);";
        }
        echo "
		//]]>\n
		</script>";
        return true;
    }

    /**
     * Erzeugt den JS Code im BODY der Seite.
     *
     * @return bool
     */
    function bodyjs()
    {
        echo "
		<script type=\"text/javascript\" src = \"../lib/gmapper/bodyjs.js\"></script>\n";
        return true;
    }

    /**
     * Erzeugt benötigten JS Code im Seitenfuss
     *
     * @return bool
     */
    function footjs()
    {
        echo "//]]>\n</script>";
        return true;
    }
    /**
     * Ende des Markerbereichs
     *
     * @return bool
     */
    function markend()
    {
        echo "//]]>\n</script>";
        return true;
    }
    /**
     * Start des Markerbereichs
     *
     * @return bool
     */
    function markstart()
    {
        echo "<script type=\"text/javascript\">\n
		//<![CDATA[\n";
        return true;
    }
    /**
     * Erzeugt einen einfachen Marker.
     *
     * @param float $lat Breitengrad des Markers
     * @param float $lng Längengrad des Markers
     * @param string $img Steuerung der Grafik
     * @return bool
     */
    function marker($lat, $lng, $img = "../lib/gmapper/143.png")
    {
        if ($lat and $lng)
        {
            $dims = getimagesize($img);
            echo "var image = new Array(\"$img\",$dims[0],$dims[1]);\n";
            echo "var point = new GLatLng(\"$lat\",\"$lng\");\n";
            echo "var marker = createMarker(point, image);\n";
            echo "var to = point;\n";
            echo "map.addOverlay(marker);\n";
            echo "\n";
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Erzeugt einen Marker mit Infopopup und verschiedenen Grafiken
     *
     * @param float $lat Breitengrad des Markers
     * @param float $lng Längengrad des Markers
     * @param string $html HTML Code für das Infopopup
     * @param string $img Steuerung der Grafik
     * @return bool
     */
    function otherMarker($lat, $lng, $html = "", $img = "../lib/gmapper/143.png")
    {
        $dims = getimagesize($img);
        if ($lat and $lng)
        {
            echo "var image = new Array(\"$img\",$dims[0],$dims[1]);\n";
            echo "var point = new GLatLng(\"$lat\",\"$lng\");\n";
            echo "var marker = createOtherMarker(point, '$html',image);\n	 ";

            echo "map.addOverlay(marker);\n";
            echo "\n";
            return true;
        }
        else
        {
            return false;
        }
    }
    /**
     * Erzeugt einen Marker mit mehreren Tabs im Infopopup
     *
     * @param float $lat Breitengrad des Markers
     * @param float $lng Längengrad des Markers
     * @param array $tabs Merhdimensionales Array mit den einzelnen Tabs
     * @param string $img Steuerung der Grafik
     * @return bool
     */
    function tabMarker($lat, $lng, $tabs, $img = "../lib/gmapper/143.png")
    {
        if ($lat and $lng)
        {
            $str = "";
            $dims = getimagesize($img);
            echo "var image = new Array(\"$img\",$dims[0],$dims[1]);\n";
            echo "var infoTabs = [\n";
            foreach($tabs as $tab)
            {
                $str .= "
  			new GInfoWindowTab(\"$tab[0]\", \"$tab[1]\"),\n";
            }
            $str = substr($str, 0, strlen($str)-2);
            echo $str;
            echo "\n];\n";
            echo "var point = new GLatLng(\"$lat\",\"$lng\");\n";
            echo "var marker = createTabMarker(point, infoTabs,image);\n";
            echo "map.addOverlay(marker);\n";
            echo "\n";
            return true;
        }
        else
        {
            return false;
        }
    }
    /**
     * Erzeugt einen Marker der auf den in $link angegeben URL verweist.
     *
     * @param float $lat Breitengrad des Markers
     * @param float $lng Längengrad des Markers
     * @param string $link Merhdimensionales Array mit den einzelnen Tabs
     * @param string $img Steuerung der Grafik
     * @return bool
     */
    function linkMarker($lat, $lng, $link, $img = "../lib/gmapper/143.png")
    {
        if ($lat and $lng)
        {
            $dims = getimagesize($img);
            echo "var image = new Array(\"$img\",$dims[0],$dims[1]);\n";
            echo "var point = new GLatLng(\"$lat\",\"$lng\");\n";
            echo "var marker = createLinkMarker(point, '$link',image);\n";
            echo "map.addOverlay(marker);\n";
            echo "\n";
            return true;
        }
        else
        {
            return false;
        }
    }
    /**
     * Erzeugt einen Marker dessen Infopopup eine Detailkarte enthält
     *
     * @param float $lat Breitengrad des Markers
     * @param float $lng Längengrad des Markers
     * @param string $img Steuerung der Grafik
     * @return bool
     */
    function mapMarker($lat, $lng, $img = "../lib/gmapper/143.png")
    {
        if ($lat and $lng)
        {
            $dims = getimagesize($img);
            echo "var image = new Array(\"$img\",$dims[0],$dims[1]);\n";
            echo "var point = new GLatLng(\"$lat\",\"$lng\");\n";
            echo "var marker = createMapMarker(point,image);\n";
            echo "map.addOverlay(marker);\n";
            echo "\n";
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * Zeichnet einen Kreis auf die Karte
     *
     * @param float $ lat Breitengrad des Zentrums
     * @param float $ lng Längengrad des Zentrums
     * @param int $ radius Radius des Kreises
     * @param string $ color Farbe der Kreislinie (Hexcode)
     * @param int $ line Strichstärke der Kreislinie
     * @param string $ img Wenn ein Bild übergeben wird, werden auf der Kreislinie Marker gesetzt.
     */
    function drawCircle($lat, $lng, $radius, $color = "#FF0000", $line = 1, $img = "", $shadow = "")
    {
        if ($lat and $lng)
        {
            $dims = getimagesize($img);
            if ($img)
            {
                echo "var image = new Array(\"$img\",$dims[0],$dims[1]);\n";
            }
            echo "var point = new GLatLng(\"$lat\",\"$lng\");\n";

            if ($img)
            {
                echo "var marker = drawCircle(point,$radius,\"$color\",$line,image,\"$shadow\");\n";
            }
            else
            {
                echo "var marker = drawCircle(point,$radius,\"$color\",$line,\"\",\"$shadow\");\n";
            }
            echo "\n";
            return true;
        }
        else
        {
            return false;
        }
    }
    /**
     * Erzeugt ein Geocoding Request und gibt eine Polyline, sowie Ortsliste zurück
     *
     * @param string $start Startort
     * @param string $end Endort
     * @param string $startmarker Markergrafik des Startorts
     * @param string $endmarker Markergrafik des Endorts
     * @param int $zoombounds Zoom und Center so setzen, dass sowohl Start als auch Zielort sichtbar sind.
     * @param string $textbox Name des HTML Elements in dem die Wegliste ausgegeben wird (Element ID)
     * @return bool
     */
    function directions($start, $html1 = "PHP-Resource User", $end, $html2 = "PHP-Resource User", $startmarker = "../lib/gmapper/143.png", $endmarker = "../lib/gmapper/143.png", $zoombounds = 0, $textbox = "")
    {
        $dims = getimagesize($startmarker);
        $dims2 = getimagesize($endmarker);

        $start = urlencode($start);
        $end = urlencode($end);
        echo "var dire = new GDirections();
		GEvent.addListener(dire, \"load\", gload);
		dire.load(\"from: $start to: $end\",{getPolyline:true,getSteps:true});
		function gload()
		{
		var distanz = dire.getDistance().meters;
		distanz = distanz / 1000;
		distanz = Math.round(distanz);
		var poly = dire.getPolyline();
		var bounds = dire.getBounds();
		var route = dire.getRoute(0);
		var step1 = route.getStep(0).getLatLng();
		var step_last = route.getEndLatLng();";
        if ($zoombounds)
        {
            echo "map.setCenter(bounds.getCenter(), map.getBoundsZoomLevel(bounds));";
        }
        echo "
		map.addOverlay(poly);
		var image = new Array(\"$startmarker\",\"$dims[0]\",\"$dims[1]\");
		var marker = createOtherMarker(step1, '$html1',image);
		map.addOverlay(marker);
		var image = new Array(\"$endmarker\",\"$dims2[0]\",\"$dims2[1]\");
		var marker = createOtherMarker(step_last, '$html2',image);
		map.addOverlay(marker);";
        if ($textbox != "")
        {
            echo "var steps = route.getNumSteps();
		document.getElementById('$textbox').innerHTML = '<b>Entfernung:</b> ' + distanz + ' Km<br>';
		/*for(i=0;i<=steps;i++)
		{
		mystep = route.getStep(i)
			if(mystep)
			{
			o = i + 1;
			shtml = mystep.getDescriptionHtml();
			document.getElementById('$textbox').innerHTML += o + '. ' + shtml + '<br>';
			}
		}*/";
        }
        echo "}";
        return true;
    }

    /**
     * Zeigt den Kartenmasstab an.
     *
     * @return bool
     */
    function showScale()
    {
        echo "map.addControl(new GScaleControl());\n";
        return true;
    }

    /**
     * Blendet den Button für den "Gelände" Kartentyp ein
     *
     * @return bool
     */
    function showPhysicalControl()
    {
        if ($this->maptyp != "physical")
        {
            echo "map.addMapType(G_PHYSICAL_MAP); ";
            return true;
        }
        else
        {
            return false;
        }
    }
    /**
     * Sperrt das Drag / Pan der Karte. Die Karte wird so auf den Viewport fixiert.
     *
     * @return bool
     */

    function lockMap()
    {
        echo "map.disableDragging(); map.hideControls();\n";
        return true;
    }
    /**
     * Erzeugt ein Bounds Objekt
     *
     * @return bool
     */
    function bounds()
    {
        echo "var bounds = new GLatLngBounds();\n";
        return true;
    }
    /**
     * Erweitert ein vorhandenes Bounds Objekt um den zuletzt hinzugefügten Punkt.
     *
     * @return bool
     */
    function boundsExtend()
    {
        echo "bounds.extend(point)\n";
        return true;
    }
    /**
     * Setzt den Kartenzoom "To bounds".
     *
     * @return bool
     */
    function zoomToBounds()
    {
        echo "map.setCenter(bounds.getCenter(), map.getBoundsZoomLevel(bounds));\n";
        return true;
    }

    /**
     * Erzeugt ein Streetview overlay. Das Overlay zeigt alle Gegengden für die Streetview verfügbar ist in blau an.
     *
     * @return bool ;
     */
    function streetviewOverlay()
    {
        echo "svOverlay = new GStreetviewOverlay();
        map.addOverlay(svOverlay);";
        return true;
    }

    /**
     * Erzeugt ein Streetview overlay. Das Overlay zeigt alle Gegengden für die Streetview verfügbar ist in blau an.
     * Wenn die Karte geklickt wird, wird Streetview im element mit der ID "pano" geöffnet
     *
     * @return bool ;
     */
    function clickStreetview()
    {

            echo "
		var thePano = new GStreetviewPanorama(document.getElementById(\"gmapper_pano\"));\n
        svOverlay = new GStreetviewOverlay();\n
        map.addOverlay(svOverlay);\n
        GEvent.addListener(map,\"click\", function(overlay,latlng) {\n
          thePano.setLocationAndPOV(latlng);\n
        });\n
        ";

    }

    function getStreetview($lat, $lng)
    {
        if (!empty($lat) and !empty($lng) )
        {
            echo "var latlng = new GLatLng($lat,$lng);\n
			  var thePano = new GStreetviewPanorama(document.getElementById(\"gmapper_pano\"));\n
			  thePano.setLocationAndPOV(latlng);\n
		";
            return true;
        }
        else
        {
            return false;
        }
    }
    function streetviewDiv($height, $width)
    {
        $height = $height . "px";
        $width = $width . "px";
        echo "<div id = \"gmapper_pano\" style=\"width: $width; height: $height;\"></div>";
    }
    /**
     * Erzeugt eine Linie zwischen mehreren Punkten
     *
     * @param array $punkte Erwartet ein Mehrdimensionales Array mit lat/lng der einzelnen Punkte
     * @param string $color Die Farbe der Linie als Hexcode
     * @param int $breite Die Breite der Linie
     * @param int $transparent Transparenz an / aus
     * @param string $arrows Richtungspfeile. "all" = überall, mid = "nur auf der Wegstrecke" , "end = "nur am "Ende"
     * @param int $geodetic Legt fest ob "geodäsische" Polylines gezeichnet werden. Diese berücksichtigen die Erdkrümmung.
     * @return bool
     */
    function polyline($punkte, $color = "#FF0000", $breite = 1, $transparent = 0.8, $arrows = "", $geodetic = 0)
    {
        $polystr = "";
        $pts = "[";

        ?>
	var polyline = new GPolyline([
  	<?php
        foreach($punkte as $punkt)
        {
            $pts .= "new GLatLng($punkt[0],$punkt[1]),";
            $polystr .= "new GLatLng($punkt[0],$punkt[1]),\n";
        }
        $polystr = substr($polystr, 0, strlen($polystr)-2);
        $pts = substr($pts, 0, strlen($pts)-1);
        $pts .= "]";

        echo "
	$polystr],'$color',$breite,$transparent";
        if ($geodetic == 1)
        {
            echo ",{geodesic:true}";
        }
        echo ");\n
	map.addOverlay(polyline);\n";
        if ($arrows == "end")
        {
            echo "arrowHead($pts);\n";
        } elseif ($arrows == "mid")
        {
            echo "midArrows($pts);\n";
        } elseif ($arrows == "all")
        {
            echo "arrowHead($pts);\n
	midArrows($pts);\n";
        }

        return true;
    }

    /**
     * Ermittelt die Länge einer Polyline in Metern.
     *
     * @param array $punkte das Array aus dem die Polyline erstellt wurde.
     * @return int $entfernung
     */
    function getDistance($koord)
    {
        if (!is_array($koord))
        {
            return false;
        }

        $ent = 0;
        $welt = 6378.137; // Erdradius, ca. Angabe

        foreach($koord as $key => $fetch)
        {
            if (isset($koord[$key + 1]))
            {
                $erste_breite = $koord[$key][0]; // lat
                $erste_laenge = $koord[$key][1]; // lon
                $erste_breite_rad = deg2rad($erste_breite);
                $erste_laenge_rad = deg2rad($erste_laenge);

                $zweite_breite = $koord[$key + 1][0]; // lat
                $zweite_laenge = $koord[$key + 1][1]; // lon
                $zweite_breite_rad = deg2rad($zweite_breite);
                $zweite_laenge_rad = deg2rad($zweite_laenge);

                $dis = acos(
                    (sin($erste_breite_rad) * sin($zweite_breite_rad)) +
                    (cos($erste_breite_rad) * cos($zweite_breite_rad) *
                        cos($zweite_laenge_rad - $erste_laenge_rad))) * $welt;

                $ent = $ent + $dis;
            }
        }
        $entfernung = $ent * 1000;
        return round($entfernung, 0);
    }

    /**
     * Nimmt eine Adresse entgegen und erzeugt einen Marker.
     *
     * @param string $adress Zu suchende Adresse
     * @param string $typ legt den Markertyp fest (marker,otherMarker,mapMarker)
     * @param string $img legt die Markergrafik fest
     * @param int $zoom legt den Kartenzoom fest (Default: 14)
     * @return array
     */
    function geocode($adress, $typ = 'marker', $img = "../lib/gmapper/143.png", $zoom = 14)
    {
        if ($adress)
        {
            $dims = getimagesize($img);
            echo "var image = new Array('$img',$dims[0],$dims[1]);\n";
            if ($adress == "getAdress()")
            {
                echo "geocodeAddress($adress,'$typ',image,$zoom);\n";
            }
            else
            {
                echo "geocodeAddress('$adress','$typ',image,$zoom);\n";
            }
            return true;
        }
        else
        {
            return false;
        }
    }
    /**
     * Führt ein Geocoding Request mit dem Google Geocoder aus.
     * Die Anfrage sollte so aufgebaut sein: <strasse> <plz> <stadt> <land>
     *
     * @param string $input Geocoding Anfrage
     * @param string $key Google Maps API Key (nur für Rückwärtskompatibilität)
     * @return array $point
     */
    function getGeoPoint($input, $key = "")
    {
        if (!$key)
        {
            $key = $this->key;
        }
        $input = urlencode($input);
        $address = "http://maps.google.com/maps/geo?q=$input&key=$key&output=csv";
        $point = file_get_contents($address);
        $point = explode(",", $point);
        $point = array($point[2], $point[3]);
        if (!empty($point))
        {
            return $point;
        }
        else
        {
            return false;
        }
    }
    /**
     * Parsed KML Dateien und gibt sie auf der Karte aus
     *
     * @param string $url Zu parsende Datei (erwartet immer eine URL !)
     * @return bool
     */
    function parseKml($url)
    {
        if ($url)
        {
            echo "var geoXml = new GGeoXml(\"$url\");\n
		map.addOverlay(geoXml);\n";
            return true;
        }
        else
        {
            return true;
        }
    }

    /**
     * Parsed XML Dateien und gibt Geopunkte als Array zurück
     *
     * @param string $file Zu parsende Datei
     * @return array
     */
    function parseGxml($file)
    {
        $parser = new XMLParser($file, 'file', 1);
        $tree = $parser->getTree();
        $points = array();
        foreach($tree[MARKERS][MARKER] as $marker)
        {
            $lat = $marker[ATTRIBUTES][LAT];
            $lng = $marker[ATTRIBUTES][LNG];
            $arr = array($lat, $lng);
            array_push($points, $arr);
        }
        if (!empty($points))
        {
            return $points;
        }
        else
        {
            return false;
        }
    }
    /**
     * Parsed XML Dateien und gibt Marker als Array zurück.
     *
     * @param string $file Zu parsende Datei
     * @return array
     */
    function xmlMapPoints($file)
    {
        $parser = new XMLParser($file, 'file', 1);
        $tree = $parser->getTree();
        $points = array();
        foreach($tree[MAP][MARKERS][MARKER] as $marker)
        {
            $lat = $marker[ATTRIBUTES][LAT];
            $lng = $marker[ATTRIBUTES][LNG];
            $img = $marker[ATTRIBUTES][IMAGE];
            $link = $marker[ATTRIBUTES][LINK];
            $arr = array($lat, $lng, $link, $img);
            array_push($points, $arr);
        }
        return $points;
    }

    /**
     * Erzeugt die Kartendarstellung aus einer XML Datei
     *
     * @param string $file XML Datei
     * @return bool
     */
    function xmlMap($file)
    {
        $parser = new XMLParser($file, 'file', 1);
        $tree = $parser->getTree();
        $lat = $tree[MAP][STARTLAT][VALUE];
        $lng = $tree[MAP][STARTLONG][VALUE];
        $width = $tree[MAP][WIDTH][VALUE];
        $height = $tree[MAP][HEIGHT][VALUE];
        $min_zoom = $tree[MAP][MINZOOM][VALUE];
        $typ = $tree[MAP][MAPTYPE][VALUE];
        $zoom = $tree[MAP][STARTZOOM][VALUE];
        if (!$min_zoom)
        {
            $min_zoom = 1;
        }
        $max_zoom = $tree[MAP][MAXZOOM][VALUE];
        if (!$max_zoom)
        {
            $max_zoom = 17;
        }
        $navcontrol = $tree[MAP][NAVCONTROL][VALUE];

        $width = $width . "px";
        $height = $height . "px";

        if ($typ == "normal")
        {
            $typstr = "G_NORMAL_MAP";
        } elseif ($typ == "satellit")
        {
            $typstr = "G_SATELLITE_MAP";
        } elseif ($typ == "hybrid")
        {
            $typstr = "G_HYBRID_MAP";
        }
        if ($navcontrol == "large")
        {
            $controlstr = "GLargeMapControl()";
        } elseif ($navcontrol == "small")
        {
            $controlstr = "GSmallMapControl()";
        }
        echo "
		<script type=\"text/javascript\">

		var map = new GMap2(document.getElementById(\"gmapper_map\"));\n
		map.addControl(new $controlstr);\n
		map.addControl(new GMapTypeControl());\n
		map.enableDoubleClickZoom();\n
		map.enableContinuousZoom();\n
		var mt = map.getMapTypes();\n
		      for (var i=0; i<mt.length; i++) {
		        mt[i].getMinimumResolution = function() {return $min_zoom;}
		        mt[i].getMaximumResolution = function() {return $max_zoom;}
		      }
		map.addControl(new GMapTypeControl());\n
		map.setCenter(new GLatLng( $lat,$lng),$zoom);\n

		map.setMapType($typstr);\n
		</script>";
        return true;
    }
    // Ende Gmapper
}
// XML Parser Code
class XMLParser
{
    var $data;
    var $vals;
    var $collapse_dups;
    var $index_numeric;
    function XMLParser($data_source, $data_source_type = 'file', $collapse_dups = 0, $index_numeric = 0)
    {
        $this->collapse_dups = $collapse_dups;
        $this->index_numeric = $index_numeric;
        $this->data = '';
        if (file_exists($data_source))
            $this->data = implode('', file($data_source));
        else
        {
            $fp = fopen($data_source, 'r');
            while (!feof($fp))
            $this->data .= fread($fp, 1000);
            fclose($fp);
        }
    }
    function getTree()
    {
        $parser = xml_parser_create('ISO-8859-1');
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $this->data, $vals, $index);
        xml_parser_free($parser);
        $i = -1;
        return $this->getchildren($vals, $i);
    }
    function buildtag($thisvals, $vals, &$i, $type)
    {
        if (isset($thisvals['attributes']))
            $tag['ATTRIBUTES'] = $thisvals['attributes'];
        if ($type === 'complete')
            $tag['VALUE'] = $thisvals['value'];
        else
            $tag = array_merge($tag, $this->getchildren($vals, $i));

        return $tag;
    }
    function getchildren($vals, &$i)
    {
        $children = array(); // Contains node data
        if ($i > -1 && isset($vals[$i]['value']))
            $children['VALUE'] = $vals[$i]['value'];
        while (++$i < count($vals))
        {
            $type = $vals[$i]['type'];
            if ($type === 'cdata')
                $children['VALUE'] .= $vals[$i]['value'];
            elseif ($type === 'complete' || $type === 'open')
            {
                $tag = $this->buildtag($vals[$i], $vals, $i, $type);
                if ($this->index_numeric)
                {
                    $tag['TAG'] = $vals[$i]['tag'];
                    $children[] = $tag;
                }
                else
                    $children[$vals[$i]['tag']][] = $tag;
            } elseif ($type === 'close')
                break;
        }
        if ($this->collapse_dups)
            foreach($children as $key => $value)
            if (is_array($value) && (count($value) == 1))
                $children[$key] = $value[0];
            return $children;
        }
    }

    ?>