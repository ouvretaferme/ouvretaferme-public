<?php
namespace game;

class FoodLib extends FoodCrud {

	public static function getByPlayer(Player $ePlayer): \Collection {

		return Food::model()
			->select(Food::getSelection() + [
				'growing' => Growing::getSelection()
			])
			->whereUser($ePlayer['user'])
			->getCollection(index: 'growing')
			->sort(['product' => ['name']], natural: TRUE);


	}

	public static function createByPlayer(Player $ePlayer): void {

		$eUser = $ePlayer['user'];
		$cGrowing = GrowingLib::getAll()->find(fn($eGrowing) => $eGrowing['harvest'] !== NULL);

		foreach($cGrowing as $eGrowing) {

			self::create(new Food([
				'user' => $eUser,
				'growing' => $eGrowing,
			]));

		}

		self::create(new Food([
			'user' => $eUser,
			'growing' => new Growing(),
		]));

	}

	public static function change(Player $ePlayer, string $history, \Collection|Growing $cGrowing, array|int $values, ?float $time = NULL): bool {

		$ePlayer->expects(['user']);

		if($cGrowing instanceof Growing) {
			$cGrowing = new \Collection([$cGrowing]);
		}

		if(is_int($values)) {
			$values = array_fill(0, $cGrowing->count(), $values);
		}

		Food::model()->beginTransaction();

			// Les stocks qui baissent se lisent sous verrou avant toute écriture : un refus ne laisse
			// rien à annuler et clôt la transaction par commit(), sans défaire celle de l'appelant.
			$position = 0;

			foreach($cGrowing as $eGrowing) {

				$value = $values[$position++];

				if($value >= 0) {
					continue;
				}

				$current = Food::model()
					->whereUser($ePlayer['user'])
					->whereGrowing($eGrowing)
					->option('for-update')
					->getValue('current');

				if($current + $value < 0) {

					Food::model()->commit();

					return FALSE;

				}

			}

			$position = 0;

			foreach($cGrowing as $eGrowing) {

				$value = $values[$position++];

				$update = [
					'current' => new \Sql('current + '.$value),
				];

				if($value > 0) {

					$update += [
						'total' => new \Sql('total + '.$value),
					];

				}

				// Une ligne par joueur et par culture, désignée par les deux : l'incrément en SQL
				// reste atomique sans relire l'élément.
				Food::model()
					->whereUser($ePlayer['user'])
					->whereGrowing($eGrowing)
					->update($update);

			}

			PlayerLib::updatePoints($ePlayer);

			HistoryLib::create(new History([
				'user' => $ePlayer['user'],
				'time' => $time,
				'message' => HistoryUi::getFoodMessage($history, $cGrowing, $values)
			]));

		Food::model()->commit();

		return TRUE;

	}

	public static function deleteByPlayer(Player $ePlayer): void {

		$cFood = Food::model()
			->select('id')
			->whereUser($ePlayer['user'])
			->getCollection();

		foreach($cFood as $eFood) {
			self::delete($eFood);
		}

	}

	/**
	 * Ce que la marmite montre au joueur : ses soupes, et combien il peut en cuisiner, une soupe prenant
	 * un légume de chaque espèce récoltable (`ActionLib::cook()`).
	 */
	public static function fillStock(Player $ePlayer, \Collection $cFood): void {

		$soups = 0;
		$stocks = [];

		foreach($cFood as $eFood) {

			if($eFood['growing']->empty()) {
				$soups = $eFood['current'];
			} else {
				$stocks[] = $eFood['current'];
			}

		}

		$ePlayer['soups'] = $soups;
		$ePlayer['cookable'] = ($stocks === []) ? 0 : min($stocks);

	}

	public static function fillRankings(\Collection $cFood): void {

		foreach($cFood as $eFood) {

			$eFood['position'] = Food::model()
					->whereGrowing($eFood['growing'])
					->whereTotal('>', $eFood['total'])
					->count() + 1;

		}

	}

}
?>
