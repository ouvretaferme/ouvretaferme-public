<?php
namespace aggregate;

/**
 * Les totaux du chiffre des ventes dont la lecture de la saison de vente a besoin
 * (`CompositionSourceAiLib::getSaleSeasonality()`), rangés à part des cases de `Sale`.
 *
 * Trois sortes de lignes, que distinguent les colonnes vides :
 * - le total d'un mois, `plant` vide ;
 * - le total d'une espèce sur l'année, `month` vide ;
 * - le total de l'année, `plant` et `month` vides.
 *
 * Un total somme toutes les cases, y compris celles que `Sale` n'écrit pas parce qu'elles sont sous
 * le seuil d'anonymat : les écarter gonflerait la part des espèces courantes dans un mois où les
 * espèces rares ne passent pas. C'est pour cela qu'ils ne sont pas lus dans `Sale`, et c'est pour
 * cela qu'ils vivent dans une autre table : `Sale` ne porte que des cases servies, et aucun lecteur
 * de `Sale` ne peut prendre un total pour une case.
 *
 * Un total n'est écrit que si ce qu'il ajoute aux cases servies qu'il couvre, son reste, passe
 * lui-même le seuil d'anonymat : sinon la différence entre le total et les cases servies livrerait
 * le chiffre de quelques structures. Un total absent vaut, à la lecture, la somme des cases servies
 * (selling\SaleProjectionAiLib::getTotals()). `contributors` porte les structures de tout le total, et les
 * tests de la projection et de la lecture vérifient qu'aucune ligne ni aucun reste n'est sous le
 * seuil.
 *
 * Les écritures sont celles d'un passage de selling\SaleProjectionAiLib, qui réécrit la table entière dans
 * la même transaction que `Sale`.
 */
class SaleTotalAiLib extends SaleTotalCrud {

	/**
	 * Dépose les lignes d'un passage, en une écriture : au plus une par mois, une par espèce servie
	 * et une pour l'année, quelques centaines au plus. Écriture directe pour la raison écrite sur
	 * SaleAiLib::createCollection().
	 */
	public static function createCollection(\Collection $cSaleTotal): void {

		SaleTotal::model()->insert($cSaleTotal);

	}

	public static function deleteAll(): void {

		SaleTotal::model()
			->all()
			->delete();

	}

}
?>
