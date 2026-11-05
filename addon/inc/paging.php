<?php
function navigator($limit,$back,$page_name,$start,$total_record,$next,$this1,$reminde,$total_page)
{
?>
<div class="btn-toolbar" role="toolbar" aria-label="...">
<table class="Navigator" cellspacing="3" cellpadding="0" width="100%" border="0">
	<tr>
        	<td width="120">
			<strong>Records:</strong> 
			<select name="record_count" id="DropDownList_row_count" onchange="javascript:setTimeout('record()', 0)" >
<?php
    $paging = array('25','50','100','200','300','400','500');
    $x_record_count="";
    foreach ($paging as $value) {
        $x_record_count .= "<option value=\"$value\"";
        if ($limit == "$value"){$x_record_count .= " selected";}
        $x_record_count .= ">" . "$value" . "</option>";
    }
    echo $x_record_count;
?>
                    	</select>
               </td>



          	<td  align="left" 	width="70"><span id="LabelStart"><?php if($back >=0){print "<a id='LabelPrevPage' href='$page_name start=0&limit=$limit&p1=after_post'>&#171;&#171; Start</a>";}?></span></td>
		<td  align="left" 	width="120"><?php if($back >=0){print "<a id='LabelPrevPage' href='$page_name start=$back&limit=$limit&p1=after_post'>&#171; Prev Page</a>";} ?></td>
          	<td  align="center" 	width="160"><strong>Page <span id="LabelCurrentPage"><?php $j=$start /$limit;  echo $j+1;?></span> of <span id="LabelPagesCount"><?php $k= ceil($total_record/$limit); echo $k; ?> ( Total <span style='font-weight: bold;'><?php echo $total_record; ?> Records</span> ) </span></strong></td>
          	<td  align="right" 	width="120"><?php if($this1 < $total_record) {print "<a id='LabelNextPage' href='$page_name start=$next&limit=$limit&p1=after_post'>Next Page &#187;</a>";}?></td>
          	<td  align="right" 	width="70"><span id="LabelEnd"><?php if($reminde>0) {$s_=($limit*$total_page);print "<a id='LabelNextPage' href='$page_name start=$s_&limit=$limit&p1=after_post'>End &#187;&#187;</a>";}?></span></td>



	</tr>
</table>
</div>
</br>
<?php
}
?>