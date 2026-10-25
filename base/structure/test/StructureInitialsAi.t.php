<?php
namespace structure;

/**
 * Les initiales d'une structure sans logo, et la couleur sur laquelle elles se posent.
 *
 * Une rangée de pastilles sans nom, comme la colonne Contrats des adhérents d'une AMAP, ne se lit
 * que si deux producteurs y diffèrent : par deux lettres qui ne sont pas l'article ou la forme
 * juridique qui ouvrent près de la moitié des noms, et par une couleur qui ne change pas d'un écran
 * à l'autre.
 *
 * Lancement : dev/test package=structure file=StructureInitialsAi
 */
class StructureInitialsAiTest extends \Test {

	const INITIALS = [
		// Deux mots qui comptent : la première lettre de chacun.
		'Bee Queen Apiculture' => 'BQ',
		'Myss\'Champi' => 'MC',
		// Les articles et prépositions se sautent, élidés compris.
		'La Ferme des Cres' => 'FC',
		'Le Fournil de la Forêt' => 'FF',
		'L\'Abeille Poitevine' => 'AP',
		// La forme juridique aussi, et un mot seul garde ses deux premières lettres.
		'GAEC MYCA' => 'MY',
		// Un nom de test ne donne pas ZZ, et un numéro ou une date ne comptent pas.
		'zzG933 Ferme des Prés' => 'ZF',
		'zzGlaçon AMAP 2026-10-10' => 'ZA',
		// Les lettres accentuées restent des lettres.
		'écurie du Val' => 'ÉV',
		// Un nom fait des seuls mots sautés repart de ses mots, un nom sans lettre de ses caractères.
		'Les' => 'LE',
		'2026' => '20',
	];

	public function testInitials(): void {

		foreach(self::INITIALS as $name => $initials) {
			$this->assertEquals($initials, new Structure(['name' => $name])->getInitials(), $name);
		}

	}

	public function testColorIsStable(): void {

		$eStructure = new Structure(['id' => 8, 'name' => 'La Ferme des Cres']);

		$html = StructureUi::getInitials($eStructure);

		$this->assertEquals('<div class="util-icon util-icon-round util-icon-sm util-icon-initials" style="--icon-background: #9dd53a">FC</div>', $html, 'la pastille, ses initiales, et la couleur que la palette donne à l\'identifiant 8');
		$this->assertEquals($html, StructureUi::getInitials(new Structure(['id' => 8, 'name' => 'La Ferme des Cres'])), 'la même structure rendue deux fois garde sa couleur');
		$this->assertEquals('#9dd53a', \user\UserUi::getDefaultColor(8), 'la couleur est celle d\'une vignette d\'utilisateur de même identifiant');
		$this->assertDiffer(\user\UserUi::getDefaultColor(8), \user\UserUi::getDefaultColor(9), 'les identifiants 8 et 9 tombent sur deux couleurs : la palette est bien indexée par l\'identifiant');

	}

}
?>
