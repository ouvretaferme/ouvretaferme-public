<?php
namespace sequence;

/**
 * Une planche ou une surface libre dessinée en croquis : en perspective isométrique, le « Plan »,
 * et pour une planche en coupe en travers. Retours du 28/09 sur la planche des vues de
 * T3 : « appli la planche dans les deux vues pour tester », « enlève la vue plan », « Isométrique
 * est renommé Plan », puis « cette vue utilise le nouvel affichage » sur une plantation, « et
 * cette vue aussi » sur la bibliothèque et « faut utiliser la nouvelle vue » sur la surface libre.
 * La perspective est donc le dessin d'une densité (`DensityPlanAiUi::get()`). Une planche a sa
 * coupe sous elle, dans le calculateur comme sous la ligne « Plan » de l'intervention et de la
 * série, qui seule nomme les passe-pieds (« faut que ce soit cohérent », le 29/09).
 *
 * Tout se calcule depuis `DensityPlanAiUi::getGeometry()` : les rangs, la largeur dessinée, et la
 * longueur, graduée au mètre. La planche est à plat, sans butte (« aplati totalement la butte ») :
 * elle se distingue du passe-pied par sa teinte, séparée de lui par un trait fin. Une surface
 * libre n'est qu'une bande cultivée, sans passe-pied ni bord.
 *
 * Le trait tremble comme un croquis, par un filtre de turbulence posé sur les formes, jamais sur
 * les textes. Chaque dessin porte son propre filtre sous un identifiant tiré au hasard : un
 * filtre partagé vivrait dans le premier dessin de la page, et un dessin masqué (un onglet, un
 * menu fermé) l'éteindrait pour tous les autres.
 *
 * Les textes gardent la même taille à l'écran quelle que soit la largeur du dessin : leur taille
 * est multipliée par `--density-view-unit`, les unités de dessin par pixel, que le script tient
 * à jour (`SequenceDensityView.fit`).
 */
class DensityViewAiUi {

	// L'échelle de la perspective, en unités de dessin par centimètre.
	const SCALE = 1.6;

	// La largeur de la coupe en unités de dessin, quelle que soit la planche.
	const CUT_WIDTH = 660;

	// Le plant du rang le plus à gauche, posé sur le trait du premier mètre, sert d'étalon.
	const ANCHOR = 100;

	// L'écart sur le rang ne se cote pas dans le dessin (« enlève », le 28/09) : le
	// quadrillage de 10 cm en donne l'ordre de grandeur. Sous 10 cm, les plantules rapetissent en
	// proportion, et sous 5 cm ne sont plus que des points (« si lespacement est inférieur à 10cm
	// réduit la taille des plantules / si inferieur < 5cm alors mets juste des points verts »).
	const DIMENSION = 10;
	const DOT = 5;

	public function __construct() {

		\Asset::css('sequence', 'densityViewAi.css');
		\Asset::js('sequence', 'densityViewAi.js');

	}

	/**
	 * `$reference` est la largeur en travers du plan le plus large parmi ceux posés côte à côte :
	 * tous les dessins reçoivent le même cadre, donc la même échelle, et une planche de 75 cm s'y
	 * lit plus étroite qu'une planche de 120.
	 */
	public function getIsometric(array $plan, int $length, float $reference = 0): string {

		$g = DensityPlanAiUi::getGeometry($plan, $length);

		$filter = $this->getFilterId();

		$width = $g['total'];
		$y0 = $g['half'];
		$y1 = $g['half'] + $g['bed'];
		$rows = $g['positions'];

		$shapes = [
			'<polygon class="density-view-alley" filter="url(#'.$filter.')" points="'.self::points([self::iso(0, 0), self::iso($length, 0), self::iso($length, $width), self::iso(0, $width)]).'"/>',
			'<polygon class="density-view-top" filter="url(#'.$filter.')" points="'.self::points([self::iso(0, $y0), self::iso($length, $y0), self::iso($length, $y1), self::iso(0, $y1)]).'"/>'
		];

		// Le quadrillage ne couvre que la planche : le passe-pied n'est pas cultivé.
		$shapes = array_merge($shapes, $this->getGrid($length, $y0, $y1));

		// La séparation de la planche et du passe-pied, un trait fin le long de ses deux bords
		// (« remets une ligne séparateur planche allée mais plus fine »). Une surface libre n'a
		// pas de bord : la bande dessinée en est un extrait.
		$bed = ($plan['use'] === Sequence::BED);

		if($bed) {
			foreach([$y0, $y1] as $y) {
				$shapes[] = self::line('density-view-border', self::iso(0, $y), self::iso($length, $y));
			}
		}

		$spacing = $plan['plantSpacing'];
		$interval = $g['bed'] / count($rows);
		$plants = $this->getPlants($rows, $spacing, $plan['stagger'], $length);

		// Les rangs se retracent par-dessus le quadrillage : chaque cote entre rangs tombe sur l'un
		// d'eux. Au-delà du plafond de plants du plan coté, le rang se dessine en trait plein, comme
		// sur le plan coté.
		foreach($rows as $y) {
			$shapes[] = self::line($plants === [] ? 'density-view-row-full' : 'density-view-row', self::iso(0, $y), self::iso($length, $y));
		}

		// Même taille pour toutes les plantules, en proportion de l'écart le plus serré, pour que
		// deux voisines ne se touchent pas : c'est le vide autour qui montre l'espacement.
		$size = min(7, 0.4 * min($spacing, $interval)) * self::SCALE * self::getSeedlingRatio($spacing);

		usort($plants, fn(array $a, array $b) => ($a[0] + $a[1]) <=> ($b[0] + $b[1]));

		foreach($plants as [$x, $y]) {
			$shapes[] = ($spacing < self::DOT) ? $this->getDot(self::iso($x, $y)) : $this->getSeedling($x, $y, $size);
		}

		// Dans une vignette de 320 px, cette taille ne laisse qu'un point vert : à partir de
		// DIMENSION, le script agrandit les plantules jusqu'à 12 px de large à l'écran (retour de
		// du 28/09, sous mandat de M-Prod), sans que deux voisines se touchent. La largeur
		// d'une plantule est celle de ses deux cotylédons, 1,85 fois `$size` (getSeedling()), la
		// place l'écart le plus serré vu de face, le rang fuyant à 30°.
		$seedling = ($plants !== [] and $spacing >= self::DIMENSION) ? [
			'seedling-width' => $size * 1.85,
			'seedling-space' => min($spacing, $interval) * self::SCALE * cos(M_PI / 6)
		] : [];

		// Comme le plan coté : une colonne de cotes collée au bord de départ, un trait par rang,
		// « cm » en tête, les libellés dans l'axe de la planche. Toutes restent (« manque des
		// ecartements en haut », le 28/09). `data-space` porte la place dont chaque libellé
		// dispose, `data-center` la distance de son milieu à « cm » : quand un seul n'y tient pas,
		// le script les réduit toutes d'autant, puis recule « cm » s'il touche le premier.
		$edges = array_merge([$y0], $rows, [$y1]);
		$angle = -rad2deg(atan2(sin(M_PI / 6), cos(M_PI / 6)));

		foreach($edges as $y) {
			$shapes[] = self::line('density-view-tick', self::iso(0, $y), self::iso(-4, $y));
		}

		for($i = 0; $i < count($edges) - 1; $i++) {

			[$a, $b] = self::iso(-9, ($edges[$i] + $edges[$i + 1]) / 2);
			$space = ($edges[$i + 1] - $edges[$i]) * self::SCALE;
			$shapes[] = '<text class="density-view-cote density-view-cote-edge" data-space="'.self::number($space).'" data-center="'.self::number((($edges[$i] + $edges[$i + 1]) / 2 - $y0 + 16) * self::SCALE).'" x="'.self::number($a).'" y="'.self::number($b).'" text-anchor="middle" dominant-baseline="middle" transform="rotate('.self::number($angle).' '.self::number($a).' '.self::number($b).')">'.self::centimetres($edges[$i + 1] - $edges[$i]).'</text>';

		}

		[$a, $b] = self::iso(-9, $y0 - 16);
		$shapes[] = '<text class="density-view-cote density-view-cote-edge density-view-cote-unit" x="'.self::number($a).'" y="'.self::number($b).'" text-anchor="middle" dominant-baseline="middle" transform="rotate('.self::number($angle).' '.self::number($a).' '.self::number($b).')">'.s("cm").'</text>';

		// La largeur de la planche, bornée à son bout et écrite dans l'axe des cotes entre rangs
		// (« borner en bas pour qu'on voit bien que le 80cm décrit la largeur de la planche »).
		// Une surface libre n'a pas de largeur.
		if($bed) {

			foreach([$y0, $y1] as $y) {
				$shapes[] = self::line('density-view-tick', self::iso($length, $y), self::iso($length + 6, $y));
			}

			$shapes[] = self::line('density-view-tick', self::iso($length + 4, $y0), self::iso($length + 4, $y1));

			[$a, $b] = self::iso($length + 11, $y0 + $g['bed'] / 2);
			$shapes[] = '<text class="density-view-cote" x="'.self::number($a).'" y="'.self::number($b).'" text-anchor="middle" dominant-baseline="middle" transform="rotate('.self::number($angle).' '.self::number($a).' '.self::number($b).')">'.s("{value} cm", self::centimetres($g['bed'])).'</text>';

		}

		$metres = intdiv($length, 100);

		for($metre = 0; $metre <= $metres; $metre++) {

			[$a, $b] = self::iso($metre * 100, $width);
			$shapes[] = self::line('density-view-tick', [$a, $b], [$a - 4, $b + 6]);

			$shapes[] = '<text class="density-view-cote" x="'.self::number($a - 6).'" y="'.self::number($b + 20).'" text-anchor="middle">'.($metre === 0 ? '0' : s("{value} m", $metre)).'</text>';

		}

		// Le cadre prend les dimensions de celui du plan le plus large posé à côté, pour qu'ils
		// partagent l'échelle, et se centre sur ce dessin-ci.
		$box = $this->getBox($length, $width);
		$frame = $this->getBox($length, max($width, $reference));

		$box = [
			$box[0] + ($box[2] - $frame[2]) / 2,
			$box[1] + ($box[3] - $frame[3]) / 2,
			$frame[2],
			$frame[3]
		];

		return $this->getSvg($box, $filter, $shapes, $seedling);

	}

	protected function getBox(int $length, float $width): array {

		$xs = [];
		$ys = [];

		foreach([0, $length] as $x) {
			foreach([0, $width] as $y) {
				[$xs[], $ys[]] = self::iso($x, $y);
			}
		}

		return [min($xs) - 24, min($ys) - 34, max($xs) - min($xs) + 24 + 70, max($ys) - min($ys) + 34 + 40];

	}

	/**
	 * `$reference` joue le même rôle que pour la perspective : des coupes posées côte à côte
	 * partagent l'échelle de la plus large, et chacune se centre dans le même cadre.
	 */
	public function getCut(array $plan, int $length, float $reference = 0): string {

		$g = DensityPlanAiUi::getGeometry($plan, $length);

		$filter = $this->getFilterId();

		$s = self::CUT_WIDTH / max($g['total'], $reference);
		$w = $g['total'] * $s;
		$rows = $g['positions'];
		$interval = $g['bed'] / count($rows);
		// La coupe montre un plant par rang, dont l'écart sur le rang ne se voit pas : sa plantule
		// garde sa taille, sans point sous 5 cm.
		$size = min(50, min($plan['plantSpacing'], $interval) * 0.45 * $s);

		// Le sol se cale sous la ligne de cotes et la plantule, sans vide au-dessus.
		$ground = 18 + 24 + $size * 0.75;
		$x0 = $g['half'] * $s;
		$x1 = ($g['half'] + $g['bed']) * $s;

		// La terre de la planche garde sa teinte jusqu'au fond de la coupe : la surface qu'elle
		// occupe se lit sur toute la hauteur (retour du 28/09).
		$shapes = [
			'<rect class="density-view-subsoil" x="0" y="'.self::number($ground).'" width="'.self::number($w).'" height="36"/>',
			'<rect class="density-view-top" x="'.self::number($x0).'" y="'.self::number($ground).'" width="'.self::number($x1 - $x0).'" height="36"/>'
		];

		// Une suite pseudo-aléatoire figée : la texture de la terre ne bouge pas d'un rendu à l'autre.
		$state = 9;
		$random = function() use(&$state): float {
			$state = ($state * 1103515245 + 12345) % 2147483648;
			return $state / 2147483648;
		};

		for($grain = 0; $grain < 260; $grain++) {
			$shapes[] = '<circle class="density-view-grain" cx="'.self::number($random() * $w).'" cy="'.self::number($ground + 3 + $random() * 31).'" r="'.self::number(0.7 + $random() * 0.9).'"/>';
		}

		// Le sous-sol n'a pas de bord : un seul trait court d'un bout à l'autre, et la planche se
		// détache par sa seule teinte, comme dans la perspective.
		$shapes[] = '<path class="density-view-ground" filter="url(#'.$filter.')" d="M0,'.self::number($ground).' L'.self::number($w).','.self::number($ground).'"/>';

		foreach($rows as $y) {
			$shapes[] = $this->getSeedlingProfile($y * $s, $ground, $size);
		}

		// Les cotes au-dessus : passe-pied, rangs, passe-pied.
		$edges = ($g['half'] > 0) ?
			array_merge([0, $g['half']], $rows, [$g['half'] + $g['bed'], $g['total']]) :
			array_merge([0], $rows, [$g['bed']]);

		$axis = 18;
		$shapes[] = self::line('density-view-axis', [0, $axis], [$w, $axis]);

		foreach($edges as $edge) {
			$shapes[] = self::line('density-view-tick', [$edge * $s, $axis - 4], [$edge * $s, $axis + 4]);
		}

		for($i = 0; $i < count($edges) - 1; $i++) {
			$shapes[] = '<text class="density-view-cote" x="'.self::number(($edges[$i] + $edges[$i + 1]) / 2 * $s).'" y="'.self::number($axis - 7).'" text-anchor="middle">'.self::centimetres($edges[$i + 1] - $edges[$i]).'</text>';
		}

		$shapes[] = '<text class="density-view-cote density-view-cote-strong" x="'.self::number(($x0 + $x1) / 2).'" y="'.self::number($ground + 62).'" text-anchor="middle">'.s("planche de {value} cm", self::centimetres($g['bed'])).'</text>';

		// Les passe-pieds se calent sur les bords du dessin : centrés, ils en sortent sur un téléphone.
		// Là où ils touchent la planche, le script les descend d'une ligne (`SequenceDensityView.layout`).
		if($g['half'] > 0) {
			$shapes[] = '<text class="density-view-cote density-view-cote-alley" x="0" y="'.self::number($ground + 62).'" text-anchor="start">'.s("passe-pied").'</text>';
			$shapes[] = '<text class="density-view-cote density-view-cote-alley" x="'.self::number($w).'" y="'.self::number($ground + 62).'" text-anchor="end">'.s("passe-pied").'</text>';
		}

		return $this->getSvg([($w - self::CUT_WIDTH) / 2, -18, self::CUT_WIDTH, $ground + 88], $filter, $shapes);

	}

	protected function getSvg(array $box, string $filter, array $shapes, array $attributes = []): string {

		$dataset = '';

		foreach($attributes as $name => $value) {
			$dataset .= ' data-'.$name.'="'.self::number($value).'"';
		}

		$h = '<svg class="density-view-svg" viewBox="'.implode(' ', array_map(fn(float $value) => self::number($value), $box)).'"'.$dataset.' onrender="SequenceDensityView.fit(this)">';
			$h .= '<defs><filter id="'.$filter.'" filterUnits="userSpaceOnUse" x="-2000" y="-2000" width="5000" height="5000"><feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" seed="3"/><feDisplacementMap in="SourceGraphic" scale="1.5"/></filter></defs>';
			$h .= implode('', $shapes);
		$h .= '</svg>';

		return $h;

	}

	protected function getFilterId(): string {

		return 'density-view-rough-'.bin2hex(random_bytes(4));

	}

	// La taille des plantules selon l'écart sur le rang : entière à partir de DIMENSION, réduite en
	// proportion en dessous. Sous DOT, la plantule devient un point, que ce facteur ne touche pas.
	protected static function getSeedlingRatio(float $spacing): float {
		return min(1, $spacing / self::DIMENSION);
	}

	/**
	 * Les plants de la planche, en centimètres. Le rang le plus à gauche à l'écran, le dernier,
	 * porte un plant sur l'étalon ; les autres gardent leur décalage de quinconce. Aucun plant
	 * collé au bout de la planche, ses feuilles déborderaient. Au-delà du plafond du plan coté,
	 * aucun plant : les rangs se dessinent en trait plein.
	 */
	protected function getPlants(array $rows, float $spacing, bool $stagger, int $length): array {

		$last = ($stagger and (count($rows) - 1) % 2 === 1) ? $spacing / 2 : 0;
		$plants = [];

		foreach($rows as $row => $y) {

			$offset = ($stagger and $row % 2 === 1) ? $spacing / 2 : 0;

			$x = fmod(self::ANCHOR - $last + $offset, $spacing);

			if($x < 0) {
				$x += $spacing;
			}

			if($x < 5) {
				$x += $spacing;
			}

			for(; $x < $length - 4; $x += $spacing) {

				$plants[] = [$x, $y];

				if(count($plants) > DensityPlanAiUi::PLANTS) {
					return [];
				}

			}

		}

		return $plants;

	}

	protected function getGrid(int $length, float $ya, float $yb): array {

		$lines = [];

		for($x = 0; $x <= $length; $x += 10) {
			$lines[] = self::line('density-view-grid'.($x % 100 === 0 ? ' density-view-grid-major' : ''), self::iso($x, $ya), self::iso($x, $yb));
		}

		// Le quadrillage en travers se compte depuis le bord de la planche.
		for($y = $ya + 10; $y < $yb; $y += 10) {
			$lines[] = self::line('density-view-grid', self::iso(0, $y), self::iso($length, $y));
		}

		return $lines;

	}

	// Une plantule de trois quarts : la tige, puis deux cotylédons dans l'axe du rang, qui fuit à 30°.
	// Elle se dessine autour de son pied, placé par le groupe extérieur, pour que le script puisse
	// l'agrandir sans la déplacer (`--density-view-seedling-scale`).
	protected function getSeedling(float $x, float $y, float $size): string {

		[$a, $b] = self::iso($x, $y);
		$stem = $size * 0.9;

		$h = '<g transform="translate('.self::number($a).' '.self::number($b).')"><g class="density-view-seedling">';
			$h .= '<line class="density-view-stem" x1="0" y1="0" x2="0" y2="'.self::number(-$stem).'"/>';

			foreach([-1, 1] as $side) {
				$cx = $side * $size * 0.5 * cos(M_PI / 6);
				$cy = -$stem + $side * $size * 0.5 * sin(M_PI / 6);
				$h .= '<ellipse class="density-view-leaf" cx="'.self::number($cx).'" cy="'.self::number($cy).'" rx="'.self::number($size * 0.55).'" ry="'.self::number($size * 0.26).'" transform="rotate(30 '.self::number($cx).' '.self::number($cy).')"/>';
			}

		$h .= '</g></g>';

		return $h;

	}

	// Un plant trop serré pour se dessiner : un point vert, de même taille quel que soit l'écart.
	protected function getDot(array $point): string {
		return '<circle class="density-view-leaf" cx="'.self::number($point[0]).'" cy="'.self::number($point[1]).'" r="1.2"/>';
	}

	// Une plantule de profil, réduite à l'essentiel : une tige courte et deux cotylédons en amande.
	protected function getSeedlingProfile(float $cx, float $ground, float $size): string {

		$top = $ground - $size * 0.45;

		$h = self::line('density-view-stem', [$cx, $ground], [$cx, $top]);

		foreach([-1, 1] as $side) {
			$ex = $cx + $side * $size * 0.38;
			$ey = $top - $size * 0.12;
			$h .= '<ellipse class="density-view-leaf" cx="'.self::number($ex).'" cy="'.self::number($ey).'" rx="'.self::number($size * 0.36).'" ry="'.self::number($size * 0.14).'" transform="rotate('.self::number($side * -18).' '.self::number($ex).' '.self::number($ey).')"/>';
		}

		return $h;

	}

	protected static function iso(float $x, float $y): array {

		return [
			($x - $y) * cos(M_PI / 6) * self::SCALE,
			($x + $y) * sin(M_PI / 6) * self::SCALE
		];

	}

	protected static function line(string $class, array $from, array $to): string {

		return '<line class="'.$class.'" x1="'.self::number($from[0]).'" y1="'.self::number($from[1]).'" x2="'.self::number($to[0]).'" y2="'.self::number($to[1]).'"/>';

	}

	protected static function points(array $points): string {

		return implode(' ', array_map(fn(array $point) => self::number($point[0]).','.self::number($point[1]), $points));

	}

	// Les cotes au centimètre (« arrondi à l'entier les chiffres de cm », le 28/09).
	protected static function centimetres(float $value): string {
		return \util\TextUi::number(round($value), 0);
	}

	protected static function number(float $value): string {

		return number_format($value, 1, '.', '');

	}

}
?>
