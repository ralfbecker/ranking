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
