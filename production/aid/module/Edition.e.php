<?php
namespace aid;

class Edition extends EditionElement {

	// Lu comme l'aide qu'il décrit, à qui est connecté (Aid::canRead()).
	public function canRead(): bool {
		return \user\ConnectionLib::isLogged();
	}

	// Seule l'équipe tient le catalogue.
	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Les pendants sur un élément d'AidLib::applyEditionOpenOrUpcoming(), applyEditionOpen() et
	 * applyEditionUpcoming() : une édition pas encore close par sa date, qui reçoit déjà les dépôts ou
	 * ne les reçoit qu'à partir de sa date d'ouverture.
	 */
	public function isOpenOrUpcoming(): bool {

		$this->expects(['state', 'endDate']);

		return (
			$this['state'] === Edition::OPEN and
			($this['endDate'] === NULL or $this['endDate'] >= \Clock::date())
		);

	}

	public function isOpen(): bool {

		$this->expects(['startDate']);

		return ($this->isOpenOrUpcoming() and ($this['startDate'] === NULL or $this['startDate'] <= \Clock::date()));

	}

	public function isUpcoming(): bool {

		$this->expects(['startDate']);

		return ($this->isOpenOrUpcoming() and $this['startDate'] !== NULL and $this['startDate'] > \Clock::date());

	}

	public static function properties(\PropertyList $l): void {

		$l->property('endDate')
			->addAfterCheck('consistency', function(?string $endDate, \PropertyList $l): bool {

				if(
					$endDate === NULL or
					$l->isBuilt('startDate') === FALSE or
					$this['startDate'] === NULL
				) {
					return TRUE;
				}

				return $endDate >= $this['startDate'];

			});

	}

}
?>
