<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 * Proxy Klasse
 *
 */
class proxy {
	
	/**
	 * Flag das der Proxy fehler nicht gespeichert wird
	 *
	 * @var obj
	 */
	var $noupdateproxy = "";
	
	function proxy() {
		mysql_connect ( "localhost", "web1", "kmzr4FNf" );
		mysql_select_db ( "usr_web1_1" );
	}
	
	/**
	 * Funktion zum holen einer Webseite
	 *
	 * @param string $url
	 * @param string $snippet
	 * @param string $nogo
	 * @return string
	 */
	
	function request($url, $snippet, $nogo = '') {
		#echo "\n\n".$url."\n\n";
		$negativ = 0;
		while ( 1 ) {
			if ($negativ >= 3) {
				return "";
			}
			$thread = md5 ( uniqid ( rand (), true ) );
			$cookie = dirname ( __FILE__ ) . '/cookie/' . $thread . '.cookie';
			
			$q1 = "
	                UPDATE
	                    proxies
	                SET
	                    status='2',
	                    thread='" . $thread . "'
	                WHERE
	                    status='1'
	                ORDER BY
	                    lastupdated ASC
	                LIMIT
	                    1
	                ";
			
			$exec1 = mysql_query ( $q1 ) or die ( mysql_error () );
			
			$q1 = "    SELECT id,proxy,action,vars,method,connecttime,connectcount FROM proxies WHERE thread='" . $thread . "' LIMIT 1";
			
			#echo $q1."\n";
			$exec1 = mysql_query ( $q1 ) or die ( mysql_error () );
			$dbvar = mysql_fetch_assoc ( $exec1 );
			#echo " Proxy: $dbvar[proxy] :";
			#echo "<pre>".print_r($dbvar,TRUE)."</pre>";
			mysql_free_result ( $exec1 );
			
			$vars = str_replace ( '##URL##', urlencode ( $url ), $dbvar ['vars'] );
			$referer = 'http://' . parse_url ( $dbvar ['action'], PHP_URL_HOST ) . '/';
			
			$ch = curl_init ();
			if ($dbvar ['method'] == 'post') {
				curl_setopt ( $ch, CURLOPT_URL, $dbvar ['action'] );
				curl_setopt ( $ch, CURLOPT_POST, 1 );
				curl_setopt ( $ch, CURLOPT_POSTFIELDS, $vars );
			
		#echo "POSTVARS: ".$vars."\n";
			} else {
				$method = 'get';
				curl_setopt ( $ch, CURLOPT_URL, $dbvar ['action'] . '?' . $vars );
			
		#echo "GET: ".$dbvar['action'].'?'.$vars."\n";
			}
			curl_setopt ( $ch, CURLOPT_FRESH_CONNECT, 1 );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
			curl_setopt ( $ch, CURLOPT_MAXREDIRS, 3 );
			curl_setopt ( $ch, CURLOPT_TIMEOUT, 15 );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6' );
			curl_setopt ( $ch, CURLOPT_REFERER, $referer );
			curl_setopt ( $ch, CURLOPT_COOKIEFILE, $cookie );
			curl_setopt ( $ch, CURLOPT_COOKIEJAR, $cookie );
			$ret = curl_exec ( $ch );
			$err = curl_errno ( $ch );
			$time = curl_getinfo ( $ch, CURLINFO_TOTAL_TIME );
			curl_close ( $ch );
			$this->rm ( $cookie );
			
			if ($err > 0) {
				$this->updateproxy ( $dbvar ['id'], 90 );
				continue;
			}
			$newcount = $dbvar ['connectcount'] + 1;
			$newtime = ($dbvar ['connecttime'] * $dbvar ['connectcount'] + $time) / $newcount;
			if (strpos ( $ret, $snippet ) || preg_match( "/".$snippet."/i", $ret )) {
				if ($nogo && strpos ( $ret, $nogo )) {
					$this->updateproxy ( $dbvar ['id'], 90 );
					$negativ ++;
					
					#echo "nogo ohne java nicht gefunden\n";
					#echo $ret."\n";
					#echo "nogo ohne java nicht gefunden\n";
					

					continue;
				} else {
					$this->updateproxy ( $dbvar ['id'], 1, $dbvar ['action'], $dbvar ['vars'], $dbvar ['method'], $newtime, $newcount );
					$html = $ret;
					#echo "gefunden\n";
					break;
				}
			} else {
				
				$pattern = "|document.write\s*\(\s*unescape\s*\(\s*'([^']+)'\s*\)|";
				if (preg_match ( $pattern, $ret, $preg )) {
					$ret = urldecode ( $preg [1] );
					if (strpos ( $html, $snippet )) {
						if ($nogo && strpos ( $ret, $nogo )) {
							$this->updateproxy ( $dbvar ['id'], 90 );
							$negativ ++;
							
							#echo "nogo nicht gefunden\n";
							#echo $ret."\n";
							#echo "nogo nicht gefunden\n";
							

							continue;
						} else {
							$this->updateproxy ( $dbvar ['id'], 1, $dbvar ['action'], $dbvar ['vars'], $dbvar ['method'], $newtime, $newcount );
							$html = $ret;
							#echo "gefunden\n";
							break;
						}
					} else {
						$this->updateproxy ( $dbvar ['id'], 90 );
						$negativ ++;
						#echo " strposjava nicht gefunden\n";
						#echo $ret."\n";
						#echo "strposjava nicht gefunden\n";
						continue;
					}
				} else {
					$this->updateproxy ( $dbvar ['id'], 90 );
					$negativ ++;
					#echo "ohne java nicht gefunden\n";
					#echo $ret."\n";
					#echo "ohne java nicht gefunden\n";
					continue;
				}
			}
		}
		return $html;
	}
	
	/**
	 * Funktion zum update des Proxy Status
	 *
	 * @param int $id
	 * @param int $status
	 * @param string $action
	 * @param string $vars
	 * @param string $method
	 * @param string $time
	 * @param string $count
	 * @param string $cycles
	 */
	function updateproxy($id, $status, $action = '', $vars = '', $method = '', $time = '', $count = '', $cycles = '') {
		$set = '';
		if ($action)
			$set .= "action='" . mysql_real_escape_string ( $action ) . "', ";
		if ($vars)
			$set .= "vars='" . mysql_real_escape_string ( $vars ) . "', ";
		if ($method)
			$set .= "method='" . mysql_real_escape_string ( $method ) . "', ";
		if ($time)
			$set .= "connecttime='" . $time . "', ";
		if ($count)
			$set .= "connectcount='" . $count . "', ";
		if ($cycles)
			$set .= "cycles='" . $cycles . "', ";
		$q2 = "
	            UPDATE
	                proxies
	            SET
	                " . $set . "
	                status='" . $status . "',
	                thread=''
	            WHERE
	                id='" . $id . "'
	            ";
		if ($this->noupdateproxy == "1") {
		
		} else {
			$exec2 = mysql_query ( $q2 ) or die ( mysql_error () );
		}
	}
	
	/**
	 * Funktion zum herausl?sen von Forumlar Tags
	 *
	 * @param string $document
	 * @return string
	 */
	function stripforms($document) {
		preg_match_all ( "'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi", $document, $elements );
		// catenate the matches
		$match = implode ( "\r\n", $elements [0] );
		// return the forms
		return $match;
	}
	
	/**
	 * Funktion zur suche nach Links
	 *
	 * @param array $links
	 * @param string $URI
	 * @return array
	 */
	function expandlinks($links, $URI) {
		preg_match ( "/^[^\?]+/", $URI, $match );
		$match = preg_replace ( "|/[^\/\.]+\.[^\/\.]+$|", "", $match [0] );
		$match = preg_replace ( "|/$|", "", $match );
		$match_part = parse_url ( $match );
		$match_root = $match_part ["scheme"] . "://" . $match_part ["host"];
		$host = parse_url ( $URI, PHP_URL_HOST );
		$search = array ("|^http://" . preg_quote ( $host ) . "|i", "|^(\/)|i", "|^(?!http://)(?!https://)(?!mailto:)|i", "|/\./|", "|/[^\/]+/\.\./|" );
		$replace = array ("", $match_root . "/", $match . "/", "/", "/" );
		$expandedLinks = preg_replace ( $search, $replace, $links );
		return $expandedLinks;
	}
	
	/**
	 * Funktion zum l?schen von DAteien
	 *
	 * @param string $file
	 */
	function rm($file) {
		if (file_exists ( $file ))
			unlink ( $file );
	}
}

?>