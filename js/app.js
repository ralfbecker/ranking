/**
 * EGroupware digital ROCK Rankings & ResultService
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

/**
 * @augments AppJS
 */
app.classes.ranking = AppJS.extend(
{
	appname: 'ranking',

	/**
	 * eT2 content after call to et2_ready
	 */
	content: {},

	/**
	 * Constructor
	 *
	 * @memberOf app.ranking
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Athlete to select when entering measurement
	 */
	select_athlete: undefined,

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} et2 newly ready object
	 * @param {string} name
	 */
	et2_ready: function(et2, name)
	{
		// call parent
		this._super.apply(this, arguments);

		this.content = this.et2.getArrayMgr('content').data;

		switch (name)
		{
			case 'ranking.result.index':
				if (this.resultlist && this.resultlist.update_handle)
				{
					window.clearInterval(this.resultlist.update_handle);
					delete this.resultlist.update_handle;
				}
				delete this.resultlist;
				this._set_nextmacth_sort_headers();
				var sort_widget = this.et2.getWidgetById(this.content.nm.order);
				if (sort_widget) sort_widget.setSortmode(this.content.nm.sort.toLowerCase());
				jQuery('#ranking-result-index').css('overflow-y', 'auto');	// init_topo switches to "hidden"
				if (this.content.nm.show_result == 4)	// measurement
				{
					switch(this.content.nm.discipline)
					{
						case 'boulder':
							if (this.content.try) this.try_num(this.content.try);
							if (this.sorted_by != 'startorder') this.sort_athletes();
							if (this.select_athlete)
							{
								this.et2.getWidgetById('nm[PerId]').set_value(this.select_athlete);
								delete this.select_athlete;
							}
							// fall through
						case 'selfscore':
							this.init_boulder();
							break;

						case 'lead':
							this.init_topo(this.content.holds);
							break;
					}
				}
				else
				{
					this.align_nbsp('div#ranking-result-index_rows_template table tr.row td[align=center] span.et2_label');

					this.show_hint(this.egw.lang("How to use Resultservice"),
						this.egw.lang("There is a manual in left menu under preferences.")+"\n\n"+
						this.egw.lang("Start- and result-lists no longer contain input fields or buttons:\ndouble-click on an athlete to enter his result, or right click for more options."),
						'no_resultservice_hint');
				}
				if (this.content.nm.template.match(/^ranking.result.index.speed_graph/))
				{
					jQuery('#ranking-result-index').on('dblclick', 'table.speed_athlete', jQuery.proxy(this.edit_pairing, this));
				}
				break;

			case 'ranking.registration':
				this.show_hint(this.egw.lang("How to register athletes"),
					this.egw.lang("1. select competition\n2. click on [+ Register]\n3. select category\n4. search for athletes to register")+"\n\n"+
					this.egw.lang("To remove already registered athletes:\nright click on them in list and choose 'delete' from context menu"),
					'no_registration_hint');
				break;

			case 'ranking.athlete.edit':
				var license_data = location.search.match(/(\?|&)license_(nation|year|cat)=/g);
				if (license_data && license_data.length === 3)
				{
					this.et2.getWidgetById('button[apply_license]').click();
				}
				break;

			case 'ranking.athlete.index':
			case 'ranking.competitions.list':
			case 'ranking.cup.list':
			case 'ranking.cats.list':
				this.show_hint(this.egw.lang("How to use EGroupware"),
					this.egw.lang("All lists no longer contain input fields or buttons:")+"\n"+
					this.egw.lang("Double-click on a row to view or edit it, or right click for more options.")+"\n"+
					this.egw.lang("To select multiple rows use Ctrl (Cmd for Mac) or Shift to select ranges."),
					'no_general_ui_hint');
				break;
		}
	},

	/**
	 * Align non-breaking-space separated parts of text by wrapping them in equally sized spans
	 *
	 * @param {string|jQuery} elems
	 */
	align_nbsp: function(elems)
	{
		jQuery(elems).contents().filter(function(){
			return this.nodeType === 3;
		}).replaceWith(function()
		{
			var parts = jQuery(this).text().split(/\u00A0+/);
			if (parts.length == 1) return jQuery(this).clone();
			var prefix = '<div class="tdAlign'+parts.length+'">';
			var postfix = '</div>';
			return jQuery(prefix+parts.join(postfix+prefix)+postfix);
		});
	},

	beamerGo: function()
	{
		var display = this.et2.getWidgetById('display');
		var url = this.et2.getWidgetById('href').getValue();

		// readonly/hidden display for anonymous session has no getValue method
		if (display && display.getValue && display.getValue())
		{
			var fragment = '<iframe src="http://'+location.host+url+
				'" scrolling="auto" frameborder="0" width="%width%" height="%height%" allowfullscreen></iframe>';

			let push_url = 'http://'+display.getValue()+'/pushURL?url='+encodeURIComponent(fragment)+'&embed=1';
			let win = window.open(push_url, '_blank', 'width=640,height=480');
			window.setTimeout(function() {
				win.close();
			}, 100);
		}
		else
		{
			document.location = url;
		}
	},

	/**
	 * Apply changes to beamer url
	 *
	 * @param {DOMNode} _node
	 * @param {et2_widget} _widget
	 */
	applyBeamerUrl: function(_node, _widget)
	{
		var href = this.et2.getWidgetById('href');
		var regexp = new RegExp('&'+_widget.id+'=[^&]*');
		var url = href.getValue().replace(regexp, '');
		var value = _widget.getValue();

		if (value !== '')
		{
			url += '&'+_widget.id+'='+encodeURIComponent(value);
		}
		href.set_value(url);

		this.egw.json('ranking_beamer::ajax_set_session', [_widget.id, value]).sendRequest();
	},

	/**
	 * This method sets nextmatch class for nextmatch_sortheaders
	 * with "this" context in order to be able to call sortBy local
	 * function by click on sortheaders.
	 */
	_set_nextmacth_sort_headers: function ()
	{
		var self = this;
		this.et2.iterateOver(function(_widget){
			_widget.setNextmatch(self);
		}, window, et2_nextmatch_sortheader);
	},

	/**
	 * Show a hint to the user, unless he already confirmed it (and we stored a named preference for it)
	 *
	 * @param {string} _title title of hint
	 * @param {string} _text text of hint
	 * @param {string} _name name of implicit preference set if user confirms hint / dont want to see it anymore, eg. "no_xyz_hint"
	 */
	show_hint: function(_title, _text, _name)
	{
		if (this.egw.getSessionItem(this.appname, _name))
		{
			return;
		}
		var pref = this.egw.preference(_name, this.appname, function(_value)
		{
			this.show_hint(_title, _text, _name);
		}, this);

		if (typeof pref == 'undefined')	// true: user already confirmed, false: not yet loaded
		{
			var self = this;
			var dialog = et2_dialog.show_dialog(function(_button)
			{
				if (_button == et2_dialog.NO_BUTTON)
				{
					self.egw.set_preference(self.appname, _name, true);
				}
				self.egw.setSessionItem(self.appname, _name);
			}, _text+"\n\n"+
			this.egw.lang("Display this message again next time?"), _title);
		}
	},

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 */
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
	{
		if (_app == 'ranking' && _id && typeof _id == 'object')
		{
			this.egw.open_link(this.egw.link('/index.php', _id), 'ranking');
		}
	},

	/**
	 * Handle links to not reload nextmatch, if index page should be refreshed (eg. Sidebox Header clicked)
	 *
	 * @param {string} url
	 * @returns {boolean} True if we could handle the link internally, false to let framework handle it
	 */
	linkHandler: function(url)
	{
		// if we are on our index page, check if current view contains nextmatch and refresh (applyFilter) it
		if (this.et2 && url.match(/(\/ranking\/(index\.php)?|\/index\.php\?menuaction=ranking\.ranking_ui\.index&ajax=true)$/))
		{
			var nextmatch_found = false;

			this.et2.iterateOver(function(_widget) {
				_widget.applyFilters();
				nextmatch_found = true;
			}, this, et2_nextmatch);

			return nextmatch_found;
		}
		return false;
	},

	/***************************************************************************
	 * Resultservice
	 **************************************************************************/

	/**
	 * onClick handler for nextmatch sortheader
	 *
	 * @param {string} _order
	 * @param {string} _sort
	 */
	sortBy: function(_order, _sort)
	{
		var sort_widget = this.et2.getWidgetById(_order);
		if (sort_widget)
		{
			var content = this.et2._inst.widgetContainer.getArrayMgr('content').data;
			if (content.nm.order && content.nm.order != _order)
			{
				var old_sort_widget = this.et2.getWidgetById(content.nm.order);
				old_sort_widget.setSortmode('none');
				content.nm.order = _order;
				content.nm.sort = 'DESC';
			}
			// state handling in nextmatch_sortheader is broken or wired, we do our own
			_sort = content.nm.sort != 'DESC' ? 'desc' : 'asc';
			content.nm.sort = _sort.toUpperCase();

			sort_widget.setSortmode(_sort);

			// set order&sort to hidden input and submit to server
			this.et2.getWidgetById('nm[order]').set_value(content.nm.order);
			this.et2.getWidgetById('nm[sort]').set_value(content.nm.sort);
			this.et2._inst.submit();
		}
	},

	/**
	 * Action to measure a selected athlet
	 *
	 * @param {egw_action} _action
	 * @param {Array} _selected
	 */
	action_measure: function(_action, _selected)
	{
		this.select_athlete = _selected[0].id;
		this.et2.getWidgetById('nm[show_result]').set_value('4');
	},

	/**
	 * Action to delete selected athlet(s)
	 *
	 * @param {egw_action} _action
	 * @param {Array} _selected
	 */
	action_delete: function(_action, _selected)
	{
		var self = this;
		var data = _selected[0].data;
		var msg = data.nachname+', '+data.vorname+', '+data.nation+' ('+data.start_number+')'+"\n\n"+
			this.egw.lang('Delete this participant (can NOT be undone)')+'?';

		et2_dialog.show_dialog(function(_button)
		{
			if (_button == et2_dialog.YES_BUTTON)
			{
				self.et2.getWidgetById('nm[action]').set_value('delete');
				self.et2.getWidgetById('nm[selected]').set_value([data.PerId]);
				self.et2._inst.submit();
			}
		}, msg, this.egw.lang('Delete'));
	},

	/***************************************************************************
	 * Resultist lead
	 **************************************************************************/

	/**
	 * onKeypress handler for height fields propagating "+", "-" or "t" to plus-selectbox
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_number} _widget
	 * @returns {Boolean}
	 */
	height_keypress: function(_event, _widget)
	{
		var key2plus = {
			116: '9999',	// t
			84: '9999',		// T
			43:	'1',		// +
			45: '-1',		// -
			32:	'0'			// space
		};
		var key = _event.keyCode || _event.which;
		if (typeof key2plus[key] != 'undefined')
		{
			var plus_widget = _widget.getParent().getWidgetById(_widget.id.replace('result_height', 'result_plus'));
			if (plus_widget) plus_widget.set_value(key2plus[key]);
			if (key2plus[key] === '9999') _widget.set_value('');	// for top, remove height
			return false;
		  }
		// "0"-"9", "." or "," --> allow and remove "Top"
		if (48 <= key && key <= 57 || key == 44 || key == 46)
		{
			var plus_widget = _widget.getParent().getWidgetById(_widget.id.replace('result_height', 'result_plus'));
			if (plus_widget && plus_widget.get_value() == '9999') plus_widget.set_value('0');
			return true;
		}
		// ignore all other chars
		return false;
	},

	/***************************************************************************
	 * Resultist boulder
	 **************************************************************************/

	/**
	 * onChange of top: if bonus not set, set it to the same number of tries as top
	 *  if top < bonus alert user and set top to bonus
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} top select box
	 */
	check_top: function(node, top)
	{
		var top_value = top ? top.get_value() : undefined;
		var bonus = top.getRoot().getWidgetById(top.id.replace(/top/g,'zone'));
		var bonus_value = bonus ? bonus.get_value() : undefined;

		if (bonus && (!bonus_value || bonus_value == '0') && parseInt(top_value) > 0) bonus.set_value(bonus_value=top_value);

		if (bonus && parseInt(top_value) > 0 && parseInt(top_value) < parseInt(bonus_value))
		{
			alert('Top < Bonus!');
			bonus.set_value(top_value);
		}
	},

	/**
	 * onChange of bonus: dont allow to set a bonus bigger then top or no bonus, but a top
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} bonus select box
	 */
	check_bonus: function(node, bonus)
	{
		var bonus_value = bonus ? bonus.get_value() : undefined;
		var top = bonus.getRoot().getWidgetById(bonus.id.replace(/zone/g,'top'));
		var top_value = top ? top.get_value() : undefined;

		if (top && parseInt(top_value) > 0 && parseInt(bonus_value) > parseInt(top_value))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Bonus > Top!');
				top.set_value(top_value=bonus_value);
			//}, 10);
		}
		if (top && parseInt(top_value) > 0 && !bonus_value)
		{
			top.set_value(bonus_value);
		}
	},

	/**
	 * onChange of tops: if bonus not set, set it to the same number of tops
	 *  if tops > zones alert user and set tops to zones or
	 *  if less tries then tops alert user and set tries to tops
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} tops select box
	 */
	check_tops: function(node, tops)
	{
		var bonus = tops.getRoot().getWidgetById(tops.id.replace(/tops/g, 'zones'));

		if (bonus && !bonus.get_value() && parseInt(tops.get_value()) > 0) bonus.set_value(tops.get_value());

		if (bonus && parseInt(tops.get_value()) > 0 && parseInt(tops.get_value()) > parseInt(bonus.get_value()))
		{
			alert('Top > Bonus!');
			bonus.set_value(tops.get_value());
		}

		var tries = tops.getRoot().getWidgetById(tops.id.replace(/tops/g, 'top_tries'));

		if (tries && !tries.get_value() && parseInt(tops.get_value()) > 0) tries.set_value(tops.get_value());

		if (tries && parseInt(tops.get_value()) > 0 && parseInt(tops.get_value()) > parseInt(tries.get_value()))
		{
			alert('Top > Tries!');
			tries.set_value(tops.get_value());
		}
	},

	/**
	 * onChange of top_tries: if number of tops > top_tries, set tops to tries
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} tries select box
	 */
	check_top_tries: function(node, tries)
	{
		var tops = tries.getRoot().getWidgetById(tries.id.replace(/top_tries/g, 'tops'));

		if (tops && parseInt(tops.get_value()) > 0 && parseInt(tops.get_value()) > parseInt(tries.get_value()))
		{
			alert('Top > Tries!');
			tops.set_value(top_tries.get_value());
		}
	},

	/**
	 * onChange of bonus sum: dont allow to set a boni bigger then tops or no bonus, but a top
	 * 	or if less tries then boni alert user and set tries to boni
	 *
	 * @param {DOMNode} node
	 * @param {DOMNode} bonus select box
	 */
	check_boni: function(node, bonus)
	{
		var tops = bonus.getRoot().getWidgetById(bonus.id.replace(/zones/g,'tops'));

		if (tops && parseInt(tops.get_value()) > 0 && parseInt(bonus.get_value()) < parseInt(tops.get_value()))
		{
			alert('Bonus < Top!');
			tops.set_value(bonus.get_value());
		}
		if (tops && parseInt(tops.get_value()) > 0 && !bonus.get_value())
		{
			tops.set_value(bonus.get_value());
		}

		var tries = bonus.getRoot().getWidgetById(bonus.id.replace(/zones/g,'zone_tries'));

		if (tries && !tries.get_value() && parseInt(bonus.get_value()) > 0) tries.set_value(bonus.get_value());

		if (tries && parseInt(bonus.get_value()) > 0 && parseInt(bonus.get_value()) > parseInt(tries.get_value()))
		{
			alert('Bonus > Tries!');
			tries.set_value(bonus.get_value());
		}
	},

	/**
	 * onChange of bonus tries: dont allow to set less tries then bonus
	 *
	 * @param {DOMNode} node
	 * @param {DOMNode} tries select box
	 */
	check_bonus_tries: function(node, tries)
	{
		var bonus = tries.getRoot().getWidgetById(tries.id.replace(/zone_tries/g,'zones'));

		if (bonus && parseInt(bonus.get_value()) > 0 && parseInt(bonus.get_value()) > parseInt(tries.get_value()))
		{
			alert('Bonus > Tries!');
			bonus.set_value(tries.get_value());
		}

	},

	/***************************************************************************
	 * Boulder measurement
	 **************************************************************************/

	resultlist: undefined,

	/**
	 * Init boulder measurement or update button state
	 */
	init_boulder: function()
	{
		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();
		var boulder_n = this.et2.getWidgetById('nm[boulder_n]');
		var n = boulder_n ? boulder_n.get_value() : null;

		// loading values from server
		if (PerId && n)
		{
			var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
			var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
			var route_order = this.et2.getWidgetById('nm[route]').get_value();
			var keys = {WetId: WetId, GrpId: GrpId, route_order: route_order, boulder_n: n};

			this.egw.json('ranking_boulder_measurement::ajax_load_athlete',
				[PerId, {},	// {} = send data back
				keys],
				function(_data)
				{
					this.et2.getWidgetById('zone').set_value(_data['zone'+n]);
					this.et2.getWidgetById('top').set_value(_data['top'+n]);
					this.et2.getWidgetById('avatar').set_src(_data['profile_url']);
					this.try_num(_data['try'+n]);

					this.message(_data.athlete);

					// disable UI, if user is no judge for the given problem
					if (typeof _data.is_judge !== 'undefined')
					{
						this.no_measurement(!_data.is_judge);
					}
				},
				null, false, this).sendRequest();
		}
		else
		{
			this.no_measurement(true);
		}

		jQuery('#ranking-result-index_button\\[update\\],#ranking-result-index_button\\[try\\],#ranking-result-index_button\\[bonus\\],#ranking-result-index_button\\[top\\]')
			.attr('disabled', !PerId || !n);

		// for selfscore install some behavior on bonus/top/flash checkboxes to
		// only allow valid combinations and ease use eg. click flash checks bonus&top
		if(this.content.nm.discipline == 'selfscore')
		{
			jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=zone]').on('change', function(){
				if (!this.checked)
				{
					jQuery(this.parentNode).find('input[type=checkbox]').prop('checked', false);
				}
			});
			jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=top]').on('change', function(){
				if (!this.checked)
				{
					jQuery(this.parentNode).find('input[type=checkbox][name*=flash]').prop('checked', false);
				}
				else
				{
					jQuery(this.parentNode).find('input[type=checkbox][name*=zone]').prop('checked', true);
				}
			});
			jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=flash]').on('change', function(){
				if (this.checked)
				{
					jQuery(this.parentNode).find('input[type=checkbox]').prop('checked', true);
				}
			});
			// hide not used bonus/top/flash checkboxes
			var use = this.content.selfscore_use || 't';
			if(use.indexOf('b') < 0)
			{
				jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=zone]').css('display','none');
			}
			if(use.indexOf('t') < 0)
			{
				jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=top]').css('display','none');
			}
			if(use.indexOf('f') < 0)
			{
				jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=flash]').css('display','none');
			}
		}

		if (!this.resultlist)
		{
			this.resultlist = new Resultlist('ranking-result-index_resultlist',egw_webserverUrl+'/ranking/json.php'+
				'?comp='+this.content.comp.WetId+
				'&cat='+this.et2.getWidgetById('nm[cat]').get_value()+
				'&route='+this.et2.getWidgetById('nm[route]').get_value()+'&detail=2&toc=0');

			// set methods for interaction with protocol
			if (window.protocol)
			{
				window.protocol.set_msg(jQuery.proxy(this.message, this));
				window.protocol.get_athlete(jQuery.proxy(this.get_athlete, this));
			}
		}
	},

	/**
	 * Disable boulder measurement to not allow sending updates
	 *
	 * @param {boolean} _ro
	 */
	no_measurement: function(_ro)
	{
		if (!this.et2 && _ro)
		{
			return window.setTimeout(this.no_measurement.bind(this, _ro), 100)
		}

		['button[update]','button[try]','button[bonus]','button[top]']
			.forEach(name => this.et2.getWidgetById(name).set_readonly(_ro));
	},

	/**
	 * Get athlete name by PerId using options from athlete selectbox
	 *
	 * @param {int} _PerId
	 * @returns {String}
	 */
	get_athlete: function(_PerId)
	{
		var select = this.et2.getWidgetById('nm[PerId]').getDOMNode();

		if (select && select.options)
		{
			for(var i=1; i < select.options.length; ++i)
			{
				var option = select.options.item(i);
				if (option.value == _PerId)
				{
					return jQuery(option).text();
				}
			}
		}
		return '#'+_PerId;
	},

	/**
	 * Display message
	 *
	 * @param {string} _msg message to display
	 */
	message: function(_msg)
	{
		this.et2.getWidgetById('msg').set_value(_msg);
	},

	/**
	 * Get current state
	 *
	 * @returns {object} with values for attributes try, bonus and top
	 */
	get_state: function()
	{
		return {
			try: this.try_num(),
			bonus: this.et2.getWidgetById('zone').get_value(),
			top: this.et2.getWidgetById('top').get_value()
		};
	},

	/**
	 * [Try] button clicked --> update number
	 *
	 * @param button
	 */
	try_clicked: function(button)
	{
		this._button_vibrate();
		var state = this.get_state();
		var num = this.try_num();
		this.try_num(++num);

		var bonus = this.et2.getWidgetById('zone');

		// set bonus 'No'=0, for 1. try, if there's no bonus yet
		if (num == 1 && bonus && bonus.get_value() === '')
		{
			bonus.set_value('0');
		}
		this.update_boulder('try', state);
	},

	/**
	 * Set tries to given number
	 *
	 * @param {DOMNode} _node
	 * @param {et2_selectbox} _widget
	 */
	set_try: function(_node, _widget)
	{
		this.try_num(_widget.get_value(), true);
	},

	/**
	 * Get number of try from label of try button
	 *
	 * @param {int} set_value 0: reset, 1: set to 1, if not already higher
	 * @param {boolean} set_anyway set, even if number is smaller
	 * @returns int number of try
	 */
	try_num: function(set_value, set_anyway)
	{
		var try_button = this.et2.getWidgetById('button[try]');

		var num = parseInt(jQuery(try_button.getDOMNode()).text());
		if (isNaN(num)) num = 0;

		if (typeof set_value != 'undefined')
		{
			if (set_value == 0 || num < set_value || set_anyway)
			{
				this.et2.getWidgetById('try').set_value(num = set_value);
			}
			try_button.set_label((num ? ''+num+'. ' : '')+try_button.options.label);
		}
		return num;
	},

	/**
	 * Bonus button clicked
	 *
	 * @param button
	 */
	bonus_clicked: function(button)
	{
		this._button_vibrate();
		var state = this.get_state();
		var bonus = this.et2.getWidgetById('zone');

		// only update bonus, if there is no bonus yet
		if (!state.bonus || state.bonus == '0')
		{
			bonus.set_value(this.try_num(1));
		}
		this.check_bonus(bonus.getDOMNode(), bonus);

		this.update_boulder('bonus', state);
	},

	/**
	 * Bonus button clicked
	 *
	 * @param button
	 */
	top_clicked: function(button)
	{
		this._button_vibrate();
		var state = this.get_state();
		var bonus = this.et2.getWidgetById('zone');
		var top = this.et2.getWidgetById('top');
		var num = this.try_num(1);

		if (!num.isNaN)
		{
			if(!bonus.get_value() || bonus.get_value() == '0') bonus.set_value(num);
			top.set_value(num);
			this.check_top(top.getDOMNode(), top);
		}
		this.update_boulder('top', state);
	},

	/**
	 * Sending bonus and top for PerId to server
	 *
	 * @param {string} clicked
	 * @param {object} state
	 */
	update_boulder: function(clicked, state)
	{
		var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
		var n = this.et2.getWidgetById('nm[boulder_n]').get_value();
		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();
		var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
		var route_order = this.et2.getWidgetById('nm[route]').get_value();

		if (PerId && n)
		{
			var bonus = this.et2.getWidgetById('zone').get_value();
			var top = this.et2.getWidgetById('top').get_value();

			if (typeof window.protocol != 'undefined')
			{
				window.protocol.record({
					WetId: WetId,
					GrpId: GrpId,
					route: route_order,
					PerId: PerId,
					boulder: n,
					try: this.try_num(),
					bonus: bonus,
					top: top,
					clicked: clicked,
					state: state
				});
			}
			else	// old direct transmission, if no protocol object available
			{
				var update = {};
				update['try'+n] = this.try_num();
				update['zone'+n] = bonus === '' ? 'empty' : bonus;	// egw_json_encode sends '' as null, which get not stored!
				update['top'+n] = top ? top : 0;	// required, as backend doesn't store zones with empty top!

				this.egw.json('ranking_boulder_measurement::ajax_update_result',
					[PerId, update, n, {'WetId': WetId, 'GrpId': GrpId, 'route_order': route_order}]).sendRequest();
			}
		}
	},

	/**
	 * Boulder or athlete changed
	 *
	 * @param selectbox
	 */
	boulder_changed: function(selectbox)
	{
		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();
		var n = this.et2.getWidgetById('nm[boulder_n]').get_value();
		var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
		var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
		var route_order = this.et2.getWidgetById('nm[route]').get_value();
		var keys = {WetId: WetId, GrpId: GrpId, route_order: route_order};

		// reset values
		this.et2.getWidgetById('zone').set_value('');
		this.et2.getWidgetById('top').set_value('');
		this.try_num(0);

		// try restore values from local protocol, in case we have lost contact to server
		if (PerId && n && window.protocol)
		{
			var local =  window.protocol.get(jQuery.extend({PerId: PerId, boulder: n, route: route_order}, keys));
			if (local)
			{
				if (local.try) this.try_num(local.try);
				if (local.bonus) this.et2.getWidgetById('zone').set_value(local.bonus);
				if (local.top) this.et2.getWidgetById('top').set_value(local.top);
			}
		}
		this.init_boulder();
	},

	/**
	 * Go to next athlete
	 */
	boulder_next: function()
	{
		var PerId = this.et2.getWidgetById('nm[PerId]');
		var node = PerId.getDOMNode();

		node.selectedIndex = node.selectedIndex + 1;	// ++ works NOT reliable ...

		// need to call this manually, as changing selectedIndex does NOT trigger onchange
		this.boulder_changed(PerId);
	},

	/**
	 * Current order of athletes to be sorted: "order", "number" or "name"
	 */
	sorted_by: 'startorder',

	/**
	 * Sort options of athlete selectbox
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	sort_athletes: function(_event, _widget, _node)
	{
		if (_event)
		{
			switch(this.sorted_by)
			{
				case 'startorder':
					this.sorted_by = 'startnumber'; break;
				case 'startnumber':
					this.sorted_by = 'name'; break;
				default:
					this.sorted_by = 'startorder'; break;
			}
		}
		var select = this.et2.getWidgetById('nm[PerId]');
		var options = select.options.select_options;
		var by = this.sorted_by;
		var regexp = /(\d+) \((\d+)\) (.*)/;
		options.sort(function(_a, _b)
		{
			var a = regexp.exec(_a.label);
			var b = regexp.exec(_b.label);
			switch(by)
			{
				case 'name':
					return a[3].localeCompare(b[3]);
				case 'startorder':
					return a[1]-b[1];
				case 'startnumber':
					return a[2]-b[2];
			}
		});
		select.set_select_options(options);

		if (_event)
		{
			this.egw.message(this.egw.lang('Sorted athletes by %1', this.egw.lang(this.sorted_by)));
		}
	},

	/**************************************************************************
	 * Selfscore boulder measurement
	 **************************************************************************/

	/**
	 * Update selfscore scorecard
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_button} _widget
	 */
	update_scorecard: function(_event, _widget)
	{
		var values = _widget.getInstanceManager().getValues(this.et2);

		this.egw.json('ranking_selfscore_measurement::ajax_update_result',
			[values.nm.PerId, values.score,
			{'WetId': values.comp.WetId, 'GrpId': values.nm.cat, 'route_order': values.nm.route}],
				function(_msg)
				{
					this.message(_msg);
					this.resultlist.update();
				},
				null, false, this).sendRequest();
	},

	/**
	 * Boulder or athlete changed
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_selectbox} _widget
	 */
	scorecard_changed: function(_event, _widget)
	{
		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();

		if (PerId)
		{
			var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
			var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
			var route_order = this.et2.getWidgetById('nm[route]').get_value();

			this.egw.json('ranking_selfscore_measurement::ajax_load_athlete',
				[PerId, {'WetId': WetId, 'GrpId': GrpId, 'route_order': route_order}],
				function(_data)
				{
					this.message(_data.msg);
					delete _data.msg;

					var readonly = !_data.update_allowed;
					delete _data.update_allowed;

					this.et2.getWidgetById('score').set_value(_data);

					// necessary to hide scorecard bonus/top/flash checkboxes and add behavior
					this.init_boulder();

					// enable/disable checkboxes and apply button depending on update_allowed
					this.et2.getWidgetById('score').iterateOver(function(_widget)
					{
						_widget.set_readonly(readonly);
					}, this, et2_checkbox);
					this.et2.getWidgetById('button[apply]').set_disabled(readonly);
				},
				null, false, this).sendRequest();
		}
		else
		{
			this.message('');
			this.et2.getWidgetById('score').set_value({content: {}});
		}
	},

	/**************************************************************************
	 * LEAD measurement
	 **************************************************************************/

	TOP_HEIGHT: 999,	// everything >= this.TOP_HEIGHT means top (server defines it as 99999999 for DB!)
	TOP_PLUS: 9999,		// option-value of 'Top' in plus selectbox

	/**
	 * Change athlete, onchange for ahtlete selection
	 *
	 * @param {DOMNode} _node
	 * @param {et2_select} _widget
	 */
	change_athlete: function(_node, _widget)
	{
		if (_widget.get_value())
		{
			var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
			var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
			var route_order = this.et2.getWidgetById('nm[route]').get_value();

			this.unmark_holds();
			this.egw.json('ranking_measurement::ajax_load_athlete', [ _widget.get_value(),
				[],
				{ WetId: WetId, GrpId: GrpId, route_order: route_order }],
				function(_data)
				{
					var holds = this.getHoldsByHeight(_data.result_plus == this.TOP_PLUS ? this.TOP_HEIGHT :
						(_data.result_height ? _data.result_height : 1));
					if (holds.length) holds[0].scrollIntoView(false);
					if (_data.result_plus == this.TOP_PLUS || _data.result_height) this.mark_holds(holds);

					this.et2.getWidgetById('result_height').set_value(_data.result_height);
					this.et2.getWidgetById('result_plus').set_value(_data.result_plus);
					this.message(_data.athlete);
				},
				null, false, this).sendRequest();
		}
		else
		{
			this.et2.getWidgetById('result_height').set_value('');
			this.et2.getWidgetById('result_plus').set_value('');
			this.et2.getWidgetById('result_time').set_value('');
		}
	},

	/**
	 * Go to next athlete
	 */
	lead_next: function()
	{
		var PerId = this.et2.getWidgetById('nm[PerId]');
		var node = PerId.getDOMNode();

		node.selectedIndex = node.selectedIndex + 1;	// ++ works NOT reliable ...

		this.change_athlete(node, PerId);
	},

	/**
	 * Update athlete, send new result to server
	 *
	 * @param {boolean|number} scroll_mark =true or number to add to current height eg. 3 to scroll 3rd heigher hold into view
	 */
	update_athlete: function(scroll_mark)
	{
		var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
		var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
		var route_order = this.et2.getWidgetById('nm[route]').get_value();
		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();

		if (PerId)
		{
			var height = this.et2.getWidgetById('result_height');
			var plus   = this.et2.getWidgetById('result_plus');
			var time   = this.et2.getWidgetById('result_time');
			// top?
			if (plus.get_value() == this.TOP_PLUS || height.get_value() >= this.TOP_HEIGHT)
			{
				height.set_value('');
				plus.set_value(this.TOP_PLUS);
			}
			if (typeof scroll_mark == 'undefined' || scroll_mark)
			{
				var holds = this.getHoldsByHeight(plus.get_value() == this.TOP_PLUS ? this.TOP_HEIGHT :
					(typeof scroll_mark == 'number' ? scroll_mark : 0)+parseInt(height.get_value()));
				if (holds.length)
				{
					holds.scrollIntoView(typeof scroll_mark == 'number');
					if (typeof scroll_mark != 'number') this.mark_holds(holds);
				}
			}
			this.egw.json('ranking_measurement::ajax_update_result',
				[PerId, { result_height: height.get_value(), result_plus: plus.get_value(), result_time: time.get_value()}, 1,
				{WetId: WetId, GrpId: GrpId, route_order: route_order}]).sendRequest();
		}
	},

	/**
	 * Mark holds: red and bold
	 *
	 * @param holds
	 */
	mark_holds: function(holds)
	{
		jQuery(holds).css({'color':'red','font-weight':'bold'});
	},

	/**
	 * Unmark holds: black and normal
	 *
	 * @param holds holds to mark, default all
	 */
	unmark_holds: function(holds)
	{
		if (typeof holds == 'undefined') holds = jQuery('div.topoHandhold');

		jQuery(holds).css({'color':'black', 'font-weight':'normal'});
	},

	/**
	 * Load topo
	 *
	 * @param {DOMNode} _node
	 * @param {et2_selct} _widget
	 */
	load_topo: function(_node, _widget)
	{
		var topo = document.getElementById('ranking-result-index_topo');
		var path = _widget.get_value();

		this.remove_handholds();

		topo.src = window.egw_webserverUrl+(path ? '/webdav.php'+path : '/phpgwapi/templates/default/images/transparent.png');

		if (path) this.egw.json('ranking_measurement::ajax_load_topo', [path]).sendRequest();
	},

	/**
	 * Handler for a click on the topo image
	 *
	 * @param {jQuery.event} e
	 */
	topo_clicked: function(e)
	{
		//console.log(e);

		var topo = e.target;
		//console.log(topo);

		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();

		if (!PerId)
		{
			// FF 15 has offsetX/Y values in e.orginalEvent.layerX/Y (Chrome&IE use offsetX/Y)
			var x = e.offsetX ? e.offsetX : e.originalEvent.layerX;
			var y = e.offsetY ? e.offsetY : e.originalEvent.layerY;
			//this.egw.message('topo_clicked() x='+x+'/'+topo.width+', y='+y+'/'+topo.height);
			//this.add_handhold({'xpercent':100.0*x/topo.width, 'ypercent': 100.0*y/topo.height, 'height': 'Test'});
			this.egw.json('ranking_measurement::ajax_save_hold',
				[{xpercent: 100.0*x/topo.width, ypercent: 100.0*y/topo.height}]).sendRequest();
		}
		else
		{
			// measurement mode
		}
	},

	active_hold: undefined,

	/**
	 * Handler for a click on a hold
	 *
	 * @param {jQuery.event} e
	 */
	hold_clicked: function(e)
	{
		this.active_hold = e.target;	// hold container
		this.active_hold = jQuery(this.active_hold.nodeName != 'DIV' ? this.active_hold.parentNode : this.active_hold);	// img or span clicked, not container div itself
		//console.log(this.active_hold);
		//console.log(this.active_hold.data('hold'));

		var PerId = this.et2.getWidgetById('nm[PerId]').get_value();

		if (!PerId)	// edit topo mode
		{
			var popup = this.et2.getWidgetById('hold_popup').getDOMNode();
			var height = this.et2.getWidgetById('hold_height');
			var top = this.et2.getWidgetById('hold_top');

			popup.style.display = 'block';
			var is_top = this.active_hold.data('hold').height == this.TOP_HEIGHT;
			height.set_readonly(is_top);
			top.set_value(is_top);
			if (!is_top) height.set_value(this.active_hold.data('hold').height);
		}
		else	// measuring an athlete
		{
			this.et2.getWidgetById('result_height').set_value(this.active_hold.data('hold').height);
			this.et2.getWidgetById('result_plus').set_value('0');

			this.mark_holds(this.active_hold);
			this.update_athlete(3);	// 3 = scroll 3 holds heigher into view (false = no automatic scroll)
		}
	},

	/**
	 * Top checkbox in hold popup changed
	 *
	 * @param {jQuery.event} _e
	 * @param {et2_checkbox} _widget
	 */
	hold_top_changed: function(_e, _widget)
	{
		var height = this.et2.getWidgetById('hold_height');
		var checked = _widget.get_value() === 'true';
		if (checked) height.set_value('');
		height.set_readonly(checked);
		if (!checked) height.getDOMNode().focus();
	},

	/**
	 * Close hold popup and unset this.active_hold
	 */
	hold_popup_close: function()
	{
		this.et2.getWidgetById('hold_popup').getDOMNode().style.display='none';
		this.active_hold = null;
	},

	/**
	 * Submit hold popup (and close it)
	 *
	 * @param {jQuery.event} _e
	 * @param {et2_button} _button
	 * @param {DOMNode} _node
	 */
	hold_popup_submit: function(_e, _button, _node)
	{
		var json;
		switch (_button.id)
		{
			case 'button[renumber]':
			case 'button[save]':
				this.active_hold.data('hold').height = this.et2.getWidgetById('hold_top').get_value() ?
					this.TOP_HEIGHT : this.et2.getWidgetById('hold_height').get_value();
				json = this.egw.json(_button.id == 'button[save]' ?
					'ranking_measurement::ajax_save_hold' : 'ranking_measurement::ajax_renumber_holds',
					[this.active_hold.data('hold')]);
				break;
			case 'button[delete]':
				json = this.egw.json('ranking_measurement::ajax_delete_hold', [this.active_hold.data('hold').hold_id]);
				break;
		}
		this.active_hold.remove();
		this.hold_popup_close();
		if (json) json.sendRequest();
	},

	/**
	 * Add or update a single handhold
	 *
	 * @param {object} hold
	 */
	add_handhold: function(hold)
	{
		//console.log('add_handhold({xpercent: '+hold.xpercent+', ypercent: '+hold.ypercent+', height: '+hold.height+'})');
		// as container has a fixed height with overflow: auto, we have to scale ypercent to it
		var y_ratio = jQuery('#ranking-result-index_topo').height() / jQuery('div.topoContainer').height();
		//console.log('#topo.height='+jQuery('#ranking-result-index_topo').height()+' / div.topoContainer.height='+jQuery('div.topoContainer').height()+' = '+y_ratio);
		var container = jQuery('#hold_id_'+hold.hold_id);
		if (!container.length)
		{
			var container_div = document.createElement('div');
			container_div.className = 'topoHandhold';
			container_div.style.left = hold.xpercent+'%';
			container_div.style.top = (y_ratio*hold.ypercent)+'%';
			container = jQuery(container_div);
			container.attr('id', 'hold_id_'+hold.hold_id);

			jQuery(document.createElement('img'))
				.appendTo(container)
				.attr('src', window.egw_webserverUrl+'/ranking/templates/default/images/griff32.png');
			jQuery(document.createElement('span'))
				.appendTo(container);
			container.click(jQuery.proxy(this.hold_clicked, this));

			jQuery('div.topoContainer').append(container);
		}
		container.attr('title', hold.height >= this.TOP_HEIGHT ? 'Top' : hold.height);
		container.find('span').text(hold.height >= this.TOP_HEIGHT ? 'Top' : hold.height);
		container.data('hold', hold);
	},

	/**
	 * Display an array of handholds
	 *
	 * @param {array} holds
	 */
	show_handholds: function(holds)
	{
		for(var i = 0; i < holds.length; ++i)
		{
			this.add_handhold(holds[i]);
		}
	},

	remove_handholds: function()
	{
		jQuery('div.topoContainer div').remove();
	},

	/**
	 * Recalculate handhold position, eg. when window get's resized or topo image is loaded
	 *
	 * Required because topo image is scaled to width:100% AND displayed in a container div with fixed height and overflow:auto
	 *
	 * @param {boolean} resizeContainer =true
	 */
	recalc_handhold_positions: function(resizeContainer)
	{
		var topo_container = jQuery('div.topoContainer');
		if (!topo_container.length) return;
		topo_container.parent().css({overflow:'hidden'});
		var y_ratio = 1.0;
		// resize topoContainer to full page height
		if (typeof resizeContainer == 'undefined' || resizeContainer)
		{
			var topo_pos = topo_container.offset();
			jQuery('div.topoContainer').height(jQuery(window).height()-topo_pos.top-jQuery('#divGenTime').height()-jQuery('#divPoweredBy').height()-20);
			y_ratio = jQuery('#ranking-result-index_topo').height() / jQuery('div.topoContainer').height();
			//console.log('recalc_handhold_positions() $(#topo).height()='+jQuery('#ranking-result-index_topo').height()+', $(div.topoContainer).height()='+jQuery('div.topoContainer').height()+' --> y_ratio='+y_ratio);
		}
		jQuery('div.topoHandhold').each(function(index,container){
			container.style.top = (y_ratio*jQuery(container).data('hold').ypercent)+'%';
		});
	},

	/**
	 * Transform topo for printing and call print
	 */
	print_topo: function()
	{
		jQuery('div.topoContainer').width('18cm');	// % placed handholds do NOT work with % with on print!
		jQuery('div.topoContainer').css('height','auto');

		jQuery('div.topoContainer').css('visible');

		this.recalc_handhold_positions(false);

		window.focus();
		window.print();
	},

	/**
	 * Get holds with a given height
	 *
	 * @param {number} height
	 * @returns array
	 */
	getHoldsByHeight: function(height)
	{
		height = parseFloat(height);
		//console.log('getHoldsByHeight('+height+')');
		return jQuery('div.topoHandhold').filter(function() {
			return jQuery(this).data('hold').height == height;
		});
	},

	/**
	 * Init topo stuff, get's call on document.ready via $GLOBALS['egw_info']['flags']['java_script']
	 *
	 * @param {array} holds
	 */
	init_topo: function(holds)
	{
		jQuery(window).resize(jQuery.proxy(this.recalc_handhold_positions, this));
		jQuery('#ranking-result-index_topo').load(jQuery.proxy(this.recalc_handhold_positions, this));
		jQuery('#ranking-result-index_topo').click(jQuery.proxy(this.topo_clicked, this));
		if (holds && holds.length) this.show_handholds(holds);
		jQuery('#ranking-result-index').css('overflow-y', 'hidden');	// otherwise we get a permanent scrollbar
		if (egwIsMobile()){
			this._scalingHandler(jQuery('#ranking-result-index_topo').parent());
			jQuery(window).on("orientationchange",function(){
				jQuery(window).trigger('resize');
			});
		}
		// mark current athlets height
		var result_height = this.et2.getWidgetById('result_height');
		var height = parseFloat(result_height.get_value ? result_height.get_value() : result_height.value);
		var plus = this.et2.getWidgetById('result_plus').get_value();
		var current = this.getHoldsByHeight(plus == this.TOP_PLUS ? this.TOP_HEIGHT : (height ? height : 1));
		if (current.length) {
			current[0].scrollIntoView(false);
			if (height || plus == this.TOP_PLUS) this.mark_holds(current);
		}
	},

	/**
	 * Function to scale up/down a given element
	 *
	 * Scaling happens based on pinch In/Out touch actions.
	 *
	 * @param {jQuery object} _node dom node to scale up
	 */
	_scalingHandler: function(_node)
	{
		// scale xy used for transforming
		var Sxy = 1;

		// node to scale up/down
		var node = _node;

		// scaling threshold
		var scaleThreshold = 0.10;

		node.css({"transform-origin":"0 0"});

		/**
		 * Method to bind pinch handler for android devicces
		 * @param {type} _node node
		 */
		var android_scale = function (_node)
		{
			_node.swipe({
				fingers:2,
				pinchThreshold:0,
		        preventDefaultEvents:true,
				pinchStatus: function (event, phase, direction, distance , duration , fingerCount, pinchZoom, fingerData)
				{
					var zoom = (parseFloat(pinchZoom) - Math.floor(parseFloat(pinchZoom))) * scaleThreshold;
					if (fingerCount == 2)
					{
						scale(zoom, direction);
					}
				}
			});
		};

		/**
		 * Method to bind gusture (pinch) handlers for iOS devices
		 *
		 * @description Bind gesture handlers used for iOS devices
		 * and prevents iOS default gusture handlers which
		 * conflicts with our functionalities.
		 *
		 * @param {type} _node
		 * @returns {undefined}
		 */
		var iOS_scale = function (_node)
		{
			_node.on({
				gesturechange:function(e){
					e.preventDefault();
					scale(e.originalEvent.scale * scaleThreshold,Sxy < e.originalEvent.scale?"in":"out");
				},
				gestureend: function(e){
					e.preventDefault();
				},
				gesturestart: function (e){
					e.preventDefault();
				}
			});
		};

		/**
		 * Scale in/out the node base on scale value
		 *
		 * @param {float} _scale scale number
		 * @param {type} _direction direction to scale in/out
		 */
		var scale = function(_scale, _direction) {
			var zoom = _scale;
			var transform = "";
			switch (_direction)
			{
				case 'out':
					if (Sxy >1)
					{
						Sxy -= zoom;
						if (Sxy <1) Sxy = 1;
					}
					break;
				case 'in':
					Sxy += zoom;
				default:
					break;
			}
			transform = Sxy ==1? "":"scale("+Sxy+","+Sxy+")";
			node.css ( {
				'-webkit-transform': transform,
				'transform':transform
			});
			node.parent().css({
				overflow:'hidden'
			});
			window.setTimeout(function()
			{
				node.parent().css({overflow:'auto'});
			}, 1);
			jQuery(window).trigger('resize');
		};

		// initialize gesture (pinch) handling base on devices
		if (egwIsMobile() && framework.getUserAgent() != 'iOS')
		{
			android_scale (node);
		}
		else if (egwIsMobile())
		{
			iOS_scale (node);
		}
	},

	/**
	 * Triggers vibrate
	 */
	_button_vibrate: function()
	{
		if (egwIsMobile()) framework.vibrate(50);
	},

	/**
	 * Find a row / array element which has a certain attribute value plus id of previous and next row
	 *
	 * @param {array} _rows
	 * @param {mixed} _id value of attribute _row_id
	 * @param {string} _row_id name of attribute, default "PerId"
	 * @return {object} values for attributes:
	 *	- data searched row
	 *	- prev id of previous row
	 *	- next id of next row
	 */
	getRowById: function(_rows, _id, _row_id)
	{
		if (typeof _row_id == 'undefined') _row_id='PerId';

		var ret = {};
		for (var row in _rows)
		{
			if (ret.data && _rows[row])
			{
				ret.next = _rows[row][_row_id];
				break;
			}
			if (_rows[row] && _rows[row][_row_id] == _id)
			{
				ret.data = _rows[row];
			}
			if (typeof ret.data == 'undefined' && _rows[row])
			{
				ret.prev = _rows[row][_row_id];
			}
		}
		return ret;
	},

	/**
	 * Edit double-clicked speed pairing cell
	 *
	 * @param {jQuery.event} event
	 */
	edit_pairing: function(event)
	{
		var id = jQuery(event.currentTarget).find('input[type=hidden]').val();
		var parts = id ? id.split(/:/) : [];	// WetId:GrpId:route_order:PerId
		var content = this.et2.getArrayMgr('content');
		var route = parseInt(parts[2]);
		if (id.length < 4 || !route || !parts[3] ||
			!content || !content.getEntry('nm[is_judge]') ||
			content.getEntry('nm[result_official]'))
		{
			return;
		}
		// speed finals with less then 16 participants renumber heats in content
		// to use same template hiding first columns!
		var rows = content.getEntry('nm[rows]');
		var heat = route;
		while (heat < 6 && (typeof rows['heat'+heat] == 'undefined' ||
			// in case of a wildcard rows['heat'+heat][1] might not exist!
			rows['heat'+heat][typeof rows['heat'+heat][1] == 'undefined' ? 2 : 1].route_order < route))
		{
			heat++;
		}
		var next = content.getEntry('nm[rows][heat'+(heat+1)+']');
		this.action_edit({}, [{id: parts[3]}], 'heat'+heat,
			next && next[1].result_rank);
	},

	/**
	 * Function to create edit dialog for athlete from the result list
	 *
	 * @param {object} _action egw action object
	 * @param {object} _selected selected action from the grid
	 * @param {string} _heat additional index into nm.rows or undefined
	 * @param {bool}   _ro do NOT allow updates
	 */
	action_edit: function (_action, _selected, _heat, _ro)
	{
		var PerId = _selected[0].id;
		var template = 'ranking.result.index.rows_boulder.edit';
		var content = this.et2.getArrayMgr('content');
		var sel_options = this.et2.getArrayMgr('sel_options');
		var self = this;
		if (content) var nm = content.getEntry('nm');

		if (nm)
		{
			var template = nm.template+'.edit';
			if (typeof etemplate2.templates[template] == 'undefined') return;	// no edit template found
			var row = this.getRowById(_heat ? nm.rows[_heat] : nm.rows, PerId);
			var entry = jQuery.extend(row.data, {nm: nm});

			// remove not existing boulders, to not show autorepeated rows
			for(var i=parseInt(nm.num_problems)+1; i <= 10; ++i)
			{
				delete entry['boulder'+i];
			}
		}
		var buttons = [];
		var width = 480;
		if (!nm.result_official && !_ro)
		{
			buttons.push({button_id: 'update', text: this.egw.lang('Update'), id: 'update', image: 'apply', default: true, disabled: !!entry.checked});

			if (nm.is_judge && nm.template == 'ranking.result.index.rows_boulder')
			{
				if (entry.checked)
				{
					buttons.push({button_id: 'uncheck', text: this.egw.lang('Uncheck'), id: 'uncheck', image: 'bullet'});
				}
				else
				{
					buttons.push({button_id: 'checked', text: this.egw.lang('Checked'), id: 'checked', image: 'check', "default":true});
				}
				width = 575;
			}
		}
		buttons.push({button_id: 'previous', text: this.egw.lang('Back'), id: 'previous', image: 'back', disabled: !row.prev});
		buttons.push({button_id: 'next', text: this.egw.lang('Next'), id: 'next', image: 'continue', disabled: !row.next});

		buttons.push({button_id: 'close', text: this.egw.lang('Close'), id: 'close', image: 'cancel', click: function() {
			jQuery(this).dialog("close");
		}});

		var dialog = et2_createWidget("dialog",
		{
			id: 'update-result',
			callback: function(_button, _values)
			{
				switch(_button)
				{
					case 'update':
					case 'checked':
					case 'uncheck':
						self.update_result_row.call(self, _button, entry, _values, function(_data)
						{
							// reopen dialog with changed content
							self.action_edit({}, [{id: PerId}], _heat, _ro);
							// ToDo: update dialog with returned values and not reopen it
							//var row = this.getRowById(_data.content, PerId);
							//dialog.options.value = jQuery.extend(dialog.options.value, row.data);
							//dialog.set_template(template);
						}, self);
						// We need to set nextmatch with this context to be able to
						// call sortBy after update happens
						self._set_nextmacth_sort_headers();
						//return false;
						break;
					case 'next':
						self.action_edit({}, [{id: row.next}], _heat, _ro);
						break;
					case 'previous':
						self.action_edit({}, [{id: row.prev}], _heat, _ro);
						break;
					default:
						alert('Not yet ;-)');
				}
			},
			title: this.egw.lang('Update Result'),
			buttons: buttons,
			value: {
				content: entry || {},
				sel_options: sel_options.data || {},
				readonlys: entry.checked || nm.result_official || _ro ? { __ALL__: true} : {}
			},
			template: template,
			class: "update_result",
			minWidth: width,
			width: width
		});
	},

	/**
	 * Update result of current row
	 *
	 * Not yet used as grid stuff is not commited and eT2 serverside does not validate partial submits correct.
	 *
	 * @param {String} _button "update" or "checked"
	 * @param {object} _entry changed entry, incl. old values
	 * @param {object} _values new values
	 * @param {function} _callback
	 * @param {object} _context
	 */
	update_result_row: function(_button, _entry, _values, _callback, _context)
	{
		if (_button != 'update')
		{
			_values.checked = _button == "checked";
		}
		this.egw.json('ranking.ranking_result_ui.ajax_update', [{
			WetId: _entry.WetId,
			GrpId: _entry.GrpId,
			route_order: _entry.route_order
		}, _entry.PerId, _values, _button != 'update'], function(_data)
		{
			this.message(_data.msg);
			delete _data.msg;

			var nm = this.et2.getWidgetById('nm[rows]');
			_data.sel_options = this.et2.getArrayMgr('sel_options').data;
			if (nm) nm.set_value(_data);

			// update content for next click
			this.et2.getArrayMgr('content').data.nm.rows = _data.content;

			if (_callback) _callback.call(_context || this, _data);
		}, null, false, this).sendRequest();
	},

	/**
	 * Eliminated changed
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	eliminated_changed: function(_ev, _widget, _node)
	{
		if (_widget.get_value())
		{
			var time = _widget.getParent().getWidgetById(_widget.id.replace(/eliminated/, 'result_time'));
			if (time) time.set_value('');
		}
	},

	/**
	 * Eliminated changed
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	time_changed: function(_ev, _widget, _node)
	{
		if (_widget.get_value())
		{
			var eliminated = _widget.getParent().getWidgetById(_widget.id.replace(/result_time/, 'eliminated'));
			if (eliminated) eliminated.set_value('');
		}
	},

	/***************************************************************************
	 * Registration
	 **************************************************************************/

	ACL_EDIT: 4,
	ACL_REGISTER: 64,
	ACL_JUDGE: 512,
	ACL_REPLACE: 8192,

	_comp_rights: {},

	/**
	 * Set or query competition (edit-)rights
	 *
	 * @param {int} _comp
	 * @param {int} _mask mask for check
	 * @param {int} _rights to set, or undefined for check
	 * @returns {boolean}
	 */
	competition_rights: function(_comp, _mask, _rights)
	{
		_comp = parseInt(_comp);
		if (!_comp) return false;

		if (typeof _rights != 'undefined') this._comp_rights[_comp] = _rights;

		return !!(this._comp_rights[_comp] & _mask);
	},

	register_dialog: undefined,
	register_nm: undefined,

	/**
	 * Registration dialog
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	register: function(_ev, _widget, _node)
	{
		app.ranking._register.call(app.ranking, _ev, _widget, _node);
	},
	_register: function(_ev, _widget, _node, _replace)
	{
		var nm = this.register_nm = _widget.getRoot().getWidgetById('nm');
		var filters = nm.getValue();

		if (!filters.comp)
		{
			et2_dialog.alert(this.egw.lang('You need to select a competition first!', this.egw.lang('Registration')));
			return;
		}
		if (!(this.competition_rights(filters.comp, this.ACL_REGISTER) ||
			_replace && this.competition_rights(filters.comp, this.ACL_REPLACE)))
		{
			et2_dialog.alert(this.egw.lang('Missing registration rights!', this.egw.lang('Registration')));
			return;
		}
		var cats = _widget.getRoot().getArrayMgr('sel_options').getEntry('GrpId');
		var replace = _replace;

		var callback = jQuery.proxy(function(button_id, value, confirmed)
		{
			var athletes = value.PerId;
			if (!athletes || !athletes.length)
			{
				et2_dialog.alert(this.egw.lang('You need to select one or more athletes first!', this.egw.lang('Registration')));
				return false;
			}
			var reason = this.register_dialog.template.widgetContainer.getWidgetById('prequal_reason');
			if (button_id == 'prequalify' && reason.disabled)
			{
				reason.set_disabled(false);
				return false;
			}
			else
			{
				reason.set_disabled(true);
			}
			var filter = this.register_nm.getValue();
			this.egw.json('ranking.ranking_registration_ui.ajax_register', [{
				WetId: replace ? replace.WetId : filter.comp,
				GrpId: replace ? replace.GrpId : value.GrpId,
				PerId: athletes,
				mode: button_id,
				reason: value.prequal_reason,
				confirmed: confirmed,
				replace: replace ? replace.PerId : undefined
			}], this.register_callback, null, false, this).sendRequest();

			// keep dialog open by returning false
			return false;
		}, this);

		var buttons = [{text: this.egw.lang("Register"),id: "register", image: 'check', class: "ui-priority-primary", default: true}];
		if (this.competition_rights(filters.comp, this.ACL_EDIT))
		{
			buttons.push({text: this.egw.lang("Prequalify"), id: 'prequalify', image: 'bullet'});
		}
		if (_replace)
		{
			buttons = [{text: this.egw.lang("Replace"), id: "replace", image: 'check', class: "ui-priority-primary", default: true}];
		}
		buttons.push({text: this.egw.lang("Close"), id: "close", click: function() {
			// If you override, 'this' will be the dialog DOMNode.
			// Things get more complicated.
			// Do what you like, but don't forget this line:
			jQuery(this).dialog("close");
		}});

		var dialog = this.register_dialog = et2_createWidget("dialog", {
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: callback,
			buttons: buttons,
			title: _replace ? this.egw.lang('Replace')+' '+_replace.nachname+', '+_replace.vorname+' ('+_replace.nation+')':
				this.egw.lang('Register athlets for this competition'),
			template: "ranking.registration.add",
			value: {
				content: {
					GrpId: _replace ? _replace.GrpId : (filters.col_filter.GrpId || cats[0].value)
				},
				sel_options: {
					GrpId: cats
				},
				readonlys: {
					GrpId: typeof _replace !== 'undefined'
				}
			},
			width: '480px'
		});
		dialog.template.widgetContainer.getWidgetById('PerId').set_autocomplete_params({
			GrpId: _replace ? _replace.GrpId : (filters.col_filter.GrpId || cats[0].value),
			nation: _replace ? _replace.nation : filters.nation,
			sex: _replace ? _replace.sex : filters.col_filter.sex
		});
		if (_replace) dialog.template.widgetContainer.getWidgetById('PerId').set_multiple(false);
	},

	/**
	 * Handle server response to registration request from either this.register or this.register_action
	 *
	 * @param {object} _data
	 */
	register_callback: function(_data)
	{
		var taglist = !this.register_dialog ? null : this.register_dialog.template.widgetContainer.getWidgetById('PerId');
		var athletes = taglist ? taglist.getValue() : [];

		if (_data.registered && taglist)
		{
			for (var i=0; i < _data.registered; i++) athletes.shift();
			taglist.set_value(athletes);
		}
		if (_data.question)
		{
			et2_dialog.show_dialog(jQuery.proxy(function(_button)
			{
				if (_button == et2_dialog.NO_BUTTON)
				{
					if (taglist)
					{
						athletes.shift();
						taglist.set_value(athletes);
					}
				}
				else
				{
					// resend request with confirmation to server
					this.egw.json('ranking.ranking_registration_ui.ajax_register', [{
						WetId: _data.WetId,
						GrpId: _data.GrpId,
						PerId: _data.PerId,
						mode: _data.mode,
						replace: _data.replace,
						confirmed: true
					}], this.register_callback, null, false, this).sendRequest();
				}
			}, this), _data.question, _data.athlete, null, et2_dialog.BUTTON_YES_NO,
			et2_dialog.QUESTION_MESSAGE, undefined, this.egw);
		}
		// if we replaced, close the dialog
		else if (_data.replace && this.register_dialog)
		{
			this.register_dialog.destroy();
		}
	},

	/**
	 * Setter for register_allow_prequalify
	 *
	 * @param {boolan} _allow
	 */
	allow_prequalify: function(_allow)
	{
		this.register_allow_prequalify = _allow;
	},

	/**
	 * Registration dialog: category changed
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	register_cat_changed: function(_ev, _widget, _node)
	{
		var taglist = this.register_dialog.template.widgetContainer.getWidgetById('PerId');
		var filters = this.register_nm.getValue();

		taglist.set_autocomplete_params({
			GrpId: _widget.get_value(),
			nation: filters.nation,
			sex: filters.col_filter.sex
		});
	},

	/**
	 * Replace a registered athlete with an other one the user has to choose
	 *
	 * @param {egw_action} _action id: "replace"
	 * @param {array} _selected eg. ["ranking::1638:5:1772"]
	 */
	replace_action: function(_action, _selected)
	{
		var data = this.egw.dataGetUIDdata(_selected[0].id);

		this._register(null, this.et2, null, data.data);

		/*this.egw.json('ranking.ranking_registration_ui.ajax_register', [{
			WetId: data.data.WetId,
			GrpId: data.data.GrpId,
			PerId: data.data.PerId,
			mode: _action.id
		}], this.register_callback, null, false, this).sendRequest();*/
	},

	/**
	 * Execute action on registration list
	 *
	 * @param {egw_action} _action id: "delete" or "confirm"
	 * @param {array} _selected eg. ["ranking::1638:5:1772"]
	 */
	register_action: function(_action, _selected)
	{
		var data = this.egw.dataGetUIDdata(_selected[0].id);

		this.egw.json('ranking.ranking_registration_ui.ajax_register', [{
			WetId: data.data.WetId,
			GrpId: data.data.GrpId,
			PerId: data.data.PerId,
			mode: _action.id
		}], this.register_callback, null, false, this).sendRequest();
	},

	/**
	 * Registration mail
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	register_mail: function(_ev, _widget, _node)
	{
		app.ranking._register_mail.call(app.ranking, _ev, _widget, _node);
	},
	_register_mail: function(_ev, _widget, _node)
	{
		var nm = _widget.getRoot().getWidgetById('nm');
		var filters = nm.getValue();

		if (!this.competition_rights(filters.comp, this.ACL_EDIT|this.ACL_JUDGE))
		{
			et2_dialog.alert(this.egw.lang('Permission denied!', this.egw.lang('Mail')));
			return;
		}
		if (!filters.comp)
		{
			et2_dialog.alert(this.egw.lang('You need to select a competition first!', this.egw.lang('Registration')));
			return;
		}
		var cats = _widget.getParent().getArrayMgr('sel_options').getEntry('GrpId') ||
			_widget.getRoot().getArrayMgr('sel_options').getEntry('GrpId');

		var callback = jQuery.proxy(function(button_id, value, confirmed)
		{
			var athletes = value.PerId;
			if (!athletes || !athletes.length)
			{
				et2_dialog.alert(this.egw.lang('You need to select one or more athletes first!', this.egw.lang('Registration')));
				return;
			}
			var filter = this.register_nm.getValue();

			// keep dialog open by returning false
			return false;
		}, this);

		var buttons = [
			{text: this.egw.lang("Selected participants"), id: "selected", image: 'check', class: "ui-priority-primary", default: true},
			{text: this.egw.lang("All participants"), id: "all", image: 'check'},
			{text: this.egw.lang("Participants without password"), id: "no_password", image: 'check'},
			{text: this.egw.lang("Participants without password or recent reminder"), id: "no_password", image: 'check'},
			{text: this.egw.lang("Close"), id: "close", click: function() {
				// If you override, 'this' will be the dialog DOMNode.
				// Things get more complicated.
				// Do what you like, but don't forget this line:
				jQuery(this).dialog("close");
			}}
		];
		var selection = nm.getSelection();
		// if no selection, remove that options
		if (!selection.all && !selection.ids.length) {
			buttons.shift();
		}
		var dialog = et2_createWidget("dialog", {
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: jQuery.proxy(function(_button, _values)
			{
				this.egw.json('ranking.ranking_registration_ui.ajax_mail',
					[_values, _button, selection, filters], null, null, false, this).sendRequest();
			}, this),
			buttons: buttons,
			title: this.egw.lang('Mail participants'),
			template: "ranking.registration.mail",
			value: {
				content: this.et2.getArrayMgr('content').getEntry('mail')
			}
		});
	}
});
