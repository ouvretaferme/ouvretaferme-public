<?php
namespace learning;

/**
 * Un fichier de la feuille signée d'une date en présentiel (lot L8e) : la photo ou le PDF de la
 * feuille d'émargement que les personnes ont signée, déposé par l'équipe après la date. Il se lit et
 * s'écrit comme la liste des inscrits de sa date, par l'équipe seule : il porte des signatures.
 */
class DateSheet extends DateSheetElement {

	/**
	 * Une photo par page de feuille, une date de plusieurs jours en a plusieurs : au-delà, ce n'est
	 * plus une feuille mais un dépôt de fichiers.
	 */
	const MAX_BY_DATE = 10;

	public static function getSelection(): array {

		return parent::getSelection() + [
			'date' => Date::getSelection(),
			'createdBy' => ['firstName', 'lastName'],
		];

	}

	public function canRead(): bool {

		$this->expects(['date']);

		return $this['date']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['date']);

		return $this['date']->canWrite();

	}

	/**
	 * Le retrait est permis à tout moment, date passée ou annulée comprise : un fichier déposé par
	 * erreur sur la mauvaise date ne doit pas y rester.
	 */
	public function acceptWrite(): bool {

		$this->expects(['date']);

		return $this['date']->acceptWrite();

	}

}
?>
