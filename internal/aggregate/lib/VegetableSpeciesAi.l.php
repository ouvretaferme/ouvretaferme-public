<?php
namespace aggregate;

/**
 * Les espèces que chaque ferme cultive dans une saison, et les mois où elle prévoit de les
 * récolter, pour le calendrier du tableau de bord maraîchage (`series\VegetableDashboardAiLib`).
 *
 * Une ligne par ferme, saison et espèce de référence, écrite par VegetableProjectionAiLib : la
 * saison en cours chaque nuit, le passé par la reprise `aggregate/configure/vegetableAi`. Une
 * culture dont l'espèce n'a pas de référence n'est pas écrite ici, comme le calendrier l'écartait
 * déjà.
 *
 * `months` ne porte que les mois de la saison de la ligne : les mois de récolte prévus débordent
 * sur l'année suivante, et ceux-là appartiennent au calendrier de la saison voisine.
 */
class VegetableSpeciesAiLib extends VegetableSpeciesCrud {

	// Même découpage que CultivationAiLib, et pour la même raison : la taille d'un INSERT dépend
	// d'un réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/** Les lignes en ligne des fermes données sur une saison. */
	public static function getByStructures(\Collection $cStructure, int $season): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		return VegetableSpecies::model()
			->select(['structure', 'plant', 'cultivations', 'months'])
			->whereStructure('IN', $cStructure)
			->whereSeason($season)
			->whereOnline(TRUE)
			->getCollection();

	}

	/**
	 * Dépose des lignes, en ligne ou non selon ce qu'elles portent.
	 *
	 * Écriture directe et par lot, pour la raison écrite sur VegetablePlanAiLib::createCollection().
	 */
	public static function createCollection(\Collection $cVegetableSpecies): void {

		foreach(array_chunk($cVegetableSpecies->getArrayCopy(), self::CHUNK) as $chunk) {

			$cVegetableSpeciesChunk = new \Collection($chunk);

			VegetableSpecies::model()->insert($cVegetableSpeciesChunk);

		}

	}

	/** Toutes les lignes des fermes données, en ligne comme en préparation. */
	public static function deleteByStructures(\Collection $cStructure): void {

		VegetableSpecies::model()
			->whereStructure('IN', $cStructure)
			->delete();

	}

	public static function deleteOffline(): void {

		VegetableSpecies::model()
			->whereOnline(FALSE)
			->delete();

	}

	/** Les lignes en ligne, d'une saison ou de toutes. */
	public static function deleteOnline(?int $season = NULL): void {

		VegetableSpecies::model()
			->whereSeason($season, if: $season !== NULL)
			->whereOnline(TRUE)
			->delete();

	}

	/** Met en ligne les lignes préparées, d'une saison ou de toutes. */
	public static function publish(?int $season = NULL): void {

		VegetableSpecies::model()
			->whereSeason($season, if: $season !== NULL)
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

}
?>
