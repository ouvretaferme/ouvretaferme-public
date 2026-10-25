<?php
namespace dev;

/**
 * Une route d'écriture de l'API construit son élément avec l'origine `api`. `ModuleApi::create()` et
 * `update()` posent `new \PropertyList('create'|'update', 'api')` ; une ressource qui remplace leur
 * build par le sien pose la même chose, sinon `$l->origin` vaut NULL dans les étapes du `.e.php` :
 * une règle écrite pour les écrans s'applique alors à l'API, et une tolérance écrite pour l'API ne
 * s'y applique pas. Modèle : `toolbox\Equipment`, dont deux étapes lisent cette origine.
 *
 * Deux règles, parce qu'un troisième argument absent et une origine absente ne se corrigent pas du
 * même geste.
 *
 * Lancement : dev/test package=dev file=ApiBuildOriginSentinel
 */
class ApiBuildOriginSentinelTest extends SentinelTest {

	const LIST = [];

	const ORIGIN = [];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			if(preg_match('#/api/[^/]+\.a\.php$#', $file) !== 1) {
				continue;
			}

			$this->addFound('list', $file, $this->findBuildWithoutList($tokens));
			$this->addFound('origin', $file, $this->findListWithoutApi($tokens));

		}

	}

	/** @return int[] */
	private function findBuildWithoutList(array $tokens): array {

		$lines = [];
		$count = count($tokens);

		for($index = 1; $index < $count; $index++) {

			if(
				$tokens[$index][0] !== T_STRING or
				$tokens[$index][1] !== 'build' or
				$tokens[$index - 1][0] !== T_OBJECT_OPERATOR or
				($tokens[$index + 1][1] ?? '') !== '('
			) {
				continue;
			}

			if(count(self::readArguments($tokens, $index + 1)) < 3) {
				$lines[] = $tokens[$index][2];
			}

		}

		return $lines;

	}

	/** @return int[] */
	private function findListWithoutApi(array $tokens): array {

		$lines = [];
		$count = count($tokens);

		for($index = 0; $index < $count; $index++) {

			if(
				in_array($tokens[$index][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE) === FALSE or
				str_ends_with($tokens[$index][1], 'PropertyList') === FALSE or
				($tokens[$index + 1][1] ?? '') !== '('
			) {
				continue;
			}

			$arguments = self::readArguments($tokens, $index + 1);

			if(preg_match('#^(origin:)?[\'"]api[\'"]$#', $arguments[1] ?? '') !== 1) {
				$lines[] = $tokens[$index][2];
			}

		}

		return $lines;

	}

	/**
	 * Le texte de chaque argument de l'appel dont la parenthèse ouvrante est à `$open`, recousu sans
	 * les espaces. Les virgules des tableaux, des appels et des closures imbriqués ne comptent pas.
	 *
	 * @return string[]
	 */
	private static function readArguments(array $tokens, int $open): array {

		$arguments = [];
		$current = '';
		$depth = 0;
		$count = count($tokens);

		for($index = $open; $index < $count; $index++) {

			$text = $tokens[$index][1];

			if(in_array($text, ['(', '[', '{'], TRUE)) {

				$depth++;

				if($depth === 1) {
					continue;
				}

			} else if(in_array($text, [')', ']', '}'], TRUE)) {

				$depth--;

				if($depth === 0) {

					if($current !== '') {
						$arguments[] = $current;
					}

					return $arguments;

				}

			} else if($text === ',' and $depth === 1) {

				$arguments[] = $current;
				$current = '';

				continue;

			}

			$current .= $text;

		}

		return $arguments;

	}

	public function testDetectors(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			$data->e->build($properties, $data->body);
			$data->e->build($properties, $data->body, new \PropertyList('create', 'api'));
			$eStock->build(['quantity', 'comment'], $data->body, new \PropertyList('create', 'api'));
			$data->e->build($properties, $this->convertProperties($data, $properties, $l), $l);
			$l = new \PropertyList('update');
			$l = new \PropertyList('update', 'api');
			$l = new \PropertyList($for, 'api');
			$l = new \PropertyList('create', origin: 'api');
			$l = new \PropertyList();
			PropertyList::class;
			PHP);

		$this->assertEquals([2], $this->findBuildWithoutList($tokens), 'le build sans liste est trouvé, et les virgules d\'un tableau ou d\'un appel imbriqué ne comptent pas pour un argument');
		$this->assertEquals([6, 10], $this->findListWithoutApi($tokens), 'la liste sans origine api est trouvée, le nom nommé et la forme à zéro argument comprises');

	}

	public function testBuildCarriesAList(): void {
		$this->assertRatchet('list', self::LIST, 'build() d\'une ressource d\'API sans PropertyList, le troisième argument est à poser');
	}

	public function testListCarriesTheApiOrigin(): void {
		$this->assertRatchet('origin', self::ORIGIN, 'PropertyList d\'une ressource d\'API sans l\'origine \'api\', à ajouter en second argument');
	}

}
?>
