<?php
namespace toolbox;

/**
 * « Ne plus utiliser cet outil » : l'outil sort de `Feature.hasToolbox` et des favoris de chacun,
 * pour chaque outil de l'enum, sans toucher aux autres outils de la structure. Les paniers effacent
 * en plus leur plan, et eux seuls (01/10).
 *
 * Le décor est un plan écrit par le départ sur une structure qui n'en a pas, dans la transaction que
 * `TransactionTest` annule : jamais la structure 7, qui a le sien, et rien ne reste après le test.
 *
 * Lancement : dev/test package=toolbox file=Tool
 */
class ToolTest extends \dev\TransactionTest {

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return Favorite::model();
	}

	public function setUp(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		parent::setUp();

	}

	public function tearDown(): void {

		parent::tearDown();

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testForgetEachTool(): void {

		['structure' => $eStructure, 'user' => $eUser] = $this->start();

		if($eStructure->empty()) {
			return;
		}

		$hasToolbox = fn() => \structure\Feature::model()
			->whereStructure($eStructure)
			->getValue('hasToolbox');

		$countFavorites = fn(string $tool) => Favorite::model()
			->whereStructure($eStructure)
			->whereTool($tool)
			->count();

		$tools = Favorite::model()->getPropertyEnum('tool');

		foreach($tools as $tool) {

			\structure\FeatureLib::addToolbox($eStructure, $tool);

			if(Favorite::model()->whereStructure($eStructure)->whereUser($eUser)->whereTool($tool)->exists() === FALSE) {
				FavoriteLib::create(new Favorite(['structure' => $eStructure, 'user' => $eUser, 'tool' => $tool]));
			}

		}

		foreach($tools as $position => $tool) {

			ToolLib::forget($eStructure, $tool);

			$this->assertFalse(in_array($tool, $hasToolbox()), $tool.' reste parmi les outils utilisés');
			$this->assertEquals(0, $countFavorites($tool), $tool.' reste en favori, son étoile resterait sur un outil gris');

			foreach(array_slice($tools, $position + 1) as $toolNext) {
				$this->assertTrue(in_array($toolNext, $hasToolbox()), 'retirer '.$tool.' a retiré '.$toolNext);
				$this->assertEquals(1, $countFavorites($toolNext), 'retirer '.$tool.' a retiré le favori de '.$toolNext);
			}

		}

		ToolLib::forget($eStructure, $tools[0]);
		$this->assertEquals([], $hasToolbox(), 'retirer un outil déjà retiré touche la liste');

	}

	/**
	 * Quitter les paniers efface tout leur plan : tailles, semaines, lignes et espèces. Les décomptes
	 * d'avant prouvent que chaque étage existait, sans quoi un zéro d'après ne dirait rien.
	 */
	public function testForgetBasketDeletesPlan(): void {

		['structure' => $eStructure] = $this->start();

		if($eStructure->empty()) {
			return;
		}

		foreach($this->countPlan($eStructure) as $level => $count) {
			$this->assertTrue($count > 0, 'le décor n\'a aucun '.$level.' : son effacement ne se verrait pas');
		}

		ToolLib::forget($eStructure, Favorite::BASKET);

		$this->assertEquals(['plan' => 0, 'formula' => 0, 'week' => 0, 'line' => 0, 'species' => 0], $this->countPlan($eStructure), 'quitter les paniers laisse une part de leur plan');
		$this->assertFalse(in_array(Favorite::BASKET, \structure\Feature::model()->whereStructure($eStructure)->getValue('hasToolbox')), 'les paniers restent parmi les outils utilisés');

	}

	/**
	 * Quitter un autre outil ne touche pas aux paniers : ce que la confirmation de chacun promet,
	 * « Ce que vous y avez saisi est conservé ».
	 */
	public function testForgetOtherToolKeepsPlan(): void {

		['structure' => $eStructure] = $this->start();

		if($eStructure->empty()) {
			return;
		}

		$counts = $this->countPlan($eStructure);

		foreach(Favorite::model()->getPropertyEnum('tool') as $tool) {

			if($tool !== Favorite::BASKET) {
				ToolLib::forget($eStructure, $tool);
			}

		}

		$this->assertEquals($counts, $this->countPlan($eStructure), 'quitter un autre outil a touché au plan des paniers');

	}

	/**
	 * La confirmation dit la perte avant le geste, pour les paniers seuls, et le message qui suit
	 * le geste dit qu'ils ont été supprimés.
	 */
	public function testForgetConfirm(): void {

		['structure' => $eStructure] = $this->start();

		if($eStructure->empty()) {
			return;
		}

		\structure\FeatureLib::addToolbox($eStructure, Favorite::RAIN);

		$this->assertTrue($eStructure->canPlanning(), 'l\'administrateur connecté n\'a pas le planning, le geste ne se proposerait pas');

		$render = fn(string $tool) => new ToolUi()->getHeader($eStructure, $tool, new \Collection(), menu: '');

		$basket = $render(Favorite::BASKET);
		$rain = $render(Favorite::RAIN);

		$this->assertTrue(str_contains($basket, 'tool:doForget'), 'le geste manque aux paniers, la confirmation ne se lirait pas');

		$this->assertTrue(str_contains($rain, 'tool:doForget'), 'le geste manque à la pluviométrie, la confirmation ne se lirait pas');

		$this->assertEquals('Les paniers et leurs réglages ont bien été supprimés.', AlertUi::getSuccess('Tool::forgottenBasket'), 'le message qui suit le retrait des paniers');

	}

	/**
	 * @return array{plan: int, formula: int, week: int, line: int, species: int}
	 */
	private function countPlan(\structure\Structure $eStructure): array {

		$planIds = CompositionPlan::model()
			->whereStructure($eStructure)
			->getColumn('id');

		$formulaIds = CompositionFormula::model()
			->wherePlan('IN', $planIds)
			->getColumn('id');

		$weekIds = CompositionWeek::model()
			->whereFormula('IN', $formulaIds)
			->getColumn('id');

		return [
			'plan' => count($planIds),
			'formula' => count($formulaIds),
			'week' => count($weekIds),
			'line' => CompositionLine::model()->whereWeek('IN', $weekIds)->count(),
			'species' => CompositionSpecies::model()->wherePlan('IN', $planIds)->count(),
		];

	}

	/**
	 * Un plan écrit par le départ sur la première structure sans plan dont un administrateur est
	 * actif, en 2099, avec une taille de panier toute l'année et les espèces proposables cochées, sur
	 * le modèle de `CompositionFrequencyTest::createPlan()`. L'administrateur est connecté, le départ
	 * se rendant à qui gère la structure. Vide, avec un échec qui le dit, quand la base de dev n'a
	 * personne à connecter.
	 *
	 * @return array{structure: \structure\Structure, user: \user\User}
	 */
	private function start(): array {

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return ['structure' => new \structure\Structure(), 'user' => new \user\User()];
		}

		\user\ConnectionLib::setOnline($eMember['user']);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2099);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$eCompositionPlan['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le panier', 'price' => 16.0, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		CompositionPlanLib::create($eCompositionPlan);

		return ['structure' => \structure\StructureLib::getById($eStructure['id']), 'user' => $eMember['user']];

	}

}
?>
