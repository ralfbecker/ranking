/**
 * EGroupware digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2014 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

jQuery(function(){
	jQuery('#show_passwd').click(function(ev){
		jQuery('input[name^=password]', this.form).attr('type', this.checked ? 'text' : 'password');
	});
	// remove empty cat selection, which leads to competition selection
	jQuery('select#exec\\[nm\\]\\[cat\\] option[value=""],select#exec\\[nm\\]\\[PerId\\] option[value=""]').remove();
});
