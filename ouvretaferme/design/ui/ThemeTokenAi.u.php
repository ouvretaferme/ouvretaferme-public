<?php
namespace design;

/**
 * La section des jetons de thème de la page du design system (#design-themeTokens) : les variables
 * qu'un thème pose pour habiller les composants du socle sans réécrire leurs règles, rangées par rôle,
 * avec le composant qui lit chacune et la valeur qu'il prend quand personne ne la pose.
 *
 * Le premier usage est celui des thèmes de formations des réseaux (chantier e-learning, lot A3a) : le
 * thème pose ses jetons sous un élément propre aux écrans de formations, et chaque composant les lit
 * par `var(--jeton, repli)`, le repli valant la valeur du socle. Hors d'un thème, rien ne change.
 *
 * La démonstration montre deux fois les mêmes composants, au socle puis sous `design-theme-demo`
 * (designAi.css), qui pose quelques jetons comme le ferait un thème.
 */
class ThemeTokenAiUi {

	public function tokens(): string {

		$h = '<p>'.s("Un thème habille les composants du socle en posant des variables, jamais en réécrivant leurs règles.").'</p>';
		$h .= '<p class="util-annotation">'.s("Chaque composant lit son jeton avec un repli égal à la valeur du socle, si bien qu'un écran sans thème ne change pas.").'</p>';
		$h .= '<p class="util-annotation">'.s("Un thème pose ses jetons sous le sélecteur d'un élément propre aux écrans de son package, jamais sur body, sur :root ni sur une classe du socle, parce que les feuilles de package restent chargées après une navigation ajax.").' '.s("La feuille d'un thème ne vise aucune classe util-*, btn, tab-* ou editor-* : pour un composant, des jetons ou rien.").' '.s("Une propriété déjà posée par la règle d'un composant se lit dans sa déclaration, à sa place, pour que la spécificité ne change pas. Une propriété qu'aucune règle ne pose se lit dans une règle neuve sous :where(), avec la valeur initiale ou héritée pour repli.").' '.s("Un jeton neuf porte le nom de son rôle (--callout-*, --marker-*), jamais celui d'un package, et deux jetons n'ont jamais la même valeur dans tous les thèmes : l'un des deux serait de trop.").' '.s("Un texte, une icône ou une flèche du socle écrit en couleur de marque sur un fond clair lit --primary-on-light, avec pour repli la couleur qu'il prend sans thème, --color-brand le plus souvent. C'est l'encre que la page d'un réseau calcule pour qu'elle reste lisible sur son fond et sur le blanc des cartes (learning, Customize.u.php), alors que les fonds, les pleins et les filets gardent la couleur du réseau.").' '.s("Un composant qui lit d'abord --domain, comme util-more ou la valeur d'une tuile, garde ce jeton, propre aux écrans de structure.").'</p>';

		$h .= $this->getDemo();

		$notes = $this->getNotes();

		foreach($this->getRoles() as $role => $tokens) {

			$h .= '<h4 class="mt-2">'.$role.'</h4>';
			if(isset($notes[$role])) {
				$h .= '<p class="util-annotation">'.$notes[$role].'</p>';
			}
			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Jeton").'</th>';
						$h .= '<th>'.s("Lu par").'</th>';
						$h .= '<th>'.s("Sans thème").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';
					foreach($tokens as $token => [$readers, $fallback]) {
						$h .= '<tr>';
							$h .= '<td><code>'.$token.'</code></td>';
							$h .= '<td>'.$readers.'</td>';
							$h .= '<td><code>'.$fallback.'</code></td>';
						$h .= '</tr>';
					}
				$h .= '</tbody>';
			$h .= '</table>';

		}

		return $h;

	}

	private function getDemo(): string {

		$h = '<div class="design-pair">';
			$h .= '<div>';
				$h .= '<h4>'.s("Sans thème").'</h4>';
				$h .= $this->getDemoComponents();
			$h .= '</div>';
			$h .= '<div class="design-theme-demo">';
				$h .= '<h4>'.s("Sous un thème qui pose six jetons").'</h4>';
				$h .= $this->getDemoComponents();
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	private function getDemoComponents(): string {

		$h = \main\DesignUi::getAccentCard('info', NULL, s("Encadré"), '<p>'.s("Le fond, le filet et le liseré viennent du thème.").'</p>');

		$h .= '<ol class="util-procedure">';
			foreach([s("Semer"), s("Repiquer")] as $step) {
				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.$step.'</h4>';
				$h .= '</li>';
			}
		$h .= '</ol>';

		$h .= '<div class="tabs tabs-h mb-1">';
			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected">'.s("Leçon").'</a>';
				$h .= '<a class="tab-item">'.s("Quiz").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= \util\DateUi::calendar('2026-10-15');

		return $h;

	}

	/**
	 * Ce qu'un rôle demande de savoir avant de poser ses jetons, quand le tableau ne le dit pas.
	 *
	 * @return array<string, string>
	 */
	private function getNotes(): array {

		return [
			s("Liste de suggestions") => s("Les deux jetons se règlent ensemble par util-brand-scope-light, posé avec util-brand-scope quand l'encre pleine de la marque tient moins de 4,5:1 sur le fond de la liste. --omnibox-muted-tint, une part de mélange, est déclaré sur :root (reboot.css) avec les autres ; --omnibox-active-background garde son repli dans son var(), qui dépend de l'encre de la liste."),
			s("Boutons") => s("--button-outline-background pose un fond sous un bouton contour au repos, qui n'en a pas au socle. Il existe pour un thème qui peint une trame en background-image sous le texte : l'encre du bouton est calculée sur le fond et les surfaces, et le croisement des traits peut la faire tomber sous 4,5:1. Kraft et Futur le posent, sur la page d'un réseau. Un thème sans trame ne le pose pas. Le survol d'un contour mêle sa couleur à ce fond, et la sentinelle DesignContrastSentinel mesure l'encre sur la trame de chaque gabarit."),
		];

	}

	/**
	 * @return array<string, array<string, array{0: string, 1: string}>>
	 */
	private function getRoles(): array {

		return [
			s("Filet") => [
				'--border-width' => ['util-card, util-deck, util-calendar', '1px'],
			],
			s("Titres") => [
				'--heading-case' => ['util-title > h2, h3', 'inherit'],
				'--heading-tracking' => ['util-title > h2, h3', 'inherit'],
				'--heading-weight' => ['util-title > h2, h3 (root.css), doc-content h3', 'lighter · normal'],
				'--heading-border' => ['util-title > h2, h3, doc-content h2', 'none · 2px dashed var(--color-neutral-variant)'],
				'--heading-background' => ['util-title > h2, h3', 'transparent'],
				'--heading-color' => ['util-title > h2, h3, doc-content util-title > h2', 'inherit · var(--text)'],
				'--heading-padding' => ['util-title > h2, h3', '0'],
				'--heading-rotate' => ['util-title > h2, h3', 'none'],
				'--heading-clip' => ['util-title > h2, h3', 'none'],
				'--heading-justify' => ['util-title', 'space-between'],
			],
			s("Boutons") => [
				'--button-case' => ['btn', 'none'],
				'--button-weight' => ['btn, util-page-header-aside btn, util-page-header-title util-chip', '500'],
				'--button-outline-background' => ['btn-outline-primary, -secondary, -success, -warning, -danger, -brand, -accounting, -commercialisation, -order et -muted au repos, et le fond que le survol des contours de couleur teinte', 'transparent'],
			],
			s("Liste de suggestions") => [
				'--omnibox-muted-tint' => ['util-omnibox-group, -rest, -item-detail, -item-sub et -notice-muted', '70%'],
				'--omnibox-active-background' => ['util-omnibox-item au survol et au curseur, li d\'autocomplete-list', 'color-mix(in srgb, var(--primary-ink) 13.3%, transparent)'],
			],
			s("Chiffre clé") => [
				'--figure-background' => ['util-figure', 'var(--color-bg-vert-bleu)'],
			],
			s("Encadré") => [
				'--callout-background' => ['util-card-accent', 'var(--background-surface)'],
				'--callout-border' => ['util-card-accent', 'var(--border)'],
				'--callout-accent-width' => ['util-card-accent', '0.5rem'],
				'--callout-accent-style' => ['util-card-accent', 'solid'],
				'--callout-radius' => ['util-card-accent', 'var(--radius)'],
				'--callout-shadow' => ['util-card-accent', 'var(--shadow-v2)'],
				'--callout-title-color' => ['util-card-accent > util-card-title', 'var(--accent-on-light, var(--accent))'],
				'--callout-title-case' => ['util-card-accent > util-card-title', 'none'],
			],
			s("Étapes") => [
				'--marker-counter' => ['util-procedure-step::before', 'decimal'],
				'--marker-size' => ['util-procedure, colonne, pastille et trait de util-procedure-step', '2rem'],
				'--marker-start' => ['util-procedure, premier numéro de la suite', '1'],
				'--marker-radius' => ['util-procedure-step::before', '50%'],
				'--marker-background' => ['util-procedure-step::before', 'var(--primary)'],
				'--marker-color' => ['util-procedure-step::before, et ::after sous util-procedure-check', 'var(--primary-ink)'],
				'--marker-ring' => ['util-procedure-step::before', 'none'],
				'--marker-offset' => ['util-procedure-icon, descente de la pastille, départ du trait et son allongement par --step-line-end, posée à --space-xs quand l\'étape porte un util-deck', '0'],
				'--step-line' => ['util-procedure-step::after', 'var(--primary) à 25 %'],
				'--step-line-end' => ['util-procedure-step::after, écart entre la fin du trait et le bas de l\'étape', '0.25rem'],
			],
			s("Tableau") => [
				'--table-head-background' => ['util-table-head', 'transparent · var(--background-surface)'],
				'--table-head-color' => ['th, util-table-header, util-table-head', 'var(--text-intermediate)'],
				'--table-head-case' => ['th, util-table-header, util-table-head', 'uppercase'],
				'--table-head-border' => ['util-table-head', '2px solid var(--secondary-neutral)'],
				'--table-row-border' => ['util-table-row', '0'],
			],
			s("Onglets") => [
				'--tab-active-color' => ['tab-item survolé ou choisi', 'var(--primary-on-light, var(--secondary-light))'],
				'--tab-active-background' => ['tab-item survolé ou choisi', 'transparent'],
				'--tab-radius' => ['tab-item survolé ou choisi', '0'],
				'--tab-bar-height' => ['tab-item::after', '5px'],
			],
			s("Citation") => [
				'--quote-background' => ['editor-quote d\'une liste de blocs', 'none'],
				'--quote-border' => ['editor-quote d\'une liste de blocs', 'none'],
				'--quote-radius' => ['editor-quote d\'une liste de blocs', '0'],
				'--quote-padding' => ['editor-quote d\'une liste de blocs', '0'],
				'--quote-icon-color' => ['editor-quote-image d\'une liste de blocs', 'var(--muted)'],
				'--quote-font-size' => ['blockquote d\'une liste de blocs', 'inherit'],
				'--quote-font-style' => ['blockquote d\'une liste de blocs', 'inherit'],
				'--quote-author-color' => ['util-annotation d\'une citation d\'une liste de blocs', 'var(--muted)'],
			],
			s("Cartes de liste") => [
				'--deck-head-image' => ['util-deck-head', 'none'],
				'--deck-rotate' => ['util-deck', 'none'],
				'--deck-rotate-even' => ['util-deck pair', 'var(--deck-rotate, none)'],
				'--deck-clip' => ['util-deck', 'none'],
				'--deck-radius' => ['util-deck, ses étages extrêmes et ses plis', 'var(--radius)'],
			],
			s("Calendrier") => [
				'--calendar-size' => ['util-calendar', '2.5rem'],
				'--calendar-radius' => ['util-calendar', 'var(--radius)'],
				'--calendar-ring' => ['util-calendar', 'none'],
				'--calendar-background' => ['util-calendar', 'var(--calendar) à 12 % sur la surface'],
				'--calendar-color' => ['util-calendar', 'var(--calendar) à 78 % sur le contraste'],
				'--calendar-contrast' => ['util-calendar', 'black'],
				'--calendar-day-size' => ['util-calendar-day', '1.15rem'],
			],
			s("Pages publiques") => [
				'--page-hero-background' => ['util-page-hero, learning-lesson-position, l\'étape choisie de learning-reader', 'var(--color-bg-turquoise)'],
				'--page-hero-disc-large' => ['util-page-hero, grand disque en haut à droite', 'var(--color-lavande) à 18 % sur blanc'],
				'--page-hero-disc-small' => ['util-page-hero, petit disque en bas', 'var(--color-brand) à 14 % sur blanc'],
				'--page-hero-ink' => ['util-page-hero h1, util-page-hero-lead, util-label de util-page-hero-top, learning-lesson-position', 'var(--color-encre) · var(--color-brand)'],
				'--icon-raised-color' => ['util-icon-raised, la tuile de util-page-hero-top', 'var(--color-brand)'],
				'--band-background' => ['util-band, hors util-band-alt', 'white'],
			],
			s("Jetons du socle que lisent aussi les thèmes") => [
				'--background-surface' => ['util-card, util-deck-head et ses états, util-table, mélanges du calendrier et des bandes d\'état', 'white'],
				'--primary-on-light' => ['util-figure-value, tab-item, btn-light, util-page-header-back, et les textes du socle en couleur de marque hors de ceux qui lisent --domain en premier', 'var(--color-brand) · var(--secondary-light) · var(--primary)'],
			],
		];

	}

}
?>
