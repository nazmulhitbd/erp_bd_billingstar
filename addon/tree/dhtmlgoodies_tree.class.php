<?php
class dhtmlgoodies_tree{
	var $elementArray = array();
	var $nameOfCookie = "dhtmlgoodies_expanded"; // Name of the cookie where the expanded nodes are stored.

	function dhtmlgoodies_tree()
	{
	}

	function writeCSS()
	{
?>

		<?php
	}

	function writeJavascript()
	{
		?>
		<script type="text/javascript">






		var nameOfCookie = '<?php echo $this->nameOfCookie; ?>';
		<?php
		$cookieValue = "";
		if(isset($_COOKIE[$this->nameOfCookie]))$cookieValue = $_COOKIE[$this->nameOfCookie];
		echo "var initExpandedNodes =\"".$cookieValue."\";\n";
		?>
		function Get_Cookie(name) {
		   var start = document.cookie.indexOf(name+"=");
		   var len = start+name.length+1;
		   if ((!start) && (name != document.cookie.substring(0,name.length))) return null;
		   if (start == -1) return null;
		   var end = document.cookie.indexOf(";",len);
		   if (end == -1) end = document.cookie.length;
		   return unescape(document.cookie.substring(len,end));
		}
		// This function has been slightly modified
		function Set_Cookie(name,value,expires,path,domain,secure) {
			expires = expires * 60*60*24*1000;
			var today = new Date();
			var expires_date = new Date( today.getTime() + (expires) );
		    var cookieString = name + "=" +escape(value) +
		       ( (expires) ? ";expires=" + expires_date.toGMTString() : "") +
		       ( (path) ? ";path=" + path : "") +
		       ( (domain) ? ";domain=" + domain : "") +
		       ( (secure) ? ";secure" : "");
		    document.cookie = cookieString;
		}
		/*
		End downloaded cookie functions
		*/

		function expandAll()
		{
			var treeObj = document.getElementById('dhtmlgoodies_tree');
			var images = treeObj.getElementsByTagName('IMG');
			for(var no=0;no<images.length;no++){
				if(images[no].className=='tree_plusminus' && images[no].src.indexOf(plusNode)>=0)expandNode(false,images[no]);
			}
		}
		function collapseAll()
		{
			var treeObj = document.getElementById('dhtmlgoodies_tree');
			var images = treeObj.getElementsByTagName('IMG');
			for(var no=0;no<images.length;no++){
				if(images[no].className=='tree_plusminus' && images[no].src.indexOf(minusNode)>=0)expandNode(false,images[no]);
			}
		}


		function expandNode(e,inputNode)
		{
			if(initExpandedNodes.length==0)initExpandedNodes=",";
			if(!inputNode)inputNode = this;
			if(inputNode.tagName.toLowerCase()!='img')inputNode = inputNode.parentNode.getElementsByTagName('IMG')[0];

			var inputId = inputNode.id.replace(/[^\d]/g,'');

			var parentUl = inputNode.parentNode;
			var subUl = parentUl.getElementsByTagName('UL');

			if(subUl.length==0)return;
			if(subUl[0].style.display=='' || subUl[0].style.display=='none'){
				subUl[0].style.display = 'block';
				inputNode.src = minusNode;
				initExpandedNodes = initExpandedNodes.replace(',' + inputId+',',',');
				initExpandedNodes = initExpandedNodes + inputId + ',';

			}else{
				subUl[0].style.display = '';
				inputNode.src = plusNode;
				initExpandedNodes = initExpandedNodes.replace(','+inputId+',',',');
			}
			Set_Cookie(nameOfCookie,initExpandedNodes,60);
		}

		function initTree()
		{
			// Assigning mouse events
			var parentNode = document.getElementById('dhtmlgoodies_tree');
			var lis = parentNode.getElementsByTagName('LI'); // Get reference to all the images in the tree
			for(var no=0;no<lis.length;no++){
				var subNodes = lis[no].getElementsByTagName('UL');
				if(subNodes.length>0){
					lis[no].childNodes[0].style.visibility='visible';
				}else{
					lis[no].childNodes[0].style.visibility='hidden';
				}
			}

			var images = parentNode.getElementsByTagName('IMG');
			for(var no=0;no<images.length;no++){
				if(images[no].className=='tree_plusminus')images[no].onclick = expandNode;
			}

			var aTags = parentNode.getElementsByTagName('A');
			var cursor = 'pointer';
			if(document.all)cursor = 'hand';
			for(var no=0;no<aTags.length;no++){
				//aTags[no].onclick = expandNode;
				aTags[no].style.cursor = cursor;
			}
			var initExpandedArray = initExpandedNodes.split(',');

			for(var no=0;no<initExpandedArray.length;no++){
				if(document.getElementById('plusMinus' + initExpandedArray[no])){
					var obj = document.getElementById('plusMinus' + initExpandedArray[no]);
					expandNode(false,obj);
				}
			}

            for(var no1=0;no1<images.length;no1++)
            {
             if(images[no1].className=='tree_plusminus' && images[no1].src.indexOf(plusNode)>=0)expandNode(false,images[no1]);
            }
		}


function test(url)
{
    //window.open(url,t)
    parent.frm_main.location.href=url;
}

		window.onload = initTree;

		</script>
		<?php

	}



	/*
	This function adds elements to the array
	*/

	function addToArray($id,$name,$parentID,$url="",$target="",$imageIcon="../addon/images/dhtmlgoodies_folder.gif"){
		if(empty($parentID))$parentID=0;
		$this->elementArray[$parentID][] = array($id,$name,$url,$target,$imageIcon);
	}

	function drawSubNode($parentID){
		if(isset($this->elementArray[$parentID])){
			echo "<ul>";
			for($no=0;$no<count($this->elementArray[$parentID]);$no++){
				$urlAdd = "";
                $a="#Vm0weE1GbFhSWGxTYmtwUVZtMOONVNVMWxyVm5kVmJBILLINGGcHlWV3RLVUZWVU1Eaz0#";
				if($this->elementArray[$parentID][$no][2]){
					$urlAdd = " href=\"".$a."\"";
                    $urlAdd.=" onclick=\"test('".$this->elementArray[$parentID][$no][2]."')\"";
					if($this->elementArray[$parentID][$no][3])$urlAdd.=" target=\"".$this->elementArray[$parentID][$no][3]."\"";
				}
				echo "<li class=\"tree_node\"><img class=\"tree_plusminus\" id=\"plusMinus".$this->elementArray[$parentID][$no][0]."\" src=\"../addon/images/dhtmlgoodies_plus.gif\"><img src=\"".$this->elementArray[$parentID][$no][4]."\"><a class=\"tree_link\"$urlAdd>".$this->elementArray[$parentID][$no][1]."</a>";
				$this->drawSubNode($this->elementArray[$parentID][$no][0]);
				echo "</li>";
			}
			echo "</ul>";
		}
	}

	function drawTree(){
		echo "<div id=\"dhtmlgoodies_tree\">";
		echo "<ul id=\"dhtmlgoodies_topNodes\">";
		for($no=0;$no<count($this->elementArray[0]);$no++){
			$urlAdd = "";
			if($this->elementArray[0][$no][2]){
				$urlAdd = " href=\"".$this->elementArray[0][$no][2]."\"";
				if($this->elementArray[0][$no][3])$urlAdd.=" target=\"".$this->elementArray[0][$no][3]."\"";
			}
			echo "<li class=\"tree_node\" id=\"node_".$this->elementArray[0][$no][0]."\"><img id=\"plusMinus".$this->elementArray[0][$no][0]."\" class=\"tree_plusminus\" src=\"../addon/images/dhtmlgoodies_plus.gif\"><img src=\"".$this->elementArray[0][$no][4]."\"><a class=\"tree_link\"$urlAdd>".$this->elementArray[0][$no][1]."</a>";
			$this->drawSubNode($this->elementArray[0][$no][0]);
			echo "</li>";
		}
		echo "</ul>";
		echo "</div>";
	}
}
?>