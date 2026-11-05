<?php
require_once dirname(__DIR__) . '/inc/security.php';
// Faults go to the server's error log. On screen they would show visitors file paths and SQL.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
include_once(dirname(__FILE__) . '/../inc/utility.php');

if(!isset($link)) {
    require_once dirname(__FILE__) . '/../display/dbconfig.php';
}
bs_gate();

$result = mysql_query_moon("SHOW FULL PROCESSLIST");
if ($result) {
    while ($row = mysql_fetch_array($result)) {
        $process_id = $row["Id"];
        if (($row["Time"] >= $ptime) && ($row["User"] == $username) && ($row["Command"] != "Daemon") && ($row["Command"] != "Sleep")) {
            if($ptime > 0) {
                $sql = "KILL $process_id";
                mysql_query_moon($sql);
            }
        }
    }
}

include_once(dirname(__FILE__) . '/module.php');
