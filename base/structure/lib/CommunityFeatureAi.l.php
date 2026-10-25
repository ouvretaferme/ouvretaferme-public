<?php
namespace structure;

/**
 * Ce qu'un groupe peut allumer, et ce qu'il trouve allumé en arrivant.
 *
 * ⚠️ **Un champ par couple et non trois.** Le sujet a l'air d'en porter trois — disponible, allumée
 * à la création, extinguible — mais ils ne sont pas indépendants. « Extinguible » vaut vrai pour
 * les seize couples par construction : ce qui ne s'éteint pas n'est pas une fonctionnalité, c'est
 * structurant, et n'a ni carte ni entrée ici (les membres, les fermes, le partage des données).
 * Et « allumée à la création » n'a de sens que si le type la permet. Déclarés séparément, les deux
 * booléens ouvriraient huit combinaisons dont quatre sont absurdes ; une déclaration qui permet
 * d'écrire un état impossible finit par le contenir.
 *
 * ⚠️ **La clé est le nom de la colonne de `structureFeature`**, et non un vocabulaire de plus. Un
 * second nom aurait demandé une table d'appariement, donc un troisième inventaire à tenir à jour.
 * C'est aussi ce qui permet à la sentinelle de vérifier que la colonne existe encore, comme celle
 * des attentes vérifie ses propriétés de culture.
 *
 * ⚠️ **Deux tables et non une.** `FEATURES` décrit des interrupteurs : un état par groupe, qui
 * s'écrit, qu'un administrateur change. `TYPES` décrit ce qu'un type **est** : aucun état, rien ne
 * s'écrit, personne ne le change. Les fondre demanderait une colonne « ceci est un interrupteur »,
 * c'est-à-dire réintroduire la distinction qu'on aurait effacée. Ajouter un type de groupe reste
 * un seul fichier à ouvrir, ce qui est le but.
 */
class CommunityFeatureAiLib {

	/** Ce type ne peut pas avoir cette fonctionnalité : elle n'apparaît nulle part. */
	const ABSENT = 'absent';

	/** Disponible, éteinte à la création. */
	const OFF = 'off';

	/** Disponible, allumée à la création — ce qui ne la verrouille pas. */
	const ON = 'on';

	/**
	 * Ce que chaque type de groupe fait de chaque fonctionnalité.
	 *
	 * ⚠️ `hasQuotation` est la colonne des fermes, reprise telle quelle : un groupe est une
	 * structure, et le produit n'a aucune raison de tenir deux mécanismes pour la même question.
	 * Sa sémantique diffère pourtant des deux côtés — sur une ferme elle s'allume au premier geste,
	 * ici elle porte une décision d'administrateur —, et c'est écrit dans le `.yml` sur la colonne.
	 */
	const FEATURES = [

		'hasCommunityVegetable' => [
			Structure::PRODUCER => self::OFF,
			Structure::NETWORK => self::OFF,
			Structure::EDUCATION => self::OFF,
			Structure::ACCOUNTING => self::ABSENT,
		],

		// Un groupe de formation n'a pas de prix à relever, une structure comptable non plus.
		'hasQuotation' => [
			Structure::PRODUCER => self::OFF,
			Structure::NETWORK => self::OFF,
			Structure::EDUCATION => self::ABSENT,
			Structure::ACCOUNTING => self::ABSENT,
		],

		// ⚠️ Allumés à la création en formation : une promotion est la façon dont un organisme
		// range ses étudiants, il la trouve prête. Allumé n'est pas verrouillé, il peut l'éteindre.
		'hasCommunitySections' => [
			Structure::PRODUCER => self::OFF,
			Structure::NETWORK => self::OFF,
			Structure::EDUCATION => self::ON,
			Structure::ACCOUNTING => self::OFF,
		],

		// Les fermes école ne se rattachent à personne et n'existent que pour la formation.
		'hasCommunitySchools' => [
			Structure::PRODUCER => self::ABSENT,
			Structure::NETWORK => self::ABSENT,
			Structure::EDUCATION => self::ON,
			Structure::ACCOUNTING => self::ABSENT,
		],

		// Les formations ne s'ouvrent qu'à un réseau et à un groupe de formation, qui les trouvent
		// allumées : elles sont la raison d'être du second.
		'hasCommunityLearning' => [
			Structure::PRODUCER => self::ABSENT,
			Structure::NETWORK => self::ON,
			Structure::EDUCATION => self::ON,
			Structure::ACCOUNTING => self::ABSENT,
		],

	];

	/**
	 * Ce qu'un type de groupe **est**, et qu'aucun administrateur ne change.
	 *
	 * `shares` : les périmètres qu'il peut demander aux fermes qui le rejoignent.
	 *
	 * ⚠️ **Cette table ne décide plus des partages depuis le 09/09/2026** : `CommunityRightAiLib`
	 * les porte, avec un troisième régime que celle-ci ne savait pas dire — « imposé », qui vivait
	 * en dur dans `Structure::hasCommunityShare()`. Ce qui reste ici est la déclaration de ce qu'un
	 * type **est** hors des droits, et `shares` n'a plus aucun lecteur du produit.
	 *
	 * ⚠️ Ce qui accepte des fermes n'y figure pas : la règle n'est pas une liste de types mais
	 * « un groupe qui a un type », et énumérer les quatre valeurs d'un enum qui en compte quatre
	 * invite le prochain lecteur à croire qu'elle en exclut une.
	 */
	const TYPES = [

		Structure::PRODUCER => [
			'shares' => ['shareProduction', 'shareProductionExport'],
		],

		Structure::NETWORK => [
			'shares' => ['shareProduction', 'shareProductionExport'],
		],

		// ⚠️ La copie se coche, la production non : rejoindre une promotion, c'est accepter d'être
		// lu, il n'y a rien à décider et `hasCommunityShare()` la rend inhérente. Copier le plan
		// vers une autre ferme est un geste de plus, que l'organisme demande et que l'étudiant
		// accorde. Une case qui manque n'est pas la même chose qu'une case cochée d'avance.
		Structure::EDUCATION => [
			'shares' => ['shareProductionExport'],
		],

		Structure::ACCOUNTING => [
			'shares' => [],
		],

	];

	/**
	 * Ce type de groupe peut-il avoir cette fonctionnalité ?
	 *
	 * Dit ce que le type permet et non ce que le groupe a choisi. Les deux se séparent parce que
	 * l'interrupteur doit rester atteignable une fois éteint : l'écran des fonctionnalités pose
	 * cette question-ci pour décider d'afficher la carte, tout le reste pose `has()` et disparaît.
	 */
	public static function accepts(Structure $eStructure, string $feature): bool {

		$eStructure->expects(['type', 'communityType']);

		// ⚠️ Le nom vient parfois d'un POST : la méthode répond donc pour **toute** chaîne, et non
		// pour les seules clés déclarées. Ce n'est pas une garde sur un état interne, c'est le
		// contrat de la méthode — sans elle, une valeur forgée lèverait sur une clé absente au
		// lieu d'être refusée, et le refus est ce que l'appelant attend.
		if(array_key_exists($feature, self::FEATURES) === FALSE) {
			return FALSE;
		}

		if($eStructure['type'] !== Structure::COMMUNITY or $eStructure['communityType'] === NULL) {
			return FALSE;
		}

		return in_array($eStructure['communityType'], self::getCommunityTypes($feature), TRUE);

	}

	/**
	 * Les types de groupe qui peuvent avoir cette fonctionnalité, la même règle que `accepts()`
	 * rendue en liste pour les requêtes qui la posent en SQL (`learning\CourseLib::applyCommunityLearning()`).
	 */
	public static function getCommunityTypes(string $feature): array {

		// ⚠️ La comparaison porte sur ce qui **ouvre** et non sur ce qui refuse, et ce sens-là est
		// le sujet de la ligne. Écrite `!== ABSENT`, elle traiterait toute valeur inconnue comme
		// ouvrante : une quatrième valeur ajoutée demain ouvrirait la fonctionnalité au type qui
		// ne devait pas l'avoir, et une faute de frappe dans la table ferait de même. La forme
		// ci-dessous se trompe dans l'autre sens — une valeur inconnue ferme, donc la
		// fonctionnalité disparaît d'un écran où on l'attendait. Un manque se voit, un accès de
		// trop ne se voit pas.
		return array_keys(array_filter(
			self::FEATURES[$feature],
			fn(string $access) => in_array($access, [self::OFF, self::ON], TRUE)
		));

	}

	/**
	 * Cette fonctionnalité est-elle allumée sur ce groupe ?
	 *
	 * Les deux questions et non la seule colonne : un groupe qui change de type garde en base ce
	 * qu'il avait allumé avant, et le lire tel quel ouvrirait un onglet que son nouveau type ne
	 * connaît pas.
	 */
	public static function has(Structure $eStructure, string $feature): bool {

		return (
			self::accepts($eStructure, $feature) and
			$eStructure->conf(Feature::class)[$feature]
		);

	}

	/**
	 * Les fonctionnalités qu'un groupe neuf trouve allumées.
	 *
	 * Lue une fois, à la création de la ligne de `structureFeature`. Ailleurs, c'est la colonne qui
	 * fait foi : un défaut relu plus tard écraserait ce que l'administrateur a décidé.
	 */
	public static function getDefaults(Structure $eStructure): array {

		// ⚠️ Le type d'abord et seul : cette méthode est appelée à la création de **toute**
		// structure, et une ferme ne porte pas `communityType`. Les exiger ensemble ferait lever
		// `expects()` sur la création d'une ferme, très loin d'ici.
		$eStructure->expects(['type']);

		if($eStructure['type'] !== Structure::COMMUNITY) {
			return [];
		}

		$eStructure->expects(['communityType']);

		if($eStructure['communityType'] === NULL) {
			return [];
		}

		$defaults = [];

		foreach(self::FEATURES as $feature => $types) {

			if($types[$eStructure['communityType']] === self::ON) {
				$defaults[] = $feature;
			}

		}

		return $defaults;

	}

}
?>
