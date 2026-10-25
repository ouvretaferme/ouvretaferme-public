<?php
namespace plant;

/**
 * Ce que la bibliothèque de cultures sert autour du plan agrégé : l'espèce ouverte,
 * le référentiel du sélecteur et le droit de choisir l'espèce.
 *
 * Le plan de culture agrégé lui-même, sa projection nocturne et ses réglages vivent
 * dans le package `aggregate` (CultivationCropPlanAiLib, CultivationProjectionAiLib,
 * CultivationAiSetting) : ce sont des données agrégées entre structures, que d'autres
 * lecteurs que cette page ont vocation à lire.
 */
class CommunityAiLib {

	/**
	 * L'espèce de référence, celle qui n'appartient à aucune structure.
	 *
	 * Chaque structure possède sa propre ligne espèce et toutes celles qui partagent
	 * un fqn décrivent la même plante ; c'est le fqn qui sert de pivot pour agréger.
	 */
	public static function getPlantByFqn(string $fqn): Plant {

		return Plant::model()
			->select(Plant::getSelection())
			->where('fqn', $fqn)
			->where('structure IS NULL')
			->get();

	}

	/**
	 * Le sélecteur d'espèce est-il servi au compte connecté ?
	 *
	 * En développement il l'est à tout le monde : la page s'y prépare sur toutes les
	 * espèces. En production il est réservé aux comptes de l'avant-première, jusqu'à
	 * l'ouverture de la campagne. Partout ailleurs la bibliothèque reste servie sur la
	 * seule espèce ouverte, et la page ne charge même pas le référentiel.
	 */
	public static function canSelectPlant(): bool {

		if(LIME_ENV === 'dev') {
			return TRUE;
		}

		$eUserOnline = \user\ConnectionLib::getOnline();

		return (
			$eUserOnline->notEmpty() and
			in_array($eUserOnline['id'], PlantSetting::COMMUNITY_PLANT_PREVIEW_USERS, TRUE)
		);

	}

	/**
	 * Les espèces du référentiel, celles qui n'appartiennent à aucune structure.
	 *
	 * Elles alimentent le sélecteur d'espèce. Aucun comptage n'y est joint : compter
	 * les cultures par espèce demande un regroupement sur toute la table des cultures
	 * (mesuré à près de quatre secondes), alors que la liste seule coûte trois
	 * millisecondes — et à une espèce près, toutes ont de la donnée. Une espèce vide
	 * tombe de toute façon sur l'état vide de la page, qui le dit.
	 */
	public static function getPlantReference(): \Collection {

		return Plant::model()
			->select(['id', 'name', 'fqn', 'vignette'])
			->where('structure IS NULL')
			->where('fqn IS NOT NULL')
			// Le référentiel mêle légumes, fruits, fleurs et grandes cultures. Les
			// créneaux et la pépinière ne décrivent que la conduite maraîchère : un
			// abricotier ou une avoine n'ont rien à faire dans cette liste.
			->whereUsageVegetable(TRUE)
			// Une espèce pérenne — asperge, rhubarbe, artichaut — s'implante une fois
			// pour plusieurs années : la page n'a rien à en dire, elle décrit des
			// créneaux d'implantation dans la saison. Le filtre sur les séries pérennes
			// écarte déjà 80 à 91 % de leurs cultures ; les servir quand même reviendrait
			// à tirer un plan de culture du résidu.
			->whereCycle(Plant::ANNUAL)
			->whereStatus(Plant::ACTIVE)
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

}
?>
