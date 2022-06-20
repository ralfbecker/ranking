/**
 * EGroupware digital ROCK Rankings & ResultService
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link: https://www.egroupware.org
 * @link https://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-21 by Ralf Becker <RalfBecker@digitalrock.de>
 */

/*egw:uses
	/api/js/jsapi/egw_app.js
 */

import { EgwApp } from '../../api/js/jsapi/egw_app';
import {et2_nextmatch, et2_nextmatch_sortheader} from "../../api/js/etemplate/et2_extension_nextmatch";
import {et2_button} from "../../api/js/etemplate/et2_widget_button";
import {et2_createWidget, et2_widget} from "../../api/js/etemplate/et2_core_widget";
import {et2_textbox} from "../../api/js/etemplate/et2_widget_textbox";
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";
import {et2_checkbox} from "../../api/js/etemplate/et2_widget_checkbox";
import {etemplate2} from "../../api/js/etemplate/etemplate2";

declare var Resultlist;	// ../sitemgr/digitalrock/dr_api.js
declare var protocol;	// ./boulder.js

/**
 * @augments AppJS
 */
class RankingApp extends EgwApp
{
	readonly appname = 'ranking';

	/**
	 * eT2 content after call to et2_ready
	 */
	content : any = {};

	/**
	 * Constructor
	 *
	 * @memberOf app.ranking
	 */
	constructor()
	{
		// call parent
		super('ranking');
	}

	/**
	 * Destructor
	 */
	destroy()
	{
		// call parent
		super.destroy.apply(this, arguments);
	}

	/**
	 * Athlete to select when entering measurement
	 */
	select_athlete : any = undefined;

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} et2 newly ready object
	 * @param {string} name
	 */
	et2_ready(et2, name)
	{
		// call parent
		super.et2_ready.apply(this, arguments);

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
				let sort_widget = <et2_nextmatch_sortheader>this.et2.getWidgetById(this.content.nm.order);
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
								this.et2.setValueById('nm[PerId]', this.select_athlete);
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
					jQuery('#ranking-result-index').on('dblclick', 'table.speed_athlete', this.edit_pairing.bind(this));
				}
				break;

			case 'ranking.registration':
				this.show_hint(this.egw.lang("How to register athletes"),
					this.egw.lang("1. select competition\n2. click on [+ Register]\n3. select category\n4. search for athletes to register")+"\n\n"+
					this.egw.lang("To remove already registered athletes:\nright click on them in list and choose 'delete' from context menu"),
					'no_registration_hint');
				break;

			case 'ranking.athlete.edit':
				const license_data = location.search.match(/(\?|&)license_(nation|year|cat)=/g);
				if (license_data && license_data.length === 3)
				{
					(<et2_button>this.et2.getWidgetById('button[apply_license]')).click();
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
	}

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		if (pushData.app.substr(0, 7) !== 'ranking') return;

		if (pushData.app === 'ranking-result' && this.content.nm.template.match(/^ranking\.result\.index/))
		{
			const show_result = this.et2.getValueById('nm[show_result]');
			if (show_result == 4) return;	// no nextmatch/refresh in measurement

			const WetId = this.et2.getValueById('nm[comp]');
			const GrpId = this.et2.getValueById('nm[cat]');
			if (isNaN(WetId) || WetId != pushData.id.WetId ||
				isNaN(GrpId) || pushData.id.GrpId != GrpId)
			{
				return;	// different competition or category
			}

			const route = this.et2.getValueById('nm[route]');
			if (isNaN(route) || route >= 0 && route != pushData.id.route)
			{
				return;	// no route selected or (single) route does not match
			}

			// ToDo: deal with evtl. open edit result dialog

			// for matching route or general result trigger refresh

		}
	}

	/**
	 * Align non-breaking-space separated parts of text by wrapping them in equally sized spans
	 *
	 * @param {string|jQuery} elems
	 */
	align_nbsp(elems)
	{
		jQuery(elems).contents().filter(function(){
			return this.nodeType === 3;
		}).replaceWith(function()
		{
			const parts = jQuery(this).text().split(/\u00A0+/);
			if (parts.length == 1) return jQuery(this).clone();
			const prefix = '<div class="tdAlign' + parts.length + '">';
			const postfix = '</div>';
			return jQuery(prefix+parts.join(postfix+prefix)+postfix);
		});
	}

	beamerGo()
	{
		const display = <et2_textbox>this.et2.getWidgetById('display');
		const url = this.et2.getValueById('href');

		// readonly/hidden display for anonymous session has no getValue method
		if (display && display.getValue && display.getValue())
		{
			const fragment = '<iframe src="http://' + location.host + url +
				'" scrolling="auto" frameborder="0" width="%width%" height="%height%" allowfullscreen></iframe>';

			const push_url = 'http://'+display.getValue()+'/pushURL?url='+encodeURIComponent(fragment)+'&embed=1';
			const win = window.open(push_url, '_blank', 'width=640,height=480');
			window.setTimeout(function() {
				win.close();
			}, 500);
		}
		else
		{
			document.location = url;
		}
	}

	/**
	 * Apply changes to beamer url
	 *
	 * @param {DOMNode} _node
	 * @param {et2_widget} _widget
	 */
	applyBeamerUrl(_node, _widget)
	{
		const href = <et2_textbox>this.et2.getWidgetById('href');
		const regexp = new RegExp('&' + _widget.id + '=[^&]*');
		let url = href.getValue().replace(regexp, '');
		const value = _widget.getValue();

		if (value !== '')
		{
			url += '&'+_widget.id+'='+encodeURIComponent(value);
		}
		href.set_value(url);

		this.egw.json('ranking_beamer::ajax_set_session', [_widget.id, value]).sendRequest();
	}

	/**
	 * This method sets nextmatch class for nextmatch_sortheaders
	 * with "this" context in order to be able to call sortBy local
	 * function by click on sortheaders.
	 */
	_set_nextmacth_sort_headers()
	{
		const self = this;
		this.et2.iterateOver(function(_widget){
			_widget.setNextmatch(self);
		}, window, et2_nextmatch_sortheader);
	}

	/**
	 * Show a hint to the user, unless he already confirmed it (and we stored a named preference for it)
	 *
	 * @param {string} _title title of hint
	 * @param {string} _text text of hint
	 * @param {string} _name name of implicit preference set if user confirms hint / dont want to see it anymore, eg. "no_xyz_hint"
	 */
	show_hint(_title, _text, _name)
	{
		if (this.egw.getSessionItem(this.appname, _name))
		{
			return;
		}
		const pref = this.egw.preference(_name, this.appname, function (_value) {
			this.show_hint(_title, _text, _name);
		}, this);

		if (typeof pref == 'undefined')	// true: user already confirmed, false: not yet loaded
		{
			const self = this;
			const dialog = et2_dialog.show_dialog(function (_button) {
				if (_button == et2_dialog.NO_BUTTON) {
					self.egw.set_preference(self.appname, _name, true);
				}
				self.egw.setSessionItem(self.appname, _name, undefined);
			}, _text + "\n\n" +
				this.egw.lang("Display this message again next time?"), _title);
		}
	}

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
	observer(_msg, _app, _id, _type, _msg_type, _links)
	{
		if (_app == 'ranking' && _id && typeof _id == 'object')
		{
			this.egw.open_link(this.egw.link('/index.php', _id), 'ranking');
		}
	}

	/**
	 * Handle links to not reload nextmatch, if index page should be refreshed (eg. Sidebox Header clicked)
	 *
	 * @param {string} url
	 * @returns {boolean} True if we could handle the link internally, false to let framework handle it
	 */
	linkHandler(url)
	{
		// if we are on our index page, check if current view contains nextmatch and refresh (applyFilter) it
		if (this.et2 && url.match(/(\/ranking\/(index\.php)?|\/index\.php\?menuaction=ranking\.ranking_ui\.index&ajax=true)$/))
		{
			let nextmatch_found = false;

			this.et2.iterateOver(function(_widget) {
				_widget.applyFilters();
				nextmatch_found = true;
			}, this, et2_nextmatch);

			return nextmatch_found;
		}
		return false;
	}

	/***************************************************************************
	 * Resultservice
	 **************************************************************************/

	/**
	 * onClick handler for nextmatch sortheader
	 *
	 * @param {string} _order
	 * @param {string} _sort
	 */
	sortBy(_order, _sort)
	{
		const sort_widget = <et2_nextmatch_sortheader>this.et2.getWidgetById(_order);
		if (sort_widget)
		{
			const content = this.et2._inst.widgetContainer.getArrayMgr('content').data;
			if (content.nm.order && content.nm.order != _order)
			{
				const old_sort_widget = <et2_nextmatch_sortheader>this.et2.getWidgetById(content.nm.order);
				old_sort_widget.setSortmode('none');
				content.nm.order = _order;
				content.nm.sort = 'DESC';
			}
			// state handling in nextmatch_sortheader is broken or wired, we do our own
			_sort = content.nm.sort != 'DESC' ? 'desc' : 'asc';
			content.nm.sort = _sort.toUpperCase();

			sort_widget.setSortmode(_sort);

			// set order&sort to hidden input and submit to server
			this.et2.setValueById('nm[order]', content.nm.order);
			this.et2.setValueById('nm[sort]', content.nm.sort);
			this.et2.getInstanceManager().submit();
		}
	}

	/**
	 * Action to measure a selected athlet
	 *
	 * @param {egw_action} _action
	 * @param {Array} _selected
	 */
	action_measure(_action, _selected)
	{
		this.select_athlete = _selected[0].id;
		this.et2.setValueById('nm[show_result]', '4');
	}

	/**
	 * Action to delete selected athlet(s)
	 *
	 * @param {egw_action} _action
	 * @param {Array} _selected
	 */
	action_delete(_action, _selected)
	{
		const self = this;
		const data = _selected[0].data;
		const msg = data.nachname + ', ' + data.vorname + ', ' + data.nation + ' (' + data.start_number + ')' + "\n\n" +
			this.egw.lang('Delete this participant (can NOT be undone)') + '?';

		et2_dialog.show_dialog(function(_button)
		{
			if (_button == et2_dialog.YES_BUTTON)
			{
				self.et2.setValueById('nm[action]', 'delete');
				self.et2.setValueById('nm[selected]', [data.PerId]);
				self.et2._inst.submit();
			}
		}, msg, this.egw.lang('Delete'));
	}

	/**
	 * Let user confirm federation change for GER, as it invalidates the license
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_number} _widget
	 */
	federationChanged(_event, _widget : et2_selectbox)
	{
		if (this.et2.getArrayMgr('content').getEntry('nation') !== 'GER')
		{
			return;
		}

		et2_dialog.show_dialog((_button) =>
		{
			if (_button === et2_dialog.CANCEL_BUTTON)
			{
				_widget.set_value(this.et2.getArrayMgr('content').getEntry('fed_id'));
			}
		}, "Wenn die Sektion gewechselt wird, erlischt die Kletterlizenz! Sie muss neu beantragt UND von Sektion, LV und Bundesgeschäftsstelle bestätigt werden, bevor wieder an einem Wettkampf teilgenommen werden kann.",
		'Sektion wechseln?', undefined, et2_dialog.BUTTONS_OK_CANCEL, et2_dialog.QUESTION_MESSAGE);
	}

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
	height_keypress(_event, _widget)
	{
		let plus_widget;
		const key2plus = {
			116: '9999',	// t
			84: '9999',		// T
			43: '1',		// +
			45: '-1',		// -
			32: '0'			// space
		};
		const key = _event.keyCode || _event.which;
		if (typeof key2plus[key] != 'undefined')
		{
			plus_widget = _widget.getParent().getWidgetById(_widget.id.replace('result_height', 'result_plus'));
			if (plus_widget) plus_widget.set_value(key2plus[key]);
			if (key2plus[key] === '9999') _widget.set_value('');	// for top, remove height
			return false;
		  }
		// "0"-"9", "." or "," --> allow and remove "Top"
		if (48 <= key && key <= 57 || key == 44 || key == 46)
		{
			plus_widget = _widget.getParent().getWidgetById(_widget.id.replace('result_height', 'result_plus'));
			if (plus_widget && plus_widget.get_value() == '9999') plus_widget.set_value('0');
			return true;
		}
		// ignore all other chars
		return false;
	}

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
	check_top(node, top)
	{
		const top_value = top ? top.get_value() : undefined;
		const bonus = top.getRoot().getWidgetById(top.id.replace(/top/g, 'zone'));
		let bonus_value = bonus ? bonus.get_value() : undefined;

		if (bonus && (!bonus_value || bonus_value == '0') && parseInt(top_value) > 0) bonus.set_value(bonus_value=top_value);

		if (bonus && parseInt(top_value) > 0 && parseInt(top_value) < parseInt(bonus_value))
		{
			et2_dialog.alert('Top < Bonus!');
			bonus.set_value(top_value);
		}
	}

	/**
	 * onChange of bonus: dont allow to set a bonus bigger then top or no bonus, but a top
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} bonus select box
	 */
	check_bonus(node, bonus)
	{
		const bonus_value = bonus ? bonus.get_value() : undefined;
		const top = bonus.getRoot().getWidgetById(bonus.id.replace(/zone/g, 'top'));
		let top_value = top ? top.get_value() : undefined;

		if (top && parseInt(top_value) > 0 && parseInt(bonus_value) > parseInt(top_value))
		{
			//window.setTimeout(function(){	// work around iOS bug crashing Safari
				et2_dialog.alert('Bonus > Top!');
				top.set_value(top_value=bonus_value);
			//}, 10);
		}
		if (top && parseInt(top_value) > 0 && !bonus_value)
		{
			top.set_value(bonus_value);
		}
	}

	/**
	 * onChange of tops: if bonus not set, set it to the same number of tops
	 *  if tops > zones alert user and set tops to zones or
	 *  if less tries then tops alert user and set tries to tops
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} tops select box
	 */
	check_tops(node, tops)
	{
		const bonus = tops.getRoot().getWidgetById(tops.id.replace(/tops/g, 'zones'));

		if (bonus && !bonus.get_value() && parseInt(tops.get_value()) > 0) bonus.set_value(tops.get_value());

		if (bonus && parseInt(tops.get_value()) > 0 && parseInt(tops.get_value()) > parseInt(bonus.get_value()))
		{
			et2_dialog.alert('Top > Bonus!');
			bonus.set_value(tops.get_value());
		}

		const tries = tops.getRoot().getWidgetById(tops.id.replace(/tops/g, 'top_tries'));

		if (tries && !tries.get_value() && parseInt(tops.get_value()) > 0) tries.set_value(tops.get_value());

		if (tries && parseInt(tops.get_value()) > 0 && parseInt(tops.get_value()) > parseInt(tries.get_value()))
		{
			et2_dialog.alert('Top > Tries!');
			tries.set_value(tops.get_value());
		}
	}

	/**
	 * onChange of top_tries: if number of tops > top_tries, set tops to tries
	 *
	 * @param {DOMNode} node
	 * @param {et2_selectbox} tries select box
	 */
	check_top_tries(node, tries)
	{
		const tops = tries.getRoot().getWidgetById(tries.id.replace(/top_tries/g, 'tops'));

		if (tops && parseInt(tops.get_value()) > 0 && parseInt(tops.get_value()) > parseInt(tries.get_value()))
		{
			et2_dialog.alert('Top > Tries!');
			tops.set_value(tops.get_value());
		}
	}

	/**
	 * onChange of bonus sum: dont allow to set a boni bigger then tops or no bonus, but a top
	 * 	or if less tries then boni alert user and set tries to boni
	 *
	 * @param {DOMNode} node
	 * @param {DOMNode} bonus select box
	 */
	check_boni(node, bonus)
	{
		const tops = bonus.getRoot().getWidgetById(bonus.id.replace(/zones/g, 'tops'));

		if (tops && parseInt(tops.get_value()) > 0 && parseInt(bonus.get_value()) < parseInt(tops.get_value()))
		{
			et2_dialog.alert('Bonus < Top!');
			tops.set_value(bonus.get_value());
		}
		if (tops && parseInt(tops.get_value()) > 0 && !bonus.get_value())
		{
			tops.set_value(bonus.get_value());
		}

		const tries = bonus.getRoot().getWidgetById(bonus.id.replace(/zones/g, 'zone_tries'));

		if (tries && !tries.get_value() && parseInt(bonus.get_value()) > 0) tries.set_value(bonus.get_value());

		if (tries && parseInt(bonus.get_value()) > 0 && parseInt(bonus.get_value()) > parseInt(tries.get_value()))
		{
			et2_dialog.alert('Bonus > Tries!');
			tries.set_value(bonus.get_value());
		}
	}

	/**
	 * onChange of bonus tries: dont allow to set less tries then bonus
	 *
	 * @param {DOMNode} node
	 * @param {DOMNode} tries select box
	 */
	check_bonus_tries(node, tries)
	{
		const bonus = tries.getRoot().getWidgetById(tries.id.replace(/zone_tries/g, 'zones'));

		if (bonus && parseInt(bonus.get_value()) > 0 && parseInt(bonus.get_value()) > parseInt(tries.get_value()))
		{
			et2_dialog.alert('Bonus > Tries!');
			bonus.set_value(tries.get_value());
		}

	}

	/***************************************************************************
	 * Boulder measurement
	 **************************************************************************/

	resultlist : any = undefined;

	/**
	 * Init boulder measurement or update button state
	 */
	init_boulder()
	{
		const PerId = this.et2.getValueById('nm[PerId]',);
		const boulder_n = <et2_textbox>this.et2.getWidgetById('nm[boulder_n]');
		const n = boulder_n ? boulder_n.get_value() : null;

		// loading values from server
		if (PerId && n)
		{
			const WetId = this.et2.getValueById('comp[WetId]',);
			const GrpId = this.et2.getValueById('nm[cat]',);
			const route_order = this.et2.getValueById('nm[route]',);
			const keys = {WetId: WetId, GrpId: GrpId, route_order: route_order, boulder_n: n};

			this.egw.json('ranking_boulder_measurement::ajax_load_athlete',
				[PerId, keys],
				function(_data)
				{
					this.et2.setValueById('zone', _data['zone'+n]);
					this.et2.setValueById('top', _data['top'+n]);
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
			.prop('disabled', !PerId || !n);

		// for selfscore install some behavior on bonus/top/flash checkboxes to
		// only allow valid combinations and ease use eg. click flash checks bonus&top
		if(this.content.nm.discipline == 'selfscore')
		{
			jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=zone]').on('change', function(){
				if (!(<HTMLInputElement>this).checked)
				{
					jQuery(this.parentNode).find('input[type=checkbox]').prop('checked', false);
				}
			});
			jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=top]').on('change', function(){
				if (!(<HTMLInputElement>this).checked)
				{
					jQuery(this.parentNode).find('input[type=checkbox][name*=flash]').prop('checked', false);
				}
				else
				{
					jQuery(this.parentNode).find('input[type=checkbox][name*=zone]').prop('checked', true);
				}
			});
			jQuery('#ranking-result-index_ranking-result-selfscore_measurement input[type=checkbox][name*=flash]').on('change', function(){
				if ((<HTMLInputElement>this).checked)
				{
					jQuery(this.parentNode).find('input[type=checkbox]').prop('checked', true);
				}
			});
			// hide not used bonus/top/flash checkboxes
			const use = this.content.selfscore_use || 't';
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
			this.resultlist = new Resultlist('ranking-result-index_resultlist', egw.webserverUrl+'/ranking/json.php'+
				'?comp='+this.content.comp.WetId+
				'&cat='+this.et2.getValueById('nm[cat]', )+
				'&route='+this.et2.getValueById('nm[route]', )+'&detail=2&toc=0');

			// set methods for interaction with protocol
			if (protocol)
			{
				protocol.set_msg(this.message.bind(this));
				protocol.get_athlete(this.get_athlete.bind(this));
			}
		}
	}

	/**
	 * Disable boulder measurement to not allow sending updates
	 *
	 * @param {boolean} _ro
	 */
	no_measurement(_ro)
	{
		if (!this.et2 && _ro)
		{
			return window.setTimeout(this.no_measurement.bind(this, _ro), 100)
		}

		['button[update]','button[try]','button[bonus]','button[top]']
			.forEach(name => (<et2_button>this.et2.getWidgetById(name)).set_readonly(_ro));
	}

	/**
	 * Get athlete name by PerId using options from athlete selectbox
	 *
	 * @param {int} _PerId
	 * @returns {String}
	 */
	get_athlete(_PerId)
	{
		const select = <HTMLSelectElement>this.et2.getDOMWidgetById('nm[PerId]').getDOMNode();

		if (select && select.options)
		{
			for(let i=1; i < select.options.length; ++i)
			{
				const option = select.options.item(i);
				if (option.value == _PerId)
				{
					return jQuery(option).text();
				}
			}
		}
		return '#'+_PerId;
	}

	/**
	 * Display message
	 *
	 * @param {string} _msg message to display
	 */
	message(_msg)
	{
		this.et2.setValueById('msg', _msg);
	}

	/**
	 * Get current state
	 *
	 * @returns {object} with values for attributes try, bonus and top
	 */
	get_state()
	{
		return {
			try: this.try_num(),
			bonus: this.et2.getValueById('zone', ),
			top: this.et2.getValueById('top', )
		};
	}

	/**
	 * [Try] button clicked --> update number
	 *
	 * @param button
	 */
	try_clicked(button)
	{
		this._button_vibrate();
		const state = this.get_state();
		let num = this.try_num();
		this.try_num(++num);

		const bonus = <et2_selectbox>this.et2.getWidgetById('zone');

		// set bonus 'No'=0, for 1. try, if there's no bonus yet
		if (num == 1 && bonus && bonus.get_value() === '')
		{
			bonus.set_value('0');
		}
		this.update_boulder('try', state);
	}

	/**
	 * Set tries to given number
	 *
	 * @param {DOMNode} _node
	 * @param {et2_selectbox} _widget
	 */
	set_try(_node, _widget)
	{
		this.try_num(_widget.get_value(), true);
	}

	/**
	 * Get number of try from label of try button
	 *
	 * @param {int} set_value 0: reset, 1: set to 1, if not already higher
	 * @param {boolean} set_anyway set, even if number is smaller
	 * @returns int number of try
	 */
	try_num(set_value : number = undefined, set_anyway = false) : number
	{
		let try_button = <et2_button>this.et2.getWidgetById('button[try]');

		let num = parseInt(jQuery(try_button.getDOMNode()).text());
		if (isNaN(num)) num = 0;

		if (typeof set_value != 'undefined')
		{
			if (set_value == 0 || num < set_value || set_anyway)
			{
				this.et2.setValueById('try', num = set_value);
			}
			try_button.set_label((num ? ''+num+'. ' : '')+try_button.options.label);
		}
		return num;
	}

	/**
	 * Bonus button clicked
	 *
	 * @param button
	 */
	bonus_clicked(button)
	{
		this._button_vibrate();
		const state = this.get_state();
		const bonus = <et2_selectbox>this.et2.getWidgetById('zone');

		// only update bonus, if there is no bonus yet
		if (!state.bonus || state.bonus == '0')
		{
			bonus.set_value(this.try_num(1));
		}
		this.check_bonus(bonus.getDOMNode(), bonus);

		this.update_boulder('bonus', state);
	}

	/**
	 * Bonus button clicked
	 *
	 * @param button
	 */
	top_clicked(button)
	{
		this._button_vibrate();
		let state = this.get_state();
		const bonus = <et2_selectbox>this.et2.getWidgetById('zone');
		const top = <et2_selectbox>this.et2.getWidgetById('top');
		let num = this.try_num(1);

		if (!isNaN(num))
		{
			if(!bonus.get_value() || bonus.get_value() == '0') bonus.set_value(num);
			top.set_value(num);
			this.check_top(top.getDOMNode(), top);
		}
		this.update_boulder('top', state);
	}

	/**
	 * Sending bonus and top for PerId to server
	 *
	 * @param {string} clicked
	 * @param {object} state
	 */
	update_boulder(clicked, state)
	{
		const WetId = this.et2.getValueById('comp[WetId]',);
		const n = this.et2.getValueById('nm[boulder_n]',);
		const PerId = this.et2.getValueById('nm[PerId]',);
		const GrpId = this.et2.getValueById('nm[cat]',);
		const route_order = this.et2.getValueById('nm[route]',);

		if (PerId && n)
		{
			const bonus = this.et2.getValueById('zone',);
			const top = this.et2.getValueById('top',);

			if (typeof protocol != 'undefined')
			{
				protocol.record({
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
				const update = {};
				update['try'+n] = this.try_num();
				update['zone'+n] = bonus === '' ? 'empty' : bonus;	// egw_json_encode sends '' as null, which get not stored!
				update['top'+n] = top ? top : 0;	// required, as backend doesn't store zones with empty top!

				this.egw.json('ranking_boulder_measurement::ajax_update_result',
					[PerId, update, {'WetId': WetId, 'GrpId': GrpId, 'route_order': route_order}, n]).sendRequest();
			}
		}
	}

	/**
	 * Boulder or athlete changed
	 *
	 * @param selectbox
	 */
	boulder_changed(selectbox)
	{
		const PerId = this.et2.getValueById('nm[PerId]',);
		const n = this.et2.getValueById('nm[boulder_n]',);
		const WetId = this.et2.getValueById('comp[WetId]',);
		const GrpId = this.et2.getValueById('nm[cat]',);
		const route_order = this.et2.getValueById('nm[route]',);
		const keys = {WetId: WetId, GrpId: GrpId, route_order: route_order};

		// reset values
		this.et2.setValueById('zone', '');
		this.et2.setValueById('top', '');
		this.try_num(0);

		// try restore values from local protocol, in case we have lost contact to server
		if (PerId && n && protocol)
		{
			const local = protocol.get(jQuery.extend({
				PerId: PerId,
				boulder: n,
				route: route_order
			}, keys));
			if (local)
			{
				if (local.try) this.try_num(local.try);
				if (local.bonus) this.et2.setValueById('zone', local.bonus);
				if (local.top) this.et2.setValueById('top', local.top);
			}
		}
		this.init_boulder();
	}

	/**
	 * Go to next athlete
	 */
	boulder_next()
	{
		const PerId = this.et2.getDOMWidgetById('nm[PerId]');
		const node = <HTMLSelectElement>PerId.getDOMNode();

		node.selectedIndex = node.selectedIndex + 1;	// ++ works NOT reliable ...

		// need to call this manually, as changing selectedIndex does NOT trigger onchange
		this.boulder_changed(PerId);
	}

	/**
	 * Current order of athletes to be sorted: "order", "number" or "name"
	 */
	sorted_by : any = 'startorder';

	/**
	 * Sort options of athlete selectbox
	 *
	 * @param {JQuery.Event} _event
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	sort_athletes(_event : JQuery.Event = undefined, _widget : et2_widget = undefined, _node : Node = undefined)
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
		const select = <et2_selectbox>this.et2.getWidgetById('nm[PerId]');
		const options = select.options.select_options;
		const by = this.sorted_by;
		const regexp = /(\d+) \((\d+)\) (.*)/;
		options.sort(function(_a, _b)
		{
			const a = regexp.exec(_a.label);
			const b = regexp.exec(_b.label);
			switch(by)
			{
				case 'name':
					return a[3].localeCompare(b[3]);
				case 'startorder':
					return parseInt(a[1]) - parseInt(b[1]);
				case 'startnumber':
					return parseInt(a[2]) - parseInt(b[2]);
			}
		});
		select.set_select_options(options);

		if (_event)
		{
			this.egw.message(this.egw.lang('Sorted athletes by %1', this.egw.lang(this.sorted_by)));
		}
	}

	/**************************************************************************
	 * Selfscore boulder measurement
	 **************************************************************************/

	/**
	 * Update selfscore scorecard
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_button} _widget
	 */
	update_scorecard(_event, _widget)
	{
		const values = _widget.getInstanceManager().getValues(this.et2);

		this.egw.json('ranking_selfscore_measurement::ajax_update_result',
			[values.nm.PerId, values.score, {'WetId': values.comp.WetId, 'GrpId': values.nm.cat, 'route_order': values.nm.route}],
				function(_msg)
				{
					this.message(_msg);
					this.resultlist.update();
				},
				null, false, this).sendRequest();
	}

	/**
	 * Boulder or athlete changed
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_selectbox} _widget
	 */
	scorecard_changed(_event, _widget)
	{
		const PerId = this.et2.getValueById('nm[PerId]',);

		if (PerId)
		{
			const WetId = this.et2.getValueById('comp[WetId]',);
			const GrpId = this.et2.getValueById('nm[cat]',);
			const route_order = this.et2.getValueById('nm[route]',);

			this.egw.json('ranking_selfscore_measurement::ajax_load_athlete',
				[PerId, {'WetId': WetId, 'GrpId': GrpId, 'route_order': route_order}],
				function(_data)
				{
					this.message(_data.msg);
					delete _data.msg;

					const readonly = !_data.update_allowed;
					delete _data.update_allowed;

					this.et2.setValueById('score', _data);

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
			this.et2.setValueById('score', {content: {}});
		}
	}

	/**************************************************************************
	 * LEAD measurement
	 **************************************************************************/

	TOP_HEIGHT : any = 999;	// everything >= this.TOP_HEIGHT means top (server defines it as 99999999 for DB!)
	TOP_PLUS : any = 9999;		// option-value of 'Top' in plus selectbox

	/**
	 * Change athlete, onchange for ahtlete selection
	 *
	 * @param {DOMNode} _node
	 * @param {et2_select} _widget
	 */
	change_athlete(_node, _widget)
	{
		if (_widget.get_value())
		{
			const WetId = this.et2.getValueById('comp[WetId]',);
			const GrpId = this.et2.getValueById('nm[cat]',);
			const route_order = this.et2.getValueById('nm[route]',);

			this.unmark_holds();
			this.egw.json('ranking_measurement::ajax_load_athlete', [ _widget.get_value(),
				{ WetId: WetId, GrpId: GrpId, route_order: route_order }],
				function(_data)
				{
					const holds = this.getHoldsByHeight(_data.result_plus == this.TOP_PLUS ? this.TOP_HEIGHT :
						(_data.result_height ? _data.result_height : 1));
					if (holds.length) holds[0].scrollIntoView(false);
					if (_data.result_plus == this.TOP_PLUS || _data.result_height) this.mark_holds(holds);

					this.et2.setValueById('result_height', _data.result_height);
					this.et2.setValueById('result_plus', _data.result_plus);
					this.message(_data.athlete);
				},
				null, false, this).sendRequest();
		}
		else
		{
			this.et2.setValueById('result_height', '');
			this.et2.setValueById('result_plus', '');
			this.et2.setValueById('result_time', '');
		}
	}

	/**
	 * Go to next athlete
	 */
	lead_next()
	{
		const PerId = <et2_selectbox>this.et2.getDOMWidgetById('nm[PerId]');
		const node = <HTMLSelectElement>PerId.getDOMNode();

		node.selectedIndex = node.selectedIndex + 1;	// ++ works NOT reliable ...

		this.change_athlete(node, PerId);
	}

	/**
	 * Update athlete, send new result to server
	 *
	 * @param {boolean|number} scroll_mark =true or number to add to current height eg. 3 to scroll 3rd heigher hold into view
	 */
	update_athlete(scroll_mark)
	{
		const WetId = this.et2.getValueById('comp[WetId]',);
		const GrpId = this.et2.getValueById('nm[cat]',);
		const route_order = this.et2.getValueById('nm[route]',);
		const PerId = this.et2.getValueById('nm[PerId]',);

		if (PerId)
		{
			const height = <et2_textbox>this.et2.getWidgetById('result_height');
			const plus = <et2_selectbox>this.et2.getWidgetById('result_plus');
			const time = <et2_textbox>this.et2.getWidgetById('result_time');
			// top?
			if (plus.get_value() == this.TOP_PLUS || height.get_value() >= this.TOP_HEIGHT)
			{
				height.set_value('');
				plus.set_value(this.TOP_PLUS);
			}
			if (typeof scroll_mark == 'undefined' || scroll_mark)
			{
				const holds = this.getHoldsByHeight(plus.get_value() == this.TOP_PLUS ? this.TOP_HEIGHT :
					(typeof scroll_mark == 'number' ? scroll_mark : 0) + parseInt(height.get_value()));
				if (holds.length)
				{
					(holds as any).scrollIntoView(typeof scroll_mark == 'number');	// jQuery plugin NOT regular DOM method!
					if (typeof scroll_mark != 'number') this.mark_holds(holds);
				}
			}
			this.egw.json('ranking_measurement::ajax_update_result',
				[PerId, { result_height: height.get_value(), result_plus: plus.get_value(), result_time: time.get_value()},
				{WetId: WetId, GrpId: GrpId, route_order: route_order}, 1]).sendRequest();
		}
	}

	/**
	 * Mark holds: red and bold
	 *
	 * @param holds
	 */
	mark_holds(holds)
	{
		jQuery(holds).css({'color':'red','font-weight':'bold'});
	}

	/**
	 * Unmark holds: black and normal
	 *
	 * @param holds holds to mark, default all
	 */
	unmark_holds(holds = undefined)
	{
		if (typeof holds == 'undefined') holds = jQuery('div.topoHandhold');

		jQuery(holds).css({'color':'black', 'font-weight':'normal'});
	}

	/**
	 * Load topo
	 *
	 * @param {DOMNode} _node
	 * @param {et2_selct} _widget
	 */
	load_topo(_node, _widget)
	{
		const topo = <HTMLImageElement>document.getElementById('ranking-result-index_topo');
		const path = _widget.get_value();

		this.remove_handholds();

		topo.src = egw.webserverUrl+(path ? '/webdav.php'+path : '/phpgwapi/templates/default/images/transparent.png');

		if (path) this.egw.json('ranking_measurement::ajax_load_topo', [path]).sendRequest();
	}

	/**
	 * Handler for a click on the topo image
	 *
	 * @param {jQuery.event} e
	 */
	topo_clicked(e)
	{
		//console.log(e);

		const topo = e.target;
		//console.log(topo);

		const PerId = this.et2.getValueById('nm[PerId]',);

		if (!PerId)
		{
			// FF 15 has offsetX/Y values in e.orginalEvent.layerX/Y (Chrome&IE use offsetX/Y)
			const x = e.offsetX ? e.offsetX : e.originalEvent.layerX;
			const y = e.offsetY ? e.offsetY : e.originalEvent.layerY;
			//this.egw.message('topo_clicked() x='+x+'/'+topo.width+', y='+y+'/'+topo.height);
			//this.add_handhold({'xpercent':100.0*x/topo.width, 'ypercent': 100.0*y/topo.height, 'height': 'Test'});
			this.egw.json('ranking_measurement::ajax_save_hold',
				[{xpercent: 100.0*x/topo.width, ypercent: 100.0*y/topo.height}]).sendRequest();
		}
		else
		{
			// measurement mode
		}
	}

	active_hold : any = undefined;

	/**
	 * Handler for a click on a hold
	 *
	 * @param {jQuery.event} e
	 */
	hold_clicked(e)
	{
		this.active_hold = e.target;	// hold container
		this.active_hold = jQuery(this.active_hold.nodeName != 'DIV' ? this.active_hold.parentNode : this.active_hold);	// img or span clicked, not container div itself
		//console.log(this.active_hold);
		//console.log(this.active_hold.data('hold'));

		const PerId = this.et2.getValueById('nm[PerId]',);

		if (!PerId)	// edit topo mode
		{
			const popup = this.et2.getDOMWidgetById('hold_popup').getDOMNode();
			const height = <et2_textbox>this.et2.getWidgetById('hold_height');
			const top = <et2_textbox>this.et2.getWidgetById('hold_top');

			popup.style.display = 'block';
			const is_top = this.active_hold.data('hold').height == this.TOP_HEIGHT;
			height.set_readonly(is_top);
			top.set_value(is_top);
			if (!is_top) height.set_value(this.active_hold.data('hold').height);
		}
		else	// measuring an athlete
		{
			this.et2.setValueById('result_height', this.active_hold.data('hold').height);
			this.et2.setValueById('result_plus', '0');

			this.mark_holds(this.active_hold);
			this.update_athlete(3);	// 3 = scroll 3 holds heigher into view (false = no automatic scroll)
		}
	}

	/**
	 * Top checkbox in hold popup changed
	 *
	 * @param {jQuery.event} _e
	 * @param {et2_checkbox} _widget
	 */
	hold_top_changed(_e, _widget : et2_checkbox)
	{
		const height = <et2_textbox>this.et2.getWidgetById('hold_height');
		const checked = _widget.get_value() === 'true';
		if (checked) height.set_value('');
		height.set_readonly(checked);
		if (!checked) height.getDOMNode().focus();
	}

	/**
	 * Close hold popup and unset this.active_hold
	 */
	hold_popup_close()
	{
		this.et2.getDOMWidgetById('hold_popup').getDOMNode().style.display='none';
		this.active_hold = null;
	}

	/**
	 * Submit hold popup (and close it)
	 *
	 * @param {jQuery.event} _e
	 * @param {et2_button} _button
	 * @param {DOMNode} _node
	 */
	hold_popup_submit(_e, _button, _node)
	{
		let json;
		switch (_button.id)
		{
			case 'button[renumber]':
			case 'button[save]':
				this.active_hold.data('hold').height = this.et2.getValueById('hold_top', ) ?
					this.TOP_HEIGHT : this.et2.getValueById('hold_height', );
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
	}

	/**
	 * Add or update a single handhold
	 *
	 * @param {object} hold
	 */
	add_handhold(hold)
	{
		//console.log('add_handhold({xpercent: '+hold.xpercent+', ypercent: '+hold.ypercent+', height: '+hold.height+'})');
		// as container has a fixed height with overflow: auto, we have to scale ypercent to it
		const y_ratio = jQuery('#ranking-result-index_topo').height() / jQuery('div.topoContainer').height();
		//console.log('#topo.height='+jQuery('#ranking-result-index_topo').height()+' / div.topoContainer.height='+jQuery('div.topoContainer').height()+' = '+y_ratio);
		let container = jQuery('#hold_id_' + hold.hold_id);
		if (!container.length)
		{
			const container_div = document.createElement('div');
			container_div.className = 'topoHandhold';
			container_div.style.left = hold.xpercent+'%';
			container_div.style.top = (y_ratio*hold.ypercent)+'%';
			container = jQuery(container_div);
			container.attr('id', 'hold_id_'+hold.hold_id);

			jQuery(document.createElement('img'))
				.appendTo(container)
				.attr('src', egw.webserverUrl+'/ranking/templates/default/images/griff32.png');
			jQuery(document.createElement('span'))
				.appendTo(container);
			container.click(this.hold_clicked.bind(this));

			jQuery('div.topoContainer').append(container);
		}
		container.attr('title', hold.height >= this.TOP_HEIGHT ? 'Top' : hold.height);
		container.find('span').text(hold.height >= this.TOP_HEIGHT ? 'Top' : hold.height);
		container.data('hold', hold);
	}

	/**
	 * Display an array of handholds
	 *
	 * @param {array} holds
	 */
	show_handholds(holds)
	{
		for(let i = 0; i < holds.length; ++i)
		{
			this.add_handhold(holds[i]);
		}
	}

	remove_handholds()
	{
		jQuery('div.topoContainer div').remove();
	}

	/**
	 * Recalculate handhold position, eg. when window get's resized or topo image is loaded
	 *
	 * Required because topo image is scaled to width:100% AND displayed in a container div with fixed height and overflow:auto
	 *
	 * @param {boolean} resizeContainer =true
	 */
	recalc_handhold_positions(resizeContainer)
	{
		const topo_container = jQuery('div.topoContainer');
		if (!topo_container.length) return;
		topo_container.parent().css({overflow:'hidden'});
		let y_ratio = 1.0;
		// resize topoContainer to full page height
		if (typeof resizeContainer == 'undefined' || resizeContainer)
		{
			const topo_pos = topo_container.offset();
			jQuery('div.topoContainer').height(jQuery(window).height()-topo_pos.top-jQuery('#divGenTime').height()-jQuery('#divPoweredBy').height()-20);
			y_ratio = jQuery('#ranking-result-index_topo').height() / jQuery('div.topoContainer').height();
			//console.log('recalc_handhold_positions() $(#topo).height()='+jQuery('#ranking-result-index_topo').height()+', $(div.topoContainer).height()='+jQuery('div.topoContainer').height()+' --> y_ratio='+y_ratio);
		}
		jQuery('div.topoHandhold').each(function(index,container){
			container.style.top = (y_ratio*jQuery(container).data('hold').ypercent)+'%';
		});
	}

	/**
	 * Transform topo for printing and call print
	 */
	print_topo()
	{
		jQuery('div.topoContainer').width('18cm');	// % placed handholds do NOT work with % with on print!
		jQuery('div.topoContainer').css('height','auto');

		jQuery('div.topoContainer').css('visible');

		this.recalc_handhold_positions(false);

		window.focus();
		window.print();
	}

	/**
	 * Get holds with a given height
	 *
	 * @param {number} height
	 * @returns JQuery<HTMLElement>
	 */
	getHoldsByHeight(height)
	{
		height = parseFloat(height);
		//console.log('getHoldsByHeight('+height+')');
		return jQuery('div.topoHandhold').filter(function() {
			return jQuery(this).data('hold').height == height;
		});
	}

	/**
	 * Init topo stuff, get's call on document.ready via $GLOBALS['egw_info']['flags']['java_script']
	 *
	 * @param {array} holds
	 */
	init_topo(holds)
	{
		jQuery(window).resize(this.recalc_handhold_positions.bind(this));
		jQuery('#ranking-result-index_topo').load(this.recalc_handhold_positions.bind(this));
		jQuery('#ranking-result-index_topo').click(this.topo_clicked.bind(this));
		if (holds && holds.length) this.show_handholds(holds);
		jQuery('#ranking-result-index').css('overflow-y', 'hidden');	// otherwise we get a permanent scrollbar
		if (egwIsMobile()){
			this._scalingHandler(jQuery('#ranking-result-index_topo').parent());
			jQuery(window).on("orientationchange",function(){
				jQuery(window).trigger('resize');
			});
		}
		// mark current athlets height
		const result_height = <et2_textbox>this.et2.getWidgetById('result_height');
		const height = parseFloat(result_height.get_value ? result_height.get_value() : (<HTMLInputElement>result_height.getDOMNode()).value);
		const plus = this.et2.getValueById('result_plus',);
		const current = this.getHoldsByHeight(plus == this.TOP_PLUS ? this.TOP_HEIGHT : (height ? height : 1));
		if (current.length) {
			current[0].scrollIntoView(false);
			if (height || plus == this.TOP_PLUS) this.mark_holds(current);
		}
	}

	/**
	 * Function to scale up/down a given element
	 *
	 * Scaling happens based on pinch In/Out touch actions.
	 *
	 * @param {jQuery object} _node dom node to scale up
	 */
	_scalingHandler(_node)
	{
		/**
				 * Scale in/out the node base on scale value
				 *
				 * @param {float} _scale scale number
				 * @param {type} _direction direction to scale in/out
				 */
		const scale = function(_scale, _direction) {
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
// scale xy used for transforming
		let Sxy = 1;

		// node to scale up/down
		const node = _node;

		// scaling threshold
		const scaleThreshold = 0.10;

		node.css({"transform-origin":"0 0"});

		/**
		 * Method to bind pinch handler for android devicces
		 * @param {type} _node node
		 */
		const android_scale = function (_node) {
			_node.swipe({
				fingers: 2,
				pinchThreshold: 0,
				preventDefaultEvents: true,
				pinchStatus(event, phase, direction, distance, duration, fingerCount, pinchZoom, fingerData)
				{
					const zoom = (parseFloat(pinchZoom) - Math.floor(parseFloat(pinchZoom))) * scaleThreshold;
					if (fingerCount == 2) {
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
		const iOS_scale = function (_node) {
			_node.on({
				gesturechange(e)
				{
					e.preventDefault();
					scale(e.originalEvent.scale * scaleThreshold, Sxy < e.originalEvent.scale ? "in" : "out");
				},
				gestureend(e)
				{
					e.preventDefault();
				},
				gesturestart(e)
				{
					e.preventDefault();
				}
			});
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
	}

	/**
	 * Triggers vibrate
	 */
	_button_vibrate()
	{
		if (egwIsMobile()) framework.vibrate(50);
	}

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
	getRowById(_rows, _id, _row_id = undefined)
	{
		if (typeof _row_id == 'undefined') _row_id='PerId';

		let ret : any = {};
		for (const row in _rows)
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
	}

	/**
	 * Edit double-clicked speed pairing cell
	 *
	 * @param {jQuery.event} event
	 */
	edit_pairing(event)
	{
		const id = <string>jQuery(event.currentTarget).find('input[type=hidden]').val();
		const parts = id ? id.split(/:/) : [];	// WetId:GrpId:route_order:PerId
		const content = this.et2.getArrayMgr('content');
		const route = parseInt(parts[2]);
		if (id.length < 4 || !route || !parts[3] ||
			!content || !content.getEntry('nm[is_judge]') ||
			content.getEntry('nm[result_official]'))
		{
			return;
		}
		// speed finals with less then 16 participants renumber heats in content
		// to use same template hiding first columns!
		const rows = content.getEntry('nm[rows]');
		let heat = route;
		while (heat < 6 && (typeof rows['heat'+heat] == 'undefined' ||
			// in case of a wildcard rows['heat'+heat][1] might not exist!
			rows['heat'+heat][typeof rows['heat'+heat][1] == 'undefined' ? 2 : 1].route_order < route))
		{
			heat++;
		}
		const next = content.getEntry('nm[rows][heat'+(heat+1)+']');
		this.action_edit({}, [{id: parts[3]}], 'heat'+heat,
			next && next[1].result_rank);
	}

	/**
	 * Function to create edit dialog for athlete from the result list
	 *
	 * @param {object} _action egw action object
	 * @param {object} _selected selected action from the grid
	 * @param {string} _heat additional index into nm.rows or undefined
	 * @param {bool}   _ro do NOT allow updates
	 */
	action_edit(_action, _selected, _heat, _ro)
	{
		const PerId = _selected[0].id;
		let template = 'ranking.result.index.rows_boulder.edit';
		const content = this.et2.getArrayMgr('content');
		const sel_options = this.et2.getArrayMgr('sel_options');
		const self = this;
		let nm,entry,row;
		if (content) nm = content.getEntry('nm');

		if (nm)
		{
			template = nm.template + '.edit';
			if (typeof etemplate2.templates[template] == 'undefined') return;	// no edit template found
			row = this.getRowById(_heat ? nm.rows[_heat] : nm.rows, PerId);
			entry = jQuery.extend(row.data, {nm: nm});

			// remove not existing boulders, to not show autorepeated rows
			for(let i=parseInt(nm.num_problems)+1; i <= 10; ++i)
			{
				delete entry['boulder'+i];
			}
		}
		const buttons = [];
		let width = 480;
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

		const dialog = et2_createWidget("dialog",
			{
				id: 'update-result',
				callback(_button, _values)
				{
					switch (_button) {
						case 'update':
						case 'checked':
						case 'uncheck':
							self.update_result_row.call(self, _button, entry, _values, function (_data) {
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
							et2_dialog.alert('Not yet ;-)');
					}
				},
				title: this.egw.lang('Update Result'),
				buttons: buttons,
				value: {
					content: entry || {},
					sel_options: sel_options.data || {},
					readonlys: entry.checked || nm.result_official || _ro ? {__ALL__: true} : {}
				},
				template: template,
				class: "update_result",
				minWidth: width,
				width: width
			});
	}

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
	update_result_row(_button, _entry, _values, _callback, _context)
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

			const nm = this.et2.getWidgetById('nm[rows]');
			_data.sel_options = this.et2.getArrayMgr('sel_options').data;
			if (nm) nm.set_value(_data);

			// update content for next click
			this.et2.getArrayMgr('content').data.nm.rows = _data.content;

			if (_callback) _callback.call(_context || this, _data);
		}, null, false, this).sendRequest();
	}

	/**
	 * Eliminated changed
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	eliminated_changed(_ev, _widget, _node)
	{
		if (_widget.get_value())
		{
			const time = _widget.getParent().getWidgetById(_widget.id.replace(/eliminated/, 'result_time'));
			if (time) time.set_value('');
		}
	}

	/**
	 * Eliminated changed
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	time_changed(_ev, _widget, _node)
	{
		if (_widget.get_value())
		{
			const eliminated = _widget.getParent().getWidgetById(_widget.id.replace(/result_time/, 'eliminated'));
			if (eliminated) eliminated.set_value('');
		}
	}

	/***************************************************************************
	 * Registration
	 **************************************************************************/

	ACL_EDIT : any = 4;
	ACL_REGISTER : any = 64;
	ACL_JUDGE : any = 512;
	ACL_REPLACE : any = 8192;

	_comp_rights : any = {};

	/**
	 * Set or query competition (edit-)rights
	 *
	 * @param {int} _comp
	 * @param {int} _mask mask for check
	 * @param {int} _rights to set, or undefined for check
	 * @returns {boolean}
	 */
	competition_rights(_comp, _mask : number, _rights : number = undefined)
	{
		_comp = parseInt(_comp);
		if (!_comp) return false;

		if (typeof _rights != 'undefined') this._comp_rights[_comp] = _rights;

		return !!(this._comp_rights[_comp] & _mask);
	}

	register_dialog : any = undefined;
	register_nm : any = undefined;

	/**
	 * Registration dialog
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	register(_ev, _widget, _node)
	{
		app.ranking._register.call(app.ranking, _ev, _widget, _node);
	}
	_register(_ev, _widget, _node, _replace)
	{
		const nm = this.register_nm = _widget.getRoot().getWidgetById('nm');
		const filters = nm.getValue();

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
		const cats = _widget.getRoot().getArrayMgr('sel_options').getEntry('GrpId');
		const replace = _replace;

		const callback = function (button_id, value, confirmed) {
			const athletes = value.PerId;
			if (!athletes || !athletes.length) {
				et2_dialog.alert(this.egw.lang('You need to select one or more athletes first!', this.egw.lang('Registration')));
				return false;
			}
			const reason = this.register_dialog.template.widgetContainer.getWidgetById('prequal_reason');
			if (button_id == 'prequalify' && reason.disabled) {
				reason.set_disabled(false);
				return false;
			} else {
				reason.set_disabled(true);
			}
			const filter = this.register_nm.getValue();
			this.egw.request('ranking.EGroupware\\Ranking\\Registration\\Ui.ajax_register', [{
				WetId: replace ? replace.WetId : filter.comp,
				GrpId: replace ? replace.GrpId : value.GrpId,
				PerId: athletes,
				mode: button_id,
				reason: value.prequal_reason,
				confirmed: confirmed,
				replace: replace ? replace.PerId : undefined
			}]).then((_data) => this.register_callback(_data));

			// keep dialog open by returning false
			return false;
		}.bind(this);

		let buttons : Array<object> = [{text: this.egw.lang("Register"),id: "register", image: 'check', class: "ui-priority-primary", default: true}];
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

		const dialog = this.register_dialog = <et2_dialog>et2_createWidget("dialog", {
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: callback,
			buttons: buttons,
			title: _replace ? this.egw.lang('Replace') + ' ' + _replace.nachname + ', ' + _replace.vorname + ' (' + _replace.nation + ')' :
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
		window.setTimeout(() => {
			dialog.template.widgetContainer.getWidgetById('PerId').set_autocomplete_params({
				GrpId: _replace ? _replace.GrpId : (filters.col_filter.GrpId || cats[0].value),
				nation: _replace ? _replace.nation : filters.nation,
				sex: _replace ? _replace.sex : filters.col_filter.sex
			});
			if (_replace) dialog.template.widgetContainer.getWidgetById('PerId').set_multiple(false);
		}, 100);
	}

	/**
	 * Handle server response to registration request from either this.register or this.register_action
	 *
	 * @param {object} _data
	 */
	register_callback(_data)
	{
		const taglist = !this.register_dialog ? null : this.register_dialog.template.widgetContainer.getWidgetById('PerId');
		const athletes = taglist ? taglist.getValue() : [];

		if (_data.registered && taglist)
		{
			for (let i=0; i < _data.registered; i++) athletes.shift();
			taglist.set_value(athletes);
		}
		if (_data.question)
		{
			et2_dialog.show_dialog((_button) =>
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
					this.egw.request('ranking.EGroupware\\Ranking\\Registration\\Ui.ajax_register', [{
						WetId: _data.WetId,
						GrpId: _data.GrpId,
						PerId: _data.PerId,
						mode: _data.mode,
						replace: _data.replace,
						confirmed: true
					}]).then((_data) => this.register_callback(_data));
				}
			}, _data.question, _data.athlete, null, et2_dialog.BUTTONS_YES_NO,
			et2_dialog.QUESTION_MESSAGE, undefined, this.egw);
		}
		// if we replaced, close the dialog
		else if (_data.replace && this.register_dialog)
		{
			this.register_dialog.destroy();
		}
	}

	/**
	 * Registration dialog: category changed
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	register_cat_changed(_ev, _widget, _node)
	{
		const taglist = this.register_dialog.template.widgetContainer.getWidgetById('PerId');
		const filters = this.register_nm.getValue();

		taglist.set_autocomplete_params({
			GrpId: _widget.get_value(),
			nation: filters.nation,
			sex: filters.col_filter.sex
		});
	}

	/**
	 * Replace a registered athlete with an other one the user has to choose
	 *
	 * @param {egw_action} _action id: "replace"
	 * @param {array} _selected eg. ["ranking::1638:5:1772"]
	 */
	replace_action(_action, _selected)
	{
		const data = this.egw.dataGetUIDdata(_selected[0].id);

		this._register(null, this.et2, null, data.data);

		/*this.egw.json('ranking.EGroupware\\Ranking\\Registration\\Ui.ajax_register', [{
			WetId: data.data.WetId,
			GrpId: data.data.GrpId,
			PerId: data.data.PerId,
			mode: _action.id
		}], this.register_callback, null, false, this).sendRequest();*/
	}

	/**
	 * Execute action on registration list
	 *
	 * @param {egw_action} _action id: "delete" or "confirm"
	 * @param {array} _selected eg. ["ranking::1638:5:1772"]
	 */
	register_action(_action, _selected)
	{
		const data = this.egw.dataGetUIDdata(_selected[0].id);

		this.egw.request('ranking.EGroupware\\Ranking\\Registration\\Ui.ajax_register', [{
			WetId: data.data.WetId,
			GrpId: data.data.GrpId,
			PerId: data.data.PerId,
			mode: _action.id
		}]).then((_data) => this.register_callback(_data));
	}

	/**
	 * Registration mail
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_widget} _widget
	 * @param {DOMNode} _node
	 */
	register_mail(_ev, _widget, _node)
	{
		app.ranking._register_mail.call(app.ranking, _ev, _widget, _node);
	}
	_register_mail(_ev, _widget, _node)
	{
		const nm = _widget.getRoot().getWidgetById('nm');
		const filters = nm.getValue();

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
		const cats = _widget.getParent().getArrayMgr('sel_options').getEntry('GrpId') ||
			_widget.getRoot().getArrayMgr('sel_options').getEntry('GrpId');

		const callback = function (button_id, value, confirmed) {
			const athletes = value.PerId;
			if (!athletes || !athletes.length) {
				et2_dialog.alert(this.egw.lang('You need to select one or more athletes first!', this.egw.lang('Registration')));
				return;
			}
			const filter = this.register_nm.getValue();

			// keep dialog open by returning false
			return false;
		}.bind(this);

		const buttons = [
			{
				text: this.egw.lang("Selected participants"),
				id: "selected",
				image: 'check',
				class: "ui-priority-primary",
				default: true
			},
			{text: this.egw.lang("All participants"), id: "all", image: 'check'},
			{text: this.egw.lang("Participants without password"), id: "no_password", image: 'check'},
			{
				text: this.egw.lang("Participants without password or recent reminder"),
				id: "no_password",
				image: 'check'
			},
			{
				text: this.egw.lang("Close"), id: "close", click: function () {
					// If you override, 'this' will be the dialog DOMNode.
					// Things get more complicated.
					// Do what you like, but don't forget this line:
					jQuery(this).dialog("close");
				}
			}
		];
		const selection = nm.getSelection();
		// if no selection, remove that options
		if (!selection.all && !selection.ids.length) {
			buttons.shift();
		}
		const dialog = et2_createWidget("dialog", {
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: function (_button, _values) {
				this.egw.json('ranking.EGroupware\\Ranking\\Registration\\Ui.ajax_mail',
					[_values, _button, selection, filters], null, null, false, this).sendRequest();
			}.bind(this),
			buttons: buttons,
			title: this.egw.lang('Mail participants'),
			template: "ranking.registration.mail",
			value: {
				content: this.et2.getArrayMgr('content').getEntry('mail')
			}
		});
	}
}

app.classes.ranking = RankingApp;