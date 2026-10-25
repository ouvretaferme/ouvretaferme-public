<?php
namespace aggregate;

/**
 * Les cultures de la communauté, mises à plat pour la bibliothèque de cultures.
 *
 * Une ligne par culture retenue, sur toutes les structures, reconstruite chaque
 * nuit par CultivationProjectionAiLib. La page lisait auparavant les tables vives —
 * mesuré à 1 120 981 lignes lues par affichage, dont un parcours complet de
 * `seriesTask` — pour un résultat que rien ne rendait dépendant de l'instant.
 *
 * ⚠ Une colonne de cette table ne doit jamais dépendre de l'échantillon ni d'un
 * réglage. Les valeurs qui se calculent sur l'échantillon filtré — plancher de
 * délai, plafond de créneau, parts de mode — changent avec les filtres et n'ont
 * donc pas de valeur fixe à stocker ; elles restent dans CultivationCropPlanAiLib. Les
 * seuils de CultivationAiSetting sont eux aussi appliqués à la lecture, ce qui permet de
 * les bouger sans reconstruire : c'est pourquoi on trouve ici l'altitude brute et
 * non son palier, le nombre de séries de la structure et non le verdict du seuil,
 * l'index de pépinière brut et non l'écart borné.
 *
 * Ne sont cuites que les règles qui ne sont pas des boutons : semaines aberrantes,
 * séries pérennes, fermes fermées, saisons à venir.
 */
class CultivationAiLib extends CultivationCrud {

	// Lignes par requête d'insertion. Une espèce en compte jusqu'à douze mille, ce qui
	// tiendrait dans un seul INSERT ici mais dépend d'un réglage serveur
	// (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/**
	 * Les cultures d'une espèce de référence, prêtes à agréger.
	 *
	 * Les seuls filtres posés ici sont ceux qui portent sur une colonne : ils
	 * réduisent le volume rendu sans rien décider. Tout le reste — altitude,
	 * mode, seuil de séries — se filtre à la lecture, dans
	 * CultivationCropPlanAiLib, parce que ce sont des réglages.
	 */
	public static function getByPlant(\plant\Plant $ePlant, ?string $action, ?string $seedling): \Collection {

		return Cultivation::model()
			// Sélection explicite plutôt que getSelection() : `plant` est déjà le critère de la
			// requête, et `seriesCultivation` ne sert qu'à départager les égalités.
			->select([
				'id', 'seriesCultivation', 'structure',
				'startAction', 'mode', 'startWeek', 'month',
				'use', 'bedWidth', 'rows', 'plantSpacing', 'density',
				'seedlingIndex',
				'harvestPeriod', 'harvestFrom', 'harvestTo', 'harvestCount', 'harvestWeeks',
				'yieldExpected', 'mainUnit',
				'altitude', 'structureSeries',
			])
			->wherePlant($ePlant)
			->whereOnline(TRUE)
			->whereStartAction($action, if: $action !== NULL)
			->whereSeedling($seedling, if: $seedling !== NULL)
			->getCollection(index: 'id');

	}

	/**
	 * La conduite majoritaire d'une espèce, plantation ou semis direct.
	 *
	 * Elle sert de vue par défaut quand l'URL n'en impose pas. Une valeur en dur ne
	 * pouvait pas convenir : la courgette se plante à 94 %, la carotte se sème en
	 * place à 99 %, et ouvrir la carotte sur la plantation montrait 68 cultures au
	 * lieu de 4 943.
	 *
	 * Départage à la plantation en cas d'égalité ou d'échantillon vide.
	 */
	public static function getActionDominantByPlant(\plant\Plant $ePlant): string {

		$cCultivation = Cultivation::model()
			->select([
				'startAction',
				'total' => new \Sql('COUNT(*)', 'int'),
			])
			->wherePlant($ePlant)
			->whereOnline(TRUE)
			->group('startAction')
			->sort(['total' => SORT_DESC])
			->getCollection();

		foreach($cCultivation as $eCultivation) {
			return $eCultivation['startAction'];
		}

		return \series\Cultivation::PLANTING;

	}

	/**
	 * Dépose d'un bloc les lignes d'une espèce, hors ligne.
	 *
	 * Écriture directe et par lot, à rebours de la règle du dépôt : cette table n'est
	 * la source de rien, elle ne porte ni règle de module, ni horodatage de mise à
	 * jour, ni journalisation, et personne ne la modifie à la main. Passer par
	 * create() ligne à ligne n'ajouterait aucune de ces choses, seulement 99 000
	 * requêtes par nuit.
	 */
	public static function createByPlant(\Collection $cCultivation): void {

		foreach(array_chunk($cCultivation->getArrayCopy(), self::CHUNK) as $chunk) {

			$cChunk = new \Collection($chunk);

			Cultivation::model()->insert($cChunk);

		}

	}

	/**
	 * Les lignes d'une espèce en cours de préparation.
	 *
	 * La reconstruction en a besoin pour rattacher les variétés : elles désignent une
	 * ligne de faits par son identifiant, et celui-ci n'est connu qu'une fois la ligne
	 * écrite. Le filtre hors ligne est ce qui distingue la version en préparation de
	 * celle que la page est en train de servir.
	 */
	public static function getOfflineByPlant(\plant\Plant $ePlant): \Collection {

		return Cultivation::model()
			->select(['id', 'seriesCultivation'])
			->wherePlant($ePlant)
			->whereOnline(FALSE)
			->getCollection();

	}

	/**
	 * Jette la version en préparation.
	 *
	 * Appelée au début de la reconstruction : ce qui traîne hors ligne est le reliquat
	 * d'un passage interrompu, une version que personne n'a jamais servie et qui ne
	 * décrit plus l'état des tables vives.
	 */
	public static function deleteOffline(): void {

		Cultivation::model()
			->whereOnline(FALSE)
			->delete();

	}

	/**
	 * Jette la version en ligne, puis met la préparation à sa place.
	 *
	 * Les deux mouvements sont indissociables : entre les deux, l'espèce n'aurait
	 * aucune ligne à servir. C'est l'appelant qui les enferme dans une transaction.
	 */
	public static function deleteOnline(): void {

		Cultivation::model()
			->whereOnline(TRUE)
			->delete();

	}

	public static function publish(): void {

		Cultivation::model()
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

}
?>
