<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (isset($_REQUEST["nav_id"])) {
    if (isset($_REQUEST["B_SEARCH"])) {
        // Update database
        $db->querynow("UPDATE nav SET B_SEARCH=" . $_REQUEST["B_SEARCH"] . " WHERE ID_NAV=" . $_REQUEST["nav_id"]);
        // Update cache
        include("../cache/nav" . $_REQUEST["nav_root"] . "." . $s_lang . ".php");
        $ar_nav[$_REQUEST["nav_id"]]['B_SEARCH'] = $_REQUEST["B_SEARCH"];
        $s_code = "<?php \$ar_nav = " . php_dump($ar_nav) . "; ?>";
        file_put_contents($file_name = $ab_path . "cache/nav" . $_REQUEST["nav_root"] . "." . $s_lang . ".php", $s_code);
        chmod($file_name, 0777);
        $_SESSION['navedit' . $_REQUEST["nav_root"] . $s_lang] = '';

        include "sys/lib.search.php";
        $search = new do_search($s_lang);
        $row = $ar_nav[$_REQUEST["nav_id"]];
        if ($_REQUEST["B_SEARCH"] == 1) {
            if (file_exists(CacheTemplate::getHeadFile("tpl/de/" . $row['IDENT'] . ".htm"))) {
                $content = file_get_contents(CacheTemplate::getHeadFile("tpl/" . $s_lang . "/" . $row['IDENT'] . ".htm"));
                #echo stdHtmlentities($content);
                #die();
                $search->add_new_text($content . $row['V1'], $_REQUEST["nav_id"], 'nav');
            }
        } else {
            $search->delete_from_searchindex($_REQUEST["nav_id"], 'nav');
        }
        die();
    }
    if (isset($_REQUEST["B_SSL"])) {
        // Update database
        $db->querynow("UPDATE nav SET B_SSL=" . $_REQUEST["B_SSL"] . " WHERE ID_NAV=" . $_REQUEST["nav_id"]);
        // Update cache
        include("../cache/nav" . $_REQUEST["nav_root"] . "." . $s_lang . ".php");
        $ar_nav[$_REQUEST["nav_id"]]['B_SSL'] = $_REQUEST["B_SSL"];
        $s_code = "<?php \$ar_nav = " . php_dump($ar_nav) . "; ?>";
        file_put_contents($file_name = $ab_path . "cache/nav" . $_REQUEST["nav_root"] . "." . $s_lang . ".php", $s_code);
        chmod($file_name, 0777);
        $_SESSION['navedit' . $_REQUEST["nav_root"] . $s_lang] = '';

        header('Content-type: application/json');
        die(json_encode(array(
            "query"     => ("UPDATE nav SET B_SSL=" . $_REQUEST["B_SSL"] . " WHERE ID_NAV=" . $_REQUEST["nav_id"]),
            "success"   => true
        )));
    }
}
?>