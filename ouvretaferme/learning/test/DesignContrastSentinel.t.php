<?php
namespace learning;

/**
 * Test-sentinelle : ce qu'un gabarit pose sous un texte de l'encadré ou de la citation se lit à
 * 4,5:1.
 *
 * Deux textes y sont posés sur une teinte que la feuille choisit, sans que `CommunityLib::getInks()`
 * la connaisse : le titre d'un encadré (`util-card-accent`), sur `--callout-background`, et l'auteur
 * d'une citation, en `--quote-author-color` sinon `--muted`, sur `--quote-background`. Les encres
 * sont calculées sur le fond et les surfaces (`LearningSetting::DESIGN_MATTERS`), pas sur ces
 * teintes, et une teinte mêlée à la couleur du réseau change avec elle : un gabarit lisible en vert
 * peut ne plus l'être en noir. Le titre de section sur sa gélule (`--heading-background`) est dans
 * le même cas.
 *
 * Le titre d'un encadré lit `--callout-title-color`, sinon la couleur de sa variante
 * (`--warning`, `--success`), qui ne tient 4,5:1 sur aucune teinte claire : un gabarit qui ne pose
 * pas le jeton tombe sous le seuil. L'information lit l'encre calculée du réseau, elle n'est pas
 * mesurée ici.
 *
 * Chaque feuille de `LearningSetting::DESIGNS` est mesurée sous la couche commune de `learning.css`
 * (`sheets()`), aux deux portées et sous chacune des couleurs de `COLORS`, sans liste de gabarits
 * nommés : le rouge vient de la mesure. Un fond que la sentinelle ne sait pas lire échoue aussi,
 * plutôt que de passer sans avoir été mesuré.
 *
 * Inventaire des feuilles seul, sans écriture en base. Se corrige la feuille du gabarit, ou la couche
 * commune de `learning.css` quand l'encre fautive en vient.
 *
 * Lancement : dev/test package=learning file=DesignContrastSentinel
 */
class DesignContrastSentinelTest extends \Test {

	const DIRECTORY = 'ouvretaferme/learning/static/css';

	const REBOOT = 'ouvretaferme/main/static/css/reboot.css';

	const MINIMUM = 4.5;

	/**
	 * Le contraste au blanc au-dessous duquel une encre de titre n'est plus sombre, et celui au-dessus
	 * duquel un fond de bandeau n'est plus clair (`testDarkInks()`).
	 */
	const DARK = 10.0;

	const LIGHT = 2.0;

	/**
	 * Les couleurs de réseau sous lesquelles chaque teinte se calcule : le noir, qui assombrit le plus
	 * un mélange, la couleur du produit, que porte un réseau sans couleur, puis le vert et le jaune
	 * clair de la recette A3n.
	 */
	const COLORS = ['#000000', CommunityLib::COLOR_DEFAULT, '#2f6b4f', '#f2c14e'];

	/**
	 * Les couleurs de réseau sous lesquelles se mesurent les bandeaux : des couleurs moyennes, dont
	 * l'encre tient 4,5:1 sans marge et qu'une atténuation fait tomber (contre-recette A3n-4), le
	 * vert et le jaune de la recette, un jaune pâle, et les deux bornes du sélecteur, le blanc et le
	 * noir, sous lesquelles une teinte de la couleur est la plus claire ou la plus sombre.
	 * Aucune ne dépend du décor.
	 */
	const HEADER_COLORS = ['#2f7d55', '#1f7a8c', '#b5532a', '#7a4fb0', '#c0392b', '#2f6b4f', '#f2c14e', '#fff3b0', '#ffffff', '#000000'];

	/**
	 * Le bandeau de la liste du réseau, sous la portée `public` : son sélecteur, l'encre et le fond que
	 * le socle lui donne quand la feuille ne les pose pas (`template-community.css`), et les titres
	 * qui peuvent prendre une autre encre, avec la propriété qui la porte. Le surtitre la reçoit par
	 * le jeton de son composant, posé sur sa ligne (`--scope-line-title-ink`, `component.css`), et
	 * non par une couleur posée sur lui.
	 */
	const HEADER = [
		'selector' => '.community-header',
		'color' => 'var(--community-ink)',
		'background' => 'var(--community)',
		'titles' => [' h1' => 'color', ' .community-main-back' => '--scope-line-title-ink'],
	];

	/**
	 * Les variantes d'encadré d'un bloc (`BlockTypeLib`) dont le titre prend la couleur de la
	 * variante, et le jeton du socle qui la porte.
	 */
	const VARIANTS = [
		'warning' => '--warning',
		'success' => '--success',
	];

	/**
	 * @var array<string, string> les jetons de `:root` dans `reboot.css`
	 */
	private array $reboot = [];

	public function init(): void {
		$this->reboot = self::tokens(file_get_contents(LIME_DIRECTORY.'/'.self::REBOOT), ':root');
	}

	public function testCalloutTitles(): void {

		$low = [];
		$unreadable = [];

		$pageBackgrounds = self::pageBackgrounds();

		foreach($this->sheets() as $design => $css) {

			foreach(['public', 'doc'] as $scope) {

				$sheetTokens = self::tokens($css, self::scope($scope, $design));
				$background = $sheetTokens['--callout-background'] ?? 'var(--background-surface)';

				foreach(self::COLORS as $color) {

					$worst = array_fill_keys(array_keys(self::VARIANTS), INF);

					foreach($pageBackgrounds as $pageBackground) {

						$tokens = array_merge($this->styleTokens($design, $scope, $color, $pageBackground), $sheetTokens);
						$backgrounds = $this->backgrounds($background, $tokens, $color);

						if($backgrounds === []) {
							$unreadable[$design.' '.$scope] = $design.' '.$scope.' « '.$background.' »';
							continue 3;
						}

						foreach(self::VARIANTS as $variant => $token) {
							$ink = $this->resolve($tokens['--callout-title-color'] ?? 'var('.$token.')', $tokens, $color);
							$worst[$variant] = min($worst[$variant], self::ratio($ink, $backgrounds));
						}

					}

					foreach($worst as $variant => $ratio) {

						if($ratio < self::MINIMUM) {
							$low[] = $design.' '.$scope.' '.$variant.' sous '.$color.' : '.number_format($ratio, 2, ',', '');
						}

					}

				}

			}

		}

		$this->assertEquals([], array_values($unreadable), 'fond d\'encadré que la sentinelle ne sait pas lire, la dernière couche de --callout-background doit être une couleur opaque : '.implode(' · ', $unreadable));
		$this->assertEquals([], $low, 'titre d\'encadré sous '.number_format(self::MINIMUM, 1, ',', '').':1, --callout-title-color se pose dans la couche commune de learning.css, sous tous les gabarits, jamais dans une feuille de gabarit (DesignSentinel::testCommonLayer) : y corriger l\'encre, ou éclaircir --callout-background à la racine du gabarit : '.implode(' · ', $low));

	}

	public function testQuoteAuthors(): void {

		$low = [];
		$unreadable = [];

		$pageBackgrounds = self::pageBackgrounds();

		foreach($this->sheets() as $design => $css) {

			foreach(['public', 'doc'] as $scope) {

				$sheetTokens = self::tokens($css, self::scope($scope, $design));

				if(isset($sheetTokens['--quote-background']) === FALSE) {
					continue;
				}

				foreach(self::COLORS as $color) {

					$worst = INF;

					foreach($pageBackgrounds as $pageBackground) {

						$tokens = array_merge($this->styleTokens($design, $scope, $color, $pageBackground), $sheetTokens);
						$backgrounds = $this->backgrounds($sheetTokens['--quote-background'], $tokens, $color);

						if($backgrounds === []) {
							$unreadable[$design.' '.$scope] = $design.' '.$scope.' « '.$sheetTokens['--quote-background'].' »';
							continue 3;
						}

						// Sans gris posé par la feuille, celui que `CustomizeUi::getStyles()` écrit pour ce fond.
						$inks = isset($sheetTokens['--quote-author-color']) ? [$sheetTokens['--quote-author-color']] : (isset($sheetTokens['--muted']) ? self::getMuted($css, $design, $scope) : [$tokens['--muted']]);

						foreach($inks as $ink) {
							$worst = min($worst, self::ratio($this->resolve($ink, $tokens, $color), $backgrounds));
						}

					}

					if($worst < self::MINIMUM) {
						$low[] = $design.' '.$scope.' sous '.$color.' : '.number_format($worst, 2, ',', '');
					}

				}

			}

		}

		$this->assertEquals([], array_values($unreadable), 'fond de citation que la sentinelle ne sait pas lire, la dernière couche de --quote-background doit être une couleur opaque : '.implode(' · ', $unreadable));
		$this->assertEquals([], $low, 'auteur de citation sous '.number_format(self::MINIMUM, 1, ',', '').':1, éclaircir --quote-background ou foncer --quote-author-color à la racine du gabarit : '.implode(' · ', $low));

	}

	/**
	 * Le texte du bandeau de la liste du réseau, plein et atténué, sur son fond réel : la teinte de la
	 * couleur que la feuille y pose comprise. Les jetons de la portée sont ceux que
	 * `CustomizeUi::getStyles()` écrit pour un réseau de la couleur et du fond donnés, sous ceux de la
	 * feuille, qui l'emportent sur eux. Chaque couleur de `HEADER_COLORS` est essayée sur chaque fond
	 * que la barre propose, le pire est retenu. Le bandeau n'est mesuré atténué que si la feuille pose
	 * `--muted` sur lui ou sur la racine de la portée, d'où il le reçoit.
	 */
	public function testHeaders(): void {

		$low = [];
		$unreadable = [];

		$backgrounds = self::pageBackgrounds();

		foreach($this->sheets() as $design => $css) {

			$selector = self::scope('public', $design).' '.self::HEADER['selector'];
			$declarations = self::declarations($css, $selector);

			$ink = $declarations['color'] ?? self::HEADER['color'];

			$titles = [];

			foreach(self::HEADER['titles'] as $suffix => $property) {

				$title = self::declarations($css, $selector.$suffix)[$property] ?? 'inherit';

				if($title !== 'inherit') {
					$titles[] = str_ireplace('currentColor', $ink, $title);
				}

			}

			$fill = $this->headerFill($css, $design, $declarations);

			$isMuted = (isset($declarations['--muted']) or isset(self::tokens($css, self::scope('public', $design))['--muted']));

			foreach(self::HEADER_COLORS as $color) {

				$worst = ['texte' => INF, 'atténué' => INF];

				foreach($backgrounds as $background) {

					$tokens = array_merge(
						$this->styleTokens($design, 'public', $color, $background),
						self::tokens($css, self::scope('public', $design)),
						array_filter($declarations, fn(string $property) => str_starts_with($property, '--'), ARRAY_FILTER_USE_KEY)
					);

					$fills = $this->backgrounds($fill, $tokens, $color, TRUE);

					if($fills === []) {
						$unreadable[$design] = $design.' « '.$fill.' »';
						continue 3;
					}

					foreach([$ink, ...$titles] as $text) {
						$worst['texte'] = min($worst['texte'], self::ratio($this->resolve($text, $tokens, $color), $fills));
					}

					if($isMuted) {
						$muted = str_ireplace('currentColor', $ink, $tokens['--muted'] ?? 'var(--muted)');
						$worst['atténué'] = min($worst['atténué'], self::ratio($this->resolve($muted, $tokens, $color), $fills));
					}

				}

				foreach($worst as $kind => $ratio) {

					if($ratio < self::MINIMUM) {
						$low[] = $design.' '.$kind.' sous '.$color.' : '.number_format($ratio, 2, ',', '');
					}

				}

			}

		}

		$this->assertEquals([], array_values($unreadable), 'fond de bandeau que la sentinelle ne sait pas lire, la dernière couche doit être une couleur opaque ou un dégradé de couleurs opaques : '.implode(' · ', $unreadable));
		$this->assertEquals([], $low, 'texte de bandeau sous '.number_format(self::MINIMUM, 1, ',', '').':1, poser sur le bandeau une encre calculée pour son fond : '.implode(' · ', $low));

	}

	/**
	 * Les bandeaux, les têtes de tableau, et les titres de section et onglets actifs posés sur un fond
	 * (`--heading-background`, `--tab-active-background`), sont clairs et leur titre est à l'encre
	 * sombre. Un bandeau
	 * plein de la couleur du réseau ou d'une nuit, sous un titre clair, se lit mais fait de chaque
	 * gabarit une affiche ; le titre coloré sur un fond clair, lui, change de lisibilité avec la
	 * couleur.
	 *
	 * Le fond est celui que `testHeaders()` mesure, trame et teinte comprises, et celui de la tête de
	 * tableau, `--table-head-background`, la page quand il est transparent. Il est clair quand chacune
	 * de ses couleurs tient au plus `LIGHT` contre le blanc. L'encre est celle du `h1` du bandeau,
	 * sinon celle du bandeau, et `--table-head-color` pour la tête de tableau : sombre quand elle
	 * tient au moins `DARK` contre le blanc, l'encre du texte du produit (`--text`) en tient 15. Le titre
	 * de section lit `--heading-color`, sinon l'encre de la portée ; l'onglet actif lit
	 * `--tab-active-color`, sinon `--primary-on-light` (`component.css`). Une gélule ou un onglet sans
	 * fond reste sur la page et n'est pas mesuré ici. Mesuré
	 * sous chaque couleur de `HEADER_COLORS` et sur chaque fond que la barre propose.
	 */
	public function testDarkInks(): void {

		$inks = [];
		$fills = [];
		$unreadable = [];

		$backgrounds = self::pageBackgrounds();
		$white = $this->resolve('#ffffff', [], '#000000');

		foreach($this->sheets() as $design => $css) {

			$surfaces = [];

			$selector = self::scope('public', $design).' '.self::HEADER['selector'];
			$declarations = self::declarations($css, $selector);

			$title = self::declarations($css, $selector.' h1')['color'] ?? 'inherit';
			$ink = ($title === 'inherit') ? ($declarations['color'] ?? self::HEADER['color']) : $title;

			$surfaces[] = [
				'label' => $design.' public bandeau',
				'ink' => $ink,
				'fill' => $this->headerFill($css, $design, $declarations),
				'declarations' => $declarations,
			];

			foreach(['public', 'doc'] as $scope) {

				$sheetTokens = self::tokens($css, self::scope($scope, $design));
				$fill = $sheetTokens['--table-head-background'] ?? 'transparent';

				$surfaces[] = [
					'label' => $design.' '.$scope.' th',
					'ink' => $sheetTokens['--table-head-color'] ?? 'var(--text-intermediate)',
					'fill' => ($fill === 'transparent') ? 'var(--learning-background)' : $fill,
					'declarations' => [],
				];

				if(isset($sheetTokens['--heading-background']) and $sheetTokens['--heading-background'] !== 'transparent') {
					$surfaces[] = [
						'label' => $design.' '.$scope.' titre de section',
						'ink' => $sheetTokens['--heading-color'] ?? self::declarations($css, self::scope($scope, $design))['color'] ?? 'var(--text)',
						'fill' => $sheetTokens['--heading-background'],
						'declarations' => [],
					];
				}

				if(isset($sheetTokens['--tab-active-background']) and $sheetTokens['--tab-active-background'] !== 'transparent') {
					$surfaces[] = [
						'label' => $design.' '.$scope.' onglet actif',
						'ink' => $sheetTokens['--tab-active-color'] ?? 'var(--primary-on-light)',
						'fill' => $sheetTokens['--tab-active-background'],
						'declarations' => [],
					];
				}

			}

			foreach($surfaces as $surface) {

				$scope = str_contains($surface['label'], ' public ') ? 'public' : 'doc';

				$darkest = INF;
				$lightest = 0.0;

				foreach(self::HEADER_COLORS as $color) {

					foreach($backgrounds as $background) {

						$tokens = array_merge(
							$this->styleTokens($design, $scope, $color, $background),
							self::tokens($css, self::scope($scope, $design)),
							array_filter($surface['declarations'], fn(string $property) => str_starts_with($property, '--'), ARRAY_FILTER_USE_KEY)
						);

						$colors = $this->backgrounds($surface['fill'], $tokens, $color, TRUE);

						if($colors === []) {
							$unreadable[$surface['label']] = $surface['label'].' « '.$surface['fill'].' »';
							continue 3;
						}

						$darkest = min($darkest, self::ratio($this->resolve($surface['ink'], $tokens, $color), [$white]));

						foreach($colors as $each) {
							$lightest = max($lightest, self::ratio($white, [$each]));
						}

					}

				}

				if($darkest < self::DARK) {
					$inks[] = $surface['label'].' : '.number_format($darkest, 2, ',', '');
				}

				if($lightest > self::LIGHT) {
					$fills[] = $surface['label'].' : '.number_format($lightest, 2, ',', '');
				}

			}

		}

		$this->assertEquals([], array_values($unreadable), 'fond de bandeau, de tête de tableau, de titre de section ou d\'onglet actif que la sentinelle ne sait pas lire : '.implode(' · ', $unreadable));
		$this->assertEquals([], $inks, 'titre de bandeau, de tête de tableau, de section ou d\'onglet actif qui n\'est pas à l\'encre sombre (contraste au blanc sous '.number_format(self::DARK, 0).'), l\'encre des bandeaux, de leurs titres et des têtes de tableau se corrige dans la couche commune de learning.css, jamais dans une feuille de gabarit (DesignSentinel::testCommonLayer), et --heading-color ou --tab-active-color: var(--text) se posent à la racine du gabarit : '.implode(' · ', $inks));
		$this->assertEquals([], $fills, 'fond de bandeau, de tête de tableau, de titre de section ou d\'onglet actif trop sombre (contraste au blanc au-dessus de '.number_format(self::LIGHT, 0).'), le tirer du fond choisi, d\'une teinte légère de --primary ou du blanc : '.implode(' · ', $fills));

	}

	/**
	 * Ce qui est peint sous le bandeau de la liste : le fond que la feuille lui pose, sinon celui du
	 * socle ; un bandeau sans fond, ou dont la dernière couche est transparente sous une bande au
	 * bord, laisse voir celui de la portée, motif compris.
	 *
	 * @param array<string, string> $declarations
	 */
	private function headerFill(string $css, string $design, array $declarations): string {

		$fill = $declarations['background'] ?? $declarations['background-color'] ?? self::HEADER['background'];

		$layers = self::split($fill);
		$last = array_pop($layers);

		if($last === 'transparent' or $last === 'var(--learning-background)') {
			$pattern = self::declarations($css, self::scope('public', $design))['background-image'] ?? NULL;
			$layers[] = ($pattern === NULL) ? 'var(--learning-background)' : $pattern.', var(--learning-background)';
			$fill = implode(', ', $layers);
		}

		return $fill;

	}

	/**
	 * Le titre de section (`util-title`) sur la gélule que la feuille lui pose par
	 * `--heading-background` (`component.css`). Le titre lit `--heading-color`, sinon la couleur de
	 * la portée : une encre calculée sur le fond de la page, pas sur cette teinte, qui suit la
	 * couleur du réseau. Mesuré comme les bandeaux, sous chaque couleur de `HEADER_COLORS` et sur
	 * chaque fond que la barre propose, le pire retenu. Une feuille qui ne pose pas de gélule n'a
	 * rien à mesurer ici : son titre est sur le fond de la page, dont les encres viennent de
	 * `DESIGN_MATTERS`.
	 */
	public function testSectionHeadings(): void {

		$low = [];
		$unreadable = [];

		$backgrounds = self::pageBackgrounds();

		$measured = 0;

		foreach($this->sheets() as $design => $css) {

			foreach(['public', 'doc'] as $scope) {

				$sheetTokens = self::tokens($css, self::scope($scope, $design));

				if(isset($sheetTokens['--heading-background']) === FALSE) {
					continue;
				}

				$measured++;

				$ink = $sheetTokens['--heading-color'] ?? self::declarations($css, self::scope($scope, $design))['color'] ?? 'var(--text)';

				foreach(self::HEADER_COLORS as $color) {

					$worst = INF;

					foreach($backgrounds as $background) {

						$tokens = array_merge($this->styleTokens($design, $scope, $color, $background), $sheetTokens);
						$fills = $this->backgrounds($sheetTokens['--heading-background'], $tokens, $color);

						if($fills === []) {
							$unreadable[$design.' '.$scope] = $design.' '.$scope.' « '.$sheetTokens['--heading-background'].' »';
							continue 3;
						}

						$worst = min($worst, self::ratio($this->resolve($ink, $tokens, $color), $fills));

					}

					if($worst < self::MINIMUM) {
						$low[] = $design.' '.$scope.' sous '.$color.' : '.number_format($worst, 2, ',', '');
					}

				}

			}

		}

		$this->assertTrue($measured > 0, 'aucune feuille ne pose --heading-background : la sentinelle ne mesure plus rien, vérifier qu\'elle lit encore le bon jeton');
		$this->assertEquals([], array_values($unreadable), 'gélule de titre que la sentinelle ne sait pas lire, la dernière couche de --heading-background doit être une couleur opaque : '.implode(' · ', $unreadable));
		$this->assertEquals([], $low, 'titre de section sous '.number_format(self::MINIMUM, 1, ',', '').':1 sur sa gélule, poser --heading-color: var(--text) à la racine du gabarit : '.implode(' · ', $low));

	}

	/**
	 * Le texte atténué sur la trame de la page : la racine de la portée peut poser en
	 * `background-image` des traits répétés (`repeating-*-gradient`), translucides, qui s'empilent
	 * là où ils se croisent. Les encres sont calculées sur le fond seul (`DESIGN_MATTERS`), et le
	 * croisement le plus sombre peut faire tomber le gris sous le seuil, comme sur les trames de
	 * Kraft et de Futur. Chaque combinaison des traits est posée sur chaque fond que la barre propose, et
	 * l'encre atténuée y est mesurée : `--muted` de la feuille, sinon le gris que
	 * `CommunityLib::getInks()` choisit, avec les jetons que `CustomizeUi::getStyles()` pose pour la
	 * couleur et le fond (`getMuted()`). Un dégradé qui ne se répète pas (un fondu) n'est pas une
	 * trame et n'est pas mesuré ici.
	 */
	public function testMutedOnPattern(): void {

		$low = [];
		$measured = 0;

		foreach($this->sheets() as $design => $css) {

			foreach(['public', 'doc'] as $scope) {

				$patterns = self::patterns($css, $design, $scope);

				if($patterns === []) {
					continue;
				}

				$measured++;

				foreach(self::COLORS as $color) {

					$ratio = INF;

					foreach(self::pageBackgrounds() as $background) {

						$tokens = array_merge(
							$this->styleTokens($design, $scope, $color, $background),
							self::tokens($css, self::scope($scope, $design))
						);

						$fills = $this->patternFills($patterns, $design, $scope, $tokens, $color);

						foreach(self::getMuted($css, $design, $scope) as $muted) {
							$ratio = min($ratio, self::ratio($this->resolve($muted, $tokens, $color), $fills));
						}

					}

					if($ratio < self::MINIMUM) {
						$low[] = $design.' '.$scope.' sous '.$color.' : '.number_format($ratio, 2, ',', '');
					}

				}

			}

		}

		$this->assertTrue($measured > 0, 'aucune feuille ne pose de trame répétée : la sentinelle ne mesure plus rien, vérifier qu\'elle lit encore background-image');
		$this->assertEquals([], $low, 'texte atténué sous '.number_format(self::MINIMUM, 1, ',', '').':1 au croisement de la trame, poser à la racine du gabarit un --muted plein, jugé sur la teinte et le fond entre lesquels passe la trame, comme --learning-tint-muted : '.implode(' · ', $low));

	}

	/**
	 * Le libellé d'un bouton contour sur la trame de la page : il prend l'encre de texte du réseau
	 * (`--primary-on-light`, `--secondary-on-light`), que `CommunityLib::getInks()` calcule sur le
	 * fond et les surfaces seuls, et le bouton n'a pas de fond. Au croisement des traits d'une trame
	 * qui se croise, une encre verte peut tomber sous le seuil, d'où le fond posé sous le bouton par
	 * Kraft et Futur. Mesuré comme le
	 * texte atténué, sous chaque couleur de `HEADER_COLORS`, avec les jetons que
	 * `CustomizeUi::getStyles()` pose pour elle ; le fond que la feuille pose sous le bouton au repos par
	 * `--button-outline-background` remplace la trame. Une feuille de gabarit ne vise pas le bouton
	 * lui-même (`DesignSentinel`) : le jeton est le seul chemin. `btn-outline-muted` lit `--text`,
	 * qui n'est pas l'encre du réseau, et n'est pas mesuré ici.
	 */
	public function testOutlineOnPattern(): void {

		$low = [];
		$unreadable = [];
		$measured = 0;

		foreach($this->sheets() as $design => $css) {

			foreach(['public', 'doc'] as $scope) {

				$patterns = self::patterns($css, $design, $scope);

				if($patterns === []) {
					continue;
				}

				$measured++;

				$fill = self::tokens($css, self::scope($scope, $design))['--button-outline-background'] ?? NULL;

				foreach(['primary', 'secondary'] as $variant) {

					$ink = 'var(--'.$variant.'-on-light, var(--'.$variant.'))';

					foreach(self::HEADER_COLORS as $color) {

						$tokens = array_merge(
							$this->styleTokens($design, $scope, $color, CommunityLib::BACKGROUND_DEFAULT),
							self::tokens($css, self::scope($scope, $design))
						);

						if($fill === NULL) {
							$fills = $this->patternFills($patterns, $design, $scope, $tokens, $color);
						} else {

							$fills = $this->backgrounds($fill, $tokens, $color);

							if($fills === []) {
								$unreadable[$design.' '.$scope.' '.$variant] = $design.' '.$scope.' '.$variant.' « '.$fill.' »';
								continue 2;
							}

						}

						$ratio = self::ratio($this->resolve($ink, $tokens, $color), $fills);

						if($ratio < self::MINIMUM) {
							$low[] = $design.' '.$scope.' btn-outline-'.$variant.' sous '.$color.' : '.number_format($ratio, 2, ',', '');
						}

					}

				}

			}

		}

		$this->assertTrue($measured > 0, 'aucune feuille ne pose de trame répétée : la sentinelle ne mesure plus rien, vérifier qu\'elle lit encore background-image');
		$this->assertEquals([], array_values($unreadable), 'fond de bouton contour que la sentinelle ne sait pas lire, la dernière couche doit être une couleur opaque : '.implode(' · ', $unreadable));
		$this->assertEquals([], $low, 'libellé de bouton contour sous '.number_format(self::MINIMUM, 1, ',', '').':1 au croisement de la trame, poser --button-outline-background: var(--background-surface) à la racine du gabarit : '.implode(' · ', $low));

	}

	/**
	 * Le menu de la fiche est dans la portée du gabarit, et son texte s'y lit.
	 *
	 * Hors de la portée, le menu resterait blanc et aux encres du produit à côté d'une colonne
	 * habillée (retour sur la fiche 296999 sous Bucolique, lot A3m : « le thème s'applique
	 * à toute la page de la formation y compris le menu à gauche »). Le gabarit de la page
	 * (`LearningAiTemplate`) est rendu ici sans base : l'élément qui porte `data-learning-design`
	 * doit contenir `.doc-menu`.
	 *
	 * Dans la portée, le menu n'a pas de surface à lui : c'est une colonne `util-filter-options-nav`
	 * dont les entrées et la formation lisent `--text`, la section `--color-neutral` (intitulé du
	 * design system) et le module `--muted`, sur le fond de la portée et sa trame. Le lien courant est
	 * un choix plein, `--primary-ink` sur l'aplat `--color-brand`, que la trame ne traverse pas. Le
	 * bouton Menu, un `btn-outline-primary` sans fond posé hors de la liste, lit `--primary-on-light`
	 * sur le fond de la portée, jamais sur l'aplat `--learning-menu-background`. Mesuré comme le
	 * bouton contour, sous chaque couleur de `HEADER_COLORS` et sur chaque fond que la barre propose,
	 * le pire retenu.
	 */
	public function testMenu(): void {

		$template = new \LearningAiTemplate();
		$template->custom = TRUE;
		$template->design = 'kraft';
		$template->menu = '<div class="util-filter-options util-filter-options-column util-filter-options-nav util-filter-options-menu"><a class="util-filter-option is-selected">Leçon</a></div>';

		$html = new \ReflectionMethod($template, 'getMain')->invoke($template, '');

		$dom = new \DOMDocument();
		@$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

		$menus = new \DOMXPath($dom)->query('//*[@data-learning-design]//*[contains(concat(" ", normalize-space(@class), " "), " doc-menu ")]');

		$this->assertEquals(1, $menus->length, 'le menu de la formation est hors de la portée du gabarit, il reste blanc à côté d\'une colonne habillée : poser data-learning-design sur un ancêtre de .doc-menu dans LearningAiTemplate::getMain()');

		$low = [];
		$lowButton = [];

		$backgrounds = self::pageBackgrounds();

		foreach($this->sheets() as $design => $css) {

			$patterns = self::patterns($css, $design, 'doc');
			$root = self::declarations($css, self::scope('doc', $design));

			foreach(self::HEADER_COLORS as $color) {

				$worst = ['lien' => INF, 'lien courant' => INF, 'section' => INF, 'formation' => INF, 'module' => INF];
				$worstButton = INF;

				foreach($backgrounds as $background) {

					$tokens = array_merge(
						$this->styleTokens($design, 'doc', $color, $background),
						self::tokens($css, self::scope('doc', $design))
					);

					if($patterns === []) {
						$pageFills = $this->backgrounds('var(--learning-background)', $tokens, $color);
					} else {
						$pageFills = $this->patternFills($patterns, $design, 'doc', $tokens, $color);
					}

					// L'aplat du menu ne couvre que `#doc-menu-list` : le bouton Menu, posé hors de la liste, se mesure sur `$pageFills`.
					$fills = isset($tokens['--learning-menu-background']) ? $this->backgrounds($tokens['--learning-menu-background'], $tokens, $color) : $pageFills;

					$text = $root['color'] ?? 'var(--text)';
					$muted = str_ireplace('currentColor', $text, $tokens['--muted'] ?? 'var(--muted)');

					$worst['lien'] = min($worst['lien'], self::ratio($this->resolve('var(--text)', $tokens, $color), $fills));
					$worst['lien courant'] = min($worst['lien courant'], self::ratio($this->resolve('var(--primary-ink)', $tokens, $color), [$this->resolve('var(--color-brand)', $tokens, $color)]));
					$worst['section'] = min($worst['section'], self::ratio($this->resolve('var(--color-neutral)', $tokens, $color), $fills));
					$worst['formation'] = min($worst['formation'], self::ratio($this->resolve('var(--text)', $tokens, $color), $fills));
					$worst['module'] = min($worst['module'], self::ratio($this->resolve($muted, $tokens, $color), $fills));
					$worstButton = min($worstButton, self::ratio($this->resolve('var(--primary-on-light, var(--primary))', $tokens, $color), $pageFills));

				}

				foreach($worst as $kind => $ratio) {

					if($ratio < self::MINIMUM) {
						$low[] = $design.' '.$kind.' sous '.$color.' : '.number_format($ratio, 2, ',', '');
					}

				}

				if($worstButton < self::MINIMUM) {
					$lowButton[] = $design.' sous '.$color.' : '.number_format($worstButton, 2, ',', '');
				}

			}

		}

		$this->assertEquals([], $low, 'texte du menu sous '.number_format(self::MINIMUM, 1, ',', '').':1 sur le fond de la portée : sur une trame, poser --learning-menu-background: var(--background-surface) à la racine du gabarit ; sur un aplat, déclarer dans DESIGN_MATTERS le fond que la feuille peint : '.implode(' · ', $low));
		$this->assertEquals([], $lowButton, 'libellé du bouton Menu sous '.number_format(self::MINIMUM, 1, ',', '').':1 sur le fond de la portée, que --learning-menu-background ne couvre pas : déclarer dans DESIGN_MATTERS le fond que la feuille peint, pour que --primary-on-light soit calculée dessus : '.implode(' · ', $lowButton));

	}

	/**
	 * Les traits répétés (`repeating-*-gradient`) que la racine de la portée pose en
	 * `background-image`, du dessus vers le dessous.
	 *
	 * @return string[]
	 */
	private static function patterns(string $css, string $design, string $scope): array {

		$patterns = [];

		foreach(self::split(self::declarations($css, self::scope($scope, $design))['background-image'] ?? '') as $layer) {

			$call = self::call($layer);

			if($call !== NULL and preg_match('/^repeating-(linear|radial|conic)-gradient$/', $call[0]) === 1) {
				$patterns[] = $call[1];
			}

		}

		return $patterns;

	}

	/**
	 * Chaque combinaison des traits de la trame posée sur le fond de page de la portée.
	 *
	 * @param string[] $patterns
	 * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
	 */
	private function patternFills(array $patterns, string $design, string $scope, array $tokens, string $color): array {

		$matters = LearningSetting::DESIGN_MATTERS[$design][$scope] ?? ['{background}'];
		$page = ($matters[0] === '{background}') ? 'var(--learning-background)' : $matters[0];

		$fills = [$this->resolve($page, $tokens, $color)];

		// Les couches se peignent de la dernière à la première : chaque trait se pose ou non sur ce
		// que les couches du dessous ont laissé.
		foreach(array_reverse($patterns) as $pattern) {

			$next = $fills;

			foreach(self::split($pattern) as $stop) {

				$stopColor = $this->resolve(self::leading($stop), $tokens, $color);

				if($stopColor !== NULL and $stopColor[3] > 0.0) {
					foreach($fills as $fill) {
						$next[] = self::over($stopColor, $fill);
					}
				}

			}

			$fills = $next;

		}

		return $fills;

	}

	/**
	 * Les fonds que la barre propose : celui du produit, puis les deux rangées de la palette, dont la
	 * seconde, plus soutenue. `styleTokens()` les passe à `CommunityLib::getSettings()`, qui rend le
	 * fond posé, adouci : la mesure lit donc la valeur même que la page pose et sur laquelle les
	 * encres sont calculées.
	 *
	 * @return string[]
	 */
	private static function pageBackgrounds(): array {
		return array_merge([CommunityLib::BACKGROUND_DEFAULT], ...array_values(\main\CustomizeUi::CUSTOM_BACKGROUNDS));
	}

	/**
	 * Les jetons que `CustomizeUi::getStyles()` écrit sur la portée pour un réseau de cette couleur
	 * et de ce fond sous le gabarit, plus l'aplat du bandeau de la liste, que
	 * `structure\StructureUi` pose en ligne avec son encre.
	 *
	 * @return array<string, string>
	 */
	private function styleTokens(string $design, string $scope, string $color, string $background): array {

		$eCommunity = new \structure\Community([
			'color' => $color,
			'customBackground' => $background,
			'customDesign' => $design,
			'customTitleFont' => NULL,
			'customFont' => NULL,
		]);

		preg_match('#<style>(.*)</style>#s', CustomizeUi::getStyles(CommunityLib::getSettings($eCommunity), $scope), $match);

		return [
			'--community' => $color,
			'--community-ink' => \structure\Community::getInk($color),
		] + self::tokens($match[1], CustomizeUi::SCOPES[$scope]);

	}

	public function testDetectors(): void {

		$this->assertEquals([230, 230, 230, 1.0], self::round($this->resolve('color-mix(in srgb, #000000 10%, white)', [], '#000000')), 'un mélange en pourcentage du premier');
		$this->assertEquals([0, 0, 0, 0.06], self::round($this->resolve('#00000010', [], '#000000')), 'un hexadécimal à alpha');
		$this->assertEquals([255, 255, 255, 0.7], self::round($this->resolve('rgb(255 255 255 / 70%)', [], '#000000')), 'rgb() à alpha');
		$this->assertEquals([47, 107, 79, 1.0], self::round($this->resolve('var(--primary)', [], '#2f6b4f')), 'la couleur du réseau');
		$this->assertEquals([61, 48, 36, 1.0], self::round($this->resolve('var(--text)', ['--text' => '#3d3024'], '#000000')), 'un jeton de la portée avant celui du socle');
		$this->assertEquals([33, 37, 41, 1.0], self::round($this->resolve('var(--text)', [], '#000000')), 'un jeton du socle');
		$this->assertEquals([1, 2, 3, 1.0], self::round($this->resolve('var(--absent, #010203)', [], '#000000')), 'le repli d\'un jeton absent');
		$this->assertEquals(NULL, $this->resolve('repeating-linear-gradient(58deg, #00000010 0 1px, transparent 1px 7px)', [], '#000000'), 'un dégradé n\'est pas une couleur');

		$this->assertEquals(
			[[239, 227, 204, 1.0], [224, 213, 191, 1.0]],
			array_map([self::class, 'round'], $this->backgrounds('repeating-linear-gradient(58deg, #00000010 0 1px, transparent 1px 7px), #efe3cc', [], '#000000')),
			'la trame se pose sur la dernière couche'
		);
		$this->assertEquals(
			[[238, 241, 246, 1.0]],
			array_map([self::class, 'round'], $this->backgrounds('linear-gradient(var(--primary), var(--primary)) no-repeat left / 3px 100%, #eef1f6', [], '#000000')),
			'un filet borné par sa taille ne passe pas sous le texte'
		);
		$this->assertEquals([], $this->backgrounds('linear-gradient(white, black)', [], '#000000'), 'un fond sans couleur opaque ne se mesure pas');

		// Les deux défauts de la recette A3d, que la sentinelle doit trouver : l'avertissement du
		// socle sur le kraft, l'auteur de citation sur une teinte de 10 %.
		$this->assertTrue(self::ratio($this->resolve('var(--warning)', [], '#000000'), [$this->resolve('#efe3cc', [], '#000000')]) < self::MINIMUM, 'l\'avertissement du socle sur le kraft');
		$this->assertTrue(self::ratio($this->resolve(\structure\Community::INK_MUTED, [], '#000000'), $this->backgrounds('color-mix(in srgb, var(--primary) 10%, white)', [], '#000000')) < self::MINIMUM, 'le gris sur une teinte noire de 10 %');
		$this->assertTrue(self::ratio($this->resolve(\structure\Community::INK_MUTED, [], '#000000'), $this->backgrounds('color-mix(in srgb, var(--primary) 5%, white)', [], '#000000')) >= self::MINIMUM, 'le gris sur une teinte noire de 5 %');

	}

	/**
	 * Les encres atténuées de la portée : `--muted` de la feuille, sinon le gris que
	 * `CommunityLib::getInks()` choisit.
	 *
	 * Un `currentColor` dans `--muted` se résout là où le jeton est lu, et non sur la racine de la
	 * portée : dans `color: var(--muted)`, c'est l'encre que l'élément atténué hérite de son parent,
	 * et sous un lien ou un bouton du réseau, l'encre de texte du réseau (`--primary-on-light`), que
	 * `CommunityLib::getInks()` arrête à 4,5:1 sur le fond. Une atténuation de cette encre-là tombe
	 * sous le seuil sans que l'encre de la racine le montre, et le jeton est mesuré sous chacune des
	 * deux.
	 *
	 * @return string[]
	 */
	private static function getMuted(string $css, string $design, string $scope): array {

		$declarations = self::declarations($css, self::scope($scope, $design));

		if(isset($declarations['--muted']) === FALSE) {
			return [self::getMutedInk($design, $scope)];
		}

		if(stripos($declarations['--muted'], 'currentColor') === FALSE) {
			return [$declarations['--muted']];
		}

		return array_map(
			fn(string $ink) => str_ireplace('currentColor', $ink, $declarations['--muted']),
			[$declarations['color'] ?? 'var(--text)', 'var(--primary-on-light, var(--primary))']
		);

	}

	/**
	 * Le gris le plus clair que `CommunityLib::getInks()` puisse choisir pour la portée : le fond du
	 * réseau y est pris blanc, le plus clair qu'il puisse être, sur lequel le premier gris essayé
	 * tient toujours.
	 */
	private static function getMutedInk(string $design, string $scope): string {

		$matters = LearningSetting::DESIGN_MATTERS[$design][$scope] ?? ['{background}'];

		return \structure\Community::getMutedInk(...array_map(fn(string $matter) => ($matter === '{background}') ? '#ffffff' : $matter, $matters));

	}

	/**
	 * Le plus faible contraste de l'encre sur les fonds, une encre translucide posée sur chacun.
	 *
	 * @param array<int, array{0: float, 1: float, 2: float, 3: float}> $backgrounds
	 */
	private static function ratio(?array $ink, array $backgrounds): float {

		if($ink === NULL) {
			return 0.0;
		}

		$ratio = INF;

		foreach($backgrounds as $background) {
			$ratio = min($ratio, \util\ColorUi::getContrastWith(self::hex($background), self::hex(self::over($ink, $background))) ?? 0.0);
		}

		return $ratio;

	}

	/**
	 * Les fonds que rencontre un texte posé sur la valeur : sa dernière couche, opaque, puis cette
	 * couche sous chaque couleur des dégradés qui la recouvrent. Un dégradé suivi d'une taille ou
	 * d'une position (`no-repeat left / 3px 100%`) est un filet au bord, que le texte ne croise pas.
	 * Vide quand la dernière couche n'est pas une couleur opaque. Avec `$gradientBase`, une dernière
	 * couche en dégradé de couleurs opaques compte par chacun de ses arrêts : la luminance d'un
	 * dégradé sRGB reste entre celles de ses bouts.
	 *
	 * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
	 */
	private function backgrounds(string $value, array $tokens, string $color, bool $gradientBase = FALSE): array {

		$layers = self::split($value);
		$last = array_pop($layers);
		$base = $this->resolve($last, $tokens, $color);

		if($base !== NULL) {
			$bases = ($base[3] < 1.0) ? [] : [$base];
		} else if($gradientBase) {
			$bases = $this->gradientStops($last, $tokens, $color);
		} else {
			$bases = [];
		}

		if($bases === []) {
			return [];
		}

		$backgrounds = $bases;

		foreach($layers as $layer) {

			$call = self::call($layer);

			if(
				$call === NULL or
				$call[2] !== '' or
				preg_match('/^(repeating-)?(linear|radial|conic)-gradient$/', $call[0]) === 0
			) {
				continue;
			}

			foreach(self::split($call[1]) as $stop) {

				$stopColor = $this->resolve(self::leading($stop), $tokens, $color);

				if($stopColor !== NULL and $stopColor[3] > 0.0) {
					foreach($bases as $eachBase) {
						$backgrounds[] = self::over($stopColor, $eachBase);
					}
				}

			}

		}

		return $backgrounds;

	}

	/**
	 * Les arrêts d'un dégradé, s'ils sont tous des couleurs opaques ; vide sinon. Ce qui ne se lit
	 * pas comme une couleur (l'angle, la direction) est écarté.
	 *
	 * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
	 */
	private function gradientStops(string $value, array $tokens, string $color): array {

		$call = self::call($value);

		if(
			$call === NULL or
			$call[2] !== '' or
			preg_match('/^(repeating-)?(linear|radial|conic)-gradient$/', $call[0]) === 0
		) {
			return [];
		}

		$stops = [];

		foreach(self::split($call[1]) as $stop) {

			$stopColor = $this->resolve(self::leading($stop), $tokens, $color);

			if($stopColor === NULL) {
				continue;
			}

			if($stopColor[3] < 1.0) {
				return [];
			}

			$stops[] = $stopColor;

		}

		return $stops;

	}

	/**
	 * Une couleur CSS en `[r, g, b, alpha]` : hexadécimal sur 3, 6 ou 8 chiffres, `white`, `black`,
	 * `transparent`, `rgb()`, `color-mix(in srgb, …)` et `var()`, lu dans la portée puis dans
	 * `reboot.css`. `--primary` et `--secondary` valent la couleur du réseau, que
	 * `CustomizeUi::getColorTokens()` pose sur les deux, sauf si la portée les redéfinit ; sans
	 * couleur de réseau, `--secondary` est en réalité celle de la commercialisation, qu'aucun fond
	 * d'encadré ni de citation ne lit. NULL pour ce qui n'est pas une couleur.
	 *
	 * @return array{0: float, 1: float, 2: float, 3: float}|null
	 */
	private function resolve(string $value, array $tokens, string $color, int $depth = 0): ?array {

		$value = trim($value);

		if($depth > 10) {
			return NULL;
		}

		if(preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $match)) {

			$hex = $match[1];

			if(strlen($hex) === 3) {
				$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
			}

			$channels = array_map('hexdec', str_split($hex, 2));

			return [(float)$channels[0], (float)$channels[1], (float)$channels[2], isset($channels[3]) ? $channels[3] / 255 : 1.0];

		}

		$named = [
			'white' => [255.0, 255.0, 255.0, 1.0],
			'black' => [0.0, 0.0, 0.0, 1.0],
			'transparent' => [0.0, 0.0, 0.0, 0.0],
		];

		if(isset($named[strtolower($value)])) {
			return $named[strtolower($value)];
		}

		$call = self::call($value);

		if($call === NULL or $call[2] !== '') {
			return NULL;
		}

		[$name, $inner] = $call;

		if($name === 'var') {

			$arguments = self::split($inner);
			$token = $arguments[0];

			if(isset($tokens[$token])) {
				return $this->resolve($tokens[$token], $tokens, $color, $depth + 1);
			}

			if(in_array($token, ['--primary', '--secondary'], TRUE)) {
				return $this->resolve($color, $tokens, $color, $depth + 1);
			}

			if(isset($this->reboot[$token])) {
				return $this->resolve($this->reboot[$token], $tokens, $color, $depth + 1);
			}

			return isset($arguments[1]) ? $this->resolve(implode(',', array_slice($arguments, 1)), $tokens, $color, $depth + 1) : NULL;

		}

		if($name === 'rgb' or $name === 'rgba') {

			$parts = preg_split('/[\s,\/]+/', trim($inner));

			if(count($parts) < 3) {
				return NULL;
			}

			$alpha = $parts[3] ?? '1';
			$alpha = str_ends_with($alpha, '%') ? (float)$alpha / 100 : (float)$alpha;

			return [(float)$parts[0], (float)$parts[1], (float)$parts[2], $alpha];

		}

		if($name === 'color-mix') {

			$arguments = self::split($inner);

			if(count($arguments) !== 3 or $arguments[0] !== 'in srgb') {
				return NULL;
			}

			$mixed = [];

			foreach([$arguments[1], $arguments[2]] as $argument) {

				if(preg_match('/^(.*?)\s+([0-9.]+)%$/s', $argument, $match)) {
					$mixed[] = [$this->resolve($match[1], $tokens, $color, $depth + 1), (float)$match[2] / 100];
				} else {
					$mixed[] = [$this->resolve($argument, $tokens, $color, $depth + 1), NULL];
				}

			}

			[[$first, $firstShare], [$second, $secondShare]] = $mixed;

			if($first === NULL or $second === NULL) {
				return NULL;
			}

			$firstShare ??= ($secondShare === NULL) ? 0.5 : 1 - $secondShare;
			$secondShare ??= 1 - $firstShare;

			$total = $firstShare + $secondShare;
			$firstShare /= $total;
			$secondShare /= $total;

			// Le mélange se fait en alpha prémultiplié : une couleur mêlée à `transparent` garde sa
			// teinte et ne prend que la transparence.
			$alpha = $first[3] * $firstShare + $second[3] * $secondShare;

			if($alpha === 0.0) {
				return [0.0, 0.0, 0.0, 0.0];
			}

			$channels = [];

			foreach([0, 1, 2] as $index) {
				$channels[] = ($first[$index] * $first[3] * $firstShare + $second[$index] * $second[3] * $secondShare) / $alpha;
			}

			return [...$channels, $alpha];

		}

		return NULL;

	}

	/**
	 * La couleur posée sur un fond opaque.
	 */
	private static function over(array $top, array $base): array {

		$channels = [];

		foreach([0, 1, 2] as $index) {
			$channels[] = $top[$index] * $top[3] + $base[$index] * (1 - $top[3]);
		}

		return [...$channels, 1.0];

	}

	private static function hex(array $rgba): string {
		return vsprintf('#%02x%02x%02x', array_map(fn(float $channel) => (int)round($channel), array_slice($rgba, 0, 3)));
	}

	private static function round(?array $rgba): ?array {

		if($rgba === NULL) {
			return NULL;
		}

		return [(int)round($rgba[0]), (int)round($rgba[1]), (int)round($rgba[2]), round($rgba[3], 2)];

	}

	/**
	 * Un appel de fonction en tête de la valeur, ses parenthèses équilibrées : son nom, ce qu'elles
	 * contiennent et ce qui le suit. NULL si la valeur ne commence pas par un appel.
	 *
	 * @return array{0: string, 1: string, 2: string}|null
	 */
	private static function call(string $value): ?array {

		$value = trim($value);

		if(preg_match('/^([a-z-]+)\(/i', $value, $match) === 0) {
			return NULL;
		}

		$open = strlen($match[1]);
		$depth = 0;

		for($index = $open; $index < strlen($value); $index++) {

			if($value[$index] === '(') {
				$depth++;
			} else if($value[$index] === ')') {

				$depth--;

				if($depth === 0) {
					return [strtolower($match[1]), substr($value, $open + 1, $index - $open - 1), trim(substr($value, $index + 1))];
				}

			}

		}

		return NULL;

	}

	/**
	 * Ce qui ouvre un arrêt de dégradé, sa couleur avant ses positions : un appel, un hexadécimal ou
	 * un mot.
	 */
	private static function leading(string $stop): string {

		$stop = trim($stop);
		$call = self::call($stop);

		if($call !== NULL) {
			return substr($stop, 0, strlen($stop) - strlen($call[2]));
		}

		return preg_split('/\s+/', $stop)[0];

	}

	/**
	 * La valeur coupée à ses virgules de premier niveau, hors des parenthèses.
	 *
	 * @return string[]
	 */
	private static function split(string $value): array {

		$parts = [];
		$depth = 0;
		$current = '';

		foreach(str_split($value) as $character) {

			if($character === '(') {
				$depth++;
			} else if($character === ')') {
				$depth--;
			}

			if($character === ',' and $depth === 0) {
				$parts[] = trim($current);
				$current = '';
			} else {
				$current .= $character;
			}

		}

		$parts[] = trim($current);

		return $parts;

	}

	/**
	 * Ce qui s'applique sous chaque gabarit, par nom de gabarit : la couche commune de `learning.css`,
	 * écrite pour ce gabarit, puis sa feuille. Les encres que les dix gabarits posaient à l'identique
	 * vivent dans la couche commune ; lue sans elle, une feuille retomberait sur l'encre du socle
	 * (`HEADER`) et serait jugée sur une encre qu'elle n'affiche pas. La feuille vient après, et
	 * l'emporte ici quand elle redéclare une valeur : c'est l'ordre de la fiche, où `learning.css` se
	 * charge avant elle.
	 *
	 * @return array<string, string>
	 */
	private function sheets(): array {

		$common = self::common(file_get_contents(LIME_DIRECTORY.'/'.self::DIRECTORY.'/learning.css'));

		$sheets = [];

		foreach(LearningSetting::DESIGNS as $design) {
			$sheets[$design] = str_replace('[data-learning-design]', '[data-learning-design="'.$design.'"]', $common)."\n".file_get_contents(LIME_DIRECTORY.'/'.self::DIRECTORY.'/design-'.$design.'.css');
		}

		return $sheets;

	}

	/**
	 * Les règles de `learning.css` dont chaque sélecteur est borné par `[data-learning-design]`, sans
	 * nom de gabarit : elles valent sous tous. Les règles imbriquées d'une `@media` n'en font pas
	 * partie, la mesure se faisant hors de toute largeur.
	 */
	private static function common(string $css): string {

		$css = preg_replace('#/\*.*?\*/#s', '', $css);
		$css = preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);

		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

		$common = [];

		foreach($rules as [$rule, $selectors]) {

			$parts = array_map('trim', explode(',', $selectors));

			if(array_filter($parts, fn(string $part) => str_contains($part, '[data-learning-design]') === FALSE) === []) {
				$common[] = trim($rule);
			}

		}

		return implode("\n", $common);

	}

	private static function scope(string $scope, string $design): string {
		return '.learning-'.$scope.'[data-learning-design="'.$design.'"]';
	}

	/**
	 * Les jetons qu'une feuille pose sur un sélecteur, la dernière déclaration l'emportant, quand il
	 * figure tel quel dans la liste d'une règle : la racine d'une portée, `:root`.
	 *
	 * @return array<string, string>
	 */
	private static function tokens(string $css, string $selector): array {

		return array_filter(self::declarations($css, $selector), fn(string $property) => str_starts_with($property, '--'), ARRAY_FILTER_USE_KEY);

	}

	/**
	 * Les déclarations qu'une feuille pose sur un sélecteur, jetons compris, la dernière l'emportant,
	 * quand il figure tel quel dans la liste d'une règle.
	 *
	 * @return array<string, string>
	 */
	private static function declarations(string $css, string $selector): array {

		$css = preg_replace('#/\*.*?\*/#s', '', $css);

		preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

		$values = [];

		foreach($rules as [, $selectors, $body]) {

			$selectors = array_map(fn(string $part) => trim(preg_replace('/\s+/', ' ', $part)), explode(',', $selectors));

			if(in_array($selector, $selectors, TRUE) === FALSE) {
				continue;
			}

			preg_match_all('/(?:^|;)\s*(-{0,2}[a-z][a-z0-9-]*)\s*:([^;]+)/i', $body, $declarations, PREG_SET_ORDER);

			foreach($declarations as [, $property, $value]) {
				$values[$property] = trim($value);
			}

		}

		return $values;

	}

}
?>
