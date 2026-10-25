<?php
namespace aggregate;

/**
 * Ce que chaque ferme cultive et place dans une saison, pour le tableau de bord maraîchage d'un
 * groupe (`series\VegetableDashboardAiLib`).
 *
 * Une ligne par ferme et par saison, écrite par VegetableProjectionAiLib : la saison en cours
 * chaque nuit, le passé par la reprise `aggregate/configure/vegetableAi`. Le tableau de bord
 * lisait jusqu'ici les tables vives à chaque affichage : 1,3 à 2,2 s mesurées le 19/09/2026 sur
 * les groupes 6388 et 6375.
 *
 * ⚠️ Aucune règle d'affichage n'est cuite ici : ni le seuil de séries, ni le plancher, ni la
 * population du groupe. Ils s'appliquent à la lecture, sur le groupe du moment, et une ferme qui
 * rejoint un groupe y paraît donc sans attendre la nuit.
 */
class VegetablePlanAiLib extends VegetablePlanCrud {

	// Même découpage que CultivationAiLib, et pour la même raison : la taille d'un INSERT dépend
	// d'un réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/** Les lignes en ligne des fermes données, sur une saison ou sur toutes. */
	public static function getByStructures(\Collection $cStructure, ?int $season = NULL): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		return VegetablePlan::model()
			->select(['structure', 'season', 'series', 'cultivations', 'harvested', 'species', 'area', 'greenhouse'])
			->whereStructure('IN', $cStructure)
			->whereSeason($season, if: $season !== NULL)
			->whereOnline(TRUE)
			->getCollection();

	}

	/**
	 * L'heure où la saison en ligne des fermes données a été calculée, `NULL` tant qu'aucune
	 * projection ne l'a publiée.
	 *
	 * Par saison : le cron ne refait que l'année en cours (et l'année écoulée le 1er janvier), une
	 * saison plus ancienne garde l'heure de la reprise qui l'a écrite. La plus ancienne des lignes,
	 * parce que c'est l'instant à partir duquel ce que l'écran montre peut avoir changé dans les
	 * tables vives. Bornée aux fermes du groupe, ce que l'écran lit, et ce qui sert l'index
	 * `[structure, season]`.
	 */
	public static function getComputedAt(\Collection $cStructure, int $season): ?string {

		if($cStructure->empty()) {
			return NULL;
		}

		return VegetablePlan::model()
			->select([
				'computedAt' => new \Sql('MIN(createdAt)')
			])
			->whereStructure('IN', $cStructure)
			->whereSeason($season)
			->whereOnline(TRUE)
			->get()['computedAt'];

	}

	/**
	 * Dépose des lignes, en ligne ou non selon ce qu'elles portent.
	 *
	 * Écriture directe et par lot, à rebours de la règle du dépôt, pour la raison écrite sur
	 * CultivationAiLib::createByPlant() : cette table n'est la source de rien et ne porte aucune
	 * règle de module, et create() ligne à ligne ne coûterait que des requêtes.
	 */
	public static function createCollection(\Collection $cVegetablePlan): void {

		foreach(array_chunk($cVegetablePlan->getArrayCopy(), self::CHUNK) as $chunk) {

			$cVegetablePlanChunk = new \Collection($chunk);

			VegetablePlan::model()->insert($cVegetablePlanChunk);

		}

	}

	/** Toutes les lignes des fermes données, en ligne comme en préparation. */
	public static function deleteByStructures(\Collection $cStructure): void {

		VegetablePlan::model()
			->whereStructure('IN', $cStructure)
			->delete();

	}

	public static function deleteOffline(): void {

		VegetablePlan::model()
			->whereOnline(FALSE)
			->delete();

	}

	/** Les lignes en ligne, d'une saison ou de toutes. */
	public static function deleteOnline(?int $season = NULL): void {

		VegetablePlan::model()
			->whereSeason($season, if: $season !== NULL)
			->whereOnline(TRUE)
			->delete();

	}

	/** Met en ligne les lignes préparées, d'une saison ou de toutes. */
	public static function publish(?int $season = NULL): void {

		VegetablePlan::model()
			->whereSeason($season, if: $season !== NULL)
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

}
?>
