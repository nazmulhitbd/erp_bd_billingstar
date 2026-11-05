<?php
if(function_exists('audit_finish'))audit_finish(); // before the database closes
if(isset($link)) {
    stopmysqlquery($dbuser,$processtime);
    if($link)mysql_close($link);unset($db);unset($link);
}
flush();
if(ob_get_level()>0)ob_end_flush();
?>