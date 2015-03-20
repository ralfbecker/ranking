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

		switch (name)
		{
			case 'ranking.result.index':
				var self = this;
				this.et2.iterateOver(function(_widget){
					_widget.setNextmatch(self);
				}, window, et2_nextmatch_sortheader);
				var content = this.et2._inst.widgetContainer.getArrayMgr('content').data;
				var sort_widget = this.et2.getWidgetById(content.nm.order);
				if (sort_widget) sort_widget.setSortmode(content.nm.sort.toLowerCase());
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
		if (_app == 'ranking' && !_id && this.et2)
		{
			this.et2._inst.submit();
		}
	},

	/**
	 * onClick handler for nextmatch sortheader
	 *
	 * @returns {undefined}
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
alert('Not yet implemented :-('); return;
		var bonus = document.getElementById(top.name.replace(/tops/g,'zones'));

		if (bonus && !bonus.value && parseInt(top.value) > 0) bonus.value = top.value;

		if (bonus && parseInt(top.value) > 0 && parseInt(top.value) > parseInt(bonus.value))
		{
			window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Top > Bonus!');
				bonus.value = top.value;
			}, 10);
		}

		var tries = document.getElementById(top.name.replace(/tops/g,'top_tries'));

		if (tries && !tries.value && parseInt(top.value) > 0) tries.value = top.value;

		if (tries && parseInt(top.value) > 0 && parseInt(top.value) > parseInt(tries.value))
		{
			window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Top > Tries!');
				tries.value = top.value;
			}, 10);
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
alert('Not yet implemented :-('); return;
		var top = document.getElementById(bonus.name.replace(/zones/g,'tops'));

		if (top && parseInt(top.value) > 0 && parseInt(bonus.value) < parseInt(top.value))
		{
			window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Bonus < Top!');
				top.value = bonus.value;
			}, 10);
		}
		if (top && parseInt(top.value) > 0 && !bonus.value)
		{
			top.value = bonus.value;
		}

		var tries = document.getElementById(bonus.name.replace(/zones/g,'zone_tries'));

		if (tries && !tries.value && parseInt(bonus.value) > 0) tries.value = bonus.value;

		if (tries && parseInt(bonus.value) > 0 && parseInt(bonus.value) > parseInt(tries.value))
		{
			window.setTimeout(function(){	// work around iOS bug crashing Safari
				alert('Bonus > Tries!');
				tries.value = bonus.value;
			}, 10);
		}
	}

});
