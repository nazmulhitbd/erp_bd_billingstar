<?php
require_once "../addon/conf/connection.php";
require_once "../addon/conf/client_theme.php";
require_once "../addon/conf/VersionControl.php";
chkauthenticate(basename($_SERVER['PHP_SELF']));   // prevent copy this page
$issetvalue="";if(isset($GLOBALS['StatusBar']))$issetvalue=$GLOBALS['StatusBar'];
if($issetvalue=="ta381"){$GLOBALS['StatusBar']="411";}
else {


    if(strlen(trim(StatusBarMMSS()))<=0)echo "Please Contact with This Software Support, Thanks."; 


?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<title></title>
</head>
<body bgcolor=<?php echo $statusbar_bgcolor;?> >
<table cellspacing="0" cellpadding="0" align="center" >
<tr>
	<td align="center"><font size="2"><?php if(AthStatusBarMMSS(12981298)=="HasanMIM")echo StatusBarMMSS(); ?></font></td>
</tr>
</table>
</body>
</html>
<?php } ?>