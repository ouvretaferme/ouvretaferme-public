<?php
namespace dev;

/**
 * La valeur d'un attribut HTML concaténée en PHP s'ouvre par un guillemet : `post-id="'.$e['id'].'"`,
 * jamais `post-id='.$e['id'].'"`.
 *
 * Sans guillemet ouvrant, la valeur s'arrête à la première espace et emporte le guillemet fermant :
 * le navigateur poste « 442782" ». Le cast (int) de PHP lisait 442782 sans rien dire ; depuis que les
 * lecteurs typés refusent un nombre illisible (`var_filter()`, `framework/core/Function.php`), l'action
 * ne trouve plus son élément. Sept boutons sont tombés ainsi le même jour, dont « Arrondir » de la
 * caisse, sans que rien ne lève.
 *
 * Est relevée toute chaîne littérale qui finit par `nom=` à l'intérieur d'une balise, suivie d'une
 * concaténation. L'inverse, un guillemet ouvrant sans guillemet fermant, ne se voit pas ainsi : la valeur
 * avale alors l'attribut suivant, ce qui casse l'écran au premier regard.
 *
 * Lancement : dev/test package=dev file=HtmlAttributeSentinel
 */
class HtmlAttributeSentinelTest extends \Test {

	/**
	 * Les lignes des chaînes qui ouvrent un attribut sans guillemet avant une concaténation.
	 *
	 * @return int[]
	 */
	private function scan(string $code): array {

		$lines = [];
		$tokens = token_get_all($code);
		$count = count($tokens);

		foreach($tokens as $position => $token) {

			if(is_array($token) === FALSE or $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}

			$string = substr($token[1], 1, -1);

			if(preg_match('/<[a-zA-Z][^<>]*\s[a-zA-Z][\w:.-]*=\z/', $string) !== 1) {
				continue;
			}

			$next = $position + 1;

			while($next < $count and is_array($tokens[$next]) and in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT], TRUE)) {
				$next++;
			}

			if(($tokens[$next] ?? NULL) === '.') {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/** Le scanner trouve l'attribut ouvert sans guillemet, et rien de ce qui est légitime. */
	public function testScannerFindsUnquotedAttribute(): void {

		$lines = $this->scan(<<<'PHP'
			<?php
			$h = '<a data-ajax="/selling/sale:doRound" post-id='.$eSale['id'].'" class="btn">';
			$h = "<a post-id=".$eSale['id'].'>';
			$h = '<a data-ajax="/selling/sale:doRound" post-id="'.$eSale['id'].'" class="btn">';
			$h = '<a href="/vente?id='.$eSale['id'].'">';
			$h = 'total='.$total;
			$h = '<div class="x">a=b</div>'.$x;
			$h = '<input value='.encode($value).'>';
			PHP);

		$this->assertEquals([2, 3, 8], $lines, 'les trois attributs ouverts sans guillemet sont trouvés, rien d\'autre');

	}

	public function testNoUnquotedAttribute(): void {

		$found = [];

		foreach(SentinelLib::getFiles('.php') as $relative => $path) {
			foreach($this->scan(file_get_contents($path)) as $line) {
				$found[] = $relative.':'.$line;
			}
		}

		$this->assertEquals([], $found, 'valeur d\'attribut HTML concaténée sans guillemet ouvrant, à écrire nom="\'.$valeur.\'" : '.implode(', ', $found));

	}

}
?>
