<?php
include_once(dirname(__FILE__) . '/client_theme.php');
$version = '3.0';

//$GLOBALS['Poweredby']="Powered By <a href=\"http://$brandweb\" target=\"_blank\"><b>$brand </b></a>";

if (strlen(trim((string)($cright ?? ''))) > 0) {
    $copyrightLink = htmlspecialchars((string)($crightlink ?? ''), ENT_QUOTES, 'UTF-8');
    $copyrightText = htmlspecialchars((string)$cright, ENT_QUOTES, 'UTF-8');
    $GLOBALS['Copyright'] = date("Y") . "&copy; <a href=\"https://{$copyrightLink}/\" target=\"_blank\" rel=\"noopener\"><b>{$copyrightText}</b></a>.  ";
}
else $GLOBALS['Copyright']="Copyright &copy;<a href=\"https://www.petacoretech.com/\" target=\"_blank\" rel=\"noopener\"><b>Petacore Tech</b></a>";

$GLOBALS['Vers'] = $finalversion==1 ? " V:$version All rights reserved" : " Demo version";

function AthStatusBarMMSS($dd){if($dd=12981298)return "HasanMIM";else return "";}
function StatusBarMMSS(){return ($GLOBALS['Poweredby'] ?? '') . $GLOBALS['Copyright'] . $GLOBALS['Vers'];}

?>