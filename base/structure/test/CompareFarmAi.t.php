<?php
namespace structure;

/**
 * La ferme de départ et la ferme d'en face de l'écran de comparaison (`?farm=` et `?against=`).
 *
 * Les deux ids viennent de l'adresse : n'importe qui peut y écrire l'id d'une ferme qu'il n'a pas le
 * droit de lire. `CommunityAction::prepareCompare()` les valide donc contre la population déjà
 * bornée au sous-groupe regardé **et** filtrée par le partage du plan de culture, et une ferme hors
 * de cette population rend l'écran sans ferme choisie. Trois refus se prouvent ici :
 *
 * 1. une ferme qui ne partage pas son plan de culture ;
 * 2. une ferme hors du sous-groupe regardé ;
 * 3. une ferme d'en face égale à la ferme de départ.
 *
 * Chaque refus est précédé de l'acceptation qui le rend probant : la même ferme, ou le même
 * couple, est retenu quand rien ne l'écarte.
 *
 * L'action est exécutée pour de vrai : la route, puis `CommunityAction::render()`, avec une vue qui
 * ne fait que garder ce que l'action lui passe. Seule l'ordonnance de `render()` prouve que la
 * validation vient après le sous-groupe, et c'était le blocage de la passe architecte sur le plan.
 *
 * Décor : le groupe partageant G4. F6 et F11, la ferme de l'autre associé, partagent leur plan de
 * culture, F9 ne le partage pas ; les deux premières y ont été présentées par deux personnes
 * différentes, ce qui permet à un sous-groupe de n'en garder qu'une. F6 et F7 cultivent la même
 * espèce cette saison, posée en `setUp()` : la frise d'une ferme et le repli sous une espèce
 * partagée n'ont rien à montrer sans elle. Isolation : les cultures et le sous-groupe sont créés dans
 * une transaction, annulée en tearDown().
 *
 * Lancement : dev/test package=structure file=CompareFarmAi
 */
class CompareFarmAiTest extends CommunityFixtureAiTest {

	public function setUp(): void {

		Section::model()->beginTransaction();

		if($this->hasDecor()) {
			$this->createCultivation(self::STRUCTURE_SHARED);
			$this->createCultivation(self::STRUCTURE_SHARING_ONLY);
		}

	}

	public function tearDown(): void {
		Section::model()->rollBack();
	}

	/**
	 * Ce que l'écran de comparaison reçoit pour une adresse donnée, lu par le lecteur principal.
	 */
	private function runCompare(array $get, Section $eSection = new Section()): \stdClass {

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user));

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$getBefore = $_GET;
		$_GET = ['id' => (string)$this->communitySharing] + ($eSection->empty() ? [] : ['section' => (string)$eSection['id']]) + array_map('strval', $get);

		// Ce que le démarrage de l'application pose avant toute page (`ouvretaferme/Lime.s.php`),
		// et que `Page::execute()` ne rejoue pas.
		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		$view = new class(NULL, fn() => NULL) extends \View {

			public ?\stdClass $data = NULL;

			public function getContentType(): string {
				return 'text/html';
			}

			public function render(\stdClass $data, ?\Template $template = NULL): void {
				$this->data = $data;
			}

		};

		try {
			\Page::execute(\Route::getCompatiblePages('groupe/'.$this->communitySharing.'/comparer'), $data);
			$this->assertFail('l\'écran de comparaison n\'a levé aucune action');
		} catch(\CommunityAction $action) {
			$action->render($view);
		} finally {
			$_GET = $getBefore;
			$method->setValue(NULL, $methodBefore);
		}

		return $view->data ?? new \stdClass();

	}

	/**
	 * Les fermes que l'écran met en regard, dans l'ordre où la frise les rend.
	 */
	private function getCompared(\stdClass $data): array {
		return array_values($data->cStructure->getIds());
	}

	public function testFarmMustShareProduction(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED]);

		$this->assertEquals(self::STRUCTURE_SHARED, $data->eStructureFarm['id'] ?? NULL, 'une ferme qui partage son plan de culture est retenue comme ferme de départ');
		$this->assertEquals(self::STRUCTURE_SHARED, $this->getCompared($data)[0] ?? NULL, 'la ferme de départ ouvre la frise');
		$this->assertFalse($data->nothingToCompare, 'avec une ferme choisie, l\'écran garde ses sélecteurs et sa frise');
		$this->assertTrue($data->cSpecies->empty(), 'sans espèce demandée, le mode ferme ne lit pas les espèces du groupe');
		$this->assertTrue($data->cPlantTimeline->notEmpty(), 'la frise de la ferme de départ est lue');
		$this->assertFalse(in_array($this->structureSharedWithoutProduction, $this->getCompared($data)), 'la frise face au réseau ne compte pas la ferme qui ne partage pas');

		$data = $this->runCompare(['farm' => $this->structureSharedWithoutProduction]);

		$this->assertTrue($data->eStructureFarm->empty(), 'une ferme qui ne partage pas son plan de culture n\'est pas retenue, quelle que soit l\'adresse');
		$this->assertTrue($data->cPlantTimeline->empty(), 'sans ferme retenue, aucune frise n\'est lue');

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED, 'against' => $this->structureSharedWithoutProduction]);

		$this->assertTrue($data->eStructureAgainst->empty(), 'une ferme d\'en face qui ne partage pas n\'est pas retenue');

	}

	/**
	 * « Rien à comparer » parle du groupe : il se rend sans ferme choisie quand rien n'est cultivé, et
	 * jamais en mode ferme, où la frise a son propre écran vide.
	 */
	public function testNothingToCompareIsAboutTheGroup(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->runCompare([]);

		$this->assertTrue($data->cSpecies->notEmpty(), 'sans ferme choisie, les espèces du groupe sont lues');
		$this->assertFalse($data->nothingToCompare, 'et le groupe a de quoi comparer');

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED, 'plant' => 1]);

		$this->assertTrue($data->ePlant->empty(), 'une espèce inconnue n\'est pas retenue');
		$this->assertFalse($data->nothingToCompare, 'et le mode ferme retombe sur sa frise, pas sur « Rien à comparer »');

	}

	public function testFarmMustBeInSection(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->runCompare(['farm' => $this->structureOtherAssociate]);

		$this->assertEquals($this->structureOtherAssociate, $data->eStructureFarm['id'] ?? NULL, 'sans sous-groupe, la ferme de l\'autre associé est retenue');

		$eSection = new Section([
			'structure' => $this->getStructure($this->communitySharing),
			'name' => 'Promotion du lecteur principal',
			'status' => Section::OPEN
		]);

		SectionLib::create($eSection);
		SectionLib::associateUser($eSection, new \user\User(['id' => $this->user]));

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED], $eSection);

		$this->assertEquals($eSection['id'], $data->eSection['id'] ?? NULL, 'le sous-groupe demandé est celui que l\'écran regarde');
		$this->assertEquals(self::STRUCTURE_SHARED, $data->eStructureFarm['id'] ?? NULL, 'une ferme du sous-groupe est retenue');

		$data = $this->runCompare(['farm' => $this->structureOtherAssociate], $eSection);

		$this->assertTrue($data->eStructureFarm->empty(), 'une ferme hors du sous-groupe regardé n\'est pas retenue');

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED, 'against' => $this->structureOtherAssociate], $eSection);

		$this->assertTrue($data->eStructureAgainst->empty(), 'une ferme d\'en face hors du sous-groupe n\'est pas retenue');

	}

	public function testAgainstMustDifferFromFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED, 'against' => $this->structureOtherAssociate]);

		$this->assertEquals($this->structureOtherAssociate, $data->eStructureAgainst['id'] ?? NULL, 'deux fermes différentes forment un couple');
		$this->assertEquals([self::STRUCTURE_SHARED, $this->structureOtherAssociate], $this->getCompared($data), 'ferme à ferme, la frise ne porte que les deux fermes');
		$this->assertTrue($data->cStructureFarm->count() > 2, 'la population du compte reste celle des fermes qui partagent, et non les deux comparées');

		$data = $this->runCompare(['farm' => self::STRUCTURE_SHARED, 'against' => self::STRUCTURE_SHARED]);

		$this->assertEquals(self::STRUCTURE_SHARED, $data->eStructureFarm['id'] ?? NULL, 'la ferme de départ reste retenue');
		$this->assertTrue($data->eStructureAgainst->empty(), 'une ferme d\'en face égale à la ferme de départ retombe à vide');
		$this->assertTrue(count($this->getCompared($data)) > 1, 'et la frise repasse face au réseau');

	}

}
