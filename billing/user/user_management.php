<?php
require_once "../../addon/conf/connection.php";
chkauthenticate(basename($_SERVER['PHP_SELF']));   // prevent copy this page 
//end For Search
session_start();
// navigate
//for permission
$cinfo_sql="SELECT  user_config,user FROM admin_user,admin_role where admin_user.user_role_id=admin_role.user_role_id and user_id=".(int) $_COOKIE['user_id_admin'];
$cinfo_res=mysql_query_moon($cinfo_sql);
$cinfo_row=mysql_fetch_row($cinfo_res);
$user_config_=explode(',',$cinfo_row[0]);
$user_=explode(',',$cinfo_row[1]);
//end for permission

//for Search


// The words searched for are kept, never SQL: rebuilding a query from the session is what let a * turn back into a quote.
$statement="";
if(isset($_POST['ButtonApply']) || isset($_POST['record_count']))
{
    $_SESSION['um_search'] = (string) ($_POST['InputLogin'] ?? '');
}
if(isset($_POST['ButtonApply']) || isset($_POST['record_count']) || isset($_GET['p1']))
{
    $um_search = (string) ($_SESSION['um_search'] ?? '');
    if($um_search!="")
    {
        $statement .=" and login_id like '" . bs_like($um_search) . "%'";
    }
}
//end For Search
$page_name="user_management.php?";
[$limit, $start] = bs_paging();
if(isset($_POST['record_count']))
{
    setcookie("limit", $limit, 0,"/");
}
$eu = ($start - 0);
$this1 = $eu + $limit;
$back = $eu - $limit;
$next = $eu + $limit;
$query2="select count(logid) from login_log where logid<>0 $statement";
$result2=mysql_query_moon($query2);
$nume=mysql_fetch_row($result2);
$total_record=$nume[0];
$reminde=0;if($total_record>$limit) $reminde=$total_record%$limit;
$total_page=floor($total_record/$limit);
// end
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>user Management</title>
<script language="JavaScript" src="../../addon/js/wui.js" type="text/javascript"></script>
<script language="JavaScript" src="../../addon/js/wui_bar.js" type="text/javascript"></script>
  <link href="../../addon/App_Themes/DefaultTheme/default.css" type="text/css" rel="stylesheet" />
  <link href="../../addon/App_Themes/DefaultTheme/default_color.css" type="text/css" rel="stylesheet" />
  <link href="../../addon/App_Themes/DefaultTheme/default_font.css" type="text/css" rel="stylesheet" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/css/datepicker3.min.css" />
  <link rel="stylesheet" href="libs/css/main.css" />

<script language="JavaScript" type="text/javascript">
/*<![CDATA[*/
function checkAll(iobj)
{
        inputArray = document.getElementsByTagName("input");
        for(i=0; i<inputArray.length; i++)
        {
                if(inputArray[i].type.toLowerCase() == "checkbox" && inputArray[i] != iobj)
                {
                        tempCheckbox = inputArray[i];
                        if(tempCheckbox.checked)
                        {
                                tempCheckbox.checked = false;
                        }
                        else
                        {
                                tempCheckbox.checked = true;
                        }
                }
        }
}

  function record()
  {
    var theForm = document.forms['ctl01'];
    theForm.submit();
  }
function popup(url)
{
    window.open(url,'Tag', 'width=600, height=375, titlebar=0, popFrameless, resizable=no, menubar=no, toolbar=no, status=no, scrollbars=yes, top=220,  left=220');
}

/*]]>*/
</script>

</head>

<body style="background: <?php echo $body_bgcolor;?>" >
<div class="panel panel-default">
  <div class="panel-body">

  <form name="ctl01" method="post" action="user_management.php" id="ctl01">

    <div style="PADDING-RIGHT: 0px; PADDING-LEFT: 0px; PADDING-BOTTOM: 5px; MARGIN: 0px 0px 5px; PADDING-TOP: 0px">
	
      <ol class="breadcrumb">
        <span style="FONT-WEIGHT: bold">You are here:</span> / Visitor Tracking  <span id="LabelURHereSmall"></span>
        <h2><span id="LabelURHere">Visitor Tracking</span></h2>
      </ol>
    </div>
        <div style="PADDING-RIGHT: 00px; PADDING-LEFT: 00px; PADDING-BOTTOM: 0px; MARGIN: 0px auto; PADDING-TOP: 0px; HEIGHT: 80%">
				<!--<form class="WUIform" action="list.aspx" method="post">-->
				<div style="WIDTH: 100%">
					<div class="search_panel" >
						<table cellSpacing="0" cellPadding="0" border="0">
							<tr style="VERTICAL-ALIGN: top">
								<td vAlign="middle">
									<div style="FONT-WEIGHT: bold; MARGIN-LEFT: 3px; WIDTH: 50px">
										Search:
									</div>
								</td>
								<td width="200" valign=middle>
									<table cellSpacing="1" cellPadding="1" width="100%" border="0">
										<tr vAlign="middle" width="20">
											<td vAlign="middle" width="20" valign=middle>
												<div class="FormrowTitle" width="20">
													Login
												</div>
											</td>
											<td>
												<input class="form-control" placeholder="Login" class="WUIformrow_input" id="InputLogin" name="InputLogin" value="<?php if(isset($_POST['InputLogin'])) echo bs_h($_POST['InputLogin']); ?>">
											</td>
										</tr>
									</table>
								</td>
								<td noWrap width="1">
								</td>
								<td noWrap width="1">
								</td>
								<td vAlign="middle" align="center">
									<input class="btn btn-primary" type="submit" name="ButtonApply" value="&nbsp; Apply &nbsp;" id="ButtonApply" class="FormButton" />
								</td>
							</tr>
                                    </table>
					</div>
				</div>

<!-- paging start  -->
<?php 
require_once "../../addon/inc/paging.php";
navigator($limit,$back,$page_name,$start,$total_record,$next,$this1,$reminde,$total_page);
?>
<!-- end  -->


      <div class="WUIrsDivOverflow">
        <table style="font-family: MS Sans Serif; font-size: 12; color: #515151; border-collapse: collapse" cellpadding="1" cellspacing="1" border="0" width="100%" >
          <tr class="WUIrsheader">
            <td class="WUIrsheaderel" width="20"><input type="checkbox" onclick="checkAll(this)" /></td>
            <td class="WUIrsheaderel" width="30">Nr</td>
            <td class="WUIrsheaderel">Log ID</td>
            <td class="WUIrsheaderel">Login ID</td>
            <td class="WUIrsheaderel">Login Time</td>
            <td class="WUIrsheaderel">Logout Time</td>
            <td class="WUIrsheaderel">IP Address</td>
            <td class="WUIrsheaderel">User Type</td>
          </tr>
         <?php
$sql="SELECT logid,login_id,DATE_FORMAT(login_time,'%d-%b-%Y %r') as login_time,DATE_FORMAT(logout_time,'%d-%b-%Y %r') as logout_time,ip_address,log_date,user_type,date(login_time)
             FROM   login_log where logid<>0 $statement  ORDER BY logid DESC limit $eu, $limit ";
$result=mysql_query_moon($sql);

$i=0;
while ($row = mysql_fetch_row($result))
{



    $i= $i + 1;
    if ($i%2==0)
    {
             ?>
              <tr valign="top" onmouseover="javascript:RSRecordrow_Activate(this)" onmouseout="javascript:RSRecordrow_DeActivate(this,'1')" class="WUIrsrecordroweven">
              <?php
        echo "<td class=WUIfield height=15 style='text-align: left;'><input name=delete[] type=checkbox id=RepeaterClientList_ctl01_ItemTemplateCheckbox value=$row[0] /></td>";
        echo "<td class=WUIfield height=15 style='text-align: left;'>$i.</td>";
        echo "<td class=WUIfield height=15><b>$row[0]</b></td>";
        echo "<td class=WUIfield height=15>$row[1]</td>";
        echo "<td class=WUIfield height=15>$row[2]</td>";
        echo "<td class=WUIfield height=15 align=center>$row[3]</td>";
        echo "<td class=WUIfield height=15>$row[4]</td>";
        echo "<td class=WUIfield height=15>" . ($row[6]==0 ? 'Staff' : 'Store (old sign-in)') . "</td>";


        echo "</tr>";
    }
    else
    {
             ?>
              <tr valign="top" onmouseover="javascript:RSRecordrow_Activate(this)" onmouseout="javascript:RSRecordrow_DeActivate(this,'0')" class="WUIrsrecordrowodd">
              <?php
        echo "<td class=WUIfield height=15 style='text-align: left;'><input name=delete[] type=checkbox id=RepeaterClientList_ctl01_ItemTemplateCheckbox value=$row[0] /></td>";
        echo "<td class=WUIfield height=15 style='text-align: left;'>$i.</td>";
        echo "<td class=WUIfield height=15><b>$row[0]</b></td>";
        echo "<td class=WUIfield height=15>$row[1]</td>";
        echo "<td class=WUIfield height=15>$row[2]</td>";
        echo "<td class=WUIfield height=15 align=center>$row[3]</td>";
        echo "<td class=WUIfield height=15>$row[4]</td>";
        echo "<td class=WUIfield height=15>" . ($row[6]==0 ? 'Staff' : 'Store (old sign-in)') . "</td>";
        echo "</tr>";
    }
}
         ?>
        </table>
      </div>
		<div style="MARGIN-TOP: 10px">
        <div class="WUIgroupListSeparator">&nbsp;</div>
        <?php
if($user_[3]==1)
{
        ?>
          <input class="FormButton" id="ButtonRemove" onclick="javascript:return AskDelete('Are you sure?');" type="submit" value="&nbsp; Remove selected" name="ButtonRemove">
          <input class="FormButton" id="RemoveAll" onclick="javascript:return AskChildrenTrf();" type="submit" value="Remove All" name="RemoveAll">
        <?php
}
        ?>
		</div>
</form>
</div>
</div>
</body>

</html>

<?php
if (isset($_POST['ButtonRemove']))
{
    for ($j=0; $j<count($_POST['delete']); $j++)
    {
        $delete = "delete from login_log  where login_log.logid = ".(int) $_POST['delete'][$j];
        if($finalversion==1)mysql_query_moon($delete);
    }
    echo  "<meta http-equiv=refresh content=0.01 >";
}
if (isset($_POST['RemoveAll']))
{
    $delete = "delete from login_log ";
    if($finalversion==1)mysql_query_moon($delete);
    echo  "<meta http-equiv=refresh content=0.01 >";
}

?>
<?php include "../../addon/conf/connection_close.php";  ?>