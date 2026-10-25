<?php
namespace structure;

/**
 * Les deux droits de duplication : ce que la déclaration de `CommunityRightAiLib` en fait.
 *
 * L'inventaire de la déclaration est dans `CommunityRightSentinelAi.t.php`. Ici, les comportements
 * qui s'en dérivent et qu'aucun écran ne montre tous à la fois : le booléen qui ferme la
 * commercialisation, la demande posée à la création d'un groupe de formation, la borne des copies
 * par la lecture, et le parcours de démarrage qui gagne une étape.
 *
 * ⚠️ Les éléments construits en mémoire portent leur ligne `Community` : sans id,
 * `Structure::community()` ne saurait pas où la chercher, et `getRegime()` la lit pour un droit
 * qui porte `enabledBy`.
 *
 * Lancement : dev/test package=structure file=CommunityRightAi
 */
class CommunityRightAiTest extends \Test {

	private function getGroup(string $communityType, bool $hasSellingDuplicate): Structure {

		return new Structure([
			'type' => Structure::COMMUNITY,
			'communityType' => $communityType,
			'community' => new Community([
				'hasSellingDuplicate' => $hasSellingDuplicate,
				'shareProduction' => \Set::fromArray([Community::STAFF]),
				'shareProductionExport' => \Set::fromArray([]),
				'shareProductionDuplicate' => \Set::fromArray([Community::STAFF]),
				'shareSellingDuplicate' => \Set::fromArray([Community::STAFF]),
			]),
		]);

	}

	/**
	 * Booléen faux : la duplication de la commercialisation n'existe nulle part, même demandée en base.
	 *
	 * ⚠️ La colonne `shareSellingDuplicate` porte l'équipe dans ces éléments : c'est ce qui rend le
	 * contrôle capable d'échouer. Une colonne vide rendrait les mêmes réponses sans le booléen.
	 */
	public function testSellingDuplicateIsAbsentWhileBooleanIsFalse(): void {

		foreach([Structure::NETWORK, Structure::EDUCATION] as $type) {

			$eStructureClosed = $this->getGroup($type, FALSE);

			$this->assertEquals(CommunityRightAiLib::ABSENT, CommunityRightAiLib::getRegime($eStructureClosed, 'shareSellingDuplicate'), $type.' fermé : absent');
			$this->assertFalse(CommunityRightAiLib::accepts($eStructureClosed, 'shareSellingDuplicate'), $type.' fermé : refusé');
			$this->assertEquals([], CommunityRightAiLib::getAsked($eStructureClosed, 'shareSellingDuplicate'), $type.' fermé : rien de demandé');
			$this->assertFalse(CommunityRightAiLib::isAsked($eStructureClosed, 'shareSellingDuplicate'), $type.' fermé : pas demandé');
			$this->assertFalse(CommunityRightAiLib::grants($eStructureClosed, 'shareSellingDuplicate', \Set::fromArray([Community::STAFF]), Community::STAFF, [Community::MEMBER]), $type.' fermé : une ferme qui l\'accorde n\'accorde rien');
			$this->assertFalse(in_array('shareSellingDuplicate', CommunityRightAiLib::getAudienceRights($eStructureClosed), TRUE), $type.' fermé : absent des écrans de réglage');

			$eStructureOpen = $this->getGroup($type, TRUE);

			$this->assertEquals(CommunityRightAiLib::OPTIONAL, CommunityRightAiLib::getRegime($eStructureOpen, 'shareSellingDuplicate'), $type.' ouvert : au choix');
			$this->assertEquals([Community::STAFF], CommunityRightAiLib::getAsked($eStructureOpen, 'shareSellingDuplicate'), $type.' ouvert : demandé à l\'équipe');
			$this->assertTrue(CommunityRightAiLib::grants($eStructureOpen, 'shareSellingDuplicate', \Set::fromArray([Community::STAFF]), Community::STAFF, [Community::MEMBER]), $type.' ouvert : accordé à l\'équipe');
			$this->assertTrue(in_array('shareSellingDuplicate', CommunityRightAiLib::getAudienceRights($eStructureOpen), TRUE), $type.' ouvert : présent sur les écrans de réglage');

		}

	}

	/**
	 * La duplication de la production ne vaut que pour l'équipe, même quand la colonne porte les producteurs.
	 */
	public function testDuplicatesAreForStaffOnly(): void {

		$eStructureNetwork = $this->getGroup(Structure::NETWORK, TRUE);

		foreach(['shareProductionDuplicate', 'shareSellingDuplicate'] as $right) {

			$this->assertEquals([Community::STAFF], CommunityRightAiLib::getAudiences($right), $right.' : l\'équipe seule');
			$this->assertFalse(CommunityRightAiLib::grants($eStructureNetwork, $right, \Set::fromArray([Community::STAFF, Community::MEMBER]), Community::MEMBER, [Community::MEMBER]), $right.' : jamais aux producteurs');

		}

	}

	/**
	 * En formation, la duplication de la production est imposée comme la lecture : accordée à
	 * l'équipe quoi que porte la colonne, que ni la ferme ni le groupe ne règlent plus.
	 */
	public function testEducationImposesDuplication(): void {

		$eStructureEducation = $this->getGroup(Structure::EDUCATION, TRUE);

		$this->assertEquals(CommunityRightAiLib::IMPOSED, CommunityRightAiLib::getRegime($eStructureEducation, 'shareProductionDuplicate'));
		$this->assertFalse(CommunityRightAiLib::isOptional($eStructureEducation, 'shareProductionDuplicate'), 'le groupe ne le règle plus');
		$this->assertTrue($eStructureEducation->imposesCommunityRight('shareProductionDuplicate'), 'imposé');
		$this->assertEquals([Community::STAFF], CommunityRightAiLib::getGranted($eStructureEducation, 'shareProductionDuplicate', new \Set(0)), 'accordé à l\'équipe sur une colonne à zéro');
		$this->assertTrue(CommunityRightAiLib::grants($eStructureEducation, 'shareProductionDuplicate', new \Set(0), Community::STAFF, [Community::MEMBER]), 'la ferme d\'un étudiant se duplique par l\'équipe');

		$this->assertEquals(CommunityRightAiLib::OPTIONAL, CommunityRightAiLib::getRegime($this->getGroup(Structure::NETWORK, TRUE), 'shareProductionDuplicate'), 'un réseau garde le choix');

	}

	/**
	 * La création n'écrit aucune demande, sur une ligne `Community` refaite dans la transaction.
	 *
	 * ⚠️ Le contrôle **écrit** au lieu de relire une ligne existante : les groupes de la base portent
	 * déjà la leur, et l'interroger ne dirait rien de ce que `createForStructure()` décide
	 * aujourd'hui. Même procédé que `CommunityFeatureAiTest::testStartFlagIsOnForCommunities()`.
	 */
	public function testCreationAsksNothing(): void {

		foreach([Structure::EDUCATION, Structure::NETWORK] as $type) {

			$eStructure = Structure::model()
				->select(['id', 'name', 'type', 'communityType'])
				->whereId('IN', StructureSetting::FIXTURE_STRUCTURES)
				->whereType(Structure::COMMUNITY)
				->whereCommunityType($type)
				->whereStatus(Structure::ACTIVE)
				->get();

			if($eStructure->empty()) {
				$this->assertFail('aucun groupe '.$type.' actif dans le décor pour éprouver createForStructure()');
				continue;
			}

			Community::model()->beginTransaction();

				Community::model()->whereStructure($eStructure)->delete();

				CommunityLib::createForStructure($eStructure);

				$eCommunity = Community::model()
					->select(['shareProduction', 'shareProductionDuplicate', 'shareSellingDuplicate'])
					->whereStructure($eStructure)
					->get();

				$this->assertEquals(0, $eCommunity['shareProductionDuplicate']->get(), $type.' : duplication de la production jamais demandée à la création');
				$this->assertEquals(0, $eCommunity['shareSellingDuplicate']->get(), $type.' : duplication de la commercialisation jamais demandée à la création');
				$this->assertEquals(0, $eCommunity['shareProduction']->get(), $type.' : la lecture non plus');

			Community::model()->rollBack();

		}

	}

	/**
	 * Une copie se réduit aux publics qui lisent ; la lecture ne se réduit à rien.
	 *
	 * Les écritures en base qui s'en servent sont éprouvées dans `AffiliationRightAiTest`.
	 */
	public function testCopiesAreBoundToRead(): void {

		$readByMembers = fn(string $right) => ($right === 'shareProduction') ? [Community::MEMBER] : [];

		$this->assertEquals([Community::MEMBER], CommunityRightAiLib::getBoundToRequired('shareProductionExport', [Community::STAFF, Community::MEMBER], $readByMembers), 'la copie garde les producteurs qui lisent');
		$this->assertEquals([], CommunityRightAiLib::getBoundToRequired('shareProductionDuplicate', [Community::STAFF], $readByMembers), 'la duplication perd l\'équipe qui ne lit pas');
		$this->assertEquals([Community::STAFF], CommunityRightAiLib::getBoundToRequired('shareSellingDuplicate', [Community::STAFF], $readByMembers), 'la commercialisation ne dépend pas de la lecture');
		$this->assertEquals([Community::STAFF], CommunityRightAiLib::getBoundToRequired('shareProduction', [Community::STAFF], fn(string $right) => []), 'la lecture ne dépend de rien');

		$granted = fn(string $right) => [Community::STAFF];

		$this->assertEquals(['shareProduction' => []], CommunityRightAiLib::getGrantChange('shareProduction', Community::STAFF, FALSE, $granted), 'une bascule n\'écrit que son droit');
		$this->assertEquals(['shareProduction' => [Community::STAFF, Community::MEMBER]], CommunityRightAiLib::getGrantChange('shareProduction', Community::MEMBER, TRUE, $granted), 'accorder ajoute le public');

	}

	/**
	 * Accorder une copie sans la lecture se refuse ; la commercialisation ne dépend de rien.
	 */
	public function testCopyWithoutReadIsRefused(): void {

		$readByMembers = fn(string $right) => ($right === 'shareProduction') ? [Community::MEMBER] : [];
		$readByStaff = fn(string $right) => ($right === 'shareProduction') ? [Community::STAFF] : [];

		foreach(['shareProductionExport', 'shareProductionDuplicate'] as $right) {
			$this->assertFalse(CommunityRightAiLib::acceptsGrant($right, Community::STAFF, $readByMembers), $right.' : refusé à l\'équipe qui ne lit pas');
			$this->assertTrue(CommunityRightAiLib::acceptsGrant($right, Community::STAFF, $readByStaff), $right.' : accepté pour l\'équipe qui lit');
		}

		$this->assertTrue(CommunityRightAiLib::acceptsGrant('shareSellingDuplicate', Community::STAFF, fn(string $right) => []), 'la commercialisation s\'accorde sans la lecture');

	}

	/**
	 * Rejoindre sans apporter de ferme ne se propose qu'en formation : ailleurs, l'invité qui
	 * administre une ferme choisit laquelle il partage (décision du 26/09/2026).
	 */
	public function testJoinWithoutFarmIsEducationOnly(): void {

		foreach([Structure::EDUCATION => TRUE, Structure::NETWORK => FALSE, Structure::PRODUCER => FALSE, Structure::ACCOUNTING => FALSE] as $type => $expected) {
			$this->assertEquals($expected, $this->getGroup($type, FALSE)->acceptJoinWithoutFarm(), $type);
		}

		$this->assertFalse(new Structure(['type' => Structure::PRODUCER])->acceptJoinWithoutFarm(), 'une ferme');

	}

	/**
	 * En formation, le démarrage n'a d'étape de partage que si la duplication des ventes, seul droit
	 * au choix, est ouverte sur le groupe.
	 */
	public function testEducationStartSharesOnlyWhatIsOptional(): void {

		$eStructureEducation = $this->getGroup(Structure::EDUCATION, FALSE);

		$this->assertEquals(0, CommunityStartAiUi::getStepNumber($eStructureEducation, 'share'), 'rien à décider, pas d\'étape de partage');
		$this->assertEquals(2, CommunityStartAiUi::getStepNumber($eStructureEducation, 'invite'), 'les invitations suivent les fonctionnalités, sans étape de présentation');

		$eStructureEducation = $this->getGroup(Structure::EDUCATION, TRUE);

		$this->assertEquals(2, CommunityStartAiUi::getStepNumber($eStructureEducation, 'share'), 'la duplication des ventes ouverte ramène l\'étape de partage');
		$this->assertEquals(3, CommunityStartAiUi::getStepNumber($eStructureEducation, 'invite'), 'les invitations passent en troisième');

	}

	/**
	 * Un réseau ne voit l'étape de partage que si Maraîchage, seule fonctionnalité qui lit ses
	 * droits, est allumé. Une communauté de producteurs garde la sienne (lot MR3 de la mise en route).
	 *
	 * ⚠️ Les éléments portent aussi leur ligne `Feature`, pour la raison écrite en tête du fichier
	 * sur `Community` : `startWith` la lit.
	 */
	public function testNetworkStartSharesOnlyWithVegetable(): void {

		$eStructureNetwork = $this->getGroupWithFeatures(Structure::NETWORK, FALSE, ['hasCommunityLearning' => TRUE]);

		$this->assertEquals(0, CommunityStartAiUi::getStepNumber($eStructureNetwork, 'share'), 'réseau aux formations seules : pas d\'étape de partage');
		$this->assertEquals(3, CommunityStartAiUi::getStepNumber($eStructureNetwork, 'invite'), 'les invitations suivent les fonctionnalités');
		$this->assertTrue(CommunityRightAiLib::isOptional($eStructureNetwork, 'shareProduction'), 'le paramétrage garde le droit au choix');

		$eStructureNetwork = $this->getGroupWithFeatures(Structure::NETWORK, FALSE, ['hasQuotation' => TRUE]);

		$this->assertEquals(0, CommunityStartAiUi::getStepNumber($eStructureNetwork, 'share'), 'les relevés de prix ne ramènent pas l\'étape');

		$eStructureNetwork = $this->getGroupWithFeatures(Structure::NETWORK, FALSE, ['hasCommunityVegetable' => TRUE]);

		$this->assertEquals(3, CommunityStartAiUi::getStepNumber($eStructureNetwork, 'share'), 'Maraîchage allumé ramène l\'étape');
		$this->assertEquals(4, CommunityStartAiUi::getStepNumber($eStructureNetwork, 'invite'), 'les invitations passent en quatrième');

		$eStructureNetwork = $this->getGroupWithFeatures(Structure::NETWORK, TRUE, []);

		$this->assertEquals(3, CommunityStartAiUi::getStepNumber($eStructureNetwork, 'share'), 'la duplication des ventes ouverte à la main ramène l\'étape');
		$this->assertTrue(CommunityRightAiLib::acceptsStart($eStructureNetwork, 'shareSellingDuplicate'), 'qui ne pose qu\'elle');
		$this->assertFalse(CommunityRightAiLib::acceptsStart($eStructureNetwork, 'shareProduction'), 'et pas la production');

		$eStructureProducer = $this->getGroupWithFeatures(Structure::PRODUCER, FALSE, []);

		$this->assertEquals(3, CommunityStartAiUi::getStepNumber($eStructureProducer, 'share'), 'une communauté de producteurs garde son étape sans Maraîchage');

	}

	/**
	 * Le paramétrage d'un groupe se range en onglets dès qu'il a un partage à montrer, et ces onglets
	 * tiennent lieu de titre. Une AMAP porte en base les mêmes droits qu'un groupe, que son type ne
	 * lui ouvre pas.
	 */
	public function testSharingSettingsFollowAudienceRights(): void {

		foreach([Structure::NETWORK, Structure::EDUCATION] as $type) {

			$eStructure = $this->getGroup($type, TRUE);

			$this->assertTrue($eStructure->getCommunityAudienceRights() !== [], $type.' : des droits se règlent');
			$this->assertTrue($eStructure->acceptCommunitySharing(), $type.' : le paramétrage a ses onglets');

		}

		$eStructureAmap = $this->getGroup(Structure::NETWORK, TRUE);
		$eStructureAmap['type'] = Structure::AMAP;

		$this->assertFalse($eStructureAmap->acceptCommunitySharing(), 'une AMAP n\'a pas de partage des données');

	}

	private function getGroupWithFeatures(string $communityType, bool $hasSellingDuplicate, array $features): Structure {

		// Un id inexistant, pour que les fonctionnalités se posent en mémoire sans rien lire en base
		$eStructure = $this->getGroup($communityType, $hasSellingDuplicate);
		$eStructure['id'] = 99999103;
		$eStructure->conf(Feature::class)->merge($features + array_fill_keys(array_keys(CommunityFeatureAiLib::FEATURES), FALSE));

		return $eStructure;

	}

}
?>
