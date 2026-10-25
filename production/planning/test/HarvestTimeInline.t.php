<?php
namespace planning;

/**
 * Le temps d'une saisie de récolte se règle dans le panneau qui la porte, sous la ligne de sa personne
 * (lot BW, retours 280 et 290, « intègre le temps de travail en inline », « et immédiatement
 * présente ») : au tiroir, au panneau « Nouvelle récolte » et dans « Nouvelle intervention » faite, les pas
 * sont ouverts d'office, sans tuile ; sur la feuille du tactile, une tuile les ouvre à la place du pavé. Tous
 * passent par `HarvestEntryUi::getTimeWindow()`. Les pas n'ont plus d'« Enregistrer » : chacun reporte aussitôt
 * la durée dans le champ caché, et « Ajouter » reste le seul envoi, plein pendant qu'on règle le temps.
 *
 * Lancement : dev/test package=planning file=HarvestTimeInline
 */
class HarvestTimeInlineTest extends HarvestFixtureTest {

	/**
	 * Partout, la feuille du tactile comprise, les pas sont ouverts d'office et sans tuile, et la durée part par
	 * le champ caché `time[<user>]`.
	 */
	public function testRendered(): void {

		$eUser = $this->getMember();

		$document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.new HarvestEntryUi()->getTimeWindow(new \util\FormUi(), $eUser, wrapped: FALSE).'</body></html>', LIBXML_NOERROR);

		$this->assertTrue($document->querySelector('.planning-harvest-time > .planning-duration-step-window.planning-duration-step-bare.planning-duration-step-inline.is-open:first-child > .planning-duration-step') !== NULL, 'les pas ne sont pas ouverts d\'office, sans tuile');
		$this->assertTrue($document->querySelector('.planning-duration-step-tile') === NULL, 'les pas gardent une tuile');
		$this->assertTrue($document->querySelector('.planning-harvest-time > input[type="hidden"][name="time['.$eUser['id'].']"]') !== NULL, 'la durée ne part plus par son champ caché');

	}

	/**
	 * Dans node, sur des nœuds simulés : un pas ou une frappe dans les pas en ligne reportent aussitôt la durée
	 * (`change()` du consommateur, qui écrit le champ caché), une durée inchangée ne repart pas, et l'ouverture
	 * n'éteint pas « Ajouter ». Ouverts d'office, les pas ne se ferment pas : Échap les laisse ouverts, sur la
	 * durée reportée.
	 */
	public function testStepReportsAtOnce(): void {

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
				getComputedStyle: () => ({position: "static"}),
				qs: () => null,
				qsa: () => {}
			});

			vm.runInContext('.json_encode($classes).' + ";globalThis.PlanningDurationStep = PlanningDurationStep;", context);

			const PlanningDurationStep = context.PlanningDurationStep;
			PlanningDurationStep.place = () => {};

			let sent = [];
			let dismissed = 0;

			PlanningDurationStep.register({
				selector: ".planning-duration-step",
				bounded: true,
				change: (field, time) => {
					sent.push(time);
				},
				dismiss: () => {
					dismissed++;
				}
			});

			const input = value => {
				let text = value;
				return {get value() { return text; }, set value(written) { text = String(written); }, classList: classList(), getAttribute: () => null};
			};

			const submit = {classList: classList("btn", "btn-primary"), toggleAttribute: () => {}};
			const form = {qsa: (selector, each) => selector.includes(".btn-primary") && submit.classList.contains("btn-primary") ? each(submit) : undefined};

			// Les pas d\'une personne, dans une fenêtre des classes données, d\'une durée reportée nulle.
			const create = (...classes) => {

				const hour = input("0");
				const minute = input("00");
				const group = {classList: classList()};
				const stepWindow = {classList: classList("planning-duration-step-window", ...classes)};

				const field = {
					dataset: dataset({duration: "harvest-1", sent: "0"}),
					classList: classList(),
					isConnected: true,
					matches: selector => selector === ".planning-duration-step",
					qs: selector => ({"input[name=\"timeAddHour\"]": hour, "input[name=\"timeAddMinute\"]": minute, ".timesheet-time-field-group": group})[selector] ?? null,
					closest: selector => ({".planning-duration-step-window": stepWindow, "form": form})[selector] ?? null
				};

				stepWindow.qs = selector => (selector === ".planning-duration-step") ? field : null;
				stepWindow.previousElementSibling = {qs: () => null};

				return {field, hour, minute, stepWindow};

			};

			const results = {};

			// Au tiroir et aux panneaux : ouverts d\'office, sans tuile.
			const shown = create("planning-duration-step-bare", "planning-duration-step-inline", "is-open");

			PlanningDurationStep.add(shown.field, 15);
			PlanningDurationStep.add(shown.field, 15);
			PlanningDurationStep.add(shown.field, 15);
			results.steps = [...sent];

			sent = [];
			shown.minute.value = "45";
			PlanningDurationStep.report(shown.field);
			results.unchanged = [...sent];

			shown.hour.value = "1";
			PlanningDurationStep.report(shown.field);
			results.typed = [...sent];

			PlanningDurationStep.close(shown.field);
			results.kept = [shown.hour.value, shown.minute.value, shown.field.dataset.sent, shown.stepWindow.classList.contains("is-open"), dismissed];
			results.submit = submit.classList.contains("btn-primary");

			console.log(JSON.stringify(results));
		');

		if($this->assertTrue($results !== NULL, 'les pas en ligne ne tournent pas dans node : '.$output) === FALSE) {
			return;
		}

		$this->assertEquals([0.25, 0.5, 0.75], $results['steps'], 'un pas ne reporte pas aussitôt la durée dans le champ caché');
		$this->assertEquals([], $results['unchanged'], 'une durée inchangée repart');
		$this->assertEquals([1.75], $results['typed'], 'une frappe ne reporte pas aussitôt la durée');
		$this->assertEquals(['1', '45', '1.75', TRUE, 1], $results['kept'], 'refermer les pas ouverts d\'office les retire, ou revient sur la durée reportée');
		$this->assertTrue($results['submit'], 'les pas ouverts éteignent « Ajouter », seul envoi du formulaire');

	}

}
?>
