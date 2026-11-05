<?php
require_once "../addon/conf/connection.php";

if($finalversion==0){header("Location: ../index.php");exit;}  // this is for demo
$cfgtablename = "admin_user"; // table name where session info is stored
$past = time() - 100 ;

audit_logout();
//===============update log==========================
$log_id = (int) ($_COOKIE['logid'] ?? 0);
$update_log="  UPDATE login_log SET login_log.logout_time=NOW() WHERE login_log.`logid`='$log_id'";
$res_update_log=mysql_query_moon($update_log);
//===============end update log======================
//for session
$strSQL = "UPDATE $cfgtablename SET sesid='',lastaccess=0 WHERE sesid='" . bs_session_id($_COOKIE['admin_sesid'] ?? '') . "'";
$result = mysql_query_moon($strSQL);
setcookie("admin_sesid","",0,"/");
//end Session
//this makes the time in the past to destroy the cookie
setcookie("ID_my_site_admin", "",0,"/");
setcookie("Key_my_site_admin", "",0,"/");
setcookie("user_id_admin", "",0,"/");
setcookie("logid", "",0,"/");
setcookie("limit", "",0,"/");
setcookie("authenticatechk", "",0, "/");
?>
<script language="JavaScript" type="text/javascript">top.location.href='../index.php';</script>
<?php include "../addon/conf/connection_close.php";  ?>