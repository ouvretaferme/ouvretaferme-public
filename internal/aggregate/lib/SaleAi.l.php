<?php
namespace aggregate;

/**
 * Le chiffre des ventes d'une espèce, par mois civil, anonymisé : la saison de vente que la
 * composition des paniers lit pour moduler la part d'une espèce (`CompositionSourceAiLib`).
 *
 * Une ligne par couple (espèce de référence, mois), sans année : la fenêtre glissante de la passe qui
 * l'écrit en fournit l'actualité. `contributors` porte le nombre de structures distinctes derrière la
 * case, et seule une case au seuil d'anonymat est écrite : aucune ligne de la table ne porte le
 * chiffre d'une structure seule. Les totaux dont la lecture a besoin, et qui somment aussi les cases
 * sous le seuil, vivent dans `SaleTotal` (SaleTotalAiLib).
 *
 * Aucune colonne ne désigne une structure, ce que `AnonymitySentinelAiTest` vérifie.
 *
 * Les écritures sont celles d'un passage de selling\SaleProjectionAiLib, qui réécrit la table entière.
 */
class SaleAiLib extends SaleCrud {

	// Même découpage que PriceAiLib, et pour la même raison : la taille d'un INSERT dépend d'un
	// réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/**
	 * Dépose les lignes d'un passage.
	 *
	 * Écriture directe et par lot, à rebours de la règle du dépôt, pour la raison écrite sur
	 * CultivationAiLib::createByPlant() : cette table n'est la source de rien et ne porte aucune règle
	 * de module, et create() ligne à ligne ne coûterait que des requêtes.
	 */
	public static function createCollection(\Collection $cSale): void {

		foreach(array_chunk($cSale->getArrayCopy(), self::CHUNK) as $chunk) {

			$cSaleChunk = new \Collection($chunk);

			Sale::model()->insert($cSaleChunk);

		}

	}

	/**
	 * Jette toutes les cases.
	 *
	 * C'est l'appelant qui enferme ce mouvement et l'écriture de la version neuve dans une
	 * transaction : entre les deux, la table n'aurait rien à servir. L'UNIQUE (plant, month) interdit
	 * de garder les deux versions côte à côte.
	 */
	public static function deleteAll(): void {

		Sale::model()
			->all()
			->delete();

	}

}
?>
