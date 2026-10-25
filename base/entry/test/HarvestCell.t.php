<?php
namespace entry;

/**
 * La case d'un jour de récolte (`EntryUi::getHarvestCell()`) : la série, le jour et le couple variété · calibre
 * posés par la case (`EntryLib::getCell()`), sans que la phrase nomme l'espèce.
 */
class HarvestCellTest extends EntryFixtureTest {

	/**
	 * La case comme la page la lit : `POST('cell', 'array')`, des chaînes, le couple vide en chaîne vide, et
	 * `part` absent pour la case de la série entière.
	 */
	private function getCell(\series\Cultivation $eCultivation, ?string $date = NULL, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), bool $part = FALSE): array {

		return EntryLib::getCell($this->eStructure, [
			'cultivation' => (string)$eCultivation['id'],
			'date' => $date ?? \Clock::date(),
			'variety' => $eVariety->empty() ? '' : (string)$eVariety['id'],
			'harvestSize' => $eSize->empty() ? '' : (string)$eSize['id'],
		] + ($part ? ['part' => '1'] : []));

	}

	private function readCell(string $text, array $cell): array {

		return $this->read($text, ['cell' => $cell]);

	}

	/**
	 * « 12 kg » suffit : la récolte, l'espèce et la série sont celles de la case, qui ne les fait pas nommer.
	 */
	public function testQuantityAlone(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->readCell('12 kg ', $this->getCell($eCultivation));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], '« 12 kg » s\'écrit dans la case');
		$this->assertEquals('harvest', $reading['intention']);
		$this->assertEquals($eCultivation['plant']['id'], $reading['object']['id'], 'l\'espèce est celle de la série');
		$this->assertEquals(NULL, $reading['named'], 'aucun mot ne l\'a nommée');
		$this->assertEquals($eCultivation['id'], $reading['cultivation']['id']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals(12.0, (float)$this->getHarvest($eCultivation)->first()['harvest']);

	}

	/**
	 * La case d'une série désigne sa série, même quand l'espèce en a une autre en récolte : le menu poserait
	 * la question, la case non.
	 */
	public function testSeriesWithoutQuestion(): void {

		$this->createCultivationInPhase(TRUE, 'Série du décor A');
		$eCultivation = $this->createCultivationInPhase(TRUE, 'Série du décor B');

		$this->assertEquals('cultivation', $this->read($this->getText('12 kg '))['question']['name'] ?? NULL, 'le menu demande la série');

		$reading = $this->readCell('12 kg ', $this->getCell($eCultivation));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals($eCultivation['id'], $reading['cultivation']['id'], 'la case écrit sur sa série');

	}

	/**
	 * La case d'une part écrit sur l'intervention de son couple, qu'elle crée puis complète, et dit le total
	 * de cette intervention.
	 */
	public function testCouple(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);
		$eSize = $this->createSize($eCultivation['plant']);

		$cell = $this->getCell($eCultivation, eVariety: $eVariety, eSize: $eSize, part: TRUE);

		$reading = $this->readCell('12 kg ', $cell);

		$this->assertTrue($reading['confirmation']['created'], 'le couple n\'a pas encore d\'intervention');

		EntryLib::write($this->eStructure, $reading);

		$reading = $this->readCell('8 kg ', $cell);

		$this->assertFalse($reading['confirmation']['created'], 'la seconde saisie complète la première');
		$this->assertEquals(20.0, $reading['confirmation']['today']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = \series\Task::model()
			->select(['harvest', 'variety', 'harvestSize'])
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->getCollection();

		$this->assertEquals(1, $cTask->count());
		$this->assertEquals(20.0, (float)$cTask->first()['harvest']);
		$this->assertEquals($eVariety['id'], $cTask->first()['variety']['id']);
		$this->assertEquals($eSize['id'], $cTask->first()['harvestSize']['id']);

	}

	/**
	 * Le temps seul s'écrit aussi dans la case, sur la récolte du jour.
	 */
	public function testTimeAlone(): void {

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure du décor suit le temps') === FALSE) {
			return;
		}

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$reading = $this->readCell('30 min ', $this->getCell($eCultivation));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(NULL, $reading['confirmation']['quantity']);
		$this->assertEquals(30, $reading['confirmation']['minutes']);

	}

	/**
	 * Ce que la case dit déjà ne se redit pas autrement : une autre espèce, un autre verbe, un autre jour, une
	 * série hors des candidates du jour. Le jour de la case suit la règle de la récolte.
	 */
	public function testRefusals(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$cell = $this->getCell($eCultivation);

		$eCultivationOther = $this->createCultivation($this->createSeries('Série du décor autre'), $this->createPlant('Autre'));
		$this->setPhase($eCultivationOther, TRUE);

		$cases = [
			'une autre espèce' => [$eCultivationOther['plant']['name'].' 12 kg ', $cell, 'cell'],
			'un autre verbe' => ['temps 30 min ', $cell, 'cell'],
			'un autre jour' => ['hier 12 kg ', $cell, 'cellDate'],
			'une case à venir' => ['12 kg ', $this->getCell($eCultivation, date('Y-m-d', \Clock::shift('+ 1 DAY'))), 'future'],
		];

		foreach($cases as $case => [$text, $cellCase, $reason]) {

			$reading = $this->readCell($text, $cellCase);

			$this->assertEquals(EntryLib::REFUSE, $reading['outcome'], $case.' est refusé');
			$this->assertEquals($reason, $reading['refusal']['reason'] ?? NULL, $case.' est refusé pour sa raison');

		}

		$eCultivationOff = $this->createCultivationOfSeason('Série du décor deux saisons plus tôt', $this->getSeason() - 2);

		$reading = $this->readCell('12 kg ', $this->getCell($eCultivationOff));

		$this->assertEquals('cellSeries', $reading['refusal']['reason'] ?? NULL, 'une série hors des candidates du jour est refusée');

	}

	/**
	 * Une part se saisit dans une autre unité comme la série entière : sur l'intervention de la part dans
	 * cette unité, à côté de celle qu'elle tient déjà en kilos, et jamais dans l'unité de celle-ci.
	 */
	public function testPartOtherUnit(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$cell = $this->getCell($eCultivation, eVariety: $eVariety, part: TRUE);

		$reading = $this->readCell('12 kg ', $cell);

		EntryLib::write($this->eStructure, $reading);

		$reading = $this->readCell('40 pièces ', $cell);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'une part en kilos accepte des pièces');
		$this->assertEquals([\series\Task::UNIT, 40.0, TRUE], [$reading['confirmation']['unit'], $reading['confirmation']['quantity'], $reading['confirmation']['created']], 'les pièces vont sur une intervention neuve de la part, en pièces');

		EntryLib::write($this->eStructure, $reading);

		$cTask = \series\Task::model()
			->select(['harvest', 'harvestUnit', 'variety'])
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->sort('id')
			->getCollection();

		$this->assertEquals([
			[12.0, \series\Task::KG, $eVariety['id']],
			[40.0, \series\Task::UNIT, $eVariety['id']],
		], $cTask->makeArray(fn(\series\Task $eTask) => [(float)$eTask['harvest'], $eTask['harvestUnit'], $eTask['variety']['id']]), 'la part écrit 40 pièces et non 40 kg, ses kilos restent');

		$reading = $this->readCell('8 kg ', $cell);

		$this->assertEquals([\series\Task::KG, 20.0], [$reading['confirmation']['unit'], $reading['confirmation']['today']], 'les kilos de la part retrouvent leur intervention');

	}

	/**
	 * La case est postée par le navigateur : une culture d'une autre structure, une variété hors des parts de la
	 * culture, un calibre d'une autre espèce ou un drapeau de part illisible ne passent pas.
	 */
	public function testPostedCell(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$this->assertEquals(NULL, EntryLib::getCell($this->eStructure, []), 'hors d\'une case, rien n\'est posé');

		$eCultivationForeign = \series\Cultivation::model()
			->select('id')
			->whereStructure('!=', $this->eStructure)
			->get();

		if($this->assertDecorPresent($eCultivationForeign->notEmpty(), 'une culture d\'une autre structure') === FALSE) {
			return;
		}

		$cases = [
			'une culture d\'une autre structure' => ['cultivation' => (string)$eCultivationForeign['id'], 'date' => \Clock::date()],
			'une culture postée en liste' => ['cultivation' => [(string)$eCultivation['id']], 'date' => \Clock::date()],
			'une date illisible' => ['cultivation' => (string)$eCultivation['id'], 'date' => '2026-13-40'],
			'une variété sans part dans la culture' => ['cultivation' => (string)$eCultivation['id'], 'date' => \Clock::date(), 'variety' => (string)$this->createVariety($eCultivation['plant'])['id']],
			'un calibre d\'une autre espèce' => ['cultivation' => (string)$eCultivation['id'], 'date' => \Clock::date(), 'harvestSize' => (string)$this->createSize($this->createPlant('Autre'))['id']],
			'un drapeau de part autre que 1' => ['cultivation' => (string)$eCultivation['id'], 'date' => \Clock::date(), 'part' => 'yes'],
		];

		foreach($cases as $case => $cell) {

			$thrown = FALSE;

			try {
				EntryLib::getCell($this->eStructure, $cell);
			} catch(\Action) {
				$thrown = TRUE;
			}

			$this->assertTrue($thrown, $case.' est refusée');

		}

	}

	/**
	 * Le champ de la case porte sa série, son jour et son couple dans le corps de chaque lecture, que
	 * `entry.js` poste sans le toucher : sans eux, « 12 kg » ne se lirait pas.
	 */
	public function testFieldPostsCell(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVariety($eCultivation['plant']);

		$html = new EntryUi()->getHarvestCell($this->eStructure, $eCultivation, '2026-10-20', $eVariety);

		$this->assertEquals(1, preg_match('/data-suggestions-body="([^"]+)"/', $html, $match));

		$body = json_decode(html_entity_decode($match[1]), TRUE);

		$this->assertEquals([
			'structure' => $this->eStructure['id'],
			'cell[cultivation]' => $eCultivation['id'],
			'cell[date]' => '2026-10-20',
			'cell[variety]' => $eVariety['id'],
			'cell[harvestSize]' => '',
		], $body);

		$this->assertEquals(0, substr_count($html, 'id="entry-omnibox"'), 'la case ne prend pas l\'id du champ du menu');

	}

	/**
	 * Une case vide demande une quantité, sans unité, au focus comme au repos : sa lecture ne propose aucune puce,
	 * et la liste ne s'ouvre qu'à la première frappe, la ligne qui écrit en tête.
	 */
	public function testBlankCell(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$cell = $this->getCell($eCultivation);

		$reading = $this->readCell('', $cell);

		$this->assertTrue($reading['blank']);
		$this->assertEquals([], $reading['couple']['chips'], 'la case vide ne propose pas de puce');
		$this->assertEquals([], HarvestIntentionUi::getResults($this->eStructure, $reading, new \Collection(), new \Collection()), 'ni rien d\'autre');
		$this->assertEquals(QuantityComplementUi::getBlankPlaceholder(), EntryUi::getEntry($reading)['placeholder'], 'le focus pose la question de la case au repos');

		$reading = $this->readCell('1', $cell);

		$this->assertFalse($reading['blank']);
		$this->assertEquals(['variety'], array_keys($reading['couple']['chips']), 'la première frappe propose les puces');

		$values = array_column(HarvestIntentionUi::getResults($this->eStructure, $reading, new \Collection(), new \Collection()), 'value');

		$this->assertEquals('write', $values[0] ?? NULL, 'la ligne qui écrit en tête');

	}

}
?>
