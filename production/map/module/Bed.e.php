<?php
namespace map;

class Bed extends BedElement {

	public static function getSelection(): array {
		return parent::getSelection() + [
			'zone' => ['seasonFirst', 'seasonLast', 'name'],
			'plot' => ['seasonFirst', 'seasonLast', 'name', 'zoneFill', 'zone', 'mode'],
		];
	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('name')
			->addAfterCheck('empty', function(?string $name): bool {
				return ($name !== NULL);
			})
			->addAfterCheck('duplicate', function(string $name, \PropertyList $l): bool {

				// Sans jardin valide il n'y a pas de périmètre où chercher un doublon : sans ce
				// garde, expects() lèverait une exception au lieu de laisser parler l'échec du jardin
				$l->skipIfInvalid('plot');

				$this->expects(['plot']);

				if($this->offsetExists('id')) {
					Bed::model()->whereId('!=', $this);
				}

				return Bed::model()
					->wherePlot($this['plot'])
					->whereName($name)
					->whereStatus(Bed::ACTIVE)
					->exists() === FALSE;

			});

		$l->property('plot')
			->addAfterCheck('owned', function(Plot $ePlot, \PropertyList $l): bool {

				switch($l->for) {

					case 'create' :

						$this->expects(['structure']);

						if(
							Plot::model()
								->select(PlotElement::getSelection())
								->whereStructure($this['structure'])
								->get($ePlot) === FALSE
						) {
							return FALSE;
						}

						$this['zone'] = $ePlot['zone'];

						return TRUE;

					// En modification, la planche peut changer de parcelle : le jardin suit
					default :

						$this->expects(['structure']);

						if(
							Plot::model()
								->select(PlotElement::getSelection())
								->whereStructure($this['structure'])
								->get($ePlot) === FALSE
						) {
							return FALSE;
						}

						$this['zone'] = $ePlot['zone'];

						return TRUE;

				}

			});

		$l->property('length')
			->replaceCheck(function(?float $length): bool {

				return (
					$length !== NULL and
					Bed::model()->check('length', $length)
				);

			});

		$l->property('width')
			->replaceCheck(function(?float $width): bool {

				return (
					$width !== NULL and
					Bed::model()->check('width', $width)
				);

			});

		$l->property('seasonFirst')
			->addAfterCheck('plot', function(?int $season): bool {
				$this->expects([
					'plot' => ['seasonFirst', 'seasonLast']
				]);
				return ($season === NULL or $this['plot']['seasonFirst'] === NULL or $season >= $this['plot']['seasonFirst']);
			});

		$l->property('seasonLast')
			->addAfterCheck('plot', function(?int $season): bool {
				$this->expects([
					'plot' => ['seasonFirst', 'seasonLast']
				]);
				return ($season === NULL or $this['plot']['seasonLast'] === NULL or $season <= $this['plot']['seasonLast']);
			})
			->addAfterCheck('consistency', function(?int $season): bool {

				return (
					$this['seasonFirst'] === NULL or
					$season === NULL or
					$this['seasonFirst'] <= $season
				);
			});

	}

}
?>