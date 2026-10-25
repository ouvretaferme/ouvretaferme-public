<?php
/**
 * Les routes du champ de saisie : la lecture au fil de la frappe, l'écriture sur la ligne de
 * confirmation, et l'annulation d'une saisie depuis le lien « Annuler » de sa pastille. Chaque intention
 * dit son droit (`IntentionLib::canWrite()`), et la réponse vient de son écran (`IntentionUi`).
 *
 * L'écriture relit la phrase côté serveur : le client envoie ce qui a été tapé et choisi, jamais
 * l'état de la lecture, et rien ne s'écrit que la même lecture ne rende complet.
 *
 * L'écriture et l'annulation d'une intention qui touche le planning (`IntentionLib::writesPlanning()`) ne
 * le rechargent pas : elles rendent les nœuds de la vue semaine qui changent (`PlanningLib::getChangeCollection()`),
 * et la semaine et la parcelle affichées voyagent pour cela avec la requête.
 *
 * Quand le planning est affiché, toutes deux en rendent des lignes, et ce rendu lit un état que ses
 * arguments ne portent pas : les actions principales de la structure sur `WorkSetting::$mainActions`,
 * d'où `TaskUi::getTaskPlace()` tire la pastille de départ de la culture. La page du planning les pose
 * avant sa vue (`series/page/index.p.php`, `ActionLib::saveMain()`) ; ces deux routes les posent à leur
 * tour avant de rendre ces lignes. Sans elles, l'écriture est faite et la réponse lève (cas vu le
 * 2026-09-28 sur la 7). La pastille seule ne les lit pas.
 *
 * Le drapeau `FEATURE_ENTRY` ferme le champ « Saisir… » du menu, et lui seul : la lecture et l'écriture
 * d'une case de récolte (`cell` posé) passent outre, la case étant la saisie rapide du planning, ouverte à
 * toutes les structures (V49). L'annulation passe outre aussi, elle ne défait que l'écriture dont elle
 * porte le ticket (`EntryLib::prepareCancel()`), donc une écriture déjà admise.
 */
new Page(function($data) {

	\user\ConnectionLib::checkLogged();

	$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate();

	// Le champ ne lit que les intentions que le membre peut écrire : sans aucune, rien ne se lit.
	if(\entry\EntryLib::getAllowed($data->eStructure) === []) {
		throw new NotAllowedAction();
	}

	// Les jetons posés, puis la frappe : la même phrase qu'un texte tapé d'un trait. La frappe garde
	// son espace finale, qui dit que le dernier mot est fini : elle se lit par une closure, le cast
	// `string` de `POST()` coupant les espaces (`cast()`, `framework/core/Function.php`).
	$data->text = mb_substr(POST('prefix').' '.POST('query', fn($value) => is_string($value), ''), 0, \entry\EntrySetting::TEXT_MAX);

	$data->answers = [
		'cultivation' => POST('cultivation', '?int'),
		'all' => POST('all', 'bool'),
		'task' => POST('task', '?int'),
		'addCultivation' => POST('addCultivation', '?int'),
		'addNoSeries' => POST('addNoSeries', '?int'),
		// Les jetons des lignes portent l'objet qu'ils désignent, que leur libellé ne départage pas toujours : deux
		// objets peuvent porter le même nom (`IntentionUi::getLines()`).
		'lines' => array_values(array_map('intval', array_filter(explode(',', POST('lines')), 'ctype_digit'))),
		'recap' => POST('recap', 'bool'),
		'status' => POST('status', '?string'),
		// Les jours qu'un temps ajoute à celui de la phrase, un par jeton (`TimeIntentionLib::getDays()`).
		'days' => array_values(array_filter(explode(',', POST('days')), fn(string $day) => \Filter::check('date', $day))),
		// La case d'un jour de récolte poste sa série, son jour et son couple avec chaque lecture (`EntryUi::getHarvestCell()`).
		'cell' => \entry\EntryLib::getCell($data->eStructure, POST('cell', 'array', [])),
	];

	// Une case ne lit que la récolte : sans elle, le membre n'a rien à y écrire.
	if(
		$data->answers['cell'] !== NULL and
		array_key_exists('harvest', \entry\EntryLib::getAllowed($data->eStructure)) === FALSE
	) {
		throw new NotAllowedAction();
	}

	$data->week = POST('week', '?string');
	// L'onglet Temps de travail sans choix de membre montre qui tape (`PlanningUi::getUsers()`) : le
	// navigateur ne poste alors rien.
	$data->timeUser = post_exists('timeUser') ? POST('timeUser', '?int') : $data->eUserOnline['id'];

	// La parcelle filtre la vue semaine que l'écran montre (`#entry-context`). Elle est postée par le
	// navigateur : celle d'une autre structure est refusée, avant toute lecture et toute écriture.
	$data->ePlot = \map\PlotLib::getById(POST('plot'));

	if($data->ePlot->notEmpty()) {
		$data->ePlot->validateProperty('structure', $data->eStructure);
	}

	// L'accueil dit qu'il porte le champ et quels blocs il montre (`#entry-context`) : la réponse redessine
	// ceux que l'écriture change (`HomeLib::getChange()`). Toute autre origine est celle du menu, qui ne
	// redessine rien.
	$data->onHome = (POST('origin') === 'dashboard');
	$data->homeShown = $data->onHome ? array_filter(explode(',', POST('blocks'))) : [];

})
	->post('read', function($data) {

		if($data->answers['cell'] === NULL and \entry\EntrySetting::isEnabled($data->eStructure) === FALSE) {
			throw new NotExistsAction();
		}

		$data->cElement = new Collection();
		$data->reading = \entry\EntryLib::read($data->eStructure, $data->text, $data->answers, $data->cElement);

		$data->cElement = \entry\EntryLib::findObjects($data->cElement, $data->reading['pending']);
		$data->intentions = \entry\EntryLib::findIntentions($data->eStructure, $data->reading['pending']);
		$data->completion = \entry\EntryLib::getCompletion($data->text, $data->reading, $data->cElement);

		// Après la complétion, qui se calcule sur tous les objets trouvés : la liste bornée n'en montre qu'une
		// partie.
		if($data->reading['intention'] !== NULL) {
			$data->cElement = \entry\EntryLib::getIntentions()[$data->reading['intention']]::getShown($data->eStructure, $data->reading, $data->cElement);
		}

		// La pastille de semaine d'une série prend les couleurs des actions principales : seule une intention
		// qui écrit le planning en montre.
		$planning = ($data->reading['intention'] !== NULL and \entry\EntryLib::getIntentions()[$data->reading['intention']]::writesPlanning());

		$data->cAction = $planning ? \work\ActionLib::getMainByStructure($data->eStructure) : new Collection();

		throw new ViewAction($data);

	})
	->post('doWrite', function($data) {

		if($data->answers['cell'] === NULL and \entry\EntrySetting::isEnabled($data->eStructure) === FALSE) {
			throw new NotExistsAction();
		}

		$data->reading = \entry\EntryLib::read($data->eStructure, $data->text, $data->answers);

		if($data->reading['outcome'] !== \entry\EntryLib::WRITE) {
			throw new FailAction('entry\Entry::text.incomplete');
		}

		$intention = \entry\EntryLib::getIntentions()[$data->reading['intention']];

		// La vue semaine se lit avant l'écriture : la place de la ligne est l'écart entre les deux lectures.
		$data->context = $intention::writesPlanning() ? \entry\PlanningLib::getContext($data->eStructure, $data->week, $data->ePlot, $data->reading['date']) : NULL;

		if($data->context !== NULL) {
			$ccTaskBefore = \entry\PlanningLib::getDone($data->eStructure, $data->context);
			$todoBefore = \entry\PlanningLib::getTodo($intention::getPlanningTasks($data->reading['confirmation']));
		}

		\entry\EntryLib::write($data->eStructure, $data->reading);

		// Le jour dit reste pour la phrase suivante, sauf un jour à venir, qu'une intervention à faire peut porter :
		// son jeton se relirait au plus récent jour passé de ce nom (`DateComplementUi::getToken()`).
		$data->date = (
			\entry\DateComplementLib::isDate($data->reading['complements']['date']) and
			$data->reading['complements']['date'] <= \Clock::date()
		) ? $data->reading['complements']['date'] : NULL;

		$data->homeChange = $data->onHome ? \entry\HomeLib::getChange($data->eStructure, $data->reading['intention'], $data->homeShown) : NULL;

		if($data->context !== NULL) {
			$data->cccTask = \entry\PlanningLib::getWeek($data->eStructure, $data->context);
			$data->change = \entry\PlanningLib::getChangeCollection($data->context, $ccTaskBefore, $data->cccTask, $intention::getPlanningTasks($data->reading['confirmation']), $todoBefore);
			$data->eUserTime = \entry\PlanningLib::getUserTime($data->eStructure, $data->context, $data->timeUser, ($data->reading['confirmation']['minutes'] === NULL) ? 0.0 : \entry\DurationComplementLib::getHours($data->reading['confirmation']['minutes']));
			\work\ActionLib::getMainByStructure($data->eStructure);
		}

		throw new ViewAction($data);

	})
	->post('doCancel', function($data) {

		// La pastille poste l'intention qui annule et le jeton de son ticket (`IntentionLib::afterCommit()`).
		$data->intention = POST('intention');

		if(array_key_exists($data->intention, \entry\EntryLib::getAllowed($data->eStructure)) === FALSE) {
			throw new NotAllowedAction();
		}

		$data->cancellation = \entry\EntryLib::prepareCancel($data->eStructure, $data->intention, POST('ticket', '?string'));

		$intention = \entry\EntryLib::getIntentions()[$data->intention];

		$data->context = $intention::writesPlanning() ? \entry\PlanningLib::getContext($data->eStructure, $data->week, $data->ePlot, $data->cancellation['date']) : NULL;

		if($data->context !== NULL) {
			$ccTaskBefore = \entry\PlanningLib::getDone($data->eStructure, $data->context);
			$todoBefore = \entry\PlanningLib::getTodo($intention::getPlanningTasks($data->cancellation));
		}

		\entry\EntryLib::cancel($data->eStructure, $data->intention, $data->cancellation);

		$data->homeChange = $data->onHome ? \entry\HomeLib::getChange($data->eStructure, $data->intention, $data->homeShown) : NULL;

		if($data->context !== NULL) {
			$data->cccTask = \entry\PlanningLib::getWeek($data->eStructure, $data->context);
			$data->change = \entry\PlanningLib::getChangeCollection($data->context, $ccTaskBefore, $data->cccTask, $intention::getPlanningTasks($data->cancellation), $todoBefore);
			$data->eUserTime = \entry\PlanningLib::getUserTime($data->eStructure, $data->context, $data->timeUser, $data->cancellation['time']);
			\work\ActionLib::getMainByStructure($data->eStructure);
		}

		throw new ViewAction($data);

	});
?>
