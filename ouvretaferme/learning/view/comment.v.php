<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\CommentUi()->create($data->e, $data->origin);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\CommentUi()->update($data->e);

});

// Depuis la boite, une réponse et une lecture ne font que baisser le compte des nouveaux : seul le compteur se
// redessine, et « Tout marquer comme lu » s'en va quand il n'y a plus rien à marquer.
new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->qs('#learning-comment-'.$data->eComment['id'])->outerHtml(new \learning\CommentUi()->getInboxRow($data->eComment));
	$t->qs('#learning-inbox-counter')->outerHtml(\learning\CommentUi::getInboxCounter($data->commentsNew));

	if($data->commentsNew === 0) {
		$t->qs('#learning-inbox-read-all')->remove();
	}

	$t->js()->success('learning', 'Comment::answered');
	$t->js()->closePanel('#panel-learning-comment-answer');

});

new JsonView('doRead', function($data, AjaxTemplate $t) {

	$t->qs('#learning-comment-'.$data->eComment['id'])->outerHtml(new \learning\CommentUi()->getInboxRow($data->eComment));
	$t->qs('#learning-inbox-counter')->outerHtml(\learning\CommentUi::getInboxCounter($data->commentsNew));

	if($data->commentsNew === 0) {
		$t->qs('#learning-inbox-read-all')->remove();
	}

});
?>
