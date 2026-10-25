<?php
namespace toolbox;

/**
 * L'ordre des rangs de la grille ne dépend pas du tirage (r72, correction 1). Dans un organe, les
 * rangs se rangeaient par nombre de lignes proposées, que chaque tirage change
 * (`CompositionFormulaLib::replay()`, `version + 1`) : la ligne verte d'une espèce glissait d'un rang
 * à l'autre et la grille paraissait changer de saison. Ils se rangent désormais par longueur de
 * saison (`weeksSeason`), puis par nom.
 *
 * - deux rejeux rendent les mêmes rangs dans le même ordre, groupe par groupe ;
 * - dans chaque groupe, la saison la plus longue vient en tête, et à longueur égale le nom départage.
 *
 * Le plan est écrit par le départ, sur 52 semaines, pour une structure qui n'en a pas, sous un
 * administrateur de cette structure.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionGridOrder
 */
class CompositionGridOrderTest extends \dev\TransactionTest {

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

	public function testReplayKeepsOrder(): void {

		$eMember = $this->getManagerWithoutPlan();

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return;
		}

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		\user\ConnectionLib::setOnline($eMember['user']);

		$this->createStarted($eStructure);

		$orderInitial = $this->getOrder($eStructure);

		$this->assertTrue(count(array_merge(...array_values($orderInitial))) > 10, 'la grille a assez de rangs pour qu\'un ordre se lise');

		$this->checkGroups($eStructure, 'tirage 0');

		foreach([1, 2, 3] as $version) {

			$eCompositionFormula = CompositionFormulaLib::getById(CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula']['id'], CompositionFormula::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
			]);

			CompositionFormulaLib::replay($eCompositionFormula);

			$this->assertEquals($version, CompositionFormulaLib::getById($eCompositionFormula['id'])['version'], 'tirage '.$version.' écrit');
			$this->assertEquals($orderInitial, $this->getOrder($eStructure), 'les rangs gardent leur ordre au tirage '.$version);

		}

		$this->checkGroups($eStructure, 'tirage 3');

	}

	/**
	 * Les noms des rangs de la grille, groupe par groupe, dans l'ordre de l'écran.
	 */
	private function getOrder(\structure\Structure $eStructure): array {

		$order = [];

		foreach(CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula']['grid'] as $group) {
			$order[$group['organ'] ?? 'autres'] = array_map(fn(array $row) => $row['species']->getLabel(), $group['species']);
		}

		return $order;

	}

	/**
	 * Dans chaque groupe, la saison la plus longue en tête, puis le nom.
	 */
	private function checkGroups(\structure\Structure $eStructure, string $case): void {

		foreach(CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula']['grid'] as $group) {

			$rows = $group['species'];

			foreach(array_keys($rows) as $index) {

				if($index === 0) {
					continue;
				}

				[$rowBefore, $row] = [$rows[$index - 1], $rows[$index]];

				$compare = (count($rowBefore['weeksSeason']) <=> count($row['weeksSeason'])) ?: -\L::getCollator()->compare($rowBefore['species']->getLabel(), $row['species']->getLabel());

				$this->assertTrue($compare > 0, $case.', groupe '.($group['organ'] ?? 'autres').' : '.$rowBefore['species']->getLabel().' ('.count($rowBefore['weeksSeason']).' semaines) avant '.$row['species']->getLabel().' ('.count($row['weeksSeason']).' semaines)');

			}

		}

	}

	/**
	 * Un plan écrit par le départ, sur la saison de la communauté et sans climat.
	 */
	private function createStarted(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = range(1, 52);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * Le premier administrateur en activité d'une structure qui n'a pas de plan pour la saison et
	 * peut en composer un.
	 */
	private function getManagerWithoutPlan(): \structure\Member {

		$cMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->getCollection(0, 200);

		foreach($cMember as $eMember) {
			if(\structure\StructureLib::getById($eMember['structure']['id'])->acceptComposition()) {
				return $eMember;
			}
		}

		return new \structure\Member();

	}

}
?>
