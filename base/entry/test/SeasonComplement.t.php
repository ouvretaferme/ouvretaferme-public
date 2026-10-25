<?php
namespace entry;

/**
 * La saison tapée après l'espèce (17/10) : un nombre d'année se lit comme la saison, sauf
 * suivi d'une unité, où il reste une quantité. L'horloge est figée : les saisons lues sont celles qui
 * entourent aujourd'hui.
 */
class SeasonComplementTest extends \Test {

	private function read(string $text): array {

		$words = EntryLib::split($text);
		$season = NULL;
		$quantity = NULL;

		$typing = EntryLib::getTyping($text, $words);

		SeasonComplementLib::read($words, $season, $typing, 'harvest');
		QuantityComplementLib::read($words, $quantity, $typing, 'harvest');

		return [$season, $quantity, array_values($words)];

	}

	public function testSeason(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertEquals([2025, NULL, ['ail']], $this->read('ail 2025 '));
		$this->assertEquals([2025, NULL, ['ail']], $this->read('ail 2025'), 'en cours de frappe, l\'année est déjà la saison');
		$this->assertEquals([2025, 12.0, ['ail']], $this->read('ail 2025 12 '));
		$this->assertEquals([2025, 12.0, ['ail']], $this->read('ail 12 2025 '));
		$this->assertEquals([2027, NULL, ['ail']], $this->read('ail saison 2027 '), 'le mot saison qui la précède est pris avec elle');

	}

	/**
	 * Suivi d'une unité, le nombre reste une quantité, et le début d'une unité en cours de frappe compte
	 * déjà : « 2025 k » se compose en « 2025 kg ».
	 */
	public function testUnitFollows(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertEquals([NULL, 2025.0, ['ail', 'kg']], $this->read('ail 2025 kg '));
		$this->assertEquals([NULL, 2025.0, ['ail', 'kg']], $this->read('ail 2025kg '));
		$this->assertEquals([NULL, 2025.0, ['ail', 'k']], $this->read('ail 2025 k'));
		$this->assertEquals([NULL, 2025.0, ['ail', 'bottes']], $this->read('ail 2025 bottes '));
		$this->assertEquals([NULL, 2026.0, ['ail', 'pieces']], $this->read('ail 2026 pièces '));

	}

	/**
	 * Les années lues sont les saisons dont la saisie lit les cultures à la date du jour
	 * (`CultivationResolveLib::getSeasons()`), plus une de chaque côté, que la lecture refuse ensuite
	 * (`EntryLib::read()`) : au-delà, le nombre reste une quantité.
	 */
	public function testBounds(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertEquals([2024, NULL, ['ail']], $this->read('ail 2024 '));
		$this->assertEquals([2028, NULL, ['ail']], $this->read('ail 2028 '));
		$this->assertEquals([NULL, 2023.0, ['ail']], $this->read('ail 2023 '));
		$this->assertEquals([NULL, 2029.0, ['ail']], $this->read('ail 2029 '));
		$this->assertEquals([NULL, 2025.5, ['ail']], $this->read('ail 2025,5 '));
		$this->assertEquals([NULL, 202.0, ['ail']], $this->read('ail 202'));

		$this->setClock('2027-03-01 10:00:00');

		$this->assertEquals([2029, NULL, ['ail']], $this->read('ail 2029 '), 'les saisons avancent avec l\'année');
		$this->assertEquals([NULL, 2024.0, ['ail']], $this->read('ail 2024 '));

	}

	/**
	 * Une seconde année n'est pas réclamée : la quantité la prend, comme un second nombre.
	 */
	public function testSecondYear(): void {

		$this->setClock('2026-10-16 10:00:00');

		$this->assertEquals([2025, 2026.0, ['ail']], $this->read('ail 2025 2026 '));

	}

}
?>
