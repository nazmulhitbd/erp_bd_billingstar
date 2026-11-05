//<?
/**
 *	Zakładki.
 *	
 *	Zakładki Outlook.
 *	@package OutlookBar
 */

/*
* zmienne globalne
* */
var WUIoutbar = new WUI_outbar();

/**
 *	Akcje dla onload w BODY
 *
 *	Zawartość tej funkcji zostanie wykonana każdorazowo
 *	po załadowaniu strony. 
 *	@return void
 *	@param void  
 */
function WUI_outbar()
{
	// atrybuty
	this.ActiveObj = null;			// object
	this.AnimateObj = null;			// object
	this.AnimTime = null;			// int
	this.MaxHeightDefault = 430;	// int
	this.AnimStep = 50;				// int
	this.Animation = false;			// bool
	this.item_nr;					// int
	
	// metody
	this.HideBar = WUI_outbar_HideBar;
	this.ShowBar = WUI_outbar_ShowBar;
}

function WUI_outbar_ShowBar (obj)
{
	HTTPR.Get(dpalias + "/xmlhttp/blank.aspx?profile.baritem_nr=" + this.item_nr);
	this.AnimateObj = obj;
	this.AnimateObj.style.height = "0px";
	this.AnimateObj.style.overflow = "hidden";
	this.AnimateObj.style.display = "block";
	this.AnimTime = setInterval('WUI_outbar_ShowAnimate()', 1);
}

function WUI_outbar_HideBar (obj)
{
	HTTPR.Get(dpalias + "/xmlhttp/blank.aspx?profile.baritem_nr=0");
	this.AnimateObj = obj;
	this.AnimateObj.style.display = "block";
	this.AnimateObj.style.overflow = "hidden";
	this.AnimTime = setInterval('WUI_outbar_HideAnimate()', 1);
}

function WUI_outbar_ShowAnimate()
{
	// odkryj warstwe
	var temp = parseInt(WUIoutbar.AnimateObj.style.height);
	WUIoutbar.AnimateObj.style.height = (temp + WUIoutbar.AnimStep) + "px";
	if (!WUIoutbar.MaxHeight) WUIoutbar.MaxHeight = WUIoutbar.MaxHeightDefault;
	if (temp >= WUIoutbar.MaxHeight)
	{
		WUIoutbar.AnimateObj.style.height = WUIoutbar.MaxHeight + "px";
		window.clearInterval(WUIoutbar.AnimTime);
		if (WUIoutbar.Other != false) WUIoutbar.Other.style.display = "none";
		WUIoutbar.Animation = false;
	}

	// chowaj aktywna warstwe
	if (WUIoutbar.Other != false)
	{
		var temp = parseInt (WUIoutbar.Other.style.height);
		if (temp <= WUIoutbar.AnimStep)
		{
			WUIoutbar.Other.style.display = "none";
			WUIoutbar.Animation = false;
		}
		else if (parseInt (WUIoutbar.AnimateObj.style.height) <= WUIoutbar.AnimStep) WUIoutbar.Other.style.height = (temp - WUIoutbar.AnimStep - 20) + "px";
		else WUIoutbar.Other.style.height = (temp - WUIoutbar.AnimStep) + "px";
	}
}

function WUI_outbar_HideAnimate()
{
	var temp = parseInt(WUIoutbar.AnimateObj.style.height);
	if (temp <= WUIoutbar.AnimStep)
	{
		window.clearInterval(WUIoutbar.AnimTime);
		WUIoutbar.AnimateObj.style.display = "none";
		WUIoutbar.Animation = false;
	}
	else WUIoutbar.AnimateObj.style.height = (temp - WUIoutbar.AnimStep) + "px";
}

/*
*	Zdarzenia dla myszki.
**/

function WUI_outbar_onclick(obj)
{
	// Sprawdz, czy animacja zakladek jest aktywna
	if (WUIoutbar.Animation) return false;

	var TextDiv = obj.parentNode.childNodes[1];
	//if (WUIoutbar.MaxHeight == null) WUIoutbar.MaxHeight = parseInt(TextDiv.style.height);
	var Button = obj;
	var Img = obj.childNodes[1];
	WUIoutbar.Animation = true;

	// ukryj aktywna zakladke
	if (WUIoutbar.ActiveObj != null && WUIoutbar.ActiveObj != obj)
	{
		WUIoutbar.ActiveObj.childNodes[1].src = "/sysimg/ddmenu_sub.gif";
		/*
		*	Ustawienie zmiennej przechowujacej aktywna
		*	zakladke. Potrzebne do jej dalszej "obrobki" przy chowaniu warstwy.
		**/		
		WUIoutbar.Other = WUIoutbar.ActiveObj.parentNode.childNodes[1];
	} else WUIoutbar.Other = false;

	if (TextDiv.style.display == "block")
	{
		WUIoutbar.HideBar (TextDiv);
		Img.src = "/sysimg/ddmenu_sub.gif";
	}
	else
	{
		Img.src = "/sysimg/ddmenu_sub_down.gif";
		WUIoutbar.ShowBar (TextDiv);
	}
	WUIoutbar.ActiveObj = obj;
}

function WUI_outbar_onmousedown(obj)
{
	obj.style.borderStyle = "inset";
}

function WUI_outbar_onmouseout(obj)
{
	obj.style.borderStyle = "outset";
}

//?>
