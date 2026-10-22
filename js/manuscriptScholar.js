/*!
 * Manuscript Scholar: small helper script for the theme (GPL-3.0-or-later).
 * - opens and closes the abstract under article lists (with ARIA state),
 * - marks the sidebar menu link of the current page,
 * - lines up the footer and the home page cards with the page content,
 * - puts the visible part of the header image in the middle of the header,
 * - unfolds and folds the rest of the current issue on the home page ("Read
 *   more" / "Show less") and keeps the button a fixed distance below the last
 *   visible article,
 * - optionally raises all text sizes (see --ms-font-bump in the stylesheet).
 * Every feature is wrapped so that a failure in one never stops the others.
 */
(function () {
	'use strict';

	var d = document;
	var timers = {};

	function q(selector, root) {
		return (root || d).querySelector(selector);
	}

	function all(selector, root) {
		return Array.prototype.slice.call((root || d).querySelectorAll(selector));
	}

	function num(value) {
		return parseFloat(value) || 0;
	}

	function safe(fn) {
		return function () {
			try {
				fn.apply(null, arguments);
			} catch (e) {}
		};
	}

	/** Run fn after the window stops resizing (80 ms) */
	function onResize(name, fn) {
		addEventListener('resize', function () {
			clearTimeout(timers[name]);
			timers[name] = setTimeout(safe(fn), 80);
		});
	}

	/* ---- Abstract under article lists ---------------------------------- */

	function toggleAbstract(link) {
		var panel = link.getAttribute('aria-controls') ? d.getElementById(link.getAttribute('aria-controls')) : null;
		if (!panel) {
			var next = link.parentNode && link.parentNode.nextElementSibling;
			panel = next && next.className.indexOf('ms-abstract') > -1 ? next : null;
		}
		if (!panel) {
			return false;
		}
		var open = panel.hidden;
		panel.hidden = !open;
		link.textContent = link.getAttribute(open ? 'data-hide' : 'data-show');
		link.setAttribute('aria-expanded', open ? 'true' : 'false');
		link.classList.toggle('is-open', open);
		return true;
	}

	function abstractToggles() {
		d.addEventListener('click', safe(function (e) {
			var link = e.target.closest && e.target.closest('a.ms-show-abstract');
			if (link && toggleAbstract(link)) {
				e.preventDefault();
			}
		}));
		// role="button" links also react to the space bar
		d.addEventListener('keydown', safe(function (e) {
			var link = e.target.closest && e.target.closest('a.ms-show-abstract');
			if (link && (e.key === ' ' || e.key === 'Spacebar') && toggleAbstract(link)) {
				e.preventDefault();
			}
		}));
	}

	/* ---- Sidebar menu: current page ------------------------------------ */

	function activeMenuLink() {
		var path = location.pathname.replace(/\/$/, '');
		all('ul.ms-menu a').forEach(function (a) {
			if (a.hostname === location.hostname && a.pathname.replace(/\/$/, '') === path) {
				a.className += ' is-active';
				a.setAttribute('aria-current', 'page');
			}
		});
	}

	/* ---- Optional text size bump (off by default) ----------------------- */

	function fontBump() {
		var cs = getComputedStyle(d.documentElement);
		var bump = num(cs.getPropertyValue('--ms-font-bump'));
		var titleBump = num(cs.getPropertyValue('--ms-title-bump'));
		if ((!bump && !titleBump) || !d.body) {
			return;
		}
		var titles = '.obj_article_summary .title, .obj_article_summary .title a';
		var nodes = [d.body].concat(all('*', d.body));
		var sizes = nodes.map(function (el) {
			return num(getComputedStyle(el).fontSize);
		});
		nodes.forEach(function (el, i) {
			if (sizes[i] && !/^(script|style|noscript|link|meta|svg|path|g|use)$/i.test(el.tagName)) {
				var extra = titleBump && el.matches && el.matches(titles) ? titleBump : 0;
				el.style.setProperty('font-size', (sizes[i] + bump + extra) + 'px', 'important');
			}
		});
	}

	/* ---- Footer and home cards line up with the page content ------------ */

	function align() {
		var targets = ['.pkp_structure_footer', '.ms-highlights'];
		var props = ['margin-left', 'margin-right', 'width', 'padding-left', 'padding-right', 'box-sizing'];
		var main = q('.pkp_structure_main');
		var side = q('.pkp_structure_sidebar');
		targets.forEach(function (selector) {
			var el = q(selector);
			if (!el) {
				return;
			}
			props.forEach(function (p) {
				el.style.removeProperty(p);
			});
			if (!main || innerWidth < 768 || getComputedStyle(d.body).direction === 'rtl') {
				return;
			}
			var cm = getComputedStyle(main);
			var left = main.getBoundingClientRect().left + num(cm.paddingLeft) + num(cm.borderLeftWidth);
			var right = side
				? side.getBoundingClientRect().right - num(getComputedStyle(side).paddingRight)
				: main.getBoundingClientRect().right;
			var parent = el.parentNode;
			var cp = getComputedStyle(parent);
			var parentLeft = parent.getBoundingClientRect().left + num(cp.paddingLeft) + num(cp.borderLeftWidth);
			el.style.setProperty('box-sizing', 'border-box', 'important');
			el.style.setProperty('width', (right - left) + 'px', 'important');
			el.style.setProperty('margin-left', (left - parentLeft) + 'px', 'important');
			el.style.setProperty('margin-right', '0', 'important');
			el.style.setProperty('padding-left', '0', 'important');
			el.style.setProperty('padding-right', '0', 'important');
		});
	}

	/* ---- Header image: visible logo in the middle ---------------------- */

	/*
	 * The image fills the white page from edge to edge. Its visible part (the
	 * pixels that are not transparent) is moved so it sits in the middle of
	 * the header. The visible part is never wider than the image, so nothing
	 * of it is cut; what moves out of the header is transparent.
	 */
	function logo() {
		var img = q('.pkp_site_name .is_img img');
		var centre = null;
		if (!img) {
			return;
		}
		function shift() {
			img.style.removeProperty('margin-left');
			if (centre === null || innerWidth < 992) {
				return;
			}
			var move = (0.5 - centre) * img.getBoundingClientRect().width;
			if (Math.abs(move) >= 1) {
				img.style.setProperty('margin-left', move + 'px', 'important');
			}
		}
		function measure() {
			try {
				var w = img.naturalWidth;
				var h = img.naturalHeight;
				if (!w || !h) {
					return;
				}
				var canvas = d.createElement('canvas');
				canvas.width = w;
				canvas.height = h;
				var ctx = canvas.getContext('2d');
				ctx.drawImage(img, 0, 0);
				var data = ctx.getImageData(0, 0, w, h).data;
				var first = w;
				var last = -1;
				for (var y = 0; y < h; y += 2) {
					for (var x = 0; x < first; x++) {
						if (data[(y * w + x) * 4 + 3] > 40) {
							first = x;
							break;
						}
					}
					for (var z = w - 1; z > last; z--) {
						if (data[(y * w + z) * 4 + 3] > 40) {
							last = z;
							break;
						}
					}
				}
				if (last >= first) {
					centre = (first + last + 1) / 2 / w;
					shift();
				}
			} catch (e) {}
		}
		if (img.complete) {
			measure();
		} else {
			img.addEventListener('load', measure);
		}
		onResize('logo', shift);
	}

	/* ---- "Read more": unfold the rest of the current issue in place ----- */

	function readMore() {
		var gap = 8;

		/** A fixed distance between the last visible article and the button */
		function place() {
			var button = q('.current_issue .ms-readmore');
			var items = all('.current_issue .obj_article_summary').filter(function (el) {
				return el.offsetParent !== null;
			});
			var last = items[items.length - 1];
			if (!button || !last) {
				return;
			}
			button.style.removeProperty('margin-top');
			var parts = all('.ms-scholar, .ms-abstract:not([hidden])', last);
			var anchor = parts.length ? parts[parts.length - 1] : last;
			var margin = num(getComputedStyle(button).marginTop);
			var distance = button.getBoundingClientRect().top - anchor.getBoundingClientRect().bottom;
			button.style.setProperty('margin-top', (margin + gap - distance) + 'px', 'important');
		}

		function toggle(link) {
			var block = link.closest('.current_issue');
			if (!block) {
				return false;
			}
			var unfold = block.classList.contains('ms-collapsed');
			all('.ms-more', block).forEach(function (el) {
				el.hidden = !unfold;
			});
			block.classList.toggle('ms-collapsed', !unfold);
			link.setAttribute('aria-expanded', unfold ? 'true' : 'false');
			link.textContent = link.getAttribute(unfold ? 'data-less' : 'data-more');
			place();
			if (!unfold) {
				// folded again: keep the button on screen
				link.scrollIntoView({ block: 'nearest' });
			}
			return true;
		}

		if (!q('.current_issue .ms-readmore')) {
			return;
		}
		place();
		addEventListener('load', safe(place));
		onResize('readmore', place);
		d.addEventListener('click', safe(function (e) {
			var link = e.target.closest && e.target.closest('a.ms-readmore__button');
			if (link && toggle(link)) {
				e.preventDefault();
			} else if (e.target.closest && e.target.closest('[data-show]')) {
				setTimeout(safe(place), 0);
			}
		}));
		d.addEventListener('keydown', safe(function (e) {
			var link = e.target.closest && e.target.closest('a.ms-readmore__button');
			if (link && (e.key === ' ' || e.key === 'Spacebar') && toggle(link)) {
				e.preventDefault();
			} else if (e.target.closest && e.target.closest('[data-show]')) {
				setTimeout(safe(place), 0);
			}
		}));
	}

	function init() {
		[abstractToggles, activeMenuLink, fontBump, align, logo, readMore].forEach(function (feature) {
			safe(feature)();
		});
		addEventListener('load', safe(align));
		onResize('align', align);
	}

	if (d.readyState === 'loading') {
		d.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
