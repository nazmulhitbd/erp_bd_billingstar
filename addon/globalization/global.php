<?php
if(isset($lang)){include_once(dirname(__FILE__) . '/' . 'glob.' . $lang . '.php');}
else{include_once(dirname(__FILE__) . '/' . 'glob.en-US.php');}
function trns($s){global $lipi;if(isset($lipi[$s])){return $lipi[$s];}else{return $s;}}
function ptrns($s){echo trns($s);}
?>