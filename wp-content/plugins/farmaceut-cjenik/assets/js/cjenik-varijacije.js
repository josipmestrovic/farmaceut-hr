(function ($) {
	'use strict';

	function nadjiRoot($form) {
		var $root = $form.closest('.product, .summary').find('[data-farmaceut-cjenik-root]').first();
		if (!$root.length) {
			$root = $form.closest('.summary').find('.farmaceut-cjenik--single').first();
		}
		return $root;
	}

	function zapamtiParentHtml($root) {
		if ($root.data('farmaceutCjenikParentHtml')) {
			return;
		}
		$root.data('farmaceutCjenikParentHtml', {
			className: $root.attr('class') || '',
			html: $root.html()
		});
	}

	function vratiParentHtml($root) {
		var parent = $root.data('farmaceutCjenikParentHtml');
		if (!parent) {
			return false;
		}
		$root.attr('class', parent.className);
		$root.attr('data-farmaceut-cjenik-root', '');
		$root.html(parent.html);
		return true;
	}

	function isprazniCjenik($root) {
		$root.attr('class', 'farmaceut-cjenik farmaceut-cjenik--varijacija');
		$root.attr('data-farmaceut-cjenik-root', '');
		$root.html('');
	}

	/**
	 * @param {jQuery} $form
	 * @param {string} html
	 * @param {{reset?: boolean}} opcije reset=true → parent raspon; inače varijacija (prazno = prazan blok)
	 */
	function azurirajCjenik($form, html, opcije) {
		var $root = nadjiRoot($form);
		if (!$root.length) {
			return;
		}

		zapamtiParentHtml($root);
		opcije = opcije || {};

		if (opcije.reset) {
			if (!vratiParentHtml($root)) {
				isprazniCjenik($root);
			}
			return;
		}

		if (typeof html === 'string' && html.length) {
			var $tmp = $('<div>').append($.parseHTML(html));
			var $novi = $tmp.children('[data-farmaceut-cjenik-root], .farmaceut-cjenik').first();
			if ($novi.length) {
				$root.attr('class', $novi.attr('class'));
				$root.attr('data-farmaceut-cjenik-root', '');
				$root.html($novi.html());
			} else {
				$root.html(html);
			}
			return;
		}

		// Odabrana varijacija bez vlastitog cjenik HTML-a — ne vraćaj parent raspon.
		isprazniCjenik($root);
	}

	$(document.body).on('found_variation', 'form.variations_form', function (event, variation) {
		var html = variation && variation.farmaceut_cjenik_html ? variation.farmaceut_cjenik_html : '';
		azurirajCjenik($(event.target), html, { reset: false });
	});

	$(document.body).on('reset_data', 'form.variations_form', function (event) {
		azurirajCjenik($(event.target), '', { reset: true });
	});
})(jQuery);
