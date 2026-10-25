<?php
namespace util;

/**
 * Calculs de couleur des personnalisations : luminance relative et contraste
 * WCAG, encres dérivées d'une couleur choisie.
 *
 * Les seuils, eux, restent chez l'appelant : ce qui est lisible sur un bouton
 * de boutique ne l'est pas forcément dans le corps de texte d'un site.
 */
class ColorUi {

	protected static function parse(string $color): ?array {

		$rgb = sscanf($color, '#%02x%02x%02x');

		if($rgb === NULL or in_array(NULL, $rgb, TRUE)) {
			return NULL;
		}

		return $rgb;

	}

	protected static function getLuminance(array $rgb): float {

		$channel = function(int $value): float {
			$value /= 255;
			return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
		};

		return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);

	}

	protected static function getRatio(array $rgb, float $luminance): float {

		$value = self::getLuminance($rgb);

		return (max($value, $luminance) + 0.05) / (min($value, $luminance) + 0.05);

	}

	/**
	 * Rapport de contraste WCAG entre une couleur et l'encre posée dessus.
	 *
	 * Rend NULL sur une couleur illisible : à l'appelant de décider si un tel cas
	 * passe, la seule chose sûre étant qu'on ne peut pas le mesurer.
	 */
	public static function getContrast(string $color, bool $isTextBlack): ?float {

		$rgb = self::parse($color);

		if($rgb === NULL) {
			return NULL;
		}

		return self::getRatio($rgb, $isTextBlack ? 0.0 : 1.0);

	}

	/**
	 * Rapport de contraste WCAG entre une couleur et une encre quelconque, et non
	 * seulement le noir ou le blanc purs de `getContrast()`.
	 */
	public static function getContrastWith(string $color, string $ink): ?float {

		$rgb = self::parse($color);
		$rgbInk = self::parse($ink);

		if($rgb === NULL or $rgbInk === NULL) {
			return NULL;
		}

		return self::getRatio($rgb, self::getLuminance($rgbInk));

	}

	public static function getTextOnColor(string $color): string {

		$rgb = self::parse($color);

		if($rgb === NULL) {
			return 'white';
		}

		return self::getLuminance($rgb) > 0.4 ? '#1A1A1A' : 'white';

	}

	/**
	 * La couleur, éclaircie ou foncée pas à pas jusqu'à se lire sur le fond.
	 */
	public static function getInkOnBackground(string $color, string $background): string {

		$rgb = self::parse($color);
		$rgbBackground = self::parse($background);

		if($rgb === NULL or $rgbBackground === NULL) {
			return $color;
		}

		$luminanceBackground = self::getLuminance($rgbBackground);

		$toward = self::getRatio([255, 255, 255], $luminanceBackground) > self::getRatio([0, 0, 0], $luminanceBackground) ? 255 : 0;

		$rgbInk = $rgb;

		for($step = 0; $step < 24 and self::getRatio($rgbInk, $luminanceBackground) < 4.5; $step++) {
			$rgbInk = array_map(fn($c) => (int)round($c + ($toward - $c) * 0.12), $rgbInk);
		}

		return sprintf('#%02X%02X%02X', $rgbInk[0], $rgbInk[1], $rgbInk[2]);

	}

	public static function getInkOnLight(string $color): string {

		return self::getInkOnBackground($color, '#FFFFFF');

	}

	/**
	 * L'encre du texte, rapprochée du fond tant qu'elle reste lisible : une
	 * teinte discrète qui tient encore le contraste, plutôt qu'un gris fixe.
	 */
	public static function getMutedOnBackground(string $background, bool $isTextBlack): string {

		$rgbBackground = self::parse($background);

		if($rgbBackground === NULL) {
			return $isTextBlack ? '#6A6A6A' : '#CCCCCC';
		}

		$luminanceBackground = self::getLuminance($rgbBackground);

		$rgbMuted = $isTextBlack ? [33, 37, 41] : [255, 255, 255];

		if(self::getRatio($rgbMuted, $luminanceBackground) < 4.5) {
			return $isTextBlack ? '#6A6A6A' : '#CCCCCC';
		}

		for($step = 0; $step < 24; $step++) {

			$rgbNext = [];

			foreach($rgbMuted as $index => $value) {
				$rgbNext[$index] = (int)round($value + ($rgbBackground[$index] - $value) * 0.08);
			}

			if(self::getRatio($rgbNext, $luminanceBackground) < 4.5) {
				break;
			}

			$rgbMuted = $rgbNext;

		}

		return sprintf('#%02X%02X%02X', $rgbMuted[0], $rgbMuted[1], $rgbMuted[2]);

	}

}
?>
