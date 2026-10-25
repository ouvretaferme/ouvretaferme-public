<?php
namespace structure;

/**
 * La déclaration des droits de partage dit-elle encore la vérité du code ?
 *
 * `CommunityRightAiLib` est le seul endroit du dépôt qui apparie un droit, sa nature, et ce que
 * chaque type de groupe en fait. Rien ne rappellera cette table à qui ajoutera un cinquième type de
 * groupe, ou renommera une colonne de partage, dans six mois depuis un autre package : d'où cette
 * sentinelle, qui ne vérifie aucun comportement mais un **inventaire**, et échoue en nommant
 * l'écart.
 *
 * Elle compare dans les deux sens : le couple apparu qu'on n'a pas déclaré, et la déclaration
 * devenue fausse parce que la colonne ou le type a disparu.
 *
 * ⚠️ Les deux tables portent les mêmes noms de droit, et ce n'est pas une commodité : `Community`
 * dit ce que le groupe demande, `Affiliation` ce que la ferme accorde, et les comparer suppose de
 * les nommer pareil. La sentinelle exige donc la colonne des deux côtés.
 *
 * Lancement : dev/test package=structure file=CommunityRightSentinelAi
 */
class CommunityRightSentinelAiTest extends \Test {

	private function getTypes(): array {

		$types = Structure::model()->getPropertyEnum('communityType');

		sort($types);

		return $types;

	}

	/**
	 * Chaque droit déclaré porte-t-il ses deux colonnes réelles, et les quatre types ?
	 *
	 * Une clé sans colonne écrirait dans le vide, et un type manquant ferait lever `getRegime()`
	 * sur un `Undefined array key` — c'est-à-dire sur l'écran d'un groupe, en production, plutôt
	 * qu'ici.
	 */
	public function testEveryRightIsAColumnOnBothTablesAndCoversEveryType(): void {

		$community = Community::model()->getProperties();
		$affiliation = Affiliation::model()->getProperties();
		$types = $this->getTypes();

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			$this->assertTrue(in_array($right, $community, TRUE), "la colonne ".$right." existe encore sur Community");
			$this->assertTrue(in_array($right, $affiliation, TRUE), "la colonne ".$right." existe encore sur Affiliation");

			$declared = array_keys($definition['types']);
			sort($declared);

			$this->assertEquals($types, $declared, "le droit ".$right." déclare les quatre types");

		}

	}

	/**
	 * Aucun régime hors des quatre, aucune nature hors des deux.
	 *
	 * Une faute de frappe ne lèverait rien : `accepts()` compare à ce qui ouvre, donc un régime
	 * inconnu ferme le droit sur un écran où on l'attendait, sans message. Ici il est nommé.
	 */
	public function testEveryRegimeAndNatureIsDeclared(): void {

		$regimes = [CommunityRightAiLib::ABSENT, CommunityRightAiLib::IMPOSED, CommunityRightAiLib::OPTIONAL];
		$natures = [CommunityRightAiLib::AUDIENCE, CommunityRightAiLib::SILENT];

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			$this->assertTrue(in_array($definition['nature'], $natures, TRUE), "la nature de ".$right." est déclarée");

			foreach($definition['types'] as $type => $regime) {
				$this->assertTrue(in_array($regime, $regimes, TRUE), "le régime de ".$right." sur ".$type." est déclaré");
			}

		}

	}

	/**
	 * Aucune clé de déclaration hors de celles que le Lib lit.
	 *
	 * ⚠️ Une clé mal orthographiée ne lève rien, elle est ignorée : `require` au lieu de `requires`
	 * rendrait un droit de copie accordable sans la lecture, et `enabled` au lieu de `enabledBy`
	 * ouvrirait à tous les groupes un droit qui devait attendre son booléen.
	 */
	public function testEveryKeyIsRead(): void {

		$keys = ['nature', 'types', 'audiences', 'requires', 'enabledBy', 'reach', 'imposedTo', 'startWith'];

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			$unknown = array_diff(array_keys($definition), $keys);

			$this->assertEquals([], array_values($unknown), "le droit ".$right." déclare des clés que personne ne lit : ".implode(', ', $unknown));

		}

	}

	/**
	 * `audiences` restreint les publics du `set`, sans en inventer et sans les vider.
	 *
	 * Vide, le droit disparaîtrait des écrans de réglage sans message, `getAudienceRights()` le
	 * prenant pour un droit sans public. Sur un droit sans public, la clé ne voudrait rien dire.
	 */
	public function testDeclaredAudiencesAreASubsetOfTheSet(): void {

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			if(array_key_exists('audiences', $definition) === FALSE) {
				continue;
			}

			$this->assertEquals(CommunityRightAiLib::AUDIENCE, $definition['nature'], "le droit ".$right." déclare des publics et n'en a pas");
			$this->assertDiffer([], $definition['audiences'], "le droit ".$right." déclare une liste de publics vide");

			$outside = array_diff($definition['audiences'], CommunityRightAiLib::AUDIENCES);

			$this->assertEquals([], array_values($outside), "le droit ".$right." déclare des publics hors du set : ".implode(', ', $outside));

		}

	}

	/**
	 * `imposedTo` ne se déclare que sur un type qui impose le droit, et parmi ses publics.
	 *
	 * Sur un type au choix ou absent, la clé ne serait jamais lue et laisserait croire à une
	 * restriction qui n'existe pas. Hors des publics du droit, elle accorderait un public que les
	 * écrans de réglage ne montrent nulle part.
	 */
	public function testImposedAudiencesBoundToImposedTypes(): void {

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			foreach($definition['imposedTo'] ?? [] as $type => $audiences) {

				$this->assertEquals(CommunityRightAiLib::IMPOSED, $definition['types'][$type], "le droit ".$right." borne les publics imposés du type ".$type.", qui ne l'impose pas");
				$this->assertDiffer([], $audiences, "le droit ".$right." n'impose rien au type ".$type);

				$outside = array_diff($audiences, CommunityRightAiLib::getAudiences($right));

				$this->assertEquals([], array_values($outside), "le droit ".$right." impose au type ".$type." des publics qu'il n'a pas : ".implode(', ', $outside));

			}

		}

	}

	/**
	 * `startWith` ne se déclare que sur un type où le droit est au choix, et ne nomme que des
	 * fonctionnalités que ce type peut allumer.
	 *
	 * Sur un type qui impose le droit ou ne l'a pas, la mise en route n'a pas de question à poser et
	 * la clé ne serait jamais lue. Une fonctionnalité inconnue ou absente du type ne s'allumerait
	 * jamais, et l'étape de partage disparaîtrait pour toujours sans que rien ne le dise.
	 */
	public function testStartWithBoundToOptionalTypesAndTheirFeatures(): void {

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			foreach($definition['startWith'] ?? [] as $type => $features) {

				$this->assertEquals(CommunityRightAiLib::OPTIONAL, $definition['types'][$type], "le droit ".$right." attend une fonctionnalité au démarrage du type ".$type.", où il n'est pas au choix");
				$this->assertDiffer([], $features, "le droit ".$right." n'attend aucune fonctionnalité au démarrage du type ".$type);

				foreach($features as $feature) {

					$isFeature = array_key_exists($feature, CommunityFeatureAiLib::FEATURES);

					$this->assertTrue($isFeature, "le droit ".$right." attend au démarrage ".$feature.", qui n'est pas une fonctionnalité");

					// getCommunityTypes() lève sur une fonctionnalité inconnue, et l'exception arrêterait la méthode avant les écarts suivants
					if($isFeature === FALSE) {
						continue;
					}

					$this->assertTrue(in_array($type, CommunityFeatureAiLib::getCommunityTypes($feature), TRUE), "le droit ".$right." attend au démarrage du type ".$type." la fonctionnalité ".$feature.", que ce type ne peut pas allumer");

				}

			}

		}

	}

	/**
	 * `requires` pointe un droit déclaré, sans chaîne, qui couvre les publics et les types de l'enfant.
	 *
	 * ⚠️ **Sans chaîne** : l'action ne retire que les enfants directs du droit retiré, et l'écran ne
	 * verrouille que sur le parent direct. Un petit-enfant resterait accordé sans la lecture.
	 * **Publics couverts** : un public de l'enfant absent du parent ne s'accorderait jamais, verrouillé
	 * pour toujours. **Types couverts** : un enfant présent là où son parent est absent serait de même
	 * inaccordable.
	 */
	public function testRequiresPointsToAParentThatCoversTheChild(): void {

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			if(array_key_exists('requires', $definition) === FALSE) {
				continue;
			}

			$parent = $definition['requires'];

			if(array_key_exists($parent, CommunityRightAiLib::RIGHTS) === FALSE) {
				$this->assertFail("le droit ".$right." dépend de ".$parent.", qui n'est pas déclaré");
				continue;
			}

			$this->assertFalse(array_key_exists('requires', CommunityRightAiLib::RIGHTS[$parent]), "le droit ".$right." dépend de ".$parent.", qui dépend lui-même d'un autre droit");

			$uncovered = array_diff(CommunityRightAiLib::getAudiences($right), CommunityRightAiLib::getAudiences($parent));

			$this->assertEquals([], array_values($uncovered), "le droit ".$right." a des publics que ".$parent." n'a pas : ".implode(', ', $uncovered));

			foreach($definition['types'] as $type => $regime) {

				if($regime !== CommunityRightAiLib::ABSENT) {
					$this->assertDiffer(CommunityRightAiLib::ABSENT, CommunityRightAiLib::RIGHTS[$parent]['types'][$type], "le droit ".$right." existe sur ".$type." où ".$parent." est absent");
				}

			}

		}

	}

	/**
	 * `enabledBy` pointe un booléen de `Community` qu'aucun formulaire ne construit.
	 *
	 * ⚠️ Le booléen se pose à la main en base, groupe par groupe (décidé le 14/09/2026) :
	 * qu'il entre dans une liste de propriétés du Lib, et un administrateur de groupe ouvrirait lui-même
	 * ce qu'on lui ouvre.
	 */
	public function testEnabledByIsAnUnbuiltBoolean(): void {

		$properties = [...CommunityLib::getPropertiesCreate(), ...CommunityLib::getPropertiesUpdate()];

		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			if(array_key_exists('enabledBy', $definition) === FALSE) {
				continue;
			}

			$column = $definition['enabledBy'];

			if(in_array($column, Community::model()->getProperties(), TRUE) === FALSE) {
				$this->assertFail("le droit ".$right." attend la colonne ".$column.", absente de Community");
				continue;
			}

			$this->assertEquals('bool', Community::model()->getPropertyType($column), "la colonne ".$column." qui ouvre ".$right." est un booléen");
			$this->assertFalse(in_array($column, $properties, TRUE), "la colonne ".$column." qui ouvre ".$right." ne se construit pas par le Lib");

		}

	}

	/**
	 * Les publics sont ceux du `set` des deux colonnes, et pas une liste écrite à côté.
	 *
	 * ⚠️ C'est le contrôle qui a le plus de chances de servir : ajouter un public est une ligne de
	 * `.yml`, et rien n'obligerait à l'ajouter ici. Les écrans ne montreraient alors que deux
	 * colonnes sur trois, sans erreur.
	 */
	public function testAudiencesMatchTheColumnSet(): void {

		$declared = CommunityRightAiLib::AUDIENCES;
		sort($declared);

		foreach(['Community' => Community::model(), 'Affiliation' => Affiliation::model()] as $name => $mModule) {

			foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

				if($definition['nature'] !== CommunityRightAiLib::AUDIENCE) {
					continue;
				}

				$set = $mModule->getPropertySet($right);
				sort($set);

				$this->assertEquals($declared, $set, "les publics de ".$right." sur ".$name." sont ceux de AUDIENCES");

			}

		}

	}

	/**
	 * Un droit sans public n'a pas de `set`, et un droit à public en a un.
	 *
	 * Les deux sens comptent : déclarer `AUDIENCE` sur une colonne booléenne ferait cocher des
	 * publics qui n'iraient nulle part, et `SILENT` sur un `set` en perdrait la moitié.
	 */
	public function testNatureMatchesTheColumnType(): void {

		// ⚠️ `getPropertyType()` et non `getPropertySet()` : la seconde rend `$properties[$x][1]`,
		// qui n'existe pas sur une colonne booléenne, et lève sur son type de retour plutôt que de
		// répondre. On demande le type, qui est toujours là.
		foreach(CommunityRightAiLib::RIGHTS as $right => $definition) {

			$isSet = (Community::model()->getPropertyType($right) === 'set');

			if($definition['nature'] === CommunityRightAiLib::AUDIENCE) {
				$this->assertTrue($isSet, "le droit à public ".$right." est un set sur Community");
			} else {
				$this->assertFalse($isSet, "le droit sans public ".$right." n'est pas un set sur Community");
			}

		}

	}

	/**
	 * L'exception qui a motivé le lot : la formation impose la production, et la copie aux seuls formateurs.
	 *
	 * Elle vivait en dur dans `Structure::hasCommunityShare()`. La déclarer ici ne suffit pas à
	 * garantir qu'elle n'est pas aussi restée là-bas ; ce que ce cas garde, c'est qu'on ne la
	 * reprenne pas silencieusement en la passant à `OPTIONAL` un jour de ménage. La copie y était
	 * absente jusqu'au 27/09/2026 : les étudiants n'en reçoivent toujours rien.
	 *
	 * ⚠️ L'élément porte sa ligne `Community` : sans id, `Structure::community()` ne saurait pas où
	 * la chercher, et un droit qui porte `enabledBy` la lit.
	 */
	public function testEducationImposesProductionAndCopyToStaff(): void {

		$eStructureEducation = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::EDUCATION, 'community' => new Community(['hasSellingDuplicate' => FALSE])]);

		$this->assertEquals(CommunityRightAiLib::IMPOSED, CommunityRightAiLib::getRegime($eStructureEducation, 'shareProduction'));
		$this->assertEquals(CommunityRightAiLib::IMPOSED, CommunityRightAiLib::getRegime($eStructureEducation, 'shareProductionExport'));
		$this->assertEquals([Community::STAFF], CommunityRightAiLib::getGranted($eStructureEducation, 'shareProductionExport', new \Set()));
		$this->assertEquals(['shareProduction', 'shareProductionExport', 'shareProductionDuplicate'], CommunityRightAiLib::getRights($eStructureEducation));

	}

	/**
	 * Une structure comptable n'a aucun droit, donc son onglet de partage n'a rien à montrer.
	 */
	public function testAccountingHasNoRightAtAll(): void {

		$eStructureAccounting = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::ACCOUNTING]);

		$this->assertEquals([], CommunityRightAiLib::getRights($eStructureAccounting));

	}

	/**
	 * Un droit forgé et un groupe sans type se refusent, ils ne lèvent pas.
	 */
	public function testForgedRightAndTypelessGroupAreRefused(): void {

		$eStructureNetwork = new Structure(['type' => Structure::COMMUNITY, 'communityType' => Structure::NETWORK]);
		$eStructureTypeless = new Structure(['type' => Structure::COMMUNITY, 'communityType' => NULL]);
		$eStructureProducer = new Structure(['type' => Structure::PRODUCER, 'communityType' => NULL]);

		$this->assertFalse(CommunityRightAiLib::accepts($eStructureNetwork, 'shareEverything'));
		$this->assertEquals([], CommunityRightAiLib::getAudiences('shareEverything'));

		$this->assertFalse(CommunityRightAiLib::accepts($eStructureTypeless, 'shareProduction'));
		$this->assertFalse(CommunityRightAiLib::accepts($eStructureProducer, 'shareProduction'));

	}

}
?>
