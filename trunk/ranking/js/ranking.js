/**
 * eGroupWare digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
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

function start_time_measurement(button,athlete)
{
	set_style_by_class('td','ajax-loader','display','inline'); 
	document.getElementById('msg').innerHTML='Time measurement'; 
	
	xajax_doXMLHTTP('ranking.uiresult.ajax_time_measurement',button.form.etemplate_exec_id.value,athlete);
}
