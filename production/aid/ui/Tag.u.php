<?php
namespace aid;

/**
 * Les onze projets par lesquels on entre dans les aides, et ce qu'elles financent. Un projet n'est
 * pas une table : c'est un bit de `Tag.projects`, qui porte ici son nom et son icône.
 */
class TagUi {

	public static function getProjectIcon(int $project): string {

		return match($project) {
			Tag::INVEST => 'tools',
			Tag::INSTALL => 'house-door',
			Tag::ADVICE => 'mortarboard',
			Tag::CRISIS => 'umbrella',
			Tag::IRRIGATION => 'droplet',
			Tag::ENERGY => 'lightning-charge',
			Tag::ENVIRONMENT => 'tree',
			Tag::ORGANIC => 'flower1',
			Tag::SELL => 'basket',
			Tag::LAND => 'geo-alt',
			Tag::EMPLOYMENT => 'people'
		};

	}

	/**
	 * Les tags d'une aide en badges, en contour : un classement, pas un état. Dans la liste d'un projet,
	 * seuls ceux du projet s'affichent : la carte n'y porte que ce que le rail au-dessus permet de
	 * choisir. Dans la liste d'un tag (`$eTagFiltered`), le titre de la liste le dit déjà.
	 */
	public static function getBadges(\Collection $cTag, ?int $project = NULL, Tag $eTagFiltered = new Tag()): string {

		$h = '';

		foreach($cTag as $eTag) {

			if($project !== NULL and $eTag['projects']->value($project) === FALSE) {
				continue;
			}

			if($eTagFiltered->notEmpty() and $eTagFiltered['id'] === $eTag['id']) {
				continue;
			}

			$h .= '<span class="badge badge-outline badge-production">'.encode($eTag['name']).'</span>';

		}

		return ($h === '') ? '' : '<div class="badges">'.$h.'</div>';

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Tag::model()->describer($property, [
			'name' => s("Nom"),
			'projects' => s("Projets"),
		]);

		switch($property) {

			case 'projects' :
				$d->values = [
					Tag::INVEST => s("Investir dans du matériel ou un bâtiment"),
					Tag::INSTALL => s("S'installer"),
					Tag::ADVICE => s("Se faire conseiller, se former"),
					Tag::CRISIS => s("Traverser une crise, soulager la trésorerie"),
					Tag::IRRIGATION => s("Irriguer, stocker l'eau"),
					Tag::ENERGY => s("Réduire la facture d'énergie"),
					Tag::ENVIRONMENT => s("Planter des haies, protéger l'environnement"),
					Tag::ORGANIC => s("Passer ou rester en bio"),
					Tag::SELL => s("Transformer, vendre en direct"),
					Tag::LAND => s("Accéder au foncier"),
					Tag::EMPLOYMENT => s("Embaucher, se faire remplacer"),
				];
				break;

		}

		return $d;

	}

}
?>
