<?php
ob_start();
set_time_limit(0);
include_once(dirname(__FILE__) . '/dbconnect.php');
include_once(dirname(__FILE__) . '/client_theme.php');
include_once(dirname(__FILE__) . '/configuration.php');
include_once(dirname(__FILE__) . '/VersionControl.php');

if($default_record==""){$default_record=25;}else{$default_record=$default_record;}
if($session_idle_time<10){$cfgidletime = 10;}else{$cfgidletime = $session_idle_time;}

// for admin
$cfgtablename = "admin_user"; // table name where session info is stored
$exitway="Location: ../";
if (bs_session_id($_COOKIE['admin_sesid'] ?? '') === ''){header("Location: ../index.php");exit;}
else
{
    $sesid = mysql_real_escape_string($_COOKIE['admin_sesid']);$strSQL = "SELECT user_id,sesid,user_name,lastaccess,level FROM $cfgtablename WHERE sesid='$sesid'";
    $result = mysql_query_moon($strSQL);


    if (mysql_num_rows($result)){if($multilog_version==0){header($exitway);exit;}}
    $row = mysql_fetch_array($result);

    if ($row['lastaccess']+$cfgidletime*60 < time()){header($exitway);exit;}
    elseif ($row['level'] < 0){header($exitway);exit;}



    else
    {
        $lastaccess = time();
        $strSQL = "UPDATE $cfgtablename SET lastaccess=$lastaccess WHERE user_id='". $row['user_id'] . "'";
        $result = mysql_query_moon($strSQL);
        $GLOBALS['user_id'] = $row['user_id'];$GLOBALS['user_name'] = $row['user_name'];$GLOBALS['level'] = $row['level'];
        require_once dirname(__DIR__) . '/inc/perm.php';
        perm_guard();
    }
}
?>

