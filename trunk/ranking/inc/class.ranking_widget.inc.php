<?php
/**
 * EGroupware digital ROCK Rankings - widget rendering
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2013 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 *
 *
 */
class ranking_widget
{
	/**
	 * Name of get parameter to trigger serverside rendering
	 */
	const FRAGMENT = '_escaped_fragment_';

	/**
	 * URL to query json data
	 *
	 * @var string
	 */
	protected $json_url;

	/**
	 * Throw errors as exceptions or return error message
	 *
	 * @var boolean
	 */
	protected $throw_error=false;

	/**
	 * Widget-specific parameters
	 *
	 * @var array
	 */
	protected $widget_params = array();

	/**
	 * Callback to translate string
	 *
	 * @var callable
	 */

	/**
	 * Charset for htmlspecialchars
	 *
	 * @var string
	 */
	public $charset = 'utf-8';

	/**
	 * Constructor
	 *
	 * @param string $json_url
	 * @param boolean $throw_error=false default: return error instead of content, true: throw an exception
	 */
	public function __construct($json_url, $throw_error=false)
	{
		$this->json_url = $json_url;
	}

	/**
	 * Set widget-specifc parameter
	 *
	 * Examples:
	 * - setParam('Competitions', array(
	 *  	'All events'      => '',
	 * 		'Championships'   => 'filter[cat_id]=69,71&filter[cup]=',
	 * 		'Masters & Promo' => 'filter[cat_id]=70',
	 * 		'World Cups'      => 'filter[cat_id]=69&filter[cup]=!',
	 * 		'Youth Cups'      => 'filter[cat_id]=71&filter[cup]=!'
	 * 	));
	 * 	- setParam('Profil', 'document.getElementById("profile")');
	 *
	 * @param string|array $name widget-name eg. 'Competitions' to set filter or array with name => value pairs
	 * @param string $value=null value if $name is a string
	 */
	public function setParam($name, $value=null)
	{
		if (!is_array($name))
		{
			$this->widget_params[$name] = $value;
		}
		else
		{
			$this->widget_params = $name;
		}
	}

	/**
	 * Set callback to translate strings of widgets
	 *
	 * @param callback $translate called with string as parameter and returns a string
	 */
	public function setTranslate($translate)
	{
		$this->translate = $translate;
	}

	/**
	 * Query parameters
	 *
	 * @var array
	 */
	protected $params = array();

	/**
	 * Render HTML or javascript for widget
	 *
	 * HTML is only rendered if $_GET['_escaped_fragment_'] is set
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return string html or javascript for content or widget
	 */
	public function render($id='widget_content', &$title=null)
	{
		if (isset($_GET[self::FRAGMENT]))
		{
			parse_str($_GET[self::FRAGMENT], $this->params);

			try {
				return $this->render_widgets($id, $title);
			}
			catch (Exception $e) {
				if ($this->throw_error)
				{
					throw $e;
				}
				else
				{
					return 'ERROR: '.$e->getMessage();
				}
			}
		}
		return $this->render_js_widget($id);
	}

	/**
	 * Render javascript widget
	 *
	 * @param string $id='widget_content'
	 */
	public function render_js_widget($id='widget_content')
	{
		list($dr_api_url) = explode('json.php', $this->json_url);
		$dr_api_url .= 'sitemgr/digitalrock/dr_api.js';

		$params = json_encode($this->widget_params);
		if (isset($this->widget_params['Profile']))	// remove quoting as string
		{
			$params = preg_replace('/"Profile":".*"(,"|})/', '"Profile":'.$this->widget_params['Profile'].'$1', $params);
		}

		return "<div id='$id' />

<script type='text/javascript' src='$dr_api_url'></script>
<script type='text/javascript'>
	var widget;
	$(document).ready(function() {
		widget = new DrWidget('$id', '$this->json_url', $params);
	});
</script>
";
	}

	/**
	 * Data returned from JSON feed
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Parameters support by this widget
	 *
	 * @var array
	 */
	public static $supported_params = array('person','comp','cat','route','nation','year','filter','type');

	/**
	 * URL without our parameters
	 *
	 * @var string
	 */
	protected $page_url;

	/**
	 * Render widget server-side depending on given parameters
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return html
	 */
	public function render_widgets($id='widget_content', &$title=null)
	{
		$query = '';
		foreach(self::$supported_params as $name)
		{
			if (isset($this->params[$name]))
			{
				foreach((array)$this->params[$name] as $key => $value)
				{
					$query .= ($query ? '&' : '?').
						$name.(is_string($key) ? '['.$key.']' : '').'='.urlencode($value);
				}
			}
		}
		$this->page_url = preg_replace('/('.implode('|', self::$supported_params).'|'.self::FRAGMENT.')=[^&]*(&|$)/', '', $_SERVER['REQUEST_URI']);

		//echo "<pre>".print_r($params, true)."</pre>\n"; echo $query;
		if (!($json = file_get_contents($this->json_url.$query)))
		{
			throw new Exception("Requesting data from $url failed!");
		}
		if (!($this->data = json_decode($json, true)))
		{
			throw new Exception("Data from $url is NO valid JSON!");
		}
		//echo "<pre>".print_r($this->data, true)."</pre>\n";

		if (isset($this->data['competitions']))
		{
			return $this->Competitions($id, $title);
		}
		if (isset($this->data['athletes']))
		{
			return $this->Starters($id, $title);
		}
		if (isset($this->data['participants']))// || isset($this->data['categorys']))
		{
			return $this->Resultlist($id, $title);
		}
		return "Not yet implemented!";
	}

	private $result_cols = array();

	/**
	 * Server-side rendering of results, rankings and startlists
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @param string $result=true false: startlist
	 * @return html
	 */
	protected function Resultlist($id, &$title=null, $result=true)
	{
		$title = $this->data['comp_name'];
		$content = $this->tag('h1', $title, array('class' => 'compHeader'));
		if (!empty($this->data['route_result']))
		{
			$content .= $this->tag('h3', $this->data['route_result'], array('class' => 'resultDate'));
		}
		$content .= $this->tag('h1', $this->data['route_name'], array('class' => 'listHeader'));

		if ((!isset($this->params['toc']) || $this->params['toc']) &&
			(!isset($this->params['beamer']) || !$this->params['beamer']) &&
			$this->data['discipline'] != 'ranking')
		{
			$toc = $gen = '';
			$page_url = preg_replace('/(comp|cat|route|'.self::FRAGMENT.')=[^&]*(&|$)/', '', $_SERVER['REQUEST_URI']).
				'#!comp='.$this->data['WetId'].'&cat='.$this->data['GrpId'].'&route=';
			foreach($this->data['route_names'] as $r => $name)
			{
				if ($r != $this->data['route_order'])
				{
					$li = $this->tag('li', $this->tag('a', $name, array(
						'href' => $page_url.$r,
					)), array(), false);
				}
				else
				{
					$li = $this->tag('li', $name);
				}
				if ($r == -1)
				{
					$gen = $li;
				}
				else
				{
					$toc = $li.$toc;
				}
			}
			$content .= $this->tag('ul', $gen.$toc, array('class' => 'listToc'), false);
		}

		$this->result_cols = $this->result_cols($result);
		//echo "<pre>".print_r($this->result_cols, true)."</pre>\n";

		// header
		$thead = $this->createRow($this->result_cols, 'th');

		// ahtletes
		$tbody = '';
		foreach($this->data['participants'] as $data)
		{
			if (isset($data['results']) && is_array($data['results']))
			{
				if (!empty($data['name']))
				{
					$th = htmlspecialchars($data['name'], ENT_COMPAT, $this->charset)."\n";
					if (!empty($data['url']))
					{
						$th .= $this->tag('a', $this->lang('complete result'), array(
							'href' => $data['url'],
						));
						$quote = false;
					}
					$tbody .= $this->tag('tr', $this->tag('th', $th, array(
						'colspan' => count($this->result_cols),
					), false), array(), false);
				}
				foreach($data['results'] as $data)
				{
					$tbody .= $this->createRow($data);
				}
			}
			else	// single result row
			{
				if ($result && (empty($data['result_rank']) || $data['result_rank'] < 1))
				{
					break;	// no more ranked competitiors
				}
				$tbody .= $this->createRow($data);
			}
		}
		$content .= $this->tag('table',
			$this->tag('thead', $thead, array(), false).
			$this->tag('tbody', $tbody, array(), false),
			array(
				'class' => 'DrTable',
			), false);

		return $this->tag('div', $content, array(
			'id' => $id,
			'class' => 'Resultlist',
		), false);
	}

	/**
	 * Create new data-row with all columns from this.columns
	 */
	private function createRow($data, $tag='td')
	{
		$row = '';
		$span = 1;
		foreach($this->result_cols as $col => $label_data)
		{
			if (--$span > 0) continue;

			$url = isset($data['url']) ? $data['url'] : null;
			$col_data = isset($data[$col]) ? $data[$col] : null;
			// allow /-delemited expressions to index into arrays and objects
			if (!isset($col_data) || strpos($col, '/') !== false)
			{
				$col_data = $data;
				foreach(explode('/', $col) as $col)
				{
					if ($col == 'lastname' || $col == 'firstname')
					{
						$url = $col_data['url'];
					}
					$col_data = isset($col_data[$col]) ? $col_data[$col] : null;
				}
			}
			elseif (strpos($col, '/') !== false)
			{
				$col = substr($col, strrpos($col, '/'));
			}
			$quote = true;
			switch($col)
			{
				case 'firstname':
				case 'lastname':
					if (!empty($url))
					{
						$col_data = $this->tag('a', $col_data, array(
							'href' => $this->page_url.'#!person='.$data['PerId'].'&cat='.$this->data['GrpId'],
						));
						$quote = false;
					}
					break;
				case 'nation':
				case 'federation':
					if (!empty($data['fed_url']))
					{
						$col_data = $this->tag('a', $col_data, array(
							'href' => $data['fed_url'],
							'target' => '_blank',
						));
						$quote = false;
					}
					break;
			}
			if (is_array($col_data))
			{
				if (isset($col_data['colspan']) && $col_data['colspan'] > 1) $span = $col_data['colspan'];
				if (!empty($col_data['url']))
				{
					$col_data = $this->tag('a', $col_data['label'], array(
						'href' => $col_data['url'],
					));
					$quote = false;
				}
				else
				{
					$col_data = $col_data['label'];
				}
			}
			$row .= $this->tag($tag, empty($col_data) ? '' : $col_data, array(
				'class' => $col,
			)+($span > 1 ? array('colspan' => $span) : array()), $quote);
		}
		$attr = array();
		// add or remove quota line
		if (isset($this->result_cols['result_rank']) && !empty($this->data['quota']) && !empty($data['result_rank']) &&
			$data['result_rank'] >= 1 && $data['result_rank'] > $this->data['quota'])
		{
			$attr = array('class' => 'quota_line');
			unset($this->data['quota']);	// to set quota line only once
		}
		return $this->tag('tr', $row, $attr, false);
	}

	/**
	 * Get columns for result display
	 *
	 * @param string $result=true false: startlist
	 * @todo Startlist cols
	 * @return array column => label pairs
	 */
	private function result_cols($result = true)
	{
		$detail = isset($this->params['detail']) ? $this->params['detail'] : null;

		switch($this->data['discipline'])
		{
			case 'speedrelay':
				$cols = empty($detail) ? array(	// default detail
					'result_rank' => 'Rank',
					'team_name' => 'Teamname',
					'athletes/0/lastname' => 'Athlete #1',
					'athletes/1/lastname' => 'Athlete #2',
					'athletes/2/lastname' => 'Athlete #3',
					'result' => 'Sum'
				) : ($detail == '1' ? array(	// detail=1
					'result_rank' => 'Rank',
					'team_name' => 'Teamname',
					//'team_nation' => 'Nation',
					'athletes/0/lastname' => array('label' => 'Athlete #1', 'colspan' => 3),
					'athletes/0/firstname' => '',
					'athletes/0/result_time' => '',
					'athletes/1/lastname' => array('label' => 'Athlete #2', 'colspan' => 3),
					'athletes/1/firstname' => '',
					'athletes/1/result_time' => '',
					'athletes/2/lastname' => array('label' => 'Athlete #3', 'colspan' => 3),
					'athletes/2/firstname' => '',
					'athletes/2/result_time' => '',
					'result' => 'Sum'
				) : array(	// detail=0
					'result_rank' => 'Rank',
					'team_name' => 'Teamname',
					'team_nation' => 'Nation',
					'result' => 'Sum'
				));
				break;

			case 'ranking':
				$cols = array(
					'result_rank' => 'Rank',
					'lastname' => array('label' => 'Name', 'colspan' => 2),
					'firstname' => '',
					'nation' => 'Nation',
					'points' => 'Points',
					'result' => 'Result'
				);
				if (empty($detail))
				{
					unset($cols['result']);
					// allow to click on points to show single results
					$cols['points'] = array(
						'label' => $cols['points'],
						'url' => location.href+'&detail=1'
					);
				}
				break;

			default:
				$cols = isset($detail) && $detail == '0' ? array(
					'result_rank' => 'Rank',
					'lastname' => array('label' => 'Name', 'colspan' => 2),
					'firstname' => '',
					'nation' => 'Nation',
					'result' => 'Result'
				) : array(
					'result_rank' => 'Rank',
					'lastname' => array('label' => 'Name', 'colspan' => 2),
					'firstname' => '',
					'nation' => 'Nation',
					'start_number' => 'StartNr',
					'result' => 'Result'
				);
				break;
		}
		// for SUI and GER competitions replace nation
		if (!empty($this->data['nation']))
		{
			switch ($this->data['nation'])
			{
				case 'GER':
					$this->replace_attributes($cols, 'nation', 'federation', 'DAV Sektion');
					break;
				case 'SUI':
					$this->replace_attribute($cols, 'nation', 'city', 'City');
					break;
			}
		}
		// for general result use one column per heat
		if (isset($cols['result']) && $this->data['route_names'] && $this->data['route_order'] == -1)
		{
			unset($cols['result']);
			// show final first and 2. quali behind 1. quali: eg. 3, 2, 0, 1
			foreach(array_reverse($this->data['route_names'], true) as $route => $name)
			{
				if ($route != 1 && isset($this->data['route_names'][abs($route)]))
				{
					$cols['result'.abs($route)] = $this->data['route_names'][abs($route)];
				}
			}
			// evtl. add points column
			if (!empty($this->data['participants'][0]['quali_points']))
			{
				$cols['quali_points'] = 'Points';
			}
			$title_prefix = '';
		}
		if (!empty($cols['result']) && !empty($this->data['participants'][0]['rank_prev_heat']) &&
			(!isset($detail) || $detail != 0))
		{
			$cols['rank_prev_heat'] = 'previous heat';
		}
		return $cols;
	}

	/**
	 * Replace attribute named from with one name to and value keeping the order of the attributes
	 *
	 * @param object obj
	 * @param string from
	 * @param string to
	 * @param mixed value
	 */
	private function replace_attribute(array &$attrs, $from, $to, $value)
	{
		$found = false;
		foreach($attrs as $attr => $val)
		{
			if (!$found)
			{
				if ($attr == $from)
				{
					$found = true;
					unset($attrs[$attr]);
					$attrs[$to] = $value;
				}
			}
			else
			{
				unset($attrs[$attr]);
				$attrs[$attr] = $val;
			}
		}
	}

	protected $fed_rows = array();
	protected $fed_rows_pos = array();

	/**
	 * Server-side rendering of starters / registration
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return html
	 */
	protected function Starters($id, &$title=null)
	{
		$data = &$this->data;
		//return '<pre>'.print_r($data, true)."</pre>\n";
		$title = $data['name'].' : '.$data['date_span'];
		$content = $this->tag('h1', $title, array('class' => 'compHeader'));
		if (!empty($data['deadline']))
		{
			$content .= $this->tag('h3', $this->lang('Deadline').': '.$data['deadline'], array('class' => 'resultDate'));
		}

		// create header row
		$thead = $this->tag('th', $this->lang(isset($data['federations']) ? 'Federation' : 'Nation'));
		$cats = array();
		foreach($data['categorys'] as $i => $cat)
		{
			$thead .= $this->tag('th', $cat['name'], array('class' => 'category'));
			$cats[$cat['GrpId']] = $i;
		}
		$thead = $this->tag('tr', $thead, array(), false);

		$tbody = '';
		foreach($data['athletes'] as $athlete)
		{
			// evtl. create new row for federation/nation
			if (!isset($fed) || $fed != $athlete['reg_fed_id'])
			{
				$tbody .= $this->fillUpFedRows();
			}
			// find rows with space in column of category
			$cat_col = $cats[$athlete['cat']];
			$r = 0;
			foreach($this->fed_rows_pos as $r => $pos)
			{
				if ($pos <= $cat_col) break;
			}
			if (!$this->fed_rows_pos || $pos > $cat_col)	// create a new fed-row
			{
				if (!isset($fed) || $fed != $athlete['reg_fed_id'])
				{
					$fed = $athlete['reg_fed_id'];
					$this->fed_rows[++$r] = $this->tag('th', $this->federation($fed), array('class' => 'federation'));
				}
				else
				{
					$this->fed_rows[++$r] = $this->tag('th');
				}
				$this->fed_rows_pos[$r] = 0;
			}
			$this->fillUpFedRow($r, $cat_col);
			// create athlete cell
			$this->fed_rows[$r] .= $this->tag('td',
				$this->tag('span', $athlete['lastname'], array('class' => 'lastname')).
				$this->tag('span', $athlete['firstname'], array('class' => 'firstname')),
				array('class' => 'athlete'), false
			);
			$this->fed_rows_pos[$r]++;
		}
		$tbody .= $this->fillUpFedRows();

		$tfoot = $this->tag('tr',$this->tag('th',
			$this->lang('Total of %1 athlets registered in all categories.', count($data['athletes'])),
			array('colspan' => 1+count($data['categorys']))), array(), false);

		$content .= $this->tag('table',
			$this->tag('thead', $thead, array(), false).
			$this->tag('tbody', $tbody, array(), false).
			$this->tag('tfoot', $tfoot, array(), false),
			array('class' => 'DrTable'), false);

		return $this->tag('div', $content, array(
			'id' => $id,
			'class' => 'Starters',
		), false);
	}

	/**
	 * Fill a single fed-row up to a given position with empty td's
	 *
	 * @param int $r row-number
	 * @param int $to column-number, default whole row
	 */
	private function fillUpFedRow ($r, $to=null)
	{
		if (!isset($to)) $to = count($this->data['categorys']);

		for (; $this->fed_rows_pos[$r] < $to; ++$this->fed_rows_pos[$r])
		{
			$this->fed_rows[$r] .= $this->tag('td');
		}
	}

	/**
	 * Fill up all fed rows with empty td's
	 *
	 * @return string with filled up rows
	 */
	private function fillUpFedRows()
	{
		$rows = '';
		foreach($this->fed_rows as $r => &$row)
		{
			$this->fillUpFedRow($r);
			$rows .= $this->tag('tr', $row, array(), false);
		}
		$this->fed_rows = $this->fed_rows_pos = array();

		return $rows;
	}

	/**
	 * Get name of federation specified by given id
	 *
	 * @param _fed_id
	 * @returns string with name
	 */
	private function federation($fed_id)
	{
		if (isset($this->data['federations']))
		{
			foreach($this->data['federations'] as $fed)
			{
				if ($fed['fed_id'] == $fed_id)
				{
					return !empty($fed['shortcut']) ? $fed['shortcut'] : $fed['name'];
				}
			}
		}
		return $fed_id;	// nation of int. competition
	}

	/**
	 * Server-side rendering of calendar
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return html
	 */
	protected function Competitions($id, &$title=null)
	{
		$data = &$this->data;
		//return '<pre>'.print_r($data, true)."</pre>\n";
		if (!($year = (int)$data['competitions'][0]['date'])) $year = (int)date('Y');

		$title = $this->lang('Calendar').' '.$year;
		$content = $this->tag('h1', $title);
$content .= '<script>
function change_filter(form, value)
{
	// unfortunately modifying form.action does NOT work :-(
	//form.action=form.action.replace(/(#|\\?).*$/, "?"+value);
	var parts = value.split("&");
	for(var i=0; i < parts.length; ++i)
	{
		var name_val = parts[i].split("=", 2);
		var hidden = document.createElement("input");
		hidden.name = name_val[0];
		hidden.type = "hidden";
		hidden.value = name_val[1];
		jQuery(form).append(hidden);
	}
	form.submit();
}
</script>';
		if (!isset($data['years']) || !is_array($data['years']))
		{
			$data['years'] = array($year+1, $year, $year-1);
		}
		$options = '';
		foreach($data['years'] as $y)
		{
			$options .= $this->tag('option', $y, array(
				'value' => $y,
			)+((string)$y === (string)$year ? array('selected' => '') : array()));
		}
		$filters = $this->tag('select', $options, array(
			'name' => 'year',
			'onchange' => 'this.form.submit()',
		), false);

		$options = '';
		if (isset($this->widget_params['Competitions']) && $this->widget_params['Competitions'])
		{
			foreach($this->widget_params['Competitions'] as $label => $fragment)
			{
				$options .= $this->tag('option', $label, array(
					'value' => $fragment,
				)/*+((string)$y === (string)$year ? array('selected' => '') : array())*/);
			}
			$filters .= $this->tag('select', $options, array(
				'onchange' => 'change_filter(this.form, this.value)',
			), false);
		}
		$page_url = preg_replace('/(year|filter\[[^]]+\]|'.self::FRAGMENT.')=[^&]*(&|$)/', '', $_SERVER['REQUEST_URI']);
		$filters = $this->tag('form', $filters, array(
			'method' => 'GET',
			'action' => $page_url,
		), false);
		$content .= $this->tag('div', $filters, array('class' => 'filter'), false);

		foreach($data['competitions'] as $competition)
		{
			$comp = $this->tag('div', $competition['name'], array('class' => 'title'));
			$comp .= $this->tag('div', $competition['date_span'], array('class' => 'date'));
			$cats = $links = '';
			$links2labels = array(
				'homepage' => 'Event Website',
				'info' => 'Information',
				'startlist' => 'Startlist',
				'result' => 'Result',
			);
			//echo '<pre>'.print_r($competition['cats'], true)."</pre>\n";
			if (isset($competition['cats']))
			{
				foreach($competition['cats'] as $cat)
				{
					$url = '';
					if (isset($cat['status']))
					{
						switch($cat['status'])
						{
							case 4:	// registration
								$links2labels['starters'] = 'Starters';
								$competition['starters'] = $page_url.'#!type=starters&comp='.$competition['WetId'];
								break;
							case 2:	// startlist in result-service
							case 1:	// result in result-service
							case 0:	// result in ranking (ToDo: need extra export, as it might not be in result-service)
								$url = $page_url.'#!comp='.$competition['WetId'].'&cat='.$cat['GrpId'];
								break;
						}
					}
					if ($url)
					{
						$cats .= $this->tag('li', $this->tag('a', $cat['name'], array('href' => $url)), array(), false);
					}
					else
					{
						$cats .= $this->tag('li', $cat['name']);

					}
				}
			}
			foreach($links2labels as $link => $label)
			{
				if (!empty($competition[$link]))
				{
					$links .= $this->tag('li', $this->tag('a', $label, array(
						'href' => $competition[$link],
					)+($link != 'starters' ? array(
						'target' => '_blank',
					):array())), array(), false);
				}
			}
			if ($links) $comp .= $this->tag('ul', $links, array('class' => 'links'), false);
			if ($cats) $comp .= $this->tag('ul', $cats, array('class' => 'cats'), false);

			$content .= $this->tag('div', $comp, array('class' => 'competition'), false);
		}
		return $this->tag('div', $content, array(
			'id' => $id,
			'class' => 'Competitions',
		), false);
	}

	/**
	 * Translate a string
	 *
	 * @param string $str
	 * @param mixed variable number of arguments to replace placeholder %1, %2, ...
	 * @return string
	 */
	protected function lang($str)
	{
		if (!empty($this->translate) && is_callable($this->translate))
		{
			$args = func_get_args();
			$str = call_user_func_array($this->translate, $args);
		}
		if (func_num_args() > 1)
		{
			$args = func_get_args();
			array_shift($args);
			foreach($args as $n => $arg)
			{
				$args['%'.(1+$n)] = $arg;
				unset($args[$n]);
			}
			$str = strtr($str, $args);
		}
		return $str;
	}

	/**
	 * Render a single html tag incl. quoting of attributes and (optional) content
	 *
	 * @param string $tag name of tag, eg. 'div'
	 * @param string $content=''
	 * @param array $attrs=array() attributes as name => (unquoted!) value pairs
	 * @param boolean $quote_content=true should $content run through htmlspecialchars
	 */
	private function tag($tag, $content='', array $attrs=array(), $quote_content=true)
	{
		$html = '<'.$tag;
		foreach($attrs as $name => $value)
		{
			$html .= ' '.$name.((string)$value === '' ? '' : '="'.htmlspecialchars($value, ENT_COMPAT, $this->charset).'"');
		}
		if ($content === '')
		{
			$html .= '/>';
		}
		else
		{
			$nl = strlen($content) < 80 ? '' : "\n";
			$html .= '>'.$nl.
				($quote_content ? htmlspecialchars($content, ENT_COMPAT, $this->charset) : $content).
				$nl.'</'.$tag.'>'."\n";
		}
		return $html;
	}
}
