<?php
namespace series;

class Repeat extends RepeatElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanning();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('stop')
			->addAfterCheck('future', function(?string $stop): bool {

				return (
					$stop === NULL or
					strcmp($stop, \Clock::date()) > 0
				);

			})
			->addAfterCheck('season', function(?string $stop): bool {

				$this->expects([
					'task' => ['season']
				]);

				if($this['task']['season'] === NULL) {
					return TRUE;
				}

				return (
					$stop !== NULL and
					(
						(int)substr($stop, 0, 4) >= $this['task']['season'] - 1 and
						(int)substr($stop, 0, 4) <= $this['task']['season'] + 1
					)
				);

			});

	}

}
?>