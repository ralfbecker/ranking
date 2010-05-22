/**
 * eGroupWare digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007/8 by Ralf Becker <RalfBecker@digitalrock.de>
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

		document.getElementById(plus_sel_id).value = plus == '+' ? 1 : (plus == '-' ? -1 : 9999);
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

	if (bonus && !bonus.value && top.value > 0) bonus.value = top.value;

	if (bonus && top.value > 0 && top.value < bonus.value)
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

	if (bonus && !bonus.value && top.value > 0) bonus.value = top.value;

	if (bonus && top.value > 0 && top.value > bonus.value)
	{
		alert('Top > Bonus!');
		bonus.value = top.value;
	}
	
	var tries = document.getElementById(top.name.replace(/tops/g,'top_tries'));
	
	if (tries && !tries.value && top.value > 0) tries.value = top.value;

	if (tries && top.value > 0 && top.value > tries.value)
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

	if (top && top.value > 0 && bonus.value > top.value)
	{
		alert('Bonus > Top!');
		top.value = bonus.value;
	}
	if (top && top.value > 0 && !bonus.value)
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

	if (top && top.value > 0 && bonus.value < top.value)
	{
		alert('Bonus < Top!');
		top.value = bonus.value;
	}
	if (top && top.value > 0 && !bonus.value)
	{
		top.value = bonus.value;
	}
	
	var tries = document.getElementById(bonus.name.replace(/zones/g,'zone_tries'));
	
	if (tries && !tries.value && bonus.value > 0) tries.value = bonus.value;

	if (tries && bonus.value > 0 && bonus.value > tries.value)
	{
		alert('Bonus > Tries!');
		tries.value = bonus.value;
	}
}

function start_time_measurement(button,athlete)
{
	set_style_by_class('td','ajax-loader','display','inline');
	document.getElementById('msg').innerHTML='Time measurement';

	xajax_doXMLHTTP('ranking.uiresult.ajax_time_measurement',button.form.etemplate_exec_id.value,athlete);
}
