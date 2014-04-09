<?php
/**
 * EGroupware digital ROCK Rankings - federation UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2008-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class ranking_federation_ui extends ranking_bo
{
	/**
	 * functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
	);

	/**
	 * query federations for nextmatch
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		//echo __METHOD__.'()'; _debug_array($query);
		$GLOBALS['egw']->session->appsession('ranking','feds_state',$query);

		$total = $this->federation->get_rows($query,$rows,$readonlys);

		//_debug_array($rows);

		$readonlys = $parent_ids = array();
		foreach($rows as $n => &$fed)
		{
			if ($fed['fed_parent'])
			{
				$parent_ids[$n] = $fed['fed_parent'];
			}
			// wont delete federation with athletes or children!
			$readonlys['delete['.$fed['fed_id'].']'] = !$this->is_admin || $fed['num_athletes'] > 0 || $fed['num_children'] > 0;
		}
		if ($parent_ids)
		{
			$parents = $this->federation->query_list('verband','fed_id',array('fed_id' => array_unique($parent_ids)));
			foreach($rows as $n => &$fed)
			{
				if ($fed['fed_parent'])
				{
					$fed['parent_name'] = $parents[$fed['fed_parent']];
				}
			}
		}
		return $total;
	}

	/**
	 * List existing Athletes
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index($content=null,$msg='')
	{
		$tpl = new etemplate('ranking.federation.index');

		//$this->is_admin = false;
		if (!is_array($content))
		{
			$content['nm'] = $GLOBALS['egw']->session->appsession('ranking','feds_state');

			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'    =>	'ranking.ranking_federation_ui.get_rows',
					'no_filter'   => True,
					'no_filter2'  => True,
					'no_cat'      => True,// I  disable the cat-selectbox
					'order'       => 'nation',// IO name of the column to sort after (optional for the sortheaders)
					'sort'        => 'ASC',// IO direction of the sort: 'ASC' or 'DESC'
					'csv_fields'  => false,
					'default_cols' => '!fed_id',
				);
				if ($this->only_nation_athlete)
				{
					$content['nm']['col_filter']['nation'] = $this->only_nation_athlete;
				}
				// also set nation filter, if grants are from a single nation
				elseif (count($fed_nations = $this->federation->get_user_nations()) == 1)
				{
					$content['nm']['col_filter']['nation'] = array_pop($fed_nations);
				}
			}
		}
		else
		{
			//_debug_array($content);
			if($content['nm']['rows']['edit'])
			{
				list($fed_id) = each($content['nm']['rows']['edit']);
				$content['fed'] = $this->federation->read($fed_id);
				$content['fed']['grants'] = $this->federation->get_grants();
			}
			elseif ($this->is_admin && $content['nm']['rows']['delete'])
			{
				list($fed_id) = each($content['nm']['rows']['delete']);
				$msg = $this->federation->delete($fed_id) ? lang('Federation deleted.') :
					lang('Error deleting federation!');
			}
			elseif($content['button'])
			{
				list($button) = each($content['button']);
				unset($content['button']);
				switch($button)
				{
					case 'save':
					case 'apply':
						if (!$content['fed']['nation'] || !$content['fed']['verband'])
						{
							$msg = lang('Field must not be empty !!!');
							foreach(array('nation','verband') as $name)
							{
								if (!$content['fed'][$name])
								{
									$tpl->set_validation_error("fed[$name]",'Field must not be empty !!!');
								}
							}
							$button = '';
						}
						elseif ($this->is_admin && $this->federation->save($content['fed']) == 0)
						{
							$this->federation->set_grants($content['fed']['grants']);
							$content['fed'] = $this->federation->data + array('grants' => $content['fed']['grants']);
							$msg = lang('Federation saved.');
						}
						else
						{
							$msg = lang('Error saving federation!');
							$button = '';
						}
						if ($button != 'save') break;
						// fall through for save
					case 'cancel':
						$content['fed'] = array();
						break;
				}
			}
			elseif($this->is_admin && $content['action'])
			{
				if (!($checked = $content['nm']['rows']['checked']))
				{
					$msg = lang('You need to select some federations first!');
				}
				else
				{
					$fed = $content['fed'];
					switch($content['action'])
					{
						case 'merge':
							if (!$fed['fed_id'])
							{
								$msg = lang('No federation selected to edit!');
							}
							else
							{
								$msg = lang('%1 federations merged.',
									$this->federation->merge($fed['fed_id'],$checked));
							}
							break;
						case 'apply':
							if(!$fed['nation'] && !$fed['fed_parent'] && !$fed['fed_shortcut'] && !$fed['fed_parent'])
							{
								$msg = lang('Nothing to apply!');
							}
							else
							{
								$msg = lang('Changes applied to %1 federations.',
									$this->federation->apply($fed,$checked));
							}
							break;
						case 'parent':
							if (!$fed['fed_id'])
							{
								$msg = lang('No federation selected to edit!');
							}
							else
							{
								$msg = lang('%1 federations added to parent federation.',
									$this->federation->apply(array('fed_parent' => $fed['fed_id']),$checked));
							}
							break;
					}
				}
				unset($content['action']);
			}
			unset($content['nm']['rows']);
		}
		$content['msg'] = $msg ? $msg : $_GET['msg'];
		$content['is_admin'] = $this->is_admin;
		$readonlys['button[save]'] = $readonlys['button[apply]'] = !$this->is_admin;

		if (!$content['fed']) $content['fed']['nation'] = $content['nm']['col_filter']['nation'];

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Nations and Federations');
		$tpl->exec('ranking.ranking_federation_ui.index',$content,array(
			'nation' => $this->athlete->distinct_list('nation'),
			'action' => array(
				'apply' => lang('Apply modifications below to all selected federations'),
				'merge' => lang('Merge selected federations with the one opened for editing'),
				'parent' => lang('Add to parent federation opened for editing'),
			),
			'fed_continent' => ranking_federation::$continents,
			'fed_parent' => !$content['fed']['nation'] ? array() :
				$this->federation->query_list('verband','fed_id',array(
					'nation' => $content['fed']['nation'],
					'fed_id != '.(int)$content['fed']['fed_id'],
				),ranking_federation::FEDERATION_CHILDREN.' DESC,verband ASC'),
		),$readonlys,array(
			'fed' => array('fed_id' => $content['fed']['fed_id']),
			'nm'  => $content['nm'],
		));
	}
}
