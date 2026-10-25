<?php
namespace toolbox;

/**
 * Les outils de la Boîte, qui ne sont pas un module : un outil est une valeur de `Favorite.tool`, et
 * son usage une entrée de `Feature.hasToolbox`.
 */
class ToolLib {

	/**
	 * « Ne plus utiliser cet outil » (retour du 28/09) : l'outil retourne parmi ceux à
	 * essayer, gris, et ce qu'on y a saisi reste en base. Les favoris de chacun partent avec lui, sans
	 * quoi l'étoile, que `ToolUi::getStar()` garde à un favori, resterait sur un outil gris. Le
	 * prochain premier geste l'inscrit de nouveau.
	 *
	 * Les paniers font exception (01/10) : les quitter efface leur plan, c'est-à-dire leurs
	 * tailles, leurs semaines, leurs lignes et les réglages de chaque espèce, et c'est le moyen de
	 * repartir de zéro. Les produits composés déjà créés dans Vente restent : ils appartiennent aux
	 * ventes, et la référence que l'espèce portait vers un produit part avec l'espèce.
	 */
	public static function forget(\structure\Structure $eStructure, string $tool): void {

		Favorite::model()->beginTransaction();

			\structure\FeatureLib::removeToolbox($eStructure, $tool);

			$cFavorite = Favorite::model()
				->select('id')
				->whereStructure($eStructure)
				->whereTool($tool)
				->getCollection();

			foreach($cFavorite as $eFavorite) {
				FavoriteLib::delete($eFavorite);
			}

			if($tool === Favorite::BASKET) {

				$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);

				if($eCompositionPlan->notEmpty()) {
					CompositionPlanLib::delete($eCompositionPlan);
				}

			}

		Favorite::model()->commit();

	}

}
?>
