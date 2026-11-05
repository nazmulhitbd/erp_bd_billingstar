<?php
require_once __DIR__ . '/php_compat.php';
require_once __DIR__ . '/mysql_compat.php';



/** Kept for the pages that still call it. It used to compare cookies a visitor could write for themselves, which proved nothing. */
function chkauthenticate($page)
{
}

function mysql_query_ucns_count($query, $default_value=0)
{
    if (!$result=mysql_query_moon($query)) {return 0;}
    if (mysql_num_rows($result)==0)return $default_value;
    else return mysql_result($result,0);
}

/** The name every older page calls. It used to run KILL on a number from a cookie, so anyone could stop other people's queries. */
function mysql_query_moon($sqlstr)
{
    return mysql_query($sqlstr);
}

function mysql_query_moon_normal($sqlstr)
{
    $result=mysql_query($sqlstr);
    return $result;
}

function stopmysqlquery($DBMYSQLUSER,$processtimeout)
{
    $result = mysql_query("SHOW FULL PROCESSLIST");
    if ($result)
    {
        while ($row=mysql_fetch_array($result))
        {
            $process_id=$row["Id"];
            if (($row["Time"] > $processtimeout) && ($row["User"] == $DBMYSQLUSER) && (querychk($row["Info"])==false) && ($row["Command"] <> "Daemon")  && ($row["Command"] <> "Sleep") )
            {
                if($processtimeout>0) {$sql="KILL $process_id";	mysql_query_moon($sql);}
            }
        }
    }
}


function mmss($time_sec)
{
    $minutes = sprintf('%02d', floor($time_sec / 60));
    $seconds = sprintf(':%02d', (int) $time_sec % 60);
    return 	$minutes . $seconds;
}
function hhmmss($time_sec)
{
    return 	gmdate("H:i:s", round($time_sec));
}

function format_date($date_string)
{
    $date=explode('-',$date_string);
    if($date[1]=='Jan') $month='01';
    if($date[1]=='Feb') $month='02';
    if($date[1]=='Mar') $month='03';
    if($date[1]=='Apr') $month='04';
    if($date[1]=='May') $month='05';
    if($date[1]=='Jun') $month='06';
    if($date[1]=='Jul') $month='07';
    if($date[1]=='Aug') $month='08';
    if($date[1]=='Sep') $month='09';
    if($date[1]=='Oct') $month='10';
    if($date[1]=='Nov') $month='11';
    if($date[1]=='Dec') $month='12';
    $date_format="$date[2]/$month/$date[0]";
    return $date_format;
}
function format_date1($date_string)
{
    $date=explode('/',$date_string);
    if($date[1]==1) $month='Jan';
    if($date[1]==2) $month='Feb';
    if($date[1]==3) $month='Mar';
    if($date[1]==4) $month='Apr';
    if($date[1]==5) $month='May';
    if($date[1]==6) $month='Jun';
    if($date[1]==7) $month='Jul';
    if($date[1]==8) $month='Aug';
    if($date[1]==9) $month='Sep';
    if($date[1]==10) $month='Oct';
    if($date[1]==11) $month='Nov';
    if($date[1]==12) $month='Dec';
    $date_format="$date[2]-$month-$date[0]";
    return $date_format;
}

function querychk($sql)
{
    return strpos_arr($sql, array('insert', 'update','delete'));
}

function strpos_arr($haystack, $needle)
{
    if(!is_array($needle)) $needle = array($needle);
    foreach($needle as $what)
    {
        if(($pos = strpos($haystack, $what))!==false) return $pos;
    }
    return false;
}

function safe($value)
{
    return mysql_real_escape_string($value);
}

function decode($string)
{
    for($i=0; $i<8; $i++)$string=base64_decode($string);return $string;
}

function encode($string)
{
    $string = (string) $string;
    for($i=0; $i<8; $i++)$string=base64_encode($string);return $string;
}


function isMobile($userBrowser) // http://detectmobilebrowsers.com/
{
    $retval=0;
    if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|
	hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|
	phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|
	windows (ce|phone)|xda|xiino/i',$userBrowser)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|
	a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|
	au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|
	cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|
	do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|
	gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|
	hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|
	inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|
	lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|
	mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|
	n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|
	pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|
	qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|
	sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|
	sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|
	tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|
	veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|
	whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($userBrowser,0,4)))
        $retval=1;
    return $retval;
}

function mailer($subject,$body,$AltBody)
{
    global $mailreport,$mailto,$mailcc,$mailbcc,$mailauth,$mailproto,$mailhost,$mailport,$mailuname,$mailpass;
    include_once(dirname(__FILE__) . '\..\conf\dbconnect_mail.php');
    include_once(dirname(__FILE__) . '\..\conf\client_theme.php');
    if($mailreport==1)
    {
        // for mail



        $mailsmptauth=$mailauth;
        $mailsmtpsecure=$mailproto;
        $mailhost=$mailhost;
        $mailport=$mailport;
        $mailusername=$mailuname;
        $mailpassword=$mailpass;


        // GMAIL password
        $mailsetfrom=$mailusername;
        $mailsetfrom1=$index_title;
        $mailaddreplyto=$mailusername;
        $mailaddreplyto1="Server Administrator";



        // end for mail


        include_once(dirname(__FILE__) . '\..\mail\moon.php');
        $mail=new PHPMailer();$mail->IsSMTP();$mail->SMTPDebug=0;$mail->SMTPAuth=$mailsmptauth;$mail->SMTPSecure=$mailsmtpsecure;$mail->Host=$mailhost;$mail->Port=$mailport;$mail->Username=$mailusername;$mail->Password=$mailpassword;$mail->SetFrom($mailsetfrom,$mailsetfrom1);$mail->AddReplyTo($mailaddreplyto,$mailaddreplyto1);
        if($mailto!='')
        {
            $address = $mailto;
            $mail->AddAddress($address, "Server Administrator");
        }
        if($mailcc!='')
        {
            $cc = $mailcc;
            $mail->AddCC($cc, "Server Administrator");
        }
        if($mailbcc!='')
        {
            $bcc = $mailbcc;
            $mail->AddBCC($bcc, "Server Administrator");
        }
        $mail->Subject= $subject;
        $mail->AltBody    = $AltBody;
        $body             = $body;
        $mail->MsgHTML($body);
        //$mail->AddAttachment("images/phpmailer.gif");      // attachment
        //$mail->AddAttachment("images/phpmailer_mini.gif"); // attachment
        if(!$mail->Send()) {echo "Mailer Error: " . $mail->ErrorInfo;}
    }
}



function getServerAddress()
{
    if (!empty($_SERVER['SERVER_ADDR'])) {
        return $_SERVER['SERVER_ADDR'];
    }

    if (PHP_SAPI === 'cli-server') {
        return '127.0.0.1';
    }

    $hostAddress = gethostbyname(gethostname());
    return filter_var($hostAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $hostAddress : '';
}


function base_url()
{
    $s = empty($_SERVER["HTTPS"]) ? '' : (($_SERVER["HTTPS"] == "on") ? "s" : "");
    $sp = strtolower($_SERVER["SERVER_PROTOCOL"]);
    $protocol = substr($sp, 0, strpos($sp, "/")) . $s;
    $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
    $s= $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    return chop($s,basename($s));
}

function module($numomber)   //just for execution control
{
    $sql_client_theme="select module from client_theme where client_theme_id=1";
    $res_client_theme=mysql_query($sql_client_theme);
    $row_client_theme=mysql_fetch_row($res_client_theme);
    $a=explode(',',$row_client_theme[0]);
    return $a[$numomber];
}

///////////////////////////////////////////////////////////////////validate user///////////////////////////////
function str_makerand ($minlength, $maxlength, $useupper, $uselower, $usenumbers)
{
    $charset = "";
    $key = "";
    if ($useupper) $charset .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    if ($uselower)$charset .= "abcdefghijklmnopqrstuvwxyz";
    if ($usenumbers) $charset .= "0123456789";
    //if ($usespecial) $charset .= "~@#$%^*()_+-={}|]["; // Note: using all special characters this reads: "~!@#$%^&*()_+`-={}|\\]?[\":;'><,./";
    if ($minlength > $maxlength) $length = mt_rand ($maxlength, $minlength);
    else $length = mt_rand ($minlength, $maxlength);
    for ($i=0; $i<$length; $i++) $key .= $charset[(mt_rand(0,(strlen($charset)-1)))];
    return $key;
}

///////////////////////////////////////////////////////////////////////////////////////
function sum($b)
{
    $sum = 0;
    $words=explode(",", $b);
    $n=count($words);
    for($i = 0; $i < $n; $i++)
    {
        $sum += $words[$i];
    }
    return $sum;
}
function main($aa,$type)
{
    $elements = $aa;
    $type1=$type;
    $combs = find_all_combinations($elements);
    $complete_com=print_combinations($combs,$type1);
    return $complete_com;
}

function find_all_combinations($elements)
{
    $combs = array();
    for($group_size = 1; $group_size <= count($elements); $group_size++)
    {
        find_combinations($combs, $elements, $group_size);
    }
    return $combs;
}

function find_combinations(&$combs, $elements, $group_size, $recurse_level = 1, $start_index = 0, $curr_element = '')
{
    $element_index = $start_index;
    do
    {
        $new_comb = $curr_element;
        if (!empty($new_comb))
        {
            $new_comb .= ',';
        }
        $new_comb .= $elements[$element_index];
        if ($recurse_level == $group_size)
        {
            $combs[$new_comb] = $new_comb;
        }
        else
        {
            find_combinations($combs, $elements, $group_size, $recurse_level + 1, $element_index + 1, $new_comb);
        }
        $element_index++;
    } while ($element_index < count($elements) - $group_size + $recurse_level);
}

function factorial($number)
{
    if ($number == 0) return 1;
    return $number * factorial($number - 1);
}

function print_combinations($combs,$type)
{
    $main_str="";
    foreach ($combs as $comb)
    {
        if(sum($comb)==$type)$main_str = $comb."=".sum($comb);
    }
    return $main_str;
}
