<pre>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


# Funktion Zum holen der Proxy Urls aus der Seite http://proxy.org/cgi_proxies.shtml

set_time_limit(0);

include_once('functions.inc.php');

dbconnect();

$url = 'http://proxy.org/cgi_proxies.shtml';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.6) Gecko/20060728 Firefox/1.5.0.6');
$ret = curl_exec($ch);
curl_close($ch);
$pattern = '/<option\s+((style|class)="[^"]*"\s+)?value="([^"]+)">[^&]*&nbsp;&nbsp;/';

if (preg_match_all($pattern, $ret, $array, PREG_SET_ORDER)) {

    foreach ($array AS $val) {

        $q  = "
                SELECT
                    id
                FROM
                    proxies
                WHERE
                    proxy = '".$val[3]."'
                ";
        $exec = mysql_query($q) or die(mysql_error());
        $num = mysql_num_rows($exec);

        if ($num == 0) {

            $q  = "
                    INSERT INTO
                        proxies
                        ( `proxy`,
                          `created`
                        )
                    VALUES
                        ('".$val[3]."',
                         NOW()
                         )
                    ";
			
            $exec = mysql_query($q) or die(mysql_error());

        }

    }

}


?>
