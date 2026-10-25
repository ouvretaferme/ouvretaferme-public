<?php
namespace structure;

/**
 * La déclaration des fonctionnalités de groupe dit-elle encore la vérité du code ?
 *
 * `CommunityFeatureAiLib` est le seul endroit du dépôt qui apparie un type de groupe et ce qu'il
 * peut allumer. Rien dans le code ne rappellera cette table à qui ajoutera une cinquième valeur à
 * l'enum `communityType` dans six mois, depuis un autre écran : d'où cette sentinelle, qui ne
 * vérifie aucun comportement mais un **inventaire**, et échoue en nommant l'écart.
 *
 * Elle compare dans les deux sens : le couple apparu qu'on n'a pas déclaré, et la déclaration
 * devenue fausse parce que la colonne ou le type a disparu.
 *
 * Les comportements qui en découlent sont dans `CommunityFeatureAi.t.php`.
 *
 * Lancement : dev/test package=structure file=CommunityFeatureSentinelAi
 */
class CommunityFeatureSentinelAiTest extends \Test {

	private function getTypes(): array {

		$types = Structure::model()->getPropertyEnum('communityType');

		sort($types);

		return $types;

	}

	/**
	 * Chaque fonctionnalité déclarée porte-t-elle une colonne réelle, et les quatre types ?
	 *
	 * Une clé sans colonne écrirait dans le vide, et un type manquant ferait lever
	 * `CommunityFeatureAiLib::accepts()` sur un `Undefined array key` — c'est-à-dire sur l'écran
	 * d'un groupe, en production, plutôt qu'ici.
	 */
	public function testEveryFeatureIsAColumnAndCoversEveryType(): void {

		$properties = Feature::model()->getProperties();
		$types = $this->getTypes();

		foreach(CommunityFeatureAiLib::FEATURES as $feature => $states) {

			$this->assertTrue(in_array($feature, $properties, TRUE), "la colonne ".$feature." existe encore sur Feature");

			$declared = array_keys($states);
			sort($declared);

			$this->assertEquals($types, $declared, "la fonctionnalité ".$feature." déclare les quatre types");

		}

	}

	/**
	 * Aucun état hors des trois valeurs.
	 *
	 * Une faute de frappe ne lèverait rien : `accepts()` compare à `ABSENT` et rendrait donc vrai,
	 * ouvrant la fonctionnalité à un type qui ne devait pas l'avoir.
	 */
	public function testEveryStateIsOneOfTheThree(): void {

		$states = [CommunityFeatureAiLib::ABSENT, CommunityFeatureAiLib::OFF, CommunityFeatureAiLib::ON];

		foreach(CommunityFeatureAiLib::FEATURES as $feature => $byType) {

			foreach($byType as $type => $state) {
				$this->assertTrue(in_array($state, $states, TRUE), "l'état de ".$feature." sur ".$type." est déclaré");
			}

		}

	}

	/**
	 * La seconde table couvre-t-elle exactement l'enum, et ses partages sont-ils des colonnes ?
	 */
	public function testEveryTypeIsDeclaredWithRealShares(): void {

		$declared = array_keys(CommunityFeatureAiLib::TYPES);
		sort($declared);

		$this->assertEquals($this->getTypes(), $declared, "les quatre types de groupe sont déclarés");

		$properties = Community::model()->getProperties();

		foreach(CommunityFeatureAiLib::TYPES as $type => $definition) {

			foreach($definition['shares'] as $share) {
				$this->assertTrue(in_array($share, $properties, TRUE), "le partage ".$share." de ".$type." existe encore sur Community");
			}

		}

	}

	/**
	 * Un groupe neuf ouvre-t-il avec ce que la déclaration annonce ?
	 *
	 * La recette du lot, dérivée de la table et non recopiée : elle suivra toute décision future
	 * sans qu'on ait à revenir ici, et elle échouera si `getDefaults()` cesse de la lire.
	 */
	public function testDefaultsFollowTheDeclaration(): void {

		foreach($this->getTypes() as $type) {

			$eStructure = new Structure(['type' => Structure::COMMUNITY, 'communityType' => $type]);

			$expected = [];

			foreach(CommunityFeatureAiLib::FEATURES as $feature => $states) {

				if($states[$type] === CommunityFeatureAiLib::ON) {
					$expected[] = $feature;
				}

			}

			$this->assertEquals($expected, CommunityFeatureAiLib::getDefaults($eStructure), "un groupe ".$type." neuf ouvre avec ce qui est déclaré");

		}

	}

	/**
	 * Les mots que le parcours de démarrage demande à la classe du type.
	 *
	 * ⚠️ **Déclarés ici parce qu'ils ne se dérivent pas** : `CommunityStartAiUi` les demande par
	 * `StructureUi::getText()`, une clé à la fois, au fil de son rendu. Ajouter une phrase au
	 * parcours ajoute donc une ligne ici, et le contrôle ci-dessous dira laquelle manque.
	 */
	const START_TEXTS = ['the', 'of', 'this', 'your', 'farms', 'created', 'ready'];

	/**
	 * Les types qui savent dire les mots du parcours et ne le portent pas, avec la raison.
	 *
	 * Un trou déclaré se lit, un trou tu se découvre en production. Celui-ci n'est pas un oubli :
	 * une structure comptable n'a rien à démarrer — ni fonctionnalité à choisir ni membre à
	 * inviter au premier jour —, et elle hérite ses mots de la classe commune, ce qui la rend
	 * éligible sans qu'on l'ait voulu.
	 */
	const START_ABSENT = [
		Structure::ACCOUNTING => "rien à démarrer : ses membres et ses antennes se règlent ailleurs"
	];

	/**
	 * Chaque type qui porte le parcours sait-il dire les mots que le parcours emploie ?
	 *
	 * ⚠️ **Une classe de type répond par un `match` sans `default`** : une clé qu'elle ne connaît
	 * pas ne rend pas une valeur vide, elle **lève**, et l'écran tombe entier. Le défaut ne se voit
	 * donc qu'en ouvrant l'écran du type oublié — et personne n'ouvre l'écran d'un type que le lot
	 * en cours ne mentionne pas. C'est arrivé le 12/09 : la garde du gabarit s'était élargie aux
	 * structures qui ont « une mise en route », donc aux AMAP, et `farms` y levait.
	 *
	 * Le contrôle porte dans les deux sens :
	 *
	 * 1. un type qui **déclare** le parcours doit répondre à toutes les clés ;
	 * 2. un type qui répond à toutes les clés **sans** déclarer le parcours doit dire pourquoi dans
	 *    `START_ABSENT` : il pourrait le porter, et le silence ne dirait pas si c'est voulu.
	 */
	public function testEveryStartTypeAnswersItsWords(): void {

		foreach($this->getTypes() as $type) {

			$eStructure = new Structure(['type' => Structure::COMMUNITY, 'communityType' => $type]);

			// ⚠️ La classe du type et non `StructureUi::getText()` : celle-ci rattrape
			// `UnhandledMatchError` et rend une chaîne vide, pour qu'une clé oubliée ne fasse pas
			// tomber l'écran. Interrogé par elle, le contrôle ne trouverait jamais rien.
			$class = StructureUi::getClass($eStructure);

			$missing = [];

			foreach(self::START_TEXTS as $text) {

				try {
					$class::getText($eStructure, $text);
				} catch(\Throwable) {
					$missing[] = $text;
				}

			}

			if($eStructure->acceptCommunityStart()) {
				$this->assertEquals([], $missing, "le type ".$type." porte le parcours mais sa classe ne sait pas dire : ".implode(', ', $missing));
			} else if($missing === []) {
				$this->assertTrue(
					array_key_exists($type, self::START_ABSENT),
					"le type ".$type." sait dire tous les mots du parcours sans le porter : le déclarer dans Structure::acceptCommunityStart(), ou dire ici pourquoi il en est écarté"
				);
			}

		}

	}


}
?>
