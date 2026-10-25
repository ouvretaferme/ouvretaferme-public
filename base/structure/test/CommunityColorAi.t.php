<?php
namespace structure;

/**
 * La couleur d'un groupe et l'encre qu'on pose dessus.
 *
 * La bande des écrans du groupe écrit en blanc sur la couleur (pastille, bouton plein) et écrit
 * la couleur sur du blanc (liens, cachet, bouton à contour). Le contraste étant symétrique, une
 * couleur s'enregistre si le blanc y tient 4,5:1, et ce seuil se juge sur des valeurs : contraste
 * du blanc sur chaque couleur du tableau, (1,05) / (L + 0,05).
 *
 * Le bandeau, lui, choisit son encre, la meilleure des deux : elle ne compte plus qu'à l'affichage
 * d'une couleur enregistrée avant le seuil.
 *
 * Lancement : dev/test package=structure file=CommunityColorAi
 */
class CommunityColorAiTest extends \Test {

	/**
	 * Couleur, contraste du blanc arrondi, et si elle s'enregistre.
	 */
	const COLORS = [
		'#FFFFFF' => [1.0, FALSE],
		'#FFF3B0' => [1.12, FALSE],
		'#F5D90A' => [1.42, FALSE],
		'#4CAF50' => [2.78, FALSE],
		'#2196F3' => [3.12, FALSE],
		'#7A7A7A' => [4.29, FALSE],
		// Les deux gris qui encadrent le seuil.
		'#767676' => [4.54, TRUE],
		'#757575' => [4.61, TRUE],
		'#1B4B53' => [9.64, TRUE],
		'#474007' => [10.48, TRUE],
	];

	/**
	 * Couleur et encre du bandeau, la plus lisible des deux.
	 */
	const INKS = [
		'#FFF3B0' => Community::INK_DARK,
		'#F5D90A' => Community::INK_DARK,
		'#4CAF50' => Community::INK_DARK,
		'#E53935' => Community::INK_LIGHT,
		'#1B4B53' => Community::INK_LIGHT,
	];

	public function testContrastOfLight(): void {

		foreach(self::COLORS as $color => [$contrast, ]) {
			$this->assertEquals($contrast, round(\util\ColorUi::getContrastWith($color, Community::INK_LIGHT), 2), 'blanc sur '.$color);
		}

	}

	public function testInkIsTheMostReadable(): void {

		foreach(self::INKS as $color => $ink) {
			$this->assertEquals($ink, Community::getInk($color), 'encre sur '.$color);
		}

	}

	public function testUnreadableColorIsRefused(): void {

		foreach(self::COLORS as $color => [, $accepted]) {

			$fw = new \FailWatch();

			new Community()->build(['color'], ['color' => $color]);

			$this->assertEquals($accepted === FALSE, $fw->has('Community::color.contrast'), $color.($accepted ? ' s\'enregistre' : ' est refusée'));

		}

	}

	/**
	 * La couleur déjà enregistrée passe : sans quoi un groupe qui la portait avant la règle ne
	 * pourrait plus rien enregistrer d'autre sur le même formulaire.
	 */
	public function testStoredColorIsKept(): void {

		$fw = new \FailWatch();

		new Community(['color' => '#FFF3B0'])->build(['color'], ['color' => '#fff3b0']);

		$this->assertFalse($fw->has('Community::color.contrast'), 'la couleur enregistrée n\'est pas refusée de nouveau');

	}

}
?>
