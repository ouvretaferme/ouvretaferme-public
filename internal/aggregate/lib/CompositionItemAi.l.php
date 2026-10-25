<?php
namespace aggregate;

/**
 * Les espèces d'une composition de panier, anonymisées.
 *
 * Une ligne par ligne de la composition dont le produit mène à une espèce de référence. Une
 * même espèce peut donc paraître deux fois dans une composition, quand la structure la vend
 * sous deux produits : les deux lignes ne s'additionnent pas, leurs unités pouvant différer.
 * Une ligne sans espèce de référence n'est pas écrite ici, mais elle reste comptée dans
 * `lines` de sa composition.
 */
class CompositionItemAiLib extends CompositionItemCrud {

	// Même découpage que CultivationAiLib, et pour la même raison : la taille d'un INSERT dépend
	// d'un réglage serveur (`max_allowed_packet`) qu'on ne maîtrise pas partout.
	private const CHUNK = 2000;

	/**
	 * Dépose les lignes d'un passage, hors ligne.
	 *
	 * Écriture directe et par lot, à rebours de la règle du dépôt, pour la raison écrite sur
	 * CultivationAiLib::createByPlant() : cette table n'est la source de rien et ne porte aucune
	 * règle de module, et create() ligne à ligne ne coûterait que des requêtes.
	 */
	public static function createCollection(\Collection $cCompositionItem): void {

		foreach(array_chunk($cCompositionItem->getArrayCopy(), self::CHUNK) as $chunk) {

			$cCompositionItemChunk = new \Collection($chunk);

			CompositionItem::model()->insert($cCompositionItemChunk);

		}

	}

	public static function deleteOffline(): void {

		CompositionItem::model()
			->whereOnline(FALSE)
			->delete();

	}

	public static function deleteOnline(): void {

		CompositionItem::model()
			->whereOnline(TRUE)
			->delete();

	}

	public static function publish(): void {

		CompositionItem::model()
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

}
?>
