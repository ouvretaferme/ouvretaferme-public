<?php
namespace structure;

/**
 * Le fond, les polices et la couleur de texte des pages de formations d'un réseau.
 *
 * Le fond est pris dans la palette du socle, `\main\CustomizeUi::CUSTOM_BACKGROUNDS`, la même que celle
 * de la boutique : toute autre couleur est refusée, même claire. Le gris des annotations
 * (`INK_MUTED`, #6b6b70) ne tient 4,5:1 que sur 5 des 22 teintes (3,28:1 au pire, sur #d1c6ec) :
 * sur les autres, les pages le remplacent par `INK_MUTED_DARK`, et `testMutedInkHoldsOnEveryBackground`
 * impose la lisibilité sur toute la palette, y compris sur une teinte qu'on y ajouterait.
 * Les polices sont prises dans les listes fermées de la boutique et du site. La couleur sert de
 * texte quand elle tient 4,5:1 sur le fond et sur le blanc des cartes, sinon le texte la prend
 * foncée jusqu'à les tenir.
 *
 * Contrastes calculés par `ColorUi::getContrastWith()` : #2f6b4f tient 5,54:1 sur #f5f0e6 et 6,29:1
 * sur le blanc ; #f2c14e tient 1,48:1 et 1,68:1.
 *
 * Lancement : dev/test package=structure file=CommunityCustomizeAi
 */
class CommunityCustomizeAiTest extends \Test {

	/**
	 * Hors palette, et pourquoi chacune : claire mais absente, sombre, et le défaut de la boutique,
	 * qui n'est pas une teinte de la palette.
	 */
	const OUTSIDE = ['#f5f0e6', '#c9c9c9', '#000000', '#f0f2f4'];

	public function testBackgroundOutsideThePaletteIsRefused(): void {

		foreach(self::OUTSIDE as $background) {

			$fw = new \FailWatch();

			new Community()->build(['customBackground'], ['customBackground' => $background]);

			$this->assertTrue($fw->has('Community::customBackground.check'), $background.' est refusé');

		}

	}

	public function testEveryBackgroundOfThePaletteIsAccepted(): void {

		$count = 0;

		foreach(\main\CustomizeUi::CUSTOM_BACKGROUNDS as $name => $backgrounds) {

			foreach($backgrounds as $background) {

				$fw = new \FailWatch();

				$eCommunity = new Community();
				$eCommunity->build(['customBackground'], ['customBackground' => $background]);

				$this->assertTrue($fw->ok(), $name.' '.$background.' s\'enregistre');
				$this->assertEquals($background, $eCommunity['customBackground']);

				$count++;

			}

		}

		$this->assertEquals(22, $count, 'onze teintes en deux intensités');

	}

	public function testUppercaseBackgroundIsStoredLowercase(): void {

		$fw = new \FailWatch();

		$eCommunity = new Community();
		$eCommunity->build(['customBackground'], ['customBackground' => '#F3EDE4']);

		$this->assertTrue($fw->ok(), 'la palette se reconnaît quelle que soit la casse');
		$this->assertEquals('#f3ede4', $eCommunity['customBackground']);

	}

	/**
	 * L'invariant qui remplace l'ancien refus : sur chaque teinte, le gris retenu tient 4,5:1.
	 */
	public function testMutedInkHoldsOnEveryBackground(): void {

		$darkened = [];

		foreach(\main\CustomizeUi::CUSTOM_BACKGROUNDS as $backgrounds) {

			foreach($backgrounds as $background) {

				$ink = Community::getMutedInk($background);
				$contrast = \util\ColorUi::getContrastWith($background, $ink);

				$this->assertTrue($contrast >= 4.5, $ink.' sur '.$background.' : '.round($contrast, 2).':1');

				if($ink === Community::INK_MUTED_DARK) {
					$darkened[] = $background;
				}

			}

		}

		$this->assertEquals(17, count($darkened), 'le gris foncé sert là où #6b6b70 ne tient pas : '.implode(', ', $darkened));
		$this->assertEquals(Community::INK_MUTED, Community::getMutedInk('#ffffff'), 'le gris d\'origine reste sur le blanc');

	}

	public function testEmptyBackgroundIsAccepted(): void {

		$fw = new \FailWatch();

		$eCommunity = new Community();
		$eCommunity->build(['customBackground'], ['customBackground' => '']);

		$this->assertTrue($fw->ok(), 'un fond vide rend celui de Ouvretaferme');
		$this->assertEquals(NULL, $eCommunity['customBackground']);

	}

	public function testFontOutsideTheListIsRefused(): void {

		foreach([
			'customTitleFont' => \main\CustomizeUi::CUSTOM_TITLE_FONTS,
			'customFont' => \main\CustomizeUi::CUSTOM_FONTS
		] as $property => $fonts) {

			$fw = new \FailWatch();

			new Community()->build([$property], [$property => "'Comic Sans MS', cursive"]);

			$this->assertTrue($fw->has('Community::'.$property.'.check'), $property.' hors liste refusée');

			$fw = new \FailWatch();

			$eCommunity = new Community();
			$eCommunity->build([$property], [$property => $fonts[0]['value']]);

			$this->assertTrue($fw->ok(), $property.' de la liste admise');
			$this->assertEquals($fonts[0]['value'], $eCommunity[$property]);

			$fw = new \FailWatch();

			$eCommunity = new Community();
			$eCommunity->build([$property], [$property => '']);

			$this->assertTrue($fw->ok(), $property.' vide admise');
			$this->assertEquals(NULL, $eCommunity[$property]);

		}

	}

	/**
	 * Une police de titre n'est pas une police de texte : les deux listes ne se mêlent pas.
	 */
	public function testFontListsAreNotMixed(): void {

		$fw = new \FailWatch();

		new Community()->build(['customFont'], ['customFont' => "'Lobster', cursive"]);

		$this->assertTrue($fw->has('Community::customFont.check'), 'une police de titre refusée pour le texte');

	}

	public function testTextInk(): void {

		// Le blanc des cartes se passe comme un fond : la matière d'une portée peut ne pas le contenir.
		$this->assertEquals('#2F6B4F', Community::getTextInk('#2F6B4F', '#F5F0E6', Community::INK_LIGHT), '#2f6b4f se lit sur #f5f0e6 et sur le blanc');
		$this->assertEquals(Community::INK_DARK, Community::getTextInk('#F2C14E', '#F5F0E6', Community::INK_LIGHT), '#f2c14e retombe sur l\'encre sombre');
		$this->assertEquals(Community::INK_DARK, Community::getTextInk('#F2C14E', Community::INK_LIGHT), 'sur le blanc aussi');

		// Lisible sur un fond sombre mais pas sur le blanc des cartes : le blanc décide.
		$this->assertEquals(Community::INK_DARK, Community::getTextInk('#E0E0E0', '#202020', Community::INK_LIGHT), 'le blanc des cartes compte autant que le fond');

		$this->assertEquals(Community::INK_DARK, Community::getTextInk('rouge', Community::INK_LIGHT), 'une couleur illisible rend l\'encre sombre');

	}

	public function testDesign(): void {

		foreach(\learning\LearningSetting::DESIGNS as $design) {

			$fw = new \FailWatch();

			$eCommunity = new Community();
			$eCommunity->build(['customDesign'], ['customDesign' => $design]);

			$this->assertTrue($fw->ok(), $design.' admis');
			$this->assertEquals($design, $eCommunity['customDesign']);

		}

		$fw = new \FailWatch();

		new Community()->build(['customDesign'], ['customDesign' => 'inconnu']);

		$this->assertTrue($fw->has('Community::customDesign.check'), 'un gabarit hors de la liste est refusé');

		$fw = new \FailWatch();

		$eCommunity = new Community();
		$eCommunity->build(['customDesign'], ['customDesign' => '']);

		$this->assertTrue($fw->ok(), 'le gabarit classique admis');
		$this->assertEquals(NULL, $eCommunity['customDesign'], 'le gabarit classique vaut NULL');

	}

	/**
	 * Les encres d'un gabarit se calculent sur les couleurs qu'il fixe sous le texte, et chacune
	 * tient 4,5:1 sur toutes (plan A3a, table du §6).
	 */
	public function testDesignInks(): void {

		$green = '#2f6b4f';
		$yellow = '#f2c14e';

		// Aucun gabarit n'a de fond à lui : la couleur se lit sur le fond
		// choisi et le blanc des cartes comme sans gabarit, et un jaune qui ne s'y lit pas y est
		// foncé, sans céder à l'encre sombre.
		foreach(['futur', 'kraft'] as $design) {

			$inks = self::getInks($design, $green, NULL);

			foreach(['public', 'doc'] as $scope) {
				$this->assertEquals($green, $inks[$scope]['textInk'], $design.' '.$scope.', le vert se lit sur le fond et le blanc');
				$this->assertEquals(Community::INK_MUTED, $inks[$scope]['mutedInk'], $design.' '.$scope.', le gris d\'origine');
			}

			$this->assertEquals('#80662A', self::getInks($design, $yellow, NULL)['public']['textInk'], $design.', le jaune foncé jusqu\'à se lire sur un fond clair');

		}

		foreach(\learning\LearningSetting::DESIGN_MATTERS as $design => $scopes) {

			foreach($scopes as $scope => $matters) {

				foreach([$green, $yellow, NULL] as $color) {

					$inks = self::getInks($design, $color, NULL)[$scope];

					foreach($matters as $matter) {

						$matter = ($matter === '{background}') ? \learning\CommunityLib::BACKGROUND_DEFAULT : $matter;

						foreach(['mutedInk', 'textInk'] as $ink) {
							$contrast = \util\ColorUi::getContrastWith($matter, $inks[$ink]);
							$this->assertTrue($contrast >= 4.5, $design.' '.$scope.' '.($color ?? 'sans couleur').' : '.$ink.' '.$inks[$ink].' sur '.$matter.', '.round($contrast, 2).':1');
						}

					}

				}

			}

		}

	}

	/**
	 * Une couleur claire, posée en texte, garde la teinte du réseau : foncée jusqu'à 4,5:1 sur le
	 * fond adouci et sur le blanc des cartes, pour chaque teinte de la palette et sous chaque
	 * gabarit, sans retomber sur l'encre sombre. Le jaune pâle est la couleur que le blanc seul
	 * (`ColorUi::getInkOnLight()`) laisse sous le seuil sur onze fonds adoucis sur douze.
	 */
	public function testTextInkDarkenedOnEveryBackground(): void {

		$backgrounds = array_merge([NULL], ...array_values(\main\CustomizeUi::CUSTOM_BACKGROUNDS));
		$low = [];

		foreach(['#f2c230', '#f2c14e', '#fff3b0'] as $color) {

			foreach($backgrounds as $background) {

				$softened = ($background === NULL) ? \learning\CommunityLib::BACKGROUND_DEFAULT : \learning\CommunityLib::getBackgroundSoftened($background);

				$cases = ['classique' => [\learning\CommunityLib::getSettings(self::getCommunity(NULL, $color, $background))['textInk'], [$softened, Community::INK_LIGHT]]];

				foreach(\learning\LearningSetting::DESIGNS as $design) {

					$inks = self::getInks($design, $color, $background);

					foreach(['public', 'doc'] as $scope) {

						$matters = \learning\LearningSetting::DESIGN_MATTERS[$design][$scope] ?? ['{background}', Community::INK_LIGHT];
						$matters = array_map(fn(string $matter) => ($matter === '{background}') ? $softened : $matter, $matters);

						$cases[$design.' '.$scope] = [$inks[$scope]['textInk'], $matters];

					}

				}

				foreach($cases as $label => [$ink, $matters]) {

					if($ink === Community::INK_DARK) {
						$low[] = $color.' sur '.($background ?? 'défaut').' '.$label.' : encre sombre';
					}

					foreach($matters as $matter) {

						$contrast = \util\ColorUi::getContrastWith($matter, $ink);

						if($contrast < 4.5) {
							$low[] = $color.' sur '.($background ?? 'défaut').' '.$label.' : '.$ink.' sur '.$matter.', '.round($contrast, 2).':1';
						}

					}

				}

			}

		}

		$this->assertEquals([], $low, 'une couleur claire foncée qui ne tient pas 4,5:1, ou qui cède à l\'encre sombre');

	}

	/**
	 * Une couleur sans teinte, blanc ou gris, n'est pas foncée : elle donnerait un gris moyen qui ne
	 * rappelle plus le réseau, et le texte garde l'encre sombre. Le seuil est
	 * `CommunityLib::TEXT_INK_SATURATION` (0,3) : #e6c8a8 (0,27) reste à l'encre sombre, #e6c4a0
	 * (0,30) est foncé.
	 */
	public function testTextInkWithoutHue(): void {

		foreach([NULL, '#d1c6ec'] as $background) {

			$label = ' sur '.($background ?? 'le fond par défaut');

			foreach(['#ffffff' => 'le blanc', '#c8c8c8' => 'un gris', '#e6c8a8' => 'un beige juste sous le seuil'] as $color => $name) {

				$this->assertEquals(Community::INK_DARK, \learning\CommunityLib::getSettings(self::getCommunity(NULL, $color, $background))['textInk'], $name.' garde l\'encre sombre'.$label);
				$this->assertEquals(Community::INK_DARK, self::getInks('futur', $color, $background)['public']['textInk'], $name.' garde l\'encre sombre sous un gabarit'.$label);

			}

			$settings = \learning\CommunityLib::getSettings(self::getCommunity(NULL, '#e6c4a0', $background));

			$this->assertTrue($settings['textInk'] !== Community::INK_DARK, 'un beige juste au-dessus du seuil est foncé'.$label);

			foreach([$settings['backgroundSoftened'], Community::INK_LIGHT] as $matter) {
				$this->assertTrue(\util\ColorUi::getContrastWith($matter, $settings['textInk']) >= 4.5, 'et se lit sur '.$matter.$label);
			}

		}

	}

	/**
	 * Sans couleur ni fond, un gabarit pose quand même ses encres, calculées sur la couleur du produit.
	 */
	public function testDesignWithoutColorNorBackground(): void {

		foreach(['kraft', 'futur'] as $design) {

			$eCommunity = self::getCommunity($design, NULL, NULL);
			$settings = \learning\CommunityLib::getSettings($eCommunity);

			$this->assertEquals(\learning\CommunityLib::BACKGROUND_DEFAULT, $settings['background'], $design.', le fond par défaut');

			foreach(['public', 'doc'] as $scope) {

				$expected = self::getInks($design, \learning\CommunityLib::COLOR_DEFAULT, NULL)[$scope];

				$this->assertEquals($expected, $settings['inks'][$scope], $design.' '.$scope.', les encres de la couleur du produit');

			}

		}

		$this->assertEquals(\learning\CommunityLib::COLOR_DEFAULT, self::getInks('futur', NULL, NULL)['public']['textInk'], 'futur sans couleur, la couleur du produit se lit');
		$this->assertEquals(\learning\CommunityLib::COLOR_DEFAULT, self::getInks('kraft', NULL, NULL)['public']['textInk'], 'kraft sans couleur, la couleur du produit se lit');

		$this->assertEquals([], \learning\CommunityLib::getSettings(self::getCommunity(NULL, '#2f6b4f', NULL))['inks'], 'le gabarit classique ne calcule rien de plus');

	}

	/**
	 * Une matière mêlée : le gris retenu tient aux deux bouts.
	 */
	public function testMixedMatter(): void {

		$ink = Community::getMutedInk('#d6bf98', '#fbf5e6');

		$this->assertEquals(Community::INK_MUTED_DARKER, $ink);
		$this->assertTrue(\util\ColorUi::getContrastWith('#d6bf98', $ink) >= 4.5);
		$this->assertTrue(\util\ColorUi::getContrastWith('#fbf5e6', $ink) >= 4.5);

		$this->assertEquals(Community::INK_MUTED, Community::getMutedInk('#fbf5e6'), 'le papier seul garde le gris d\'origine');

	}

	/**
	 * Un mélange sRGB peut être plus sombre que ses deux bouts : à 50 %, `#f9c725` et `#42ef02`
	 * donnent `#9ddb13`, sur lequel le gris qui tient aux deux bouts descend sous 4,5:1.
	 */
	public function testMutedInkOnMix(): void {

		$ink = Community::getMutedInk('#f9c725', '#42ef02');

		$this->assertTrue(\util\ColorUi::getContrastWith('#9ddb13', $ink) >= 4.5, 'le gris tient sur le mélange des deux fonds, '.$ink);
		$this->assertTrue(\util\ColorUi::getContrastWith('#f9c725', $ink) >= 4.5, 'et sur le premier bout');
		$this->assertTrue(\util\ColorUi::getContrastWith('#42ef02', $ink) >= 4.5, 'et sur le second');

	}

	/**
	 * Un fond sombre en tête n'autorise pas le gris clair sans le juger sur les autres fonds.
	 */
	public function testMutedInkOnDarkThenMid(): void {

		$ink = Community::getMutedInk('#0c1018', '#3a3a3a');

		$this->assertTrue(\util\ColorUi::getContrastWith('#0c1018', $ink) >= 4.5, 'le gris tient sur le fond sombre, '.$ink);
		$this->assertTrue(\util\ColorUi::getContrastWith('#3a3a3a', $ink) >= 4.5, 'et sur la surface plus claire, '.$ink);

	}

	private static function getInks(string $design, ?string $color, ?string $background): array {
		return \learning\CommunityLib::getSettings(self::getCommunity($design, $color, $background))['inks'];
	}

	private static function getCommunity(?string $design, ?string $color, ?string $background): Community {

		return new Community([
			'customDesign' => $design,
			'color' => $color,
			'customBackground' => $background,
			'customTitleFont' => NULL,
			'customFont' => NULL
		]);

	}

}
?>
