<?php
/**
 * EGroupware digital ROCK Rankings - federation UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2008-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

class ranking_federation_ui extends ranking_bo
{
	/**
	 * functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
		'edit' => true,
	);

	/**
	 * Edit a federation
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 */
	function edit(array $content=null, $msg='')
	{
		//$this->is_admin = false;

		if (!is_array($content))
		{
			if (empty($_GET['fed_id']))
			{
				$content = array();
			}
			elseif(strpos($_GET['fed_id'], ',') === false)
			{
				if (!($content = $this->federation->read($_GET['fed_id'])))
				{
					Api\Framework::window_close(lang('Entry not found !!!'));
				}
				$content['grants'] = $this->federation->get_grants();
			}
			// edit multiple ones
			else
			{
				$content = array(
					'fed_ids' => explode(',', $_GET['fed_id']),
				);
				Api\Framework::message('Apply modifications below to all selected federations', 'info');
			}
		}
		// changes are only allowed for admins
		elseif($this->is_admin)
		{
			$button = key($content['button']);
			unset($content['button']);

			switch($button)
			{
				case 'save':
				case 'apply':
					// editing of multiple federations
					if (!empty($content['fed_ids']))
					{
						$msg = lang('Changes applied to %1 federations.',
							$this->federation->apply(array_diff_key($content, ['fed_ids' => true]),
								$content['fed_ids']));
					}
					elseif (empty($content['nation']) || empty($content['verband']))
					{
						$msg = lang('Field must not be empty !!!');
						foreach(array('nation','verband') as $name)
						{
							if (empty($content[$name]))
							{
								Api\Etemplate::set_validation_error($name, 'Field must not be empty !!!');
							}
						}
						$button = '';
					}
					elseif ($this->federation->save($content) == 0)
					{
						$this->federation->set_grants($content['grants']);
						$msg = lang('Federation saved.');
						// update content, in case save() set defaults
						$content = $this->federation->data;
						$content['grants'] = $this->federation->get_grants();
					}
					else
					{
						$msg = lang('Error saving federation!');
						$button = '';
					}
					Api\Framework::refresh_opener($msg, 'ranking', $this->federation->data['fed_id'], $content['fed_id'] ? 'edit' : 'add');
					if ($button === 'save') Api\Framework::window_close();
					break;

				case 'delete':
					if (!$this->is_admin || !$content['fed_id'] || $content['num_children'] || $content['num_athletes'])
					{
						$msg = lang('Permission denied!');
					}
					else
					{
						$msg = $this->federation->delete($content['fed_id']) ?
							lang('Federation deleted.') : lang('Error deleting federation!');
					}
					break;
			}
			Api\Framework::message($msg);
		}

		if (!$this->is_admin)
		{
			$readonlys = ['__ALL__' => true, 'button[cancel]' => false];
		}
		elseif ($content['num_children'] || $content['num_athletes'])
		{
			$readonlys['button[delete]'] = true;
		}

		$fed_parents = empty($content['nation']) ? array() :
			$this->federation->query_list('verband', 'fed_id', array(
				'nation' => $content['nation'],
				'fed_id != '.(int)$content['fed_id'],
			), ranking_federation::FEDERATION_CHILDREN.' DESC, verband ASC');

		$tpl = new Api\Etemplate('ranking.federation.edit');
		$tpl->exec('ranking.ranking_federation_ui.edit',$content, array(
			'nation' => $this->athlete->distinct_list('nation'),
			'fed_continent' => ranking_federation::$continents,
			'fed_parent' => $fed_parents,
			'fed_parent_since' => $fed_parents,
		), $readonlys, $content, 2);
	}

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
		Api\Cache::setSession('feds_state', 'ranking', $query);

		$total = $this->federation->get_rows($query,$rows,$readonlys);
		//_debug_array($rows);

		$readonlys = $parent_ids = array();
		foreach($rows as $n => &$fed)
		{
			if ($fed['fed_since'] >= date('Y') && $fed['fed_parent_since'])
			{
				$fed['fed_parent'] = $fed['fed_parent_since'];
			}
			if ($fed['fed_parent'])
			{
				$parent_ids[$n] = $fed['fed_parent'];
			}
			// wont delete federation with athletes or children!
			if (!$this->is_admin || $fed['num_athletes'] > 0 || $fed['num_children'] > 0)
			{
				$fed['class'] = 'noDelete';
			}
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
	function index($content=null)
	{
		if (is_array($content))
		{
			// multi-actions are only for admins
			if ($this->is_admin && $content['nm']['action'] && $content['nm']['selected'])
			{
				foreach($content['nm']['selected'] as $id)
				{
					switch($content['nm']['action'])
					{
						case 'delete':
							if (!$this->is_admin || !($fed = $this->federation->read($id)) ||
								$fed['num_children'] || $fed['num_athletes'])
							{
								$msg = lang('Permission denied!');
								break 2;
							}
							if (!$this->federation->delete($id))
							{
								$msg = lang('Error deleting federation!');
								break 2;
							}
							$msg = count($content['nm']['action']).' '.lang('Federation deleted.');
							break;

						case 'merge':
							array_shift($content['nm']['selected']);
							$msg = lang('%1 federation(s) deleted after merge.',
								$this->federation->merge($id, $content['nm']['selected']));
							break 2;
					}
				}
				Api\Framework::message($msg);
				unset($content['nm']['action'], $content['nm']['selected']);
			}
		}
		else
		{
			$content['nm'] = Api\Cache::getSession('feds_state', 'ranking');

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
					'default_cols'=> '!fed_id',
					'dataStorePrefix' => 'ranking_feds',
					'row_id'      => 'fed_id',
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
			$content['nm']['actions'] = $this->get_actions();
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Nations and Federations');

		$fed_parents = !$content['fed']['nation'] ? array() :
				$this->federation->query_list('verband','fed_id',array(
					'nation' => $content['fed']['nation'],
					'fed_id != '.(int)$content['fed']['fed_id'],
				),ranking_federation::FEDERATION_CHILDREN.' DESC,verband ASC');

		$this->set_ui_state();
		$tpl = new Api\Etemplate('ranking.federation.index');
		$tpl->exec('ranking.ranking_federation_ui.index',$content,array(
			'nation' => $this->athlete->distinct_list('nation'),
			'fed_continent' => ranking_federation::$continents,
			'fed_parent' => $fed_parents,
			'fed_parent_since' => $fed_parents,
		), [], $content);
	}

	/**
	 * Return actions for cup list
	 *
	 * @return array
	 */
	function get_actions()
	{
		$actions =array(
			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => $this->is_admin,	// allow admin to edit multiples ones
				'url' => 'menuaction=ranking.ranking_federation_ui.edit&fed_id=$id',
				'popup' => '900x500',
				'group' => $group=0,
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=ranking.ranking_federation_ui.edit',
				'popup' => '900x500',
				'disabled' => !$this->is_admin,
				'group' => $group,
			),
			'merge' => array(
				'caption' => 'Merge',
				'hint' => 'Merge selected federations in the first one',
				'disable' => !$this->is_admin,
				'allowOnMultiple' => 'only',
				'confirm' => 'Merge selected federations in the first one',
				'group' => $group=5,
			),
			'delete' => array(
				'caption' => 'Delete',
				'disableClass' => 'noDelete',	// checks children and athletes
				'confirm' => 'Delete this entry',
				'group' => $group=5,
			),
		);
		return $actions;
	}
}
