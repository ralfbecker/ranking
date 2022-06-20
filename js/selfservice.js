/**
 * EGroupware digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2014-21 by Ralf Becker <RalfBecker@digitalrock.de>
 */

jQuery(() => {
	// hide/show password
	jQuery('#show_passwd').click(function(ev){
		jQuery('input[name^=password]', this.form).attr('type', this.checked ? 'text' : 'password');
	});

	// reload window after successful license application form download
	jQuery('form[action*="action=apply"]').on('submit', () => {
		window.setTimeout(() => location.href=location.href.replace(/action=[^&]*/, 'action='), 3000)
	});

	// submit on athlete change
	jQuery('select[name=PerId]').on('change', function(ev){
		this.form.submit();
	});
});