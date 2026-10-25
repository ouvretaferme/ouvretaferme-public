<?php
namespace map;

/**
 * Le nom par défaut d'un jardin et d'une parcelle pendant l'onboarding des emplacements.
 *
 * Le test protège la simplification de trim((string)($input['name'] ?? '')) en
 * trim($input['name'] ?? '') : le cast était inutile, la valeur venant d'un POST est
 * une chaîne ou rien du tout. Ce qui doit continuer de marcher, c'est la détection de
 * « rien de saisi », espaces seuls compris — sans elle build() refuserait la chaîne
 * vide sur une colonne text8(1, …).
 *
 * Aucune base : la fonction sous test est pure, l'entrée arrive en argument.
 *
 * Lancement : dev/test package=map file=OnboardingNameAi
 */
class OnboardingNameAiTest extends \Test {

	private function isEmpty(mixed $value): bool {

		return (trim($value ?? '') === '');

	}

	public function testWhatCountsAsNoNameGiven(): void {

		$this->assertTrue($this->isEmpty(NULL), 'clé absente');
		$this->assertTrue($this->isEmpty(''), 'chaîne vide');
		$this->assertTrue($this->isEmpty('   '), 'espaces seuls');
		$this->assertTrue($this->isEmpty("\t\n"), 'tabulation et retour ligne');

	}

	public function testARealNameIsKept(): void {

		$this->assertFalse($this->isEmpty('Serre 1'), 'un nom ordinaire');
		$this->assertFalse($this->isEmpty(' Serre 1 '), 'un nom entouré d\'espaces');
		$this->assertFalse($this->isEmpty('0'), 'un nom qui vaut zéro — le piège du test truthy');

	}

	public function testTheDefaultNamesExist(): void {

		$this->assertTrue(OnboardingLib::getDefaultZoneName() !== '', 'le jardin a un nom de repli');
		$this->assertTrue(OnboardingLib::getDefaultPlotName(Plot::GREENHOUSE, 1) !== '', 'la parcelle sous abri aussi');
		$this->assertTrue(OnboardingLib::getDefaultPlotName(Plot::OPEN_FIELD, 1) !== '', 'et en plein champ');

	}

}
?>
