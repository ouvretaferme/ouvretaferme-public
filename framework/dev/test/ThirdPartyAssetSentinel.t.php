<?php
namespace dev;

/**
 * Une ressource chargée depuis un tiers porte une version exacte et une empreinte `integrity`.
 *
 * Sans version exacte, le tiers sert la dernière publiée, que personne n'a relue ; sans empreinte,
 * le navigateur exécute ce que le tiers sert, y compris après une compromission. La copie locale
 * (`\Asset::lib()`) reste la forme préférée : elle ne dépend de personne.
 *
 * Le détecteur lit les URL `http(s)://` de fichiers `.js` ou `.css` passées à `\Asset::jsUrl()` ou `\Asset::cssUrl()` dans les
 * `.php`, et les chaînes d'URL de `.js` ou `.css` dans les `.js` hors `lib/`. Il ne voit pas une URL
 * construite par concaténation.
 *
 * Lancement : dev/test package=dev file=ThirdPartyAssetSentinel
 */
class ThirdPartyAssetSentinelTest extends SentinelTest {

	/**
	 * L'existant, une entrée par URL, avec ce qui lui manque et pourquoi.
	 *
	 * `\Asset::importJson()` ne transmet que le chemin d'une ressource à la navigation ajax : une
	 * empreinte posée en argument ne protège donc que le chargement complet de la page.
	 */
	const THIRD_PARTY = [
		'framework/dev/static/js/doc.js:https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js' => 'version flottante et sans empreinte : le fichier n\'est chargé par aucune page, sa suppression est proposée',
		'framework/dev/static/js/doc.js:https://unpkg.com/swagger-ui-dist@5/swagger-ui.css' => 'version flottante et sans empreinte : le fichier n\'est chargé par aucune page, sa suppression est proposée',
		'production/map/ui/Mapbox.u.php:https://api.mapbox.com/mapbox-gl-js/v3.31.0/mapbox-gl.css' => 'sans empreinte : la navigation ajax la perdrait, en attente du cadriciel',
		'production/map/ui/Mapbox.u.php:https://api.mapbox.com/mapbox-gl-js/v3.31.0/mapbox-gl.js' => 'sans empreinte : la navigation ajax la perdrait, en attente du cadriciel',
	];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			foreach(self::findPhpAssets($tokens) as $url => $line) {
				$this->addFoundKey('thirdParty', $file, $url, $line);
			}

		}

		foreach(SentinelLib::getFiles('.js') as $file => $path) {

			if(str_contains($file, '/lib/')) {
				continue;
			}

			foreach(self::findJsAssets(file_get_contents($path)) as $url => $line) {
				$this->addFoundKey('thirdParty', $file, $url, $line);
			}

		}

	}

	public function testThirdPartyAssets(): void {

		$this->assertDeclared('thirdParty', self::THIRD_PARTY, 'ressource tierce sans version exacte ou sans empreinte : la servir par \Asset::lib(), ou lui donner une version x.y.z et un argument integrity (openssl dgst -sha384 -binary | openssl base64 -A) avec crossorigin');

	}

	/**
	 * Les URL tierces de `\Asset::jsUrl()` et `\Asset::cssUrl()` à qui manque une version exacte ou
	 * un argument `integrity`.
	 *
	 * @return array<string, int>
	 */
	public static function findPhpAssets(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, $text]) {

			if(
				$type !== T_STRING or
				in_array($text, ['jsUrl', 'cssUrl'], TRUE) === FALSE or
				($tokens[$i - 1][0] ?? NULL) !== T_DOUBLE_COLON or
				($tokens[$i + 1][0] ?? NULL) !== '(' or
				($tokens[$i + 2][0] ?? NULL) !== T_CONSTANT_ENCAPSED_STRING
			) {
				continue;
			}

			$url = substr($tokens[$i + 2][1], 1, -1);

			// Une URL sans fichier est une ouverture de connexion (`rel="preconnect"`), pas une ressource.
			if(preg_match('#^https?://.+\.(js|css)$#', $url) === 0) {
				continue;
			}

			$close = SentinelLib::getClosing($tokens, $i + 1);
			$hasIntegrity = FALSE;

			for($j = $i + 3; $j < $close; $j++) {

				if($tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING and substr($tokens[$j][1], 1, -1) === 'integrity') {
					$hasIntegrity = TRUE;
				}

			}

			if($hasIntegrity === FALSE or self::hasExactVersion($url) === FALSE) {
				$found[$url] = $tokens[$i][2];
			}

		}

		return $found;

	}

	/**
	 * Les URL tierces de `.js` ou `.css` écrites dans un script, à qui manque une version exacte ou
	 * une empreinte `integrity` posée dans le même fichier.
	 *
	 * @return array<string, int>
	 */
	public static function findJsAssets(string $code): array {

		$found = [];
		$hasIntegrity = str_contains($code, 'integrity');

		preg_match_all('#[\'"`](https?://[^\'"`\s]+\.(?:js|css))[\'"`]#', $code, $matches, PREG_OFFSET_CAPTURE);

		foreach($matches[1] as [$url, $offset]) {

			if($hasIntegrity === FALSE or self::hasExactVersion($url) === FALSE) {
				$found[$url] = substr_count($code, "\n", 0, $offset) + 1;
			}

		}

		return $found;

	}

	private static function hasExactVersion(string $url): bool {
		return preg_match('#[@/]v?\d+\.\d+\.\d+[/]#', $url) === 1;
	}

	public function testDetectors(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			\Asset::jsUrl('https://cdn.example.org/npm/chart.js');
			\Asset::jsUrl('https://cdn.example.org/lib/v1.2.3/lib.js');
			\Asset::cssUrl('https://cdn.example.org/lib/v1.2.3/lib.css', ['integrity' => 'sha384-x', 'crossorigin' => 'anonymous']);
			\Asset::jsUrl('https://cdn.example.org/lib@1/lib.js', ['integrity' => 'sha384-x']);
			\Asset::js('main', 'chart.js');
			PHP);

		$this->assertEquals([
			'https://cdn.example.org/npm/chart.js' => 2,
			'https://cdn.example.org/lib/v1.2.3/lib.js' => 3,
			'https://cdn.example.org/lib@1/lib.js' => 5,
		], self::findPhpAssets($tokens), 'le détecteur PHP ne sépare pas la ressource fautive de ses voisines');

		$code = <<<'JS'
			const URL = 'https://unpkg.com/lib@5/lib.js';
			const PINNED = 'https://unpkg.com/lib@5.1.0/lib.css';
			JS;

		$this->assertEquals([
			'https://unpkg.com/lib@5/lib.js' => 1,
			'https://unpkg.com/lib@5.1.0/lib.css' => 2,
		], self::findJsAssets($code), 'le détecteur JS laisse passer une URL sans empreinte');

		$this->assertEquals([], self::findJsAssets("script.integrity = 'sha384-x';\nconst PINNED = 'https://unpkg.com/lib@5.1.0/lib.css';"), 'le détecteur JS retient une URL figée et signée');

	}

}
?>
