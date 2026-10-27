/*!
 * Individualize Scholar: small helper script for the theme (GPL-3.0-or-later).
 * - opens and closes the abstract under listed articles (with ARIA state),
 * - unfolds and folds the rest of the current issue on the home page
 *   ("Read more" / "Show less").
 * Without JavaScript "Show Abstract" opens the article page and "Read more"
 * the issue page. Each feature is wrapped so a failure never stops the other.
 */
(function () {
	'use strict';

	var d = document;

	function safe(fn) {
		return function () {
			try {
				return fn.apply(null, arguments);
			} catch (e) {
				return false;
			}
		};
	}

	/** Run toggle(link) for clicks and the space bar on links matching selector */
	function onActivate(selector, toggle) {
		d.addEventListener('click', safe(function (e) {
			var link = e.target.closest && e.target.closest(selector);
			if (link && toggle(link)) {
				e.preventDefault();
			}
		}));
		// role="button" links also react to the space bar
		d.addEventListener('keydown', safe(function (e) {
			var link = e.target.closest && e.target.closest(selector);
			if (link && (e.key === ' ' || e.key === 'Spacebar') && toggle(link)) {
				e.preventDefault();
			}
		}));
	}

	function toggleAbstract(link) {
		var id = link.getAttribute('aria-controls');
		var panel = id ? d.getElementById(id) : null;
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

	function toggleIssue(link) {
		var toc = link.closest('.issue-toc');
		if (!toc) {
			return false;
		}
		var unfold = toc.classList.contains('ms-collapsed');
		Array.prototype.forEach.call(toc.querySelectorAll('.ms-more'), function (el) {
			el.hidden = !unfold;
		});
		toc.classList.toggle('ms-collapsed', !unfold);
		link.setAttribute('aria-expanded', unfold ? 'true' : 'false');
		link.textContent = link.getAttribute(unfold ? 'data-less' : 'data-more');
		if (!unfold) {
			// folded again: keep the button on screen
			link.scrollIntoView({ block: 'nearest' });
		}
		return true;
	}

	onActivate('a.ms-show-abstract', toggleAbstract);
	onActivate('a.ms-readmore__button', toggleIssue);
})();
