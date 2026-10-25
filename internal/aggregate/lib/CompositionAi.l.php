<?php
namespace aggregate;

/**
 * Les compositions de panier des structures consentantes, anonymisées.
 *
 * Une ligne par composition datée déjà livrée, reconstruite chaque nuit par
 * selling\CompositionProjectionAiLib. À la différence des cultures, aucune colonne ne désigne une
 * structure, une vente ni un produit. `contributor` et `composedProduct` sont des rangs tirés
 * dans un ordre mélangé à chaque passage ; ils permettent de compter les structures d'une
 * tranche et de reconnaître deux tailles de panier d'une même structure ; leur numéro change
 * à chaque passage.
 *
 * ⚠ Ce n'est pas encore l'anonymat. Les colonnes réunies désignent presque toujours une vente :
 * mesuré en dev le 2026-09-15, 2 419 compositions sur 2 522 sont seules à porter leur triplet
 * (semaine, valeur, lignes), et 7 contributeurs sur 72 ont une altitude qu'aucune autre
 * structure consentante ne partage. Une ligne ne sort donc jamais seule : le seuil de
 * structures appliqué à la lecture (Q7, L8) reste la protection, comme pour les cultures.
 *
 * ⚠ Comme pour les cultures, une colonne ne dépend ni de l'échantillon ni d'un réglage :
 * l'altitude et la valeur sont brutes, et le seuil de structures, les paliers d'altitude et
 * les tranches de valeur s'appliquent à la lecture, dans le lot qui lit.
 */
class CompositionAiLib extends CompositionCrud {

	/**
	 * Jette la version en préparation, reliquat d'un passage interrompu.
	 */
	public static function deleteOffline(): void {

		Composition::model()
			->whereOnline(FALSE)
			->delete();

	}

	/**
	 * Jette la version en ligne. C'est l'appelant qui enferme ce mouvement et publish() dans une
	 * transaction : entre les deux, la table n'aurait rien à servir.
	 */
	public static function deleteOnline(): void {

		Composition::model()
			->whereOnline(TRUE)
			->delete();

	}

	public static function publish(): void {

		Composition::model()
			->whereOnline(FALSE)
			->update(['online' => TRUE]);

	}

}
?>
