<?php
namespace structure;

/**
 * Ce que les fonctionnalités de groupe décident : un groupe sans type, une ferme, la mise en route
 * d'une AMAP, l'ordre de lecture de `isEducation()` et le drapeau écrit à la création.
 *
 * L'inventaire de la déclaration est dans `CommunityFeatureSentinelAi.t.php`.
 *
 * Lancement : dev/test package=structure file=CommunityFeatureAi
 */
class CommunityFeatureAiTest extends \Test {

	/**
	 * Un groupe sans type n'accepte rien et n'allume rien.
	 *
	 * ⚠️ Le cas n'est pas théorique : mesuré le 2026-09-08, deux groupes de la base de dev portent
	 * `communityType IS NULL`. Sans cette porte, `accepts()` lèverait sur leur écran.
	 */
	public function testAGroupWithoutTypeAcceptsNothing(): void {

		$eStructure = new Structure(['type' => Structure::COMMUNITY, 'communityType' => NULL]);

		foreach(array_keys(CommunityFeatureAiLib::FEATURES) as $feature) {
			$this->assertFalse(CommunityFeatureAiLib::accepts($eStructure, $feature), "un groupe sans type n'accepte pas ".$feature);
		}

		$this->assertEquals([], CommunityFeatureAiLib::getDefaults($eStructure), "un groupe sans type n'allume rien");

	}

	/**
	 * Une ferme n'est pas un groupe : les colonnes existent sur sa ligne, la déclaration ne la
	 * concerne pas.
	 */
	public function testAFarmAcceptsNoCommunityFeature(): void {

		$eStructure = new Structure(['type' => Structure::PRODUCER, 'communityType' => NULL]);

		foreach(array_keys(CommunityFeatureAiLib::FEATURES) as $feature) {
			$this->assertFalse(CommunityFeatureAiLib::accepts($eStructure, $feature), "une ferme n'accepte pas ".$feature);
		}

	}

	/**
	 * Une AMAP a une mise en route, et ce n'est pas celle des groupes : c'est le premier bloc de son
	 * tableau de bord, qui ne demande ni le parcours en trois étapes ni la page de démarrage d'une ferme.
	 */
	public function testAmapStartsWithoutTheCommunityJourney(): void {

		$eStructure = new Structure(['type' => Structure::AMAP, 'communityType' => NULL]);

		$this->assertFalse($eStructure->acceptStartPage(), "une AMAP n'a pas la page de démarrage d'une ferme");
		$this->assertFalse($eStructure->acceptCommunityStart(), "ni le parcours des groupes, dont elle ne dit pas les mots");

	}

	/**
	 * `isEducation()` lit deux propriétés, et l'ordre dans lequel elle les lit est le sujet.
	 *
	 * ⚠️ Une ferme ne porte **pas** `communityType` : sa sélection ne le charge pas et un élément
	 * construit à la main ne le pose pas. Lire l'attribut avant le discriminant rend le bon
	 * résultat en salissant chaque création de ferme d'un `Undefined array key`. C'est pour cela
	 * que le contrôle **capte le warning** au lieu de se fier à la valeur : la valeur est juste
	 * dans les deux ordres, et un contrôle qui ne regarde qu'elle reste vert sur l'ordre fautif.
	 *
	 * ⚠️ Le cas de la ferme est un élément **sans la clé**, et non avec la clé à `NULL` : les deux
	 * ne se comportent pas pareil, et seul le premier reproduit ce que rend la sélection d'une ferme.
	 */
	public function testEducationIsReadInTheRightOrder(): void {

		$eStructureEducation = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::EDUCATION]);
		$eStructureProducerGroup = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::PRODUCER]);
		$eStructureFarm = new Structure(['type' => Structure::PRODUCER]);

		$this->assertTrue($eStructureEducation->isEducation());
		$this->assertFalse($eStructureProducerGroup->isEducation(), "un groupe de producteurs n'est pas un organisme de formation");

		$warning = NULL;

		set_error_handler(function(int $code, string $message) use (&$warning): bool {
			$warning = $message;
			return TRUE;
		}, E_WARNING);

		$answer = $eStructureFarm->isEducation();

		restore_error_handler();

		$this->assertFalse($answer, "une ferme n'est pas un organisme de formation");
		$this->assertEquals(NULL, $warning, "isEducation() a lu communityType sur une ferme : le discriminant doit passer en premier, sans quoi chaque création de ferme salit ses journaux d'un Undefined array key");

	}

	/**
	 * Le drapeau de mise en route s'allume à la création, et pour les seuls groupes qui accueillent du
	 * monde.
	 *
	 * Les trois types de groupe qui accueillent du monde, formation, producteurs et réseau, ont leur
	 * parcours en trois étapes sur leur accueil. Naissent sans : la ferme, l'AMAP, dont la mise en
	 * route est un bloc de son tableau de bord, et le groupe comptable, qui n'a ni fonctionnalité à
	 * choisir ni membre à inviter.
	 *
	 * ⚠️ Le contrôle **écrit** au lieu de relire une ligne existante, et c'est ce qui le rend
	 * capable d'échouer : les groupes du décor portent déjà leur ligne, et l'interroger ne dirait
	 * rien de ce que `createForStructure()` décide aujourd'hui. La ligne est donc effacée puis
	 * refaite dans la transaction, sur le modèle de `AccountingAiTest::testCreateForStructureSetsModelDefaults()`.
	 */
	public function testStartFlagIsOnForCommunities(): void {

		$cases = [
			[Structure::COMMUNITY, Structure::EDUCATION, TRUE, 'un groupe de formation naît avec son parcours'],
			[Structure::COMMUNITY, Structure::PRODUCER, TRUE, 'une communauté de producteurs aussi'],
			[Structure::COMMUNITY, Structure::NETWORK, TRUE, 'un réseau aussi'],
			[Structure::COMMUNITY, Structure::ACCOUNTING, FALSE, "une structure comptable n'a rien à démarrer"],
			[Structure::AMAP, NULL, FALSE, "une AMAP n'en a pas, sa mise en route est sur son tableau de bord"],
			[Structure::PRODUCER, NULL, FALSE, "une ferme n'en a pas"]
		];

		// La décision, pour les six cas et sans la base : c'est elle que `createForStructure()`
		// applique, et aucun type ne manque à l'appel parce qu'aucun n'a besoin d'exister en base.
		foreach($cases as [$type, $communityType, $expected, $message]) {

			$eStructure = new Structure(['type' => $type, 'communityType' => $communityType]);

			$this->assertEquals($expected, $eStructure->acceptCommunityStart(), $message);

		}

		// Puis l'écriture réelle, sur les types que le décor porte. ⚠️ Le compteur et son plancher
		// ne sont pas une précaution de style : sans eux, un décor sans aucun groupe rendrait ce
		// contrôle vert sans avoir rien écrit — et c'est précisément ce qu'il doit prouver.
		$exercised = 0;

		foreach($cases as [$type, $communityType, $expected, $message]) {

			$eStructure = Structure::model()
				->select(['id', 'type', 'communityType', 'legalCountry'])
				->whereId('IN', StructureSetting::FIXTURE_STRUCTURES)
				->whereType($type)
				->whereCommunityType($communityType)
				->whereStatus(Structure::ACTIVE)
				->get();

			if($eStructure->empty()) {
				continue;
			}

			Feature::model()->beginTransaction();

				Feature::model()->whereStructure($eStructure)->delete();

				FeatureLib::createForStructure($eStructure);

				$eFeature = Feature::model()->select(['hasStart'])->whereStructure($eStructure)->get();

				$this->assertEquals($expected, $eFeature['hasStart'], $message.' (écrit par createForStructure)');

			Feature::model()->rollBack();

			$exercised++;

		}

		$this->assertTrue($exercised >= 3, 'trop peu de types présents dans le décor pour éprouver createForStructure() : '.$exercised);

	}


}
?>
