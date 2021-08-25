<?php
/**
 * EGroupware digital ROCK Rankings - administration
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

/**
 * Editing athletes data is mapped to EGW_ACL_ADD
 *
 * This is orginally defined in boranking, which is NOT included here.
 */
define('EGW_ACL_ATHLETE',EGW_ACL_ADD);
define('EGW_ACL_REGISTER',EGW_ACL_CUSTOM_1);

/**
 * digital ROCK Rankings - Administration
 */
class admin
{
	/**
	 * @var array $public_functions exported functions, callable by menuaction
	 */
	var $public_functions = array
	(
		'acl' => True,
		'user_acl' => True,
	);
	/**
	 * @var array $nations
	 */
	var $nations = array();
	/**
	 * @var Api\Acl $acl reference to the global acl object, $GLOBALS['egw']->acl
	 */
	var $acl;

	/**
	 * constructor
	 */
	function __construct()
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		$this->acl = $GLOBALS['egw']->acl;
	}

	/**
	 * Edit nation-based ACL
	 *
	 * @param array $content from eTemplate
	 */
	function acl($content = null)
	{
		$account_id = is_array($content) ? $content['account_id'] : (int) $_GET['account_id'];

		if ($account_id)
		{
			// check if account has admin rights
			$locations = $this->acl->get_all_location_rights($account_id,'admin');
			$account_is_admin = isset($locations['run']) && $locations['run'];
			unset($locations);
		}
		if (is_array($content))
		{
			$account_id = $content['account_id'];

			if ($content['save'] || $content['apply'] || $content['delete'])
			{
				$delete = !empty($content['delete']) ? key($content['delete']) : null;
				// saving the content
				foreach(array_merge($content['nations'],array('***new***')) as $n => $nation)
				{
					$data =& $content[++$n];

					if ($nation == '***new***' && !($nation = strtoupper($data['nation']))) continue;	// no new entry

					$this->acl->delete_repository('ranking',$nation,$account_id ? $account_id : false);

					if ($n == $delete) continue;	// it got deleted

					if ($account_id)
					{
						$rights = ($data['read'] ? EGW_ACL_READ : 0) | ($data['edit'] ? EGW_ACL_EDIT|EGW_ACL_READ : 0) |
							($data['athlets'] ? EGW_ACL_ATHLETE : 0) | ($data['register'] ? EGW_ACL_REGISTER : 0);

						//echo "$nation: $account_id = $rights<br>";
						if ($rights) $this->acl->add_repository('ranking',$nation,$account_id,$rights);
					}
					else
					{
						$accounts = array_unique(array_merge((array)$data['read'],(array)$data['edit'],(array)$data['athlets'],(array)$data['register']));
						//echo "<p>$n: $nation: accounts=".print_r($accounts,true).", read=".print_r($data['read'],true).", edit=".print_r($data['edit'],true).", athlets=".print_r($data['athlets'],true)."</p>\n";
						foreach($accounts as $account)
						{
							$rights = (is_array($data['read']) && in_array($account,$data['read']) ? EGW_ACL_READ : 0) |
								(is_array($data['edit']) && in_array($account,$data['edit']) ? EGW_ACL_EDIT|EGW_ACL_READ : 0) |
								(is_array($data['athlets']) && in_array($account,$data['athlets']) ? EGW_ACL_ATHLETE : 0) |
								(is_array($data['register']) && in_array($account,$data['register']) ? EGW_ACL_REGISTER : 0);

							if ($rights)	// only write rights if there are some
							{
								//echo "$nation: $account = $rights<br>";
								$this->acl->add_repository('ranking',$nation,$account,$rights);
							}
						}
					}
				}
			}
			if ($content['save'] || $content['cancel'])
			{
				Api\Framework::redirect_link('/index.php', array(
					'menuaction' => 'admin.admin_ui.index',
					'ajax' => 'true'
				), 'admin');
			}
			$preserve = array(
				'account_id' => $content['account_id'],
			);
		}
		else
		{
			$preserve = array(
				'account_id' => $account_id,
			);
		}
		$readonlys = $content = array();
		unset($rights);
		if ($account_id)
		{
			if (!$account_is_admin)
			{
				$rights = $this->acl->get_all_location_rights($account_id,'ranking');
				$own_rights = $this->acl->get_all_location_rights($account_id,'ranking',false);
			}
		}
		if (!($nations = $this->acl->get_locations_for_app('ranking'))) $nations = array();
		// remove federation ACL (location starting with a hash followed by the fed_id)
		require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_federation.inc.php');
		foreach($nations as $key => $nation)
		{
			if ($nation[0] == ranking_federation::ACL_LOCATION_PREFIX) unset($nations[$key]);
		}
		sort($nations);
		if(($null_key = array_search('NULL',$nations)) !== false) unset($nations[$null_key]);	// to have it always first
		$nations = array_merge(array('NULL'),$nations);
		if ($rights)	// sort the nation the user rights too first
		{
			$nations = array_merge(array_intersect($nations,array_keys($rights)),array_diff($nations,array_keys($rights)));
		}
		if (true) $nations = $preserve['nations'] = array_values($nations);
		$n = 1;
		foreach($nations as $nation)
		{
			foreach(array(
				'read'     => EGW_ACL_READ,
				'edit'     => EGW_ACL_EDIT,
				'athlets'  => EGW_ACL_ATHLETE,
				'register' => EGW_ACL_REGISTER,
			) as $name => $right)
			{
				if ($account_id)
				{
					$content[$n][$name] = !!($rights[$nation] & $right) || $account_is_admin;
					$readonlys[$n.'['.$name.']'] = $content[$n][$name] && !($own_rights[$nation] & $right) || $account_is_admin;
				}
				else
				{
					$content[$n][$name] = $this->acl->get_ids_for_location($nation,$right,'ranking');
				}
			}
			$content[$n]['nation'] = $nation == 'NULL' ? lang('international') : $nation;
			//echo "$n:"; _debug_array($content[$n]);
			$readonlys[$n.'[nation]'] = true;
			++$n;
		}
		// one line to add new nations
		$content[$n] = array(
			'nation'   => '',
			'read'     => $account_id ? $account_is_admin : array(),
			'edit'     => $account_id ? $account_is_admin : array(),
			'athlets'  => $account_id ? $account_is_admin : array(),
			'register' => $account_id ? $account_is_admin : array(),
		);
		if ($account_id && $account_is_admin)
		{
			$readonlys[$n.'[read]'] = $readonlys[$n.'[edit]'] = $readonlys[$n.'[athlets]'] = $readonlys[$n.'[register]'] = true;
			$content['msg'] = lang('%1 is an admin and has therefor all rights!',$GLOBALS['egw']->common->grab_owner_name($account_id));
		}
		$readonlys['delete['.$n.']'] = true;

		$tmpl = new Api\Etemplate($account_id ? 'ranking.admin.user_acl' : 'ranking.admin.acl');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Nation ACL').
			($account_id ? ': '.$GLOBALS['egw']->common->grab_owner_name($account_id) : '');
		$tmpl->exec('ranking.admin.acl', $content,null, $readonlys, $preserve);
	}

	/**
	 * add nation ACL tab to Admin >> Edit user
	 */
	function edit_user()
	{
		global $menuData;

		$menuData[] = array(
			'description'   => 'nation ACL',
			'url'           => '/index.php',
			'extradata'     => 'menuaction=ranking.admin.acl&ajax=true'
		);
	}
}