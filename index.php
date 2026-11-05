<?php
require_once __DIR__ . '/addon/inc/redirect_directory_path.php';
header ("Expires: ".gmdate("D, d M Y H:i:s", time())." GMT");
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");
ob_start();
require_once "addon/inc/cookie.php";cookiefree();
if(!is_file('addon/display/dbconfig.php')){header('Location: ./install_script/index.php');exit();}
require_once "addon/conf/dbconnect.php";
setcookie("authenticatechk", "",0, "/");$mugsre=0;include "addon/conf/service_check.php";
require_once "addon/conf/configuration.php";
require_once "addon/conf/client_theme.php";
require_once "addon/inc/licence.php";
licence_splash(true);


if(!isset($a[0]))$a[0]=null;if(!isset($a[1]))$a[1]=null;if(!isset($a[2]))$a[2]=null;
$serverAddr = $_SERVER['SERVER_ADDR'] ?? (PHP_SAPI === 'cli-server' ? '127.0.0.1' : '');

if((trim($a[0])==trim($serverAddr)) || (trim($a[1])==trim($serverAddr)) || (trim($a[2])==trim($serverAddr)))
{
    if($session_idle_time<10)
    {
        $cfgidletime = 10;        // max time a user can be idle (in minutes)
    }
    else
    {
        $cfgidletime = $session_idle_time;        // max time a user can be idle (in minutes)
    }
    $cfgtablename = "admin_user"; // table name where session info is stored

    global $userBrowser;
    require_once "addon/inc/cookie.php";
    require_once "addon/display/display.php";
    require_once "addon/inc/user.php";
}
else
{
  ?><div align="center"><img src="addon/images/error.png" /></div><?php 
}
require_once "addon/conf/connection_close.php";  
?>