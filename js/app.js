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
				if (this.content.nm.show_result == 4)	// measurement
				{
					switch(this.content.nm.discipline)
					{
						case 'boulder':
						case 'selfscore':
							this.init_boulder();
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
	 * @param n
	 */
	set_try: function(n)
	{
		this.try_num(n-1, true);

		this.try_clicked(this.et2.getWidgetById('button[try]'));
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
	}
});
