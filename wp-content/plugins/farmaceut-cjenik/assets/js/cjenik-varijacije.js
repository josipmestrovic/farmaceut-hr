(function ($) {
	'use strict';

	function azurirajCjenik($form, html) {
		var $root = $form.closest('.product, .summary').find('[data-farmaceut-cjenik-root]');
		if (!$root.length) {
			$root = $form.closest('.summary').find('.farmaceut-cjenik--single');
		}
		if (!$root.length) {
			return;
		}
		if (typeof html === 'string' && html.length) {
			$root.replaceWith(html);
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
