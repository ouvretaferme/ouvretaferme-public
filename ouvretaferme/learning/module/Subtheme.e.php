<?php
namespace learning;

/**
 * Une sous-thématique précise la thématique d'une formation : facultative, une au plus, toujours
 * prise dans la thématique de la formation (`Course::subtheme.consistency`).
 */
class Subtheme extends SubthemeElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Le menu n'offre la suppression qu'à une sous-thématique qu'aucune formation ne porte : les
	 * autres montrent l'entrée désactivée avec sa raison. Le refus serveur reste celui de
	 * `SubthemeLib::delete()`, qui recompte en base et rend son message. Le parent garde la règle
	 * d'écriture (`acceptWrite()`), qu'une surcharge qui ne le rappellerait pas perdrait.
	 */
	public function acceptDelete(): bool {

		$this->expects(['courses']);

		return (
			parent::acceptDelete() and
			$this['courses'] === 0
		);

	}

	public static function properties(\PropertyList $l): void {

		// La thématique voyage dans le formulaire d'ajout : une adresse forgée peut en poster une
		// qui n'existe pas, que le schéma laisse passer parce qu'il ne regarde que la forme de l'id.
		$l->property('theme')
			->addAfterCheck('exists', fn(Theme $eTheme): bool => Theme::model()->whereId($eTheme)->exists());

	}

}
?>
