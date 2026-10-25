<?php
namespace entry;

/**
 * Le garde-fou de la réponse ciblée sur l'accueil, sur une récolte (`HomeFixtureTest`). Deux preuves
 * précèdent les comparaisons : le même rendu comparé à lui-même est égal, et une écriture dont on
 * n'applique pas les instructions est trouvée.
 */
class HomeFingerprintTest extends HomeFixtureTest {

	private function write(\Dom\HTMLDocument $document, string $quantity, array $input = []): array {

		return $this->getInstructions('doWrite', $input + [
			'prefix' => 'Récolte '.$this->getPlant()['name'],
			'query' => $quantity.' ',
			'origin' => 'dashboard',
			'blocks' => $this->getShown($document),
		]);

	}

	public function testProofs(): void {

		$this->createCultivationInPhase(TRUE);

		$this->assertEquals($this->getFingerprint($this->render()), $this->getFingerprint($this->render()), 'la neutralisation rend le même rendu égal à lui-même');

		$before = $this->render();

		$this->write($before, '12 kg');

		$this->assertTrue($this->getFingerprint($before) !== $this->getFingerprint($this->render()), 'une écriture sans ses instructions est trouvée');

	}

	/**
	 * Une récolte saisie sur l'accueil redessine le bloc des récoltes de la semaine, qui porte la nouvelle
	 * quantité, et son annulation le rend tel qu'avant.
	 */
	public function testHarvest(): void {

		$this->createCultivationInPhase(TRUE);

		$before = $this->render();

		$this->assertTrue($before->querySelector('#'.\dashboard\DashboardUi::getBlockId('planning-harvest')) !== NULL, 'l\'accueil montre les récoltes de la semaine');

		$instructions = $this->write($before, '12 kg');

		$change = $this->lastData->homeChange;

		$this->assertTrue(array_key_exists('planning-harvest', $change['replace']), 'les récoltes de la semaine se redessinent');
		$this->assertEquals(NULL, $change['columns'], 'sans rendre les colonnes entières');

		$this->check($before, $instructions, 'écriture');

		$cancel = $this->cancel($before, $this->lastData->reading['undo']);

		$this->check($before, $cancel, 'annulation');

	}

	/**
	 * Un bloc que l'écran ne montrait pas et que l'écriture fait paraître : les colonnes se rendent entières,
	 * le bloc prenant la place que le membre a réglée.
	 */
	public function testAppears(): void {

		$this->createCultivationInPhase(TRUE);

		$before = $this->render();
		$before->querySelector('#'.\dashboard\DashboardUi::getBlockId('planning-harvest'))->remove();

		$instructions = $this->write($before, '12 kg');

		$this->assertTrue($this->lastData->homeChange['columns'] !== NULL, 'les colonnes se rendent entières');

		$this->check($before, $instructions, 'apparition');

	}

	/**
	 * Depuis le menu, ou d'une origine inconnue, l'écriture ne lit aucun bloc et ne rend que la pastille.
	 */
	public function testOutside(): void {

		$this->createCultivationInPhase(TRUE);

		foreach([NULL, 'planning'] as $origin) {

			$input = [
				'prefix' => 'Récolte '.$this->getPlant()['name'],
				'query' => '12 kg ',
				'blocks' => 'planning-harvest',
			];

			if($origin !== NULL) {
				$input['origin'] = $origin;
			}

			$instructions = $this->getInstructions('doWrite', $input);

			$this->assertEquals(NULL, $this->lastData->homeChange, 'origine '.($origin ?? 'absente').' : rien de l\'accueil n\'est lu');
			$this->assertEquals(1, count($instructions), 'origine '.($origin ?? 'absente').' : la pastille seule');

		}

	}

	/**
	 * Un nom posté que l'intention ne déclare pas ne fait rien lire.
	 */
	public function testShownOutsideDeclared(): void {

		$this->createCultivationInPhase(TRUE);

		$before = $this->render();

		$this->write($before, '12 kg', ['blocks' => 'selling-deliveries,planning-harvest']);

		$this->assertTrue(array_key_exists('selling-deliveries', $this->lastData->homeChange['replace']) === FALSE, 'un bloc que l\'intention ne déclare pas n\'est pas relu');

	}

}
?>
