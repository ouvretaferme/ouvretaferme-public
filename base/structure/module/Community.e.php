<?php
namespace structure;

class Community extends CommunityElement {

	const INK_DARK = '#1A1A1A';
	const INK_LIGHT = '#FFFFFF';
	const INK_MUTED = '#6B6B70';
	const INK_MUTED_DARK = '#56565B';
	const INK_MUTED_DARKER = '#4A4A4F';
	const INK_MUTED_LIGHT = '#8A8A90';

	public function canRead(): bool {
		return TRUE;
	}

	public static function properties(\PropertyList $l): void {

		$l->property('fqn')
			->addAfterCast('lowercase', function(?string &$fqn, \PropertyList $l) {

				if($l->for === 'update') {
					$this['oldFqn'] = $this['fqn'];
				}

				// La colonne est en ascii_bin : sans cette normalisation, deux adresses qui ne
				// diffèrent que par la casse cohabitent en base et se confondent dans une URL.
				if($fqn !== NULL) {
					$fqn = strtolower($fqn);
				}

			})
			// Le filtre du type fqn laisse passer les points et un tiret en tête.
			->addAfterCheck('format', fn(string $fqn): bool => isFqn($fqn))
			->addAfterCheck('reserved', fn(string $fqn): bool => in_array($fqn, RESERVED_FQN) === FALSE)
			->addAfterCheck('duplicate', fn(string $fqn): bool => CommunityLib::isFqnTaken($fqn, $this['structure']) === FALSE);

		// La couleur déjà enregistrée passe, comme `Shop::customColor.contrast`, pour ne pas
		// bloquer l'enregistrement du reste du paramétrage.
		$l->property('color')
			->addAfterCheck('contrast', function(?string $color): bool {

				if(
					$color === NULL or
					strcasecmp($color, $this['color'] ?? '') === 0
				) {
					return TRUE;
				}

				return self::isReadableUnderLight($color);

			});

		// Le type color admet les majuscules, la palette est écrite en minuscules.
		$l->property('customBackground')
			->addAfterCast('lowercase', function(?string &$background) {
				if($background !== NULL) {
					$background = strtolower($background);
				}
			})
			->replaceCheck(fn(?string $background): bool => ($background === NULL or \main\CustomizeUi::isCustomBackground($background)));

		$l->property('customTitleFont')
			->replaceCheck(fn(?string $font): bool => ($font === NULL or \main\CustomizeUi::isCustomFont($font, \main\CustomizeUi::CUSTOM_TITLE_FONTS)));

		$l->property('customFont')
			->replaceCheck(fn(?string $font): bool => ($font === NULL or \main\CustomizeUi::isCustomFont($font, \main\CustomizeUi::CUSTOM_FONTS)));

		$l->property('customDesign')
			->replaceCheck(fn(?string $design): bool => ($design === NULL or in_array($design, \learning\LearningSetting::DESIGNS, TRUE)));

		// Les deux facteurs du filtre par ratio s'allument et s'éteignent ensemble. La case
		// de l'écran le dit, mais l'API n'a pas de case : la règle se tient ici, où le web
		// et l'API passent tous les deux.
		$l->property('quotationRatioHigh')
			->addAfterCheck('paired', function(?float $ratio, \PropertyList $l): bool {

				return (($ratio === NULL) === self::isRatioEmpty($this, $l, 'quotationRatioLow'));

			});

		$l->property('quotationRatioLow')
			->addAfterCheck('paired', function(?float $ratio, \PropertyList $l): bool {

				// Les deux facteurs construits ensemble, l'étape de quotationRatioHigh a déjà jugé
				// la paire dans les deux sens : la rejouer ici lèverait deux fois le même refus.
				// Ne reste que l'entrée partielle, qui n'atteint pas l'autre étape.
				if($l->hasProperty('quotationRatioHigh')) {
					return TRUE;
				}

				return (($ratio === NULL) === ($this['quotationRatioHigh'] === NULL));

			});

		$l->property('quotationRegion')
			->replaceCheck(fn(?string $region): bool => ($region === NULL or StructureLib::acceptQuotationRegion($region)));

	}

	/**
	 * L'encre qui se lit le mieux sur la couleur du groupe, noire ou blanche.
	 *
	 * Le seuil de `ColorUi::getTextOnColor()` n'est pas repris : il sert aussi les sites, et
	 * choisit le blanc jusqu'à une luminance de 0,4, là où le noir se lit mieux dès 0,202.
	 */
	public static function getInk(string $color): string {

		return (\util\ColorUi::getContrastWith($color, self::INK_DARK) > \util\ColorUi::getContrastWith($color, self::INK_LIGHT)) ? self::INK_DARK : self::INK_LIGHT;

	}

	/**
	 * La couleur d'un groupe porte-t-elle le blanc à 4,5:1 ?
	 *
	 * La bande des écrans du groupe écrit en blanc sur la couleur, sa pastille et son bouton plein,
	 * et écrit la couleur sur du blanc, ses liens, son cachet et son bouton à contour. Le contraste
	 * étant symétrique, un seul calcul tient les deux. Le bandeau, lui, choisit son encre
	 * (`getInk()`) et se lirait sur une couleur claire : c'est la bande qui fixe le seuil.
	 */
	public static function isReadableUnderLight(string $color): bool {

		return self::isReadableOn(self::INK_LIGHT, [$color]);

	}

	/**
	 * Le gris des annotations doit tenir 4,5:1 sur chaque couleur où il se pose (le fond, et les
	 * surfaces qu'un gabarit fixe), et sur tout mélange sRGB de ces couleurs, qu'une feuille de
	 * gabarit déclare par ses bouts : c'est le texte le plus clair des pages de formations, donc
	 * celui qui cède le premier. Faute de gris qui tienne partout, l'encre pleine prend le relais.
	 */
	public static function getMutedInk(string ...$backgrounds): string {

		$inks = (self::getInk($backgrounds[0]) === self::INK_LIGHT) ?
			[self::INK_MUTED_LIGHT, self::INK_LIGHT] :
			[self::INK_MUTED, self::INK_MUTED_DARK, self::INK_MUTED_DARKER, self::INK_DARK];

		foreach($inks as $ink) {

			if(self::isReadableOnMixes($ink, $backgrounds)) {
				return $ink;
			}

		}

		return self::getInk($backgrounds[0]);

	}

	/**
	 * Un mélange sRGB peut être plus sombre que ses deux bouts (`#ff0000` et `#00ff00` donnent
	 * `#808000`, plus sombre que l'un et l'autre). Il reste en revanche, canal par canal, entre les
	 * bouts, et la luminance croît avec chaque canal : elle est donc bornée par celles des deux
	 * coins qui prennent, canal par canal, le minimum et le maximum des fonds. Une encre plus sombre
	 * ou plus claire que les deux coins, et lisible sur chacun, se lit sur tout mélange.
	 */
	private static function isReadableOnMixes(string $ink, array $backgrounds): bool {

		$channels = array_map(fn(string $background): array => sscanf(ltrim($background, '#'), '%2x%2x%2x'), $backgrounds);

		$darkest = vsprintf('#%02x%02x%02x', array_map(fn(int ...$values): int => min($values), ...$channels));
		$lightest = vsprintf('#%02x%02x%02x', array_map(fn(int ...$values): int => max($values), ...$channels));

		// Le contraste avec le noir, (L + 0,05) / 0,05, range les couleurs comme leur luminance.
		$inkLuminance = \util\ColorUi::getContrastWith($ink, '#000000');

		return (
			self::isReadableOn($ink, [$darkest, $lightest]) and
			(
				$inkLuminance < \util\ColorUi::getContrastWith($darkest, '#000000') or
				$inkLuminance > \util\ColorUi::getContrastWith($lightest, '#000000')
			)
		);

	}

	/**
	 * La couleur du groupe quand elle sert de texte, sur chaque couleur où elle se pose : le fond,
	 * et le blanc des cartes ou les surfaces que fixe un gabarit.
	 *
	 * Le repli est l'encre sombre et non `getInk()`, qui rend du blanc pour une couleur foncée :
	 * posé sur un fond clair, ce blanc ne se lirait pas.
	 */
	public static function getTextInk(string $color, string ...$backgrounds): string {

		foreach([$color, self::INK_DARK, self::INK_LIGHT] as $ink) {

			if(self::isReadableOn($ink, $backgrounds)) {
				return $ink;
			}

		}

		return self::INK_DARK;

	}

	private static function isReadableOn(string $ink, array $backgrounds): bool {

		foreach($backgrounds as $background) {

			if((\util\ColorUi::getContrastWith($background, $ink) ?? 0.0) < 4.5) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Le facteur opposé est-il vide, dans cette entrée ou sur l'élément ?
	 *
	 * Lu dans l'entrée dès que l'opposé est du build : build() construit les propriétés
	 * en série, et l'opposé n'est pas encore posé quand la chaîne de sa jumelle s'achève
	 * — sur l'élément, un couple complet posté sur un groupe qui n'en portait aucun
	 * serait refusé, et le filtre ne pourrait jamais s'allumer. Une chaîne vide y vaut
	 * absent : le cast n'a pas tourné sur cette clé-là.
	 */
	private static function isRatioEmpty(Community $eCommunity, \PropertyList $l, string $property): bool {

		if($l->hasProperty($property) === FALSE) {
			return ($eCommunity[$property] === NULL);
		}

		return (($l->input[$property] ?? '') === '');

	}

}
?>
