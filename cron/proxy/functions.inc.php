<?php
/* ###VERSIONSBLOCKINLCUDE### */



function dbconnect() {

	$dbname = "usr_web1_1";
	mysql_connect("localhost","web1","kmzr4FNf");
	mysql_select_db($dbname);

}

function request($url, $snippet, $nogo = '') {

    while (1) {
        $thread = md5(uniqid(rand(), true));
        $cookie = dirname(__FILE__).'/cookie/'.$thread.'.cookie';

        $q1  = "
                UPDATE
                    proxies
                SET
                    status='2',
                    thread='".$thread."'
                WHERE
                    status='1'
                ORDER BY
                    lastupdated ASC
                LIMIT
                    1
                ";
        $exec1 = mysql_query($q1) or die(mysql_error());

        $q1  = "
                SELECT
                    id, action, vars, method, connecttime, connectcount
                FROM
                    proxies
                WHERE
                    thread='".$thread."'
                LIMIT
                    1
                ";
        $exec1 = mysql_query($q1) or die(mysql_error());
        $dbvar = mysql_fetch_assoc($exec1);
        mysql_free_result($exec1);

        $vars = str_replace('##URL##', urlencode($url), $dbvar['vars']);
        $referer = 'http://'.parse_url($dbvar['action'], PHP_URL_HOST).'/';

        $ch = curl_init();
        if ($dbvar['method'] == 'post') {
            curl_setopt($ch, CURLOPT_URL, $dbvar['action']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        } else {
            $method = 'get';
            curl_setopt($ch, CURLOPT_URL, $dbvar['action'].'?'.$vars);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6');
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        $ret = curl_exec($ch);
        $err = curl_errno($ch);
        $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        rm($cookie);

        if ($err > 0) {
            updateproxy($dbvar['id'], 90);
            continue;
        }

        $newcount = $dbvar['connectcount'] + 1;
        $newtime = ($dbvar['connecttime'] * $dbvar['connectcount'] + $time) / $newcount;

        if (strpos($ret, $snippet)) {
            if ($nogo && strpos($ret, $nogo)) {
                updateproxy($dbvar['id'], 90);
                continue;
            } else {
                updateproxy($dbvar['id'], 1, $dbvar['action'], $dbvar['vars'], $dbvar['method'], $newtime, $newcount);
                $html = $ret;
                break;
            }
        } else {
            $pattern = "|document.write\s*\(\s*unescape\s*\(\s*'([^']+)'\s*\)|";
            if (preg_match($pattern,$ret,$preg)) {
                $ret = urldecode($preg[1]);
                if (strpos($html, $snippet)) {
                    if ($nogo && strpos($ret, $nogo)) {
                        updateproxy($dbvar['id'], 90);
                        continue;
                    } else {
                        updateproxy($dbvar['id'], 1, $dbvar['action'], $dbvar['vars'], $dbvar['method'], $newtime, $newcount);
                        $html = $ret;
                        break;
                    }
                } else {
                    updateproxy($dbvar['id'], 90);
                    continue;
                }
            } else {
                updateproxy($dbvar['id'], 90);
                continue;
            }
        }

    }

    return $html;
}

function updateproxy($id, $status, $action = '', $vars = '', $method = '', $time = '', $count = '', $cycles = '') {

    $set = '';
    if ($action) $set .= "action='".mysql_real_escape_string($action)."', ";
    if ($vars) $set .= "vars='".mysql_real_escape_string($vars)."', ";
    if ($method) $set .= "method='".mysql_real_escape_string($method)."', ";
    if ($time) $set .= "connecttime='".$time."', ";
    if ($count) $set .= "connectcount='".$count."', ";
    if ($cycles) $set .= "cycles='".$cycles."', ";

    $q2  = "
            UPDATE
                proxies
            SET
                ".$set."
                status='".$status."',
                thread=''
            WHERE
                id='".$id."'
            ";
    echo $q2."<br>\n";
    $exec2 = mysql_query($q2) or die(mysql_error());
}

function stripforms($document) {
	preg_match_all("'<\/?(FORM|INPUT|SELECT|TEXTAREA|(OPTION))[^<>]*>(?(2)(.*(?=<\/?(option|select)[^<>]*>[\r\n]*)|(?=[\r\n]*))|(?=[\r\n]*))'Usi",$document,$elements);

	// catenate the matches
	$match = implode("\r\n",$elements[0]);

	// return the forms
	return $match;
}


function expandlinks($links,$URI) {

	preg_match("/^[^\?]+/",$URI,$match);

	$match = preg_replace("|/[^\/\.]+\.[^\/\.]+$|","",$match[0]);
	$match = preg_replace("|/$|","",$match);
	$match_part = parse_url($match);
	$match_root =
	$match_part["scheme"]."://".$match_part["host"];
	$host = parse_url($URI, PHP_URL_HOST);

	$search = array( 	"|^http://".preg_quote($host)."|i",
						"|^(\/)|i",
						"|^(?!http://)(?!https://)(?!mailto:)|i",
						"|/\./|",
						"|/[^\/]+/\.\./|"
					);

	$replace = array(	"",
						$match_root."/",
						$match."/",
						"/",
						"/"
					);

	$expandedLinks = preg_replace($search,$replace,$links);

	return $expandedLinks;
}

function rm($file) {
    if (file_exists($file)) unlink($file);
}

/* #muß nicht benutzt wrden 
function curl_redir_exec($ch)
{
    static $curl_loops = 0;
    static $curl_max_loops = 20;
    if ($curl_loops++ >= $curl_max_loops)
    {
        $curl_loops = 0;
        return FALSE;
    }
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    list($header, $data) = explode("\n\n", $data, 2);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code == 301 || $http_code == 302)
    {
        $matches = array();
        preg_match('/Location:(.*?)\n/', $header, $matches);
        $url = @parse_url(trim(array_pop($matches)));
        if (!$url)
        {
            //couldn't process the url to redirect to
            $curl_loops = 0;
            return $data;
        }
        $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
        if (!$url['scheme'])
            $url['scheme'] = $last_url['scheme'];
        if (!$url['host'])
            $url['host'] = $last_url['host'];
        if (!$url['path'])
            $url['path'] = $last_url['path'];
        $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
        curl_setopt($ch, CURLOPT_URL, $new_url);
        echo 'Redirecting to '. $new_url."\n";
        return curl_redir_exec($ch);
    } else {
        $curl_loops=0;
        return $data;
    }
}
*/

?>
