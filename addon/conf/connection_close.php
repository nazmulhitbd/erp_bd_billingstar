<?php
if(function_exists('audit_finish'))audit_finish(); // before the database closes
// The page's output filter (language, country wording) still reads the database, so it runs before the database closes.
flush();
if(ob_get_level()>0)ob_end_flush();
if(isset($link)) {
    stopmysqlquery($dbuser,$processtime);
    if($link)mysql_close($link);unset($db);unset($link);
}
?>