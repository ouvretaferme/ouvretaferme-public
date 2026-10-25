<?php
namespace user;

/**
 * Chaque choix du catalogue de l'éditeur d'avatar a-t-il de quoi s'afficher ?
 *
 * **De quoi c'est l'inventaire.** Les familles et les entrées de `base/scene/static/avatar/catalog.json`, que
 * l'outil de livraison du skill 3d-perso écrit, et pour chacune ce que l'éditeur montre : le libellé de la famille,
 * celui de l'entrée, celui du choix « aucun » d'une famille facultative (`UserAvatarUi::p()`), et la vignette de
 * l'entrée rendue d'avance (`base/user/static/image/avatar/<famille>-<entrée>.png`). Une palette partagée
 * (`sharedPalettes`) a sa propre rangée, et le libellé de celle-ci.
 *
 * **Pourquoi une sentinelle.** Le catalogue change dans le skill, hors du package `user`. Une entrée ajoutée
 * là-bas arrive dans l'éditeur sans libellé ni vignette, et rien ne lève avant qu'un utilisateur voie un choix vide.
 *
 * **Quel côté fait foi.** Le json : une entrée qu'il porte reçoit son libellé et sa vignette ici, une entrée qu'il ne
 * porte plus voit son libellé et sa vignette retirés d'ici, jamais l'inverse.
 *
 * Lecture seule, rien n'est écrit en base.
 *
 * Lancement : dev/test package=user file=AvatarCatalogSentinel
 */
class AvatarCatalogSentinelTest extends \Test {

	/**
	 * Les familles du catalogue que l'éditeur n'offre pas encore, et pourquoi.
	 */
	const FAMILIES_PENDING = [
		UserAvatarLib::ACCESSORY => 'enregistrée sur UserAvatar.accessory, offerte par le lot de l\'accessoire',
	];

	/**
	 * Les images du répertoire des vignettes qui ne sont pas des choix.
	 */
	const IMAGES_OTHER = [
		'visage.png' => 'le visage du bouton « Avatar » de l\'onglet 3D (production/map/ui/Scene.u.php)',
		'haut-chemise-carreaux.png' => 'la chemise à carreaux de la grille des hauts du design system (ouvretaferme/design/ui/Design.u.php)',
	];

	public function testEveryFamilyIsOfferedOrPending(): void {

		$families = array_column(UserAvatarLib::getCatalog()['families'], 'key');

		$stale = array_values(array_diff(array_keys(self::FAMILIES_PENDING), $families));
		$this->assertEquals([], $stale, 'familles déclarées en attente que catalog.json ne porte plus, à retirer de FAMILIES_PENDING : '.implode(', ', $stale));

		$offered = array_keys(UserAvatarLib::getFamilies());
		$pending = array_values(array_intersect($families, array_keys(self::FAMILIES_PENDING)));

		$familiesUnaccounted = array_values(array_diff($families, $offered, $pending));

		$this->assertEquals([], $familiesUnaccounted, 'familles de catalog.json ni offertes par l\'éditeur ni déclarées dans FAMILIES_PENDING, à déclarer en attente ou à offrir : '.implode(', ', $familiesUnaccounted));

	}

	public function testEveryChoiceHasALabelAndAVignette(): void {

		$families = UserAvatarLib::getFamilies();

		$labelsFamily = UserAvatarUi::p('family')->values;
		$labelsNone = UserAvatarUi::p('none')->values;
		$labelsEntry = UserAvatarUi::p('entry')->values;
		$labelsPalette = UserAvatarUi::p('palette')->values;
		$palettesShared = UserAvatarLib::getCatalog()['sharedPalettes'];

		$missing = [];
		$entries = [];
		$vignettes = [];

		foreach($families as $family => ['entries' => $entriesFamily, 'optional' => $optional]) {

			if(array_key_exists($family, $labelsFamily) === FALSE) {
				$missing[] = 'libellé de la famille '.$family;
			}

			if($optional and array_key_exists($family, $labelsNone) === FALSE) {
				$missing[] = 'libellé « aucun » de la famille '.$family;
			}

			foreach($entriesFamily as $entry) {

				$entries[] = $entry;
				$vignettes[] = $family.'-'.$entry.'.png';

				if(array_key_exists($entry, $labelsEntry) === FALSE) {
					$missing[] = 'libellé de l\'entrée '.$entry;
				}

				if(is_file($this->getDirectory().'/'.$family.'-'.$entry.'.png') === FALSE) {
					$missing[] = 'vignette '.$family.'-'.$entry.'.png';
				}

			}

		}

		foreach($palettesShared as $palette) {

			if(array_key_exists($palette, $labelsPalette) === FALSE) {
				$missing[] = 'libellé de la palette partagée '.$palette;
			}

		}

		$this->assertEquals([], $missing, 'choix de catalog.json sans de quoi s\'afficher, à ajouter dans UserAvatarUi::p() ou base/user/static/image/avatar/ : '.implode(', ', $missing));

		$staleLabels = array_values(array_merge(
			array_diff(array_keys($labelsFamily), array_keys($families)),
			array_diff(array_keys($labelsNone), array_keys(array_filter($families, fn(array $family) => $family['optional']))),
			array_diff(array_keys($labelsEntry), $entries),
			array_diff(array_keys($labelsPalette), $palettesShared),
		));

		$this->assertEquals([], $staleLabels, 'libellés de UserAvatarUi::p() que catalog.json ne porte plus, à retirer : '.implode(', ', $staleLabels));

		$staleVignettes = array_values(array_diff(
			array_map(basename(...), glob($this->getDirectory().'/*.png')),
			$vignettes,
			array_keys(self::IMAGES_OTHER)
		));

		$this->assertEquals([], $staleVignettes, 'vignettes de base/user/static/image/avatar/ que catalog.json ne porte plus, à retirer : '.implode(', ', $staleVignettes));

	}

	private function getDirectory(): string {
		return \Package::getPath('user').'/static/image/avatar';
	}

}
?>
