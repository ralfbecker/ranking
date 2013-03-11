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
			parse_str($_GET[self::FRAGMENT], $params);

			try {
				return $this->render_widgets($params, $id, $title);
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
	 * Render widget server-side depending on given parameters
	 *
	 * @param array $params
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return html
	 */
	public function render_widgets(array $params, $id='widget_content', &$title=null)
	{
		$query = '';
		foreach(array('person','comp','cat','nation','year','filter','type') as $name)
		{
			if (isset($params[$name]))
			{
				foreach((array)$params[$name] as $key => $value)
				{
					$query .= ($query ? '&' : '?').
						$name.(is_string($key) ? '['.$key.']' : '').'='.urlencode($value);
				}
			}
		}
		if (!($json = file_get_contents($this->json_url.$query)))
		{
			throw new Exception("Requesting data from $url failed!");
		}
		if (!($data = json_decode($json, true)))
		{
			throw new Exception("Data from $url is NO valid JSON!");
		}
		if (isset($data['competitions']))
		{
			return $this->Competitions($data, $id, $title);
		}
		return "Not yet implemented!";
	}

	/**
	 * Server-side rendering of calendar
	 *
	 * @param array $data
	 * @param string $id='widget_content'
	 * @param string &$title on return of server-side rendering title of widget, to eg. put in page-title
	 * @return html
	 */
	protected function Competitions(array $data, $id, &$title=null)
	{
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
				//'name' => 'filter',
//				'onchange' => 'this.form.action=this.form.action.replace(/(#|\\?).*$/, "?"+this.value); this.form.submit()',
				'onchange' => 'change_filter(this.form, this.value)',
			), false);
		}
		$filters = $this->tag('form', $filters, array(
			'method' => 'GET',
			'action' => $_SERVER['REQUEST_URI'],
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
								$competition['starters'] = '#!type=starters&comp='.$competition['WetId'];
								break;
							case 2:	// startlist in result-service
							case 1:	// result in result-service
							case 0:	// result in ranking (ToDo: need extra export, as it might not be in result-service)
								$url = '#!comp='.$competition['WetId'].'&cat='.$cat['GrpId'];
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
	 * @return string
	 */
	protected function lang($str)
	{
		if (!empty($this->translate) && is_callable($this->translate))
		{
			$args = func_get_args();
			$str = call_user_func_array($this->translate, $args);
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
