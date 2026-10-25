<?php
namespace learning;

/**
 * Test-sentinelle : les gabarits des pages de formations d'un réseau.
 *
 * Un gabarit tient en quatre endroits qui ne se voient pas l'un l'autre : son nom dans la liste
 * fermée `LearningSetting::DESIGNS`, que le contrôle de `structure\Community::customDesign` lit ; sa
 * feuille `static/css/design-<nom>.css`, que `CustomizeUi::getStyles()` charge ; son libellé au
 * describer de `structure\Community`, que la barre propose ; ses matières dans
 * `LearningSetting::DESIGN_MATTERS`, sur lesquelles se calculent les encres. Un nom sans feuille
 * rend une page sans gabarit, une feuille sans nom n'est jamais chargée, une matière fausse rend un
 * texte qui ne se lit pas.
 *
 * Et une feuille de gabarit est chargée sur toute la session, en navigation ajax : elle ne vise que
 * ses deux portées, `.learning-public` et `.learning-doc`, sous son propre nom, et n'habille les
 * composants du socle que par leurs jetons (section #design-themeTokens du design system).
 *
 * Inventaire seul, sans écriture en base. La liste de référence est `DESIGNS` : un écart se corrige
 * du côté de la feuille, du describer ou des matières, jamais en retirant un nom pour verdir.
 *
 * Lancement : dev/test package=learning file=DesignSentinel
 */
class DesignSentinelTest extends \Test {

	const DIRECTORY = 'ouvretaferme/learning/static/css';

	/**
	 * Les pseudo-éléments qu'un gabarit peut poser, relatifs à sa portée : ceux que le socle laisse
	 * libres sur le bandeau de la liste (plan A3a, §4). `.community-header-media::after`
	 * et `.community-header-tabs .tab-item::after` sont au socle, `template-community.css`.
	 */
	const PSEUDO_ELEMENTS = [
		'.community-header::after',
		'.community-header h1::before',
		'.community-header h1::after',
	];

	/**
	 * Le bandeau de la liste du réseau, sous la portée `public`.
	 */
	const HEADER = '.community-header';

	/**
	 * Les jetons d'où un fond de gabarit peut dériver sans que la feuille les définisse : le fond
	 * choisi, adouci, sous ses deux noms, la teinte que `CustomizeUi::getStyles()` en tire, la couleur
	 * du réseau, et la surface du socle, blanche.
	 */
	const DERIVED = ['--learning-background', '--learning-background-chosen', '--learning-tint', '--primary', '--background-surface'];

	/**
	 * Les mots qu'une valeur de fond dérivée peut porter hors de ses `var()` : les fonctions de mélange
	 * et de dégradé, leurs directions et formes, la répétition, la taille et la boite, le blanc et
	 * `transparent`.
	 */
	const KEYWORDS = [
		'color-mix', 'in', 'srgb', 'oklab', 'oklch',
		'linear-gradient', 'radial-gradient', 'conic-gradient', 'repeating-linear-gradient', 'repeating-radial-gradient', 'repeating-conic-gradient',
		'to', 'top', 'bottom', 'left', 'right', 'center', 'at', 'from', 'circle', 'ellipse', 'closest-side', 'closest-corner', 'farthest-side', 'farthest-corner',
		'no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'space', 'round', 'fixed', 'scroll', 'local', 'padding-box', 'border-box', 'content-box', 'cover', 'contain', 'auto', 'calc',
		'none', 'transparent', 'white', 'inherit', 'initial', 'unset',
	];

	/**
	 * Les feuilles du socle où un jeton posé par un gabarit doit être lu, et où un pseudo-élément
	 * de la liste ci-dessus ne doit pas être occupé.
	 */
	const BASE_SHEETS = [
		'ouvretaferme/main/static/css/*.css',
		'base/block/static/css/read.css',
		'ouvretaferme/learning/static/css/learning.css',
	];

	public function testSheets(): void {

		$sheets = array_keys($this->sheets());

		$missing = array_diff(LearningSetting::DESIGNS, $sheets);
		$orphan = array_diff($sheets, LearningSetting::DESIGNS);

		$this->assertEquals([], array_values($missing), 'gabarit sans feuille, écrire '.self::DIRECTORY.'/design-<nom>.css : '.implode(', ', $missing));
		$this->assertEquals([], array_values($orphan), 'feuille jamais chargée, ajouter son nom à LearningSetting::DESIGNS avec son libellé et ses matières : '.implode(', ', $orphan));

	}

	public function testLabels(): void {

		$labels = array_keys(\structure\CommunityUi::p('customDesign')->values);

		$missing = array_diff(LearningSetting::DESIGNS, $labels);
		$orphan = array_diff($labels, LearningSetting::DESIGNS);

		$this->assertEquals([], array_values($missing), 'gabarit sans libellé, l\'ajouter au describer de structure\\CommunityUi (customDesign) : '.implode(', ', $missing));
		$this->assertEquals([], array_values($orphan), 'libellé d\'un gabarit absent de LearningSetting::DESIGNS, la barre le proposerait et le contrôle le refuserait : '.implode(', ', $orphan));

	}

	public function testSelectors(): void {

		$outside = [];
		$components = [];
		$pseudo = [];

		foreach($this->sheets() as $design => $css) {

			foreach(self::rules($css) as [$selector]) {

				$relative = self::relative($selector, $design);

				if($relative === NULL) {
					$outside[] = $design.' « '.$selector.' »';
					continue;
				}

				if(preg_match('/\.(util-|btn|tab-|editor-)/', $relative)) {
					$components[] = $design.' « '.$selector.' »';
				}

				if(preg_match('/::?(before|after)\b/', $relative)) {

					$normalized = trim(preg_replace('/:{1,2}(before|after)\b/', '::$1', $relative));

					if(in_array($normalized, self::PSEUDO_ELEMENTS, TRUE) === FALSE) {
						$pseudo[] = $design.' « '.$selector.' »';
					}

				}

			}

		}

		$this->assertEquals([], $outside, 'sélecteur hors des portées du gabarit, il s\'appliquerait ailleurs dans la session : le faire commencer par .learning-public[data-learning-design="<nom>"] ou .learning-doc[data-learning-design="<nom>"] : '.implode(' · ', $outside));
		$this->assertEquals([], $components, 'un gabarit vise un composant du socle, qu\'il doit habiller par ses jetons seulement : '.implode(' · ', $components));
		$this->assertEquals([], $pseudo, 'pseudo-élément hors de ceux que le socle laisse libres (PSEUDO_ELEMENTS) : '.implode(' · ', $pseudo));

	}

	public function testPseudoElementsFree(): void {

		$taken = [];

		foreach($this->baseSheets() as $file => $css) {

			foreach(self::rules($css) as [$selector, , $line]) {

				$normalized = trim(preg_replace('/:{1,2}(before|after)\b/', '::$1', $selector));

				if(in_array($normalized, self::PSEUDO_ELEMENTS, TRUE)) {
					$taken[] = $file.':'.$line.' « '.$selector.' »';
				}

			}

		}

		$this->assertEquals([], $taken, 'le socle occupe un pseudo-élément que les gabarits posent : le retirer de PSEUDO_ELEMENTS et réécrire les gabarits qui s\'en servent, ou poser la règle du socle ailleurs : '.implode(' · ', $taken));

	}

	public function testDeclarations(): void {

		$important = [];
		$background = [];
		$cycle = [];

		foreach($this->sheets() as $design => $css) {

			foreach(self::rules($css) as [$selector, $body]) {

				if(str_contains($body, '!important')) {
					$important[] = $design.' « '.$selector.' »';
				}

				$isRoot = (self::relative($selector, $design) === '');

				if($isRoot and preg_match('/(^|;)\s*background(-color)?\s*:/', $body)) {
					$background[] = $design.' « '.$selector.' »';
				}

				if(
					preg_match('/(?:^|;)\s*--learning-background\s*:([^;]+)/', $body, $match) and
					self::isCyclic($match[1])
				) {
					$cycle[] = $design.' « '.$selector.' »';
				}

			}

		}

		$this->assertEquals([], $important, 'un gabarit ne force rien, il pose des jetons et sa signature : retirer !important de '.implode(' · ', $important));
		$this->assertEquals([], $background, 'le fond d\'un gabarit passe par --learning-background, que les encres lisent dans DESIGN_MATTERS ; sur la racine de la portée, la signature ne pose que background-image : '.implode(' · ', $background));
		$this->assertEquals([], $cycle, 'une propriété qui se lit elle-même forme un cycle, la valeur est invalide et la portée reste sans fond : mêler le fond choisi par var(--learning-background-chosen) dans '.implode(' · ', $cycle));

	}

	public function testTokensRead(): void {

		$read = [];

		foreach($this->baseSheets() as $css) {
			preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', $css, $matches);
			$read += array_fill_keys($matches[1], TRUE);
		}

		$unread = [];

		foreach($this->sheets() as $design => $css) {

			foreach(self::rules($css) as [, $body]) {

				preg_match_all('/(?:^|;)\s*(--[a-z0-9-]+)\s*:/i', $body, $matches);

				foreach($matches[1] as $token) {

					if(isset($read[$token]) === FALSE) {
						$unread[$design.' '.$token] = $design.' '.$token;
					}

				}

			}

		}

		$this->assertEquals([], array_values($unread), 'jeton posé par un gabarit qu\'aucune feuille du socle ne lit, il n\'habille rien : le lire au composant avec un repli égal à sa valeur, ou le retirer de la feuille : '.implode(', ', $unread));

	}

	/**
	 * Les couleurs que la feuille fixe sous le texte, par portée, comparées à `DESIGN_MATTERS`. Un
	 * fond non déclaré est celui que choisit le réseau, une surface non déclarée le blanc du socle ;
	 * une surface transparente ou lue sur le fond laisse voir le fond choisi.
	 */
	public function testMatters(): void {

		$wrong = [];

		foreach($this->sheets() as $design => $css) {

			foreach(['public', 'doc'] as $scope) {

				$tokens = ['--learning-background' => NULL, '--background-surface' => NULL];

				foreach(self::rules($css) as [$selector, $body]) {

					$applies = FALSE;

					foreach(self::split($selector) as $part) {
						if($part === self::scope($scope, $design)) {
							$applies = TRUE;
						}
					}

					if($applies === FALSE) {
						continue;
					}

					foreach(array_keys($tokens) as $token) {
						if(preg_match('/(?:^|;)\s*'.preg_quote($token, '/').'\s*:([^;]+)/', $body, $match)) {
							$tokens[$token] = trim($match[1]);
						}
					}

				}

				$found = array_merge(
					($tokens['--learning-background'] === NULL) ? ['{background}'] : self::colors($tokens['--learning-background']),
					($tokens['--background-surface'] === NULL) ? ['#ffffff'] : self::colors($tokens['--background-surface'])
				);
				$found = array_values(array_unique($found));
				sort($found);

				if(isset(LearningSetting::DESIGN_MATTERS[$design][$scope])) {
					$expected = array_values(array_unique(array_map('strtolower', LearningSetting::DESIGN_MATTERS[$design][$scope])));
				} else {
					$expected = ['#ffffff', '{background}'];
				}

				sort($expected);

				if($found !== $expected) {
					$wrong[] = $design.' '.$scope.' : la feuille fixe '.implode(', ', $found).', DESIGN_MATTERS dit '.implode(', ', $expected);
				}

			}

		}

		$this->assertEquals([], $wrong, 'les encres se calculent sur DESIGN_MATTERS : la feuille fait foi pour la couleur qu\'elle peint, la corriger dans DESIGN_MATTERS : '.implode(' · ', $wrong));

	}

	/**
	 * Une police de titre de gabarit (`LearningSetting::DESIGN_FONTS`) chargée sans 700 ne laisse
	 * aucun titre en gras : le navigateur en synthétiserait un faux. Les graisses chargées sont celles
	 * du `link` de la police dans la barre (`wght@400;700`), 400 seule quand il n'en dit rien.
	 *
	 * Le poids d'un titre vient du socle (`template-base.css` pose 700 sur `h1`, `root.css` d'autres
	 * poids sur `h2` et `h3`) : une lecture de la feuille ne peut pas le calculer. Ce qui se vérifie
	 * ici est la borne que la feuille doit poser, une fois pour les titres que le socle écrit dans la
	 * police de titre, `h1`, `h2` et `h3` aux deux portées, à un poids que la police porte. Un élément
	 * hors de `h1`–`h3` qui lirait `--font-title` en gras (un composant de `component.css`) n'est pas
	 * vu : le contrôle qui le verrait mesure dans le navigateur le `font-weight` calculé de chaque
	 * élément dont la première famille est la police de titre, et refuse ce qui dépasse.
	 */
	public function testTitleWeights(): void {

		$wrong = [];

		foreach(LearningSetting::DESIGN_FONTS as $design => $fonts) {

			$font = CustomizeUi::getTitleFont($fonts['title']);

			if($font === NULL) {
				continue;
			}

			$weights = preg_match('/wght@([0-9;]+)/', $font['link'] ?? '', $match) ? array_map('intval', explode(';', $match[1])) : [400];
			$heaviest = max($weights);

			if($heaviest >= 700) {
				continue;
			}

			$css = file_get_contents(LIME_DIRECTORY.'/'.self::DIRECTORY.'/design-'.$design.'.css');

			foreach(['public', 'doc'] as $scope) {

				foreach(['h1', 'h2', 'h3'] as $title) {

					$bounded = FALSE;

					foreach(self::rules($css) as [$selector, $body]) {

						if(
							in_array(self::scope($scope, $design).' '.$title, self::split($selector), TRUE) and
							preg_match('/(?:^|;)\s*font-weight\s*:\s*([^;]+)/', $body, $weight)
						) {
							$value = match(trim($weight[1])) {
								'normal' => 400,
								'bold' => 700,
								default => is_numeric(trim($weight[1])) ? (int)trim($weight[1]) : INF,
							};
							$bounded = ($value <= $heaviest);
						}

					}

					if($bounded === FALSE) {
						$wrong[] = $design.' '.$scope.' '.$title.' ('.$font['label'].' jusqu\'à '.$heaviest.')';
					}

				}

			}

		}

		$this->assertEquals([], $wrong, 'police de titre chargée sans 700, ses titres seraient en faux gras : poser font-weight à un poids qu\'elle porte sur '.self::scope('<portée>', '<nom>').' h1, h2 et h3 : '.implode(' · ', $wrong));

	}

	/**
	 * Un gabarit n'a pas de fond à lui : tout fond qu'il peint dérive du fond choisi par le réseau
	 * (`--learning-background`, `--learning-background-chosen`, la teinte `--learning-tint` qui en est
	 * tirée), de sa couleur (`--primary`) ou du blanc. Un fond codé en dur impose au réseau une
	 * matière qu'il n'a pas choisie, et que ses encres doivent rattraper.
	 *
	 * Sont lus les fonds que la feuille déclare, `background*`, et les jetons de fond qu'elle pose
	 * pour le socle (`--*background*`, `--*-image`, `--community`). Un jeton que la feuille définit
	 * elle-même est suivi jusqu'à sa valeur ; un jeton qu'elle ne définit pas doit être l'un de
	 * `DERIVED`. Hors des `var()`, la valeur ne peut nommer que le blanc, éventuellement translucide,
	 * et `transparent` : tout autre code ou nom de couleur est refusé. Les bordures et les encres ne
	 * sont pas des fonds et ne sont pas visées.
	 */
	public function testBackgroundsDerived(): void {

		$wrong = [];

		foreach($this->sheets() as $design => $css) {

			$definitions = self::definitions($css);

			foreach(self::rules($css) as [, $body, $line]) {

				foreach(self::properties($body) as [$property, $value]) {

					if(self::isBackground($property) === FALSE) {
						continue;
					}

					$reason = self::underived($value, $definitions);

					if($reason !== NULL) {
						$wrong[] = $design.':'.$line.' '.$property.' ('.$reason.')';
					}

				}

			}

		}

		$this->assertEquals([], $wrong, 'fond propre au gabarit, il doit dériver du fond choisi, de --primary ou du blanc, par color-mix() : '.implode(' · ', $wrong));

	}

	/**
	 * Un bandeau porte au plus un ornement : un filet ou une teinte. Ni double cadre, ni trame,
	 * ni pavé, ni bulles : chaque ornement ajouté se dispute le titre.
	 *
	 * Est compté sur le bandeau de la liste et sur ce qu'il contient : un fond visible, par couche ;
	 * une bordure ou un contour visible, par côté déclaré, double s'il est `double` ; une ombre ; un
	 * soulignement ; un pseudo-élément qui a un contenu. Un fond égal à celui de la page ne se voit
	 * pas. Sans fond posé par la feuille, le bandeau garde l'aplat de la couleur que le socle lui
	 * donne (`template-community.css`), qui compte. Une règle plus loin dans la feuille, `@media`
	 * compris, remplace la précédente sur le même côté : c'est la cascade d'une même feuille à
	 * spécificité égale.
	 */
	public function testOrnaments(): void {

		$wrong = [];

		foreach($this->sheets() as $design => $css) {

			$ornaments = self::ornaments($css, $design);

			if(count($ornaments) > 1) {
				$wrong[] = $design.' ('.implode(', ', $ornaments).')';
			}

		}

		$this->assertEquals([], $wrong, 'bandeau à plusieurs ornements, n\'en garder qu\'un, un filet ou une teinte : '.implode(' · ', $wrong));

	}

	/**
	 * L'encadré garde son filet coloré à gauche dans tous les gabarits : c'est lui
	 * qui distingue un encadré d'une carte. Un gabarit peut en changer la largeur, pas l'éteindre.
	 */
	public function testCalloutAccent(): void {

		$wrong = [];

		foreach($this->sheets() as $design => $css) {

			foreach(self::rules($css) as [, $body, $line]) {

				foreach(self::properties($body) as [$property, $value]) {

					if(
						($property === '--callout-accent-width' and preg_match('/^0(\.0+)?([a-z]+)?$/', $value) === 1) or
						($property === '--callout-accent-style' and in_array($value, ['none', 'hidden'], TRUE))
					) {
						$wrong[] = $design.':'.$line.' '.$property.': '.$value;
					}

				}

			}

		}

		$this->assertEquals([], $wrong, 'filet d\'encadré éteint, retirer la déclaration : '.implode(' · ', $wrong));

	}

	/**
	 * Les blocs d'une leçon sont écartés d'un cran de plus que dans le produit, le même
	 * cran dans tous les gabarits : `learning.css` le pose sur la portée de la leçon,
	 * pour la liste et pour deux volets voisins, et aucune feuille de gabarit ne touche la liste.
	 * L'écart du produit est dans `read.css`, `.block-list`.
	 */
	public function testBlockGap(): void {

		$expected = [
			'.learning-public .learning-lesson .block-list' => ['gap', 'var(--space-lg)'],
			'.learning-public .learning-lesson .block-list .util-deck + .util-deck' => ['margin-top', 'var(--space-lg)'],
		];

		$found = array_fill_keys(array_keys($expected), NULL);

		foreach(self::rules(file_get_contents(LIME_DIRECTORY.'/'.self::DIRECTORY.'/learning.css')) as [$selector, $body]) {

			foreach(self::split($selector) as $part) {

				if(array_key_exists($part, $expected) === FALSE) {
					continue;
				}

				foreach(self::properties($body) as [$property, $value]) {
					if($property === $expected[$part][0]) {
						$found[$part] = $value;
					}
				}

			}

		}

		foreach($expected as $selector => [$property, $value]) {
			$this->assertEquals($value, $found[$selector], 'écart entre les blocs d\'une leçon : poser '.$property.': '.$value.' sur '.$selector.' dans learning.css');
		}

		$touched = [];

		foreach($this->sheets() as $design => $css) {
			foreach(self::rules($css) as [$selector, , $line]) {
				if(str_contains($selector, '.block-list')) {
					$touched[] = $design.':'.$line;
				}
			}
		}

		$this->assertEquals([], $touched, 'une feuille de gabarit change l\'écart entre les blocs, qui est le même partout : '.implode(' · ', $touched));

	}

	/**
	 * Ce que `learning.css` pose sous tous les gabarits (`[data-learning-design]` sans nom), aucune
	 * feuille de gabarit ne le redéclare sur le même sélecteur. À spécificité égale, la feuille se
	 * charge avant `learning.css` sur la page du réseau et après sur la fiche : sa valeur ne
	 * s'afficherait que sur la fiche, et `DesignContrastSentinel`, qui la lit après la couche
	 * commune, mesurerait une encre que la page du réseau ne montre pas.
	 */
	public function testCommonLayer(): void {

		$common = [];

		foreach(self::rules(file_get_contents(LIME_DIRECTORY.'/'.self::DIRECTORY.'/learning.css')) as [$selector, $body]) {

			$relatives = [];

			foreach(self::split($selector) as $part) {

				if(preg_match('/^\.learning-(public|doc)\[data-learning-design\](.*)$/', $part, $match) === 0) {
					continue 2;
				}

				$relatives[] = $match[1].$match[2];

			}

			foreach($relatives as $relative) {
				foreach(self::properties($body) as [$property]) {
					$common[$relative][$property] = TRUE;
				}
			}

		}

		$this->assertTrue(isset($common['public']['--table-head-color']), 'la couche commune de learning.css est introuvable : la sentinelle ne voit plus rien, vérifier qu\'elle lit encore .learning-public[data-learning-design]');

		$redeclared = [];

		foreach($this->sheets() as $design => $css) {

			foreach(self::rules($css) as [$selector, $body, $line]) {

				foreach(self::split($selector) as $part) {

					foreach(['public', 'doc'] as $scope) {

						$prefix = self::scope($scope, $design);

						if(str_starts_with($part, $prefix) === FALSE) {
							continue;
						}

						$relative = $scope.substr($part, strlen($prefix));

						foreach(self::properties($body) as [$property]) {
							if(isset($common[$relative][$property])) {
								$redeclared[] = $design.':'.$line.' '.$property.' sur « '.$part.' »';
							}
						}

					}

				}

			}

		}

		$this->assertEquals([], $redeclared, 'une feuille de gabarit redéclare ce que la couche commune de learning.css pose sous tous les gabarits, sa valeur ne s\'afficherait que sur la fiche : la retirer, ou sortir la déclaration de la couche commune pour la poser dans chacun des dix gabarits : '.implode(' · ', $redeclared));

	}

	/**
	 * Les ornements du bandeau de la liste, un libellé par ornement.
	 *
	 * @return string[]
	 */
	private static function ornaments(string $css, string $design): array {

		$prefix = self::scope('public', $design).' '.self::HEADER;
		$values = [];

		foreach(self::rules($css) as [$selector, $body]) {

			foreach(self::split($selector) as $part) {

				if(str_starts_with($part, $prefix) === FALSE) {
					continue;
				}

				$target = substr($part, strlen($prefix));

				if($target !== '' and preg_match('/^[\s:]/', $target) === 0) {
					continue;
				}

				$target = trim(preg_replace('/:{1,2}(before|after)\b/', '::$1', $target));

				foreach(self::properties($body) as [$property, $value]) {

					$family = self::ornamentFamily($property);

					if($family !== NULL) {
						$values[$target.'|'.$family] = $value;
					}

				}

			}

		}

		$ornaments = [];

		if(isset($values['|background']) === FALSE) {
			$ornaments[] = 'aplat du socle';
		}

		foreach($values as $key => $value) {

			[$target, $family] = explode('|', $key);
			$label = trim($target.' '.$family);

			if(preg_match('/::(before|after)$/', $target) === 1) {

				if($family === 'content' and in_array($value, ['none', 'normal'], TRUE) === FALSE) {
					$ornaments[] = $target;
				}

				continue;

			}

			if($family === 'content') {
				continue;
			}

			if(in_array($family, ['background', 'background-image'], TRUE)) {

				foreach(self::layers($value) as $layer) {
					if(preg_match('/^(none|transparent|inherit|initial|unset|var\(--learning-background\))$/', $layer) === 0) {
						$ornaments[] = $label;
					}
				}

				continue;

			}

			if(preg_match('/(^|\s)(none|hidden|0|0px|0rem)(\s|$)|\btransparent\b/', $value) === 1) {
				continue;
			}

			$ornaments[] = $label;

			if(preg_match('/\bdouble\b/', $value) === 1) {
				$ornaments[] = $label.' double';
			}

		}

		return $ornaments;

	}

	/**
	 * La famille d'ornement d'une propriété, NULL si elle n'en dessine pas. Les côtés d'une bordure
	 * sont des filets distincts ; la largeur, le style ou la couleur seuls modifient un filet déjà
	 * compté et ne sont pas lus.
	 */
	private static function ornamentFamily(string $property): ?string {

		if(in_array($property, ['background', 'background-color'], TRUE)) {
			return 'background';
		} else if(in_array($property, ['text-decoration', 'text-decoration-line'], TRUE)) {
			return 'text-decoration';
		} else if(
			in_array($property, ['background-image', 'outline', 'box-shadow', 'content'], TRUE) or
			preg_match('/^border(-(top|right|bottom|left|block|inline|block-start|block-end|inline-start|inline-end))?$/', $property) === 1
		) {
			return $property;
		} else {
			return NULL;
		}

	}

	/**
	 * Le premier défaut d'une valeur de fond qui ne dérive pas du fond choisi, de `--primary` ou du
	 * blanc, NULL si elle en dérive.
	 *
	 * @param array<string, string[]> $definitions
	 * @param string[] $seen
	 */
	private static function underived(string $value, array $definitions, array $seen = []): ?string {

		preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', $value, $matches);

		foreach($matches[1] as $token) {

			if(in_array($token, $seen, TRUE)) {
				continue;
			}

			if(isset($definitions[$token])) {

				foreach($definitions[$token] as $definition) {

					$reason = self::underived($definition, $definitions, [...$seen, $token]);

					if($reason !== NULL) {
						return $token.' → '.$reason;
					}

				}

			} else if(in_array($token, self::DERIVED, TRUE) === FALSE) {
				return 'lit '.$token;
			}

		}

		$rest = preg_replace('/var\(\s*--[a-z0-9-]+\s*,?/i', '(', $value);

		preg_match_all('/#[0-9a-f]{3,8}\b/i', $rest, $hexadecimals);

		foreach($hexadecimals[0] as $hexadecimal) {
			if(preg_match('/^#(f{3,4}|f{6}([0-9a-f]{2})?)$/i', $hexadecimal) === 0) {
				return $hexadecimal;
			}
		}

		$rest = preg_replace('/#[0-9a-f]{3,8}\b/i', '', $rest);
		$rest = preg_replace('/rgba?\(\s*255[\s,]+255[\s,]+255\b[^)]*\)/i', '', $rest);

		if(preg_match('/\b(rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(/i', $rest, $match)) {
			return $match[1].'()';
		}

		$rest = preg_replace('/-?[0-9]*\.?[0-9]+[a-z%]*/i', '', $rest);

		preg_match_all('/[a-z][a-z-]*/i', $rest, $words);

		foreach($words[0] as $word) {
			if(in_array(strtolower($word), self::KEYWORDS, TRUE) === FALSE) {
				return $word;
			}
		}

		return NULL;

	}

	private static function isBackground(string $property): bool {
		return (preg_match('/^(background(-color|-image)?|--[a-z0-9-]*background[a-z0-9-]*|--[a-z0-9-]+-image|--community)$/', $property) === 1);
	}

	/**
	 * Les valeurs que la feuille donne à chacun de ses jetons, toutes portées confondues.
	 *
	 * @return array<string, string[]>
	 */
	private static function definitions(string $css): array {

		$definitions = [];

		foreach(self::rules($css) as [, $body]) {
			foreach(self::properties($body) as [$property, $value]) {
				if(str_starts_with($property, '--')) {
					$definitions[$property][] = $value;
				}
			}
		}

		return $definitions;

	}

	/**
	 * Les déclarations d'un corps de règle, dans l'ordre : propriété, valeur.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function properties(string $body): array {

		$properties = [];

		foreach(explode(';', $body) as $declaration) {

			$position = strpos($declaration, ':');

			if($position === FALSE) {
				continue;
			}

			$properties[] = [strtolower(trim(substr($declaration, 0, $position))), trim(substr($declaration, $position + 1))];

		}

		return $properties;

	}

	/**
	 * Les couches d'une valeur de fond, découpées sur les virgules hors parenthèses.
	 *
	 * @return string[]
	 */
	private static function layers(string $value): array {

		$layers = [''];
		$depth = 0;

		foreach(str_split($value) as $character) {

			if($character === ',' and $depth === 0) {
				$layers[] = '';
				continue;
			}

			$depth += match($character) {
				'(' => 1,
				')' => -1,
				default => 0,
			};

			$layers[array_key_last($layers)] .= $character;

		}

		return array_map('trim', $layers);

	}

	public function testDetectors(): void {

		$css = "/* a { b: c; } */\n.x[data-learning-design=\"kraft\"] { --a: 1; }\n@media print {\n\t.y, .z::after { color: red; }\n}\n";
		$rules = self::rules($css);

		$this->assertEquals(2, count($rules), 'le commentaire est écarté, la règle imbriquée dans @media est lue');
		$this->assertEquals('.y, .z::after', $rules[1][0], 'le sélecteur imbriqué est lu sans son @media');
		$this->assertEquals(['.y', '.z::after'], self::split($rules[1][0]), 'une liste de sélecteurs se découpe');

		$this->assertEquals('', self::relative('.learning-public[data-learning-design="kraft"]', 'kraft'), 'la racine de la portée');
		$this->assertEquals(' .community-header h1', self::relative('.learning-public[data-learning-design="kraft"] .community-header h1', 'kraft'), 'un descendant de la portée');
		$this->assertEquals(NULL, self::relative('.learning-public[data-learning-design="futur"] .community-header', 'kraft'), 'la portée d\'un autre gabarit');
		$this->assertEquals(NULL, self::relative('.learning-doc[data-learning-design="kraft"], .community-header', 'kraft'), 'une liste dont un membre sort de la portée');
		$this->assertEquals(NULL, self::relative('.learning-doc[data-learning-design="kraft"]-x', 'kraft'), 'un préfixe qui n\'est pas la portée');

		$this->assertEquals(['{background}', '#fbf5e6'], self::colors('color-mix(in srgb, var(--learning-background-chosen) 45%, #FBF5E6)'), 'un fond mêlé à un papier');
		$this->assertTrue(self::isCyclic(' color-mix(in srgb, var(--learning-background) 45%, #fbf5e6)'), 'un fond qui se lit lui-même');
		$this->assertTrue(self::isCyclic('var( --learning-background, white)'), 'un fond qui se lit lui-même, avec repli');
		$this->assertFalse(self::isCyclic('color-mix(in srgb, var(--learning-background-chosen) 45%, #fbf5e6)'), 'le fond choisi, sous son second nom');
		$this->assertEquals(['{background}'], self::colors('transparent'), 'une surface transparente laisse voir le fond');
		$this->assertEquals(['#ffffff'], self::colors('white'), 'le blanc nommé');

		$this->assertEquals(NULL, self::underived('color-mix(in srgb, var(--primary) 20%, white)', []), 'une teinte de la couleur sur le blanc');
		$this->assertEquals(NULL, self::underived('linear-gradient(135deg, var(--learning-tint), var(--learning-background))', []), 'un dégradé de la teinte au fond');
		$this->assertEquals(NULL, self::underived('rgb(255 255 255 / 12%)', []), 'le blanc translucide');
		$this->assertEquals(NULL, self::underived('#FFFFFF', []), 'le blanc en hexadécimal');
		$this->assertEquals(NULL, self::underived('var(--paper)', ['--paper' => ['color-mix(in srgb, var(--learning-background-chosen) 60%, white)']]), 'un jeton de la feuille qui dérive du fond');
		$this->assertEquals('#c9ab7f', self::underived('#c9ab7f', []), 'un papier codé en dur');
		$this->assertEquals('--paper → #fbf5e6', self::underived('color-mix(in srgb, var(--paper) 50%, white)', ['--paper' => ['#fbf5e6']]), 'un papier caché derrière un jeton de la feuille');
		$this->assertEquals('lit --secondary', self::underived('color-mix(in srgb, var(--secondary) 14%, white)', []), 'la seconde couleur n\'est pas celle du réseau');
		$this->assertEquals('black', self::underived('color-mix(in srgb, var(--primary) 80%, black)', []), 'un noir nommé');
		$this->assertEquals('rgb()', self::underived('rgb(0 0 0 / 10%)', []), 'un noir translucide');

		$public = '.learning-public[data-learning-design="kraft"] .community-header';

		$this->assertEquals(['aplat du socle'], self::ornaments($public.' h1 { color: inherit; }', 'kraft'), 'le bandeau de la liste sans fond garde l\'aplat du socle');
		$this->assertEquals([], self::ornaments($public.' { background: transparent; }', 'kraft'), 'un bandeau éteint');
		$this->assertEquals(['background', 'border-bottom'], self::ornaments($public.' { background: var(--learning-tint); border-bottom: 1px solid var(--primary); }', 'kraft'), 'une teinte et un filet');
		$this->assertEquals(['border-bottom', 'border-bottom double'], self::ornaments($public.' { background: transparent; border-bottom: 4px double var(--primary); }', 'kraft'), 'un double filet');
		$this->assertEquals(['background', 'h1::after'], self::ornaments($public.' { background: white; }'."\n".$public.' h1::after { content: ""; }', 'kraft'), 'une barre sous le titre');
		$this->assertEquals(['background', 'background'], self::ornaments($public.' { background: repeating-linear-gradient(45deg, white 0 1px, transparent 1px 4px), var(--learning-tint); }', 'kraft'), 'une trame sur une teinte');
		$this->assertEquals([], self::ornaments($public.' { background: transparent; border-top: 1px solid var(--primary); }'."\n@media (max-width: 575px) {\n".$public.' { border-top: none; }'."\n}", 'kraft'), 'un filet retiré plus loin dans la feuille');
		$this->assertEquals([], self::ornaments($public.' { background: var(--learning-background); }', 'kraft'), 'un fond égal à celui de la page');

	}

	/**
	 * @return array<string, string> le contenu de chaque feuille de gabarit, par nom de gabarit
	 */
	private function sheets(): array {

		$sheets = [];

		foreach(glob(LIME_DIRECTORY.'/'.self::DIRECTORY.'/design-*.css') as $path) {
			$sheets[substr(basename($path, '.css'), strlen('design-'))] = file_get_contents($path);
		}

		ksort($sheets);

		return $sheets;

	}

	/**
	 * @return array<string, string>
	 */
	private function baseSheets(): array {

		$sheets = [];

		foreach(self::BASE_SHEETS as $pattern) {
			foreach(glob(LIME_DIRECTORY.'/'.$pattern) as $path) {
				$sheets[substr($path, strlen(LIME_DIRECTORY) + 1)] = file_get_contents($path);
			}
		}

		return $sheets;

	}

	/**
	 * Les règles d'une feuille, imbriquées dans un `@media` comprises : sélecteur, corps, ligne.
	 *
	 * @return array<int, array{0: string, 1: string, 2: int}>
	 */
	private static function rules(string $css): array {

		$css = preg_replace_callback('#/\*.*?\*/#s', fn(array $match) => str_repeat("\n", substr_count($match[0], "\n")), $css);

		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

		$rules = [];

		foreach($matches as $match) {

			$selector = trim(preg_replace('/\s+/', ' ', $match[1][0]));

			if(str_starts_with($selector, '@')) {
				continue;
			}

			$rules[] = [$selector, trim($match[2][0]), substr_count($css, "\n", 0, $match[1][1] + strlen($match[1][0]) - strlen(ltrim($match[1][0]))) + 1];

		}

		return $rules;

	}

	/**
	 * @return string[]
	 */
	private static function split(string $selector): array {
		return array_map('trim', explode(',', $selector));
	}

	private static function scope(string $scope, string $design): string {
		return '.learning-'.$scope.'[data-learning-design="'.$design.'"]';
	}

	/**
	 * Ce que vise le sélecteur sous la portée du gabarit, `''` pour la racine, NULL si un membre de
	 * la liste sort des deux portées ou vise celle d'un autre gabarit.
	 */
	private static function relative(string $selector, string $design): ?string {

		$relatives = [];

		foreach(self::split($selector) as $part) {

			$relative = NULL;

			foreach(['public', 'doc'] as $scope) {

				$prefix = self::scope($scope, $design);

				if(
					str_starts_with($part, $prefix) and
					preg_match('/^[\s:]|^$/', substr($part, strlen($prefix)))
				) {
					$relative = substr($part, strlen($prefix));
				}

			}

			if($relative === NULL) {
				return NULL;
			}

			$relatives[] = $relative;

		}

		return implode(', ', array_unique($relatives));

	}

	/**
	 * Une valeur de `--learning-background` qui lit `--learning-background` : la propriété se lit
	 * elle-même, et une propriété personnalisée prise dans un cycle vaut « invalide au calcul ».
	 */
	private static function isCyclic(string $value): bool {
		return (preg_match('/var\(\s*--learning-background\s*[,)]/', $value) === 1);
	}

	/**
	 * Les couleurs d'une valeur de fond : les codes hexadécimaux, le blanc nommé, et `{background}`
	 * quand la valeur laisse voir le fond choisi ou le mêle.
	 *
	 * @return string[]
	 */
	private static function colors(string $value): array {

		$colors = [];

		if(
			str_contains($value, 'var(--learning-background') or
			preg_match('/\btransparent\b/', $value)
		) {
			$colors[] = '{background}';
		}

		preg_match_all('/#[0-9a-f]{6}\b/i', $value, $matches);

		foreach($matches[0] as $color) {
			$colors[] = strtolower($color);
		}

		if(preg_match('/\bwhite\b/', $value)) {
			$colors[] = '#ffffff';
		}

		return array_values(array_unique($colors));

	}

}
?>
