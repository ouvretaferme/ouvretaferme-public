<?php
namespace planning;

/**
 * « Supprimer » des Récoltes (`harvestWeek:doHarvestDelete`) retire du planning l'intervention qu'il laisse sans
 * rien (`\series\Task::isHarvestShell()`, consigne du 2026-10-19), et laisse celle qui porte encore
 * quelque chose : du temps un autre jour, un commentaire, du matériel, une photo, un emplacement. Une récolte
 * à faire n'est jamais une coquille.
 *
 * Lancement : dev/test package=planning file=HarvestShell
 */
class HarvestShellTest extends HarvestFixtureTest {

	const STRICT = TRUE;

	/**
	 * Une récolte saisie dans les Récoltes, avec une quantité et du temps : « Supprimer » la retire, et le stock
	 * rend ce qu'il avait reçu.
	 */
	public function testShellDeleted(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$ePlant = $this->createPlant();
		$eCultivation = $this->createCultivation(ePlant: $ePlant);
		$eProduct = $this->createProduct('Stock de la coquille', $this->getUnit(\series\Task::KG), 10.0, ePlant: $ePlant);

		$eTask = $this->harvest($eCultivation, ['stock' => $eProduct['id']]);

		if($this->assertDecorPresent($eTask->notEmpty() and $this->getStock($eProduct) === 15.0, 'une récolte de 5 kg et 1 h au stock') === FALSE) {
			return;
		}

		$this->delete($eCultivation, ['stock' => $eProduct['id']]);

		$this->assertFalse($this->exists($eTask), 'la récolte vidée reste au planning');
		$this->assertEquals(10.0, $this->getStock($eProduct), 'le stock ne rend pas les 5 kg');

	}

	/**
	 * La même récolte, qui porte encore un commentaire, du matériel, une photo ou un emplacement, reste.
	 */
	public function testContentKeeps(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$cases = [
			'un commentaire' => fn(\series\Task $eTask) => $this->createComment($eTask, 'Rangé en chambre froide', '10:05:00'),
			// Écrit en direct : le matériel se choisit dans le formulaire de l'intervention, dont `build()` vérifierait
			// que l'outil est à la structure ; seule compte ici une liste non vide.
			'du matériel' => fn(\series\Task $eTask) => \series\Task::model()->update($eTask, ['tools' => [1]]),
			// Écrite en direct : `PhotoLib::create()` lit l'image sur le disque, et seule compte ici la ligne qui
			// rattache une photo à l'intervention.
			'une photo' => function(\series\Task $eTask) {

				$ePhoto = new \gallery\Photo([
					'structure' => $this->eStructure,
					'task' => $eTask,
					'hash' => str_repeat('a', 20),
					'width' => 1,
					'height' => 1,
				]);

				\gallery\Photo::model()->insert($ePhoto);

			},
			// Écrit en direct, comme `createPlaceForeign()` : `PlaceLib::addBeds()` lit l'usage de la série de la culture,
			// et seule compte ici la ligne qui rattache une planche à l'intervention.
			'un emplacement' => function(\series\Task $eTask) {

				$eBed = $this->createBed();

				$ePlace = new \series\Place([
					'structure' => $this->eStructure,
					'season' => $this->getSeason(),
					'series' => new \series\Series(),
					'task' => $eTask,
					'zone' => $eBed['zone'],
					'plot' => $eBed['plot'],
					'bed' => $eBed,
					'length' => NULL,
					'width' => NULL,
					'area' => NULL,
					'createdBy' => $this->getMember(),
				]);

				\series\Place::model()->insert($ePlace);

			},
		];

		foreach($cases as $case => $add) {

			$eCultivation = $this->createCultivation(ePlant: $this->createPlant());
			$eTask = $this->harvest($eCultivation);

			if($this->assertDecorPresent($eTask->notEmpty(), $case.' : une récolte de 5 kg et 1 h') === FALSE) {
				return;
			}

			$add($eTask);

			$this->delete($eCultivation);

			$this->assertTrue($this->exists($eTask), $case.' : la récolte qui le porte quitte le planning');

		}

	}

	/**
	 * Une récolte du plan, encore à faire, qui ne porte que du temps : « Supprimer » le retire, elle reste à
	 * faire. Récoltée puis supprimée, elle est faite et vide : elle part, et la prévision de la culture ne bouge
	 * pas (tranché par M-Planning le 2026-10-19).
	 */
	public function testPlanned(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week(), \Clock::date());

		$this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '', 'time' => $this->getTimeInput('1')]);

		if($this->assertDecorPresent($this->getStatus($eTask) === \series\Task::TODO, 'une récolte du plan à faire, avec 1 h') === FALSE) {
			return;
		}

		$this->delete($eCultivation);

		$this->assertEquals(\series\Task::TODO, $this->getStatus($eTask), 'la récolte du plan à faire ne reste pas à faire');

		$expected = $this->getExpected($eCultivation);

		$this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3']);

		if($this->assertDecorPresent($this->getStatus($eTask) === \series\Task::DONE, 'la récolte du plan faite par 3 kg') === FALSE) {
			return;
		}

		$this->delete($eCultivation);

		$this->assertFalse($this->exists($eTask), 'la récolte du plan, récoltée puis vidée, reste au planning');
		$this->assertEquals($expected, $this->getExpected($eCultivation), 'la prévision de la culture a bougé');

	}

	/**
	 * Une récolte qui a du temps un autre jour de la semaine reste : « Supprimer » ne retire que le jour ouvert.
	 */
	public function testTimeOtherDay(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());
		$eTask = $this->harvest($eCultivation);

		if($this->assertDecorPresent($eTask->notEmpty(), 'une récolte de 5 kg et 1 h') === FALSE) {
			return;
		}

		$this->createTimesheet($eTask, 0.5, date('Y-m-d', strtotime(\Clock::date().' -1 day')));

		$this->delete($eCultivation);

		$this->assertTrue($this->exists($eTask), 'la récolte qui a du temps la veille quitte le planning');

	}

	/**
	 * Deux interventions se partagent le jour : celle qui garde un commentaire reste, l'autre part.
	 */
	public function testShared(): void {

		if($this->requireAction(ACTION_RECOLTE) === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivation(ePlant: $this->createPlant());

		$eTaskKept = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$eTaskShell = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());

		$this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '2', 'task' => $eTaskKept['id']]);
		$this->post($eCultivation, ['mode' => 'add', 'harvestMore' => '3', 'task' => $eTaskShell['id']]);
		$this->createComment($eTaskKept, 'Pour la boutique', '10:05:00');

		if($this->assertDecorPresent($this->getHarvested($eTaskKept) === 2.0 and $this->getHarvested($eTaskShell) === 3.0, 'deux récoltes du jour, de 2 et 3 kg') === FALSE) {
			return;
		}

		$this->delete($eCultivation);

		$this->assertTrue($this->exists($eTaskKept), 'la récolte qui garde un commentaire quitte le planning');
		$this->assertFalse($this->exists($eTaskShell), 'la récolte vidée du jour partagé reste au planning');

	}

	/**
	 * 5 kg et 1 h aujourd'hui, sur une récolte neuve de la culture : rend l'intervention écrite.
	 */
	private function harvest(\series\Cultivation $eCultivation, array $input = []): \series\Task {

		$this->post($eCultivation, $input + ['mode' => 'add', 'harvestMore' => '5', 'time' => $this->getTimeInput('1')]);

		return \series\Task::model()
			->select(\series\Task::getSelection())
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->get();

	}

	private function post(\series\Cultivation $eCultivation, array $input): void {

		$action = $this->executePage('planning/harvestWeek:doHarvest', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
		], 'POST');

		$this->assertTrue($action instanceof \ViewAction, 'la saisie est refusée : '.implode(', ', $this->getFails($action)));

	}

	private function delete(\series\Cultivation $eCultivation, array $input = []): void {

		$action = $this->executePage('planning/harvestWeek:doHarvestDelete', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
		], 'POST');

		$this->assertTrue($action instanceof \ViewAction, '« Supprimer » est refusé : '.implode(', ', $this->getFails($action)));

	}

	private function exists(\series\Task $eTask): bool {

		return \series\Task::model()->whereId($eTask)->exists();

	}

	private function getStatus(\series\Task $eTask): ?string {

		$eTaskRead = \series\Task::model()->select('status')->whereId($eTask)->get();

		return $eTaskRead->empty() ? NULL : $eTaskRead['status'];

	}

	private function getHarvested(\series\Task $eTask): float {

		return round(\series\Harvest::model()->whereTask($eTask)->getValue(new \Sql('SUM(quantity)', 'float')) ?? 0.0, 3);

	}

	private function getExpected(\series\Cultivation $eCultivation): array {

		return \series\Cultivation::model()
			->select(['yieldExpected', 'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected'])
			->whereId($eCultivation)
			->get()
			->extracts(['yieldExpected', 'harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

	}

	private function getStock(\product\Product $eProduct): ?float {

		return \product\ProductLib::getById($eProduct['id'])['stock'];

	}

}
?>
