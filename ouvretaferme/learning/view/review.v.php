<?php
new JsonView('more', function($data, AjaxTemplate $t) {

	$ui = new \learning\ReviewUi();

	// ⚠️ `insertAdjacentHtml` n'accepte qu'un nœud racine (`Element::renderAdjacentHTML()`, lime.js) et
	// lève sur plusieurs : une instruction par avis, dans l'ordre du fil.
	foreach($data->cReview as $eReview) {
		$t->qs('#learning-reviews-thread')->insertAdjacentHtml('beforeend', $ui->getRow($data->eCourse, $eReview));
	}

	$t->qs('#learning-reviews-more')->outerHtml($ui->getMore($data->eCourse, $data->offset + $data->cReview->count(), $data->more));

});

// Une lecture ne fait que baisser le compte des nouveaux : seul le compteur se redessine, et « Tout
// marquer comme lu » s'en va quand il n'y a plus rien à marquer.
new JsonView('doRead', function($data, AjaxTemplate $t) {

	$t->qs('#learning-review-'.$data->eReview['id'])->outerHtml(new \learning\ReviewUi()->getInboxRow($data->eReview));
	$t->qs('#learning-review-inbox-counter')->outerHtml(\learning\ReviewUi::getInboxCounter($data->reviewsNew));

	if($data->reviewsNew === 0) {
		$t->qs('#learning-review-inbox-read-all')->remove();
	}

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\ReviewUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\ReviewUi()->update($data->e);

});

new AdaptativeView('answer', function($data, PanelTemplate $t) {

	return new \learning\ReviewUi()->answer($data->e);

});

new AdaptativeView('report', function($data, PanelTemplate $t) {

	return new \learning\ReviewUi()->report($data->e);

});
?>
