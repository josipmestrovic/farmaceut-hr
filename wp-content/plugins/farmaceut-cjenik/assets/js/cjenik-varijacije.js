(function ($) {
	'use strict';

	function nadjiRoot($form) {
		var $root = $form.closest('.product, .summary').find('[data-farmaceut-cjenik-root]').first();
		if (!$root.length) {
			$root = $form.closest('.summary').find('.farmaceut-cjenik--single').first();
		}
		return $root;
	}

	function azurirajCjenik($form, html) {
		var $root = nadjiRoot($form);
		if (!$root.length) {
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

		$root.find('[data-farmaceut-cjenik-bazna]').empty();
		$root.find('[data-farmaceut-cjenik-jedinicna]').attr('hidden', true).empty();
		$root.find('[data-farmaceut-cjenik-omnibus]').attr('hidden', true).empty();
	}

	$(document.body).on('found_variation', 'form.variations_form', function (event, variation) {
		var html = variation && variation.farmaceut_cjenik_html ? variation.farmaceut_cjenik_html : '';
		azurirajCjenik($(event.target), html);
	});

	$(document.body).on('reset_data', 'form.variations_form', function (event) {
		azurirajCjenik($(event.target), '');
	});
})(jQuery);
