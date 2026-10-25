<?php
namespace structure;

/**
 * Qui lit les statistiques maraîchage du groupe, et ce qu'en garde la liste des fermes (lots L12 et
 * L13 du chantier mes-producteurs).
 *
 * Depuis L13, les statistiques n'ont plus qu'un écran, `/groupe/{id}/maraichage`, sous-onglet
 * « Statistiques » de Maraîchage. Trois conditions l'ouvrent : le groupe héberge des fermes, il a
 * allumé `hasCommunityVegetable`, et le lecteur est de l'équipe. L'écran refuse (`NotAllowedAction`)
 * dès qu'une manque. Chaque refus est précédé de l'acceptation qui le rend probant : le même groupe
 * et le même écran, ouverts à l'équipe quand la fonctionnalité est allumée.
 *
 * Les routes sont exécutées pour de vrai, comme dans `CompareFarmAi` : `Page::execute()`, puis
 * `CommunityAction::render()` sur une vue qui ne garde que ce que l'action lui passe. C'est l'action
 * qui lit les agrégats sur le drapeau posé par la route : seule leur suite prouve que le lecteur
 * refusé ne déclenche aucune lecture.
 *
 * Décor : le groupe partageant G4, que les deux lecteurs du décor administrent. La fonctionnalité s'allume, et le second lecteur redevient simple membre, dans une
 * transaction annulée en `tearDown()`.
 *
 * Lancement : dev/test package=structure file=CommunityVegetableAi
 */
class CommunityVegetableAiTest extends CommunityRouteAiTest {

	private function setVegetable(bool $value): void {
		FeatureLib::updateFeature($this->getStructure($this->communitySharing), 'hasCommunityVegetable', $value);
		$this->forgetStatics();
	}

	/**
	 * Le bloc vide d'un sous-groupe sans ferme (planche production-finir-statistiques-vide) : l'écran
	 * compte les fermes rattachées dans la portée, et ne propose les statistiques de tout le groupe
	 * que si elles s'affichent. La réponse est celle que l'adresse sans sous-groupe rend au même
	 * lecteur, lue dans le même test.
	 *
	 * ⚠️ Le groupe entier ne la calcule pas : il n'a nulle part ailleurs où renvoyer.
	 */
	public function testEmptySectionReadsTheWholeGroup(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$dataCommunity = $this->run('maraichage', $this->user);

		$this->assertTrue($dataCommunity->structures > 0, 'le témoin : le groupe entier a des fermes rattachées');
		$this->assertEquals($dataCommunity->cStructure->count(), $dataCommunity->structures, 'et l\'écran en dit le nombre');
		$this->assertFalse($dataCommunity->hasFiguresCommunity, 'le groupe entier ne renvoie pas à lui-même');

		$eSection = $this->createSection('Promotion sans ferme');

		$data = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertEquals($eSection['id'], $data->eSection['id'] ?? NULL, 'le sous-groupe demandé est celui que l\'écran regarde');
		$this->assertEquals(0, $data->structures, 'aucune ferme rattachée dans le sous-groupe');
		$this->assertFalse($data->hasAboveFloor, 'donc aucune statistique');
		$this->assertFalse($data->hasSharing, 'ni ferme qui accorde la lecture');
		$this->assertFalse($data->hasCompare, 'ni comparaison');
		$this->assertEquals($dataCommunity->structures, $data->cStructureCommunity->count(), 'la liste du groupe entier a traversé le filtre du sous-groupe');
		$this->assertFalse($dataCommunity->hasAboveFloor, 'le témoin : le groupe du décor n\'a pas de statistiques');
		$this->assertFalse($data->hasFiguresCommunity, 'donc le sous-groupe n\'y renvoie pas');

		$this->setFiguresCommunity($dataCommunity->cStructure->getIds());

		$this->assertTrue($this->run('maraichage', $this->user)->hasAboveFloor, 'le témoin : le groupe entier a maintenant des statistiques');

		$data = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertFalse($data->hasAboveFloor, 'le sous-groupe reste vide');
		$this->assertTrue($data->hasFiguresCommunity, 'et renvoie aux statistiques de tout le groupe');

	}

	/**
	 * Trois fermes du groupe déclarent un atelier maraîchage : c'est le plancher, et l'écran du groupe
	 * entier a de quoi s'afficher.
	 */
	private function setFiguresCommunity(array $structures): void {

		foreach(array_slice($structures, 0, \series\VegetableDashboardAiLib::FLOOR) as $structure) {

			// Sans build() : le décor veut un atelier actif et rien d'autre, aucune valeur ne vient
			// d'un formulaire et `fqn` est la constante que le tableau de bord lit.
			ProductionLib::create(new Production([
				'structure' => new Structure(['id' => $structure]),
				'fqn' => \series\VegetableDashboardAiLib::WORKSHOP,
				'organic' => Production::NO,
				'status' => Production::ACTIVE
			]));

		}

		$this->forgetStatics();

	}

	private function createSection(string $name): Section {

		$eSection = new Section([
			'structure' => $this->getStructure($this->communitySharing),
			'name' => $name,
			'status' => Section::OPEN
		]);

		SectionLib::create($eSection);
		$this->forgetStatics();

		return $eSection;

	}

	/**
	 * Le bouton « Comparer les cultures » du bloc vide ne mène jamais au bloc « Rien à comparer pour
	 * l'instant » : il ne s'affiche que si la comparaison a une espèce à proposer, et la réponse est
	 * celle que l'adresse de la comparaison rend au même lecteur, lue dans le même test. Des fermes qui
	 * accordent la lecture sans plan de culture n'ouvrent rien, et la règle continue alors vers les
	 * statistiques de tout le groupe.
	 */
	public function testEmptyScreenComparesOnlyWhatShows(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$data = $this->run('maraichage', $this->user);
		$dataCompare = $this->run('comparer', $this->user);

		$this->assertFalse($data->hasAboveFloor, 'le témoin : l\'écran du groupe est le bloc vide');
		$this->assertTrue($data->hasSharing, 'le témoin : des fermes du groupe accordent la lecture de leur production');
		$this->assertTrue($dataCompare->nothingToCompare, 'le témoin : la comparaison n\'a rien à montrer');
		$this->assertFalse($data->hasCompare, 'donc le bloc vide n\'y renvoie pas');

		// Un sous-groupe où le second lecteur est rangé porte la ferme qu'il a apportée, qui accorde la
		// lecture. Les statistiques du groupe viennent des autres fermes : le sous-groupe reste vide.
		$eSection = $this->createSection('Promotion sans plan de culture');
		SectionLib::associateUser($eSection, \user\UserLib::getById($this->userOther));
		$this->forgetStatics();

		$dataSection = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']]);

		$this->setFiguresCommunity(array_diff($data->cStructure->getIds(), $dataSection->cStructure->getIds()));

		$dataCommunity = $this->run('maraichage', $this->user);

		$this->assertTrue($dataCommunity->hasAboveFloor, 'le témoin : le groupe entier a des statistiques');
		$this->assertFalse($dataCommunity->hasCompare, 'par ses seuls ateliers, et sa comparaison n\'a rien à montrer : son écran n\'y renvoie pas');

		$dataSection = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertTrue($dataSection->structures > 0, 'le témoin : le sous-groupe a des fermes rattachées');
		$this->assertFalse($dataSection->hasAboveFloor, 'le témoin : sans statistiques');
		$this->assertTrue($dataSection->hasSharing, 'le témoin : elles accordent la lecture');
		$this->assertFalse($dataSection->hasCompare, 'sans plan de culture, la comparaison n\'est pas proposée');
		$this->assertTrue($dataSection->hasFiguresCommunity, 'et le bouton devient celui des statistiques de tout le groupe');

		// L'onglet « Cultures » du sous-groupe ne renvoie à la comparaison de tout le groupe que si elle
		// a une espèce à proposer. Elle n'en a pas encore.
		$dataCompare = $this->run('comparer', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertTrue($dataCompare->nothingToCompare, 'le témoin : la comparaison du sous-groupe n\'a rien à montrer');
		$this->assertFalse($dataCompare->hasCompareCommunity, 'celle de tout le groupe non plus, donc le bloc n\'y renvoie pas');

		// Une culture dans une ferme du groupe hors du sous-groupe, qui accorde la lecture.
		$structuresOutside = array_values(array_diff($this->run('comparer', $this->user)->cStructureFarm->getIds(), $dataSection->cStructure->getIds()));

		$this->assertTrue($structuresOutside !== [], 'le témoin : une ferme hors du sous-groupe accorde la lecture');

		$this->createCultivation($structuresOutside[0]);
		\user\ConnectionLib::setOnline(new \user\User());
		$this->forgetStatics();

		$dataCompareCommunity = $this->run('comparer', $this->user);
		$dataCompare = $this->run('comparer', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertFalse($dataCompareCommunity->nothingToCompare, 'le témoin : la comparaison de tout le groupe a une espèce à montrer');
		$this->assertFalse($dataCompareCommunity->hasCompareCommunity, 'et ne renvoie pas à elle-même');
		$this->assertTrue($dataCompare->nothingToCompare, 'le témoin : celle du sous-groupe reste vide');
		$this->assertTrue($dataCompare->hasCompareCommunity, 'donc son bloc renvoie à la comparaison de tout le groupe');
		$this->assertFalse($this->run('maraichage', $this->user, ['section' => (string)$eSection['id']])->hasCompare, 'et les statistiques du sous-groupe ne renvoient toujours pas à sa comparaison vide');

		// Une culture de la saison en cours dans une ferme du sous-groupe : la comparaison a une espèce.
		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user));
		$this->createCultivation($dataSection->cStructure->first()['id']);
		\user\ConnectionLib::setOnline(new \user\User());
		$this->forgetStatics();

		$dataSection = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']]);
		$dataCompare = $this->run('comparer', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertFalse($dataSection->hasAboveFloor, 'le témoin : une culture ne fait pas des statistiques');
		$this->assertFalse($dataCompare->nothingToCompare, 'le témoin : la comparaison du sous-groupe a une espèce à montrer');
		$this->assertTrue($dataSection->hasCompare, 'donc le bloc vide y renvoie');
		$this->assertFalse($dataSection->hasFiguresCommunity, 'et la comparaison passe devant les statistiques de tout le groupe');
		$this->assertFalse($dataCompare->hasCompareCommunity, 'la comparaison du sous-groupe n\'est plus vide, elle ne renvoie nulle part');

		// Le groupe entier passe le plancher par ses seuls ateliers : son écran dit qu'il n'a pas encore
		// de statistiques de culture, et renvoie lui aussi à la comparaison dès qu'elle a une espèce.
		$dataCommunity = $this->run('maraichage', $this->user);

		$this->assertTrue($dataCommunity->hasAboveFloor, 'le témoin : le groupe entier a des statistiques');
		$this->assertTrue($dataCommunity->dashboard['plan']['farms'] < \series\VegetableDashboardAiLib::FLOOR, 'le témoin : aucune ne vient d\'un plan de culture');
		$this->assertFalse($this->run('comparer', $this->user)->nothingToCompare, 'le témoin : la comparaison de tout le groupe a une espèce à montrer');
		$this->assertTrue($dataCommunity->hasCompare, 'donc l\'écran sans plan de culture y renvoie');

	}

	/**
	 * Un lecteur de l'équipe borné à ses sous-groupes n'a pas de groupe entier où aller : son bloc
	 * vide ne propose jamais « Voir les statistiques de tout le groupe », que le même sous-groupe
	 * propose à un lecteur qui voit tout.
	 */
	public function testBoundedReaderIsNotSentToTheWholeGroup(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$eStructure = $this->getStructure($this->communitySharing);
		$eSection = $this->createSection('Promotion du lecteur borné');

		// Sans build() : un rôle de l'équipe borné à ses sous-groupes est le décor, que le produit ne
		// fabrique pas en un geste (même écriture que `EducationReadAi::placeGroup()`).
		$eRole = new Role([
			'structure' => $eStructure,
			'name' => 'Conseiller borné',
			'fqn' => StructureSetting::ROLE_FQNS[0],
			'sections' => Role::OWN,
		]);

		RoleLib::create($eRole);

		Member::model()
			->whereStructure($eStructure)
			->whereUser($this->userOther)
			->update(['role' => $eRole]);

		SectionLib::associateUser($eSection, \user\UserLib::getById($this->userOther));
		$this->forgetStatics();

		$structuresSection = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']])->cStructure->getIds();

		$this->setFiguresCommunity(array_diff($this->run('maraichage', $this->user)->cStructure->getIds(), $structuresSection));

		$dataAll = $this->run('maraichage', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertEquals(NULL, $dataAll->sectionScope, 'le témoin : le premier lecteur voit tout le groupe');
		$this->assertFalse($dataAll->hasAboveFloor, 'le témoin : le sous-groupe n\'a pas de statistiques');
		$this->assertFalse($dataAll->hasCompare, 'le témoin : ni comparaison à montrer');
		$this->assertTrue($dataAll->hasFiguresCommunity, 'le témoin : il est renvoyé aux statistiques de tout le groupe');

		$data = $this->run('maraichage', $this->userOther, ['section' => (string)$eSection['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le lecteur borné ouvre les statistiques de son sous-groupe');
		$this->assertEquals([$eSection['id']], $data->sectionScope, 'le témoin : sa portée est son sous-groupe');
		$this->assertEquals($eSection['id'], $data->eSection['id'] ?? NULL, 'qu\'il regarde');
		$this->assertFalse($data->hasAboveFloor, 'le témoin : son écran est le bloc vide');
		$this->assertFalse($data->hasFiguresCommunity, 'il n\'est pas renvoyé au groupe entier');

		// Même règle sur l'onglet « Cultures » : la comparaison de tout le groupe a une espèce à
		// proposer, le lecteur qui voit tout y est renvoyé, le lecteur borné non.
		$structuresOutside = array_values(array_diff($this->run('comparer', $this->user)->cStructureFarm->getIds(), $structuresSection));

		$this->assertTrue($structuresOutside !== [], 'le témoin : une ferme hors du sous-groupe accorde la lecture');

		$this->createCultivation($structuresOutside[0]);
		\user\ConnectionLib::setOnline(new \user\User());
		$this->forgetStatics();

		$dataCompareAll = $this->run('comparer', $this->user, ['section' => (string)$eSection['id']]);

		$this->assertTrue($dataCompareAll->nothingToCompare, 'le témoin : la comparaison du sous-groupe n\'a rien à montrer');
		$this->assertTrue($dataCompareAll->hasCompareCommunity, 'le témoin : le lecteur qui voit tout est renvoyé à celle de tout le groupe');

		$dataCompare = $this->run('comparer', $this->userOther, ['section' => (string)$eSection['id']]);

		$this->assertEquals([$eSection['id']], $dataCompare->sectionScope, 'le témoin : la portée du lecteur borné est son sous-groupe');
		$this->assertTrue($dataCompare->nothingToCompare, 'le témoin : son écran est le même bloc');
		$this->assertFalse($dataCompare->hasCompareCommunity, 'il n\'est pas renvoyé à la comparaison de tout le groupe');

	}

	/**
	 * Le second lecteur redevient simple membre du groupe. C'est un état de test : le produit ne
	 * retire pas son rôle à un administrateur par cette voie.
	 */
	private function setOtherAsMember(): void {

		$eStructure = $this->getStructure($this->communitySharing);

		Member::model()
			->whereStructure($eStructure)
			->whereUser($this->userOther)
			->update(['role' => RoleLib::getOne($eStructure, 'member')]);

		$this->forgetStatics();

	}

	public function testStaffReadsStatistics(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$data = $this->run('maraichage', $this->user);

		$this->assertTrue($data instanceof \stdClass, 'l\'écran Statistiques s\'ouvre à l\'équipe');
		$this->assertTrue($data->vegetableApply, 'sur les agrégats du tableau de bord');
		$this->assertTrue(is_bool($data->hasAboveFloor), 'et décide seul s\'il cède la place au bloc sous le plancher');

	}

	public function testVegetableOffRefuses(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(FALSE);

		$this->assertEquals('NotAllowedAction', $this->run('maraichage', $this->user), 'l\'écran refuse l\'équipe d\'un groupe qui n\'a pas allumé le maraîchage');

		$data = $this->run('fermes', $this->user);

		$this->assertTrue($data instanceof \stdClass, 'la liste des fermes reste lisible sans le maraîchage');
		$this->assertFalse(isset($data->dashboard), 'et ne lit aucun agrégat');

	}

	public function testMemberRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$this->assertTrue($this->run('maraichage', $this->userOther) instanceof \stdClass, 'le témoin : administrateur, le second lecteur lit l\'écran');

		$this->setOtherAsMember();

		$this->assertEquals('NotAllowedAction', $this->run('maraichage', $this->userOther), 'l\'écran refuse le simple membre');

	}

	/**
	 * La liste des fermes ne porte plus de statistiques (L13) : ni bandeau, ni agrégat, et l'ancienne
	 * adresse de l'écran ne mène plus nulle part, retirée sans redirection (19/09/2026).
	 */
	public function testFarmsCarryNoStatistics(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$data = $this->run('fermes', $this->user);

		$this->assertTrue($data instanceof \stdClass, 'le témoin : la liste des fermes s\'ouvre à l\'équipe d\'un groupe au maraîchage allumé');
		$this->assertFalse(isset($data->dashboard), 'sans lecture d\'agrégats');

		$this->assertEquals([], \Route::getCompatiblePages('groupe/'.$this->communitySharing.'/fermes/statistiques'), 'l\'ancienne adresse ne mène à aucune page');

	}

	/**
	 * Ce que `getStructures()` rend pour ce que la route `/fermes` a préparé, avec un marqueur à la
	 * place de la carte.
	 */
	private function renderFarms(\stdClass $data, \Collection $cStructureProject): string {

		$class = StructureUi::getClass($data->eStructure);

		return new $class()->getStructures(
			$data->eStructure,
			$data->eCommunity,
			$data->cStructureMine,
			$cStructureProject,
			$data->cStructureMember,
			TRUE,
			$data->hasShareable,
			$data->hasProducer,
			$data->hasDuplicate,
			membersHead: '<div id="members-head"></div>'
		);

	}

	/**
	 * La rangée de la liste des fermes rouvre le panneau mémorisé. La ferme projet est P1, que le
	 * décor rattache au groupe partageant.
	 */
	public function testFarmsTabRow(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setVegetable(TRUE);

		$data = $this->run('fermes', $this->user);

		$this->assertEquals([$this->structureProject], $data->cStructureProject->getIds(), 'le témoin : la ferme projet du groupe est lue');

		$html = $this->renderFarms($data, $data->cStructureProject);

		$this->assertTrue(str_contains($html, encode('Lime.Tab.restore(this, "members")')), 'Lime.Tab rouvre le panneau mémorisé');

	}

	/**
	 * Un groupe comptable n'a pas de fermes projet, et la règle vit dans le Lib pour tous ses
	 * appelants (liste des fermes, copie de plan).
	 *
	 * ⚠️ Le témoin est le groupe d'une ferme projet active du décor : le cas non vide doit exister,
	 * sinon un `getProjectByCommunity()` toujours vide passerait.
	 */
	public function testAccountingHasNoProject(): void {

		$eStructureProject = Structure::model()
			->select(['parent' => Structure::getSelection()])
			->whereId('IN', StructureSetting::FIXTURE_STRUCTURES)
			->whereProducerType(Structure::PROJECT)
			->whereStatus(Structure::ACTIVE)
			->whereParent('!=', NULL)
			->get();

		if($eStructureProject->empty()) {
			$this->assertFail('aucune ferme projet active dans le décor : le témoin est introuvable');
			return;
		}

		$eStructureCommunity = $eStructureProject['parent'];

		$this->assertTrue(StructureLib::getProjectByCommunity($eStructureCommunity)->notEmpty(), 'le témoin : le groupe d\'une ferme projet en a');

		$eStructureCommunity['communityType'] = Structure::ACCOUNTING;

		$this->assertTrue(StructureLib::getProjectByCommunity($eStructureCommunity)->empty(), 'le même groupe, comptable, n\'en a pas');

	}

}
?>
