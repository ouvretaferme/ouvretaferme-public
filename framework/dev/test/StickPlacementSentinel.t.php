<?php
namespace dev;

/**
 * Un placement de `data-dropdown` que `Lime.Stick.move()` ne sait pas lire est ignoré sans rien
 * signaler.
 *
 * La grammaire est celle du corps de `move()` (`framework/util/static/js/lime.js`), et elle se lit
 * en trois endroits : un placement commençant par `auto` est **remplacé en entier** par `top` ou
 * `bottom` selon la place restante, donc tout ce qui le suit est perdu ; `right` part dans une
 * branche à lui, qui ne lit aucune fin ; sinon la verticale doit commencer par `top`, `bottom` ou
 * `center`, et la fin, quand il y en a une, être `start`, `center` ou `end`. Une fin inconnue
 * tombe dans le dernier `else`, qui choisit `start` ou `end` selon la moitié d'écran où se trouve
 * l'élément : le placement écrit n'est pas refusé, il est remplacé par un autre, et rien ne le dit,
 * ni à l'écriture, ni au rendu, ni à la relecture.
 *
 * C'est pour cela que le contrat se garde ici : celui qui écrit `bottom-left` travaille dans un
 * autre package, n'a aucune raison de lire `lime.js`, et obtient un menu qui se place, donc qui a
 * l'air de marcher.
 *
 * ⚠️ Ce que cette sentinelle ne voit pas : un placement construit, `data-dropdown="'.$dropdown.'"`.
 * Il y en a trois au 2026-10-01, tous dans `commercialisation/selling/ui/Sale.u.php`, et les trois
 * `match` qui les remplissent ne rendent que `bottom-end` et `bottom-start`. Lire à travers une
 * concaténation demanderait de suivre la variable jusqu'à sa source, ce qu'un relevé lexical ne
 * fait pas.
 *
 * Lancement : dev/test package=dev file=StickPlacementSentinel
 */
class StickPlacementSentinelTest extends \Test {

	/**
	 * Les verticales que `move()` honore, et les fins qu'elle reconnaît.
	 */
	const VERTICAL = ['top', 'bottom', 'center'];
	const HORIZONTAL = ['start', 'center', 'end'];

	/**
	 * Les placements que le dépôt porte encore et que `move()` ne sait pas lire, par chemin et
	 * valeur, avec ce que le repli en fait à leur place.
	 *
	 * La raison dit le repli et non le lot qui corrigera : un lot se referme, et une raison qui le
	 * nomme devient fausse sans que la pose ait bougé. Le choix du remplacement n'est pas mécanique,
	 * `bottom-left` voulant dire « à gauche » quand la fin qui le rend est `start` ou `end` selon le
	 * sens de lecture voulu, donc il demande de regarder l'écran. Une entrée se retire par celui qui
	 * corrige la pose, dans le même commit.
	 *
	 * Un repli qui tombe aujourd'hui sur le placement voulu ne rend pas la pose inoffensive : il
	 * dépend de la moitié d'écran où se trouve l'élément, donc il change le jour où l'élément
	 * déménage, et le placement écrit continue de dire autre chose que ce qui s'applique.
	 */
	const ALLOWED = [
		'accounting/preaccounting/ui/Preaccounting.u.php bottom-down' => 'repli sur start ou end selon la moitié d\'écran, le bouton de téléchargement du FEC n\'ayant pas de côté voulu écrit',
		'base/media/ui/Media.u.php top-right' => 'repli sur start ou end selon la moitié d\'écran, « right » n\'étant une fin pour aucune branche de move()',
		'base/structure/ui/Menu.u.php bottom-left' => 'repli qui rend start pour un lien de la moitié gauche, donc rien ne se voit aujourd\'hui, et le menu est partagé par plusieurs chantiers',
	];

	public function testEveryPlacementIsOneStickReads(): void {

		$found = [];

		foreach($this->getSources() as $file => $source) {

			foreach(self::findPlacements($source) as [$line, $placement]) {

				if(self::isRead($placement)) {
					continue;
				}

				$key = $file.' '.$placement;

				if(isset(self::ALLOWED[$key]) === FALSE) {
					$found[] = $file.':'.$line.' ('.$placement.')';
				}

			}

		}

		$this->assertEquals([], $found, 'placement de data-dropdown que Lime.Stick.move() ne lit pas, donc remplacé en silence par start ou end selon la moitié d\'écran : '.implode(', ', $found).' · le code fait foi, la pose se corrige, ou sa raison se déclare dans ALLOWED');

	}

	public function testEveryAllowedPlacementIsStillPosted(): void {

		$posted = [];

		foreach($this->getSources() as $file => $source) {

			foreach(self::findPlacements($source) as [, $placement]) {
				$posted[] = $file.' '.$placement;
			}

		}

		$stale = array_values(array_diff(array_keys(self::ALLOWED), $posted));

		$this->assertEquals([], $stale, 'placements déclarés dans ALLOWED que le dépôt ne porte plus : '.implode(', ', $stale).' · le code fait foi, la déclaration se retire, et elle part avec le commit qui corrige la pose');

	}

	/**
	 * Le détecteur se prouve sur du code en ligne : sans cela, un relevé vide rendrait la règle
	 * verte pour de mauvaises raisons.
	 */
	public function testDetectorReadsTheGrammar(): void {

		$source = '<a data-dropdown="bottom-left"></a>'."\n".'<a data-dropdown="bottom-end"></a>';

		$this->assertEquals([[1, 'bottom-left'], [2, 'bottom-end']], self::findPlacements($source), 'le détecteur relève les deux poses et leur ligne');

		foreach(['bottom', 'bottom-start', 'bottom-center', 'bottom-end', 'top-end', 'center-center', 'right', 'auto'] as $placement) {
			$this->assertTrue(self::isRead($placement), $placement.' est lu par move()');
		}

		foreach(['bottom-left', 'top-right', 'bottom-down', 'auto-end', 'left', 'bottom-start-end'] as $placement) {
			$this->assertFalse(self::isRead($placement), $placement.' n\'est pas lu par move()');
		}

	}

	/**
	 * @return \Generator<string, string>
	 */
	private function getSources(): \Generator {

		foreach(['.php', '.js'] as $suffix) {

			foreach(SentinelLib::getFiles($suffix) as $file => $path) {

				if(fnmatch('*Sentinel*.t.php', basename($file))) {
					continue;
				}

				yield $file => file_get_contents($path);

			}

		}

	}

	/**
	 * Les placements littéraux d'une source, chacun avec sa ligne.
	 *
	 * La garde `(?<!\[)` écarte un sélecteur CSS écrit dans une chaîne, `[data-dropdown="bottom"]`,
	 * qui nomme un placement sans en poser un.
	 *
	 * @return array<int, array{0: int, 1: string}>
	 */
	private static function findPlacements(string $source): array {

		$placements = [];

		foreach(explode("\n", $source) as $index => $line) {

			if(preg_match_all('/(?<!\[)data-dropdown="([a-z-]+)"/', $line, $matches) === 0) {
				continue;
			}

			foreach($matches[1] as $placement) {
				$placements[] = [$index + 1, $placement];
			}

		}

		return $placements;

	}

	private static function isRead(string $placement): bool {

		// `move()` remplace tout placement commençant par `auto` par `top` ou `bottom` : une fin
		// écrite derrière n'est pas lue, elle est jetée avec le reste.
		if($placement === 'auto') {
			return TRUE;
		}

		// `right` ne passe pas par la lecture de la fin, il a sa branche.
		if($placement === 'right') {
			return TRUE;
		}

		[$vertical, $suffix] = array_pad(explode('-', $placement, 2), 2, '');

		if(in_array($vertical, self::VERTICAL, TRUE) === FALSE) {
			return FALSE;
		}

		return match($suffix) {
			'' => TRUE,
			default => in_array($suffix, self::HORIZONTAL, TRUE),
		};

	}

}
