<?php
namespace dev;

/**
 * Relevé des mouvements écrits dans les feuilles de style suivies : transitions, animations et
 * images clés, une ligne par segment.
 *
 * Lancement : dev/motion (scope=css ou scope=script)
 */
class MotionLib {

	const TRANSITION = 'transition';
	const ANIMATION = 'animation';
	const KEYFRAMES = 'keyframes';
	const SCRIPT = 'script';

	const COST_COMPOSITE = 'composite';
	const COST_PAINT = 'peinture';
	const COST_LAYOUT = 'mise en page';
	const COST_UNKNOWN = 'inconnu';

	const COLUMNS = ['file', 'line', 'segment', 'kind', 'declaration', 'selector', 'media', 'property', 'name', 'duration', 'delay', 'curve', 'iteration', 'cost', 'gesture', 'literal', 'value'];

	const COMPOSITE = ['transform', 'opacity', 'filter', 'translate', 'rotate', 'scale', 'backdrop-filter'];

	const LAYOUT = [
		'all', 'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height', 'top', 'right', 'bottom', 'left', 'inset',
		'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
		'border-width', 'font-size', 'line-height', 'letter-spacing', 'flex', 'flex-basis', 'flex-grow', 'gap', 'grid-template-columns', 'grid-template-rows',
	];

	const PAINT = [
		'color', 'background', 'background-color', 'background-position', 'background-size', 'border-color', 'border-radius', 'box-shadow', 'text-shadow',
		'outline', 'outline-color', 'outline-offset', 'fill', 'stroke', 'stroke-dashoffset', 'visibility', 'clip-path', 'text-decoration-color',
	];

	const CURVES = ['ease', 'linear', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'];

	const ANIMATION_KEYWORDS = ['normal', 'reverse', 'alternate', 'alternate-reverse', 'none', 'forwards', 'backwards', 'both', 'running', 'paused', 'initial', 'inherit', 'unset'];

	const GESTURES = [
		':hover' => 'survol',
		':focus-within' => 'focus',
		':focus-visible' => 'focus',
		':focus' => 'focus',
		':active' => 'appui',
		':checked' => 'coche',
		'[open]' => 'ouverture',
		'.open' => 'ouverture',
		'.show' => 'ouverture',
		'.active' => 'activation',
		'.selected' => 'sélection',
		':disabled' => 'désactivation',
	];

	/**
	 * @return array<int, array<string, string|int>>
	 */
	public static function getDeclarations(): array {

		$rows = [];

		foreach(SentinelLib::getFiles('.css') as $file => $path) {
			array_push($rows, ...self::parse(file_get_contents($path), $file));
		}

		return self::resolveAnimations($rows);

	}

	/**
	 * @return array<int, array<string, string|int>>
	 */
	public static function getScriptDeclarations(): array {

		$rows = [];

		foreach(SentinelLib::getFiles('.js') + SentinelLib::getFiles('.php') as $file => $path) {

			if(
				str_ends_with($file, '.min.js') or
				str_contains($file, '/static/lib/') or
				str_ends_with($file, '.t.php')
			) {
				continue;
			}

			array_push($rows, ...self::parseScript(file_get_contents($path), $file));

		}

		usort($rows, fn($a, $b) => [$a['file'], $a['line'], $a['segment']] <=> [$b['file'], $b['line'], $b['segment']]);

		return $rows;

	}

	/**
	 * @return array<int, array<string, string|int>>
	 */
	public static function parse(string $css, string $file): array {

		$css = self::removeComments($css);

		$rows = [];
		$selectors = [];
		$keyframes = [];

		$stack = [];
		$buffer = '';
		$bufferLine = 0;
		$line = 1;
		$quote = NULL;
		$depth = 0;

		$length = strlen($css);

		for($i = 0; $i < $length; $i++) {

			$character = $css[$i];

			if($character === "\n") {
				$line++;
			}

			if($quote !== NULL) {

				if($character === '\\') {
					$buffer .= $character.($css[$i + 1] ?? '');
					$i++;
					continue;
				}

				if($character === $quote) {
					$quote = NULL;
				}

				$buffer .= $character;
				continue;

			}

			if($character === '"' or $character === "'") {
				$quote = $character;
			} else if($character === '(') {
				$depth++;
			} else if($character === ')') {
				$depth = max(0, $depth - 1);
			} else if($depth === 0 and $character === '{') {

				$prelude = self::normalize($buffer);
				$stack[] = ['prelude' => $prelude, 'line' => $bufferLine];

				if(preg_match('/^@(-[a-z]+-)?keyframes\s+(\S+)/i', $prelude, $match) === 1) {
					$keyframes[count($stack) - 1] = ['name' => trim($match[2], '"\''), 'line' => $bufferLine, 'properties' => []];
				} else if(str_starts_with($prelude, '@') === FALSE and self::getKeyframesLevel($keyframes) === NULL) {
					$selectors[] = $prelude;
				}

				$buffer = '';
				continue;

			} else if($depth === 0 and ($character === ';' or $character === '}')) {

				self::readDeclaration($rows, $keyframes, $stack, $buffer, $bufferLine, $file);
				$buffer = '';

				if($character === '}') {

					$level = count($stack) - 1;

					if(isset($keyframes[$level])) {
						$rows[] = self::createKeyframes($file, $keyframes[$level], self::getMedia(array_slice($stack, 0, $level)));
						unset($keyframes[$level]);
					}

					array_pop($stack);

				}

				continue;

			}

			if($buffer === '' and ctype_space($character)) {
				continue;
			}

			if($buffer === '') {
				$bufferLine = $line;
			}

			$buffer .= $character;

		}

		foreach($rows as $key => $row) {
			if($row['kind'] !== self::KEYFRAMES) {
				$rows[$key]['gesture'] = self::getGesture($row, $selectors);
			}
		}

		usort($rows, fn($a, $b) => [$a['line'], $a['segment']] <=> [$b['line'], $b['segment']]);

		$segments = [];

		foreach($rows as $key => $row) {
			$segments[$row['line']] ??= 0;
			$rows[$key]['segment'] = $segments[$row['line']]++;
		}

		return self::resolveAnimations($rows);

	}

	/**
	 * @return array<int, array<string, string|int>>
	 */
	public static function parseScript(string $code, string $file): array {

		$rows = [];
		$lines = explode("\n", $code);

		if(str_ends_with($file, '.php')) {

			foreach($lines as $position => $content) {

				if(preg_match('/style\s*=\s*\\\\?["\'][^"\']*\b(transition|animation)[a-z-]*\s*:\s*([^;"\']*)/i', $content, $match) === 1) {
					$rows[] = self::createScript($file, $position + 1, 'style', $match[0], self::getFirstTime($match[2]));
				}

			}

			return $rows;

		}

		foreach($lines as $position => $content) {

			$found = [];

			if(preg_match('/\.style\.(transition|animation)[A-Za-z]*\s*=/', $content) === 1) {
				$found['style'] = '';
			}

			if(preg_match('/\.animate\s*\(/', $content) === 1) {
				$found['animate'] = '';
			}

			if(preg_match('/\b(duration|transitionDuration|animationDuration)\s*:\s*[0-9.]+/', $content) === 1) {
				$found['duration'] = '';
			}

			if(
				preg_match('/\bset(Timeout|Interval)\s*\(.*,\s*([0-9.]+)\s*\)/', $content, $match) === 1 and
				self::isNearMotion($lines, $position)
			) {
				$found['timeout'] = self::toSeconds($match[2].'ms');
			}

			foreach($found as $pattern => $duration) {
				$rows[] = self::createScript($file, $position + 1, $pattern, trim($content), $duration, count($rows));
			}

		}

		return $rows;

	}

	private static function removeComments(string $css): string {

		return preg_replace_callback('/\/\*.*?\*\//s', fn($match) => str_repeat("\n", substr_count($match[0], "\n")), $css);

	}

	private static function normalize(string $text): string {
		return trim(preg_replace('/\s+/', ' ', $text));
	}

	private static function getKeyframesLevel(array $keyframes): ?int {
		return $keyframes === [] ? NULL : array_key_last($keyframes);
	}

	private static function readDeclaration(array &$rows, array &$keyframes, array $stack, string $buffer, int $line, string $file): void {

		$position = strpos($buffer, ':');

		if($position === FALSE or $stack === []) {
			return;
		}

		$declaration = strtolower(trim(substr($buffer, 0, $position)));
		$value = self::normalize(substr($buffer, $position + 1));
		$value = trim(preg_replace('/\s*!important$/i', '', $value));

		if(preg_match('/^[a-z-]+$/', $declaration) !== 1) {
			return;
		}

		$level = self::getKeyframesLevel($keyframes);

		if($level !== NULL) {

			$property = preg_replace('/^-[a-z]+-/', '', $declaration);

			if(in_array($property, ['animation-timing-function', 'offset'], TRUE) === FALSE) {
				$keyframes[$level]['properties'][$property] = TRUE;
			}

			return;

		}

		$property = preg_replace('/^-[a-z]+-/', '', $declaration);

		if(str_starts_with($property, 'transition') === FALSE and str_starts_with($property, 'animation') === FALSE) {
			return;
		}

		$selector = end($stack)['prelude'];
		$media = self::getMedia($stack);
		$kind = str_starts_with($property, 'transition') ? self::TRANSITION : self::ANIMATION;

		foreach(self::split($value) as $segment => $part) {

			$row = array_merge(self::createRow($file, $line, $segment, $kind, $declaration, $selector, $part, $media), match($property) {
				'transition' => self::readTransition($part),
				'animation' => self::readAnimation($part),
				'transition-property' => ['property' => $part],
				'transition-duration', 'animation-duration' => ['duration' => self::readTime($part)],
				'transition-delay', 'animation-delay' => ['delay' => self::readTime($part)],
				'transition-timing-function', 'animation-timing-function' => ['curve' => $part],
				'animation-name' => ['name' => $part],
				'animation-iteration-count' => ['iteration' => $part],
				default => [],
			});

			$row['literal'] = (int)(self::isLiteralTime($row['duration']) or self::isLiteralTime($row['delay']) or self::isLiteralCurve($row['curve']));

			if($kind === self::TRANSITION) {
				$row['cost'] = self::getCost([$row['property']]);
			}

			$rows[] = $row;

		}

	}

	/**
	 * @return string[]
	 */
	private static function split(string $value): array {

		$parts = [];
		$current = '';
		$depth = 0;

		foreach(str_split($value) as $character) {

			if($character === '(') {
				$depth++;
			} else if($character === ')') {
				$depth = max(0, $depth - 1);
			} else if($character === ',' and $depth === 0) {
				$parts[] = trim($current);
				$current = '';
				continue;
			}

			$current .= $character;

		}

		$parts[] = trim($current);

		return array_values(array_filter($parts, fn($part) => $part !== ''));

	}

	/**
	 * @return string[]
	 */
	private static function getTokens(string $value): array {

		preg_match_all('/[a-z-]+\([^()]*(\([^()]*\)[^()]*)*\)|[^\s]+/i', $value, $matches);

		return $matches[0];

	}

	/**
	 * Comme dans la syntaxe CSS, le premier temps d'un raccourci est la durée, le second le délai.
	 */
	private static function readTransition(string $value): array {

		if($value === 'none') {
			return ['property' => 'none'];
		}

		$row = ['property' => ''];
		$times = 0;

		foreach(self::getTokens($value) as $token) {

			if(self::isTime($token) or self::isCalc($token) or self::isVariable($token, 'duration|delay|time')) {
				$row[$times++ === 0 ? 'duration' : 'delay'] = self::readTime($token);
			} else if(self::isCurve($token) or self::isVariable($token, 'ease|timing|curve')) {
				$row['curve'] = $token;
			} else if(str_starts_with($token, 'var(') === FALSE) {
				$row['property'] = $token;
			}

		}

		if($row['property'] === '') {
			$row['property'] = 'all';
		}

		return $row;

	}

	private static function readAnimation(string $value): array {

		$row = [];
		$times = 0;

		foreach(self::getTokens($value) as $token) {

			if(self::isTime($token) or self::isCalc($token) or self::isVariable($token, 'duration|delay|time')) {
				$row[$times++ === 0 ? 'duration' : 'delay'] = self::readTime($token);
			} else if(self::isCurve($token) or self::isVariable($token, 'ease|timing|curve')) {
				$row['curve'] = $token;
			} else if(str_starts_with($token, 'var(')) {
				continue;
			} else if($token === 'infinite' or is_numeric($token)) {
				$row['iteration'] = $token;
			} else if(in_array(strtolower($token), self::ANIMATION_KEYWORDS, TRUE) === FALSE) {
				$row['name'] = $token;
			}

		}

		return $row;

	}

	private static function isTime(string $token): bool {
		return preg_match('/^-?[0-9]*\.?[0-9]+m?s$/i', $token) === 1;
	}

	private static function isCalc(string $token): bool {
		return preg_match('/^calc\(/i', $token) === 1;
	}

	private static function isCurve(string $token): bool {
		return in_array(strtolower($token), self::CURVES, TRUE) or preg_match('/^(cubic-bezier|steps|linear)\(/i', $token) === 1;
	}

	private static function isVariable(string $token, string $hint): bool {
		return preg_match('/^var\(\s*--[a-z0-9-]*('.$hint.')/i', $token) === 1;
	}

	private static function readTime(string $token): string {
		return self::isTime($token) ? self::toSeconds($token) : $token;
	}

	private static function toSeconds(string $time): string {

		$seconds = str_ends_with(strtolower($time), 'ms') ? (float)$time / 1000 : (float)$time;

		return rtrim(rtrim(number_format($seconds, 9, '.', ''), '0'), '.');

	}

	private static function getFirstTime(string $value): string {

		foreach(self::getTokens($value) as $token) {
			if(self::isTime($token)) {
				return self::toSeconds($token);
			}
		}

		return '';

	}

	private static function isLiteralTime(string $time): bool {

		if(self::isCalc($time)) {
			return preg_match('/(?<![a-z0-9.-])-?[0-9]*\.?[0-9]+m?s\b/i', preg_replace('/var\([^()]*\)/i', '', $time)) === 1;
		}

		return is_numeric($time) and (float)$time > 0;

	}

	private static function isLiteralCurve(string $curve): bool {
		return $curve !== '' and str_starts_with($curve, 'var(') === FALSE;
	}

	/**
	 * @param string[] $properties
	 */
	private static function getCost(array $properties): string {

		$costs = [];

		foreach(array_diff($properties, ['', 'none']) as $property) {

			if(in_array($property, self::LAYOUT, TRUE)) {
				$costs[] = self::COST_LAYOUT;
			} else if(in_array($property, self::PAINT, TRUE) or str_starts_with($property, 'border') or str_starts_with($property, 'background')) {
				$costs[] = self::COST_PAINT;
			} else if(in_array($property, self::COMPOSITE, TRUE)) {
				$costs[] = self::COST_COMPOSITE;
			} else {
				$costs[] = self::COST_UNKNOWN;
			}

		}

		foreach([self::COST_LAYOUT, self::COST_UNKNOWN, self::COST_PAINT, self::COST_COMPOSITE] as $cost) {
			if(in_array($cost, $costs, TRUE)) {
				return $cost;
			}
		}

		return '';

	}

	/**
	 * Une transition se déclare souvent sur l'état de repos et joue quand une autre règle change
	 * l'élément : faute de motif dans son propre sélecteur, le geste se lit sur les sélecteurs de la
	 * même feuille qui prolongent le sien.
	 *
	 * @param string[] $selectors
	 */
	private static function getGesture(array $row, array $selectors): string {

		if($row['value'] === 'none') {
			return 'sans mouvement';
		}

		if($row['iteration'] === 'infinite') {
			return 'boucle';
		}

		$gestures = self::findGestures($row['selector']);

		if($gestures === [] and $row['kind'] === self::TRANSITION) {

			foreach(array_map('trim', explode(',', $row['selector'])) as $part) {

				foreach($selectors as $other) {

					foreach(array_map('trim', explode(',', $other)) as $otherPart) {

						if(
							$otherPart !== $part and
							str_starts_with($otherPart, $part) and
							in_array($otherPart[strlen($part)], [':', '.', '['], TRUE)
						) {
							$gestures += self::findGestures(substr($otherPart, strlen($part)), TRUE);
						}

					}

				}

			}

		}

		if($gestures === []) {
			return 'non classé';
		}

		ksort($gestures);

		return implode('+', array_keys($gestures));

	}

	/**
	 * @return array<string, TRUE>
	 */
	private static function findGestures(string $selector, bool $leading = FALSE): array {

		$gestures = [];

		foreach(self::GESTURES as $motif => $gesture) {

			$position = strpos($selector, $motif);

			if($position === FALSE or ($leading and $position !== 0)) {
				continue;
			}

			$next = $selector[$position + strlen($motif)] ?? '';

			if(preg_match('/[a-z0-9_-]/i', $next) === 0) {
				$gestures[$gesture] = TRUE;
			}

		}

		return $gestures;

	}

	private static function isNearMotion(array $lines, int $position): bool {

		$start = max(0, $position - 3);
		$window = implode("\n", array_slice($lines, $start, $position + 4 - $start));

		return preg_match('/transition|animation(?!Frame)/i', $window) === 1;

	}

	/**
	 * Les animations nomment des images clés qui vivent souvent dans une autre feuille : les
	 * propriétés animées se résolvent sur l'ensemble des lignes reçues.
	 *
	 * @param array<int, array<string, string|int>> $rows
	 * @return array<int, array<string, string|int>>
	 */
	private static function resolveAnimations(array $rows): array {

		$properties = [];

		foreach($rows as $row) {
			if($row['kind'] === self::KEYFRAMES) {
				$properties[$row['name']] = $row['property'];
			}
		}

		foreach($rows as $key => $row) {

			if($row['kind'] !== self::ANIMATION or $row['name'] === '' or isset($properties[$row['name']]) === FALSE) {
				continue;
			}

			$rows[$key]['property'] = $properties[$row['name']];
			$rows[$key]['cost'] = self::getCost(explode(',', $properties[$row['name']]));

		}

		return $rows;

	}

	private static function getMedia(array $stack): string {

		$preludes = array_filter(array_column($stack, 'prelude'), fn($prelude) => str_starts_with($prelude, '@'));

		return implode(' · ', $preludes);

	}

	private static function createRow(string $file, int $line, int $segment, string $kind, string $declaration, string $selector, string $value, string $media = ''): array {

		return [
			'file' => $file,
			'line' => $line,
			'segment' => $segment,
			'kind' => $kind,
			'declaration' => $declaration,
			'selector' => $selector,
			'media' => $media,
			'property' => '',
			'name' => '',
			'duration' => '',
			'delay' => '',
			'curve' => '',
			'iteration' => '',
			'cost' => '',
			'gesture' => '',
			'literal' => 0,
			'value' => $value,
		];

	}

	private static function createKeyframes(string $file, array $keyframes, string $media): array {

		$properties = array_keys($keyframes['properties']);

		$row = self::createRow($file, $keyframes['line'], 0, self::KEYFRAMES, '@keyframes', $keyframes['name'], '', $media);
		$row['name'] = $keyframes['name'];
		$row['property'] = implode(',', $properties);
		$row['cost'] = self::getCost($properties);

		return $row;

	}

	private static function createScript(string $file, int $line, string $pattern, string $value, string $duration, int $segment = 0): array {

		$row = self::createRow($file, $line, $segment, self::SCRIPT, $pattern, '', mb_substr($value, 0, 200));
		$row['duration'] = $duration;
		$row['literal'] = (int)self::isLiteralTime($duration);

		return $row;

	}

}
?>
