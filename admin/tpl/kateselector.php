<?php
/* ###VERSIONSBLOCKINLCUDE### */



 include "sys/lib.baum.php";
 
 $baum = new baum('script');
 $baum->readTree();
 
 echo ht(dump($baum->ar_tree_all));

?>