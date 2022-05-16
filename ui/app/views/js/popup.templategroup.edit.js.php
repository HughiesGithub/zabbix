<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

window.templategroup_edit_popup = {
	groupid: null,
	name: null,

	overlay: null,
	dialogue: null,
	form: null,
	footer: null,

	init({popup_url, groupid, name}) {
		history.replaceState({}, '', popup_url);

		this.groupid = groupid;
		this.name = name;

		this.overlay = overlays_stack.getById('templategroup_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.footer = this.overlay.$dialogue.$footer[0];
	},

	submit() {
		const fields = getFormFields(this.form);

		if (this.groupid !== null) {
			fields.groupid = this.groupid;
		}

		fields.name = fields.name.trim();

		for (const el of this.form.parentNode.children) {
			if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
				el.parentNode.removeChild(el);
			}
		}

		this.overlay.setLoading();

		const update_url = new Curl('zabbix.php', false);
		update_url.setArgument('action', 'templategroup.update');
		const create_url = new Curl('zabbix.php', false);
		create_url.setArgument('action', 'templategroup.create');
		const curl = this.groupid !== null ? update_url : create_url;

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	},

	cancel() {
		overlayDialogueDestroy(this.overlay.dialogueid);
	},

	clone() {
		this.groupid = null;

		PopUp('popup.templategroup.edit', {name: this.name}, {
			dialogueid: 'templategroup_edit',
			dialogue_class: 'modal-popup-static',
			prevent_navigation: true
		});
	},

	delete() {
		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'templategroup.delete');
		curl.addSID();

		this._post(curl.getUrl(), {groupids: [this.groupid]}, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.delete', {detail: response.success}));
		});
	},

	_post(url, data, success_callback) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
}
