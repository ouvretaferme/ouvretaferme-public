<?php
namespace aggregate;

/**
 * Le prix au détail d'une espèce, par semaine et par unité, anonymisé.
 *
 * Une ligne par triplet (semaine, espèce de référence, unité du référentiel), sans année : le prix
 * est saisonnier et non millésimé, et la fenêtre glissante de la passe qui l'écrit en fournit
 * l'actualité. La semaine 53 est rangée en 52, comme partout dans l'agrégé.
 *
 * `contributors` porte le nombre de structures distinctes derrière la ligne, et c'est lui que
 * `online` traduit : une ligne sous le seuil est écrite mais jamais servie, sans quoi `median`,
 * `low` et `high` diraient le prix d'une seule structure.
 *
 * ⚠️ `online` ne dit donc pas la même chose que sur les autres tables de l'agrégé, où il distingue
 * la version servie de celle en préparation et où toute ligne finit servie. Ici une ligne hors ligne
 * ne le sera jamais : au 2026-09-21, 7 072 des 8 565 lignes, dont 3 541 à un seul contributeur et
 * 3 082 où `low = high`, c'est-à-dire le prix exact d'une structure pour une semaine, une espèce et
 * une unité. Aucune colonne ne dit laquelle — ni identifiant ni référence propre à une structure,
 * ce que `AnonymitySentinelAiTest` vérifie dans les deux sens. Elles sont conservées parce que
 * `contributors` est la seule mesure de ce que le relevé couvre, et qu'un lecteur qui veut dire
 * « pas assez de structures cette semaine-là » n'a que cette ligne pour le savoir.
 *
 * Les écritures sont celles d'un passage de selling\PriceProjectionAiLib, qui réécrit la table entière ; la
 * règle qui la lit appartient aux lots suivants.
 */
class PriceAiLib extends PriceCrud {

	// Même découpage que CompositionItemAiLib, et pour la même raison : la taille d'un INSERT
	// dépend d'un réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/**
	 * Dépose les lignes d'un passage.
	 *
	 * Écriture directe et par lot, à rebours de la règle du dépôt, pour la raison écrite sur
	 * CultivationAiLib::createByPlant() : cette table n'est la source de rien et ne porte aucune
	 * règle de module, et create() ligne à ligne ne coûterait que des requêtes.
	 */
	public static function createCollection(\Collection $cPrice): void {

		foreach(array_chunk($cPrice->getArrayCopy(), self::CHUNK) as $chunk) {

			$cPriceChunk = new \Collection($chunk);

			Price::model()->insert($cPriceChunk);

		}

	}

	/**
	 * Jette les lignes qu'aucun lecteur ne sert : celles d'un relevé sous le seuil.
	 */
	public static function deleteOffline(): void {

		Price::model()
			->whereOnline(FALSE)
			->delete();

	}

	/**
	 * Jette les lignes servies.
	 *
	 * C'est l'appelant qui enferme ce mouvement et l'écriture de la version neuve dans une
	 * transaction : entre les deux, la table n'aurait rien à servir. L'UNIQUE (week, plant, unit)
	 * interdit de garder les deux versions côte à côte, comme le font les compositions.
	 */
	public static function deleteOnline(): void {

		Price::model()
			->whereOnline(TRUE)
			->delete();

	}

}
?>
