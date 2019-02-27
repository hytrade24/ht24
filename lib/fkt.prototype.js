
/* ###VERSIONSBLOCKINLCUDE### */

function contentUpdate(ajax_url, chElement, meth)
{
	if(!meth)
	  meth = 'get';
  
	req = new Ajax.Request(ajax_url, 
	  {
		  method: meth,
			onCreate: function()
			{
				wait = $('WAIT');
				if(wait)
				{
					wait = wait.innerHTML;
				}
				else(wait)
				{
					wait = 'Bitte warten ...';
				}
				$(chElement).update(wait);
			}, // create()
			onSuccess: function(transport)
			{
				var write_in = $(chElement);
				write_in.update(transport.responseText);
			} // success
	  } // ajax obj.
	); // new Ajax
} // contentUpdate()

function minilogin()
{
	var setvis = 'none';
	var vis = $('minilogin').style.display;
	if(vis == 'none')
	{
		setvis = 'block';
	}
	$('minilogin').style.display = setvis;
}


function ad_top(stat, id_ad, fk_kat, adtable)
{
	var setval = 1;
	if(stat == true) {
		var quest = 'Soll die Anzeige als TOP markiert werden?';
		// Sicherheitsabfrage:
		if(!confirm(quest)) return false;
		
		jQuery('#top_i_'+id_ad+'_'+fk_kat).attr('src', '/admin/gfx/top1.png');
		jQuery('#top_a_'+id_ad+'_'+fk_kat).unbind('click');
		jQuery('#top_a_'+id_ad+'_'+fk_kat)[0].onclick = function() {
		    ad_top(false, id_ad, fk_kat, adtable);
		};
	} else {
		var quest = 'Soll die Anzeige NICHT mehr als TOP markiert werden?';
		// Sicherheitsabfrage:
		if(!confirm(quest)) return false;
		// Top entfernen
		setval = 0;

		jQuery('#top_i_'+id_ad+'_'+fk_kat).attr('src', '/admin/gfx/top0.png');
		jQuery('#top_a_'+id_ad+'_'+fk_kat).unbind('click');
		jQuery('#top_a_'+id_ad+'_'+fk_kat)[0].onclick = function() {
		    ad_top(true, id_ad, fk_kat, adtable);
		};
	}
	var req = new Ajax.Request('/admin/index.php?page=set_topanzeige&frame=ajax&ID_AD='+id_ad+'&TABLE='+adtable+'&FK_KAT='+fk_kat+'&SET='+setval, 
		{
			method: 'get',
			onSuccess: function(transport)
			{
				//alert(transport.responseText);
			}
		});
}