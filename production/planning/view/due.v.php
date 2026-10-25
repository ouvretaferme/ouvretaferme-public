<?php
// La réponse d'un geste fait depuis un bloc dû du planning (`DueUi::getOrigin()`), que l'action de la
// source rend quand `POST('origin')` le dit, avec l'origine (`origin`), la semaine postée (`week`), ce
// qui est dû cette semaine (`dues`) et son message (`success`) : seuls les blocs dus se remplacent,
// dans la forme d'où le geste est parti. Quand il n'en reste aucun, la colonne peut redevenir vide, et
// son état vide se décide avec les interventions, que cette réponse ne connait pas : le planning se
// recharge alors en entier.
//
// `refresh` répond à un geste fait dans un panneau, qu'il ferme en reculant d'un pas dans
// l'historique. `skip` et `postpone` répondent à la croix et au décalage d'une rangée
// (`DueUi::getSkip()`, `getPostpone()`), qui n'ouvrent aucun panneau : reculer ramènerait la page
// d'avant le planning.
$refresh = function(AjaxTemplate $t, \structure\Structure $eStructure, string $origin, string $week, array $dues, array $success, bool $panel) {

	$t->js()->success(...$success);

	if($dues === []) {
		$t->ajaxReload();
	} else {

		if($panel) {
			$t->js()->moveHistory(-1);
		}

		$uiDue = new \planning\DueUi();

		$t->qs(\planning\DueUi::getTarget())->outerHtml(match($origin) {
			\planning\DueLib::ORIGIN_BLOCK => $uiDue->getWeek($eStructure, $week, $dues),
			\planning\DueLib::ORIGIN_ZONE => $uiDue->getZones($eStructure, $week, $dues),
		});

	}

};

new JsonView('refresh', fn($data, AjaxTemplate $t) => $refresh($t, $data->eStructure, $data->origin, $data->week, $data->dues, $data->success, TRUE));
new JsonView('skip', fn($data, AjaxTemplate $t) => $refresh($t, $data->eStructure, $data->origin, $data->week, $data->dues, $data->success, FALSE));
new JsonView('postpone', fn($data, AjaxTemplate $t) => $refresh($t, $data->eStructure, $data->origin, $data->week, $data->dues, $data->success, FALSE));
?>
