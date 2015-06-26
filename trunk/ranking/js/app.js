/**
 * EGroupware digital ROCK Rankings & ResultService
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-15 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
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
				var self = this;
				this.et2.iterateOver(function(_widget){
					_widget.setNextmatch(self);
				}, window, et2_nextmatch_sortheader);
				var sort_widget = this.et2.getWidgetById(this.content.nm.order);
				if (sort_widget) sort_widget.setSortmode(this.content.nm.sort.toLowerCase());
				jQuery('#ranking-result-index').css('overflow-y', 'auto');	// init_topo switches to "hidden"
				if (this.content.nm.show_result == 4)	// measurement
				{
					switch(this.content.nm.discipline)
					{
						case 'boulder':
						case 'selfscore':
							this.init_boulder();
							break;

						case 'lead':
							this.init_topo(this.content.holds);
							break;
					}
				}

				break;
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
	 * Update result of current row
	 *
	 * Not yet used as grid stuff is not commited and eT2 serverside does not validate partial submits correct.
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_button|et2_checkbox} _widget
	 */
	update_result_row: function(_event, _widget)
	{
		if (!_widget) _widget = _event;	// click seems to only set button as 1. param
		var parent = _widget.getParent();
		// boulder "checked" checkbox
		if (_widget.instanceOf(et2_checkbox) && parent._children.length == 2)
		{
			// readonly apply button --> use ajax call to uncheck
			if (parent._children[1].options.readonly)
			{
				var content = _widget.getRoot().getArrayMgr('content').data;
				this.egw.json('ranking.ranking_result_ui.ajax_uncheck_result', [{
					WetId: content.WetId,
					GrpId: content.GrpId,
					route: content.route_order,
					PerId: _widget.id.replace(/^set\[([0-9]+)\]\[checked\]$/, '$1')
				}]).sendRequest();
			}
			else	// to check --> just click on apply
			{
				parent._children[1].click();
			}
			return;
		}
		while(parent && !parent.instanceOf(et2_grid))
		{
			parent = parent.getParent();
		}
		if (parent)
		{
			_widget.getInstanceManager().submit(null, false, false, parent.getRow(_widget));
		}
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
			return;
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
		var bonus = this.et2.getWidgetById(top.id.replace(/top/g,'zone'));
		var bonus_value = bonus ? bonus.get_value() : undefined;

		if (bonus && (!bonus_value || bonus_value == '0') && parseInt(top_value) > 0) bonus.set_value(bonus_value=top_value);

		if (bonus && parseInt(top_value) > 0 && parseInt(top_value) < parseInt(bonus_value))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Top < Bonus!');
				bonus.set_value(top_value);
			//}, 10);
		}
	},

	/**
	 * onChange of tops: if bonus not set, set it to the same number of tops
	 *  if tops > zones alert user and set tops to zones or
	 *  if less tries then tops alert user and set tries to tops
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} top select box
	 */
	check_tops: function(node, top)
	{
		var bonus = this.et2.getWidgetById(top.id.replace(/tops/g, 'zones'));

		if (bonus && !bonus.get_value() && parseInt(top.get_value()) > 0) bonus.set_value(top.get_value());

		if (bonus && parseInt(top.get_value()) > 0 && parseInt(top.get_value()) > parseInt(bonus.get_value()))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Top > Bonus!');
				bonus.set_value(top.get_value());
			//}, 10);
		}

		var tries = this.et2.getWidgetById(top.id.replace(/tops/g, 'top_tries'));

		if (tries && !tries.get_value() && parseInt(top.get_value()) > 0) tries.set_value(top.get_value());

		if (tries && parseInt(top.get_value()) > 0 && parseInt(top.get_value()) > parseInt(tries.get_value()))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Top > Tries!');
				tries.set_value(top.get_value());
			//}, 10);
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
		var top = this.et2.getWidgetById(bonus.id.replace(/zone/g,'top'));
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
	 * onChange of zones: dont allow to set a zones bigger then tops or no zones, but a top
	 * 	or if less tries then zones alert user and set tries to zones
	 *
	 * @param {DOMNode} node
	 * @param {DOMNode} bonus select box
	 */
	check_boni: function(node, bonus)
	{
		var top = this.et2.getWidgetById(bonus.id.replace(/zones/g,'tops'));

		if (top && parseInt(top.get_value()) > 0 && parseInt(bonus.get_value()) < parseInt(top.get_value()))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Bonus < Top!');
				top.set_value(bonus.get_value());
			//}, 10);
		}
		if (top && parseInt(top.get_value()) > 0 && !bonus.get_value())
		{
			top.set_value(bonus.get_value());
		}

		var tries = this.et2.getWidgetById(bonus.id.replace(/zones/g,'zone_tries'));

		if (tries && !tries.get_value() && parseInt(bonus.get_value()) > 0) tries.set_value(bonus.get_value());

		if (tries && parseInt(bonus.get_value()) > 0 && parseInt(bonus.get_value()) > parseInt(tries.get_value()))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Bonus > Tries!');
				tries.set_value(bonus.get_value());
			//}, 10);
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

		jQuery('#ranking-result-index_button\\[update\\],#ranking-result-index_button\\[try\\],#ranking-result-index_button\\[bonus\\],#ranking-result-index_button\\[top\\]')
			.attr('disabled', !PerId || !n);

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
		var state = this.get_state();
		var bonus = this.et2.getWidgetById('zone');
		bonus.set_value(this.try_num(1));
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
					bonus: bonus,
					top: top,
					clicked: clicked,
					try: clicked ? this.try_num() : null,
					state: state
				});
			}
			else	// old direct transmission, if no protocol object available
			{
				var update = {};
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

		// reset values (in case connection is lost)
		this.et2.getWidgetById('zone').set_value('');
		this.et2.getWidgetById('top').set_value('');
		this.try_num(0);
		this.init_boulder();

		// loading values from server
		if (PerId && n)
		{
			var WetId = this.et2.getWidgetById('comp[WetId]').get_value();
			var GrpId = this.et2.getWidgetById('nm[cat]').get_value();
			var route_order = this.et2.getWidgetById('nm[route]').get_value();

			this.egw.json('ranking_boulder_measurement::ajax_load_athlete',
				[PerId, {},	// {} = send data back
				{'WetId': WetId, 'GrpId': GrpId, 'route_order': route_order}],
				function(_data)
				{
					this.et2.getWidgetById('zone').set_value(_data['zone'+n]);
					this.et2.getWidgetById('top').set_value(_data['top'+n]);
					this.try_num(0);
					this.init_boulder();

					this.message(_data.athlete);
				},
				null, false, this).sendRequest();
		}
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
	 * @param {string} path
	 */
	load_topo: function(path)
	{
		var topo = document.getElementById('topo');

		remove_handholds();

		topo.src = window.egw_webserverUrl+(path ? '/webdav.php'+path : '/phpgwapi/templates/default/images/transparent.png');

		if (path) xajax_doXMLHTTP('ranking_measurement::ajax_load_topo',path);
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

		// mark current athlets height
		var height = parseFloat(this.et2.getWidgetById('result_height').get_value());
		var plus = this.et2.getWidgetById('result_plus').get_value();
		var current = this.getHoldsByHeight(plus == this.TOP_PLUS ? this.TOP_HEIGHT : (height ? height : 1));
		if (current.length) {
			current[0].scrollIntoView(false);
			if (height || plus == this.TOP_PLUS) this.mark_holds(current);
		}
	}
});
