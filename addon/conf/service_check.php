<?php
setcookie('authenticatechk',encode("3528"),0,"/");

include_once(dirname(__FILE__) . '/dbconnect.php');
include_once(dirname(__FILE__) . '/VersionControl.php');
$sql_client_theme="select service_code from client_theme where client_theme_id=1";
$res_client_theme=mysql_query_moon($sql_client_theme);
$row_client_theme=mysql_fetch_row($res_client_theme);
//Check CRM

error_reporting(0);
// Licence, dates and modules come from PetaBill Cloud through addon/inc/licence.php. This only keeps the older code's defaults.
$Content="127.0.0.1;192.168.2.213;192.168.2.239;0;Petacore Tech;Demo;2015-12-01;2016-02-01;1,1,1,1,0,1,1,1,1,1;Petacore Tech;www.petacoretech.com;Petacore Tech;www.petacoretech.com;";
$a=explode(';',$Content);
unset($Content);
$serverAddr = $_SERVER['SERVER_ADDR'] ?? (PHP_SAPI === 'cli-server' ? '127.0.0.1' : '');
$a[0]=$serverAddr; // runs on any server: Server IPs in PetaBill Cloud lock an install to one

if((trim($a[0])==trim($serverAddr)) || (trim($a[1])==trim($serverAddr)) || (trim($a[2])==trim($serverAddr)))
{
    $sql_client_theme="update client_theme set module='$a[8]',customize6='$a[9]',customize5='$a[10]',customize3='$a[11]',customize4='$a[12]' where client_theme_id=1";
    $res_client_theme=mysql_query_moon($sql_client_theme);
}
else
{
    $a[0]="hackedornotnet";
}
error_reporting(-1);
?>