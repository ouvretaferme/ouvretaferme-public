<?php
namespace entry;

/**
 * Le garde-fou de la réponse ciblée : le planning de la semaine rendu, les instructions de l'écriture
 * ou de l'annulation appliquées dessus, doit être exactement le planning rendu de nouveau après elle.
 *
 * Le rendu est celui de la vraie page (`series/page/index.p.php`, vue `planning`), sur la structure du
 * décor, dans la transaction du test. Le planning est borné par une parcelle neuve (`plot`), où seules
 * les séries du test sont placées : ses lignes sont les seules que Fait et À faire montrent, quoi que
 * la structure porte cette semaine, et la lecture répétable fige le reste entre les deux rendus.
 *
 * Les instructions sont appliquées à un `Dom\HTMLDocument` comme `ajax.js` les applique au document :
 * cela éprouve ce que le PHP décide, pas `lime.js`, que la recette refait dans Chrome.
 *
 * Deux preuves précèdent les comparaisons : le même rendu comparé à lui-même est égal une fois les
 * aléas neutralisés (les ids `field-<aléa>` que le cadriciel tire pour chaque champ), et la
 * comparaison sait trouver un écart (un rendu dont on n'a pas appliqué les instructions diverge).
 */
class PlanningFingerprintTest extends EntryFixtureTest {

	private \map\Plot $ePlot;

	/** Ce que la dernière route du champ a préparé : son niveau, sa récolte. */
	private ?\stdClass $lastData = NULL;

	public function setUp(): void {

		parent::setUp();

		$this->ePlot = $this->createPlot();

		$this->setViews();

	}

	/**
	 * La vue semaine sans filtre d'action, d'espèce ni d'utilisateur, sauf celui qu'un cas demande.
	 */
	private function setViews(\work\Action $eActionSelected = new \work\Action()): void {

		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		\structure\MemberLib::setView('viewPlanning', $eStructure, \structure\Member::WEEKLY);
		\structure\MemberLib::setView('viewPlanningAction', $eStructure, $eActionSelected);
		\structure\MemberLib::setView('viewPlanningPlant', $eStructure, new \plant\Plant());
		\structure\MemberLib::setView('viewPlanningUser', $eStructure, new \user\User());

	}

	/**
	 * Une culture de l'espèce en phase de récolte, dans une série en planches placée sur la parcelle
	 * du test, plantée deux semaines plus tôt.
	 *
	 * La plantation faite n'est pas un décor : chaque écriture sur la série la recalcule
	 * (`SeriesLib::recalculate()`), et la semaine de départ de la culture se tire alors de ses semis et
	 * plantations. Sans eux, elle retombe à vide et la ligne rendue perd sa pastille de départ, la
	 * branche de `TaskUi::getTaskPlace()` qui lit les actions principales de la structure : c'est par
	 * là que l'écriture a planté sur la 7 le 2026-09-28, tests verts.
	 */
	private function createCultivationOnPlot(\plant\Plant $ePlant, string $seriesName): \series\Cultivation {

		$eSeries = $this->createSeriesBed($seriesName);

		$eCultivation = $this->createCultivation($eSeries, $ePlant);

		$this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::DONE, toWeek(date('Y-m-d', \Clock::shift('-14 days'))));

		$this->setPhase($eCultivation, TRUE);

		$this->createPlace($eSeries, $this->createBed($this->ePlot), 20);

		return $eCultivation;

	}

	/**
	 * Une intervention sans série n'a pas de place : la parcelle la masquerait (`TaskLib::filterPlot()`), et le
	 * cas qui l'éprouve rend le planning entier.
	 */
	private function getPlotId(): ?int {
		return $this->ePlot->empty() ? NULL : $this->ePlot['id'];
	}

	/**
	 * Le conteneur du planning de la semaine, tel que la page le rend.
	 */
	private function render(?string $week = NULL): \Dom\HTMLDocument {

		$path = 'ferme/'.$this->eStructure['id'].'/planning/'.\structure\Member::WEEKLY.'/'.($week ?? \Clock::week());

		$action = $this->executePageAction($path, ['plot' => $this->getPlotId()], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'le planning se rend');

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		require_once \Package::getFileFromUri('series/index', 'view');

		$view = \View::get('planning', $action);
		$template = new \ReflectionMethod(\View::class, 'getTemplate')->invoke($view);
		$template->setData($data);

		ob_start();
		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);
		$html = ob_get_clean();

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * Les instructions qu'une route du champ rend, telles que `Ajax.Instruction` les reçoit.
	 */
	private function getInstructions(string $route, array $input, ?string $week = NULL): array {

		$action = $this->executePageAction('entry/entry:'.$route, $input + [
			'structure' => $this->eStructure['id'],
			'week' => $week ?? \Clock::week(),
			'plot' => $this->getPlotId(),
		]);

		$this->assertTrue($action instanceof \ViewAction, $route.' rend ses instructions');

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		require_once \Package::getFileFromUri('entry/entry', 'view');

		$view = \View::get($route, $action);
		$template = new \ReflectionMethod(\View::class, 'getTemplate')->invoke($view);
		$template->setData($data);

		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);

		$this->lastData = $data;

		return $template->formatInstructions();

	}

	/**
	 * `$week` est la semaine que la page dit affichée (`#entry-context`), celle de la vue `render()` comparée.
	 */
	private function write(\plant\Plant $ePlant, string $quantity, \series\Cultivation $eCultivation = new \series\Cultivation(), ?string $week = NULL): array {

		$input = [
			'prefix' => 'Récolte '.$ePlant['name'],
			'query' => $quantity.' ',
		];

		if($eCultivation->notEmpty()) {
			$input['cultivation'] = $eCultivation['id'];
		}

		return $this->getInstructions('doWrite', $input, $week);

	}

	/**
	 * `$undo` est ce que la pastille de l'écriture poste (`reading['undo']`) : l'intention qui annule et le
	 * jeton de son ticket.
	 */
	private function cancel(array $undo, ?string $week = NULL): array {

		return $this->getInstructions('doCancel', $undo, $week);

	}

	/**
	 * Le temps seul, sans verbe : « <action> <espèce> <durée> ».
	 */
	private function writeTime(\work\Action $eAction, \plant\Plant $ePlant, string $duration, array $input = []): array {

		return $this->getInstructions('doWrite', $input + [
			'prefix' => $eAction['name'].' '.$ePlant['name'],
			'query' => $duration.' ',
		]);

	}

	/**
	 * Applique les instructions comme `Ajax.Instruction.render()` (`framework/util/static/js/ajax.js`).
	 * Un sélecteur qui ne trouve rien ne fait rien. Les classes à durée de vie sont passées : elles
	 * sont retirées avant que la recette compare, et une instruction inconnue fait échouer le cas.
	 */
	private function apply(\Dom\HTMLDocument $document, array $instructions): void {

		foreach($instructions as $instruction) {

			// Une instruction `js` n'a que deux membres, sans sélecteur.
			if($instruction[0] === 'js') {
				continue;
			}

			[$mode, $selector, $calls] = $instruction;

			$this->assertTrue(in_array($mode, ['qs', 'qsa'], TRUE), 'instruction '.$mode.' prise en charge');

			$nodes = ($mode === 'qs') ?
				array_filter([$document->querySelector($selector)]) :
				iterator_to_array($document->querySelectorAll($selector));

			foreach($nodes as $node) {

				foreach($calls as $call) {

					[$method, $arguments] = $call + [1 => []];

					switch($method) {

						case 'outerHtml' :
							$node->replaceWith(...$this->parse($document, $arguments[0]));
							break;

						case 'innerHtml' :
							$node->innerHTML = $arguments[0];
							break;

						case 'insertAdjacentHtml' :
							$this->insert($document, $node, $arguments[0], $arguments[1]);
							break;

						case 'remove' :
							$node->remove();
							break;

						case 'addClass' :
						case 'removeClass' :
							break;

						default :
							$this->assertFail('instruction '.$method.' prise en charge');
							break;

					}

				}

			}

		}

	}

	/**
	 * Le client refuse un HTML à plusieurs racines, en `outerHtml` comme en `insertAdjacentHtml`
	 * (`renderOuter()` et `renderAdjacentHTML()`, `framework/util/static/js/lime.js`) : il lève, les
	 * instructions suivantes ne s'appliquent pas et la réponse passe pour perdue. Le DOM du test les
	 * accepterait sans rien dire.
	 */
	private function parse(\Dom\HTMLDocument $document, string $html): array {

		$container = $document->createElement('div');
		$container->innerHTML = ltrim($html);

		$this->assertTrue($container->childNodes->length <= 1, 'une seule racine par instruction, comme le client l\'exige');

		return iterator_to_array($container->childNodes);

	}

	private function insert(\Dom\HTMLDocument $document, \Dom\Element $node, string $position, string $html): void {

		$nodes = $this->parse($document, $html);

		match($position) {
			'beforebegin' => $node->before(...$nodes),
			'afterbegin' => $node->prepend(...$nodes),
			'beforeend' => $node->append(...$nodes),
			'afterend' => $node->after(...$nodes),
		};

	}

	private function getFingerprint(\Dom\HTMLDocument $document): string {

		$container = $document->querySelector('#planning-weekly-container');

		$this->assertTrue($container !== NULL, 'le planning de la semaine est rendu');

		$html = preg_replace('/field-[a-z0-9]+/', 'field-X', $document->saveHtml($container));

		// Un lien de filtre pose son argument sur l'adresse du planning, que la page lit dans sa requête :
		// celle du test n'est pas celle de la page. `testFilters()` éprouve l'adresse de la réponse ciblée.
		return preg_replace('/href="[^"]*(?:\?|&amp;)((?:user|plant|action)Selected=[^"&]*)"/', 'href="?$1"', $html);

	}

	/**
	 * Le planning d'avant, les instructions appliquées, contre le planning rendu après : égaux.
	 */
	private function check(\Dom\HTMLDocument $before, array $instructions, string $level, string $case, ?string $week = NULL): void {

		$this->assertEquals($level, $this->lastData->change['level'] ?? NULL, $case.' : niveau');

		$this->apply($before, $instructions);

		$expected = $this->getFingerprint($this->render($week));
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * Une réponse sans contexte : la semaine n'est pas lue, et l'écran après la réponse est celui du
	 * rechargement.
	 */
	private function checkOutside(\Dom\HTMLDocument $before, array $instructions, string $case, string $week): void {

		$this->assertEquals(NULL, $this->lastData->context, $case.' : aucun contexte');

		$this->apply($before, $instructions);

		$expected = $this->getFingerprint($this->render($week));
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * Où deux empreintes divergent, en quelques centaines de caractères de part et d'autre : le
	 * conteneur entier ne se lit pas dans un message.
	 */
	private function getGap(string $expected, string $actual): string {

		if($expected === $actual) {
			return '';
		}

		$position = strspn($expected ^ $actual, "\0");
		$start = max(0, $position - 200);

		return "\n rechargé : ".substr($expected, $start, 500)."\n ciblé    : ".substr($actual, $start, 500);

	}

	/**
	 * Les deux preuves : un rendu comparé à lui-même est égal, un rendu dont on a oublié les
	 * instructions diverge.
	 */
	public function testProofs(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$this->assertEquals($this->getFingerprint($this->render()), $this->getFingerprint($this->render()), 'la neutralisation rend le même rendu égal à lui-même');

		$before = $this->render();

		$this->write($this->getPlant(), '12 kg');

		$this->assertTrue($this->getFingerprint($before) !== $this->getFingerprint($this->render()), 'une écriture sans ses instructions est trouvée');

	}

	/**
	 * Sans planning de la semaine affiché (un autre écran, une autre semaine), la réponse ne rend que
	 * la pastille.
	 */
	public function testWithoutPlanning(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$instructions = $this->getInstructions('doWrite', [
			'prefix' => 'Récolte '.$this->getPlant()['name'],
			'query' => '12 kg ',
			'week' => '',
		]);

		$this->assertTrue(isset($this->lastData->change) === FALSE, 'aucun planning à réécrire');

		$this->assertEquals(1, count($instructions), 'la pastille seule : '.json_encode($instructions));

	}

	/**
	 * La pastille vient en tête de la réponse, avant les instructions du planning : elle mesure la ligne
	 * de confirmation dont elle part avant que rien ne change. Son « Annuler » poste la récolte écrite,
	 * avec la semaine et la parcelle de la page.
	 */
	public function testToast(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$instructions = $this->write($this->getPlant(), '12 kg');

		$this->assertTrue(count($instructions) > 1, 'la pastille puis le planning');

		$toast = $this->getToast($instructions[0]);

		$this->assertTrue($toast !== NULL, 'la première instruction est la pastille : '.json_encode($instructions[0]));

		$undo = $this->lastData->reading['undo'];

		$this->assertEquals(EntryUi::TOAST_FROM, $toast['from']);
		$this->assertEquals(['harvest', $undo['ticket']], [$toast['undo']['attributes']['post-intention'], $toast['undo']['attributes']['post-ticket']]);
		$this->assertTrue(array_key_exists('post-week', $toast['undo']['attributes']) === FALSE, 'la semaine se lit sur la page au clic, la pastille survivant à la navigation');
		$this->assertTrue(array_key_exists('post-plot', $toast['undo']['attributes']) === FALSE, 'la parcelle aussi');
		$this->assertEquals('/entry/entry:doCancel', $toast['undo']['attributes']['data-entry-cancel'], 'Annuler passe par le délégué de la saisie, à l\'adresse de l\'intention');
		$this->assertTrue(str_contains($toast['detail'], 'Série A'), $toast['detail']);

		$cancel = $this->cancel($undo);

		$toastCancel = NULL;

		foreach($cancel as $instruction) {
			$toastCancel ??= $this->getToast($instruction);
		}

		$this->assertTrue($toastCancel !== NULL, 'l\'annulation pose sa pastille');
		$this->assertEquals(NULL, $toastCancel['undo']);

	}

	private function getToast(array $instruction): ?array {

		if($instruction[0] !== 'js') {
			return NULL;
		}

		foreach($instruction[1] as [$name, $arguments]) {
			if($name === 'toast') {
				return $arguments[0];
			}
		}

		return NULL;

	}

	/**
	 * Le bloc Récolte apparaît, puis la même intervention reçoit une seconde récolte (sa ligne est
	 * réécrite), puis l'annulation de chacune la ramène, et le bloc part avec la dernière.
	 */
	public function testActionAndLine(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$before = $this->render();

		// Seul cas de la bascule « semaine vide vers remplie », qu'une échéance de la semaine rendrait
		// déjà remplie (`testEmptyWeekWithDue`).
		if($this->assertDecorPresent($before->querySelector('#planning-weekly-todo #planning-due') === NULL, 'aucune échéance dans À faire de la semaine') === FALSE) {
			return;
		}

		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'première récolte');

		// La pastille de départ de la culture est rendue avec la couleur de son action : c'est la branche
		// de `TaskUi::getTaskPlace()` qui lit `WorkSetting::$mainActions`, et que la réponse doit poser
		// comme la page (cas vu le 2026-09-28, l'écriture faite et la réponse en erreur).
		$this->assertTrue($before->querySelector('#planning-weekly-done .tasks-planning-item-series .plant-start-background') !== NULL, 'la ligne écrite porte la pastille de départ en couleur');

		$undoFirst = $this->lastData->reading['undo'];

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '5 kg');
		$this->check($before, $instructions, PlanningLib::LINE, 'seconde récolte');

		$undoSecond = $this->lastData->reading['undo'];

		$before = $this->render();
		$instructions = $this->cancel($undoSecond);
		$this->check($before, $instructions, PlanningLib::LINE, 'seconde récolte annulée');

		$before = $this->render();
		$instructions = $this->cancel($undoFirst);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'première récolte annulée');

	}

	/**
	 * Le temps seul sur une intervention à faire de la semaine (lot 2b), puis son annulation : la ligne de
	 * l'intervention, dans son bloc d'action, rend ce que le rechargement rend.
	 */
	public function testTime(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$this->createTask($eCultivation, ACTION_FERTILISATION, \series\Task::TODO, \Clock::week());

		$before = $this->render();
		$instructions = $this->writeTime($this->getAction(ACTION_FERTILISATION), $this->getPlant(), '1 h');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'temps posé');

		$before = $this->render();
		$instructions = $this->writeTime($this->getAction(ACTION_FERTILISATION), $this->getPlant(), '30 min');
		$this->check($before, $instructions, PlanningLib::LINE, 'second temps');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::LINE, 'second temps annulé');

		// Sans `timeUser`, l'onglet n'offre aucun choix de membre et montre qui tape : les cas ci-dessus. Celui
		// qui montre qui tape parmi d'autres est rendu ; celui qui montre toute l'équipe, ou un autre membre,
		// est laissé en place.
		$eUser = \user\ConnectionLib::getOnline();

		foreach(['' => FALSE, $eUser['id'] + 1 => FALSE, $eUser['id'] => TRUE] as $timeUser => $expected) {
			$instructions = $this->writeTime($this->getAction(ACTION_FERTILISATION), $this->getPlant(), '15 min', ['timeUser' => $timeUser]);
			$rendered = array_filter($instructions, fn($instruction) => ($instruction[1] ?? NULL) === '#planning-weekly-time' and in_array('innerHtml', array_column($instruction[2], 0), TRUE));
			$this->assertEquals($expected, $rendered !== [], 'onglet montrant « '.$timeUser.' »');
		}

	}

	/**
	 * La coche « Toutes les séries » (lot S4d), « <action> <espèce> » sans durée, l'intervention de chaque
	 * série en jeton `all`.
	 */
	private function writeDoneAll(\work\Action $eAction, \plant\Plant $ePlant): array {

		return $this->getInstructions('doWrite', [
			'prefix' => $eAction['name'].' '.$ePlant['name'],
			'query' => '',
			'all' => 1,
		]);

	}

	/**
	 * Deux interventions cochées d'un geste dans le même bloc : un bloc déjà dans Fait se redessine, un bloc
	 * absent s'insère, et l'annulation rend chaque fois l'écran d'avant.
	 */
	public function testDoneAll(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$eAction = $this->getAction(ACTION_FERTILISATION);

		foreach(['Série A', 'Série B'] as $seriesName) {
			$this->createTask($this->createCultivationOnPlot($this->getPlant(), $seriesName), ACTION_FERTILISATION, \series\Task::TODO, \Clock::week());
		}

		$before = $this->render();
		$initial = $this->getFingerprint($before);
		$instructions = $this->writeDoneAll($eAction, $this->getPlant());
		$this->assertEquals(2, $this->lastData->reading['confirmation']['cTask']->count(), 'deux interventions cochées');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'toutes cochées, bloc neuf');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'toutes annulées, bloc retiré');

		$this->assertEquals($initial, $this->getFingerprint($this->render()), 'Annuler rend la semaine telle qu\'avant la saisie');

		$this->createTask($this->createCultivationOnPlot($this->getPlant(), 'Série C'), ACTION_FERTILISATION, \series\Task::DONE, \Clock::week());

		$before = $this->render();
		$instructions = $this->writeDoneAll($eAction, $this->getPlant());
		$this->check($before, $instructions, PlanningLib::ACTION, 'toutes cochées, bloc existant');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::ACTION, 'toutes annulées, bloc existant');

	}

	/**
	 * Rien de prévu : « Toutes » crée une intervention faite sur chaque série en phase, et l'annulation les retire.
	 */
	public function testDoneAllCreated(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');
		$this->createCultivationOnPlot($this->getPlant(), 'Série B');

		$before = $this->render();
		$instructions = $this->writeDoneAll($this->getAction(ACTION_FERTILISATION), $this->getPlant());
		$this->assertTrue($this->lastData->reading['confirmation']['created'], 'créées');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'toutes créées faites');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'toutes créées annulées');

	}

	/**
	 * Le filtre d'action masque le bloc : la réponse ne touche pas à Fait.
	 */
	public function testDoneAllFiltered(): void {

		if($this->requireAction(ACTION_FERTILISATION) === FALSE) {
			return;
		}

		foreach(['Série A', 'Série B'] as $seriesName) {
			$this->createTask($this->createCultivationOnPlot($this->getPlant(), $seriesName), ACTION_FERTILISATION, \series\Task::TODO, \Clock::week());
		}

		$this->setViews($this->getAction(ACTION_PLANTATION));

		$before = $this->render();
		$instructions = $this->writeDoneAll($this->getAction(ACTION_FERTILISATION), $this->getPlant());
		$this->check($before, $instructions, PlanningLib::NONE, 'toutes masquées par le filtre');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::NONE, 'toutes masquées annulées');

	}

	/**
	 * Le temps seul sur la récolte, « <espèce> 30 min », crée l'intervention de récolte sans quantité, et
	 * Annuler l'emporte.
	 */
	public function testHarvestTime(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '30 min');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'temps seul sur la récolte');

		$undo = $this->lastData->reading['undo'];

		$before = $this->render();
		$instructions = $this->cancel($undo);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'temps seul annulé');

		// Le temps porté par une récolte change l'onglet du temps de la même façon (lot 2a).
		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '12 kg 30 min');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'récolte avec son temps');

	}

	/**
	 * Deux cultures de la même espèce : la seconde ligne se glisse sous la tête de l'espèce, et sort
	 * seule à l'annulation.
	 */
	public function testSamePlant(): void {

		$eCultivationA = $this->createCultivationOnPlot($this->getPlant(), 'Série A');
		$eCultivationB = $this->createCultivationOnPlot($this->getPlant(), 'Série B');

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '12 kg', $eCultivationA);
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'première culture');

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '8 kg', $eCultivationB);
		$this->check($before, $instructions, PlanningLib::INSERT, 'seconde culture');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE, 'seconde culture annulée');

	}

	/**
	 * Deux espèces : la seconde arrive avec sa tête, et repart avec elle.
	 */
	public function testOtherPlant(): void {

		$ePlantOther = $this->createPlant();

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');
		$this->createCultivationOnPlot($ePlantOther, 'Série B');

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'première espèce');

		$before = $this->render();
		$instructions = $this->write($ePlantOther, '8 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_PLANT, 'seconde espèce');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE_PLANT, 'seconde espèce annulée');

	}

	/**
	 * Une récolte prévue cette semaine et pas encore faite : la saisie la termine et la fait passer d'À faire
	 * dans Fait, Annuler l'y remet.
	 */
	public function testPlanned(): void {

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$before = $this->render();
		$initial = $this->getFingerprint($before);
		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'récolte prévue');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'récolte prévue annulée');

		$this->assertEquals($initial, $this->getFingerprint($this->render()), 'Annuler rend la semaine telle qu\'avant la saisie');

	}

	/**
	 * Consigner écrit sur l'intervention que la saisie vient de terminer : Annuler ne retire que la récolte
	 * de la saisie, l'intervention reste faite et ne revient pas dans À faire.
	 */
	public function testCancelAfterConsigner(): void {

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'récolte prévue terminée par la saisie');

		$undo = $this->lastData->reading['undo'];

		\planning\HarvestWeekLib::writeEntry($this->eStructure, \series\CultivationLib::getById($eCultivation['id']), \planning\HarvestWeekLib::getDays(\Clock::week()), [
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
		], stock: FALSE);

		$before = $this->render();
		$instructions = $this->cancel($undo);
		$this->check($before, $instructions, PlanningLib::LINE, 'annulée après une écriture de Consigner');

		$this->assertTrue($before->querySelector('#planning-weekly-todo #task-item-'.$eTask['id']) === NULL, 'l\'intervention reste hors d\'À faire');

	}

	/**
	 * Une récolte datée de la semaine passée, sur la récolte qui y était prévue : la semaine affichée la
	 * montre dans « Retardé ». La saisie la termine cette semaine-là (Q1), donc elle quitte À faire sans
	 * entrer dans le Fait affiché, et Annuler l'y remet (Q4).
	 */
	public function testDatedDelayed(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$date = date('Y-m-d', \Clock::shift('-7 days'));

		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek($date));

		$before = $this->render();

		$this->assertTrue($before->querySelector('#planning-weekly-todo #task-item-'.$eTask['id']) !== NULL, 'la récolte de la semaine passée est en retard dans la semaine affichée');

		$instructions = $this->write($this->getPlant(), '12 kg '.DateComplementUi::getToken($date));

		$this->assertEquals($eTask['id'], $this->lastData->reading['eHarvest']['task']['id'], 'la saisie remplit la récolte prévue de la semaine du jour dit');
		$this->assertTrue($this->lastData->change['todo'], 'À faire est redessiné');

		$this->check($before, $instructions, PlanningLib::NONE, 'récolte datée en retard');

		$this->assertTrue($before->querySelector('#planning-weekly-todo #task-item-'.$eTask['id']) === NULL, 'la récolte en retard a quitté À faire');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::NONE, 'récolte datée en retard annulée');

		$this->assertTrue($before->querySelector('#planning-weekly-todo #task-item-'.$eTask['id']) !== NULL, 'la récolte en retard est revenue dans À faire');

	}

	/**
	 * Une récolte datée de la semaine passée, sans récolte prévue : l'intervention créée est faite cette
	 * semaine-là, et la semaine affichée ne change pas.
	 */
	public function testDatedElsewhere(): void {

		$this->setClockInSeason();

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$date = date('Y-m-d', \Clock::shift('-7 days'));

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '12 kg '.DateComplementUi::getToken($date));
		$this->check($before, $instructions, PlanningLib::NONE, 'récolte datée hors de la semaine');

		$this->assertFalse($this->lastData->change['todo'], 'rien d\'À faire à redessiner');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::NONE, 'récolte datée hors de la semaine annulée');

	}

	/**
	 * La semaine passée affichée, et une récolte datée d'un de ses jours : la réponse est celle d'une
	 * récolte du jour sur la semaine courante.
	 */
	public function testDatedDisplayedWeek(): void {

		$this->setClockInSeason();

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$date = date('Y-m-d', \Clock::shift('-7 days'));
		$week = toWeek($date);

		$before = $this->render($week);
		$instructions = $this->write($this->getPlant(), '12 kg '.DateComplementUi::getToken($date), week: $week);
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'récolte datée de la semaine affichée', $week);

		$before = $this->render($week);
		$instructions = $this->cancel($this->lastData->reading['undo'], $week);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'récolte datée de la semaine affichée annulée', $week);

	}

	/**
	 * Une semaine future affichée : elle n'a pas de « Retardé » et ne montre aucun jour passé, donc
	 * l'écriture ne lit pas la semaine et ne la change pas.
	 */
	public function testFutureWeek(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$week = toWeek(date('Y-m-d', \Clock::shift('+14 days')));

		$this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, $week);

		foreach(['', ' hier'] as $day) {

			$before = $this->render($week);
			$instructions = $this->write($this->getPlant(), '12 kg'.$day, week: $week);
			$this->checkOutside($before, $instructions, 'récolte'.$day.' sur une semaine future', $week);

			$before = $this->render($week);
			$instructions = $this->cancel($this->lastData->reading['undo'], $week);
			$this->checkOutside($before, $instructions, 'récolte'.$day.' annulée sur une semaine future', $week);

		}

	}

	/**
	 * Les détails d'une ligne que la parcelle neuve ne porte pas d'elle-même : une récolte prévue avec
	 * son jour, ses personnes et son temps estimé, une autre en retard d'une semaine, et un commentaire
	 * sur l'intervention que la saisie a faite. Ce sont les branches de `PlanningUi::getTaskDetails()`
	 * qui lisent l'état de l'interface (`period`, `cUserStructure`) plutôt que l'intervention seule.
	 */
	public function testDetails(): void {

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$eTaskPlanned = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskPlanned['plannedUsers'] = [$this->getMember()['id']];
		$eTaskPlanned['timeExpected'] = 1.5;
		\series\TaskLib::update($eTaskPlanned, ['plannedUsers', 'timeExpected']);

		$this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, toWeek(date('Y-m-d', \Clock::shift('-7 days'))));

		$before = $this->render();

		$this->assertTrue($before->querySelector('#planning-weekly-todo #task-item-'.$eTaskPlanned['id'].' .tasks-planning-item-more') !== NULL, 'la récolte prévue montre son jour, ses personnes et son temps');

		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'récolte prévue détaillée');

		$eHarvestFirst = $this->lastData->reading['eHarvest'];
		$undoFirst = $this->lastData->reading['undo'];

		$this->assertEquals($eTaskPlanned['id'], $eHarvestFirst['task']['id'], 'la saisie remplit la récolte prévue de la semaine');

		\series\CommentLib::create(new \series\Comment([
			'task' => \series\Task::model()->select(['id', 'structure', 'series', 'cultivation'])->whereId($eHarvestFirst['task'])->get(),
			'text' => 'Commentaire du décor',
		]));

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '5 kg');
		$this->check($before, $instructions, PlanningLib::LINE, 'récolte commentée');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::LINE, 'récolte commentée annulée');

		$before = $this->render();
		$instructions = $this->cancel($undoFirst);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'la récolte prévue retourne à À faire');

		$this->assertTrue($before->querySelector('#planning-weekly-todo #task-item-'.$eTaskPlanned['id']) !== NULL, 'la récolte prévue est de nouveau dans À faire');

	}

	/**
	 * Un filtre d'action qui masque la récolte : rien ne s'allume dans le planning, mais la barre de
	 * filtres, qui compte les interventions lues, change.
	 */
	public function testFiltered(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$this->setViews($this->getAction(ACTION_PLANTATION));

		$before = $this->render();
		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::NONE, 'récolte masquée par le filtre');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::NONE, 'récolte masquée annulée');

	}

	/**
	 * La barre de filtres n'existe pas sur une semaine vide à un seul utilisateur (`getFilters()`) :
	 * elle apparaît avec la récolte et disparaît avec son annulation. Les autres présences de la
	 * structure sont repoussées hors de la semaine, dans la transaction du test.
	 */
	public function testFilters(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		// Écriture de masse : un tableau de valeurs, `update($e)` n'écrirait qu'une ligne.
		\hr\Presence::model()
			->whereStructure($this->eStructure)
			->whereUser('!=', $this->getMember())
			->update(['from' => '2099-01-01']);

		$before = $this->render();

		$this->assertTrue($before->querySelector('#planning-weekly-wrapper > .planning-filters') === NULL, 'pas de barre de filtres avant la récolte');

		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'la barre apparaît');

		$this->checkFilterLinks($before, \Clock::week());

		$before = $this->render();

		$this->assertTrue($before->querySelector('#planning-weekly-wrapper > .planning-filters') !== NULL, 'la barre de filtres est là après la récolte');

		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'la barre disparaît');

		// Une semaine passée porte sa semaine dans le chemin du planning.
		$date = date('Y-m-d', \Clock::shift('-7 days'));
		$week = toWeek($date);

		$before = $this->render($week);
		$instructions = $this->write($this->getPlant(), '12 kg '.DateComplementUi::getToken($date), week: $week);
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'la barre apparaît sur une semaine passée', $week);

		$this->checkFilterLinks($before, $week);

	}

	/**
	 * La barre redessinée par l'action pose ses filtres sur le planning affiché, pas sur la route de la saisie.
	 */
	private function checkFilterLinks(\Dom\HTMLDocument $document, string $week): void {

		$url = \structure\StructureUi::urlPlanningWeekly($this->eStructure, $week).'?plot='.$this->ePlot['id'].'&';
		$cLink = $document->querySelectorAll('#planning-weekly-wrapper > .planning-filters a[href*="Selected="]');

		$this->assertTrue($cLink->length > 0, 'la barre porte des liens de filtre en semaine '.$week);

		foreach($cLink as $link) {
			$this->assertTrue(str_starts_with($link->getAttribute('href'), $url), 'le filtre pointe sur le planning de la semaine '.$week.' ('.$link->getAttribute('href').')');
		}

	}

	/**
	 * Une semaine vide qui porte une échéance du carnet d'entretien n'affiche pas « Le planning est
	 * encore vide. » (`PlanningUi::getTodoPlanning()`) : la récolte qui la remplit n'a rien à retirer, et
	 * son annulation rien à reposer.
	 */
	public function testEmptyWeekWithDue(): void {

		$this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$eTool = \work\ToolLib::getNewTool($this->eStructure, NULL);

		// Sans `build()` : le matériel est le décor, son nom est choisi par le test.
		$eTool['name'] = 'Tracteur '.uniqid();

		\work\ToolLib::create($eTool);

		// Sans `build()` : `build()` lirait le matériel choisi dans l'autocomplétion, alors que le décor
		// suit celui qu'il vient de créer, sans compteur.
		$eEquipment = new \toolbox\Equipment([
			'structure' => $this->eStructure,
			'tool' => $eTool,
			'meter' => NULL,
			'eToolNew' => new \work\Tool(),
		]);

		\toolbox\EquipmentLib::follow($eEquipment, new \toolbox\EquipmentReading());

		// Sans `build()` : l'entretien est le décor, dû aujourd'hui pour tomber dans la semaine affichée.
		\toolbox\MaintenanceLib::create(new \toolbox\Maintenance([
			'structure' => $this->eStructure,
			'equipment' => $eEquipment,
			'name' => 'Vidange moteur',
			'everyValue' => NULL,
			'everyMonths' => 12,
			'plannedDate' => \Clock::date(),
			'plannedValue' => NULL,
		]));

		$before = $this->render();

		if($this->assertDecorPresent($before->querySelector('#planning-weekly-todo #planning-due') !== NULL, 'une échéance dans À faire de la semaine') === FALSE) {
			return;
		}

		$this->assertTrue($before->querySelector('#planning-weekly-todo .util-empty') === NULL, 'pas de « planning vide » sous une échéance');

		$instructions = $this->write($this->getPlant(), '12 kg');
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'la semaine se remplit sous une échéance');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'la semaine se vide sous une échéance');

	}

	/**
	 * Une action hors de Culture, « RH 💡 » en Administration, écrite par le temps puis par la nouvelle
	 * intervention, faite : sans série ni espèce, la ligne rend ce que le rechargement rend (HN-B14). La seconde
	 * ligne demandait une tête d'espèce que le planning ne dessine pas, et la réponse levait après l'écriture.
	 */
	public function testOutsideCulture(): void {

		$eAction = $this->createActionAdministration();

		if($eAction->empty() or $this->requireMember() === FALSE) {
			return;
		}

		$this->ePlot = new \map\Plot();

		$before = $this->render();
		$instructions = $this->getInstructions('doWrite', ['prefix' => '', 'query' => 'temps rh 1 h ']);
		$this->assertEquals('time', $this->lastData->reading['intention']);
		$this->check($before, $instructions, PlanningLib::INSERT_ACTION, 'temps sur RH');

		$undoTime = $this->lastData->reading['undo'];

		// L'intervention à faire que le temps vient de créer est dans Fait, par son temps : la faite entre à
		// côté d'elle, deux lignes sans espèce que nulle tête ne sépare.
		$before = $this->render();
		$instructions = $this->getInstructions('doWrite', ['prefix' => '', 'query' => 'intervention rh ']);
		$this->assertEquals(\series\Task::DONE, $this->lastData->reading['confirmation']['status']);
		$this->check($before, $instructions, PlanningLib::INSERT, 'intervention RH faite');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE, 'intervention RH faite annulée');

		$before = $this->render();
		$instructions = $this->cancel($undoTime);
		$this->check($before, $instructions, PlanningLib::REMOVE_ACTION, 'temps sur RH annulé');

	}

	/**
	 * Une ligne sans espèce entre en tête du bloc de son action dans Fait, avant la tête d'espèce de la ligne
	 * plantée déjà faite : le bloc se trie par nom d'espèce, le vide d'abord (`TaskLib::getForPlanning()`), et
	 * la ligne se pose sous le nom de l'action (`PlanningLib::fillInsert()`).
	 */
	public function testWithoutPlantFirst(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eAction = new \work\Action([
			'name' => 'Zzbloc '.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8),
			'structure' => $this->eStructure,
			'categories' => [$this->getCategoryCulture()['id']],
		]);

		// Sans `build()` : le décor fixe le nom et la catégorie, que le formulaire d'action vérifierait.
		\work\ActionLib::create($eAction);

		$eCultivation = $this->createCultivationOnPlot($this->getPlant(), 'Série A');

		$this->ePlot = new \map\Plot();

		foreach([$eCultivation, new \series\Cultivation()] as $eCultivationTask) {

			$done = $eCultivationTask->notEmpty();

			$eTask = new \series\Task([
				'structure' => $this->eStructure,
				'series' => $done ? $eCultivationTask['series'] : new \series\Series(),
				'cultivation' => $eCultivationTask,
				'plant' => $done ? $eCultivationTask['plant'] : new \plant\Plant(),
				'action' => $eAction,
				'category' => $this->getCategoryCulture(),
				'description' => NULL,
				'status' => $done ? \series\Task::DONE : \series\Task::TODO,
				'plannedWeek' => \Clock::week(),
				'plannedDate' => \Clock::date(),
				'doneWeek' => $done ? \Clock::week() : NULL,
				'doneDate' => $done ? \Clock::date() : NULL,
				'variety' => new \plant\Variety(),
				'harvestSize' => new \plant\Size(),
				'repeatMaster' => new \series\Repeat(),
			]);

			// Sans `build()` : le décor fixe le statut, la semaine et le jour, que `build()` ne prend que d'un
			// formulaire posté.
			\series\TaskLib::create($eTask);

		}

		// Le premier temps du bloc y ajoute qui tape : le bloc se redessine (`PlanningLib::getChange()`).
		$before = $this->render();
		$instructions = $this->writeTime($eAction, $this->getPlant(), '15 min', ['cultivation' => $eCultivation['id']]);
		$this->check($before, $instructions, PlanningLib::ACTION, 'premier temps du bloc');

		$before = $this->render();
		$instructions = $this->getInstructions('doWrite', ['prefix' => '', 'query' => mb_strtolower($eAction['name']).' 1 h ']);
		$this->assertEquals(['action' => $eAction['id']], $this->lastData->change['anchor'], 'la ligne sans espèce se pose sous le nom de l\'action');
		$this->check($before, $instructions, PlanningLib::INSERT, 'ligne sans espèce en tête du bloc');

		$before = $this->render();
		$instructions = $this->cancel($this->lastData->reading['undo']);
		$this->check($before, $instructions, PlanningLib::REMOVE, 'ligne sans espèce retirée de la tête du bloc');

	}

}
?>
