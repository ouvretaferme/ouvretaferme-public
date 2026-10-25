<?php
namespace toolbox;

/**
 * L'onglet « Espèces » des paniers (r48a, planche r48, questions 3 à 5) : ce qu'il lit, et ce que
 * ses gestes écrivent et répondent. La saison s'y lit sans s'y régler (r51a) : elle se règle sur la
 * grille d'une taille, et l'onglet la redessine.
 *
 * - la saison en douze mois, sur des semaines posées à la main : la part de chaque mois, celle qui
 *   ne tient qu'à la conservation, le mois coché à partir de la moitié de ses semaines
 *   (`CompositionLib::getSeasonShares()`) ;
 * - la lecture (`CompositionPlanLib::getForScreen()` sous `TAB_SPECIES`) : les espèces livrées dans
 *   l'ordre d'un dictionnaire, sans les retirées, et rien de ce que seule une taille affiche ;
 * - la saison, un dessin seul pour qui administre comme pour qui consulte : ni lien ni menu, et
 *   une saison réglée sur la grille (`compositionSpecies.p.php`, `doUpdateSeason`) s'y
 *   redessine ;
 * - la corbeille et « Ajouter une espèce » (`doExclude`, `composition:doAddSpecies`, r48c) :
 *   l'espèce quitte les paniers puis y revient avec la saison de sa source, sans qu'un second plan
 *   s'écrive (l'ajout d'une espèce neuve est éprouvé par `CompositionSpeciesAddTest`) ;
 * - les réponses : chacune, rejouée sur l'onglet d'avant le geste, rend l'onglet d'un rechargement.
 *
 * Les droits de ces gestes sont éprouvés par `RightsTest::testCompositionSpeciesTab()`. Le bloc
 * « Synchroniser », que seul un plan parti du plan de culture affiche, l'est par
 * `CompositionReferenceTest`.
 *
 * Les gestes s'éprouvent sur un plan écrit par le départ pour une structure qui n'en a pas.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionSpeciesTab
 */
class CompositionSpeciesTabTest extends \dev\TransactionTest {

	const SEASON = 2026;

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return CompositionPlan::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	/**
	 * Trois mois de quatre, quatre et cinq semaines. Le premier est à moitié en saison, donc coché ;
	 * le deuxième est entier, dont la moitié par la conservation ; le troisième ne tient qu'à une
	 * semaine conservée, donc pas coché, et dit qu'il ne tient qu'à elle. Un mois hors saison n'a ni
	 * part ni conservation.
	 */
	public function testSeasonShares(): void {

		$seasonMonths = [1 => [1, 2, 3, 4], 2 => [5, 6, 7, 8], 3 => [9, 10, 11, 12, 13], 4 => [14, 15, 16, 17]];

		$shares = CompositionLib::getSeasonShares($seasonMonths, [3, 4, 5, 6, 7, 8, 9], [7, 8, 9]);

		$this->assertEquals([0.5, 1.0, 0.2, 0.0], array_map('floatval', array_column($shares, 'share')), 'la part de chaque mois en saison');
		$this->assertEquals([0.0, 0.5, 1.0, 0.0], array_map('floatval', array_column($shares, 'stored')), 'la part de la saison que seule la conservation ouvre');
		$this->assertEquals([1 => TRUE, 2 => TRUE, 3 => FALSE, 4 => FALSE], array_combine(array_keys($shares), array_column($shares, 'checked')), 'un mois est coché à partir de la moitié de ses semaines');
		$this->assertEquals([1 => FALSE, 2 => FALSE, 3 => TRUE, 4 => FALSE], array_combine(array_keys($shares), array_column($shares, 'storage')), 'seul le mois entièrement conservé le dit');

		$stored = CompositionLib::getSeasonShares($seasonMonths, [5, 6, 7, 8], [5, 6, 7, 8]);

		$this->assertEquals([TRUE, TRUE], [$stored[2]['checked'], $stored[2]['storage']], 'un mois entier tenu par la conservation est coché et le dit');

	}

	/**
	 * L'onglet lit toutes les espèces du plan, livrées d'un côté et retirées de l'autre, chacune dans
	 * l'ordre d'un dictionnaire et indexée par espèce, avec sa quantité au panier et ses douze mois.
	 * Il ne lit ni taille ouverte ni quantités de l'année, et un plan parti de la communauté n'a rien à
	 * synchroniser.
	 */
	public function testReading(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		// Le départ sur la communauté ne retire aucune espèce d'une structure sans culture : deux se
		// retirent ici, pour que l'onglet ait des retirées à ne pas lire.
		$speciesIds = $this->getTab($eStructure)['cCompositionSpecies']->getKeys();

		$this->exclude($eCompositionPlan, $speciesIds[count($speciesIds) - 1]);
		$this->exclude($eCompositionPlan, $speciesIds[0]);

		$eCompositionPlanTab = $this->getTab($eStructure);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$statuses = CompositionSpeciesLib::getByPlan($eCompositionPlan)->getColumn('status');

		$delivered = $this->getRows($eCompositionPlanTab['cCompositionSpecies']);

		$this->assertTrue(count($delivered) > 10, 'le départ livre plus de dix espèces, sans quoi le contrôle ne verrait rien');
		$excluded = array_keys($statuses, CompositionSpecies::EXCLUDED, TRUE);

		$this->assertTrue(count($excluded) >= 2, 'et deux espèces au moins sont retirées');
		$this->assertEquals(count($statuses), count($delivered) + count($excluded), 'l\'onglet lit toutes les espèces du plan sauf les retirées');
		$this->assertEquals([], array_keys(array_column($delivered, 'status', 'id'), CompositionSpecies::EXCLUDED, TRUE), 'aucune espèce retirée parmi les livrées');
		$this->assertFalse($eCompositionPlanTab->offsetExists('cCompositionSpeciesExcluded'), 'les retirées n\'ont plus de bloc à elles');

		foreach(['livrées' => $delivered] as $label => $rows) {

			$names = array_column($rows, 'name');
			$sorted = $names;
			\L::getCollator()->sort($sorted);

			$this->assertEquals($sorted, $names, 'les espèces '.$label.' dans l\'ordre d\'un dictionnaire');
			$this->assertEquals(array_column($rows, 'id'), array_keys($rows), 'les espèces '.$label.' indexées par espèce du plan');

		}

		$quantities = [];
		$months = [];

		foreach($delivered as $speciesId => $row) {

			if($row['basketQuantity'] !== $source['units'][$speciesId]['quantity']) {
				$quantities[] = $row['name'];
			}

			if(array_keys($row['months']) !== range(1, 12)) {
				$months[] = $row['name'];
			}

		}

		$this->assertEquals([], $quantities, 'chaque espèce porte sa quantité au panier');
		$this->assertEquals([], $months, 'chaque espèce porte ses douze mois');

		$this->assertFalse($eCompositionPlanTab->offsetExists('quantities'), 'l\'onglet ne lit pas les quantités de l\'année');
		$this->assertFalse($eCompositionPlanTab->offsetExists('eCompositionFormula') and $eCompositionPlanTab['eCompositionFormula']->offsetExists('grid'), 'ni la grille d\'une taille');
		$this->assertEquals(0, $eCompositionPlanTab['synchronization']['plants'], 'un plan parti de la communauté n\'a rien à synchroniser');

	}

	/**
	 * La saison se lit dans l'onglet et ne s'y règle pas (r51a) : chaque rang la dessine en douze
	 * barres dans un `span`, sans lien ni menu, pour qui administre aussi. Qui administre a dans la
	 * colonne des boutons la roue crantée de la grille (r59), qui ouvre le même panneau que le nom,
	 * avant la corbeille. Réglée sur la grille d'une taille, par les appels de
	 * `doUpdateSeason`, elle se redessine ici : le mois gardé est entier, les autres sont vides, et
	 * l'espèce sort des paniers des semaines quittées.
	 */
	public function testSeasonDrawn(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$speciesId, $month] = $this->getSpeciesServed($eCompositionPlan, $eStructure);

		$weeksMonth = CompositionLib::getSeasonMonths(self::SEASON)[$month];

		$this->runAsManager($eStructure, function() use($eStructure) {

			$eCompositionPlanTab = $this->getTab($eStructure);
			$document = $this->getDocument(new CompositionUi()->getSpecies($eCompositionPlanTab));

			// La roue de chaque rang ouvre le panneau du nom, puis le « + » d'une variante (r76), puis la
			// corbeille. Le décor ne porte aucune variante : chaque rang a ses trois boutons.
			$gears = [];

			foreach($document->querySelectorAll('#toolbox-composition-species-table .util-table-row') as $row) {

				$buttons = $row->querySelectorAll('.toolbox-composition-species-actions > a');

				$gears[] = [
					$buttons->item(0)->getAttribute('href') === $row->querySelector('a.toolbox-composition-species-link')->getAttribute('href'),
					str_contains($buttons->item(0)->getAttribute('href'), 'origin='.CompositionPlan::TAB_SPECIES),
					str_contains($buttons->item(1)->getAttribute('href'), 'composition:addSpecies'),
					$buttons->item(2)->hasAttribute('data-ajax'),
				];

			}

			$this->assertEquals(
				array_fill(0, $eCompositionPlanTab['cCompositionSpecies']->count(), [TRUE, TRUE, TRUE, TRUE]),
				$gears,
				'chaque rang porte la roue vers le panneau du nom et avec l\'onglet pour origine, puis le « + » qui ajoute une variante, puis la corbeille'
			);

		});

		$this->setSeason($eCompositionPlan, $speciesId, [[min($weeksMonth), max($weeksMonth)]]);

		$row = $this->getRows($this->getTab($eStructure)['cCompositionSpecies'])[$speciesId];

		$this->assertEquals([CompositionSpecies::CUSTOM, $weeksMonth], [$row['status'], $row['weeks']], 'une saison réglée sur la grille est réglée à la main');
		$this->assertEquals([$month => 1.0], array_filter(array_map(fn(array $values) => (float)$values['share'], $row['months'])), 'l\'onglet la redessine : le mois gardé est entier, les autres sont vides');
		$this->assertEquals([], array_values(array_diff($this->getWeeksServed($eCompositionPlan, $speciesId), $weeksMonth)), 'et l\'espèce sort des paniers des semaines quittées');

	}

	/**
	 * La corbeille retire l'espèce de l'onglet et de tous les paniers ; « Ajouter une espèce » lui rend
	 * la saison de sa source, son rang à sa place et ses paniers, sur le même plan. Une espèce livrée
	 * ne s'ajoute plus telle quelle : l'ajout en prépare une variante (r76).
	 */
	public function testExcludeAdd(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$speciesId] = $this->getSpeciesServed($eCompositionPlan, $eStructure);

		$rowsBefore = $this->getRows($this->getTab($eStructure)['cCompositionSpecies']);
		$servedBefore = $this->getWeeksServed($eCompositionPlan, $speciesId);

		// Le panneau cherche et poste l'espèce de la structure, le plan écrit celle du référentiel.
		$plantStructureId = $this->getPlantStructureId($eCompositionPlan, $speciesId);

		$this->assertTrue(CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureId)['eCompositionSpeciesBare']->notEmpty(), 'une espèce livrée ne s\'ajoute qu\'en variante (r76)');

		$this->exclude($eCompositionPlan, $speciesId);

		$eCompositionPlanTab = $this->getTab($eStructure);

		$this->assertFalse($eCompositionPlanTab['cCompositionSpecies']->offsetExists($speciesId), 'retirée, l\'espèce quitte les espèces livrées');
		$this->assertTrue(in_array($plantStructureId, CompositionSpeciesLib::getAddable($eCompositionPlan), TRUE), 'et rejoint celles qui s\'ajoutent');
		$this->assertEquals(count($rowsBefore) - 1, $eCompositionPlanTab['cCompositionSpecies']->count(), 'une espèce livrée de moins');
		$this->assertEquals([], $this->getWeeksServed($eCompositionPlan, $speciesId), 'elle n\'entre plus dans aucun panier');

		$this->add($eCompositionPlan, $speciesId);

		$rowsAfter = $this->getRows($this->getTab($eStructure)['cCompositionSpecies']);

		$this->assertEquals(array_keys($rowsBefore), array_keys($rowsAfter), 'remise, l\'espèce retrouve son rang');
		$this->assertEquals([$rowsBefore[$speciesId]['status'], $rowsBefore[$speciesId]['weeks']], [$rowsAfter[$speciesId]['status'], $rowsAfter[$speciesId]['weeks']], 'avec l\'état et la saison de sa source');
		$this->assertEquals($servedBefore, $this->getWeeksServed($eCompositionPlan, $speciesId), 'et ses paniers');
		$this->assertEquals(1, CompositionPlan::model()->whereStructure($eStructure)->count(), 'sans écrire un second plan');

	}

	/**
	 * Chaque réponse de l'onglet, rejouée sur l'onglet d'avant le geste, rend l'onglet qu'un
	 * rechargement afficherait : la corbeille de la première et de la dernière espèce de l'alphabet,
	 * leur remise (devant un rang, puis au bout du tableau), et un prix enregistré par « Régler une
	 * espèce » ouvert depuis l'onglet.
	 */
	public function testResponses(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$this->runAsManager($eStructure, function() use($eStructure, $eCompositionPlan) {

			[$speciesId] = $this->getSpeciesServed($eCompositionPlan, $eStructure);

			// Une espèce ne se remet que si la structure en porte la copie en activité, que le panneau
			// cherche : la première et la dernière de l'onglet qui en ont une.
			$speciesIds = array_values(array_filter(
				$this->getTab($eStructure)['cCompositionSpecies']->getKeys(),
				fn(int $speciesId) => $this->getPlantStructureId($eCompositionPlan, $speciesId) !== NULL
			));
			$speciesFirst = $speciesIds[0];
			$speciesLast = $speciesIds[count($speciesIds) - 1];

			$gestures = [
				'la première espèce retirée' => ['doExclude', $speciesFirst, fn() => $this->exclude($eCompositionPlan, $speciesFirst)],
				'la dernière espèce retirée' => ['doExclude', $speciesLast, fn() => $this->exclude($eCompositionPlan, $speciesLast)],
				'la première espèce remise' => ['doAddSpecies', $speciesFirst, fn() => $this->add($eCompositionPlan, $speciesFirst)],
				'la dernière espèce remise' => ['doAddSpecies', $speciesLast, fn() => $this->add($eCompositionPlan, $speciesLast)],
				'un prix enregistré' => ['doUpdate', $speciesId, fn() => $this->savePrice($eCompositionPlan, $speciesId, '4.2')],
			];

			$before = new CompositionUi()->getSpecies($this->getTab($eStructure));

			$this->assertTrue(str_contains($before, 'compositionSpecies:doExclude'), 'la corbeille est à l\'écran, sans quoi le contrôle ne serait pas celui de qui écrit');

			foreach($gestures as $label => [$view, $gestureSpeciesId, $gesture]) {

				$gesture();

				$data = new \stdClass();
				$data->eStructure = $eStructure;
				$data->tab = CompositionPlan::TAB_SPECIES;
				$data->eCompositionPlan = $this->getTab($eStructure);
				$data->e = $this->getSpecies($eCompositionPlan, $gestureSpeciesId);
				$data->eCompositionSpecies = $data->e;

				$replayed = $this->getReplayed($before, $this->getViewOutput($view, $data), $label);
				$reloaded = new CompositionUi()->getSpecies($this->getTab($eStructure));

				$this->assertTrue($this->getDocument($reloaded)->body->innerHTML !== $this->getDocument($before)->body->innerHTML, $label.' : le geste change l\'onglet, sans quoi la comparaison ne prouverait rien');
				$this->assertEquals($this->getDocument($reloaded)->body->innerHTML, $replayed, $label.' : la réponse rend l\'onglet d\'un rechargement');

				$before = $reloaded;

			}

		});

	}

	/**
	 * « Semaines livrées » est au-dessus des onglets : la roue de son titre (r51a) dit l'onglet
	 * ouvert, son panneau le réémet, et la réponse de l'enregistrement rend cet onglet et non celui
	 * d'une taille. Le bloc se rend entier, titre compris, le décompte changeant avec les semaines.
	 */
	public function testWeeksFromTab(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$this->runAsManager($eStructure, function() use($eStructure, $eCompositionPlan) {

			$eCompositionPlanTab = $this->getTab($eStructure);
			$card = $this->getDocument(new CompositionUi()->getWeeks($eCompositionPlanTab, CompositionPlan::TAB_SPECIES));

			$gear = $card->querySelector('#toolbox-composition-weeks > .toolbox-composition-heading > a');

			$this->assertEquals('/toolbox/composition:weeks', parse_url($gear->getAttribute('href'), PHP_URL_PATH), 'la roue ouvre le panneau des semaines');

			parse_str(parse_url($gear->getAttribute('href'), PHP_URL_QUERY), $query);

			$this->assertEquals(CompositionPlan::TAB_SPECIES, $query['origin'], 'la roue du titre dit l\'onglet ouvert');

			$eCompositionPlanPanel = CompositionPlanLib::getByStructure($eStructure);
			$eCompositionPlanPanel['structure'] = $eStructure;
			$eCompositionPlanPanel['seasonMonths'] = CompositionLib::getSeasonMonths($eCompositionPlanPanel['season']);
			$eCompositionPlanPanel['formulasAmended'] = CompositionPlanLib::getFormulasAmended($eCompositionPlanPanel);

			$panel = $this->getDocument(new CompositionUi()->getWeeksPanel($eCompositionPlanPanel, NULL, CompositionPlan::TAB_SPECIES)->body);

			$this->assertEquals(CompositionPlan::TAB_SPECIES, $panel->querySelector('input[name="origin"]')->getAttribute('value'), 'le panneau réémet l\'onglet');

			require_once __DIR__.'/../view/composition.v.php';

			$outer = [];

			foreach([CompositionPlan::TAB_SPECIES, CompositionPlan::TAB_FORMULA] as $tab) {

				$data = new \stdClass();
				$data->eStructure = $eStructure;
				$data->tab = $tab;
				$data->eCompositionPlan = CompositionPlanLib::getForScreen($eStructure, NULL, tab: $tab);

				ob_start();
				\View::get('doWeeks', new \ViewAction())->render($data, new \AjaxTemplate());

				foreach(json_decode(ob_get_clean(), TRUE)['__instructions'] as $entry) {
					if($entry[0] === 'qs') {
						$outer[$tab][$entry[1]] = $entry[2][0][1][0];
					}
				}

			}

			$this->assertEquals([TRUE, FALSE], [
				str_contains($outer[CompositionPlan::TAB_SPECIES]['#toolbox-composition-proposal'], 'id="toolbox-composition-species-table"'),
				str_contains($outer[CompositionPlan::TAB_FORMULA]['#toolbox-composition-proposal'], 'id="toolbox-composition-species-table"'),
			], 'la réponse rend le tableau des espèces depuis leur onglet, et lui seul');
			$this->assertEquals([TRUE, TRUE], [
				str_contains($outer[CompositionPlan::TAB_SPECIES]['#toolbox-composition-weeks'], 'origin=species'),
				str_contains($outer[CompositionPlan::TAB_FORMULA]['#toolbox-composition-weeks'], 'origin=formula'),
			], 'et le bloc rendu garde l\'onglet d\'où il part');

		});

	}

	/**
	 * La réponse json de la vue `$view` de `compositionSpecies.v.php`, ou de `composition.v.php` pour
	 * l'ajout (`doAddSpecies`) : aucune vue ne porte le même nom dans les deux fichiers.
	 */
	private function getViewOutput(string $view, \stdClass $data): string {

		require_once __DIR__.'/../view/compositionSpecies.v.php';
		require_once __DIR__.'/../view/composition.v.php';

		ob_start();
		\View::get($view, new \ViewAction())->render($data, new \AjaxTemplate());

		return ob_get_clean();

	}

	/**
	 * L'onglet `$before` une fois jouées sur lui, dans leur ordre, les instructions de la réponse
	 * `$output`, comme `ajax.js` les joue. La fermeture du panneau (`js`) ne touche pas l'onglet. Une
	 * instruction que le rejeu ne connait pas échoue : l'onglet comparé serait faux.
	 */
	private function getReplayed(string $before, string $output, string $label): string {

		$document = $this->getDocument($before);

		foreach(json_decode($output, TRUE)['__instructions'] ?? [] as $entry) {

			if($entry[0] === 'js') {
				continue;
			}

			[$mode, $selector, $instructions] = $entry;

			$node = $document->querySelector($selector);

			if($mode !== 'qs' or $node === NULL) {
				$this->assertFail($label.' : la réponse vise un nœud absent de l\'onglet, '.$selector);
				continue;
			}

			foreach($instructions as [$instruction, $arguments]) {

				// `ajax.js` analyse le html reçu hors de tout contexte : un conteneur neutre fait de même.
				$container = $document->createElement('div');

				switch($instruction) {

					case 'outerHtml' :
						$container->innerHTML = $arguments[0];
						$node->replaceWith(...iterator_to_array($container->childNodes));
						break;

					case 'innerHtml' :
						$node->innerHTML = $arguments[0];
						break;

					case 'insertAdjacentHtml' :
						$container->innerHTML = $arguments[1];
						match($arguments[0]) {
							'beforebegin' => $node->before(...iterator_to_array($container->childNodes)),
							'beforeend' => $node->append(...iterator_to_array($container->childNodes)),
						};
						break;

					case 'setAttribute' :
						$node->setAttribute($arguments[0], is_string($arguments[1]) ? $arguments[1] : json_encode($arguments[1]));
						break;

					case 'removeAttribute' :
						$node->removeAttribute($arguments[0]);
						break;

					case 'remove' :
						$node->remove();
						break;

					default :
						$this->assertFail($label.' : instruction '.$instruction.' que le rejeu ne sait pas jouer');

				}

			}

		}

		return $document->body->innerHTML;

	}

	private function getDocument(string $html): \Dom\HTMLDocument {

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * Le plan tel que l'onglet « Espèces » le lit.
	 */
	private function getTab(\structure\Structure $eStructure): CompositionPlan {

		return CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES);

	}

	/**
	 * Les espèces d'une collection de l'onglet en tableau, `[id d'espèce => […]]`, dans son ordre : une
	 * collection n'a qu'un curseur, et les contrôles la lisent plusieurs fois.
	 */
	private function getRows(\Collection $cCompositionSpecies): array {

		$rows = [];

		foreach($cCompositionSpecies as $speciesId => $eCompositionSpecies) {
			$rows[$speciesId] = [
				'id' => $eCompositionSpecies['id'],
				'name' => $eCompositionSpecies['plant']['name'],
				'status' => $eCompositionSpecies['status'],
				'weeks' => $eCompositionSpecies->getSeasonWeeks(),
				'basketQuantity' => $eCompositionSpecies['basketQuantity'],
				'months' => $eCompositionSpecies['months'],
			];
		}

		return $rows;

	}

	/**
	 * Une espèce livrée et un de ses mois cochés où un panier au moins la sert : celle sur laquelle un
	 * mois décoché a quelque chose à retirer. La structure en porte la copie en activité, sans quoi
	 * « Ajouter une espèce » ne la remettrait pas.
	 */
	private function getSpeciesServed(CompositionPlan $eCompositionPlan, \structure\Structure $eStructure): array {

		$seasonMonths = CompositionLib::getSeasonMonths(self::SEASON);

		foreach($this->getRows($this->getTab($eStructure)['cCompositionSpecies']) as $speciesId => $row) {

			if($this->getPlantStructureId($eCompositionPlan, $speciesId) === NULL) {
				continue;
			}

			$served = $this->getWeeksServed($eCompositionPlan, $speciesId);

			foreach($row['months'] as $month => $values) {
				if($values['checked'] and array_intersect($served, $seasonMonths[$month]) !== []) {
					return [$speciesId, $month];
				}
			}

		}

		throw new \Exception('Aucune espèce du départ n\'est servie dans un de ses mois cochés');

	}

	/**
	 * Les semaines où une taille au moins sert l'espèce.
	 */
	private function getWeeksServed(CompositionPlan $eCompositionPlan, int $speciesId): array {

		$weeks = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {
			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {
				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					if($eCompositionLine['species']['id'] === $speciesId) {
						$weeks[] = $eCompositionWeek['week'];
					}
				}
			}
		}

		$weeks = array_values(array_unique($weeks));
		sort($weeks);

		return $weeks;

	}

	/**
	 * La saison `$windowWeeks`, en intervalles, par les appels de `doUpdateSeason` : c'est ce que la
	 * grille d'une taille poste.
	 */
	private function setSeason(CompositionPlan $eCompositionPlan, int $speciesId, array $windowWeeks): void {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['status', 'windowWeeks'], [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => $windowWeeks,
		]);

		$this->assertEquals([], array_column($fw->get(), 1), 'la saison réglée sur la grille est admise');

		CompositionSpeciesLib::updateSeason($eCompositionSpecies);

	}

	/**
	 * L'espèce retirée, par les appels de `doExclude`.
	 */
	private function exclude(CompositionPlan $eCompositionPlan, int $speciesId): void {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['status'], ['status' => CompositionSpecies::EXCLUDED]);

		$this->assertEquals([], array_column($fw->get(), 1), 'le retrait est admis');

		CompositionSpeciesLib::exclude($eCompositionSpecies);

	}

	/**
	 * L'espèce retirée, remise par « Ajouter une espèce », par les appels de `composition:doAddSpecies`.
	 */
	private function add(CompositionPlan $eCompositionPlan, int $speciesId): void {

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $this->getPlantStructureId($eCompositionPlan, $speciesId));

		$this->assertTrue($eCompositionSpecies->notEmpty(), 'l\'espèce retirée s\'ajoute');
		$this->assertFalse(CompositionSpeciesLib::isClimateAsked($eCompositionSpecies), 'sans demander de climat, la communauté la datant');

		$fw = new \FailWatch();

		// Le panneau renvoie la saison qu'il propose, que personne n'a tirée aux poignées (r59).
		$eCompositionSpecies->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonAdded($eCompositionSpecies, CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies)['windowWeeks']));

		$this->assertEquals([], array_column($fw->get(), 1), 'la saison de la source est admise');

		CompositionSpeciesLib::add($eCompositionSpecies, FALSE);

	}

	/**
	 * L'espèce en activité de la structure qui recopie l'espèce du référentiel partagé que lit
	 * l'espèce `$speciesId` du plan, la plus ancienne : celle que la recherche du panneau poste. `NULL`
	 * quand la structure n'en a pas.
	 */
	private function getPlantStructureId(CompositionPlan $eCompositionPlan, int $speciesId): ?int {

		$ePlant = CompositionSpecies::model()
			->select('plant')
			->wherePlan($eCompositionPlan)
			->whereId($speciesId)
			->get()['plant'];

		return \plant\Plant::model()
			->whereStructure($eCompositionPlan['structure'])
			->whereReference($ePlant)
			->whereStatus(\plant\Plant::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getValue('id');

	}

	/**
	 * Un prix enregistré par « Régler une espèce », par les appels de `doUpdate` : le panneau poste
	 * tous ses champs, et seul ce qui a changé s'écrit.
	 */
	private function savePrice(CompositionPlan $eCompositionPlan, int $speciesId, string $price): void {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$post = ['unit' => '', 'price' => $price, 'step' => '', 'minimum' => '', 'frequency' => ''];

		$fw = new \FailWatch();

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);

		$eCompositionSpecies->build(['unit'], $post);

		CompositionSpeciesLib::loadProducts($eCompositionSpecies);

		$properties = CompositionSpeciesLib::getPropertiesPanel($eCompositionSpecies);

		$eCompositionSpecies->build($properties, $post);

		$this->assertEquals([], array_column($fw->get(), 1), 'le prix est admis');

		$propertiesChanged = CompositionSpeciesLib::getPropertiesChanged($eCompositionSpecies, array_unique(array_merge(['unit'], $properties)));

		$this->assertEquals(['price'], array_values($propertiesChanged), 'seul le prix a changé');

		CompositionSpeciesLib::update($eCompositionSpecies, $propertiesChanged);

	}

	/**
	 * L'espèce telle que la page la charge : sa sélection, son plan et la structure de son plan, puis
	 * la saison qu'elle n'a pas écrite (`applyElement` de `compositionSpecies.p.php`).
	 */
	private function getSpecies(CompositionPlan $eCompositionPlan, int $speciesId): CompositionSpecies {

		$eCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
				'product' => ['name', 'variety'],
			])
			->wherePlan($eCompositionPlan)
			->whereId($speciesId)
			->get();

		$eCompositionSpecies['plan']['structure'] = $eCompositionPlan['structure'];

		CompositionSpeciesLib::applySeasonsUnwritten($eCompositionSpecies['plan'], new \Collection([$eCompositionSpecies]));

		return $eCompositionSpecies;

	}

	/**
	 * `$check` joué par l'administrateur de la structure, connecté le temps du contrôle : c'est à lui
	 * que l'onglet offre ses gestes.
	 */
	private function runAsManager(\structure\Structure $eStructure, \Closure $check): void {

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->where('m2.admin', TRUE)
			->where('m1.structure', $eStructure)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get();

		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$check();
		} finally {
			\user\ConnectionLib::setOnline($this->eUserInitial);
		}

	}

	/**
	 * Un plan écrit par le départ, sur la saison de la communauté.
	 */
	private function createStarted(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = range(10, 40);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * La première structure active qui n'a pas de plan et qu'un administrateur en activité gère : le
	 * départ y écrit le plan du test, et l'onglet lui offre ses gestes.
	 */
	private function getStructureWithoutPlan(): \structure\Structure {

		$eMember = \structure\Member::model()
			->select(['structure' => new \Sql('m1.structure', 'int')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->join(\structure\Structure::model(), 'm1.structure = m4.id')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m4.status', \structure\Structure::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

		return \structure\StructureLib::getById($eMember['structure']);

	}

}
?>
