<?php
/**
 * Un attribut `NULL` se retire de sa case (`CompositionUi::getGridCellChanges()`).
 */
new JsonView('doReset', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	$reset = $uiComposition->getReset($data->eCompositionPlan, $data->week, $data->speciesIds);

	foreach($reset['outer'] as $selector => $html) {
		$t->qs($selector)->outerHtml($html);
	}

	foreach($reset['inner'] as $selector => $html) {
		$t->qs($selector)->innerHtml($html);
	}

	foreach($reset['attributes'] as $selector => $attributes) {
		foreach($attributes as $name => $value) {
			if($value === NULL) {
				$t->qs($selector)->removeAttribute($name);
			} else {
				$t->qs($selector)->setAttribute($name, $value);
			}
		}
	}

});
?>
