/*!
 * Generated from js/manuscriptScholarAdmin.js by scripts/sync-individualize-scholar.sh: edit that file, not this one.
 * Individualize Scholar: "Select all" buttons for the OJS back end (GPL-3.0-or-later).
 * Adds "Select all" and "Clear selection" above
 * - the article list of the Crossref export page (#submissionsListGridContainer), and
 * - the tasks notification table (table[id*="tasknotification"]).
 * It also shows the size of each file under its type ("Article Text") in the
 * file lists of the workflow and author dashboard pages; the sizes come from
 * the #ms-file-sizes data block the theme adds to those pages.
 * Loaded on back-end pages only; the labels come from data attributes of the
 * script tag. Nothing is added to a page that has neither list, and the older
 * stand-alone script (#btnSelectAllCrossref, #btnSelectAllTasks) is respected.
 */
(function () {
	'use strict';

	var d = document;
	var script = d.querySelector('script[src*="individualizeScholarAdmin.js"]');
	var labels = {
		all: (script && script.getAttribute('data-select-all')) || 'Select all',
		none: (script && script.getAttribute('data-clear-all')) || 'Clear selection'
	};

	var targets = [
		{ id: 'crossref', selector: '#submissionsListGridContainer', legacy: '#btnSelectAllCrossref' },
		{ id: 'tasks', selector: 'table[id*="tasknotification"]', legacy: '#btnSelectAllTasks' }
	];

	function text(value) {
		var span = d.createElement('span');
		span.appendChild(d.createTextNode(value));
		return span;
	}

	function button(kind, target, label) {
		var b = d.createElement('button');
		b.type = 'button';
		b.className = 'pkp_button ms-select-' + kind;
		b.setAttribute('data-ms-target', target.id);
		b.appendChild(text(label));
		if (kind === 'none') {
			b.style.marginLeft = '5px';
		}
		return b;
	}

	function bar(target) {
		var wrap = d.createElement('div');
		wrap.className = 'ms-select-bar';
		wrap.setAttribute('data-ms-target', target.id);
		wrap.style.padding = '8px 0';
		wrap.appendChild(button('all', target, labels.all));
		wrap.appendChild(d.createTextNode(' '));
		wrap.appendChild(button('none', target, labels.none));
		return wrap;
	}

	var sizes = null;
	function fileSizes() {
		if (sizes === null) {
			sizes = {};
			var data = d.getElementById('ms-file-sizes');
			if (data) {
				try {
					sizes = JSON.parse(data.textContent || '{}') || {};
				} catch (e) {
					sizes = {};
				}
			}
		}
		return sizes;
	}

	function formatSize(bytes) {
		if (bytes < 1024) {
			return bytes + ' B';
		}
		if (bytes < 1024 * 1024) {
			return Math.max(1, Math.round(bytes / 1024)) + ' KB';
		}
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	var LARGE = 5 * 1024 * 1024;

	function showSizes() {
		var map = fileSizes();
		Array.prototype.forEach.call(d.querySelectorAll('a[href*="submissionFileId="]'), function (link) {
			var match = /submissionFileId=(\d+)/.exec(link.getAttribute('href') || '');
			if (!match || !Object.prototype.hasOwnProperty.call(map, match[1])) {
				return;
			}
			var row = link;
			while (row && row.nodeName !== 'TR') {
				row = row.parentNode;
			}
			if (!row || !row.cells || !row.cells.length || row.querySelector('.ms-file-size')) {
				return;
			}
			var bytes = map[match[1]];
			var size = d.createElement('div');
			size.className = 'ms-file-size';
			size.appendChild(d.createTextNode(formatSize(bytes)));
			size.style.fontSize = '0.85em';
			size.style.marginTop = '2px';
			size.style.color = bytes >= LARGE ? '#b3261e' : '#6b6b6b';
			if (bytes >= LARGE) {
				size.style.fontWeight = '700';
			}
			row.cells[row.cells.length - 1].appendChild(size);
		});
	}

	function sync() {
		showSizes();
		targets.forEach(function (target) {
			var list = d.querySelector(target.selector);
			var existing = d.querySelector('.ms-select-bar[data-ms-target="' + target.id + '"]');
			if (!list) {
				if (existing && existing.parentNode) {
					existing.parentNode.removeChild(existing);
				}
				return;
			}
			if (existing || d.querySelector(target.legacy) || !list.parentNode) {
				return;
			}
			list.parentNode.insertBefore(bar(target), list);
		});
	}

	function setAll(target, checked) {
		var list = d.querySelector(target.selector);
		if (!list) {
			return;
		}
		Array.prototype.forEach.call(list.querySelectorAll('input[type="checkbox"]'), function (box) {
			if (box.disabled || box.checked === checked) {
				return;
			}
			box.checked = checked;
			var evt;
			if (typeof Event === 'function') {
				evt = new Event('change', { bubbles: true });
			} else {
				evt = d.createEvent('Event');
				evt.initEvent('change', true, false);
			}
			box.dispatchEvent(evt);
		});
	}

	d.addEventListener('click', function (event) {
		var node = event.target;
		while (node && node !== d) {
			if (node.getAttribute && node.getAttribute('data-ms-target') && /(^|\s)ms-select-(all|none)(\s|$)/.test(node.className)) {
				var id = node.getAttribute('data-ms-target');
				var checked = /(^|\s)ms-select-all(\s|$)/.test(node.className);
				targets.forEach(function (target) {
					if (target.id === id) {
						setAll(target, checked);
					}
				});
				return;
			}
			node = node.parentNode;
		}
	});

	var timer = null;
	function schedule() {
		if (timer) {
			return;
		}
		// The back end rebuilds its pages constantly; look once per burst
		timer = setTimeout(function () {
			timer = null;
			try {
				sync();
			} catch (e) {
			}
		}, 150);
	}

	if (typeof MutationObserver === 'function') {
		new MutationObserver(schedule).observe(d.body || d.documentElement, { childList: true, subtree: true });
	}
	schedule();
})();
