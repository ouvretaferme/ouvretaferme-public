<?php
namespace game;

class Tile extends TileElement {

	public static function getSelection(): array {
		return parent::getSelection() + [
			'growing' => GrowingElement::getSelection()
		];
	}

	public function isRipe(): bool {

		$this->expects(['harvestedAt']);

		return (
			$this['harvestedAt'] !== NULL and
			$this['harvestedAt'] <= \Clock::datetime()
		);

	}

	public function isHarvestable(): bool {

		if($this['growing']->empty()) {
			return FALSE;
		}

		$this->expects([
			'growing' => ['harvest']
		]);

		return ($this['growing']['harvest'] !== NULL);

	}

	public function getHarvest(array $tilesLuzerne): int {

		$this->expects([
			'growing' => ['harvest', 'bonusWatering']
		]);

		$bonus = count(array_intersect(GameSetting::ADJACENT[$this['tile']], $tilesLuzerne)) * GameSetting::BONUS_LUZERNE;

		return $this['growing']['harvest'] + $this['watering'] * $this['growing']['bonusWatering'] + $bonus;

	}

	public function canRead(): bool {

		$ePlayer = PlayerLib::getOnline();

		return (
			$ePlayer->notEmpty() and
			$this['user']->is($ePlayer['user'])
		);

	}

	public function acceptPlay(): bool {

		return (
			GameSetting::isPlaying() and
			$this['board'] <= PlayerLib::getOnline()->getBoards()
		);

	}

	public function acceptSeed(): bool {

		return $this['growing']->empty();

	}

	public function acceptWater(): bool {

		return (
			$this->isHarvestable() and
			$this['growing']['bonusWatering'] !== NULL and
			$this->isRipe() === FALSE
		);

	}

	public function acceptWeed(): bool {

		return (
			$this->isHarvestable() and
			$this->isRipe() === FALSE
		);

	}

	public function acceptHarvest(): bool {

		return (
			$this->isHarvestable() and
			$this->isRipe()
		);

	}

	public function assertSeed(): bool {

		return $this->canWrite() and $this->acceptSeed();

	}

	public function assertWater(): bool {

		return $this->canWrite() and $this->acceptWater();

	}

	public function assertWeed(): bool {

		return $this->canWrite() and $this->acceptWeed();

	}

	public function assertHarvest(): bool {

		return $this->canWrite() and $this->acceptHarvest();

	}

}
?>
