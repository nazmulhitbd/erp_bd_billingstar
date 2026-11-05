<?php
require_once dirname(__FILE__) . '/perm.php';
require_once dirname(__FILE__) . '/two_step.php';
$login_allowed = isset($_POST['okButton']) && is_string($_POST['user_name'] ?? null) && is_string($_POST['password'] ?? null) && bs_login_allowed($_POST['user_name']);
$login_name = $_POST['user_name'] ?? '';
if ($login_allowed)
{
    $GLOBALS['raw_password'] = (string) ($_POST['password'] ?? ''); // store logins verify the password as typed

    $_POST['user_name']=mysql_real_escape_string($_POST['user_name']);
    $_POST['password']=mysql_real_escape_string($_POST['password']);


    // staff and users who have signed in to the shell before
    {
        $error_a=0;
        $error=0;
        $checkuser_a = "select count(user_id) from admin_user where user_name ='" . $_POST['user_name'] . "'";
        $checkresult_a=mysql_query_moon($checkuser_a);
        $check_rowno_a=(int) (mysql_fetch_row($checkresult_a)[0] ?? 0); // the COUNT(*), not the number of result rows
        if($check_rowno_a>0)
        {
            $error_a=1;
            $error=$error+1;
        }
        if ($error>0)
        {
            if($error_a==1)
            {
                $sql = "select user_name, user_password, user_id,lastaccess from admin_user where user_name ='" . $_POST['user_name'] . "'";
                $check1 = mysql_query_moon($sql);
                while($info1 = mysql_fetch_row($check1))
                {
                    $info1[1] = stripslashes($info1[1]);
                    // Active flag, working hours, customer / employee password and the branch picked on the form.
                    $erp_user = perm_ready() ? perm_q('SELECT * FROM admin_user WHERE user_id=?', 'i', [(int) $info1[2]])[0] : null;
                    $erp_error = perm_signin_error($erp_user, $GLOBALS['raw_password'] ?? '', (int) ($_POST['branch_id'] ?? 0));
                    if ($erp_error !== '')
                    {
							?><script language="JavaScript" type="text/javascript">display_message(<?php echo json_encode($erp_error); ?>);</script><?php
                    }
                    elseif (!$erp_user && !hash_equals((string) $info1[1], md5($GLOBALS['raw_password'] ?? '')))
                    {
						?><script language="JavaScript" type="text/javascript">display_message("Incorrect user name or password.");</script><?php
                        $count = 0;
                        $result = mysql_query_moon($sql);
                        while ($row[$count] = mysql_fetch_assoc($result)) $count++;
                        if ($count == 0)
                        {
							?><script language="JavaScript" type="text/javascript">display_message("Incorrect user name or password.");</script><?php
                        }
                    }
                    else
                    {
                        $today1= date("Y-m-d");
                        if($finalversion==0)$info1[3]=0;  // this is for demo
                        if($multilog_version==1)$info1[3]=0;
                        if($erp_user && $erp_user['user_type']!='staff')$info1[3]=0; // portal users may reopen after closing the browser
                        if($info1[3]<>0)
                        {
                            if ($info1[3]+$cfgidletime*60 < time()) // max idle time reached
                            {
                                tfa_gate_shell($erp_user, 'billing/' . perm_shell()); // leaves for verify.php when a code is needed
                                //=================Insert Log Unit======================
                                $insert_log="insert into login_log (LOGIN_ID,log_date,login_time,ip_address,user_type) Values ('".$_POST['user_name']."','$today1',now(),'$_SERVER[REMOTE_ADDR]','0')";
                                $res_insert_log=mysql_query_moon($insert_log);
                                $row_insert_log=mysql_fetch_row($res_insert_log);
                                $insert_max="select Max(logid) from login_log where login_id = '$_POST[user_name]'";
                                $res_insert_max=mysql_query_moon($insert_max);
                                $row_insert_max=mysql_fetch_row($res_insert_max);
                                //=================Insert Log Unit======================
                                $_POST['user_name'] = stripslashes($_POST['user_name']);
                                $hour = time() + 86400;
                                //session
                                $sesid = bin2hex(random_bytes(16));
                                $lastaccess = time();
                                $strSQL = "UPDATE $cfgtablename SET sesid='$sesid',lastaccess=$lastaccess WHERE user_id=$info1[2]";
                                $result_strSQL = mysql_query_moon($strSQL);
                                /* Create a new session cookie */
                                bs_limit_clear(bs_login_keys($login_name)[0]);audit_login('staff', (int) $info1[2], $login_name, true);
                                setcookie("admin_sesid",$sesid,0,"/");
                                //end session
                                setcookie("ID_my_site_admin", $_POST['user_name'],0,"/");
                                setcookie("user_id_admin",$info1[2],0,"/");
                                setcookie("logid",$row_insert_max[0],0,"/");
                                /* end new session cookie */
                                header('Location: billing/' . perm_shell());exit;
                            }
                            else
                            {
                                				?><script language="JavaScript" type="text/javascript">display_message("Multiple Login not Allowed \r\n Contact your Administrator for Help");</script><?php
                            }
                        }
                        else
                        {
                            tfa_gate_shell($erp_user, 'billing/' . perm_shell()); // leaves for verify.php when a code is needed

                            //=================Insert Log Unit======================

                            $insert_log="insert into login_log (login_id,log_date,login_time,ip_address,user_type) Values ('".$_POST['user_name']."','$today1',now(),'$_SERVER[REMOTE_ADDR]','0')";
                            $res_insert_log=mysql_query_moon($insert_log);
                            $row_insert_log=mysql_fetch_row($res_insert_log);


                            $insert_max="select Max(logid) from login_log where login_id = '$_POST[user_name]'";
                            $res_insert_max=mysql_query_moon($insert_max);
                            $row_insert_max=mysql_fetch_row($res_insert_max);
                            //=================Insert Log Unit======================

                            $_POST['user_name'] = stripslashes($_POST['user_name']);
                            $hour = time() + 86400;
                            //session
                            $sesid = bin2hex(random_bytes(16));
                            $lastaccess = time();
                            $strSQL = "UPDATE $cfgtablename SET sesid='$sesid',lastaccess=$lastaccess WHERE user_id=$info1[2]";
                            $result_strSQL = mysql_query_moon($strSQL);
                            /* Create a new session cookie */
                            bs_limit_clear(bs_login_keys($login_name)[0]);audit_login('staff', (int) $info1[2], $login_name, true);
                            setcookie("admin_sesid",$sesid,0,"/");
                            //end session
                            setcookie("ID_my_site_admin", $_POST['user_name'],0,"/");
                            setcookie("user_id_admin",$info1[2],0,"/");
                            setcookie("logid",$row_insert_max[0],0,"/");
                            header('Location: billing/' . perm_shell());exit;
                        }
                    }
                }
            }
            // end admin
        }
        else
        {
            // A customer or SR portal login that has not used the shell yet.
            [$erp_user, $erp_error] = perm_ready() ? perm_portal_login($_POST['user_name'], $GLOBALS['raw_password'] ?? '') : [null, 'Incorrect user name or password.'];
            if ($erp_user && perm_login_block($erp_user) === '')
            {
                tfa_gate_shell($erp_user, 'billing/' . perm_shell());
                perm_start_session($erp_user);
                header('Location: billing/' . perm_shell());exit;
            }
			?><script language="JavaScript" type="text/javascript">display_message(<?php echo json_encode($erp_error ?: 'This user is not active.'); ?>);</script><?php
        }
    }
}
//end crm check

// Every sign-in that worked has left this page, so reaching here after a post means it did not.
if ($login_allowed)
{
    bs_login_failed($login_name);
    audit_login('anonymous', 0, is_string($login_name) ? $login_name : '', false);
}
elseif (isset($_POST['okButton']))
{
    audit_security('login_locked', 'Sign-in refused after too many wrong passwords: ' . (is_string($login_name) ? mb_substr($login_name, 0, 60) : ''), [], 'bad');
    ?><script language="JavaScript" type="text/javascript">display_message("Too many wrong passwords. Try again in 15 minutes.");</script><?php
}
