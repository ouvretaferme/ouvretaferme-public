<?php
namespace planning;

/**
 * La saisie d'une durée (`DurationUi`, R-Saisie) : les ronds de « Journée », le champ par pas
 * (`getStepped()`, R-Retours-0410, point 22) et sa fenêtre, sur tous les écrans qui saisissent le temps d'une
 * intervention (R-Retours-0810, 212), et ce qu'un geste de la fiche réémet pour garder la ligne à sa place
 * (`dayWeek`, R-Place), refusé quand il est forgé. « Journée » et la fenêtre des pas n'envoient rien avant
 * « Enregistrer » (R-Retours-0810, retour Enregistrer), ce que `PlanningDuration` et `PlanningDurationStep`
 * éprouvent ici sur des nœuds simulés. Que la ligne gardée soit celle d'un rechargement est éprouvé par
 * `FingerprintTest` (`testTimeMove`).
 *
 * Lancement : dev/test package=planning file=Duration
 */
class DurationTest extends HarvestFixtureTest {

	/**
	 * La durée s'écrit en heures et minutes, les minutes sur deux chiffres.
	 */
	public function testFormat(): void {

		$this->assertEquals('9h15', DurationUi::formatDuration(9.25), 'la durée écrite');
		$this->assertEquals('0h05', DurationUi::formatDuration(5 / 60), 'les minutes écrites sur deux chiffres');

	}

	/**
	 * La carte du Jour n'a plus de ronds dont la place se réémet (R-Retours-0810, 212) : le geste écrit le temps
	 * sans lire de premier rond, même hors de ceux qu'on pouvait montrer.
	 */
	public function testHoursFromIgnored(): void {

		$monday = week_date_day(\Clock::week(), 1);

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);

		[, , $action] = $this->callDay('doTime', ['task' => $eTaskDone['id'], 'date' => $monday, 'time' => '1', 'hoursFrom' => '-1']);

		$this->assertTrue($action instanceof \ViewAction, 'le Jour lit encore un premier rond : '.implode(', ', $this->getFails($action)));
		$this->assertEquals(1.0, \series\TimesheetLib::getTimeByDate($eTaskDone, \user\ConnectionLib::getOnline(), $monday), 'le Jour n\'écrit pas le temps de la carte');

	}

	/**
	 * Le champ par pas (`DurationUi::getStepped()`) : la clé de l'écran sur le conteneur, qui ne porte ni la
	 * classe ni les attributs de la saisie derrière une tuile, que `PlanningDuration` prendrait pour la
	 * sienne ; les six pas dans l'ordre, en minutes, -1h et +1h compris (I8 de R-Retours-0410-soir, retour 189) ;
	 * la durée dans les deux cases de la durée lue (retour 188).
	 */
	public function testStepped(): void {

		$cases = [
			'1h15' => [1.25, ['1', '15']],
			'zéro' => [0.0, ['0', '00']],
		];

		foreach($cases as $case => [$time, $value]) {

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new DurationUi()->getStepped('task-1-2026-10-05', $time).'</body></html>', LIBXML_NOERROR);

			$field = $document->querySelector('body > .planning-duration-step');

			if($this->assertTrue($field !== NULL, $case.' : le champ par pas manque') === FALSE) {
				continue;
			}

			$this->assertEquals('task-1-2026-10-05', $field->getAttribute('data-duration'), $case.' : le champ ne porte pas sa clé');
			$this->assertTrue($document->querySelector('.planning-duration, [data-hours-from], [data-time]') === NULL, $case.' : le champ porte la forme de la saisie derrière une tuile');

			$steps = array_map(fn($node) => $node->getAttribute('data-planning-duration-step'), iterator_to_array($field->querySelectorAll('[data-planning-duration-step]')));
			$this->assertEquals(['-60', '-15', '-5', '5', '15', '60'], $steps, $case.' : le champ ne porte pas -1h, -15, -5, +5, +15 et +1h dans cet ordre');

			$inputs = $field->querySelectorAll('.planning-duration-step-glue > .planning-duration-step-value .timesheet-time-field-group > input');
			$this->assertEquals(['timeAddHour', 'timeAddMinute'], array_map(fn($node) => $node->getAttribute('name'), iterator_to_array($inputs)), $case.' : la durée ne se tape pas dans les deux cases de la durée lue');
			$this->assertEquals($value, array_map(fn($node) => $node->getAttribute('value'), iterator_to_array($inputs)), $case.' : les cases ne disent pas la durée');

		}

	}

	/**
	 * Les cases de la durée tapée ne partent pas avec le formulaire qui loge la fenêtre : la durée part dans le
	 * champ caché de son écran, et aucune route ne lit `timeAdd`, `timeAddHour` ni `timeAddMinute` (planche de P,
	 * point 2). Le même champ de la fiche de temps, qui les poste, reste rendu sans l'attribut.
	 */
	public function testSteppedNotPosted(): void {

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body><form>'.new DurationUi()->getStepped('task-1-2026-10-05', 1.25).'</form></body></html>', LIBXML_NOERROR);

		$names = array_map(fn($node) => $node->getAttribute('name').($node->hasAttribute('form') ? '='.$node->getAttribute('form') : ''), iterator_to_array($document->querySelectorAll('.planning-duration-step input')));
		$this->assertEquals(['timeAdd=', 'timeAddHour=', 'timeAddMinute='], $names, 'une case de la durée tapée part avec le formulaire');

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new \util\FormUi()->dynamicField(new \series\Timesheet(['timeAdd' => 1.25]), 'timeAdd').'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($document->querySelector('.timesheet-time-field input[form]') === NULL, 'le champ de la fiche de temps ne poste plus sa durée');

	}

	/**
	 * Le jour réémis est celui de la réponse ; un jour hors de la semaine est forgé.
	 */
	public function testPosition(): void {

		$friday = week_date_day(\Clock::week(), 5);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		[$data, , $action] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '1', 'dayWeek' => $friday]);

		if($this->assertTrue($action instanceof \ViewAction, 'une place réémise est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals($friday, $data->eTaskLine['dayWeek'], 'la ligne ne garde pas le jour réémis');

		[, , $action] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '1', 'dayWeek' => date('Y-m-d', strtotime(week_date_day(\Clock::week(), 1).' - 1 day'))]);
		$this->assertTrue($action instanceof \NotExpectedAction, 'un jour réémis hors de la semaine est accepté');

	}

	/**
	 * Le temps d'une intervention se saisit dans la fenêtre des pas sur tous les écrans qui la saisissent
	 * (R-Retours-0810, 212, « de manière générale sur toutes les interventions ») : la bulle de la Semaine,
	 * sans tuile, « Nouvelle intervention » pour le temps prévu, et l'entretien « Fait »,
	 * qui crée une intervention. La carte du Jour, la saisie par lot et la grille de la Semaine sont éprouvées
	 * par leurs écrans (`TaskDayTest`, `TaskBatchTest`, `TaskWeekGridTest`). « Journée » garde ses ronds
	 * (`testWorkingHours`).
	 */
	public function testInterventionScreens(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$eUser = $this->getMember();

		$cases = [
			'la bulle de la Semaine' => [new TaskCardUi()->getBubble($eTask, 1.5, $eUser), FALSE, TRUE],
			'le temps prévu de « Nouvelle intervention »' => [new HarvestEntryUi()->getTimeExpectedField(new \util\FormUi()), TRUE, FALSE],
			'l\'entretien « Fait »' => [new TimeTeamUi()->getField(new \util\FormUi(), new \Collection([$eUser['id'] => $eUser])), TRUE, TRUE],
		];

		foreach($cases as $case => [$html, $tile, $spent]) {

			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

			$this->checkStepWindow($document->body, $case, $tile, $spent);

		}

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new TaskCardUi()->getBubble($eTask, 1.5, $eUser).'</body></html>', LIBXML_NOERROR);
		$this->assertEquals('1.5', $document->querySelector('.planning-task-bubble .planning-duration-step')?->getAttribute('data-sent'), 'la bulle ne s\'ouvre pas sur le temps de la personne');

	}

	/**
	 * Un temps prévu (`$spent` à `FALSE`) se saisit dans la même fenêtre qu'un temps passé, sans en prendre la
	 * marque (point 12) : le champ se dit prévu, et reçoit le refus de son temps.
	 */
	public function testStepWindowExpected(): void {

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new DurationUi()->getStepWindow('grid-1', 2.25, 'timeExpected', spent: FALSE).'</body></html>', LIBXML_NOERROR);

		$field = $document->querySelector('.planning-duration-step');

		$this->assertTrue($field?->matches('.planning-duration-step-expected[data-wrapper="timeExpected"]') === TRUE, 'le champ du temps prévu ne se dit pas prévu ou ne reçoit pas son refus');

	}

	/**
	 * La fenêtre sans tuile, toujours ouverte dans l'écran qui la porte, la bulle de la Semaine et la carte du
	 * Jour (R-Retours-0810, 212) : les pas n'envoient rien, « Enregistrer » envoie une fois, puis retire l'écran
	 * qui la porte (`dismiss`) quand le serveur a accepté, et le garde sur un refus, dont le message s'y lit.
	 * La croix et Échap retirent l'écran sans rien envoyer, la durée revenant à la dernière écrite. −1h s'arrête
	 * à zéro (arbitrage de V-Planning, condition a).
	 *
	 * `PlanningDurationStep` tourne sur des nœuds simulés, sans placement.
	 */
	public function testStepBareScript(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$classes = '';

		foreach(['PlanningDuration', 'PlanningDurationStep'] as $class) {

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe '.$class) === FALSE) {
				return;
			}

			$classes .= substr($source, $start, $end - $start + 2);

		}

		// [gestes] => [durées envoyées, écran retiré, cases affichées, dernière durée écrite], à partir de 0h45
		$cases = [
			'un pas' => [[['step', 15]], [[], FALSE, ['1', '00'], '0.75']],
			'un pas puis Enregistrer' => [[['step', 15], ['save']], [[1], TRUE, ['1', '00'], '1']],
			'un pas puis Enregistrer, le champ remplacé par la réponse' => [[['step', 15], ['redraw'], ['save']], [[1], TRUE, ['1', '00'], '1']],
			'−1h depuis 0h45 puis Enregistrer' => [[['step', -60], ['save']], [[0], TRUE, ['0', '00'], '0']],
			'Enregistrer sans rien changer' => [[['save']], [[], TRUE, ['0', '45'], '0.75']],
			'un pas puis la croix ou Échap' => [[['step', 60], ['close']], [[], TRUE, ['0', '45'], '0.75']],
			'un refus' => [[['step', 60], ['refuse'], ['save']], [[1.75], FALSE, ['1', '45'], '0.75']],
			'un refus, puis la croix' => [[['step', 60], ['refuse'], ['save'], ['close']], [[1.75], TRUE, ['0', '45'], '0.75']],
		];

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			const classList = (...initial) => {
				const names = new Set(initial);
				return {
					add: (...added) => added.forEach(name => names.add(name)),
					remove: (...removed) => removed.forEach(name => names.delete(name)),
					contains: name => names.has(name),
					toggle(name, on = (names.has(name) === false)) {
						if(on) {
							names.add(name);
						} else {
							names.delete(name);
						}
						return on;
					}
				};
			};

			// `dataset` écrit ses valeurs en texte, comme `DOMStringMap`.
			const dataset = values => new Proxy(values, {set(target, key, value) { target[key] = String(value); return true; }});

			const context = vm.createContext({
				Promise: Promise,
				PlanningTask: {MAX_TIME: 24, round: time => Math.round(time * 100) / 100, format: time => String(time)},
				requestAnimationFrame: () => {},
				// L\'envoi relève la case qui a le focus (`PlanningDurationStep.send()`) : aucune ici.
				document: {activeElement: null, body: {}},
				qs: () => null,
				qsa: () => {}
			});

			vm.runInContext('.json_encode($classes).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const PlanningDurationStep = context.PlanningDurationStep;
			PlanningDurationStep.place = () => {};

			let sent = [];
			let refuse = false;
			let redraw = false;
			let dismissed = false;

			PlanningDurationStep.register({
				selector: ".planning-duration-step",
				bounded: true,
				change: (field, time) => {
					sent.push(time);
					// La réponse du Jour redessine la colonne : le champ envoyé n\'est plus dans la page.
					if(redraw) {
						field.matches = () => false;
					}
					const json = refuse ? {__instructions: [["js", [["errors", {}]]]]} : {__instructions: []};
					refuse = false;
					return Promise.resolve(json);
				},
				dismiss: () => {
					dismissed = true;
				}
			});

			const make = () => {

				const input = value => {
					let text = value;
					return {get value() { return text; }, set value(written) { text = String(written); }, classList: classList(), getAttribute: () => null};
				};
				const hour = input("0");
				const minute = input("45");
				const group = {classList: classList()};
				const stepWindow = {classList: classList("planning-duration-step-window", "planning-duration-step-bare", "is-open")};

				const field = {
					dataset: dataset({duration: "bubble-1", sent: "0.75"}),
					classList: classList(),
					isConnected: true,
					matches: selector => selector === ".planning-duration-step",
					qs: selector => ({"input[name=\"timeAddHour\"]": hour, "input[name=\"timeAddMinute\"]": minute, ".timesheet-time-field-group": group})[selector] ?? null,
					closest: selector => (selector === ".planning-duration-step-window") ? stepWindow : null,
					contains: () => false
				};

				stepWindow.qs = selector => (selector === ".planning-duration-step") ? field : null;
				stepWindow.previousElementSibling = {qs: () => null};

				return {field, stepWindow, hour, minute};

			};

			(async () => {

				const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';
				const results = [];

				for(const gestures of cases) {

					const current = make();
					sent = [];
					refuse = false;
					redraw = false;
					dismissed = false;

					const field = current.field;

					for(const [gesture, ...values] of gestures) {

						switch(gesture) {

							case "step" :
								PlanningDurationStep.add(field, values[0]);
								break;

							case "save" :
								PlanningDurationStep.save(field);
								break;

							case "close" :
								PlanningDurationStep.close(field);
								break;

							case "refuse" :
								refuse = true;
								break;

							case "redraw" :
								redraw = true;
								break;

						}

						await new Promise(resolve => setTimeout(resolve, 0));

					}

					results.push([sent, dismissed, [current.hour.value, current.minute.value], field.dataset.sent, current.stepWindow.classList.contains("is-open")]);

				}

				console.log(JSON.stringify(results));

			})();
		');

		if($this->assertTrue($results !== NULL, 'la fenêtre sans tuile ne tourne pas dans node : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$sentExpected, $dismissedExpected, $valueExpected, $sentLast] = $cases[$case][1];
			[$sentGot, $dismissedGot, $valueGot, $sentLastGot, $open] = $results[$position];

			$this->assertEquals($sentExpected, $sentGot, $case.' : la fenêtre n\'envoie pas ce qu\'elle doit');
			$this->assertEquals($dismissedExpected, $dismissedGot, $case.' : '.($dismissedExpected ? 'l\'écran qui porte la fenêtre reste' : 'l\'écran qui porte la fenêtre est retiré'));
			$this->assertEquals($valueExpected, $valueGot, $case.' : la fenêtre n\'affiche pas la durée attendue');
			$this->assertEquals($sentLast, $sentLastGot, $case.' : la dernière durée écrite n\'est pas celle attendue');
			$this->assertTrue($open, $case.' : la fenêtre sans tuile se ferme d\'elle-même, au lieu de laisser l\'écran qui la porte la retirer');

		}

	}

	/**
	 * La fenêtre à tuile se ferme après « Enregistrer » (FJ, retour 411) : la ligne prend la durée écrite, et la
	 * réponse qui redessine la ligne la rend fermée, sans rendre le focus à une case qu'on ne voit plus. Rouverte
	 * pendant l'envoi, elle se rouvre dans le nœud neuf, et un pas fait pendant l'envoi y passe sans repartir.
	 * « Enregistrer » sans changement referme comme la croix, un refus rouvre la fenêtre sur la durée refusée. La
	 * poubelle envoie une durée nulle et ferme.
	 *
	 * `PlanningDurationStep` tourne sur des nœuds simulés, sans placement : la page n'a qu'une ligne, que la
	 * réponse peut remplacer par une neuve, rendue fermée avec la durée que le serveur a écrite.
	 */
	public function testStepWindowSaveScript(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$classes = '';

		foreach(['PlanningDuration', 'PlanningDurationStep'] as $class) {

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe '.$class) === FALSE) {
				return;
			}

			$classes .= substr($source, $start, $end - $start + 2);

		}

		// [gestes] => [durées envoyées, fenêtre ouverte, cases affichées, dernière durée écrite, case qui a le focus], à partir de 0h45
		$cases = [
			'un pas puis Enregistrer, la ligne gardée' => [[['step', 15], ['save']], [[1], FALSE, ['1', '00'], '1', NULL]],
			'un pas puis Enregistrer, la ligne redessinée' => [[['step', 15], ['redraw'], ['save']], [[1], FALSE, ['1', '00'], '1', NULL]],
			'Entrée dans les minutes, la ligne redessinée' => [[['focus', 'timeAddMinute'], ['step', 15], ['redraw'], ['save']], [[1], FALSE, ['1', '00'], '1', NULL]],
			'rouverte et un pas pendant l\'envoi, la ligne redessinée' => [[['step', 15], ['redraw'], ['save', 5]], [[1], TRUE, ['1', '05'], '1', NULL]],
			'Enregistrer sans rien changer' => [[['save']], [[], FALSE, ['0', '45'], '0.75', NULL]],
			'Enregistrer sans rien changer sur 0h05 lu en float32' => [[['column', '0.079999998211861', '0', '05'], ['save']], [[], FALSE, ['0', '05'], '0.079999998211861', NULL]],
			'un refus' => [[['step', 60], ['refuse'], ['save']], [[1.75], TRUE, ['1', '45'], '0.75', NULL]],
			'Enregistrer puis la croix' => [[['step', 15], ['save'], ['close']], [[1], FALSE, ['1', '00'], '1', NULL]],
			'la poubelle, la ligne redessinée' => [[['redraw'], ['erase']], [[0], FALSE, ['0', '00'], '0', NULL]],
		];

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			const classList = (...initial) => {
				const names = new Set(initial);
				return {
					add: (...added) => added.forEach(name => names.add(name)),
					remove: (...removed) => removed.forEach(name => names.delete(name)),
					contains: name => names.has(name),
					toggle(name, on = (names.has(name) === false)) {
						if(on) {
							names.add(name);
						} else {
							names.delete(name);
						}
						return on;
					}
				};
			};

			// `dataset` écrit ses valeurs en texte, comme `DOMStringMap`.
			const dataset = values => new Proxy(values, {set(target, key, value) { target[key] = String(value); return true; }});

			const document = {activeElement: null, body: {}};
			const page = {line: null};

			const make = (time, open) => {

				const minutes = Math.round(time * 60);

				const input = (name, value) => {
					let text = value;
					const node = {name: name, get value() { return text; }, set value(written) { text = String(written); }, classList: classList(), getAttribute: () => null};
					node.focus = () => {
						document.activeElement = node;
					};
					return node;
				};
				const hour = input("timeAddHour", String(Math.floor(minutes / 60)));
				const minute = input("timeAddMinute", String(minutes % 60).padStart(2, "0"));
				const group = {classList: classList()};
				const stepWindow = {classList: open ? classList("planning-duration-step-window", "is-open") : classList("planning-duration-step-window")};

				const field = {
					dataset: dataset({duration: "task-1-2026-10-12", sent: String(time)}),
					classList: classList(),
					isConnected: true,
					matches: selector => selector === ".planning-duration-step",
					qs: selector => ({"input[name=\"timeAddHour\"]": hour, "input[name=\"timeAddMinute\"]": minute, ".timesheet-time-field-group": group})[selector] ?? null,
					closest: selector => (selector === ".planning-duration-step-window") ? stepWindow : null,
					contains: node => node === hour || node === minute
				};

				stepWindow.qs = selector => (selector === ".planning-duration-step") ? field : null;
				stepWindow.previousElementSibling = {qs: () => null};

				return {field, stepWindow, hour, minute};

			};

			// La page ne connaît que sa ligne : un champ par sa clé, une case par son nom, et la fenêtre ouverte.
			const qs = (selector, found) => {

				const line = page.line;
				const key = selector.match(/data-duration="([^"]+)"\]/);
				let node = null;

				if(key !== null && key[1] === line.field.dataset.duration) {
					const name = selector.match(/input\[name="([^"]+)"\]/);
					node = (name === null) ? line.field : line.field.qs("input[name=\"" + name[1] + "\"]");
				} else if(selector.startsWith(".planning-duration-step-window.is-open")) {
					node = line.stepWindow.classList.contains("is-open") ? line.stepWindow : null;
				}

				if(node !== null && found !== undefined) {
					found(node);
				}

				return node;

			};

			const context = vm.createContext({
				Promise: Promise,
				PlanningTask: {MAX_TIME: 24, round: time => Math.round(time * 100) / 100, format: time => String(time)},
				requestAnimationFrame: () => {},
				document: document,
				qs: qs,
				qsa: () => {}
			});

			vm.runInContext('.json_encode($classes).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const PlanningDurationStep = context.PlanningDurationStep;
			PlanningDurationStep.place = () => {};

			let sent = [];
			let refuse = false;
			let redraw = false;

			PlanningDurationStep.register({
				selector: ".planning-duration-step",
				bounded: true,
				change: (field, time) => {
					sent.push(time);
					const refused = refuse;
					refuse = false;
					// La réponse s\'applique avant que sa promesse ne se tienne, comme `Ajax.Query` : la ligne neuve
					// porte la durée écrite, fermée, et l\'ancienne quitte la page avec le focus qu\'elle avait.
					return new Promise(resolve => setTimeout(() => {
						if(refused === false && redraw) {
							field.isConnected = false;
							if(field.contains(document.activeElement)) {
								document.activeElement = document.body;
							}
							page.line = make(time, false);
						}
						resolve(refused ? {__instructions: [["js", [["errors", {}]]]]} : {__instructions: []});
					}, 0));
				}
			});

			(async () => {

				const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';
				const results = [];

				for(const gestures of cases) {

					page.line = make(0.75, true);
					PlanningDurationStep.opened = page.line.field.dataset.duration;
					document.activeElement = null;
					sent = [];
					refuse = false;
					redraw = false;

					const field = page.line.field;

					for(const [gesture, ...values] of gestures) {

						switch(gesture) {

							case "step" :
								PlanningDurationStep.add(field, values[0]);
								break;

							case "focus" :
								field.qs("input[name=\"" + values[0] + "\"]").focus();
								break;

							// Un second nombre est un pas fait pendant l\'envoi, sur la fenêtre rouverte.
							case "save" :
								PlanningDurationStep.save(field);
								if(values.length > 0) {
									PlanningDurationStep.open(field);
									PlanningDurationStep.add(field, values[0]);
								}
								break;

							case "erase" :
								PlanningDurationStep.erase(field);
								break;

							case "close" :
								PlanningDurationStep.close(page.line.field);
								break;

							case "refuse" :
								refuse = true;
								break;

							case "redraw" :
								redraw = true;
								break;

							// La ligne telle que `DurationUi` la rend depuis la colonne `float32` : dernière durée écrite, heures, minutes.
							case "column" :
								field.dataset.sent = values[0];
								page.line.hour.value = values[1];
								page.line.minute.value = values[2];
								break;

						}

						await new Promise(resolve => setTimeout(resolve, 5));

					}

					const line = page.line;

					results.push([sent, line.stepWindow.classList.contains("is-open"), [line.hour.value, line.minute.value], line.field.dataset.sent, line.field.contains(document.activeElement) ? document.activeElement.name : null]);

				}

				console.log(JSON.stringify(results));

			})();
		');

		if($this->assertTrue($results !== NULL, 'la fenêtre à tuile ne tourne pas dans node : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$sentExpected, $openExpected, $valueExpected, $sentLast, $focusedExpected] = $cases[$case][1];
			[$sentGot, $openGot, $valueGot, $sentLastGot, $focusedGot] = $results[$position];

			$this->assertEquals($sentExpected, $sentGot, $case.' : la fenêtre n\'envoie pas ce qu\'elle doit');
			$this->assertEquals($openExpected, $openGot, $case.' : '.($openExpected ? 'la fenêtre se ferme après « Enregistrer »' : 'la fenêtre reste ouverte'));
			$this->assertEquals($valueExpected, $valueGot, $case.' : la ligne n\'affiche pas la durée attendue');
			$this->assertEquals($sentLast, $sentLastGot, $case.' : la dernière durée écrite n\'est pas celle attendue');
			$this->assertEquals($focusedExpected, $focusedGot, $case.' : le focus n\'est pas sur la case attendue');

		}

	}

	/**
	 * Fenêtre flottante ouverte, le premier clic hors d'elle la referme et rien d'autre n'agit, partout dans la
	 * page (retour 399) : ni un lien, ni une ligne, ni le « + » d'une autre personne. Un clic au clavier, un
	 * Ctrl+clic ou Cmd+clic sur un lien, un clic dans la fenêtre ou sur sa tuile, une sélection commencée dans
	 * la fenêtre, et toute fenêtre rendue au flux laissent agir. `down()` et `outside()` tournent
	 * sur un arbre simulé : une ligne porte la tuile et la fenêtre, une autre le « + » d'une autre personne et
	 * un lien.
	 */
	public function testStepWindowOutsideClick(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningDurationStep {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningDurationStep') === FALSE) {
			return;
		}

		// [fenêtre ouverte à l'appui, flottante, nœud de l'appui, nœud du clic, `detail` du clic, touche Ctrl ou Cmd, ou bouton droit] => [fenêtre fermée, clic arrêté]
		$cases = [
			'un lien' => [[TRUE, TRUE, 'link', 'link', 1, NULL], [TRUE, TRUE]],
			'une autre ligne' => [[TRUE, TRUE, 'rowOther', 'rowOther', 1, NULL], [TRUE, TRUE]],
			'le « + » d\'une autre personne' => [[TRUE, TRUE, 'tileOther', 'tileOther', 1, NULL], [TRUE, TRUE]],
			'la ligne de la fenêtre' => [[TRUE, TRUE, 'row', 'row', 1, NULL], [TRUE, TRUE]],
			'un Ctrl+clic sur une autre ligne' => [[TRUE, TRUE, 'rowOther', 'rowOther', 1, 'ctrl'], [TRUE, TRUE]],
			'dans la fenêtre' => [[TRUE, TRUE, 'field', 'field', 1, NULL], [FALSE, FALSE]],
			'sa tuile' => [[TRUE, TRUE, 'tile', 'tile', 1, NULL], [FALSE, FALSE]],
			'une sélection commencée dans la fenêtre' => [[TRUE, TRUE, 'field', 'row', 1, NULL], [FALSE, FALSE]],
			'au clavier' => [[TRUE, TRUE, NULL, 'link', 0, NULL], [FALSE, FALSE]],
			'au clavier, après un appui resté sans clic' => [[TRUE, TRUE, 'rowOther', 'link', 0, NULL], [FALSE, FALSE]],
			'un Ctrl+clic sur un lien' => [[TRUE, TRUE, 'link', 'link', 1, 'ctrl'], [FALSE, FALSE]],
			'un Cmd+clic sur un lien' => [[TRUE, TRUE, 'link', 'link', 1, 'meta'], [FALSE, FALSE]],
			'une fenêtre rendue au flux' => [[TRUE, FALSE, 'link', 'link', 1, NULL], [FALSE, FALSE]],
			'sans fenêtre' => [[FALSE, TRUE, 'link', 'link', 1, NULL], [FALSE, FALSE]],
			'un appui repris pour défiler' => [[TRUE, TRUE, 'link', 'cancel', 1, NULL], [FALSE, FALSE]],
			'un clic droit, sans clic qui suive' => [[TRUE, TRUE, 'link', 'link', 1, 'right'], [FALSE, FALSE]],
		];

		$script = '
			const vm = require("vm");
			const context = vm.createContext({});
			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);
			const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';

			const node = (parent, selectors = []) => ({
				parent: parent,
				selectors: selectors,
				contains(other) {
					for(let current = other; current !== null; current = current.parent) {
						if(current === this) {
							return true;
						}
					}
					return false;
				},
				closest(selector) {
					for(let current = this; current !== null; current = current.parent) {
						if(current.selectors.includes(selector)) {
							return current;
						}
					}
					return null;
				}
			});

			const classList = names => ({contains: name => names.has(name)});

			const body = node(null);
			const row = node(body);
			const tile = node(row, ["[data-planning-duration-step-open]"]);
			const stepWindow = node(row);
			const field = node(stepWindow);
			const rowOther = node(body);
			const tileOther = node(rowOther, ["[data-planning-duration-step-open]"]);
			const windowOther = node(rowOther);

			tile.nextElementSibling = stepWindow;
			tileOther.nextElementSibling = windowOther;
			stepWindow.qs = selector => (selector === ".planning-duration-step") ? field : null;

			const nodes = {
				row: row,
				tile: tile,
				field: field,
				rowOther: rowOther,
				tileOther: tileOther,
				link: node(rowOther, ["a[href]"])
			};

			const results = cases.map(([open, floating, pressed, target, detail, modified]) => {

				const closed = [];
				const e = {target: nodes[target] ?? null, detail: detail, ctrlKey: modified === "ctrl", metaKey: modified === "meta", stopped: false, prevented: false, stopPropagation() { this.stopped = true; }, preventDefault() { this.prevented = true; }};

				stepWindow.classList = classList(new Set(open ? ["is-open"] : []));

				context.qs = selector => open ? stepWindow : null;
				context.floating = floating;
				context.closed = closed;

				vm.runInContext("PlanningDurationStep.isFloating = () => floating; PlanningDurationStep.close = field => closed.push(field)", context);

				context.PlanningDurationStep.cancel();

				if(pressed !== null) {
					context.PlanningDurationStep.down({target: nodes[pressed], button: modified === "right" ? 2 : 0});
				}

				// Le navigateur reprend l\'appui pour défiler, et le clic qui suit vient d\'un autre geste.
				if(target === "cancel") {
					context.PlanningDurationStep.cancel();
					e.target = nodes.link;
				}

				context.PlanningDurationStep.outside(e);

				return [
					closed.length === 1 && closed[0] === field,
					e.stopped && e.prevented,
					e.stopped || e.prevented,
					context.PlanningDurationStep.pressed === null && context.PlanningDurationStep.held === null
				];

			});

			process.stdout.write(JSON.stringify(results));
		';

		[$results, $trace] = $this->runNode($script);

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$trace) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$closed, $stopped, $touched, $forgotten] = $results[$position];

			$this->assertEquals($cases[$case][1][0], $closed, $case.', la fenêtre '.($cases[$case][1][0] ? 'reste ouverte' : 'se ferme'));
			$this->assertEquals($cases[$case][1][1], $stopped, $case.', le clic '.($cases[$case][1][1] ? 'traverse' : 'est arrêté'));
			$this->assertEquals($stopped, $touched, $case.', le clic n\'est arrêté qu\'à moitié');
			$this->assertTrue($forgotten, $case.', l\'appui reste retenu pour le clic suivant');

		}

	}

	/**
	 * Une zone n'a qu'un bouton plein visible (passe ui de R) : dans un formulaire, la fenêtre ouverte éteint
	 * l'envoi du formulaire, qui passe en contour, et sa fermeture lui rend le plein. Un bouton qui n'était pas
	 * plein reste tel quel, et une fenêtre hors de tout formulaire ne touche à rien.
	 */
	public function testStepWindowMute(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$classes = '';

		foreach(['PlanningDuration', 'PlanningDurationStep'] as $class) {

			$start = strpos($source, "\nclass ".$class." {");
			$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

			if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe '.$class) === FALSE) {
				return;
			}

			$classes .= substr($source, $start, $end - $start + 2);

		}

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			const classList = (...initial) => {
				const names = new Set(initial);
				return {
					add: (...added) => added.forEach(name => names.add(name)),
					remove: (...removed) => removed.forEach(name => names.delete(name)),
					contains: name => names.has(name),
					toggle(name, on = (names.has(name) === false)) {
						if(on) {
							names.add(name);
						} else {
							names.delete(name);
						}
						return on;
					},
					list: () => [...names].sort()
				};
			};

			const context = vm.createContext({
				Promise: Promise,
				PlanningTask: {MAX_TIME: 24, round: time => Math.round(time * 100) / 100, format: time => String(time)},
				requestAnimationFrame: () => {},
				qs: () => null,
				qsa: () => {}
			});

			vm.runInContext('.json_encode($classes).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const PlanningDurationStep = context.PlanningDurationStep;
			PlanningDurationStep.place = () => {};

			// Un bouton de formulaire : `[type="submit"]`, ses classes et l\'attribut que la fenêtre pose.
			const button = (type, ...names) => {
				const attributes = new Set();
				return {
					type: type,
					classList: classList("btn", ...names),
					toggleAttribute: (name, on) => on ? attributes.add(name) : attributes.delete(name),
					hasAttribute: name => attributes.has(name)
				};
			};

			const matches = (node, selector) => ({
				"button[type=\"submit\"].btn-primary": node.type === "submit" && node.classList.contains("btn-primary"),
				"button[type=\"submit\"][data-planning-duration-muted]": node.type === "submit" && node.hasAttribute("data-planning-duration-muted")
			})[selector];

			const make = inForm => {

				const input = value => ({value: value, classList: classList(), getAttribute: () => null});
				const hour = input("0");
				const minute = input("45");
				const group = {classList: classList()};
				const stepWindow = {classList: classList("planning-duration-step-window")};

				const buttons = [button("submit", "btn-primary"), button("submit", "btn-outline-secondary"), button("button", "btn-primary")];
				const form = {qsa: (selector, each) => buttons.filter(node => matches(node, selector)).forEach(each)};

				const field = {
					dataset: {duration: "batch-time-1", sent: "0.75"},
					classList: classList(),
					matches: selector => selector === ".planning-duration-step",
					qs: selector => ({"input[name=\"timeAddHour\"]": hour, "input[name=\"timeAddMinute\"]": minute, ".timesheet-time-field-group": group})[selector] ?? null,
					closest: selector => ({".planning-duration-step-window": stepWindow, "form": inForm ? form : null})[selector] ?? null
				};

				stepWindow.qs = selector => (selector === ".planning-duration-step") ? field : null;
				stepWindow.previousElementSibling = {qs: () => null};

				return {field, buttons};

			};

			PlanningDurationStep.register({selector: ".planning-duration-step", bounded: true, change: () => undefined});

			const read = buttons => buttons.map(node => node.classList.list().join(" "));
			const results = {};

			for(const inForm of [true, false]) {
				const {field, buttons} = make(inForm);
				const before = read(buttons);
				PlanningDurationStep.open(field);
				const opened = read(buttons);
				PlanningDurationStep.close(field);
				results[inForm ? "form" : "alone"] = [before, opened, read(buttons)];
			}

			console.log(JSON.stringify(results));
		');

		if($this->assertTrue($results !== NULL, 'la fenêtre ne tourne pas dans node : '.$output) === FALSE) {
			return;
		}

		$initial = ['btn btn-primary', 'btn btn-outline-secondary', 'btn btn-primary'];

		$this->assertEquals([$initial, ['btn btn-outline-primary', 'btn btn-outline-secondary', 'btn btn-primary'], $initial], $results['form'], 'dans un formulaire, la fenêtre ouverte n\'éteint pas le seul envoi plein, ou sa fermeture ne le rallume pas');
		$this->assertEquals([$initial, $initial, $initial], $results['alone'], 'hors de tout formulaire, la fenêtre touche à des boutons');

	}

	/**
	 * La durée d'une ligne et sa fenêtre (R-Retours-0610, retour sur les points 2 et 3) : la tuile ouvre la
	 * fenêtre de son champ, qui porte le champ par pas puis « Enregistrer » (retours 197 et Enregistrer de
	 * R-Retours-0810), sans heures ni minutes, et un refus s'écrit une fois, dans le champ.
	 */
	public function testStepWindow(): void {

		$html = new DurationUi()->getStepWindow('task-1-2026-10-05', 2.25);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$tile = $document->body->firstElementChild;
		$window = $tile?->nextElementSibling;

		if(
			$this->assertTrue($tile?->matches('.planning-duration-step-tile[data-planning-duration-step-open="task-1-2026-10-05"]') === TRUE, 'la ligne n\'a pas sa tuile') === FALSE or
			$this->assertTrue($window?->matches('.planning-duration-step-window') === TRUE, 'la fenêtre ne suit pas la tuile') === FALSE
		) {
			return;
		}

		$this->assertTrue($window->firstElementChild?->matches('.planning-duration-step[data-duration="task-1-2026-10-05"]') === TRUE, 'la fenêtre ne commence pas par le champ par pas');

		$save = $window->lastElementChild?->querySelector('[data-planning-duration-step-save]');

		$this->assertTrue($save !== NULL, '« Enregistrer » ne suit pas le champ dans la fenêtre');

		$this->assertTrue($window->querySelector('.planning-duration, [data-planning-time-hour], [data-planning-time-minute]') === NULL, 'la fenêtre porte encore les heures ou les minutes');
		$this->assertEquals(1, $window->querySelectorAll('[data-wrapper~="time"]')->length, 'un refus du serveur s\'écrit plus d\'une fois dans la fenêtre');

	}

	/**
	 * Rien de saisi reste neutre (R-Retours-0410, point 12), et le temps prévu de « Nouvelle intervention » se dit
	 * prévu, pour que la durée saisie n'y prenne pas la marque du temps passé.
	 */
	public function testSpent(): void {

		$this->assertEquals('0h00', DurationUi::getSpent(0.0), 'un temps nul est marqué comme un temps passé');

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new HarvestEntryUi()->getTimeExpectedField(new \util\FormUi()).'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($document->querySelector('.planning-duration-step-expected') !== NULL, 'le temps prévu de « Nouvelle intervention » : la durée saisie sera marquée comme un temps passé');

	}

	/**
	 * « Journée » range les heures de 0 à 13 (retour 199) : une journée plus longue se tape.
	 */
	public function testWorkingHours(): void {

		$html = new TimeDayUi()->getWorking($this->eStructure, \Clock::week(), \Clock::date(), $this->getMember(), NULL, 7.5, TRUE, FALSE);
		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

		$hours = array_map(fn($node) => (int)$node->getAttribute('data-planning-time-hour'), iterator_to_array($document->querySelectorAll('.planning-time-working .planning-time-panel-hours > [data-planning-time-hour]')));

		$this->assertEquals(range(0, 13), $hours, '« Journée » ne range pas les heures de 0 à 13');

	}

	/**
	 * Le conteneur de « Journée » porte la dernière durée écrite (`data-sent`), vide pour une journée à saisir,
	 * que la fenêtre reprend quand on la ferme sans enregistrer (retour 198 de R-Retours-0810). L'heure et les
	 * minutes de la durée sont retenues dans leurs rangées.
	 */
	public function testWorkingSave(): void {

		$cases = [
			'une journée de 7h30' => [7.5, '7.5', 7, 30],
			'une journée à saisir' => [NULL, '', NULL, NULL],
		];

		foreach($cases as $case => [$time, $sent, $hour, $minute]) {

			$html = new TimeDayUi()->getWorking($this->eStructure, \Clock::week(), \Clock::date(), $this->getMember(), NULL, $time, TRUE, FALSE);
			$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

			$working = $document->querySelector('.planning-duration.planning-time-working');

			if($this->assertTrue($working !== NULL, $case.' : la tuile de la journée manque') === FALSE) {
				continue;
			}

			$this->assertEquals($sent, $working->getAttribute('data-sent'), $case.' : la journée ne porte pas sa dernière durée écrite');

			foreach([
				'les heures' => ['.planning-time-panel-hours', 'data-planning-time-hour', $hour],
				'les minutes' => ['.planning-time-panel-minutes', 'data-planning-time-minute', $minute],
			] as $row => [$selector, $attribute, $selected]) {

				$chosen = array_map(fn($node) => (int)$node->getAttribute($attribute), iterator_to_array($working->querySelectorAll($selector.' > .btn-chosen')));
				$this->assertEquals(($selected === NULL) ? [] : [$selected], $chosen, $case.' : '.$row.' ne retiennent pas la durée');

			}

		}

	}

	/**
	 * Rien ne part de « Journée » avant « Enregistrer » ou Entrée (planche R-Retours-0810, Enregistrer et 198) :
	 * une heure, des minutes, « Remettre à zéro » et la frappe ne font qu'écrire la durée affichée, et des
	 * minutes ne referment plus la fenêtre. « Enregistrer » envoie une fois et la ferme ; une durée inchangée,
	 * ou une journée à saisir qu'aucun geste n'a touchée, ne part pas. Un appui dehors, la croix et Échap la
	 * ferment sur la dernière durée écrite. Un refus la rouvre sur la durée refusée, où son message se lit,
	 * et la dernière durée écrite reste celle d'avant.
	 *
	 * `PlanningDuration` tourne sur des nœuds simulés, sans placement : `place()` a son propre cas.
	 */
	public function testWorkingSaveScript(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningDuration {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningDuration') === FALSE) {
			return;
		}

		// [durée à l'ouverture, gestes] => [durées envoyées, fenêtre ouverte, durée affichée, dernière durée écrite]
		$cases = [
			'une heure puis des minutes' => [['8', [['hour', 9], ['minute', 15]]], [[], TRUE, '9.25', '8']],
			'une heure puis Enregistrer' => [['8', [['hour', 9], ['save']]], [[9], FALSE, '9', '9']],
			'Enregistrer sans rien changer' => [['8', [['save']]], [[], FALSE, '8', '8']],
			'une durée tapée puis Entrée' => [['8', [['type', '7', '30'], ['save']]], [[7.5], FALSE, '7.5', '7.5']],
			'une journée à saisir, Enregistrer sans geste' => [['', [['save']]], [[], FALSE, '', '']],
			'une journée à saisir, « Remettre à zéro » puis Enregistrer' => [['', [['reset'], ['save']]], [[0], FALSE, '0', '0']],
			'une heure, puis un appui dehors, la croix ou Échap' => [['8', [['hour', 9], ['close']]], [[], FALSE, '8', '8']],
			'une durée tapée, puis un appui dehors' => [['8', [['type', '7', '30'], ['close']]], [[], FALSE, '8', '8']],
			'un refus' => [['8', [['hour', 9], ['refuse'], ['save']]], [[9], TRUE, '9', '8']],
			'une heure, puis la réponse d\'une ligne redessine « Journée »' => [['8', [['open'], ['hour', 9], ['redraw']]], [[], TRUE, '9', '8']],
			'une heure, la réponse d\'une ligne, puis Enregistrer' => [['8', [['open'], ['hour', 9], ['redraw'], ['save']]], [[9], FALSE, '9', '9']],
			'une heure, la réponse d\'une ligne, puis un appui dehors' => [['8', [['open'], ['hour', 9], ['redraw'], ['close']]], [[], FALSE, '8', '8']],
			'la réponse d\'une ligne quand « Journée » est fermée' => [['8', [['open'], ['hour', 9], ['close'], ['redraw']]], [[], FALSE, '8', '8']],
		];

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			const classList = () => {
				const names = new Set();
				return {
					add: (...added) => added.forEach(name => names.add(name)),
					remove: (...removed) => removed.forEach(name => names.delete(name)),
					contains: name => names.has(name),
					toggle(name, on = (names.has(name) === false)) {
						if(on) {
							names.add(name);
						} else {
							names.delete(name);
						}
						return on;
					}
				};
			};

			// `dataset` écrit ses valeurs en texte, comme `DOMStringMap`.
			const dataset = values => new Proxy(values, {set(target, key, value) { target[key] = String(value); return true; }});

			let current = null;

			const context = vm.createContext({
				// Les envois rendent une promesse du script de test : la classe la reconnaît par `instanceof Promise`,
				// qui ne vaut que dans le même royaume.
				Promise: Promise,
				PlanningTask: {MAX_TIME: 24, round: time => Math.round(time * 100) / 100, format: time => String(time)},
				requestAnimationFrame: () => {},
				qs: (selector, found) => {
					if(current !== null && selector === ".planning-duration-tiled[data-duration=\"working\"]") {
						found(current.duration);
						return current.duration;
					}
					return (current !== null && selector.includes("> .planning-time-panel.is-open") && current.panel.classList.contains("is-open")) ? current.panel : null;
				},
				qsa: (selector, each) => {
					if(current !== null && selector === current.selector +" > .planning-time-panel.is-open" && current.panel.classList.contains("is-open")) {
						each(current.panel);
					}
				}
			});

			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningDuration = PlanningDuration;", context);

			const PlanningDuration = context.PlanningDuration;
			PlanningDuration.place = () => {};

			let sent = [];
			let refuse = false;

			const change = (duration, time) => {
				sent.push(time);
				const json = refuse ? {__instructions: [["js", [["errors", {}]]]]} : {__instructions: []};
				refuse = false;
				return Promise.resolve(json);
			};

			PlanningDuration.register({selector: ".planning-duration.planning-time-working", bounded: true, change: change});

			const make = time => {

				const selector = ".planning-duration.planning-time-working";
				const panel = {classList: classList()};
				const hour = {value: "", getAttribute: () => null, focus() {}};
				const minute = {value: "", getAttribute: () => null, focus() {}};
				const fieldset = {disabled: true, qs: candidate => nodes[candidate]};
				const nodes = {
					":scope > .planning-time-panel": panel,
					".planning-time-panel-type": fieldset,
					"input[name=\"timeAddHour\"]": hour,
					"input[name=\"timeAddMinute\"]": minute
				};

				const duration = {
					dataset: dataset({duration: "working", time: time, sent: time}),
					classList: classList(),
					isConnected: true,
					matches: candidate => [selector, ".planning-duration-tiled"].includes(candidate),
					qs(candidate, found) {
						const node = nodes[candidate] ?? null;
						if(node !== null && found !== undefined) {
							found(node);
						}
						return node;
					},
					qsa() {}
				};

				duration.classList.add(...selector.split(".").filter(name => name !== ""));
				panel.parentElement = duration;

				return {duration, panel, hour, minute, selector: ".planning-duration-tiled"};

			};

			(async () => {

				const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';
				const results = [];

				for(const [time, gestures] of cases) {

					current = make(time);
					sent = [];
					refuse = false;

					current.panel.classList.add("is-open");

					for(const [gesture, ...values] of gestures) {

						const duration = current.duration;

						switch(gesture) {

							case "open" :
								current.panel.classList.remove("is-open");
								PlanningDuration.open(duration);
								break;

							// La réponse d\'une ligne de la fiche redessine « Journée » sur la durée enregistrée, puis
							// `PlanningDurationStep.resume()` appelle `reopen()`.
							case "redraw" :
								current = make(duration.dataset.sent);
								PlanningDuration.reopen();
								break;

							case "hour" :
								PlanningDuration.chooseHour(duration, values[0]);
								break;

							case "minute" :
								PlanningDuration.chooseMinute(duration, values[0]);
								break;

							case "reset" :
								PlanningDuration.choose(duration, 0);
								break;

							case "type" :
								PlanningDuration.type(duration);
								current.hour.value = values[0];
								current.minute.value = values[1];
								break;

							case "save" :
								PlanningDuration.save(duration);
								break;

							case "close" :
								PlanningDuration.close();
								break;

							case "refuse" :
								refuse = true;
								break;

						}

						await new Promise(resolve => setTimeout(resolve));

					}

					results.push([sent, current.panel.classList.contains("is-open"), current.duration.dataset.time, current.duration.dataset.sent]);

				}

				process.stdout.write(JSON.stringify(results));

			})();
		');

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {

			[$sent, $open, $time, $last] = $cases[$case][1];

			$this->assertEquals($sent, $results[$position][0], $case.' : les durées envoyées');
			$this->assertEquals($open, $results[$position][1], $case.' : la fenêtre '.($open ? 'est fermée' : 'reste ouverte'));
			$this->assertEquals($time, $results[$position][2], $case.' : la durée affichée');
			$this->assertEquals($last, $results[$position][3], $case.' : la dernière durée écrite');

		}

	}

	/**
	 * Une saisie derrière une tuile posée dans un panneau se place sur l'écran du panneau, entre le bas de son
	 * en-tête et son propre bas, et non sur les barres de la page qu'il couvre : le temps de plusieurs
	 * interventions tombait en feuille alors que la place était dessous, ou s'ouvrait au-dessus sous l'en-tête.
	 * Une page de 378 sous une barre de 39, un panneau de 420 ; la saisie mesure 250, à 0,5 rem de sa tuile.
	 * Sous la barre du pouce, la bande de l'univers (276 à 326) et la barre (326 à 378) bornent le bas de la
	 * page : lu sur `--nav-structure`, le bas tombait à 326 et la saisie passait sous la bande.
	 */
	public function testPlaceInPanel(): void {

		$source = file_get_contents(LIME_DIRECTORY.'/production/planning/static/js/planning.js');

		$start = strpos($source, "\nclass PlanningDuration {");
		$end = ($start === FALSE) ? FALSE : strpos($source, "\n}\n", $start);

		if($this->assertDecorPresent($start !== FALSE and $end !== FALSE, 'planning.js déclare la classe PlanningDuration') === FALSE) {
			return;
		}

		// [dans un panneau, bas de l'en-tête du panneau, haut de la tuile, barre du pouce] => classe posée
		$cases = [
			'panneau, place dessous dans le panneau et non dans la page' => [[TRUE, 40, 100, FALSE], ''],
			'panneau, en-tête plus bas que la barre de la page' => [[TRUE, 60, 300, FALSE], 'is-sheet'],
			'page, place au-dessus sous la barre' => [[FALSE, NULL, 330, FALSE], 'is-up'],
			'page, place ni dessous ni dessus' => [[FALSE, NULL, 100, FALSE], 'is-sheet'],
			'page, place dessous sans la barre du pouce' => [[FALSE, NULL, 20, FALSE], ''],
			'page, la bande de la barre du pouce prend le dessous' => [[FALSE, NULL, 20, TRUE], 'is-sheet'],
		];

		[$results, $output] = $this->runNode('
			const vm = require("vm");

			const style = {"--planning-time-panel-gap": "0.5", "--mainSticky": "0", "--nav-main-sticked": "2.4375", "--nav-main": "2.4375", "--nav-structure": "3.25", "--space-xs": "0.5rem"};

			let barShown = false;
			const strip = {getBoundingClientRect: () => ({top: 276, bottom: 326, height: 50})};
			const bar = {getBoundingClientRect: () => ({top: 326, bottom: 378, height: 52})};

			// De côté, la saisie tient dans l\'écran : seul le placement vertical est éprouvé ici (`DurationPlaceTest`).
			const context = vm.createContext({
				window: {innerHeight: 378, matchMedia: () => ({matches: false})},
				document: {documentElement: {clientWidth: 1000}},
				Structure: {hasBar: () => barShown},
				getComputedStyle: () => ({getPropertyValue: name => style[name]}),
				qs: () => ({getBoundingClientRect: () => ({left: 0, right: 1000})}),
				qsa: (selector, each) => (selector === "body > header, .structure-bar") ? [strip, bar].forEach(each) : undefined,
				rem: () => 16
			});

			vm.runInContext('.json_encode(substr($source, $start, $end - $start + 2)).' + ";globalThis.PlanningDuration = PlanningDuration;", context);

			const cases = '.json_encode(array_values(array_map(fn($case) => $case[0], $cases))).';

			const results = cases.map(([inPanel, headerBottom, tileTop, withBar]) => {

				barShown = withBar;

				const classes = [];
				const panelPage = inPanel ? {
					getBoundingClientRect: () => ({left: 0, right: 1000, top: 0, bottom: 420}),
					qs: selector => ({".panel-header": {getBoundingClientRect: () => ({bottom: headerBottom})}, ".panel-footer:not(:empty)": null})[selector]
				} : null;

				const duration = {classList: {contains: () => false}};

				const panel = {
					parentElement: duration,
					offsetParent: {getBoundingClientRect: () => ({top: tileTop, bottom: tileTop + 30})},
					getBoundingClientRect: () => ({left: 100, right: 484, top: tileTop + 38, bottom: tileTop + 288, height: 250}),
					style: {getPropertyValue: () => "", setProperty: () => {}, removeProperty: () => {}},
					classList: {add: name => classes.push(name)},
					closest: selector => (selector === ".panel") ? panelPage : null
				};

				context.PlanningDuration.place(panel);

				return classes.join(" ");

			});

			process.stdout.write(JSON.stringify(results));
		');

		if($this->assertTrue(is_array($results), 'node rend son résultat : '.$output) === FALSE) {
			return;
		}

		foreach(array_keys($cases) as $position => $case) {
			$this->assertEquals($cases[$case][1], $results[$position], $case.' : la saisie se pose mal');
		}

	}

	/**
	 * Le temps passé d'une saisie de récolte se règle dans les pas (R-Retours-0810, 208), sans aucune pastille
	 * d'heures ni de minutes, ouverts d'office et sans tuile (retour 290), la feuille du tactile comprise (HL).
	 * La durée part par le champ caché `time[<user>]`, désactivé tant qu'elle est nulle. Le champ par pas ne porte
	 * pas `data-wrapper` : un refus se lit sous les pas, où l'écrit la ligne de la personne.
	 */
	public function testHarvestTimeSteps(): void {

		$eUser = $this->getMember();

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new HarvestEntryUi()->getTimeWindow(new \util\FormUi(), $eUser).'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($document->querySelector('.planning-harvest-time > .planning-duration-step-window.planning-duration-step-bare.planning-duration-step-inline.is-open:first-child > .planning-duration-step') !== NULL, 'le temps n\'a pas ses pas ouverts d\'office');
		$this->assertTrue($document->querySelector('.planning-duration-tiled, [data-planning-time-hour], [data-planning-time-minute], [data-planning-time-reset]') === NULL, 'le temps garde les pastilles d\'heures et de minutes');
		$this->assertTrue($document->querySelector('.planning-harvest-time > input[type="hidden"][name="time['.$eUser['id'].']"][disabled]') !== NULL, 'la durée ne part pas par un champ caché désactivé');
		$this->assertTrue($document->querySelector('.planning-duration-step[data-wrapper]') === NULL, 'un refus s\'écrirait dans la fenêtre');

	}

}
?>
