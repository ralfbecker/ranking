/**
 * eGroupWare digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-11 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * move +/-/top in the heigth input to the plus-select-box
 *
 * @param height input-object
 * @param plus_sel_id id of the plus-select-box
 */
function handle_plus(height,plus_sel_id)
{
	if (height.value.match(/(t|top|\+|\-)$/i))
	{
		var plus = height.value.replace(/(.*)(t|top|\+|\-)$/i,'$2');

		document.getElementById(plus_sel_id).value = plus == '+' ? 1 : (plus == '-' ? -1 : TOP_PLUS);
		height.value = height.value.replace(/(.*)(t|top|\+|\-)$/i,'$1');
	}
}

/**
 * onChange of top: if bonus not set, set it to the same number of tries as top
 *  if top < bonus alert user and set top to bonus
 *
 * @param top top select box
 */
function check_top(top)
{
	var bonus = document.getElementById(top.name.replace(/top/g,'zone'));

	if (bonus && (!bonus.value || bonus.value == '0') && parseInt(top.value) > 0) bonus.value = top.value;

	if (bonus && parseInt(top.value) > 0 && parseInt(top.value) < parseInt(bonus.value))
	{
		alert('Top < Bonus!');
		bonus.value = top.value;
	}
}

/**
 * onChange of tops: if bonus not set, set it to the same number of tops
 *  if tops > zones alert user and set tops to zones or 
 *  if less tries then tops alert user and set tries to tops
 *
 * @param top top select box
 */
function check_tops(top)
{
	var bonus = document.getElementById(top.name.replace(/tops/g,'zones'));

	if (bonus && !bonus.value && parseInt(top.value) > 0) bonus.value = top.value;

	if (bonus && parseInt(top.value) > 0 && parseInt(top.value) > parseInt(bonus.value))
	{
		alert('Top > Bonus!');
		bonus.value = top.value;
	}
	
	var tries = document.getElementById(top.name.replace(/tops/g,'top_tries'));
	
	if (tries && !tries.value && parseInt(top.value) > 0) tries.value = top.value;

	if (tries && parseInt(top.value) > 0 && parseInt(top.value) > parseInt(tries.value))
	{
		alert('Top > Tries!');
		tries.value = top.value;
	}
}

/**
 * onChange of bonus: dont allow to set a bonus bigger then top or no bonus, but a top
 *
 * @param top top select box
 */
function check_bonus(bonus)
{
	var top = document.getElementById(bonus.name.replace(/zone/g,'top'));

	if (top && parseInt(top.value) > 0 && parseInt(bonus.value) > parseInt(top.value))
	{
		alert('Bonus > Top!');
		top.value = bonus.value;
	}
	if (top && parseInt(top.value) > 0 && !bonus.value)
	{
		top.value = bonus.value;
	}
}

/**
 * onChange of zones: dont allow to set a zones bigger then tops or no zones, but a top
 * 	or if less tries then zones alert user and set tries to zones
 *
 * @param top top select box
 */
function check_zones(bonus)
{
	var top = document.getElementById(bonus.name.replace(/zones/g,'tops'));

	if (top && parseInt(top.value) > 0 && parseInt(bonus.value) < parseInt(top.value))
	{
		alert('Bonus < Top!');
		top.value = bonus.value;
	}
	if (top && parseInt(top.value) > 0 && !bonus.value)
	{
		top.value = bonus.value;
	}
	
	var tries = document.getElementById(bonus.name.replace(/zones/g,'zone_tries'));
	
	if (tries && !tries.value && parseInt(bonus.value) > 0) tries.value = bonus.value;

	if (tries && parseInt(bonus.value) > 0 && parseInt(bonus.value) > parseInt(tries.value))
	{
		alert('Bonus > Tries!');
		tries.value = bonus.value;
	}
}

/**
 * Start the time measurement
 * 
 * @param button
 * @param athlete
 */
function start_time_measurement(button,athlete)
{
	set_style_by_class('td','ajax-loader','display','inline');
	document.getElementById('msg').innerHTML='Time measurement';

	xajax_doXMLHTTP('ranking.uiresult.ajax_time_measurement',button.form.etemplate_exec_id.value,athlete);
}

/**
 * LEAD measurement
 */

var TOP_HEIGHT = 999;	// everything >= TOP_HEIGHT means top (server defines it as 99999999 for DB!)
var TOP_PLUS = 9999;	// option-value of 'Top' in plus selectbox

/**
 * Change athlete, onchange for ahtlete selection
 * 
 * @param selectbox
 */
function change_athlete(selectbox)
{
	if (selectbox.value)
	{
		unmark_holds();
		xajax_doXMLHTTP('ranking_measurement::ajax_load_athlete', selectbox.value, { 'exec[result_height]': 'result_height', 'exec[result_plus]': 'result_plus' });
	}
	else
	{
		document.getElementById('exec[result_height]').value = '';
		document.getElementById('exec[result_plus]').value = '';
	}
}

/**
 * Update athlete, send new result to server
 * 
 * @param boolean scroll_mark=true
 */
function update_athlete(scroll_mark)
{
	var PerId = document.getElementById('exec[nm][PerId]').value;
	
	if (PerId)
	{
		var height = document.getElementById('exec[result_height]');
		var plus   = document.getElementById('exec[result_plus]');
		// top?
		if (plus.value == TOP_PLUS || height.value >= TOP_HEIGHT)
		{
			height.value = '';
			plus.value   = TOP_PLUS;
		}
		if (typeof scroll_mark == 'undefined' || scroll_mark)
		{	 
			var holds = getHoldsByHeight(plus.value == TOP_PLUS ? TOP_HEIGHT : height.value);
			if (holds.length)
			{
				holds[0].scrollIntoView(false);
				mark_holds(holds);
			}
		}
		xajax_doXMLHTTP('ranking_measurement::ajax_update_result', PerId, { 'result_height': height.value, 'result_plus': plus.value}, 1);				
	}
}

/**
 * Mark holds: red and bold
 * 
 * @param holds
 */
function mark_holds(holds)
{
	$j(holds).css({'color':'red','font-weight':'bold'});
}

/**
 * Unmark holds: black and normal
 * 
 * @param holds holds to mark, default all
 */
function unmark_holds(holds)
{
	if (typeof holds == 'undefined') holds = $j('div.topoHandhold');

	$j(holds).css({'color':'black', 'font-weight':'normal'});
}

/**
 * Load topo
 * 
 * @param string path
 * @param array holds handholds to display or undefined to query them from the server
 */
function load_topo(path)
{
	var topo = document.getElementById('topo');

	remove_handholds();
	
	topo.src = window.egw_webserverUrl+(path ? '/webdav.php'+path : '/phpgwapi/templates/default/images/transparent.png');

	if (path) xajax_doXMLHTTP('ranking_measurement::ajax_load_topo',path);
}

/**
 * Handler for a click on the topo image
 * 
 * @param eventObject e
 */
function topo_clicked(e)
{
	//console.log(e);
	
	var topo = e.target;
	//console.log(topo);
	
	var PerId = document.getElementById('exec[nm][PerId]').value;
	
	if (!PerId)
	{
		//$j('#msg').text('topo_clicked() x='+e.offsetX+'/'+topo.width+', y='+e.offsetY+'/'+topo.height);
		//add_handhold({'xpercent':100.0*e.offsetX/topo.width, 'ypercent': 100.0*e.offsetY/topo.height, 'height': 'Test'});
		xajax_doXMLHTTP('ranking_measurement::ajax_save_hold',{'xpercent':100.0*e.offsetX/topo.width, 'ypercent': 100.0*e.offsetY/topo.height});
	}
	else
	{
		// measurement mode
	}
}

var activ_hold;

/**
 * Handler for a click on a hold
 * 
 * @param eventObject e
 */
function hold_clicked(e)
{
	active_hold = e.target;	// hold container
	active_hold = $j(active_hold.nodeName != 'DIV' ? active_hold.parentNode : activeNode);	// img or span clicked, not container div itself
	//console.log(active_hold);
	//console.log(active_hold.data('hold'));
	
	var PerId = document.getElementById('exec[nm][PerId]').value;
	
	if (!PerId)	// edit topo mode
	{
		var popup = document.getElementById('exec[hold_popup]');
		var height = document.getElementById('exec[hold_height]');
		var top = document.getElementById('exec[hold_top]');
		
		popup.style.display = 'block';
		if (!(height.disabled = top.checked = active_hold.data('hold').height == TOP_HEIGHT))
		{
			height.value = active_hold.data('hold').height;
		}
	}
	else	// measuring an athlete
	{
		document.getElementById('exec[result_height]').value = active_hold.data('hold').height;
		document.getElementById('exec[result_plus]').value = '0';

		mark_holds(active_hold);
		update_athlete(false);	// false = no automatic scroll	
	}
}

/**
 * Close hold popup and unset active_hold
 */
function hold_popup_close()
{
	document.getElementById('exec[hold_popup]').style.display='none';
	active_hold = null;
}

/**
 * Submit hold popup (and close it)
 * 
 * @param button
 */
function hold_popup_submit(button)
{
	if (button.name == 'exec[button][save]')
	{
		active_hold.data('hold').height = document.getElementById('exec[hold_top]').checked ?
			TOP_HEIGHT : document.getElementById('exec[hold_height]').value;
		xajax_doXMLHTTP('ranking_measurement::ajax_save_hold',active_hold.data('hold'));
	}
	else
	{
		xajax_doXMLHTTP('ranking_measurement::ajax_delete_hold',active_hold.data('hold').hold_id);
	}
	active_hold.remove();
	hold_popup_close();
}

/**
 * Display a single handhold
 * 
 * @param object hold
 */
function add_handhold(hold)
{
	//console.log('add_handhold({xpercent: '+hold.xpercent+', ypercent: '+hold.ypercent+', height: '+hold.height+'})');
	// as container has a fixed height with overflow: auto, we have to scale ypercent to it
	var y_ratio = $j('#topo').height() / $j('div.topoContainer').height();
	//console.log('#topo.height='+$j('#topo').height()+' / div.topoContainer.height='+$j('div.topoContainer').height()+' = '+y_ratio);
	var container = document.createElement('div');
	container.className = 'topoHandhold';
	container.style.left = hold.xpercent+'%';
	container.style.top = (y_ratio*hold.ypercent)+'%';
	container.title = hold.height >= TOP_HEIGHT ? 'Top' : hold.height;
	var img = document.createElement('img');
	img.src = window.egw_webserverUrl+'/ranking/templates/default/images/griff32.png';
	$j(container).append(img);
	var span = document.createElement('span');
	$j(span).text(hold.height >= TOP_HEIGHT ? 'Top' : hold.height);
	$j(container).append(span);
	$j(container).data('hold',hold);
	$j(container).click(hold_clicked);
	
	$j('div.topoContainer').append(container);
}

/**
 * Display an array of handholds
 * 
 * @param array holds
 */
function show_handholds(holds)
{
	for(var i = 0; i < holds.length; ++i)
		add_handhold(holds[i]);
}

function remove_handholds()
{
	$j('div.topoContainer div').remove();
}

/**
 * Recalculate handhold position, eg. when window get's resized or topo image is loaded
 * 
 * Required because topo image is scaled to width:100% AND displayed in a container div with fixed height and overflow:auto
 */
function recalc_handhold_positions()
{
	// resize topoContainer to full page height
	var topo_pos = $j('div.topoContainer').offset();
	$j('div.topoContainer').height($j(window).height()-topo_pos.top-$j('#divGenTime').height()-$j('#divPoweredBy').height()-20);

	var y_ratio = $j('#topo').height() / $j('div.topoContainer').height();	
	$j('div.topoHandhold').each(function(index,container){
		container.style.top = (y_ratio*$j(container).data('hold').ypercent)+'%';
	});
}

/**
 * Transform topo for printing and call print
 */
function print_topo()
{
	$j('div.topoContainer').width('18cm');	// % placed handholds do NOT work with % with on print!
	$j('div.topoContainer').css('height','auto');
	$j('div.topoContainer').css('visible');
	
	recalc_handhold_positions();
	
	window.focus();
	window.print();
}

/**
 * Get holds with a given height
 * 
 * @param int|float height
 * @returns array
 */
function getHoldsByHeight(height)
{
	height = parseFloat(height);
	//console.log('getHoldsByHeight('+height+')');
	return $j('div.topoHandhold').filter(function() {
		return $j(this).data('hold').height == height;
	});
}

/**
 * Init topo stuff, get's call on document.ready via $GLOBALS['egw_info']['flags']['java_script']
 * 
 * @param array holds
 */
function init_topo(holds)
{
	$j(window).resize(recalc_handhold_positions);
	$j('#topo').load(recalc_handhold_positions);
	$j('#topo').click(topo_clicked);
	if (holds && holds.length) show_handholds(holds);
	
	// mark current athlets height
	var height = parseFloat(document.getElementById('exec[result_height]').value);
	var plus = document.getElementById('exec[result_plus]').value;
	var current = getHoldsByHeight(plus == TOP_PLUS ? TOP_HEIGHT : (height ? height : 1));
	if (current.length) {
		current[0].scrollIntoView(false);
		if (height || plus == TOP_PLUS) mark_holds(current);
	}
}

/**
 * Boulder measurement
 */

/**
 * [Try] button clicked --> update number
 * 
 * @param input type="button" button
 */
function try_clicked(button)
{
	var label = button.value;
	var num = try_num();
	try_num(++num);
	
	var bonus = document.getElementById('exec[zone]');

	// set bonus 'No'=0, for 1. try, if there's no bonus yet
	if (num == 1 && bonus && bonus.value === '')
	{
		bonus.value = '0';
		// store on server
		update_boulder();
	}
}

/**
 * Get number of try from label of try button
 * 
 * @param int set_value 0: reset, 1: set to 1, if not already higher
 * @returns int number of try
 */
function try_num(set_value)
{
	var try_button = document.getElementById('exec[button][try]');

	var num = parseInt(try_button.value);
	if (isNaN(num)) num = 0;
	
	if (typeof set_value != 'undefined')
	{
		var label = try_button.value;
		label = label.replace(/^[0-9]+. /,'');
		
		if (set_value == 0 || num < set_value) num = set_value;
		
		try_button.value = (num ? ''+num+'. ' : '')+label;
	}
	return num;
}

/**
 * Bonus button clicked
 * 
 * @param button
 */
function bonus_clicked(button)
{
	var bonus = document.getElementById('exec[zone]');
	
	if (!bonus.value || bonus.value == '0')
	{
		bonus.value = try_num(1);
		check_bonus(bonus);
	
		if (!bonus.isNaN) update_boulder();
	}
}

/**
 * Bonus button clicked
 * 
 * @param button
 */
function top_clicked(button)
{
	var bonus = document.getElementById('exec[zone]');
	var top = document.getElementById('exec[top]');
	var num = try_num(1);

	if (!num.isNaN)
	{
		if(!bonus.value || bonus.value == '0') bonus.value = num;
		top.value = num;
		check_top(top);
		
		update_boulder();
	}
}

/**
 * Sending bonus and top for PerId to server
 */
function update_boulder()
{
	var n = document.getElementById('exec[nm][boulder_n]').value;
	var PerId = document.getElementById('exec[nm][PerId]').value;
	var GrpId = document.getElementById('exec[nm][cat]').value;
	var route_order = document.getElementById('exec[nm][route]').value;

	if (PerId && n)
	{
		var update = {};
		update['top'+n] = parseInt(0+document.getElementById('exec[top]').value);	// parseInt(0+ is required, as backend doesn't store zones with empty top!
		update['zone'+n] = document.getElementById('exec[zone]').value;

		xajax_doXMLHTTP('ranking_boulder_measurement::ajax_update_result', PerId, update, n, {'GrpId': GrpId, 'route_order': route_order});				
	}
}

var resultlist;

/**
 * Init boulder measurement or update button state
 */
function init_boulder()
{
	var PerId = document.getElementById('exec[nm][PerId]').value;
	var n = document.getElementById('exec[nm][boulder_n]').value;

	document.getElementById('exec[button][update]').disabled = !PerId || !n;
	document.getElementById('exec[button][try]').disabled = !PerId || !n;
	document.getElementById('exec[button][bonus]').disabled = !PerId || !n;
	document.getElementById('exec[button][top]').disabled = !PerId || !n;
	
	if (!document.getElementById('table'))
	{
		var table = document.createElement('div');
		table.id = 'table';
		$j(document.forms.eTemplate).append(table);
		resultlist = new Resultlist('table',egw_webserverUrl+'/ranking/json.php'+
			'?comp='+document.getElementById('exec[comp][WetId]').value+
			'&cat='+document.getElementById('exec[nm][cat]').value+
			'&route='+document.getElementById('exec[nm][route]').value+'&detail=2');
	}
}

/**
 * Boulder or athlete changed
 * 
 * @param selectbox
 */
function boulder_changed(selectbox)
{
	var PerId = document.getElementById('exec[nm][PerId]').value;
	var n = document.getElementById('exec[nm][boulder_n]').value;
	
	// ToDo load values from server
	if (PerId && n)
	{
		xajax_doXMLHTTP('ranking_boulder_measurement::ajax_load_athlete', PerId, { 'exec[zone]': 'zone'+n, 'exec[top]': 'top'+n } );
	}
	else
	{
		document.getElementById('exec[zone]').value = document.getElementById('exec[top]').value = '';
	}
	try_num(0);
	init_boulder();
}

/**
 * Go to next athlete
 */
function boulder_next()
{
	var PerId = document.getElementById('exec[nm][PerId]');
	
	PerId.selectedIndex++;

	// need to call this manually, as changing selectedIndex does NOT trigger onchange
	boulder_changed(PerId);
}
