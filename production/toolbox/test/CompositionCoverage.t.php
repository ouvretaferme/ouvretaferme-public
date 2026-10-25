<?php
namespace toolbox;

/**
 * Le poids d'une unité au panier (`CompositionLib::getUnitKilos()`), que le stock d'un plan synchronisé
 * sur le plan de culture pèse (`CompositionLib::getStock()`), sur une source construite à la main,
 * sans une requête.
 *
 * Le plan de culture face aux paniers que ce fichier éprouvait (lot L5, `getCoverage()`) est parti au
 * lot r27 avec le mode comparé : la saison de vente écrite sur chaque espèce a remplacé la
 * comparaison.
 *
 * Lancement : dev/test package=toolbox file=CompositionCoverage
 */
class CompositionCoverageTest extends \Test {

	const FRESH = 1;
	const BUNCH = 2;
	const UNWEIGHED = 3;

	/**
	 * La source des paniers : un légume au kilo, une botte que le poids courant pèse à la botte et à
	 * la pièce, une pièce sans poids.
	 */
	private function getSource(): array {

		return [
			'units' => [
				self::FRESH => ['unit' => 101, 'fqn' => 'kg'],
				self::BUNCH => ['unit' => 102, 'fqn' => 'bunch'],
				self::UNWEIGHED => ['unit' => 103, 'fqn' => 'one'],
			],
			'weights' => [
				self::BUNCH => ['bunch' => 0.4, 'one' => 0.1],
			],
		];

	}

	/**
	 * La botte pèse 0,5 kg, le poids que la Synthèse affiche ; la pièce de `UNWEIGHED` n'a de poids
	 * nulle part.
	 */
	private function getSpecies(): \Collection {

		$cCompositionSpecies = new \Collection();

		$cCompositionSpecies[self::BUNCH] = new CompositionSpecies(['weightReference' => ['weighed' => TRUE, 'weight' => 0.5, 'from' => CompositionLib::WEIGHT_TYPED]]);
		$cCompositionSpecies[self::UNWEIGHED] = new CompositionSpecies(['weightReference' => ['weighed' => TRUE, 'weight' => NULL, 'from' => NULL]]);

		return $cCompositionSpecies;

	}

	public function testUnitKilos(): void {

		$source = $this->getSource();
		$cCompositionSpecies = $this->getSpecies();

		$this->assertEquals(1.0, CompositionLib::getUnitKilos(self::FRESH, 'kg', $source, $cCompositionSpecies), 'le kilo pèse un kilo');
		$this->assertEquals(0.5, CompositionLib::getUnitKilos(self::BUNCH, 'bunch', $source, $cCompositionSpecies), 'dans l\'unité du panier, le poids que la Synthèse affiche, avant le poids courant');
		$this->assertEquals(0.1, CompositionLib::getUnitKilos(self::BUNCH, 'one', $source, $cCompositionSpecies), 'dans une autre unité, le poids courant');
		$this->assertEquals(NULL, CompositionLib::getUnitKilos(self::UNWEIGHED, 'one', $source, $cCompositionSpecies), 'une pièce que rien ne pèse');
		$this->assertEquals(NULL, CompositionLib::getUnitKilos(self::FRESH, 'bunch', $source, $cCompositionSpecies), 'une botte sans poids courant');
		$this->assertEquals(NULL, CompositionLib::getUnitKilos(self::FRESH, 'barquette', $source, $cCompositionSpecies), 'une barquette n\'a de poids nulle part');

	}

}
?>
