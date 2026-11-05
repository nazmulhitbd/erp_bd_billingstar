
//<?
/**
 *	Globalne funkcje WUI.
 *
 *	Znajduj si?utaj funkcje dla BODY.
 *	@package WUI
 */

// Zmienne globalne
var BR = new Browser();
var HTTPR = new HTTPRequest();
var E = new Event();
var HV = null;
var SV = null;
var editor = null;
var CalendarRef = null;				// globalna referencja do wywoujcego INPUTA dla kalendarzyka
var WUItreeActive = null;			// globalna referencja do aktywnego elementu drzewa
var InputRef = null;				// globalna referencja do wywoujcego INPUTA dla okien popup
var InputHiddenRef = null;			// globalna referencja do wywoujcego hidden INPUTA dla okien popup


/**
 *	Akcje dla onload w BODY
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	po zaadowaniu strony.
 *	@return void
 *	@param void
 */

/**
 *	Akcja dla onmousemove w BODY.
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	przy akcji onmousemove.
 *	@return true;
 *	@param ievent obiekt event, ktry mona obsuy?o poruszeniu przy nim myszk
 */
function Body_onmousemove(ievent)
{
	//if (SV.visible) SV.Update();
	return true;
}
  function isInteger(str)
  {
    if(str.length == 0) return false;

    for(var c = 0; c < str.length; c++)
    {
      digit = str.substring(c, c+1);

      if(digit < "0" || digit > "9") return false;
    }

    return true;
  }
function IsNumeric(sText)
{
  var ValidChars = "0123456789.";
  //var IsNumber=true;
  if(sText.length == 0) return false;
  for (var i = 0; i < sText.length; i++)
  {
    var Char = sText.charAt(i);
    if ((i == 0) && (Char == "-")) // check first character for minus sign
    continue;
    if (ValidChars.indexOf(Char) == -1)
    {
        return false;
    }
  }
  return true;
}
//=====================numeric==============

//==============is empty============
function isEmpty(elem){
	if(elem.length == 0){
		return true;
	}
	return false;
}
//==============end empty===========

/**
 *	Akcja dla onclick w BODY.
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	przy akcji onclick.
 *	@return true;
 *	@param ievent obiekt event, ktry mona obsuy?o jego klikni?u
 */
function Body_onclick(ievent)
{
	if(HV.visible)
	{
		HV.Hide();
	}
	return true;
}

/**
 *	Akcja dla onmouseover w BODY.
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	przy akcji onmouseover.
 *	@return true;
 *	@param ievent obiekt event, ktry mona obsuy?o najechaniu na niego kursorem
 */
function Body_onmouseover(ievent)
{
	E.AttachEvent(ievent);
	mouseX = E.clientX;
	mouseY = E.clientY;
	//if(E.srcElement.getAttribute('hint')!=null)
	//{
		//if(E.srcElement.getAttribute('hint')!='')
		//{
//			clearTimeout(HV.timeoutCounter);
		//	if(!HV.visible)
		//	{
		//		HV.Show(E.clientX+50,E.clientY,E.srcElement.getAttribute('hint'));
		//	}
//			HV.timeoutCounter=setTimeout('HV.Hide()',2000);
		//}
	//}

	return false;
}

/**
 *	Akcja dla onmouseout w BODY.
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	przy akcji onmouseout.
 *	@return true;
 *	@param ievent obiekt event, ktry mona obsuy?o opuszczeniu kursorem obiektu
 */
function Body_onmouseout(ievent)
{
	return true;
}

/**
 *	Akcja dla onmousedown w BODY.
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	przy akcji onmousedown.
 *	@return true;
 *	@param ievent obiekt event, ktry mona obsuy?o wcini?u na nim przycisku myszki
 */
function Body_onmousedown(ievent)
{
	return true;
}

/**
 *	Akcja dla onmouseup w BODY.
 *
 *	Zawarto?ej funkcji zostanie wykonana kadorazowo
 *	przy akcji onmouseup.
 *	@return true;
 *	@param ievent obiekt event, ktry mona obsuy?o opuszczeniu przycisku myszki
 */
function Body_onmouseup(ievent)
{
	return true;
}

/**
 *	Funkcja otwierajca okienko popup z pomoc.
 *
 *	Funkcja otwiera okno z pomoc o podanym ID.
 *	@param itarget HelpId dla zawartoci pomocy
 */
function PopUpHelp(itarget)
{
	var lwin=window.open(dpalias+'ctxhlp/index.aspx?HelpId='+itarget,'Help','location=no,height=400,width=400,status=no,resizable=yes,scrollbars=yes');
	lwin.focus();
}

/**
 *	Kalendarzyk.
 *
 *	Otwiera okno popup z kalendarzykiem.
 *	@param iobj obiekt, do ktrego zostanie przekazana data
 *	@param lang wersja j?kowa ("en", domylnie polska)
 */

// Klasa button
/**
 *	Akcja dla onmouseover w przycisku.
 *
 *	Zmiana stylu przycisku.
 *	@return true;
 *	@param iobj obiekt przycisku
 */
function Button_onmouseover(iobj)
{
	iobj.style.borderStyle='solid';
	return true;
}

/**
 *	Akcja dla onmouseout w przycisku.
 *
 *	Zmiana stylu przycisku.
 *	@return true;
 *	@param iobj obiekt przycisku
 */
function Button_onmouseout(iobj)
{
	iobj.style.borderStyle='outset';
	return true;
}

/**
 *	Akcja dla onclick w przycisku.
 *
 *	Zmiana stylu przycisku.
 *	@return true;
 *	@param iobj obiekt przycisku
 */
function Button_onclick(ievent)
{
	var ltarget;

	E.AttachEvent(ievent);
	E.Cancel();
	ltarget=E.srcElement.getAttribute('target');
	if(ltarget!=null)
	{
		window.location.href=dpalias+ltarget
	}

	return true;
}

/**
 *	Aktywacja rekordu.
 *
 *	Zmiana stylu dla wiersza rekordu.
 *	@return true;
 *	@param iobj obiekt z rekordem.
 */
function RSRecordrow_Activate(iobj)
{
	iobj.className = 'WUIrsrecordrowactive';
	return true;
}

/**
 *	Deaktywacja rekordu.
 *
 *	Zmiana stylu dla wiersza rekordu.
 *	@return true;
 *	@param iobj obiekt z rekordem.
 *	@param ipos pozycja rekordu (jedna z dwch moliwych).
 */
function RSRecordrow_DeActivate(iobj,ipos)
{
	if(ipos=='0')
	{
		iobj.className='WUIrsrecordrowodd';
	}
	else
	{
		iobj.className='WUIrsrecordroweven';
	}
	return true;
}

/**
 *	Przejcie do zakadki.
 *
 *	Wywietlenie zawartoci zakadki oraz zmiana przycisku.
 *	@param id identyfikator zakadki. W WUI jest on zdefiniowany jako atrybut "id" w wui:tabbutton i wui:tab.
 *	@param nd obiekt panelu z zakadkami, z atrybutem Active, ktry zawiera identyfikator aktywnej zakadki.
 *	@param tabbutton referencja do przycisku.
 */
function ShowTab(id, nd, tabbutton)
{
	var tab = window.document.getElementById(id);
	var tabbutton_active = window.document.getElementById("tabbutton_" + nd.Active);
	if (id != nd.Active)
	{
		tabbutton_active.className = "WUItabbutton";
		tabbutton.className = "WUItabbutton_active";
		tab.style.display = "none";
		tab.style.display = "block";
		var tab = window.document.getElementById(nd.Active);
		tab.style.display = "none";
		nd.Active = id;
		ObjClass['tabpanel'] = id;
	}
}

/**
 *	Funkcja sprawdzajca, czy zakadka o podanym identyfikatorze jest aktywna.
 *
 *	Funkcja zwraca [true] jeli zakadka o podanym identyfikatorze jest aktywna.
 *	@param id identyfikator zakadki. W WUI jest on zdefiniowany jako atrybut "id" w wui:tabbutton i wui:tab.
 *	@return bool
 */
function isActiveTab(id)
{
	var tab = window.document.getElementById(id).parentNode;
	if (tab.ActiveTab == id) return true;
	return false;
}


/**
 *	Okienko popup.
 *
 *	Wywietlenie wycentrowanego okienka popup.
 *	@param itarget adres wzgl?y do lokacji
 *	@param width szeroko?kna (domylnie 400)
 *	@param height wysoko?kna (domylnie 400)
 */
function PopUp(itarget, width, height)
{
	width = (width != null) ? width : 400;
	height = (height != null) ? height : 400;
	var top, left;
	top = (screen.height / 2) - (height / 2);
	left = (screen.width / 2) - (width / 2);
	var lwin=window.open(itarget,'','location=no,height='+height+',width='+width+',status=no,resizable=yes,scrollbars=yes,top='+top+',left='+left);
	lwin.focus();
}

function PopUpDel(itarget, width, height)
{
	var col=document.getElementsByTagName("input");
	var l,count=0;

	for(l=0;l<col.length;l++)
	{
//		if(col[l].type=="checkbox" && col[l].id.substring(0,20)=="ItemTemplateCheckbox")
		if(col[l].type=="checkbox" && col[l].id.substring(0,18)=="RepeaterClientList" && col[l].checked)
		{
			count++;
		}
	}
	if(count>0)
	{
		width = (width != null) ? width : 400;
		height = (height != null) ? height : 400;
		var top, left;
		top = (screen.height / 2) - (height / 2);
		left = (screen.width / 2) - (width / 2);
		var lwin=window.open(itarget,'','location=no,height='+height+',width='+width+',status=no,resizable=yes,scrollbars=yes,top='+top+',left='+left);
		lwin.focus();
	}
	else
	{
		alert("You have to select at last one record");
	}
}


function PopUpDelAll(itarget, width, height)
{
	var col=document.getElementsByTagName("input");
	var l,count=0;

		width = (width != null) ? width : 400;
		height = (height != null) ? height : 400;
		var top, left;
		top = (screen.height / 2) - (height / 2);
		left = (screen.width / 2) - (width / 2);
		var lwin=window.open(dpalias+itarget,'','location=no,height='+height+',width='+width+',status=no,resizable=yes,scrollbars=yes,top='+top+',left='+left);
		lwin.focus();
}

/**
 *	Okienko popup do druku.
 *
 *	Wywietlenie okienka popup z zawartoci do druku.
 *	@param itarget adres wzgl?y do lokacji
 */
function PopUpPrint(itarget)
{
	var lwin=window.open(dpalias+itarget,'Print','location=no,height=400,width=400,status=no,resizable=yes,scrollbars=yes');
	lwin.focus();
}

/**
 *	Alert z potwierdzeniem.
 *
 *	Wywietlenie alertu z potwierdzeniem. Funkcja zwraca [true] jeli potwierdzenie zostanie przyj?.
 *	@param str tre?otwierdzenia
 *	@return bool
 */
function AskDelete (str)
{
	var col=document.getElementsByTagName("input");
	var l,count=0;
	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].id.substring(0,18)=="RepeaterClientList" && col[l].checked)
		{
			count++;
		}
	}
	if(count>0)
		{
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
		}
	else
	{
		alert("You have to select at last one record");
        return false;
	}
}

function AskChildrenTrf()
{
	var col=document.getElementsByTagName("input");

	var l,count=0;
	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].id.substring(0,18)=="RepeaterClientList" )
		{
			count++;
		}
	}
	if(count>0)
    {
    	if ((confirm('This deletes the whole sign-in log. It cannot be undone. Delete all records?')==true) && (confirm("Are You sure?")==true))
    	{
    	return true;
    	}
    	else
    	{
    	return false;
    	}
    }
	else
	{
		alert("There are no records to delete");
        return false;
	}
}


function AskDeleteChildrenEdit(str)
{
			if (confirm(str)==true)
			{
				document.getElementById("hiddenDeleteChildren").value="1";
				if(confirm("Are you sure?")==true)
				{
					return true;
				}
				else
				{
					return false;
				}

			}
			else
			{
				document.getElementById("hiddenDeleteChildren").value="0";
				if(confirm("Are you sure?")==true)
				{
					return true;
				}
				else
				{
					return false;
				}
			}
}

function AskDeleteChildren(str)
{

	var col=document.getElementsByTagName("input");

	var l,count=0;

	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].name.substring(0,17)=="RepeaterUsersList" && col[l].checked)
		{

			var temp =col[l].name.indexOf("ItemTemplateCheckbox");
			if(temp!=-1)
			{
				count++;
			}

		}
	}
	if(count>0)
		{
			if (confirm(str)==true)
			{

				document.getElementById("hiddenDeleteChildren").value="1";


				if(confirm("Are you sure?")==true)
				{
					return true;
				}
				else
				{
					return false;
				}

			}
			else
			{
				document.getElementById("hiddenDeleteChildren").value="0";

				if(confirm("Are you sure?")==true)
				{
					return true;
				}
				else
				{
					return false;
				}
			}
		}
	else
	{
		alert("You have to select at last one record");
	}

}

function AskDeleteUser(str)
{
	var col=document.getElementsByTagName("input");

	var l,count=0;

	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].name.substring(0,17)=="RepeaterUsersList" && col[l].checked)
		{
			count++;
		}
	}
	if(count>0)
		{
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
		}
	else
	{
		alert("You have to select at last one record");
	}
}


function AskDeleteRoute (str)
{
	var col=document.getElementsByTagName("input");

	var l,count=0;

	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].name.substring(0,18)=="RepeaterClientList" && col[l].checked)
		{
			var temp =col[l].name.indexOf("ItemTemplateCheckbox");
			if(temp!=-1)
			{
				count++;
			}
		}
	}
	if(count>0)
	{
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
	}
	else
	{

		alert("You have to select at last one record");
	}
}
function AskDeleteInFrame(str)
{
	var col=window.UsersIframe.document.getElementsByTagName("input");

	var l,count=0;

	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].name.substring(0,18)=="RepeaterClientList" && col[l].checked)
		{
			count++;
		}
	}
	if(count>0)
		{
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
		}
	else
	{
		alert("You have to select at last one record.");
	}
}

function AskDeleteIpAddress(str)
{
	var col=window.IpIframe.document.getElementsByTagName("input");

	var l,count=0;

	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].checked)
		{
			count++;
		}
	}
	if(count>0)
		{
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
		}
	else
	{
		alert("You have to select at last one record");
	}
}
function AskDeleteAniNumber(str)
{
	var col=window.AniIframe.document.getElementsByTagName("input");

	var l,count=0;

	for(l=0;l<col.length;l++)
	{
		if(col[l].type=="checkbox" && col[l].checked)
		{
			count++;
		}
	}
	if(count>0)
		{
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
		}
	else
	{
		alert("You have to select at last one record");
	}
}

function AskDeleteNoCheck(str)
{
			if (confirm(str)==true)	{return true;}	else{	return false;	}
}



function refreshAjaxProgress(actionType)
{
    //alert("This task could take a while. You can see progress in Progress Bar in the top window.");
    parent.frames["MenuTop"].document.frames[0].location.href="../../ProgressForm.aspx?actionType="+actionType;
       //parent.frames[0].document.frames[0].location.reload();
       //parent.frames[0].document.getElementById("spanIfr").style.visibility="visible";
}

function AskDeleteClients()
{
    if(AskDeleteFiltered('Are You sure?')==true) {
        refreshAjaxProgress("deleteClients");
     return true;
     }
      else return false;
}

function AskDeleteFiltered(str)
{
        if(!document.getElementById("changeAll_option1").checked)
        if(!document.getElementById("changeAll_option2").checked)
        if(!document.getElementById("changeAll_option3").checked)
        if(!document.getElementById("changeAll_option4").checked)
        if(!document.getElementById("changeAll_option5").checked)
        if(!document.getElementById("changeAll_option6").checked)
        {
            alert("Please select action for Filtered Records");
            return false;
        }
			if (confirm(str)==true)
			{
			return true;
			}
			else
			{
			return false;
			}
}

function Exit(adres)
{
	location.href = adres;

}
function ExitToErrorList(adres,flg)
{
	if(flg==1)
	{
	alert("Errors occurred during import.");
	window.opener.location.href=adres;
	}
	else
	{
	alert("File imported successfully.");
	window.opener.location.href=adres;
	}
}

/**
 *	Okienko z podgldem na grafik?
 *
 *	Wywietlenie okienka popup z grafik.
 *	@param src rdo grafiki
 */
function MediaPreview(src)
{
	var src = new String(src);
	if (src.substring(0, 3) == "url") src = src.substring(4, src.length-1);	// pobierz z background-image poprawna nazwe pliku
	var posx, posy;
	posx = Math.round ((screen.width / 2) - (640 / 2));
	posy = Math.round ((screen.height / 2) - (480 / 2));
	var lwin=window.open(dpalias+'ctxmedia/index.aspx?ImgSrc='+src,'Podgld','location=no,height=480,width=640,status=no,resizable=yes,scrollbars=yes,left='+posx+',top='+posy+'');
	lwin.focus();
}

/**
 *	Podgld grafiki w obiekcie.
 *
 *	Wywietla grafik? podanym obiekcie IMG.
 *	@param url rdo grafiki
 *	@param target referencja do obiektu IMG.
 *	@return true
 */
function WUIfileinput_preview(url, target)
{
	//url = new String (url);
	url = strReplaceAll(url, "\\", "/");
	url = "file:///" + url;
	target.src = url;
	return true;
}

/**
 *	Zmiana cigu znakw.
 *
 *	Funkcja zmieniajca cig znakw na nowy.
 *	@param str zawarto?igu
 *	@param from zawarto?zamie z"
 *	@param to zawarto?zamie na"
 *	@return string
 */
function strReplaceAll(str, from, to)
{
	str = new String (str);
    var idx = str.indexOf(from);
    while (idx > -1)
	{
        str = str.replace(from, to);
        idx = str.indexOf(from );
    }
    return str;
}

/**
 *	Zmiana stanu widocznoci warstwy.
 *
 *	Funkcja ukrywa lub pokazuje warstw? podanym [id] w zlaenoci od obecnego jej stanu.
 *	Jeli jest widoczna, to funkcja j ukrywa, w przeciwnym wypadku (jeli jest ukryta),
 *	warstwa jest chowana.
 *	Funkcja zwraca [true] w przypadku
 *	jeli warstwa zostanie odkryta, [false] - ukryta.
 *	@param id id warstwy lub referencja do obiektu
 *	@return bool
 */
function ShowHideLayer(id)
{
	var div = (typeof id == "string") ? window.document.getElementById(id) : id;
	if (div.style.display != "block")
	{
		div.style.display = "block";
		return true;
	}
		else
		{
			div.style.display = "none";
			return false;
		}
}

/**
 *	Zmiana stanu widocznoci warstwy.
 *
 *	Funkcja ukrywa lub pokazuje warstw? podanym [id]. W przypadku podania drugiego argumentu
 *	jako [true] - warstwa zostanie odkryta, [false] - schowana.
 *	Funkcja zwraca [true] w przypadku
 *	jeli warstwa zostanie odkryta, [false] - ukryta.
 *	@param id id warstwy lub referencja do obiektu
 *	@param status [true|false] stan widocznoci
 *	@return bool
 */

function ShowLayer(id, status)
{
	var div = (typeof id == "string") ? window.document.getElementById(id) : id;
	if(div!=null)
	{
		if (status)
		{
			div.style.display = "block";
			return true;
		}
		else
		{
			div.style.display = "none";
			return false;
		}
	}
}

/**
 *	Pobranie rda zdarzenia.
 *
 *	Funkcja zwracajca rdo (src) zdarzenia [ievent].
 *	@param ievent zdarzenie
 *	@return string
 */
function GetEventSrc (ievent)
{
	if (document.all) return ievent.srcElement;
	return ievent.target;
}

// Klasa Browser
function Browser()
{
	if(document.all)
	{
		this.browser='IE';
		this.valid=true;
	}
	else if(document.getElementById)
	{
		this.browser='NS';
		this.valid=true;
	}
	else
	{
		this.browser='NONE';
		this.valid=false;
	}
}

// Klasa HintView

function HintView()
{
	this.visible=false;
	this.enabled=true;
	this.timeoutCounter;
	this.div=document.createElement('DIV');
	this.title=document.createElement('DIV');
	this.content=document.createElement('DIV');
	this.div.style.display='none';
	this.div.className='Hint';
	this.title.className='HintTitle';
	this.content.className='HintContent';
	this.title.innerHTML='Pomoc';
	this.div.appendChild(this.title);
	this.div.appendChild(this.content);
	if(document.body)
	{
		document.body.appendChild(this.div);
	}
	this.Show=HintView_Show;
	this.Hide=HintView_Hide;
	this.SwitchEnabled=HintView_SwitchEnabled;
}

function HintView_Show(ix,iy,imsg)
{
	if(this.enabled)
	{
		this.content.innerHTML=imsg;
		this.div.style.left=ix+'px';
		this.div.style.top=iy+'px';
		this.div.style.display='';
		this.visible=true;
	}
}

function HintView_Hide()
{
	this.div.style.display='none';
	this.visible=false;
}

function HintView_SwitchEnabled()
{
	if(this.enabled)
	{
		this.enabled=false;
	}
	else
	{
		this.enabled=true;
	}
}

// Klasa Event

function Event()
{
	this.AttachEvent=Event_AttachEvent;
	this.Cancel=Event_Cancel;
	this.event_obj=null;
}

function Event_AttachEvent(ievent)
{
	this.event_obj=ievent;
	this.clientX=this.event_obj.clientX;
	this.clientY=this.event_obj.clientY;
	this.screenX=this.event_obj.screenX;
	this.screenY=this.event_obj.screenY;
	this.altKey=this.event_obj.altKey;
	this.ctrlKey=this.event_obj.ctrlKey;
	if(BR.browser=='IE')
	{
		this.srcElement=this.event_obj.srcElement;
		this.offsetX=this.event_obj.offsetX;
		this.offsetY=this.event_obj.offsetY;
	}
	else
	{
		this.srcElement=this.event_obj.target;
	}
}

function Event_Cancel()
{
	if(BR.browser=='IE')
	{
		this.event_obj.returnValue=false;
	}
	else
	{
		this.event_obj.stopPropagation();
	}
}

// Klasa HTTPRequest

function HTTPRequest()
{
	this.img=new Image();
	this.Get=HTTPRequest_Get;
	this.Post=HTTPRequest_Post;
	this.status=0;
}

function HTTPRequest_Get(iurl)
{
	this.img.src=iurl;
}

function HTTPRequest_Post(iurl)
{
	this.img.src=iurl;
}

/**
 *	Nawigator.
 *
 *	Funkcja adujca aktywny szablon z podanym [ivalue] limitem rekordw
 *	z zapytania [idsname].
 *	@param ivalue limit rekordw
 *	@param idsname nazwa zapytania SQL
 *	@return true
 */
function NavigatorChangeLimit(ivalue,idsname)
{
	window.location.href=dpalias+tmplpath+'?'+idsname+'.limit='+ivalue+'&'+idsname+'.offset=0';
	return true;
}

// Klasa SmokeView

function SmokeView()
{
	this.padding = 10;
	this.width = 100;

	this.visible=false;
	this.div=document.createElement('DIV');
	this.div.className='WUIhelp_smoke';
	this.div.style.width = this.width + "px";
	this.div.style.padding = this.padding + "px";
	this.div.style.display = 'none';
	if(document.body)
	{
		document.body.appendChild(this.div);
	}
	this.Show=SmokeView_Show;
	this.Hide=SmokeView_Hide;
	this.Update = SmokeView_Update;
}

function SmokeView_Show(msg)
{
	var posX = mouseX + this.padding;
	var posY = mouseY + this.padding;
	if (posX + this.width >= screen.width) posX -= (this.width + 20);
	this.div.innerHTML = msg;
	this.div.style.left = posX + 'px';
	this.div.style.top = posY + 'px';
	this.div.style.display = '';
	this.visible = true;
	setTimeout('SV.Hide()', 1000);
	//this.focus();
}

function SmokeView_Update()
{
	/*
	this.div.style.left=mouseX + this.padding + 'px';
	this.div.style.top=mouseY + this.padding + 'px';
	*/
}

function SmokeView_Hide()
{
	this.div.style.display='none';
	this.visible=false;
}

/**
 *	Wykonywanie akcji.
 *
 *	Funkcja wykonuje akcj?istr] w podanej
 *	ramce [target] (moe by?omini?).
 *	@param istr nazwa akcji
 *	@param target nazwa ramki
 *	@return true
 */
function LoadAction(istr, target)
{
	dpalias = "../";
	if (target != null) parent.frames[target].location.href = dpalias+tmplpath+'?Act='+istr;
	else window.location.href=dpalias+tmplpath+'?Act='+istr;
	return true;
}

/**
 *	adowanie szablonu.
 *
 *	Funkcja aduje szablon [istr] do podanej
 *	ramki [target] (moe by?omini?).
 *	@param istr nazwa szablonu
 *	@param target nazwa ramki
 *	@return true
 */
function LoadTemplate(istr, target)
{
//	if (target != null) parent.frames[target].location.href = dpalias + istr;
	if (target != null) parent.frames[target].location.href = istr;
//	else window.location.href = dpalias + istr;
	else window.location.href = istr;
	return true;
}

/**
 *	Zmiana stanu ga? drzewa.
 *
 *	Funkcja zmienia stan ga? drzewa (zwija lub rozwija).
 *	@param iobj referencja do obiektu z ga? drzewa
 *	@param img referencja do grafiki stanu ga? (zwini?/rozwini?)
 */
function WUItreeShowHideNode(iobj, img, current_Theme)
{
	var imgSrc = img.src;
	if (iobj.style.display == "none")
	{
		iobj.style.display = "block";
		img.src = "/billing/App_Themes/"+current_Theme+"/img/tree/tree_node_minus_on.gif";
	}
	else
	{
		iobj.style.display = "none";
		img.src = "/billing/App_Themes/"+current_Theme+"/img/tree/tree_node_plus_on.gif";
	}
}

/**
 *	Aktywowanie elementu drzewa.
 *
 *	Funkcja aktywuje element drzewa [iobj] (zmienia kolor ta, grafik?
 *	Zwraca [false] w przypadku, gdy podany element jest ju aktywny,
 *	w przeciwnym wypadku zwraca [true].
 *	@param iobj referencja do elementu drzewa
 *	@return bool
 */
function WUItreeSetActive(iobj)
{
	if (WUItreeActive == iobj) return false;
	var img = iobj.parentNode.lastChild.firstChild;
	var imgSrc = img.src;
	if (WUItreeActive != null)
	{
		WUItreeActive.parentNode.firstChild.style.display = "none";
		WUItreeActive.parentNode.lastChild.lastChild.style.color = "";
		var imgOld = WUItreeActive.parentNode.lastChild.firstChild;
		var imgOldSrc = imgOld.src;
		imgOld.src = imgOldSrc.substring(0, imgOldSrc.length - 7) + ".gif";
	}
	iobj.parentNode.firstChild.style.display = "block";
	iobj.parentNode.lastChild.lastChild.style.color = "#fff";
	if (imgSrc.substring(imgSrc.length - 7, imgSrc.length + 7) != "_on.gif") img.src = imgSrc.substring(0, imgSrc.length - 4) + "_on.gif";
	//iobj.lastChild.style.backgroundColor = "#2D4173";
	//iobj.lastChild.style.color = "#fff";
	WUItreeActive = iobj;
	return true;
}

/**
 *	Zmiana stanu grupy checkbox.
 *
 *	Funkcja zmienia stan grupy checkbox o podanej nazwie [iname]
 *	na stan o podanym [check]
 *	@param iname nazwa grupy checkbox
 *	@param check stan zaznaczenia checkbox [bool]
 */
function CheckAll(iname, check)
{
	var checkbox = window.document.getElementsByName(iname);
	for (var x = 0; x < checkbox.length; x++) checkbox[x].checked = check;
}

/**
 *	Pobranie aktualnej daty.
 *
 *	Funkcja zwraca aktualn dat?w formacie "YYYY-MM-DD").
 *	@return string
 */
function getNowDate()
{
	var data = new Date(), data_str;
	data_str = data.getFullYear();
	data_str += "-";
	data_str += (data.getMonth()+1 < 10) ? "0"+(data.getMonth()+1) : (data.getMonth()+1);
	data_str += "-";
	data_str += (data.getDate() < 10) ? "0"+data.getDate() : data.getDate();
	return data_str;
}

/**
 *	Wywietlenie b?
 *
 */


function showError(ErrorMessage, SQLErrorMessage)
{
	out = "";
	if(ErrorMessage != "")
	{
		out += ErrorMessage;
		if(SQLErrorMessage != "")
		{
			out += "\n\n";
		}
	}
	if(SQLErrorMessage != "")
	{
		out += SQLErrorMessage;
	}
	alert(out);
}

function ChangeDateFilter(combo)
{
    var selIndex = document.getElementById('SelectDate').selectedIndex;
    comboValue = document.getElementById('SelectDate').options[selIndex].value;

    if(comboValue!="0")
    {
        document.getElementById('tblFrom').style.visibility="hidden";
        document.getElementById('tblTo').style.visibility="hidden";
    } else
    {
        document.getElementById('tblFrom').style.visibility="";
        document.getElementById('tblTo').style.visibility="";
    }
}

function goto(url) {
    window.location=url;
}


//?>
