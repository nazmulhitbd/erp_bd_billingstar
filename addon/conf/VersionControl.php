<?php
include_once(dirname(__FILE__) . '/client_theme.php');
$version = '3.0';

//$GLOBALS['Poweredby']="Powered By <a href=\"http://$brandweb\" target=\"_blank\"><b>$brand </b></a>";

// The product notice under every page: PetaBill ERP belongs to Petacore Technology. A reseller's own name (Company & Login
// Settings, copyright text) shows first, and never replaces the owner's notice.
$ownerLink = '<a href="https://www.petacoretech.com/" target="_blank" rel="noopener"><b>Petacore Technology</b></a>';
$reseller = trim((string) ($cright ?? ''));
$GLOBALS['Copyright'] = ($reseller !== '' && stripos($reseller, 'petacore') === false
        ? '<a href="https://' . htmlspecialchars((string) ($crightlink ?? ''), ENT_QUOTES, 'UTF-8') . '/" target="_blank" rel="noopener"><b>' . htmlspecialchars($reseller, ENT_QUOTES, 'UTF-8') . '</b></a> &middot; '
        : '')
    . '&copy; ' . date('Y') . ' ' . $ownerLink . ' &middot; PetaBill ERP';
$GLOBALS['Vers'] = $finalversion == 1 ? " v$version. All rights reserved." : ' v' . $version . ' demo.';

function AthStatusBarMMSS($dd){if($dd=12981298)return "HasanMIM";else return "";}
function StatusBarMMSS(){return ($GLOBALS['Poweredby'] ?? '') . $GLOBALS['Copyright'] . $GLOBALS['Vers'];}

?>