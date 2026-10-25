<?php
namespace util;

/**
 * Common methods for text
 *
 */
class TextUi {

	/**
	 * Beautify a text
	 *
	 * @param string $text
	 * @return string
	 */
	public static function beautify(string $text): string {

		$text = str_replace([' ?', ' !'], ['&nbsp;?', '&nbsp;!'], $text);

		return $text;

	}

	/**
	 * 'th'ise a number
	 *
	 * @param int $number
	 * @return string
	 */
	public static function th(int $number): string {

		if($number === 1) {
			return s("{position}<sup>er</sup>", ['position' => $number]);
		} else if($number === 2) {
			return s("{position}<sup>ème</sup>", ['position' => $number]);
		} else if($number === 3) {
			return s("{position}<sup>ème</sup>", ['position' => $number]);
		} else {
			return s("{position}<sup>ème</sup>", ['position' => $number]);
		}

	}

	/**
	 *
	 */
	public static function tiny(string $text, bool $link): string {

		$matches = NULL;

		if(preg_match_all('/https?:\/\/((www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6})\b([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)/si', $text, $matches) > 0) {

			foreach($matches[0] as $key => $url) {

				if(
					str_ends_with($url, '.') or
					str_ends_with($url, ',')
				) {
					$url = substr($url, 0, -1);
				}

				$shortUrl = $matches[1][$key];

				$text = str_replace(
					$url,
					$link ?
						'<a '.attr('href', $url).' target="_blank">'.$shortUrl.'</a> '.\Asset::icon('box-arrow-up-right').'' :
						'<span '.attr('data-href', $url).' class="color-secondary">'.$shortUrl.'</span> '.\Asset::icon('box-arrow-up-right'),
					$text
				);

			}

		}

		return $text;

	}

	/**
	 * Reduce a number
	 *
	 * @param int $number
	 * @return string
	 */
	public static function reduce(int $number): string {

		if($number < 1000) {
			return $number;
		}

		if($number < 10000) {
			return (floor($number / 100) / 10).'K';
		}

		if($number < 1000000) {
			return floor($number / 1000).'K';
		}

		if($number < 10000000) {
			return (floor($number / 100000) / 10).'M';
		}

		return floor($number / 1000000).'M';

	}

	/**
	 * Switch item
	 */
	public static function switch(array $attributes, bool $on = FALSE, ?string $textOn = NULL, ?string $textOff = NULL, ?string $labelOn = NULL, ?string $labelOff = NULL): string {

		\Asset::css('util', 'form.css');

		$class = 'field-switch';
		if(array_key_exists('class', $attributes)) {
			$class .= ' '.$attributes['class'];
			unset($attributes['class']);
		}


		if(array_key_exists('disabled', $attributes)) {
			$isDisabled = $attributes['disabled'];
			if($isDisabled) {
				$class .= ' disabled';
				// `pointer-events: none` emporterait le `title` qui dit pourquoi c'est verrouillé :
				// le verrou tient donc au retrait des attributs d'action, ici.
				unset($attributes['data-ajax'], $attributes['data-confirm'], $attributes['onclick'], $attributes['href']);
			}
			unset($attributes['disabled']);
		} else {
			$isDisabled = FALSE;
		}

		$showOn = '<div '.($labelOn !== NULL ? 'title="'.encode($labelOn).'"' : '').'>'.$textOn.'</div>';
		$showOff = '<div '.($labelOff !== NULL ? 'title="'.encode($labelOff).'"' : '').'>'.$textOff.'</div>';

		$h = '<a '.attrs($attributes).' class="'.$class.' '.($on ? 'field-switch-on' : 'field-switch-off').'">';
			$h .= '<div class="field-switch-circle"></div>';
			$h .= '<div class="field-switch-text">';
				// Les deux libellés, même verrouillé : la boîte se mesure sur le plus long, et la
				// translation de `field-switch-off` sortirait un libellé seul hors du cadre rogné.
				$h .= $showOn.$showOff;
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Display a success message
	 *
	 * @param string $message
	 */
	public static function success(string $message): string {

		$text = '';

		if(str_contains($message, '\\')) {

			[$package, $fqn] = explode('\\', $message, 2);

			if(\Package::exists($package)) {

				$class = '\\'.$package.'\AlertUi';
				$text = $class::getSuccess($fqn) ?? '';

			}

		}

		return $text;

	}

	/**
	 * Display an error message
	 *
	 * @param string $message
	 * @param array $options Options to help display
	 */
	public static function error(string $message, array $options = []): string {

		$text = '';

		if(str_contains($message, '\\')) {

			[$package, $fqn] = explode('\\', $message, 2);

			if(\Package::exists($package)) {

				$class = '\\'.$package.'\AlertUi';

				$output = $class::getError($fqn, $options);

				if(is_closure($output)) {
					$text = $output();
				} else {
					$text = $output ?? '';
				}

			}

		}

		return $text;

	}


	/**
	 * Creates a numeric pagination for "?page=X", or "?<argument>=X"
	 *
	 * @param string $url URL of the paginated list
	 * @param ?string $ajaxClass Ajax.Query class that loads the page in place, for an ajax pagination
	 * @param string $argument URL argument carrying the page, for a screen with two paginated lists
	 *
	 */
	public static function pagination(int $currentPage, float $nPage, string $url = LIME_REQUEST, ?string $ajaxClass = NULL, string $argument = 'page'): string {

		$nPage = (int)ceil($nPage);

		if($nPage <= 1) {
			return '';
		}

		$url = HttpUi::setArgument($url, $argument, '{page}', FALSE);

		$range = 10;
		$rangeStart = max(0, $currentPage - $range / 2);
		$rangeEnd = min($nPage, $rangeStart + $range);

		$form = new FormUi();

		$h = '<div class="pagination-wrapper">';

			$h .= '<ul class="pagination">';

				if($currentPage > 0) {

					$h .= '<li>
						<a class="btn btn-outline-primary" '.self::getPaginationUrl($url, $url, 0, $ajaxClass).' data-page="0" aria-label="'.s("Début").'">
						<span>&laquo;</span>
						</a>
					</li>';

				}

				for($i = $rangeStart; $i < $rangeEnd; $i++) {
					$h .= '<li><a class="btn btn'.($i !== $currentPage ? '-outline' : '').'-primary" '.self::getPaginationUrl($url, $url, $i, $ajaxClass).' data-page="'.$i.'">'.($i + 1).'</a></li>';
				}

				if($currentPage !== $nPage - 1) {

					$h .= '<li>
						<a class="btn btn-outline-primary" '.self::getPaginationUrl($url, $url, $nPage - 1, $ajaxClass).' data-page="'.($nPage - 1).'" aria-label="'.s("Fin").'">
						<span>&raquo;</span>
						</a>
					</li>';

				}

			$h .= '</ul>';

			$h .= $form->open(NULL, [
				'onsubmit' => 'Lime.Pagination.form(\''.$url.'\', this'.($ajaxClass === NULL ? '' : ', \''.$ajaxClass.'\'').');'
			]);
				$h .= $form->inputGroup(
					$form->number("page", "", ["placeholder" => s("Aller à..."), 'min' => 1, 'max' => $nPage, 'class' => 'pagination-goto', 'style' => 'width: 75px', 'title' => s("Aller à la page...")]).
					$form->submit(s("Ok"))
				);
			$h .= $form->close();

		$h .= '</div>';

		return $h;
	}


	/**
	 * Creates a previous/next pagination for "?page=X"
	 *
	 */
	public static function paginationAround(int $currentPage, bool $hasMore): string {

		$url = HttpUi::setArgument(LIME_REQUEST, 'page', '{page}', FALSE);
		$urlZero = HttpUi::removeArgument(LIME_REQUEST, 'page');

		$hasBefore = ($currentPage > 0);

		if($hasBefore === FALSE and $hasMore === FALSE) {
			return '';
		}

		$h = '<div class="input-group">';

			if($hasBefore) {

				$h .= '<a class="btn btn-secondary" '.self::getPaginationUrl($urlZero, $url, $currentPage - 1).' data-page="'.($currentPage - 1).'">
					&laquo; '.s("Précédente").'
				</a>';

			}

			$h .= '<div class="input-group" style="background-color: #eee; display: flex; align-items: center; padding: 0 1rem; font-weight: bold">'.s("Page {value}", $currentPage + 1).'</div>';

			if($hasMore) {

				$h .=	'<a class="btn btn-secondary" '.self::getPaginationUrl($urlZero, $url, $currentPage + 1).' data-page="'.($currentPage + 1).'">
					'.s("Suivante").' &raquo;
				</a>';

			}

		$h .= '</div>';

		return $h;

	}

	protected static function getPaginationUrl(string $urlZero, string $url, $page, ?string $ajaxClass = NULL) {

		if($page === 0) {
			$selectedUrl = str_replace('{page}', $page, $urlZero);
		} else {
			$selectedUrl = str_replace('{page}', $page, $url);
		}

		if($ajaxClass === NULL) {
			return 'href="'.$selectedUrl.'"';
		} else {
			return 'href="'.$selectedUrl.'" data-ajax="'.$selectedUrl.'" data-ajax-method="get" data-ajax-class="'.$ajaxClass.'"';
		}

	}

	/**
	 * Un texte libre saisi par l'utilisateur, avec ses retours à la ligne. Échapper avant nl2br() : dans
	 * l'autre ordre, les <br /> seraient échappés avec le texte.
	 */
	public static function multiline(string $text): string {

		return nl2br(encode($text));

	}

	/**
	 * Un texte dont aucun mot ne se sépare de son voisin en fin de ligne, une quantité et son unité par
	 * exemple : chaque espace devient une espace insécable. Le texte n'est pas échappé.
	 */
	public static function nonBreaking(string $text): string {

		return str_replace(' ', "\u{a0}", $text);

	}

	/**
	 * La valeur d'une cellule sans objet. Une valeur qui manque, sur une ligne où elle a un sens, s'écrit
	 * par util-empty.
	 */
	public static function none(): string {

		return '<span class="color-muted">-</span>';

	}

	/**
	 * Le bouton qui copie dans le presse-papiers le texte de l'élément que désigne $selector, puis affiche
	 * $message en alerte (doCopy(), lime.js). Les attributs sont échappés ; $label reste du HTML, venu de s().
	 */
	public static function copy(string $selector, string $message, string $label, string $class = 'btn btn-outline-primary', bool $icon = TRUE): string {

		return '<a onclick="doCopy(this)" data-selector="'.encode($selector).'" data-message="'.encode($message).'" class="'.encode($class).'">'.($icon ? \Asset::icon('clipboard').' ' : '').$label.'</a>';

	}

	public static function pc(int|float $number, int $precision = 0): string {

		// Au plus $precision décimales, aucune si la valeur arrondie est entière : pc(50.0, 1) rend « 50 % ».
		$numberFormatter = new \NumberFormatter(\L::getLang(), \NumberFormatter::DECIMAL);
		$numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $precision);

		$value = round($number, $precision);

		if($value === 0.0) {
			$value = '&lt; '.$numberFormatter->format(1 / pow(10, $precision));
		} else {
			$value = $numberFormatter->format($value);
		}

		return '<span style="white-space: nowrap">'.s("{value} %", $value).'</span>';

	}

	public static function money(float $number, string $currency = 'EUR', ?int $precision = NULL, ?int $dynamic = NULL): string {

		// Un cumul qui solde exactement une base laisse un epsilon négatif que round() ramène à -0.0,
		// affiché « -0,00 € ». Aucun montant nul n'a de signe.
		if($number == 0) {
			$number = 0;
		}

		if($precision === NULL) {

			$precision = 2;
			$dynamic = 4;

		}

		$dynamic ??= $precision;

		$string = (string)$number;
		$decimal = max($precision, min($dynamic, str_contains($string, '.') ? (strlen($string) - strpos($string, '.') - 1) : 0));

		// Même règle pour un montant qui ne vaut zéro qu'une fois arrondi aux décimales
		// affichées : un compte de reprises à -0,40 € sortait « -0 € » sur un document
		// comptable écrit à l'euro.
		if(round($number, $decimal) == 0) {
			$number = 0;
		}

		$numberFormatter = new \NumberFormatter(\L::getLang(), \NumberFormatter::CURRENCY);
		$numberFormatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimal);
		// NumberFormatter arrondit au pair par défaut, et 1 240,50 € s'écrirait « 1 240 € » à l'euro. Un
		// montant affiché s'arrondit au commercial, comme round() et l'arrondi fiscal à l'euro.
		$numberFormatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

		return $numberFormatter->formatCurrency($number, $currency);

	}

	public static function number(int|float|null $number, int|string|null $precision = 'auto'): ?string {

		if($number === NULL) {
			return NULL;
		}

		if($precision === 'auto') {
			$precision = is_int($number) ? 0 : 2;
		}

		$numberFormatter = new \NumberFormatter(\L::getLang(), \NumberFormatter::DECIMAL);

		if($precision !== NULL) {

			$numberFormatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $precision);
			$formattedNumber = round($number, $precision);

		} else {
			$formattedNumber = $number;
		}

		return $numberFormatter->format($formattedNumber);

	}

	/**
	 * Le poids d'un fichier, en kilooctets sous le mégaoctet, en mégaoctets à une décimale au-delà.
	 * Une chaîne est prise pour le nombre de mégaoctets déjà écrit : le navigateur reçoit ainsi une
	 * phrase avec `{size}` à la place du nombre, qu'il remplace par le poids du fichier qu'il refuse.
	 */
	public static function size(int|string $size): string {

		if(is_string($size)) {
			return s("{value}\u{00A0}Mo", $size);
		}

		if($size < 1024 * 1024) {
			return s("{value}\u{00A0}Ko", max(1, (int)round($size / 1024)));
		}

		return s("{value}\u{00A0}Mo", self::number(round($size / 1024 / 1024, 1), NULL));

	}

	public static function csvNumber(int|float|null $number, ?int $precision = NULL): ?string {

		if($number === NULL) {
			return NULL;
		}

		$precision ??= is_int($number) ? 0 : 2;

		return str_replace('.', ',', round($number, $precision));

	}

}
?>
