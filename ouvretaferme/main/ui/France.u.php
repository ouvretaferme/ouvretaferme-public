<?php
namespace main;

/**
 * Le fond de carte de la France, partagé.
 *
 * Le tracé et les centres sont de la géographie, pas de la donnée d'un écran : les
 * statistiques de l'administration et les relevés de prix montrent deux choses
 * différentes sur exactement le même dessin. Dupliqué, l'un des deux suivrait le
 * prochain millésime IGN et l'autre non.
 *
 * Dans `main`, à côté de `ChartUi` qui sert les anneaux des deux écrans, et non
 * dedans : une table de 96 centroïdes et la lecture d'un asset ne construisent
 * aucun graphique.
 *
 * Ce qui reste chez l'appelant, c'est la couleur : une rampe à un seul sens pour un
 * effectif, une échelle à deux pôles pour un écart à une référence. La géométrie ne
 * sait rien de ce qu'on peint dessus.
 */
class FranceUi {

	/**
	 * Le tracé, lu une fois par requête. Un asset et non une donnée : il ne change qu'avec le
	 * millésime de l'IGN, il n'a rien à faire en base ni dans un Lib.
	 */
	public static function getSvg(): ?string {

		static $svg;

		if($svg === NULL) {

			$path = \Package::getPath('main').'/static/image/france.svg';
			$svg = file_exists($path) ? file_get_contents($path) : '';

		}

		return ($svg === '') ? NULL : $svg;

	}

	/**
	 * Centre de chaque région, déduit de ses départements : moyenne pondérée par leur surface, sinon
	 * une région à un gros département et six petits verrait son étiquette dériver vers les petits.
	 * Le découpage est celui de l'appelant : la mercuriale tient le sien, qui ne suit pas
	 * l'administratif.
	 *
	 * @param array<string, string[]> $regions code région => codes des départements
	 * @return array<string, array{0: float, 1: float}> code région => [x, y]
	 */
	public static function getRegionCentres(array $regions): array {

		$centres = self::getCentres();
		$regionCentres = [];

		foreach($regions as $code => $departments) {

			[$x, $y, $weight] = [0, 0, 0];

			foreach($departments as $department) {

				if(array_key_exists($department, $centres) === FALSE) {
					continue;
				}

				[$cx, $cy, $radius] = $centres[$department];
				$weight += $radius ** 2;
				$x += $cx * $radius ** 2;
				$y += $cy * $radius ** 2;

			}

			if($weight > 0) {
				$regionCentres[$code] = [$x / $weight, $y / $weight];
			}

		}

		return $regionCentres;

	}

	/**
	 * La carte, peinte par l'appelant.
	 *
	 * Ce qui est ici est de la mécanique : poser une couleur par département, poser un calque
	 * d'étiquettes par-dessus le tracé, envelopper le tout. Ce qui reste à l'appelant est l'échelle,
	 * c'est-à-dire le sens : une rampe à un seul sens pour un effectif, deux pôles pour un écart à une
	 * référence, et la légende qui le dit.
	 *
	 * Le remplissage passe par une feuille en ligne et non par un attribut sur chaque `path` : le
	 * tracé est lu tel quel dans l'asset, personne ne le réécrit.
	 *
	 * @param array<string, string> $fills code département => couleur CSS
	 * @param array $labels chacune ['x', 'y', 'value', 'name' => ?, 'url' => ?, 'class' => ?]
	 * @param array $options dont `links`, code département => adresse : le département entier mène à
	 *   l'adresse. Une étiquette ne reçoit pas le clic (`.util-map-labels`, pour ne pas masquer le tracé
	 *   qu'elle couvre), c'est donc le tracé qui porte le lien d'une donnée qui se clique. Et `titles`,
	 *   code département => texte, qui remplace le nom que le tracé donne au survol.
	 */
	public static function getMap(array $fills, array $labels, string $legend = '', array $options = []): ?string {

		$svg = self::getSvg();

		if($svg === NULL) {
			return NULL;
		}

		$centres = self::getCentres();

		// Le tracé porte un id par département, et deux cartes sur la même page les partageraient :
		// la seconde feuille en ligne repeindrait la première. Chaque carte borne donc ses règles par
		// l'id de son conteneur. Mesuré sur la page du design system, qui en montre deux.
		static $count = 0;
		$id = 'util-map-'.(++$count);

		$style = '';

		foreach($fills as $department => $fill) {

			// ⚠️ Seuls les codes du tracé : une distribution porte aussi des codes postaux mal saisis
			// (« [N », « J0 »), et `#fr-[N{…}` ouvre un sélecteur d'attribut que rien ne ferme — le
			// parseur CSS avale alors toute la fin de la feuille, donc tous les départements écrits
			// après lui. Mesuré le 29/08/2026.
			if(array_key_exists($department, $centres) === FALSE) {
				continue;
			}

			$style .= '#'.$id.' #fr-'.$department.'{fill:'.$fill.'}';

		}

		$text = '<g class="util-map-labels">';

			foreach($labels as $label) {

				$text .= '<g class="util-map-label '.($label['class'] ?? '').'" transform="translate('.round($label['x']).' '.round($label['y']).')'.(array_key_exists('labelScale', $options) ? ' scale('.$options['labelScale'].')' : '').'">';

					if(array_key_exists('url', $label)) {
						$text .= '<a href="'.$label['url'].'">';
					}

					if(($label['name'] ?? NULL) !== NULL) {
						$text .= '<text class="util-map-name" y="'.(array_key_exists('value', $label) ? -8 : 6).'">'.encode($label['name']).'</text>';
					}

					if(array_key_exists('value', $label)) {
						$text .= '<text class="util-map-value" y="'.(($label['name'] ?? NULL) !== NULL ? 26 : 7).'">'.encode($label['value']).'</text>';
					}

					if(array_key_exists('url', $label)) {
						$text .= '</a>';
					}

				$text .= '</g>';

			}

		$text .= '</g>';

		if(array_key_exists('links', $options) or array_key_exists('titles', $options)) {

			$svg = preg_replace_callback('/<path id="fr-([0-9AB]+)"(.*?)<title>(.*?)<\/title><\/path>/s', function(array $match) use($options): string {

				[$path, $department, $attributes] = $match;

				if(array_key_exists($department, $options['titles'] ?? [])) {
					$path = '<path id="fr-'.$department.'"'.$attributes.'<title>'.encode($options['titles'][$department]).'</title></path>';
				}

				if(array_key_exists($department, $options['links'] ?? [])) {
					$path = '<a href="'.encode($options['links'][$department]).'">'.$path.'</a>';
				}

				return $path;

			}, $svg);

		}

		if(array_key_exists('viewBox', $options)) {
			$svg = preg_replace('/viewBox="[^"]*"/', 'viewBox="'.implode(' ', $options['viewBox']).'"', $svg, 1);
		}

		$h = '<div id="'.$id.'" class="util-map '.(($options['region'] ?? FALSE) ? 'util-map-region ' : '').($options['class'] ?? '').'">';
			$h .= '<style>'.$style.'</style>';
			$h .= str_replace('</svg>', $text.'</svg>', $svg);
			$h .= $legend;
		$h .= '</div>';

		return $h;

	}

	public static function getViewBox(array $departments, float $padding): array {

		$centres = self::getCentres();

		$left = $top = INF;
		$right = $bottom = -INF;

		foreach($departments as $department) {

			if(array_key_exists((string)$department, $centres) === FALSE) {
				continue;
			}

			[$x, $y, $radius] = $centres[(string)$department];

			$left = min($left, $x - $radius);
			$top = min($top, $y - $radius);
			$right = max($right, $x + $radius);
			$bottom = max($bottom, $y + $radius);

		}

		if($left === INF) {
			return [];
		}

		$margin = max($right - $left, $bottom - $top) * $padding;

		return [
			(int)round($left - $margin),
			(int)round($top - $margin),
			(int)round($right - $left + 2 * $margin),
			(int)round($bottom - $top + 2 * $margin),
		];

	}

	// Le millésime INSEE du tracé : un nom plus récent ne correspondrait plus à son contour.
	public static function getDepartmentNames(): array {

		return [
			'01' => s("Ain"), '02' => s("Aisne"), '03' => s("Allier"), '04' => s("Alpes-de-Haute-Provence"),
			'05' => s("Hautes-Alpes"), '06' => s("Alpes-Maritimes"), '07' => s("Ardèche"), '08' => s("Ardennes"),
			'09' => s("Ariège"), '10' => s("Aube"), '11' => s("Aude"), '12' => s("Aveyron"),
			'13' => s("Bouches-du-Rhône"), '14' => s("Calvados"), '15' => s("Cantal"), '16' => s("Charente"),
			'17' => s("Charente-Maritime"), '18' => s("Cher"), '19' => s("Corrèze"), '21' => s("Côte-d'Or"),
			'22' => s("Côtes-d'Armor"), '23' => s("Creuse"), '24' => s("Dordogne"), '25' => s("Doubs"),
			'26' => s("Drôme"), '27' => s("Eure"), '28' => s("Eure-et-Loir"), '29' => s("Finistère"),
			'2A' => s("Corse-du-Sud"), '2B' => s("Haute-Corse"), '30' => s("Gard"), '31' => s("Haute-Garonne"),
			'32' => s("Gers"), '33' => s("Gironde"), '34' => s("Hérault"), '35' => s("Ille-et-Vilaine"),
			'36' => s("Indre"), '37' => s("Indre-et-Loire"), '38' => s("Isère"), '39' => s("Jura"),
			'40' => s("Landes"), '41' => s("Loir-et-Cher"), '42' => s("Loire"), '43' => s("Haute-Loire"),
			'44' => s("Loire-Atlantique"), '45' => s("Loiret"), '46' => s("Lot"), '47' => s("Lot-et-Garonne"),
			'48' => s("Lozère"), '49' => s("Maine-et-Loire"), '50' => s("Manche"), '51' => s("Marne"),
			'52' => s("Haute-Marne"), '53' => s("Mayenne"), '54' => s("Meurthe-et-Moselle"), '55' => s("Meuse"),
			'56' => s("Morbihan"), '57' => s("Moselle"), '58' => s("Nièvre"), '59' => s("Nord"),
			'60' => s("Oise"), '61' => s("Orne"), '62' => s("Pas-de-Calais"), '63' => s("Puy-de-Dôme"),
			'64' => s("Pyrénées-Atlantiques"), '65' => s("Hautes-Pyrénées"), '66' => s("Pyrénées-Orientales"), '67' => s("Bas-Rhin"),
			'68' => s("Haut-Rhin"), '69' => s("Rhône"), '70' => s("Haute-Saône"), '71' => s("Saône-et-Loire"),
			'72' => s("Sarthe"), '73' => s("Savoie"), '74' => s("Haute-Savoie"), '75' => s("Paris"),
			'76' => s("Seine-Maritime"), '77' => s("Seine-et-Marne"), '78' => s("Yvelines"), '79' => s("Deux-Sèvres"),
			'80' => s("Somme"), '81' => s("Tarn"), '82' => s("Tarn-et-Garonne"), '83' => s("Var"),
			'84' => s("Vaucluse"), '85' => s("Vendée"), '86' => s("Vienne"), '87' => s("Haute-Vienne"),
			'88' => s("Vosges"), '89' => s("Yonne"), '90' => s("Territoire de Belfort"), '91' => s("Essonne"),
			'92' => s("Hauts-de-Seine"), '93' => s("Seine-Saint-Denis"), '94' => s("Val-de-Marne"), '95' => s("Val-d'Oise"),
		];

	}

	/**
	 * Le nom d'un département que `\main\PlaceLib::extractCodes()` admet, outre-mer compris : le tracé
	 * ne porte que la métropole, et un lieu de production peut être à La Réunion.
	 */
	public static function getDepartmentName(string $department): string {

		return (self::getDepartmentNames() + [
			'971' => s("Guadeloupe"), '972' => s("Martinique"), '973' => s("Guyane"), '974' => s("La Réunion"), '976' => s("Mayotte"),
		])[$department];

	}

	/**
	 * Centre de chaque département sur le tracé, en [x, y, rayon équivalent], dans le repère du viewBox
	 * (1 000 de large). Le rayon est celui du disque de même surface : il dit si un nombre tient dans la
	 * forme. Calculé une fois depuis la géométrie, en même temps que le SVG, et figé ici — il ne bougera
	 * qu'avec le millésime IGN.
	 */
	public static function getCentres(): array {

		return [
		'01' => [713, 494, 38], '02' => [591, 151, 45], '03' => [565, 465, 43], '04' => [774, 692, 41], '05' => [775, 637, 37], '06' => [833, 708, 32],
		'07' => [650, 628, 37], '08' => [665, 146, 37], '09' => [451, 809, 34], '10' => [632, 276, 40], '11' => [513, 791, 39], '12' => [531, 674, 46],
		'13' => [695, 748, 35], '14' => [323, 197, 38], '15' => [530, 598, 38], '16' => [362, 532, 39], '17' => [304, 527, 41], '18' => [518, 399, 43],
		'19' => [476, 568, 38], '21' => [674, 363, 47], '22' => [153, 262, 43], '23' => [486, 495, 37], '24' => [399, 593, 48], '25' => [782, 389, 37],
		'26' => [700, 635, 40], '27' => [416, 196, 40], '28' => [441, 268, 39], '29' => [71, 280, 42], '2A' => [961, 914, 31], '2B' => [976, 861, 33],
		'30' => [633, 703, 38], '31' => [428, 766, 39], '32' => [379, 733, 39], '33' => [309, 621, 50], '34' => [578, 744, 39], '35' => [237, 291, 42],
		'36' => [456, 427, 42], '37' => [395, 379, 40], '38' => [728, 577, 44], '39' => [737, 432, 36], '40' => [295, 706, 48], '41' => [446, 344, 41],
		'42' => [632, 531, 35], '43' => [608, 591, 35], '44' => [234, 369, 42], '45' => [508, 315, 42], '46' => [458, 640, 36], '47' => [379, 666, 36],
		'48' => [587, 651, 36], '49' => [310, 366, 43], '50' => [257, 199, 40], '51' => [637, 212, 47], '52' => [704, 295, 40], '53' => [303, 291, 37],
		'54' => [768, 228, 37], '55' => [715, 208, 41], '56' => [157, 320, 42], '57' => [802, 203, 41], '58' => [587, 394, 42], '59' => [567, 63, 40],
		'60' => [513, 166, 40], '61' => [357, 244, 40], '62' => [504, 59, 43], '63' => [562, 531, 45], '64' => [296, 776, 43], '65' => [359, 796, 33],
		'66' => [520, 841, 31], '67' => [863, 239, 36], '68' => [844, 320, 30], '69' => [665, 517, 29], '70' => [763, 342, 37], '71' => [658, 440, 47],
		'72' => [363, 306, 40], '73' => [787, 556, 39], '74' => [786, 501, 34], '75' => [508, 221, 5], '76' => [418, 142, 41], '77' => [548, 244, 39],
		'78' => [474, 225, 25], '79' => [326, 449, 39], '80' => [503, 112, 41], '81' => [496, 724, 37], '82' => [435, 694, 30], '83' => [774, 757, 38],
		'84' => [702, 703, 29], '85' => [260, 437, 41], '86' => [379, 448, 42], '87' => [432, 515, 37], '88' => [783, 287, 39], '89' => [591, 322, 44],
		'90' => [820, 342, 13], '91' => [501, 254, 22], '92' => [501, 222, 7], '93' => [517, 215, 8], '94' => [516, 229, 8], '95' => [493, 199, 18],
		];

	}

}
?>
