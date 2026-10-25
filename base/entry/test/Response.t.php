<?php
namespace entry;

/**
 * Les clés de la réponse de `EntryLib::read()` sont figées : l'écran, et demain une route vocale, les
 * lisent sans garde. Chaque étape de la saisie rend les mêmes.
 */
class ResponseTest extends EntryFixtureTest {

	const KEYS = ['intention', 'verb', 'object', 'named', 'plant', 'plantNamed', 'cultivation', 'task', 'date', 'mode', 'complements', 'complementsNamed', 'lines', 'couple', 'units', 'days', 'question', 'confirmation', 'outcome', 'pending', 'typing', 'typingPrefix', 'blank', 'recap', 'refusal', 'discarded'];

	public function testKeys(): void {

		$this->createCultivationInPhase(TRUE, 'Série A');
		$this->createCultivationInPhase(TRUE, 'Série B');

		foreach([
			'',
			'rec',
			'récolte ',
			'récolte zzzqqq ',
			$this->getText(),
			$this->getText('12 kg '),
		] as $text) {

			$reading = $this->read($text);

			$this->assertEquals(self::KEYS, array_keys($reading), '« '.$text.' »');
			$this->assertEquals(['week', 'duration', 'date', 'season', 'quantity', 'unit'], array_keys($reading['complements']), '« '.$text.' »');
			$this->assertEquals(EntryLib::ADD, $reading['mode']);
			$this->assertEquals(\Clock::date(), $reading['date']);

		}

	}

	/**
	 * Ce que la liste montre au bout de la phrase : la ligne qui écrit, désignée pour Entrée, ou le
	 * refus à sa place pendant la frappe. Le total du jour n'entre dans le libellé qu'une fois la
	 * journée commencée, et aucune quantité ne s'y lit deux fois.
	 */
	public function testResults(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$results = $this->getResults($this->getText('12 sacs'));

		$this->assertEquals(1, count($results));
		$this->assertTrue($results[0]['notice']);
		// Le refus nomme toutes les unités que le champ lit, caisses comprises : c'est la donnée qui se juge ici,
		// le libellé se voit à l'écran.
		$this->assertEquals([\series\Task::KG, \series\Task::UNIT, \series\Task::BUNCH, UnitComplementLib::CRATE], $this->read($this->getText('12 sacs'))['refusal']['vocabulary']['unit']);

		$reading = $this->read($this->getText('12 sacs'));

		$this->assertTrue($reading['refusal'] !== NULL, 'le refus est porté par la lecture');
		$this->assertTrue($reading['outcome'] !== EntryLib::WRITE, 'la lecture refusée n\'écrit pas');

		$results = $this->getResults($this->getText('12 kg'));

		// La ligne qui écrit, puis les puces d'unité (`HarvestUnitChip.t.php`).
		$this->assertEquals(['write', 'unit:kg', 'unit:piece', 'unit:botte'], array_column($results, 'value'));
		$this->assertTrue($results[0]['cursor']);
		$this->assertEquals(1, substr_count($results[0]['label'].' '.$results[0]['detail'], '12'), $results[0]['label'].' · '.$results[0]['detail']);
		$this->assertEquals(0, substr_count($results[0]['label'].' '.$results[0]['detail'], 'aujourd'));
		$this->assertEquals($eCultivation['series']['name'], $results[0]['detail']);

		$reading = $this->read($this->getText('45 kg '));
		EntryLib::write($this->eStructure, $reading);

		$results = $this->getResults($this->getText('12 kg'));

		// `UnitUi::getValue()` lie le nombre à son unité par une espace insécable.
		$this->assertTrue(str_ends_with(str_replace("\u{a0}", ' ', $results[0]['label']), ', 57 kg aujourd\'hui'), $results[0]['label']);

	}

	/**
	 * Le jeton de série absorbe les mots qui ont nommé l'espèce, et eux seuls : la lecture les rend
	 * tels que la phrase les porte, pluriel compris, et la quantité reste à part.
	 */
	public function testNamedWords(): void {

		$this->createCultivationInPhase(TRUE);

		$ePlant = $this->getPlant();
		$words = EntryLib::split($ePlant['name']);

		$reading = $this->read($this->getText('12'));

		$this->assertEquals($words, $reading['named']);

		$object = EntryUi::getEntry($reading)['object'];

		$this->assertEquals($ePlant['id'], $object['value']);
		$this->assertEquals($ePlant['name'], $object['label']);
		$this->assertEquals(implode(' ', $words), $object['named']);

		$plural = $words;
		$plural[array_key_last($plural)] .= 's';

		$reading = $this->read('récolte '.implode(' ', $plural).' 12');

		$this->assertEquals($plural, $reading['named']);

		$reading = $this->read('récolte 12');

		$this->assertEquals(NULL, $reading['named']);
		$this->assertEquals(NULL, EntryUi::getEntry($reading)['object']);

	}

	/**
	 * La question nomme chaque série par son nom et, en seconde ligne, par ses lieux tels que le
	 * planning les écrit : la parcelle, puis ses planches. Une série sans lieu n'a que sa semaine.
	 */
	public function testQuestionPlaces(): void {

		$eCultivationPlaced = $this->createCultivationInPhase(TRUE, 'Série A');
		$eCultivationBare = $this->createCultivationInPhase(TRUE, 'Série B');

		$ePlot = $this->createPlot();
		$eBed = $this->createBed($ePlot);

		$this->createPlace($eCultivationPlaced['series'], $eBed, 20);

		$getResults = fn() => array_column(array_filter(
			$this->getResults($this->getText()),
			fn($result) => str_starts_with($result['value'] ?? '', 'cultivation:')
		), NULL, 'label');

		// En bloc, le planning ne nomme que la parcelle : la seconde ligne fait de même.
		$results = $getResults();

		$labels = array_keys($results);
		sort($labels);

		$this->assertEquals(['Série A', 'Série B'], $labels);

		$sub = $results['Série A']['sub'];

		$this->assertTrue(str_contains($sub, encode($ePlot['name'])), $sub);
		$this->assertFalse(str_contains($sub, encode($eBed['name'])), $sub);
		$this->assertFalse(isset($results['Série A']['detail']));

		$eSeries = $eCultivationPlaced['series'];
		$eSeries['use'] = \series\Series::BED;

		\series\Series::model()
			->select(['use'])
			->update($eSeries);

		$sub = $getResults()['Série A']['sub'];

		$this->assertTrue(str_contains($sub, encode($ePlot['name'])), $sub);
		$this->assertTrue(str_contains($sub, encode($eBed['name'])), $sub);
		$this->assertTrue(str_contains($sub, 'chevron-right'), $sub);

		$this->assertFalse(str_contains($results['Série B']['sub'] ?? '', 'chevron-right'), $results['Série B']['sub'] ?? '');

	}

	private function getResults(string $text): array {

		$cElement = new \Collection();
		$reading = EntryLib::read($this->eStructure, $text, cElement: $cElement);

		return EntryUi::getResults($this->eStructure, $reading, EntryLib::findObjects($cElement, $reading['pending']), EntryLib::findIntentions($this->eStructure, $reading['pending']));

	}

	/**
	 * Les jetons posés font le début de la phrase : le texte tapé d'un trait rend la même lecture.
	 */
	public function testTokensAreText(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$typed = $this->read($this->getText('12 kg'));
		$tokens = $this->read('Récolte '.$this->getPlant()['name'].' '.'12 kg');

		$this->assertEquals($typed['outcome'], $tokens['outcome']);
		$this->assertEquals($typed['complements'], $tokens['complements']);
		$this->assertEquals($eCultivation['id'], $tokens['cultivation']['id']);

	}

	/**
	 * Le jeton de date que le champ garde après une écriture se lit comme le jour tapé en toutes lettres.
	 */
	public function testDateTokenIsText(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$date = date('Y-m-d', \Clock::shift('-1 DAY'));

		$typed = $this->read($this->getText('12 kg hier'));
		$tokens = $this->read('Récolte '.DateComplementUi::getToken($date).' '.$this->getPlant()['name'].' '.'12 kg');

		$this->assertEquals(EntryLib::WRITE, $tokens['outcome']);
		$this->assertEquals($date, $tokens['date']);
		$this->assertEquals($typed['date'], $tokens['date']);
		$this->assertEquals($typed['complements'], $tokens['complements']);
		$this->assertEquals($eCultivation['id'], $tokens['cultivation']['id']);

	}

	/**
	 * Une quantité décimale s'écrit avec une virgule dans toutes les unités, comme dans le champ : les
	 * bottes passent par `p()`, que le rappel de `UnitUi::getValue()` ne touche pas.
	 */
	public function testDecimalComma(): void {

		foreach([\series\Task::KG, \series\Task::UNIT, \series\Task::BUNCH] as $unit) {

			$confirmation = HarvestIntentionUi::getConfirmation($this->getPlant(), ['quantity' => 1.5, 'crates' => NULL, 'unit' => $unit, 'minutes' => NULL, 'date' => \Clock::date(), 'today' => 1.5]);

			$this->assertTrue(str_contains($confirmation, '1,5'), $confirmation);
			$this->assertFalse(str_contains($confirmation, '1.5'), $confirmation);

		}

	}

}
?>
