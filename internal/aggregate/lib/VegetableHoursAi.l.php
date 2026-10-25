<?php
namespace aggregate;

/**
 * Le temps de travail saisi par chaque ferme, par année civile et par nature de travail, pour le
 * tableau de bord maraîchage (`series\VegetableDashboardAiLib`).
 *
 * Une ligne par ferme, année et `fqn` de catégorie, écrite par VegetableProjectionAiLib : l'année
 * en cours chaque nuit, le passé par la reprise `aggregate/configure/vegetableAi`. `fqn` vaut
 * `NULL` pour les catégories qu'une ferme s'est créées : elles vont dans le reste « Autres » de
 * l'écran.
 *
 * ⚠️ L'année civile et non la saison : `series\Timesheet` n'en porte pas, et la raison est écrite
 * sur `VegetableDashboardAiLib::getHoursByCategory()`. La colonne s'appelle donc `year`, pour que
 * personne ne la compare à une saison sans le savoir.
 */
class VegetableHoursAiLib extends VegetableHoursCrud {

	// Même découpage que CultivationAiLib, et pour la même raison : la taille d'un INSERT dépend
	// d'un réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/** Les lignes en ligne des fermes données sur une année. */
	public static function getByStructures(\Collection $cStructure, int $year): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		return VegetableHours::model()
			->select(['structure', 'fqn', 'hours'])
			->whereStructure('IN', $cStructure)
			->whereYear($year)
			->whereOnline(TRUE)
			->getCollection();

	}

	/**
	 * Dépose des lignes, en ligne ou non selon ce qu'elles portent.
	 *
	 * Écriture directe et par lot, pour la raison écrite sur VegetablePlanAiLib::createCollection().
	 */
	public static function createCollection(\Collection $cVegetableHours): void {

		foreach(array_chunk($cVegetableHours->getArrayCopy(), self::CHUNK) as $chunk) {

			$cVegetableHoursChunk = new \Collection($chunk);

			VegetableHours::model()->insert($cVegetableHoursChunk);

		}

	}

	/** Toutes les lignes des fermes données, en ligne comme en préparation. */
	public static function deleteByStructures(\Collection $cStructure): void {

		VegetableHours::model()
			->whereStructure('IN', $cStructure)
			->delete();

	}

	public static function deleteOffline(): void {

		VegetableHours::model()
			->whereOnline(FALSE)
			->delete();

	}

	/** Les lignes en ligne, d'une année ou de toutes. */
	public static function deleteOnline(?int $year = NULL): void {

		VegetableHours::model()
			->whereYear($year, if: $year !== NULL)
			->whereOnline(TRUE)
			->delete();

	}

	/** Met en ligne les lignes préparées, d'une année ou de toutes. */
	public static function publish(?int $year = NULL): void {

		VegetableHours::model()
			->whereYear($year, if: $year !== NULL)
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

}
?>
