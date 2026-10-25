<?php
namespace main;

class DesignUi {

	/**
	 * ⚠️ Le titre est facultatif depuis le 12/09/2026 : la carte sert aussi un contenu qui n'en a
	 * pas, comme une liste dont le titre est celui du panneau qui la porte. Un titre
	 * vide y laissait une ligne et son `margin-bottom` de 0,25 rem sous le liseré.
	 *
	 * `$class` s'ajoute aux classes de la carte, `$margin` à la grille qui l'enveloppe ; une marge vide
	 * pose la carte sans écart, quand le conteneur donne déjà le sien.
	 */
	public static function getAccentCard(string $color, ?string $id, ?string $title, string $content, string $class = '', string $margin = 'mb-2'): string {

		$h = '<div class="util-cards'.($margin === '' ? '' : ' '.$margin).'">';
			$h .= '<div class="util-card util-card-accent util-card-accent-'.$color.($class === '' ? '' : ' '.$class).'"'.($id !== NULL ? ' id="'.$id.'"' : '').'>';

				if($title !== NULL) {
					$h .= '<div class="util-card-title"><span>'.$title.'</span></div>';
				}

				$h .= $content;

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une carte à geste : ce que la page demande de faire, et les boutons qui le font. Elle s'ouvre sur
	 * une accroche, `$title`, que précède son chiffre `$value` en grand, ou à défaut `$image`, le logo
	 * de ce que la carte propose ; la phrase `$text` dit ensuite de quoi il s'agit. Une accroche qui se
	 * suffit avec son chiffre la laisse vide, et la carte passe alors au bouton.
	 *
	 * Le ton, choisi par l'appelant (NULL, `info`, `warning` ou `danger`), se lit sur l'encre du chiffre,
	 * ou sur le tampon `$stamp`, un mot frappé sur le bord du haut : sans l'un ni l'autre, il ne se voit
	 * pas. Sous un tampon, l'appelant pose sur le bouton de `$buttons` le contour du ton
	 * (`btn-outline-danger`, `btn-outline-warning`, `btn-outline-secondary` pour info), et
	 * `btn-outline-primary` sinon. Sans bouton, quand le geste se fait ailleurs sur la page, la carte
	 * s'arrête à sa phrase.
	 *
	 * ⚠️ À l'inverse de `getAccentCard()`, la carte se rend sans sa grille : elle vit en rangée, dans
	 * une `util-cards-fit` que l'appelant ouvre une fois pour toutes ses cartes. Le tampon fait
	 * descendre la rangée entière, ce qu'une grille par carte casserait.
	 */
	public static function getActionCard(?string $tone, string $title, string $text, string $buttons, ?string $value = NULL, string $image = '', ?string $stamp = NULL): string {

		$h = '<div class="util-card util-card-action'.self::getActionCardTone($tone).'">';

			$h .= '<div class="util-card-title">';
				$h .= ($value !== NULL) ? '<span class="util-figure-value">'.$value.'</span>' : $image;
				$h .= '<span>'.$title.'</span>';
			$h .= '</div>';

			// Un chiffre que son accroche suffit à dire se passe de phrase, et un paragraphe vide
			// garderait sa marge entre l'accroche et le bouton.
			if($text !== '') {
				$h .= '<p>'.$text.'</p>';
			}

			if($stamp !== NULL) {
				$h .= '<span class="util-stamp">'.$stamp.'</span>';
			}

			if($buttons !== '') {
				$h .= '<div class="util-card-buttons util-card-foot">'.$buttons.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	private static function getActionCardTone(?string $tone): string {

		return match($tone) {
			NULL => '',
			'info' => ' util-card-action-info',
			'warning' => ' util-card-action-warning',
			'danger' => ' util-card-action-danger',
		};

	}

	/**
	 * Une entrée de liste de réglages, d'exports ou d'imports, en util-deck : l'icône de son
	 * domaine, son intitulé, la ligne qui dit ce qu'il y a derrière la page, et le chevron.
	 *
	 * ⚠️ `data-ajax-download` ne fonctionne que sur un lien (`framework/util/static/js/form.js`) :
	 * un export doit donc rester une balise `a`, ce qu'est un util-deck de nature « lien ».
	 */
	public static function getDeckEntry(string $url, string $icon, string $domain, string $title, string $text = '', bool $download = FALSE): string {

		$h = '<a href="'.$url.'" class="util-deck"'.($download ? ' data-ajax-download data-waiter' : '').'>';
			$h .= self::getDeckHead($icon, $domain, $title, $text, 'chevron-right');
		$h .= '</a>';

		return $h;

	}

	/**
	 * ⚠️ La liste suit l'entrée sans enveloppe : `Lime.Dropdown.getListFromButton()` la cherche en
	 * `nextElementSibling`.
	 */
	public static function getDeckDropdown(string $icon, string $domain, string $title, string $list, string $text = ''): string {

		$h = '<a class="util-deck" data-dropdown="bottom-start">';
			$h .= self::getDeckHead($icon, $domain, $title, $text, 'caret-down-fill');
		$h .= '</a>';

		$h .= '<div class="dropdown-list">'.$list.'</div>';

		return $h;

	}

	/**
	 * La tête d'un util-deck : l'icône ronde dans la couleur de son domaine, l'intitulé et sa phrase,
	 * puis `$aside` et la flèche `$arrow` s'il y en a. `$title`, `$text` et `$aside` sont du HTML,
	 * déjà encodé par l'appelant. L'aside se place entre le corps et la flèche, comme sur la fiche.
	 */
	public static function getDeckHead(string $icon, string $domain, string $title, string $text = '', string $arrow = '', string $aside = ''): string {

		$h = '<div class="util-deck-head">';
			$h .= '<div class="util-icon util-icon-round util-icon-'.$domain.'">'.\Asset::icon($icon).'</div>';
			$h .= '<div class="util-deck-body">';
				$h .= '<div class="util-deck-title">'.$title.'</div>';
				if($text !== '') {
					$h .= '<p>'.$text.'</p>';
				}
			$h .= '</div>';
			if($aside !== '') {
				$h .= '<div class="util-deck-aside">'.$aside.'</div>';
			}
			if($arrow !== '') {
				$h .= '<div class="util-deck-arrow">'.\Asset::icon($arrow).'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'attribut qui déplie et replie un util-deck au clic sur l'élément qui le porte. `$container`
	 * nomme la classe du bloc qui bascule, util-deck-slot pour un emplacement d'un deck à plusieurs.
	 */
	public static function getDeckToggle(string $container = 'util-deck'): string {
		return attr('onclick', 'this.closest(".'.$container.'").classList.toggle("util-deck-open")');
	}

	/**
	 * Un chiffre d'un util-deck, sous son intitulé. Ils s'alignent d'un bloc à l'autre, c'est ce qui
	 * les rend comparables : la forme est donc la même d'une liste à l'autre.
	 */
	public static function getDeckFigure(string $key, string $value): string {
		return '<div class="util-deck-figure"><span class="util-deck-key">'.$key.'</span><span class="util-deck-value">'.$value.'</span></div>';
	}

	/**
	 * Une tuile d'un util-figures : la valeur en gros, son intitulé dessous. Avec `$url`, la tuile est un
	 * lien vers la liste qu'elle compte. `$value` et `$label` sont du HTML, déjà encodé par l'appelant.
	 */
	public static function getFigure(string $value, string $label, ?string $url = NULL, string $class = ''): string {

		$tag = ($url === NULL) ? 'div' : 'a';

		return '<'.$tag.($url === NULL ? '' : ' '.attr('href', $url)).' class="util-figure'.($class === '' ? '' : ' '.$class).'"><div class="util-figure-value">'.$value.'</div><div class="util-figure-label">'.$label.'</div></'.$tag.'>';

	}

	/**
	 * La rangée util-figures autour de tuiles rendues par getFigure(). `$class` porte la grille,
	 * util-figures-2 ou util-figures-3, et la marge. À ne pas confondre avec PdfUi::getFigures(), qui
	 * prend des paires valeur et libellé pour un document imprimé.
	 */
	public static function getFigures(array $figures, string $class = ''): string {
		return '<div class="util-figures'.($class === '' ? '' : ' '.$class).'">'.implode('', $figures).'</div>';
	}

	/**
	 * Une information d'une fiche util-record : son libellé, sa valeur et, s'il en a un, son geste.
	 * `$class` porte une déclinaison de l'item, util-record-item-alert ou util-record-item-wide.
	 */
	public static function getRecordItem(string $label, string $value, string $action = '', string $class = ''): string {

		$h = '<li class="util-record-item'.($class === '' ? '' : ' '.$class).'">';
			$h .= '<div class="util-record-label">'.$label.'</div>';
			$h .= '<div class="util-record-value">'.$value.'</div>';
			if($action !== '') {
				$h .= '<div class="util-record-action">'.$action.'</div>';
			}
		$h .= '</li>';

		return $h;

	}

	public static function getDragHandle(): string {
		return '<span class="util-drag-handle" title="'.s("Déplacer").'">'.\Asset::icon('grip-vertical').'</span>';
	}

	public static function getSplitBadge(string $label, string $value, string $class, bool $solid = TRUE): string {

		$h = '<span class="badge-split color-'.$class.'">';
			$h .= '<span class="badge '.($solid ? 'badge-solid bg-'.$class : 'badge-'.$class).'">'.$label.'</span>';
			$h .= '<span class="badge-split-detail">'.$value.'</span>';
		$h .= '</span>';

		return $h;

	}

	/**
	 * Un fond de courbes de niveau qui ne décrit aucun lieu, tiré d'un bruit de valeur.
	 *
	 * Il sert là où une carte manque : un dessin plutôt qu'un vide, sans jamais suggérer une
	 * position. La graine est l'identifiant de la structure, donc **le même fond à chaque visite
	 * et un fond par ferme** — tiré au hasard à l'affichage, il changerait de décor d'une page à
	 * l'autre, ce qui se lirait comme un défaut.
	 *
	 * ⚠️ Portage à l'identique d'une référence JS (`.claude/state/INRAE/placeholder-carte-courbes-A.js`),
	 * et la fidélité est **prouvée octet pour octet** sur les cinq SVG de référence livrés avec
	 * elle. Ce n'est pas du zèle : le piège est l'arithmétique. `Math.imul` et `>>> 0` sont des
	 * opérations 32 bits, et la multiplication naïve en PHP déborde l'entier 64 bits vers un
	 * flottant — PHP le signale par « Implicit conversion from float 9.86E+18 loses precision »,
	 * et le dessin change entièrement. D'où `getMultiply32()`, qui multiplie par demi-mots.
	 *
	 * Le contrôle sait diverger : un seul bit changé dans le générateur décale la sortie dès le
	 * 156e octet.
	 */
	public static function getNoiseContours(int $seed, int $width, int $height, int $step = 8, ?string $background = '#EAF7EE', string $stroke = '#B9C4B2', string $strokeOpacity = '.9'): string {

		$levels = [0.3, 0.42, 0.54, 0.66, 0.78];
		$cells = [$width / 50, $height / 45];

		$random = self::getRandom32($seed);
		$noise = self::getNoise($random, (int)ceil($cells[0]) + 1, (int)ceil($cells[1]) + 1);

		$countX = (int)ceil($width / $step);
		$countY = (int)ceil($height / $step);

		$values = [];

		for($y = 0; $y <= $countY; $y++) {

			$values[$y] = [];

			for($x = 0; $x <= $countX; $x++) {
				$values[$y][] = $noise($x / $countX * $cells[0], $y / $countY * $cells[1]);
			}

		}

		$h = '';

		foreach($levels as $level) {
			$h .= '<path d="'.self::getContour($values, $level, $countX, $countY, $step).'" fill="none" stroke="'.$stroke.'" stroke-width="0.9" stroke-linecap="round" opacity="'.$strokeOpacity.'"/>';
		}

		// Un fond nul laisse voir la page à travers les courbes : le bandeau d'une fiche n'en pose
		// aucun, la scène garde le sien.
		$rectangle = ($background === NULL) ? '' : '<rect width="'.$width.'" height="'.$height.'" fill="'.$background.'"/>';

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="xMidYMid slice">'
			.$rectangle.$h.'</svg>';

	}

	/**
	 * Les segments d'une courbe de niveau, par marching squares.
	 *
	 * Les seize cas de la table disent, pour chaque configuration des quatre coins d'une cellule,
	 * quels côtés la courbe traverse. Deux cas en donnent deux : la courbe passe deux fois.
	 */
	private static function getContour(array $values, float $level, int $countX, int $countY, int $step): string {

		$segments = [
			[], [[2, 3]], [[1, 2]], [[1, 3]], [[0, 1]], [[0, 3], [1, 2]], [[0, 2]], [[0, 3]],
			[[0, 3]], [[0, 2]], [[0, 1], [2, 3]], [[0, 1]], [[1, 3]], [[1, 2]], [[2, 3]], []
		];

		$d = '';

		for($y = 0; $y < $countY; $y++) {

			for($x = 0; $x < $countX; $x++) {

				$topLeft = $values[$y][$x];
				$topRight = $values[$y][$x + 1];
				$bottomRight = $values[$y + 1][$x + 1];
				$bottomLeft = $values[$y + 1][$x];

				$left = $x * $step;
				$top = $y * $step;

				$cross = fn(float $from, float $to): float => ($level - $from) / ($to - $from);

				$edges = [
					[$left + $cross($topLeft, $topRight) * $step, $top],
					[$left + $step, $top + $cross($topRight, $bottomRight) * $step],
					[$left + $cross($bottomLeft, $bottomRight) * $step, $top + $step],
					[$left, $top + $cross($topLeft, $bottomLeft) * $step]
				];

				$index = ($topLeft > $level ? 8 : 0) | ($topRight > $level ? 4 : 0)
					| ($bottomRight > $level ? 2 : 0) | ($bottomLeft > $level ? 1 : 0);

				foreach($segments[$index] as [$from, $to]) {
					$d .= 'M'.self::getDecimal($edges[$from][0]).' '.self::getDecimal($edges[$from][1])
						.'L'.self::getDecimal($edges[$to][0]).' '.self::getDecimal($edges[$to][1]);
				}

			}

		}

		return $d;

	}

	/**
	 * Le bruit de valeur : un treillis de valeurs tirées, interpolées en douceur entre elles.
	 *
	 * ⚠️ Le treillis se tire **ligne par ligne**, et l'ordre des tirages fait partie du dessin :
	 * le générateur est une suite, la parcourir autrement rend un autre relief.
	 */
	private static function getNoise(callable $random, int $countX, int $countY): callable {

		$grid = [];

		for($y = 0; $y <= $countY; $y++) {

			$grid[$y] = [];

			for($x = 0; $x <= $countX; $x++) {
				$grid[$y][] = $random();
			}

		}

		return function(float $x, float $y) use ($grid, $countX, $countY): float {

			$cellX = (int)floor($x);
			$cellY = (int)floor($y);

			$offsetX = $x - $cellX;
			$offsetY = $y - $cellY;

			$gridX = min($cellX, $countX - 1);
			$gridY = min($cellY, $countY - 1);

			// Lissage aux bords de cellule : sans lui, les courbes cassent en lignes droites à
			// chaque frontière du treillis.
			$smooth = fn(float $t): float => $t * $t * (3 - 2 * $t);

			$topLeft = $grid[$gridY][$gridX];
			$topRight = $grid[$gridY][$gridX + 1];
			$bottomLeft = $grid[$gridY + 1][$gridX];
			$bottomRight = $grid[$gridY + 1][$gridX + 1];

			return $topLeft
				+ ($topRight - $topLeft) * $smooth($offsetX)
				+ ($bottomLeft - $topLeft) * $smooth($offsetY)
				+ ($topLeft - $topRight - $bottomLeft + $bottomRight) * $smooth($offsetX) * $smooth($offsetY);

		};

	}

	/** Le générateur mulberry32 : une graine, une suite de valeurs entre 0 et 1, toujours la même. */
	private static function getRandom32(int $seed): callable {

		$state = ($seed * 2654435761) & 0xFFFFFFFF;

		return function() use (&$state): float {

			$state = ($state + 0x6D2B79F5) & 0xFFFFFFFF;

			$value = $state;
			$value = self::getMultiply32($value ^ ($value >> 15), $value | 1);
			$value = ($value ^ ($value + self::getMultiply32($value ^ ($value >> 7), $value | 61))) & 0xFFFFFFFF;

			return (($value ^ ($value >> 14)) & 0xFFFFFFFF) / 4294967296;

		};

	}

	/**
	 * Une multiplication 32 bits, par demi-mots.
	 *
	 * ⚠️ `$a * $b` déborderait : deux facteurs de 32 bits font jusqu'à 64, et PHP bascule alors
	 * l'entier en flottant en perdant les bits de poids faible — ceux, précisément, dont le
	 * générateur a besoin. Le produit se fait donc en deux moitiés, comme `Math.imul`.
	 */
	private static function getMultiply32(int $a, int $b): int {

		$a &= 0xFFFFFFFF;
		$b &= 0xFFFFFFFF;

		$highA = ($a >> 16) & 0xFFFF;
		$lowA = $a & 0xFFFF;
		$highB = ($b >> 16) & 0xFFFF;
		$lowB = $b & 0xFFFF;

		return (($lowA * $lowB) + ((($highA * $lowB + $lowA * $highB) & 0xFFFF) << 16)) & 0xFFFFFFFF;

	}

	/**
	 * Une décimale, comme JavaScript l'écrit.
	 *
	 * ⚠️ `round()` de PHP arrondit la moitié en s'éloignant de zéro, `Math.round` de JS l'arrondit
	 * vers le haut. Les coordonnées d'une courbe sont positives, donc les deux coïncident ici —
	 * mais la forme explicite dit laquelle des deux règles le portage suit.
	 */
	private static function getDecimal(float $value): string {
		return (string)(floor($value * 10 + 0.5) / 10);
	}


	/**
	 * Anneau de répartition : une part par segment, dans l'ordre donné.
	 *
	 * Les parts sont des couples [pourcentage, classe de couleur]. Les bornes du dégradé se
	 * calculent ici et non en CSS : un `conic-gradient` ne sait pas additionner ses propres
	 * segments, chacun doit recevoir son angle de début et de fin.
	 *
	 * L'anneau donne la répartition d'un coup d'œil, il ne donne pas les valeurs : il
	 * s'accompagne toujours de badges scindés ou d'une légende chiffrée.
	 */
	public static function getRing(array $parts, string $size = '7rem'): string {

		$stops = [];
		$start = 0;

		foreach($parts as [$value, $class]) {

			$stop = $start + $value;
			$stops[] = 'var(--'.$class.') '.$start.'% '.$stop.'%';
			$start = $stop;

		}

		if($stops === []) {
			return '';
		}

		return '<div class="util-ring" style="width: '.$size.'; background-image: conic-gradient('.implode(', ', $stops).')"></div>';

	}

	public static function getCelebrateBits(int $number): string {

		$bits = [
			['top: 12%; left: 18%; --from-x: 520%; --from-y: 200%; --turn: -40deg', 'graine-corail'],
			['top: 42%; left: 12%; --from-x: 700%; --from-y: 40%; --turn: 25deg', 'graine-corail-2'],
			['top: 84%; left: 5%; --from-x: 480%; --from-y: -120%; --turn: 70deg', 'graine-corail-3'],
			['top: 10%; right: 18%; --from-x: -520%; --from-y: 200%; --turn: 30deg', 'graine-corail-4'],
			['top: 40%; right: 12%; --from-x: -700%; --from-y: 40%; --turn: -55deg', 'graine-corail'],
			['top: 84%; right: 5%; --from-x: -480%; --from-y: -120%; --turn: 15deg', 'graine-corail-2'],
		];

		$h = '';

		foreach(array_slice($bits, 0, $number) as [$style, $friend]) {
			$h .= '<span class="util-celebrate-bit" style="'.$style.'">'.\Asset::svg('main', 'friends/'.$friend.'.svg').'</span>';
		}

		return $h;

	}

	/**
	 * Le logo en ligne, et non en image, pour que ses quatre feuilles battent l'une après l'autre.
	 */
	public static function getLogoWait(): string {
		return '<div class="util-logo-wait">'.\Asset::svg('main', 'svg/logo-otf.svg').'</div>';
	}

	public static function getRating(float $value, ?int $count = NULL, bool $compact = FALSE, bool $large = FALSE, ?string $url = NULL): string {

		$number = \util\TextUi::number($value, 1);

		$tag = ($url === NULL) ? 'span' : 'a';

		$h = '<'.$tag.($url === NULL ? '' : ' href="'.$url.'"').' class="util-rating'.($large ? ' util-rating-lg' : '').'" title="'.s("{value} sur 5", $number).'">';

			if($large) {
				$h .= '<span class="util-rating-value">'.$number.'</span>';
			}

			if($compact) {
				$h .= \Asset::icon('star-fill');
			} else {
				$h .= self::getRatingStars($value);
			}

			if($count !== NULL) {

				if($large === FALSE) {
					$h .= '<b>'.$number.'</b>';
				}

				$h .= '<span class="util-annotation">'.p("{value} avis", "{value} avis", $count, ['value' => \util\TextUi::number($count)]).'</span>';

			}

		$h .= '</'.$tag.'>';

		return $h;

	}

	private static function getRatingStars(float $value): string {

		$h = '<span class="util-rating-stars">';

			for($star = 1; $star <= 5; $star++) {

				if($value >= $star) {
					$h .= \Asset::icon('star-fill');
				} else if($value >= $star - 0.5) {
					$h .= \Asset::icon('star-half');
				} else {
					$h .= '<span class="util-rating-off">'.\Asset::icon('star-fill').'</span>';
				}

			}

		$h .= '</span>';

		return $h;

	}

	/**
	 * ⚠️ Les radios vont de 5 à 1, affichées de 1 à 5 par `row-reverse` : le sélecteur `~` n'atteint
	 * que les éléments qui suivent, donc les étoiles de gauche d'une note sont celles qui suivent son
	 * radio dans le document. `:has()` éviterait l'inversion, mais son coût sur Firefox l'exclut.
	 */
	public static function getRatingField(\util\FormUi $form, string $name, ?int $value): string {

		$h = '<div class="util-rating-input">';

			for($star = 5; $star >= 1; $star--) {

				$h .= $form->inputRadio($name, $star, selectedValue: $value);
				$h .= '<label for="'.$name.$star.'" title="'.p("{value} étoile", "{value} étoiles", $star).'">'.\Asset::icon('star-fill').'</label>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bloc d'une zone entière qui n'a rien à montrer : son titre, puis sa phrase et son geste s'il y
	 * en a. `$cta` le passe en util-block-cta, quand le bloc invite à un geste plutôt qu'il ne dit le
	 * vide. Les trois textes sont du HTML, déjà encodé par l'appelant.
	 */
	public static function getBlockEmpty(string $title, string $text = '', string $buttons = '', bool $cta = FALSE): string {

		$h = '<div class="'.($cta ? 'util-block-cta' : 'util-block-empty').'">';
			$h .= '<h3>'.$title.'</h3>';
			if($text !== '') {
				$h .= '<p>'.$text.'</p>';
			}
			if($buttons !== '') {
				$h .= '<div class="util-buttons">'.$buttons.'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'état vide d'un écran que ce lecteur ne peut pas remplir.
	 *
	 * ⚠️ **Un écran vide propose un geste, et un geste suppose un droit.** Servi à qui ne peut
	 * pas écrire, un `util-start` avec son bouton propose une action que la route refusera, et
	 * son texte au possessif — « le plan de culture de **votre** ferme » — parle d'une ferme qui
	 * n'est pas la sienne. Relevé le 12/09/2026 : six écrans de production sur huit le faisaient.
	 *
	 * ⚠️ **Deux lecteurs sans droit d'écriture, et non un.** Un **membre** de la ferme lit la
	 * raison — il peut demander ce droit, elle le concerne. Un **visiteur** venu d'un groupe n'a
	 * aucune raison de vouloir agir : lui expliquer pourquoi il ne peut pas l'invite à chercher un
	 * droit dont il n'a que faire, et fait du vide un refus. La bascule est donc `isMember()` et
	 * jamais le droit d'écriture, qui ne distingue pas les deux.
	 *
	 * ⚠️ **Le lien est facultatif et ne se reconduit pas par principe** : il ne se pose que s'il
	 * mène là où ce lecteur-là peut aller, sinon il déplace le refus d'un cran.
	 */
	public static function getEmptyRead(\structure\Structure $eStructure, string $member, string $visitor, string $link = ''): string {

		$h = '<div class="util-empty">';
			$h .= '<p>'.($eStructure->isMember() ? $member : $visitor).'</p>';
			$h .= $link;
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le libellé d'un geste : son icône, puis son texte, qui disparaît sous 576 px quand l'icône se
	 * comprend seule. `$label` est du HTML.
	 */
	public static function getGestureLabel(string $icon, string $label): string {
		return \Asset::icon($icon).'<span class="hide-xs-down"> '.$label.'</span>';
	}

	/**
	 * Le bouton de création d'un écran, celui que reçoit l'argument `create` de `getPageHeader()`.
	 * Une classe passée dans `$attributes` s'ajoute à `btn btn-secondary`. `$label` est du HTML.
	 */
	public static function getCreateButton(string $url, string $label, string $icon = 'plus-circle', array $attributes = []): string {

		$attributes['class'] = 'btn btn-secondary '.($attributes['class'] ?? '');

		return '<a '.attrs(['href' => $url] + $attributes).'>'.self::getGestureLabel($icon, $label).'</a>';

	}

	/**
	 * La roue d'une ligne ou d'un écran, et le menu qu'elle ouvre. `$items` arrive rendu, en
	 * dropdown-item et dropdown-divider ; `$title`, du HTML comme `$items`, ouvre le menu quand il
	 * n'est pas vide. `$class` s'ajoute au bouton, pour une roue réduite en btn-sm.
	 *
	 * ⚠️ La liste suit la roue sans enveloppe : `Lime.Dropdown.getListFromButton()` la cherche en
	 * `nextElementSibling`.
	 */
	public static function getGear(string $items, string $title = '', string $class = ''): string {

		$h = '<a data-dropdown="bottom-end" class="btn btn-color-primary dropdown-toggle'.($class === '' ? '' : ' '.$class).'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			if($title !== '') {
				$h .= '<div class="dropdown-title">'.$title.'</div>';
			}
			$h .= $items;
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un onglet d'une rangée `tabs`, qui ouvre le `tab-panel` de même `data-tab`. `$label` est du
	 * HTML, une icône en tab-item-icon peut le précéder.
	 */
	public static function getTabItem(string $tab, string $label, bool $selected = FALSE, string $class = ''): string {
		return '<a class="tab-item'.($selected ? ' selected' : '').($class === '' ? '' : ' '.$class).'" '.attr('data-tab', $tab).' onclick="Lime.Tab.select(this)">'.$label.'</a>';
	}

	/**
	 * Un jeton util-chip qui ouvre la liste de ses valeurs. `$options` porte une ligne par valeur,
	 * `[adresse, libellé, choisie]`, le libellé déjà encodé ; `$title` coiffe la liste, `$state` ajoute
	 * au jeton une forme, is-solid ou is-inverted. `is-active` dit qu'une valeur est choisie.
	 *
	 * ⚠️ La liste suit le jeton sans enveloppe : `Lime.Dropdown.getListFromButton()` la cherche en
	 * `nextElementSibling`.
	 */
	public static function getChipDropdown(string $label, array $options, bool $active = FALSE, string $title = '', string $state = ''): string {

		$h = '<a class="util-chip'.($active ? ' is-active' : '').($state === '' ? '' : ' '.$state).'" data-dropdown="bottom-start">'.$label.'</a>';

		$h .= '<div class="dropdown-list">';

			if($title !== '') {
				$h .= '<div class="dropdown-title">'.$title.'</div>';
			}

			foreach($options as [$url, $optionLabel, $selected]) {
				$h .= '<a '.attr('href', $url).' class="dropdown-item'.($selected ? ' selected' : '').'">'.$optionLabel.'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'en-tête d'un écran, posé dans sa bande (`util-page-band`) : le retour, le titre et sa
	 * marque d'état, ce qui situe l'écran, les gestes, et le logo de l'objet regardé quand il en a un.
	 * C'est ce composant qu'on prend pour un écran neuf, et non `util-action-fold`, qui fait la même
	 * chose mais ne vaut que sous un gabarit déclarant `--h1-size` et ne sait pas porter de logo.
	 *
	 * Tout arrive rendu : `$title` reçoit le contenu du titre et jamais sa balise, le poseur émettant
	 * le `<h1>` lui-même, et jamais une flèche de retour, qui passe par `$back`. Un appelant qui a un
	 * logo le rend comme il l'entend (`\structure\StructureUi::getLogo()`,
	 * `\shop\ShopLogoUi::getCamera()`), y compris sa taille, que le socle ne fixe pas.
	 *
	 * `$back` est l'adresse où mène le disque de retour, qui ne porte pas de `title`, qu'un écran
	 * tactile n'affiche pas : la flèche se comprend seule. `NULL` rend un retour par l'historique, pour
	 * les écrans qui n'ont pas d'adresse parente unique, et la chaîne vide n'en rend aucun.
	 *
	 * Les gestes s'écrivent dans trois arguments, que la méthode range toujours dans le même ordre :
	 * `$tools` (les outils, en `btn-outline-secondary`), `$create` (la création, en `btn-secondary`),
	 * `$settings` (la roue crantée, en dernier). Aucun outil ne porte de `title`, qu'un écran tactile
	 * n'affiche pas : un outil garde l'icône seule quand elle se comprend sans texte (loupe, chevrons,
	 * calendrier, ⓧ), prend un libellé en `hide-xs-down` quand l'icône reste lisible seule sous
	 * 576 px (PDF, téléverser, sélectionner), et un libellé visible à toutes les largeurs dès que
	 * l'icône est ambiguë (« Aide »).
	 *
	 * `$pages` est le menu des pages voisines, rendu par `getPages()`. Il suit le titre, compteur
	 * compris, et précède `$state`, sans entrer dans le `<h1>`. Le titre et lui partagent une rangée
	 * qui ne passe pas à la ligne : c'est le titre qui s'enroule à côté du disque, et le disque ne
	 * tombe jamais seul sous un nom long.
	 *
	 * `$season` est la saison de l'écran, en jeton rendu par `getSeason()`, que
	 * `\structure\StructureUi::getSeason()` garnit sur les écrans de la production, ou en texte quand il
	 * n'y a qu'une saison. Elle se choisit dans la rangée du titre, après `$pages`, dont le disque reste
	 * contre le titre qu'il remplace : `$context` dit où l'on est, la saison est un réglage de ce qu'on regarde.
	 *
	 * Deux décisions vivent ici plutôt que chez les appelants. La colonne du logo n'existe que si un
	 * logo est passé : créée à vide, elle laisserait sa gouttière et décalerait le titre. Et le logo,
	 * le contexte et les gestes n'ouvrent leur boîte que s'ils ont du contenu, pour la même raison,
	 * mesurée à une quarantaine de pixels de bande blanche sur la fiche contrat de l'AMAP. `$state`
	 * n'a pas de boîte à lui, il se concatène dans celle du titre, donc rien ne paraît quand il est
	 * vide sans qu'aucune garde ait à l'écarter.
	 *
	 * `$state` porte la marque d'état de l'objet regardé, ou la vue ouverte d'une rubrique quand une
	 * pastille la nomme (les ventes Professionnels et Particuliers). Il se pose à côté du titre,
	 * jamais dedans : une pastille est insécable (`.badge` porte `white-space: nowrap`), donc
	 * placée dans `$title` elle devient un mot de plus que la piste du titre ne peut pas couper.
	 *
	 * `$search` reçoit la recherche de l'écran, une omnibox rendue (`\main\OmniboxUi::get()`), qui se
	 * pose entre le titre et les gestes et passe sous le titre en dessous de 768 px. C'est l'appelant
	 * qui décide si l'écran a de quoi chercher : la chaîne vide n'ouvre aucune boîte, et l'en-tête reste
	 * alors celui d'un écran sans recherche, la grille qui fait la place au champ ne s'appliquant qu'en
	 * sa présence.
	 *
	 * `$backLabel` nomme l'écran où mène le retour. Il ne paraît que dans la bande d'un écran de
	 * groupe (`getPageMasthead()`), où le retour devient le surtitre « ← Formations » ; partout
	 * ailleurs, le disque reste seul.
	 */
	public static function getPageHeader(
		string $title,
		string $context = '',
		string $logo = '',
		string $state = '',
		?string $back = '',
		string $tools = '',
		string $create = '',
		string $pages = '',
		string $settings = '',
		string $season = '',
		string $search = '',
		string $backLabel = ''
	): string {

		$gestures = $tools.$create.$settings;

		$h = '<div class="util-page-band">';

			$h .= '<div class="util-page-header'.($logo === '' ? '' : ' util-page-header-identity').'">';

				if($logo !== '') {
					$h .= '<div class="util-page-header-logo">'.$logo.'</div>';
				}

				$h .= '<div class="util-page-header-main">';

					$h .= self::getPageBack($back, $backLabel);

					$heading = self::getHeading($title);

					if($season !== '' or $pages !== '') {

						$heading = '<div class="util-page-header-title">'.$heading;

							if($pages !== '') {
								$heading .= '<div class="util-page-header-pages">'.$pages.'</div>';
							}

							if($season !== '') {
								$heading .= '<div class="util-page-header-season">'.$season.'</div>';
							}

						$heading .= '</div>';

					}

					$h .= $heading;
					$h .= $state;

				$h .= '</div>';

				if($context !== '') {
					$h .= '<div class="util-page-header-context">'.$context.'</div>';
				}

				if($search !== '') {
					$h .= '<div class="util-page-header-search">'.$search.'</div>';
				}

				if($gestures !== '') {

					$h .= '<div class="util-page-header-aside">';

						// Chaque emplacement est borné par un commentaire, que la bande d'un écran de
						// groupe lit pour regrouper les gestes (`getPageMasthead()`). Un commentaire et
						// non une boîte : les règles de la tête comptent les gestes parmi les enfants
						// de la boîte des gestes (`component.css`, sous 768 px), et une boîte par
						// emplacement fausserait ce compte sur tous les écrans.
						foreach(['tools' => $tools, 'create' => $create, 'settings' => $settings] as $slot => $content) {
							$h .= '<!--util-page-header-'.$slot.'-->'.$content.'<!--/util-page-header-'.$slot.'-->';
						}

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La bande qui ouvre une page publique, hors d'une structure : les tutoriels, une FAQ. Un écran
	 * de structure garde `getPageHeader()`, à deux exceptions : le tableau de bord d'une AMAP pendant
	 * une saison, dont la bande porte la saison et ses chiffres, et les autres écrans d'un groupe, d'un
	 * réseau ou d'une AMAP, dont la bande enveloppe la tête du socle (`getPageMasthead()`).
	 *
	 * La bande prend la largeur de l'écran et pose son contenu dans un `container`, sur deux colonnes.
	 * À gauche, le retour, la tuile d'icône et le surtitre sur une rangée, puis le titre, le chapô et
	 * la recherche. À droite, calé en bas, `$aside`, en général les chiffres de la page dans une
	 * `util-figures-line util-figures-line-card`. Sous 768 px, la droite passe sous la gauche.
	 *
	 * `$back` suit la convention de `getPageHeader()` : la chaîne vide n'en pose aucun, NULL revient
	 * dans l'historique. `$search` reçoit une omnibox rendue, avec `util-omnibox-hero`. Chaque boîte
	 * ne s'ouvre que si elle a du contenu, pour que la bande d'une page sans chiffre ni recherche ne
	 * garde pas leur place.
	 */
	public static function getPageHero(string $title, string $label = '', string $icon = '', ?string $back = '', string $lead = '', string $search = '', string $aside = ''): string {

		$h = '<div class="util-page-hero">';
			$h .= '<div class="container util-page-hero-inner">';

				$h .= '<div>';

					if($back !== '' or $icon !== '' or $label !== '') {

						$h .= '<div class="util-page-hero-top">';

							$h .= self::getPageBack($back);

							if($icon !== '') {
								$h .= '<span class="util-icon util-icon-raised">'.\Asset::icon($icon).'</span>';
							}

							if($label !== '') {
								$h .= '<span class="util-label">'.$label.'</span>';
							}

						$h .= '</div>';

					}

					$h .= self::getHeading($title);

					if($lead !== '') {
						$h .= '<p class="util-page-hero-lead">'.$lead.'</p>';
					}

					$h .= $search;

				$h .= '</div>';

				if($aside !== '') {
					$h .= '<div>'.$aside.'</div>';
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le disque de retour d'une tête : celle d'un écran, celle d'une page publique, et la bande d'une
	 * fiche qui porte elle-même le nom. Vide, aucun retour ; NULL, le retour suit l'historique faute
	 * d'adresse connue. `$label` nomme l'écran où il mène, que seule la bande d'un écran de groupe
	 * montre (`getPageHeader()`).
	 */
	public static function getPageBack(?string $back, string $label = ''): string {

		if($back === '') {
			return '';
		}

		$h = ($back === NULL)
			? '<a onclick="history.back();" class="util-page-header-back">'
			: '<a href="'.$back.'" class="util-page-header-back">';
			$h .= \Asset::icon('arrow-left');

			if($label !== '') {
				$h .= '<span class="util-page-header-back-label">'.$label.'</span>';
			}

		$h .= '</a>';

		return $h;

	}

	/**
	 * La bande claire en tête d'un écran de groupe, sous son bandeau : celle de `getPageHero()`, dont
	 * elle garde le fond et ses disques, ramenée à la hauteur d'une tête d'écran. `$header` est la tête
	 * de l'écran, rendue par `getPageHeader()` : le titre reste émis par elle, et une vue partagée
	 * avec une ferme pose la même tête sous les deux gabarits.
	 *
	 * `$icon` est la vignette, l'icône de l'onglet ouvert, qui se tait quand la tête porte son propre
	 * logo. `$figures` reçoit les chiffres de l'écran en `util-figures-line util-figures-line-card`,
	 * calés à droite. Dans la bande, le retour devient un surtitre quand la tête le nomme
	 * (`$backLabel`), et les outils et la roue se rangent en une barre, la création au bout.
	 *
	 * `$tabs` reçoit les `.tab-item` rendus, comme `getPageTabs()`, et `$filter` le filtre posé à leur
	 * droite : un `util-filter` à menu, ou un `util-filter-options` quand ses choix et leurs compteurs
	 * doivent rester visibles. `$class` donne sa teinte à la bande d'un réseau (`util-masthead-network`)
	 * ou d'une AMAP (`util-masthead-amap`). La rangée n'est pas un `.tabs` : des onglets de `Lime.Tab` ont leurs panneaux dans le
	 * corps de l'écran, et c'est l'appelant qui pose autour de la bande et du corps le `.tabs` où
	 * `Lime.Tab` les cherche. `$fold` replie la rangée en un menu, comme dans `getPageTabs()`.
	 */
	public static function getPageMasthead(string $header, string $icon = '', string $tabs = '', string $filter = '', string $figures = '', bool $fold = FALSE, string $class = ''): string {

		$h = '<div class="util-page-hero util-masthead'.($class === '' ? '' : ' '.$class).'">';
			$h .= '<div class="container">';

				$h .= '<div class="util-masthead-head">';

					if($icon !== '') {
						$h .= '<div class="util-masthead-thumb">'.\Asset::icon($icon).'</div>';
					}

					[$header, $gestures] = self::getMastheadGestures($header);

					$h .= $header;

					if($gestures !== '') {
						// La boîte garde la classe des gestes de la tête, qui dessine leurs boutons.
						$h .= '<div class="util-page-header-aside util-masthead-actions">'.$gestures.'</div>';
					}

					if($figures !== '') {
						$h .= '<div class="util-masthead-figures">'.$figures.'</div>';
					}

				$h .= '</div>';

				if($tabs !== '' or $filter !== '') {

					$h .= '<div class="util-masthead-foot">';

						if($tabs !== '') {
							$h .= '<div class="util-masthead-tabs'.($fold ? ' tabs-h-fold' : '').'">';
								$h .= '<div class="tabs-item">'.$tabs.'</div>';
							$h .= '</div>';
						}

						$h .= $filter;

					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les gestes de la tête sortent de `getPageHeader()` pour se poser à côté d'elle, dans la bande :
	 * la tête du socle, sous 768 px, range ses gestes entre le titre et le contexte, alors que la bande
	 * garde le titre et son contexte d'un bloc et pose les gestes après lui. Les outils et la roue
	 * passent dans une barre, la création après elle. `getPageHeader()` borne chaque emplacement d'un
	 * commentaire, que seule la bande lit.
	 *
	 * @return array{string, string} la tête sans ses gestes, puis les gestes regroupés
	 */
	private static function getMastheadGestures(string $header): array {

		$gestures = '';

		$header = preg_replace_callback(
			'#<div class="util-page-header-aside"><!--util-page-header-tools-->(.*?)<!--/util-page-header-tools--><!--util-page-header-create-->(.*?)<!--/util-page-header-create--><!--util-page-header-settings-->(.*?)<!--/util-page-header-settings--></div>#s',
			function(array $slots) use(&$gestures): string {

				[, $tools, $create, $settings] = $slots;

				$bar = $tools.$settings;

				$gestures = ($bar === '' ? '' : '<div class="util-masthead-bar">'.$bar.'</div>').$create;

				return '';

			},
			$header
		);

		return [$header, $gestures];

	}

	// Le titre de la page, seul h1 de l'écran : getPageHeader() et getPageHero() l'émettent par ici.
	private static function getHeading(string $title): string {
		return '<h1>'.$title.'</h1>';
	}

	public function getPages(array $pages, bool $html = FALSE): string {

		if($pages === []) {
			return '';
		}

		$h = '<a class="btn btn-only-icon btn-outline-primary" data-dropdown="bottom-start">'.\Asset::icon('chevron-down').'</a>';
		$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light" data-dropdown-background="no">';

			foreach($pages as $page) {

				if($page === NULL) {
					$h .= '<div class="dropdown-divider"></div>';
				} else if(isset($page['title'])) {
					$h .= '<div class="dropdown-title">'.($html ? $page['title'] : encode($page['title'])).'</div>';
				} else if(isset($page['subtitle'])) {
					$h .= '<div class="dropdown-subtitle">'.($html ? $page['subtitle'] : encode($page['subtitle'])).'</div>';
				} else {
					$h .= '<a href="'.$page['url'].'" class="dropdown-item '.($page['selected'] ? 'selected' : '').'">'.($html ? $page['label'] : encode($page['label'])).'</a>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le jeton de saison, posé dans l'emplacement `$season` de `getPageHeader()` : il porte `$label`, le
	 * nom de la saison de l'écran, et ouvre la liste des autres. Une saison seule ne se choisit pas, et
	 * l'appelant la passe alors en texte, sans ce jeton.
	 *
	 * Le nom vient de l'appelant et ne se cherche pas dans la liste : la saison de l'écran peut ne pas
	 * y figurer, celle que le plan de culture retient pour un membre (`MemberLib::getDynamicSeason()`)
	 * survivant à une saison retirée de la configuration.
	 *
	 * `$seasons` a la forme des pages de `getPages()`, `url`, `label` et `selected`, la plus récente en
	 * tête : c'est l'ordre de tous les menus de saison, le plan de culture le premier, et la saison
	 * choisie se repère à sa place surlignée, pas à son rang. `$more` reçoit des entrées déjà
	 * rendues, sous un séparateur : les gestes qui ajoutent une saison portent un `data-ajax` et des
	 * `post-*` qu'une entrée de liste ne sait pas dire.
	 *
	 * `$title` nomme la liste quand ce qu'on choisit n'est pas une saison, un exercice comptable par
	 * exemple.
	 */
	public function getSeason(string $label, array $seasons, string $more = '', string $title = ''): string {

		$h = '<a class="util-chip" data-dropdown="bottom-start">'.encode($label).'</a>';

		$h .= '<div class="dropdown-list bg-primary">';

			$h .= '<div class="dropdown-title">'.($title === '' ? s("Changer de saison") : encode($title)).'</div>';

			foreach($seasons as $season) {
				$h .= '<a href="'.$season['url'].'" class="dropdown-item '.($season['selected'] ? 'selected' : '').'">'.encode($season['label']).'</a>';
			}

			if($more !== '') {
				$h .= '<div class="dropdown-divider"></div>';
				$h .= $more;
			}

		$h .= '</div>';

		return $h;

	}

	public function getPagesFromCategories(array $categories, \Closure $url, string $selected, bool $html = FALSE): string {

		$pages = [];

		foreach($categories as $key => $label) {

			if($label === NULL) {
				$pages[] = NULL;
			} else {
				$pages[] = [
					'url' => $url($key),
					'label' => $label,
					'selected' => ($key === $selected)
				];
			}

		}

		return $this->getPages($pages, $html);

	}

	/**
	 * Les onglets de tête d'un écran, ceux qui en ouvrent les vues : sur le gabarit d'une structure,
	 * au bureau, ils se posent dans la tête de l'écran quand ils en sont le premier contenu, et restent
	 * dans le contenu partout ailleurs (`tabs-page`, `component.css`).
	 *
	 * `$tabs` reçoit les `.tab-item` déjà rendus, comme toute rangée d'onglets du dépôt : un lien, un
	 * `onclick`, un `data-tab` de `Lime.Tab` ou un `data-dropdown` s'y écrivent de la même façon.
	 *
	 * `$tools` se pose à droite des onglets et reste dans le contenu : une recherche, le choix d'une
	 * parcelle, les gestes de l'écran. Chaque élément de premier niveau y est une colonne de la rangée,
	 * donc plusieurs gestes qui vont ensemble arrivent dans une seule boîte.
	 *
	 * `$panels` reçoit ce qui doit rester dans le `.tabs`, sous la rangée : les `.tab-panel` d'onglets
	 * qui basculent sans recharger, que `Lime.Tab` cherche là, ou les sous-onglets de l'onglet choisi.
	 *
	 * `$attributes` va sur le `.tabs` : l'`id` sous lequel `Lime.Tab` retient l'onglet choisi, un
	 * `onrender`, la classe par laquelle l'écran retrouve ses onglets.
	 *
	 * `$fold` replie les onglets en un menu quand ils ne tiennent pas dans la largeur (`tabs-h-fold`,
	 * `Lime.TabFold`). La classe va sur la rangée et non sur le `.tabs` : `Lime.TabFold` cherche les
	 * onglets parmi les enfants directs de l'élément qui la porte. Le repli vaut pour toute rangée qui peut
	 * dépasser la largeur d'un téléphone, et s'impose à celle dont la liste vient de la base, un onglet
	 * par catalogue, par catégorie ou par matériel, qui grandit sans borne.
	 */
	public static function getPageTabs(string $tabs, string $tools = '', string $panels = '', array $attributes = [], bool $fold = FALSE): string {

		$attributes['class'] = 'tabs tabs-lg tabs-page '.($attributes['class'] ?? '');

		$h = '<div '.attrs($attributes).'>';
			$h .= '<div class="tabs-page-head'.($fold ? ' tabs-h-fold' : '').'">';
				$h .= '<div class="tabs-item">'.$tabs.'</div>';
				$h .= $tools;
			$h .= '</div>';
			$h .= $panels;
		$h .= '</div>';

		return $h;

	}

	/**
	 * Pèse un geste avant qu'on le fasse : ce qu'il change, ce qui ne change pas, et s'il se défait.
	 * À réserver aux gestes dont les conséquences doivent être lues avant de cliquer ; sinon un
	 * `data-confirm` suffit, et pour une suppression la question est celle de `getDeletePanel()`.
	 * `$register` vaut `info` pour un geste qu'on explique, `warning` pour ce qui se reprend,
	 * `danger` pour ce qui détruit une saisie ou coupe quelqu'un d'un accès.
	 *
	 * Le titre est le geste suivi de son objet, avec le verbe du bouton (« Retirer Camille Martin du groupe »).
	 *
	 * Une conséquence est `['icon' => …, 'text' => …]`, et le texte une phrase entière, sujet puis
	 * verbe, **au futur** puisque c'est le geste qui la produit (« Les commandes passées seront
	 * figées ») : jamais « sujet : ce qui lui arrive ». Le sujet et les noms propres en gras.
	 * Chaque conséquence porte sa propre icône, c'est elle qui la fait reconnaître avant de la lire.
	 * Une conséquence qui ne vaut que pour l'un des choix du formulaire porte ses `attributes`, que le
	 * JS du formulaire lit pour la montrer ou la masquer sans attendre le serveur (`['class' => 'hide',
	 * 'data-…' => …]`) : le bloc garde ses lignes dans l'ordre de la planche, quel que soit le choix.
	 * `$rest` dit ce que le geste ne change pas, au présent, et suit les conséquences dans la même
	 * liste, sur un carré neutre.
	 *
	 * `$reversible` vaut TRUE quand un autre geste du produit défait celui-ci, qu'il écrive en base
	 * ou non. La marque qu'il rend se pose en haut à droite du bloc. NULL n'en rend aucune, pour un
	 * geste que la pastille ne décrirait pas : un paiement ne s'annule pas, et ne détruit rien.
	 *
	 * `$action` porte le bouton du geste, et ses voisins éventuels, quand rien ne s'intercale entre le
	 * bloc et lui : il se pose alors dans le bloc, sous les lignes, dans la colonne du texte. Un bouton
	 * posé sous le bloc, séparé de lui, laisse au lecteur le soin de relier deux choses que rien ne relie.
	 *
	 * ⚠️ **Le bloc ne porte jamais de champ.** Les champs qui décident du geste suivent le bloc dans le
	 * formulaire, et le bouton les suit : il va au pied du panneau, jamais dans le bloc au-dessus des
	 * champs qu'il envoie.
	 *
	 * La page design system, section « Peser un geste » en `?agents`, porte le détail.
	 */
	public static function getWeigh(string $register, string $icon, ?string $title, ?string $intro, array $consequences, array $rest, ?bool $reversible, ?string $action = NULL): string {

		$class = match($register) {
			'info' => 'util-weigh',
			'warning' => 'util-weigh util-weigh-warning',
			'danger' => 'util-weigh util-weigh-danger'
		};

		$h = '<div class="'.$class.'">';

			$h .= '<span class="util-weigh-disc">'.\Asset::icon($icon).'</span>';

			if($title !== NULL or $intro !== NULL) {

				$h .= '<div class="util-weigh-head">';

					if($title !== NULL) {
						$h .= '<div class="util-weigh-title">'.$title.'</div>';
					}

					if($intro !== NULL) {
						$h .= '<p class="util-weigh-intro">'.$intro.'</p>';
					}

				$h .= '</div>';

			}

			if($reversible === TRUE) {
				$h .= '<span class="util-weigh-mark util-weigh-mark-back">'.\Asset::icon('arrow-counterclockwise').s("Annulable").'</span>';
			} else if($reversible === FALSE) {
				$h .= '<span class="util-weigh-mark util-weigh-mark-final">'.\Asset::icon('exclamation-octagon-fill').s("Définitif").'</span>';
			}

			$h .= '<ul class="util-weigh-lines">';

				foreach($consequences as $consequence) {

					$attributes = $consequence['attributes'] ?? [];
					$classes = array_filter([($consequence['alert'] ?? FALSE) ? 'util-weigh-alert' : NULL, $attributes['class'] ?? NULL]);
					$attributes['class'] = ($classes === []) ? NULL : implode(' ', $classes);

					$h .= '<li '.attrs($attributes).'>';
						$h .= '<span class="util-weigh-glyph">'.\Asset::icon($consequence['icon']).'</span>';
						$h .= '<span>'.$consequence['text'].'</span>';
					$h .= '</li>';

				}

				foreach($rest as $line) {

					$h .= '<li class="util-weigh-same">';
						$h .= '<span class="util-weigh-glyph">'.\Asset::icon('check-lg').'</span>';
						$h .= '<span>'.$line.'</span>';
					$h .= '</li>';

				}

			$h .= '</ul>';

			if($action !== NULL) {
				$h .= '<div class="util-weigh-action">'.$action.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le panneau d'une suppression définitive, rendu par `getWeigh()` au registre `danger`.
	 *
	 * ⚠️ **Une seule question décide : quelqu'un d'autre que celui qui clique perd-il quelque
	 * chose ?** Si non, un `data-confirm` suffit, quelle que soit l'ampleur technique du geste.
	 * Ni la cascade, ni le nombre de tables, ni l'irréversibilité ne tranchent : mesuré le
	 * 13/09/2026, **14 `delete()` de Lib touchent une autre table et aucun des 14 ne mérite ce
	 * panneau** — un rapprochement qui suit son règlement ou le contenu d'un PDF qui suit son PDF
	 * emporte des lignes, il ne coupe personne.
	 *
	 * Le produit compte 116 suppressions confirmées et 149 `delete()` : le poser sur toutes le
	 * viderait de son sens, parce qu'un écran qui déploie un inventaire pour effacer une ligne de
	 * vente apprend à le survoler, et le jour où il compte vraiment personne ne le lit.
	 *
	 * ⚠️ **Ce qui part et ce qui reste dans la même liste**, chiffrés partout où c'est possible.
	 * Une liste qui ne dirait que les pertes laisserait croire que tout est perdu, et c'est
	 * précisément ce qu'un utilisateur craint au moment où il hésite.
	 *
	 * ⚠️ **Chiffré, jamais approximé.** On chiffre ce que la page a déjà sous la main ; sinon on
	 * nomme au pluriel indéfini — « les membres de ce rôle », jamais « des membres ». Un chiffre
	 * approché détruirait la confiance dans les lignes qui, elles, sont justes. Une liste longue se
	 * **regroupe** par familles, trois lignes au plus, chacune avec son ordre de grandeur ; un
	 * plafond couperait la liste sans dire ce qu'il cache.
	 *
	 * ⚠️ **Un refus se dit avant le geste et non après** : proposer un bouton que l'action refusera
	 * ensuite fait porter à l'utilisateur la découverte d'une règle. Le panneau rend alors le refus
	 * seul, sans inventaire ni bouton : il n'y a rien à peser.
	 */
	public static function getDeletePanel(string $id, string $title, string $subTitle, array $goes, array $stays, string $note, string $action, string $refusal = ''): \Panel {

		if($refusal !== '') {
			return new \Panel(id: $id, title: $title, subTitle: $subTitle, body: '<div class="util-block-info"><p>'.$refusal.'</p></div>');
		}

		$consequences = array_map(fn(string $line) => ['icon' => 'x-lg', 'text' => $line], $goes);

		return new \Panel(
			id: $id,
			title: $title,
			subTitle: $subTitle,
			body: self::getWeigh('danger', 'trash', NULL, $note, $consequences, $stays, FALSE),
			footer: $action
		);

	}

	/**
	 * Entre `data-confirm`, geste sans conséquence pour autrui, et `getDeletePanel()`, perte sans retour :
	 * un geste qui atteint d'autres personnes sans rien leur retirer. D'où ni liseré ni inventaire.
	 */
	public static function getConfirmPanel(string $id, string $title, string $subTitle, string $text, string $action): \Panel {

		$h = '<p>'.$text.'</p>';
		$h .= '<div class="flex-buttons">';
			$h .= $action;
			$h .= '<a onclick="Lime.Panel.closeLast()" class="btn btn-outline-primary">'.s("Annuler").'</a>';
		$h .= '</div>';

		return new \Panel(
			id: $id,
			title: $title,
			subTitle: $subTitle,
			body: $h
		);

	}

	/**
	 * Le bandeau qui accueille sur un écran qu'on découvre : un titre, un corps et des gestes, puis la
	 * mascotte. Tout est du HTML déjà rendu et encodé par l'appelant, qui écrit les <p> de son corps.
	 * Une partie vide ne rend rien. $class s'ajoute au bloc, pour sa marge.
	 */
	public static function getWelcome(string $title, string $text, string $mascot, string $buttons = '', string $class = ''): string {

		$h = '<div class="util-welcome'.($class === '' ? '' : ' '.$class).'">';
			$h .= '<div class="util-welcome-text">';
				if($title !== '') {
					$h .= '<div class="util-welcome-title">'.$title.'</div>';
				}
				$h .= $text;
				if($buttons !== '') {
					$h .= '<div class="util-welcome-buttons">'.$buttons.'</div>';
				}
			$h .= '</div>';
			if($mascot !== '') {
				$h .= '<div class="util-welcome-mascot">'.$mascot.'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une section d'une page publique, sur toute la largeur, autour de son contenu déjà rendu.
	 */
	public static function getBand(string $content, string $class = ''): string {

		$h = '<section class="util-band'.($class === '' ? '' : ' '.$class).'">';
			$h .= '<div class="container">';
				$h .= $content;
			$h .= '</div>';
		$h .= '</section>';

		return $h;

	}

	/**
	 * La tête d'une bande : son titre, puis son chapô s'il y en a un. Les deux sont du HTML, déjà
	 * encodé par l'appelant.
	 */
	public static function getBandHead(string $title, string $lead = ''): string {

		$h = '<div class="util-band-head">';
			$h .= '<h2>'.$title.'</h2>';
			if($lead !== '') {
				$h .= '<p class="util-band-lead">'.$lead.'</p>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le coup de pinceau qui ferme une util-brushed en haut ou en bas.
	 */
	public static function getBrushSeparator(string $position): string {

		$class = match($position) {
			'top' => 'util-separator-top',
			'bottom' => 'util-separator-bottom',
		};

		return '<div class="'.$class.'">'.\Asset::svg('main', 'svg/brush-separator.svg').'</div>';

	}

	/**
	 * Une bande de page publique fermée au pinceau en haut et en bas.
	 */
	public static function getBrushedBand(string $content, string $class = ''): string {

		$h = '<section class="util-band util-brushed'.($class === '' ? '' : ' '.$class).'">';
			$h .= self::getBrushSeparator('top');
			$h .= '<div class="container">';
				$h .= $content;
			$h .= '</div>';
			$h .= self::getBrushSeparator('bottom');
		$h .= '</section>';

		return $h;

	}

	public function getStart(string $icon, string $title, string $content, ?string $image = NULL, ?string $buttons = NULL, ?string $upcoming = NULL): string {

		$h = '<div class="util-start util-brushed'.($upcoming === NULL ? '' : ' util-start-upcoming').'">';
			$h .= self::getBrushSeparator('top');
			if($upcoming !== NULL) {
				$h .= '<div class="util-start-stamp">';
					$h .= $upcoming;
				$h .= '</div>';
			}
			if($image) {
				$h .= '<div class="util-start-image">';
					$h .= $image;
				$h .= '</div>';
			}
			$h .= '<h2 class="util-start-title">';
				$h .= '<span class="util-start-icon">';
					$h .= $icon;
				$h .= '</span>';
				$h .= $title;
			$h .= '</h2>';
			$h .= '<div class="util-start-content">';
				$h .= $content;
			$h .= '</div>';
			if($buttons !== NULL) {
				$h .= '<div class="util-start-button">';
					$h .= $buttons;
				$h .= '</div>';
			}
			$h .= self::getBrushSeparator('bottom');
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les gains d'une fonctionnalité jamais nourrie, à concaténer à la phrase dans l'argument `content`
	 * de `getStart()` : ce que l'écran montrera une fois rempli, une tuile par gain, chacun
	 * `[pictogramme, titre, précision]`. Deux ou trois gains, les colonnes étant égales : un seul
	 * s'étire sur toute la bande, quatre passent sous la largeur lisible d'une tuile.
	 */
	public static function getStartGains(array $gains): string {

		$h = '<div class="util-block-title">'.s("Ce que vous y trouverez").'</div>';
		$h .= '<div class="util-start-gains">';

			foreach($gains as [$icon, $title, $text]) {

				$h .= '<div class="util-start-gain">';
					$h .= '<div class="util-icon util-icon-round util-start-gain-icon">'.\Asset::icon($icon).'</div>';
					$h .= '<div>';
						$h .= '<div class="util-start-gain-title">'.$title.'</div>';
						$h .= '<div class="util-start-gain-text">'.$text.'</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			default => NULL

		};


	}

}
?>
