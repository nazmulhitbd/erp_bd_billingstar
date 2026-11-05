<?php
include_once(dirname(__FILE__) . '/../inc/utility.php');
if(!isset($link))
{
    global $dbhost,$dbuser,$dbpass,$dbname,$processtime,$dbport;
    include_once(dirname(__FILE__) . '/../display/dbconfig.php');

    $host = $dbhost;
    $username = $dbuser;
    $password = $dbpass;
    $database = $dbname;
    $ptime=$processtime;
    $dbport=$dbport;
    $link = mysql_connect($host.':'.$dbport, $username, $password);
    if(!$link)echo "Connection failed.";
    $db = mysql_select_db($database)or die(mysql_error());
}
	
?>
