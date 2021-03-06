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
 * Renders calendar, registration, results, rankings and profiles with data from JSON feed
 *
 * By default widget only returns html/javascript to render on client-side / browser.
 *
 * If search-engine encounters URL with #!, it requests page-content with _escaped_fragment_ query param
 * and widget renders on server-side to support crawler not understanding javascript.
 */
class ranking_widget
{
	/**
	 * Name of get parameter to trigger serverside rendering
	 */
	const FRAGMENT = '_escaped_fragment_';
	/**
	 * Render all links as hash (default), uses server-side rendering only if _escaped_fragment_ query param given
	 */
	const URL_HASH = '#!';
	/**
	 * Render all links as query, this forces server-side rendering
	 */
	const URL_QUERY = '?';
	/**
	 * Render all links as _escaped_fragment_ query param AND force server-side rendering
	 */
	const URL_TEST = 'test';

	/**
	 * What type of URLs to render, see URL_* constants
	 *
	 * @var string
	 */
	public $url_mode = self::URL_HASH;

	/**
	 * Default number of best results to show (should match default of dr_api.js!)
	 */
	const DEFAULT_BEST_RESULTS = 12;
	/**
	 * Best results to show in profile
	 *
	 * @var int
	 */
	public $best_results = self::DEFAULT_BEST_RESULTS;

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

		// need to use https url for https
		if ($_SERVER['HTTPS'] && substr($json_url, 0, 7) === 'http://')
		{
			if (substr($json_url, 0, 29) === 'http://egw.ifsc-climbing.org/')
			{
				$this->json_url = str_replace('http://egw.ifsc-climbing.org/', 'https://ifsc.egroupware.net/', $json_url);
			}
			else
			{
				$this->json_url = str_replace('http://', '//', $json_url);
			}
		}

		$this->throw_error = $throw_error;
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
		// do we want server-side rendering
		if (isset($_GET[self::FRAGMENT]) || in_array($this->url_mode, array(self::URL_QUERY, self::URL_TEST)))
		{
			if (isset($_GET[self::FRAGMENT]))
			{
				parse_str($_GET[self::FRAGMENT], $this->params);
			}
			else
			{
				$this->params = $_GET;
			}
			//error_log(__METHOD__."('$id') params=".print_r($this->params, true));

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
	 * Get URL for given query
	 *
	 * @param string|array $query query parameter as query string, url or array
	 * @return string url
	 */
	public function url($query)
	{
		if (is_array($query))
		{
			$params = array();
			foreach($query as $name => $value)
			{
				$params[] = $name.'='.urlencode($value);
			}
			$query = implode('&', $params);
		}
		elseif($query[0] == '/' || strpos($query, '://'))
		{
			if (($q = parse_url($query, PHP_URL_FRAGMENT)) || ($q = parse_url($query, PHP_URL_QUERY)))
			{
				$query = $q[0] == '!' ? substr($q, 1) : $q;
			}
		}
		switch($this->url_mode)
		{
			case self::URL_HASH:
				$url = $this->page_url.self::URL_HASH.$query;
				break;
			case self::URL_TEST:
				$query = self::FRAGMENT.'='.urlencode($query);
				// fall through
			case self::URL_QUERY:
				$url = $this->page_url.(strpos($this->page_url, '?') === false ? '?' : '&').$query;
				break;
		}
		//error_log(__METHOD__."() url_mode='$this->url_mode', page_url='$this->page_url', query='$query' --> url='$url'");
		return $url;
	}

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
		if (substr($this->page_url, -1) == '?') $this->page_url = substr($this->page_url, 0, -1);

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
		if (isset($this->data['results']))
		{
			return $this->Profile($id, $title);
		}
		return "Not yet implemented!";
	}

	/**
	 * Default Profile template, if none is specified via widget_params['Profile']
	 *
	 * @var string
	 */
	private static $default_template = '
<table class="profileHeader">
 <thead>
  <tr>
   <td class="profilePhoto"><img src="$$photo$$" border="0"></td>
   <td>
  	<h1><a href="$$homepage$$" target="_blank">
	  <span class="firstname">$$firstname$$</span>
	  <span class="lastname">$$lastname$$</span>
  	</a></h1>
    <h2 class="profileNation">$$nation$$</h1>
    <h3 class="profileFederation"><a href="$$fed_url$$" target="_blank">$$federation$$</a></h1>
   </td>
   <td class="profileLogo"><a href="http://www.digitalROCK.de" target=_blank><img src="http://www.digitalrock.de/dig_rock-155x100.png" title="digital ROCK\'s Homepage" /></a></td>
  </tr>
 </thead>
</table>
<table cols="6" class="profileData">
  <thead>
	<tr>
		<td>age:</td>
		<td class="profileAge">$$age$$</td>
		<td>date of birth:</td>
		<td colspan="3" class="profileBirthdate">$$birthdate$$</td>
	</tr>
	<tr class="profileHideRowIfEmpty">
		<td colspan="2"></td>
		<td>place of birth:</td>
		<td colspan="3" class="profileBirthplace profileHideRowIfEmpty">$$birthplace$$</td>
	</tr>
	<tr class="profileHideRowIfEmpty">
		<td>height:</td>
		<td class="profileHeight profileHideRowIfEmpty">$$height$$</td>
		<td>weight:</td>
		<td colspan="3" class="profileWeight profileHideRowIfEmpty">$$weight$$</td>
	</tr>
	<tr class="profileMarginTop profileHideRowIfEmpty">
		<td>address:</td>
		<td colspan="2" class="profileCity profileHideRowIfEmpty">$$postcode$$ $$city$$</td>
		<td colspan="3" class="profileStreet profileHideRowIfEmpty">$$street$$</td>
	</tr>
	<tr class="profileMarginTop profileHideRowIfEmpty">
		<td colspan="2">practicing climbing for:</td>
		<td colspan="4" class="profilePractice profileHideRowIfEmpty">$$practice$$</td>
	</tr>
	<tr class="profileHideRowIfEmpty">
		<td colspan="2">professional climber (if not, profession):</td>
		<td colspan="4" class="profileProfessional profileHideRowIfEmpty">$$professional$$</td>
	</tr>
	<tr class="profileHideRowIfEmpty">
		<td colspan="2">other sports practiced:</td>
		<td colspan="4" class="profileOtherSports profileHideRowIfEmpty">$$other_sports$$</td>
	</tr>
	<tr class="profileMarginTop profileHideRowIfEmpty">
		<td colspan="6" class="profileFreetext profileHideRowIfEmpty">$$freetext$$</td>
	</tr>
	<tr class="profileMarginTop">
		<td colspan="2" class="profileRanglist"><a href="$$rankings/0/url$$">$$rankings/0/name$$</a>:</td>
		<td class="profileRank">$$rankings/0/rank$$</td>
		<td colspan="2" class="profileRanglist"><a href="$$rankings/1/url$$">$$rankings/1/name$$</a>:</td>
		<td class="profileRank">$$rankings/1/rank$$</td>
	</tr>
	<tr>
		<td colspan="2" class="profileRanglist"><a href="$$rankings/2/url$$">$$rankings/2/name$$</a>:</td>
		<td class="profileRank">$$rankings/2/rank$$</td>
		<td colspan="2" class="profileRanglist"><a href="$$rankings/3/url$$">$$rankings/3/name$$</a>:</td>
		<td class="profileRank">$$rankings/3/rank$$</td>
	</tr>
	<tr class="profileResultHeader profileMarginTop">
		<td colspan="6"><a href="javascript:widget.widget.toggleResults()" title="show all results">best results:</a></td>
	</tr>
   </thead>
   <tbody>
	<tr class="profileResult $$results/N/weightClass$$">
		<td class="profileResultRank">$$results/N/rank$$</td>
		<td colspan="4" class="profileResultName"><a href="$$results/N/url$$">$$results/N/cat_name+name$$</a></td>
		<td class="profileResultDate">$$results/N/date$$</td>
	</tr>
  </tbody>
</table>
';

	/**
	 * Server-side rendering of athlete profile
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return string with html
	 */
	protected function Profile($id, &$title=null)
	{
		$template = empty($this->widget_params['Profile']) ? self::$default_template : $this->widget_params['Profile'];
		//echo "<pre>".htmlspecialchars($template)."</pre>\n";

		// replace non-result data
		$data = $this->data;	// can NOT use $this in closure with PHP 5.3, requires 5.4!
		$content = preg_replace_callback('/\$\$([^$]+)\$\$/', function($matches) use($data)
		{
			foreach(explode('/', $matches[1]) as $part)
			{
				if (empty($data[$part]))
				{
					return $part === 'N' ? $matches[0] : '';
				}
				$data = $data[$part];
			}
			switch($part)
			{
				case 'practice':
					$data = lang('%1 years, since %2', $data, date('Y')-$data);
					break;
				case 'height':
					$data .= ' cm';
					break;
				case 'weight':
					$data .= ' kg';
					break;
				case 'url':
					return $this->url($data);
			}
			return htmlspecialchars($data, ENT_COMPAT, $this->charset);
		},
		$template);

		// replace result data
		$data = $this->data;
		if (preg_match_all('|\s*<tr.*</tr>\n?|siU', $content, $matches))
		{
			foreach($matches[0] as $row)
			{
				if (strpos($row, '$$results/N/') === false) continue;

				$year = (int)date('Y');
				$limits = array();
				foreach($data['results'] as &$result)
				{
					$result['weight'] = $weight = $result['rank']/2 + ($year-$result['date']) + 4*!empty($result['nation']);
					// maintain array of N best competitions (least weight)
					if (count($limits) < $this->best_results || $weight < $limits[count($limits)-1])
					{
						foreach($limits as $n => $limit)
						{
							if ($limit > $weight) break;
						}
						if (!isset($limit) || $limit < $weight && $n == count($limits)-1) $n = count($limits);
						$limits = array_merge(array_slice($limits, 0, (int)$n), array($weight),
							array_slice($limits, (int)$n, $this->best_results-1-$n));
					}
				}
				unset($resutl);
				$weight_limit = array_pop($limits);

				$rows = '';
				$l = 0;
				foreach($data['results'] as $i => $result)
				{
					if ($result['weight'] > $weight_limit || ++$l > $this->best_results)
					{
						$result['weightClass'] = 'profileResultHidden';
					}
					$rows .= preg_replace_callback('/\$\$results\/N\/([^$]+)\$\$/', function($matches) use($data, $result)
					{
						switch ($placeholder=$matches[1])
						{
							case 'cat_name+name':
								$ret = ($result['GrpId'] != $data['GrpId'] ? $result['cat_name'].': ' : '').$result['name'];
								break;
							case 'date':
								$ret = implode('.', explode('-', $result['date']));
								break;
							case 'url':
								return $this->url($result['url']);
							default:
								$ret = empty($result[$placeholder]) ? '' : $result[$placeholder];
								break;
						}
						return htmlspecialchars($ret, ENT_COMPAT, $this->charset);
					},
					$row);
				}
				$content = str_replace($row, $rows, $content);
			}
		}

		// add our id and class
		$content = $this->tag('div', $content, array('id' => $id, 'class' => 'Profile'), false);

		// remove links with empty href
		// remove images with empty src
		// hide rows with profileHideRowIfEmpty, if ALL td.profileHideRowIfEmpty are empty
		$content .= "<script>
	jQuery('#$id').find('a[href=\"\"]').replaceWith(function(){
		return jQuery(this).contents();
	});
	jQuery('#$id').find('img[src=\"\"]').remove();
	jQuery('#$id').find('tr.profileHideRowIfEmpty').each(function(index, row){
		var tds = jQuery(row).children('td.profileHideRowIfEmpty');
		if (tds.length == tds.filter(':empty').length)
		{
			jQuery(row).hide();
		}
	});
	var widget = { widget: { toggleResults: function()
	{
		var hidden_rows = jQuery('#$id').find('tr.profileResultHidden');
		var display = hidden_rows.length ? jQuery(hidden_rows[0]).css('display') : 'none';
		hidden_rows.css('display', display == 'none' ? 'table-row' : 'none');
	}}};
</script>\n";

		return $content;
	}

	private $result_cols = array();

	/**
	 * Server-side rendering of results, rankings and startlists
	 *
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @param string $startlist=null true: display startlist, false: display result, null check for type=startlist
	 * @return html
	 */
	protected function Resultlist($id, &$title=null, $startlist=null)
	{
		if (is_null($startlist)) $startlist = isset($this->params['type']) && $this->params['type'] == 'startlist';
		if (empty($this->data['participants'][0]['result_rank'])) $startlist = true;

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
			$url = $this->url('comp='.$this->data['WetId'].'&cat='.$this->data['GrpId'].'&route=');
			foreach($this->data['route_names'] as $r => $name)
			{
				if ($r != $this->data['route_order'])
				{
					$li = $this->tag('li', $this->tag('a', $name, array(
						'href' => $url.$r,
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

		$this->result_cols = $this->result_cols(!$startlist);
		//echo "<pre>".print_r($this->result_cols, true)."</pre>\n";

		if ($startlist)
		{
			usort($this->data['participants'], function($a,$b)
			{
				return $a['start_order']-$b['start_order'];
			});
		}

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
							'href' => $this->url($data['url']),
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
				if (!$startlist && (empty($data['result_rank']) || $data['result_rank'] < 1))
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
							'href' => $this->url('person='.$data['PerId'].'&cat='.
								(isset($this->data['GrpId']) ? $this->data['GrpId'] : $this->data['cat']['GrpId'])),
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
						'href' => $this->url($col_data['url']),
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

		if ($result)
		{
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
							'url' => $_SERVER['REQUEST_URI'].'&detail=1'
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
		}
		else
		{
			switch($this->data['discipline'])
			{
				case 'speedrelay':
					$cols = !isset($detail) ? array(	// default detail
						'start_order' => 'StartNr',
						'team_name' => 'Teamname',
						'athletes/0/lastname' => 'Athlete #1',
						'athletes/1/lastname' => 'Athlete #2',
						'athletes/2/lastname' => 'Athlete #3'
					) : ($detail ? array(	// detail=1
						'start_order' => 'StartNr',
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
						'athletes/2/result_time' => ''
					) : array(	// detail=0
						'start_order' => 'StartNr',
						'team_name' => 'Teamname',
						'team_nation' => 'Nation'
					));
					break;

				default:
					$cols = array(
						'start_order' => array('label' => 'StartNr', 'colspan' => 2),
						'start_number' => '',
						'lastname' => array('label' => 'Name', 'colspan' => 2),
						'firstname' => '',
						'birthyear' => 'Birthyear',
						'nation' => 'Nation'
					);
					break;
			}

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
				if ($label == '_comp_url')
				{
					$comp_url = $fragment;
					continue;
				}
				$options .= $this->tag('option', $label, array(
					'value' => $fragment,
				)/*+((string)$y === (string)$year ? array('selected' => '') : array())*/);
			}
			$filters .= $this->tag('select', $options, array(
				'onchange' => 'change_filter(this.form, this.value)',
			), false);
		}
		$filters = $this->tag('form', $filters, array(
			'method' => 'GET',
			'action' => $this->page_url,
		), false);
		$content .= $this->tag('div', $filters, array('class' => 'filter'), false);

		foreach($data['competitions'] as $competition)
		{
			$title = $competition['name'];
			if ($comp_url)
			{
				$title = $this->tag('a', $title, array('href' => $comp_url.$competition['WetId']));
			}
			$comp = $this->tag('div', $title, array('class' => 'title'));
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
								$competition['starters'] = $this->url('type=starters&comp='.$competition['WetId']);
								break;
							case 2:	// startlist in result-service
							case 1:	// result in result-service
							case 0:	// result in ranking (ToDo: need extra export, as it might not be in result-service)
								$url = $this->url('comp='.$competition['WetId'].'&cat='.$cat['GrpId']);
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
