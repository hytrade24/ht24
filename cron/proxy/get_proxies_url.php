<?php
/* ###VERSIONSBLOCKINLCUDE### */


#phpinfo();
#die("geht");
set_time_limit(0);
include_once('functions.inc.php');

$maxcycles = 10;
#echo ini_get("open_basedir");
#echo "<br>";
#echo ini_set('open_basedir',NULL);
#echo "<br>";
#echo ini_get("open_basedir");
#echo "<br>";
#exit;
dbconnect();

$q  = "
        SELECT
            id, proxy, status, cycles
        FROM
            proxies
        WHERE
            status='0'
            OR status='90' order by id ASC
        ";

#$q .= " limit 0,1";
$exec = mysql_query($q) or die(mysql_error());

while ($dbvar = mysql_fetch_assoc($exec)) {
	echo "<pre>".print_r($dbvar,TRUE)."</pre>";
    $cookie = dirname(__FILE__).'/cookie/'.md5(uniqid(rand(), true)).'.cookie';
    $url = 'http://www.'.$dbvar['proxy'].'/';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6');
    $ret = curl_exec($ch);
    #$ret = curl_redir_exec($ch);
    $err = curl_errno($ch);
    $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $URI = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    rm($cookie);

    if ($err > 0) {
        updateproxy($dbvar['id'], 99);
        continue;
    }

    $forms = stripforms($ret);

    $pattern = "|document.write\s*\(\s*unescape\s*\(\s*'([^']+)'\s*\)|";
    if (!$forms && preg_match($pattern,$ret,$preg0)) {
        $ret = urldecode($preg0[1]);
        $forms = stripforms($ret);
    }

    $host = parse_url($URI, PHP_URL_HOST);
    $referer = 'http://'.$host.'/';
    unset($action);
    unset($method);
    unset($vars);
    unset($texti);

    preg_match_all("'(<\s*form\s.*<\s*/\s*form\s*>)'Uisx",$forms,$preg1);

    foreach ($preg1[0] AS $val) {

        unset($action);
        unset($method);
        unset($vars);
        unset($texti);

        $pattern = "'<\s*form\s.*?action\s*=\s*			# find <form action=
        				([\"\'])?					# find single or double quote
        				(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
        											# quote, otherwise match up to next space
        				'isx";
        if (preg_match($pattern,$val,$preg2)) {
            $action = ($preg2[2]) ? $preg2[2] : $preg2[3];
            $action = expandlinks($action,$URI);

            if (!stripos($action, $host) && !stripos($action, $dbvar['proxy'])) continue;

            $pattern = "'<\s*form\s.*?method\s*=\s*			# find <form method=
            				([\"\'])?					# find single or double quote
            				(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
            											# quote, otherwise match up to next space
            				'isx";
            preg_match($pattern,$val,$preg3);
            $method = ($preg3[2]) ? strtolower($preg3[2]) : strtolower($preg3[3]);
            $pattern = "'<\s*input[^>]+>'isx";
            preg_match_all($pattern, $val, $preg4);

            $texti = 0;
            $vars = '';
            foreach ($preg4[0] AS $input) {

                $pattern = "'<\s*input\s.*?type\s*=\s*			# find <input type=
                				([\"\'])?					# find single or double quote
                				(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
                											# quote, otherwise match up to next space
                				'isx";
                preg_match($pattern,$input,$preg5);
                $type = ($preg5[2]) ? strtolower($preg5[2]) : strtolower($preg5[3]);

                $pattern = "'<\s*input\s.*?name\s*=\s*			# find <input name=
                				([\"\'])?					# find single or double quote
                				(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
                											# quote, otherwise match up to next space
                				'isx";
                preg_match($pattern,$input,$preg6);
                $name = ($preg6[2]) ? urlencode($preg6[2]) : urlencode($preg6[3]);

                if (($type == 'text' || $type == '') && $name) {
                    if ($texti == 1) {
                        $texti = 0;
                        continue 2;
                    }
                    $texti = 1;
                    $vars .= $name.'=##URL##&';
                } elseif ($type == 'checkbox' && $name) {
                    if (stripos($input, 'checked')) {
                        $vars .= $name.'=on&';
                    }
                } elseif ($type == 'password') {
                    continue 2;
                } else {
                    $pattern = "'<\s*input\s.*?value\s*=\s*			# find <input value=
                    				([\"\'])?					# find single or double quote
                    				(?(1) (.*?)\\1 | ([^\s\>]+))		# if quote found, match up to next matching
                    											# quote, otherwise match up to next space
                    				'isx";
                    preg_match($pattern,$input,$preg7);
                    $value = ($preg7[2]) ? urlencode($preg7[2]) : urlencode($preg7[3]);
                    if ($name) $vars .= $name.'='.$value.'&';
                }
            }

        } else {
            continue;
        }

        if ($texti == 1) break;
    }

    if ($texti == 1) {
        $cookie = dirname(__FILE__).'/cookie/'.md5(uniqid(rand(), true)).'.cookie';
        $testvars = str_replace('##URL##', urlencode('http://www.google.de/'), $vars);

        $ch = curl_init();
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_URL, $action);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $testvars);
        } else {
            $method = 'get';
            curl_setopt($ch, CURLOPT_URL, $action.'?'.$testvars);
        }
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6');
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        $ret2 = curl_exec($ch);
        #$ret2 = curl_redir_exec($ch);
        $err2 = curl_errno($ch);
        $time2 = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        rm($cookie);

        if ($err2 > 0) {
            updateproxy($dbvar['id'], 99);
            continue;
        }
		if(preg_match("/google/i",$action)){
            updateproxy($dbvar['id'], 99);
            continue;
		}
        if (strpos($ret2, '<label for=lgr> Seiten auf Deutsch </label>')) {
            if ($dbvar['status'] == 90) ++$dbvar['cycles'];

            if  ($dbvar['cycles'] >= $maxcycles) {
                updateproxy($dbvar['id'], 99);
            } else {

                updateproxy($dbvar['id'], 1, $action, $vars, $method, $time2, 1, $dbvar['cycles']);
            }
        } else {
            $pattern = "|document.write\s*\(\s*unescape\s*\(\s*'([^']+)'\s*\)|";
            if (preg_match($pattern,$ret2,$preg8)) {
                $html = urldecode($preg8[1]);
                if (strpos($html, '<label for=lgr> Seiten auf Deutsch </label>')) {
                    if ($dbvar['status'] == 90) ++$dbvar['cycles'];
                    if  ($dbvar['cycles'] >= $maxcycles) {
                        updateproxy($dbvar['id'], 99);
                    } else {
                        updateproxy($dbvar['id'], 1, $action, $vars, $method, $time2, 1, $dbvar['cycles']);
                    }
                } else {
                    updateproxy($dbvar['id'], 99);
                }
            } else {
                updateproxy($dbvar['id'], 99);
            }
        }

    } else {
        updateproxy($dbvar['id'], 99);
    }

}

$exec = mysql_query("UPDATE `proxies` SET STATUS = '1' WHERE STATUS = '2' ") or die(mysql_error());

?>
