<?php
namespace toolbox;

/**
 * L'écran « Paniers de l'année » d'un plan composé : les deux questions communes à
 * toutes les tailles de panier en haut, les tailles en onglets dessous, chacune avec sa proposition.
 * Le code appelle une taille de panier une formule (`CompositionFormula`), l'écran ne le dit
 * jamais. Tant que la structure n'a pas de plan, l'écran est le départ (`CompositionStartUi`).
 *
 * La forme vient de la planche du débutant (v11, `.claude/state/paniers/L2-paniers-annee/debutant.py`),
 * le balisage des composants du design system. Le prix d'une formule s'y règle sur place ; les
 * semaines livrées, les espèces livrées et l'ajout d'une formule se règlent dans un panneau, dont
 * la réponse ne rend que les blocs qu'elle change (`getWeeks()` et `getProposal()` sont faits de
 * blocs à identifiant pour cela). Les gestes qui corrigent une semaine viennent avec le lot 7, qui
 * les posera sur ces mêmes blocs.
 *
 * Tout ce qui se compte ou se somme arrive calculé (`CompositionWeekLib::getByFormulaWithLines()`,
 * `CompositionLib::getSummary()`, `CompositionLib::getGrid()`, `CompositionLib::getBands()`) : ce
 * fichier ne fait que placer et écrire.
 */
class CompositionUi {

	public function __construct() {
		\Asset::css('toolbox', 'composition.css');
		\Asset::js('toolbox', 'composition.js');
	}

	/**
	 * Un outil de la Boîte à outils (planche r2, point 9), à l'adresse de ses voisins. L'ancienne,
	 * `/ferme/<id>/paniers`, renvoie ici (`page/composition.p.php`).
	 */
	public static function url(\structure\Structure $eStructure): string {
		return ToolUi::url($eStructure).'/paniers';
	}

	/**
	 * L'adresse d'un panneau de l'écran. Elle porte la formule qu'on lit, que le panneau réémet : sa
	 * réponse rend alors la même formule, et non la première.
	 */
	protected static function urlPanel(string $page, CompositionPlan $eCompositionPlan, string $structureParameter = 'id'): string {

		return $page.'?'.http_build_query([
			$structureParameter => $eCompositionPlan['structure']['id'],
			'formula' => $eCompositionPlan['eCompositionFormula']['id'],
		]);

	}

	/**
	 * Le départ (`CompositionStartUi`), que « Recommencer les paniers » rouvre avec les réponses du
	 * plan déjà posées, et que son menu des saisons rouvre sur le plan de culture choisi.
	 */
	public static function urlStart(\structure\Structure $eStructure, ?int $season = NULL): string {
		return self::url($eStructure).'/depart'.($season === NULL ? '' : '?season='.$season);
	}

	/**
	 * Le PDF des paniers de l'année (lot L4), toutes tailles, quelle que soit celle qu'on lit.
	 */
	public static function urlPdf(\structure\Structure $eStructure): string {
		return '/toolbox/composition:downloadPdf?id='.$eStructure['id'];
	}

	/**
	 * La notice de l'outil (r35), que la roue de l'en-tête ouvre en panneau.
	 */
	public static function urlNotice(\structure\Structure $eStructure): string {
		return '/toolbox/composition:notice?id='.$eStructure['id'];
	}

	public static function urlSynthesis(\structure\Structure $eStructure): string {
		return self::url($eStructure).'/synthese';
	}

	public static function urlSpecies(\structure\Structure $eStructure): string {
		return self::url($eStructure).'/especes';
	}

	public static function urlFormula(\structure\Structure $eStructure, CompositionFormula $eCompositionFormula): string {
		return self::url($eStructure).'?formula='.$eCompositionFormula['id'];
	}

	/**
	 * Les onglets ouverts sans recharger la page (chantier Performances, P14), dont `urlSynthesis()`,
	 * `urlSpecies()` et `urlFormula()` restent les adresses.
	 */
	public static function urlSynthesisTab(\structure\Structure $eStructure): string {
		return '/toolbox/composition:tab?id='.$eStructure['id'].'&tab='.CompositionPlan::TAB_SYNTHESIS;
	}

	public static function urlSpeciesTab(\structure\Structure $eStructure): string {
		return '/toolbox/composition:tab?id='.$eStructure['id'].'&tab='.CompositionPlan::TAB_SPECIES;
	}

	public static function urlFormulaTab(\structure\Structure $eStructure, CompositionFormula $eCompositionFormula): string {
		return '/toolbox/composition:tab?id='.$eStructure['id'].'&formula='.$eCompositionFormula['id'];
	}

	/**
	 * L'en-tête d'outil de la Boîte à outils (planche r2, point 9), sans saison : retour à la Boîte,
	 * le titre, et l'icône de la Boîte, dont le menu ne porte que ce qu'il porte pour tous les outils
	 * (r31, #96, #98). Les réglages des paniers et « Recommencer les paniers » vivent dans « Régler les
	 * paniers », à côté du titre « Les paniers » (`getSettingsPanel()`). La phrase qui comptait tailles,
	 * semaines et espèces sous le titre est partie (planche r2, point 2) : la carte « Semaines
	 * livrées » et l'onglet « Espèces » portent chacun leur décompte, et les onglets comptent les
	 * tailles.
	 *
	 * Le menu de l'outil ne porte que la notice (r35, planche r35, entrée de la section 1), en première
	 * ligne, avant l'étoile : elle se lit comme l'écran, observateur compris (`page/composition.p.php`,
	 * `notice`).
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite): string {

		$menu = '<a href="'.self::urlNotice($eStructure).'" class="dropdown-item">'.\Asset::icon('info-circle').' '.s("Comment se composent les paniers").'</a>';

		return new ToolUi()->getHeader($eStructure, Favorite::BASKET, $cFavorite, menu: $menu);

	}

	/**
	 * La présentation de l'outil (r36, planche r36, variante A, texte validé le 30/09),
	 * qui remplace la notice de r35 (« inbuvable ») : la promesse en tête, sous la mascotte de
	 * l'accueil de l'outil (`getStart()`), puis cinq étapes numérotées, chacune montrée par un morceau
	 * de l'écran de la structure. Ce que l'outil ne fait pas, qui suivait, est retiré (r55, « vire »,
	 * le 02/10).
	 *
	 * Les morceaux viennent des méthodes qui rendent l'écran, sur les rangs et les semaines que
	 * `CompositionPlanLib::getNotice()` a choisis. Une étape dont le plan n'a pas de quoi rendre
	 * l'illustration (structure sans paniers, taille jamais corrigée ou sans soudure) garde sa phrase
	 * seule. Chaque illustration est `inert` : elle porte les gestes de l'écran (quantités, fréquence,
	 * portes de la soudure), qu'on ne doit pas pouvoir déclencher depuis une image.
	 */
	public function getNoticePanel(CompositionPlan $eCompositionPlan, array $notice): \Panel {

		$figures = [
			'cultivated' => ($notice['cultivated'] === []) ? '' : '<div class="toolbox-composition-notice-figure" inert>'.$this->getNoticeGrid($eCompositionPlan, $notice['cultivated'], $notice['cultivatedWeeks'], FALSE).'</div>',
			'filled' => ($notice['filled'] === NULL) ? '' : $this->getNoticeCard($eCompositionPlan, $notice['filled']),
			'rhythm' => ($notice['rhythm'] === NULL) ? '' : $this->getNoticeRhythm($eCompositionPlan, $notice['rhythm'], $notice['rhythmWeeks']),
			'amended' => ($notice['amended'] === NULL) ? '' : $this->getNoticeCard($eCompositionPlan, $notice['amended']),
			'lean' => ($notice['lean'] === NULL) ? '' : '<div class="toolbox-composition-notice-figure" inert>'.$this->getLeanPeriod($eCompositionPlan, $eCompositionPlan['eCompositionFormula'], $notice['lean']).'</div>',
		];

		$steps = [
			'cultivated' => [s("Vous cultivez"), s("L'outil part de votre plan de culture, ou d'un calendrier type de votre climat. Il sait quels légumes vous aurez, et à quelles semaines.")],
			'filled' => [s("L'outil remplit les paniers"), s("Chaque semaine, il met vos légumes dans chacun de vos paniers, jusqu'au prix que vous avez choisi.")],
			'rhythm' => [s("Vous choisissez le rythme"), s("Pour chaque légume, vous dites s'il revient souvent ou de temps en temps.")],
			'amended' => [s("Vous gardez la main"), s("Vous changez un légume ou une quantité quand vous voulez. L'outil garde vos changements.")],
			'lean' => [s("L'outil vous prévient"), s("Une semaine manque de légumes ? L'outil vous le dit et vous propose des solutions. Il vous signale aussi quand votre plan de culture et vos paniers ne sont pas aux mêmes mois.")],
		];

		$h = \main\DesignUi::getWelcome(
			s("Des pistes pour composer vos paniers"),
			'<p>'.s("Une proposition par semaine, tirée de ce que vous récoltez, à ajuster vous-même.").'</p>',
			\Asset::svg('main', 'friends/escargot.svg'),
			class: 'mb-2'
		);

		$h .= '<ol class="util-procedure">';
			foreach($steps as $key => [$title, $text]) {
				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.$title.'</h4>';
					$h .= '<p>'.$text.'</p>';
					$h .= $figures[$key];
				$h .= '</li>';
			}
		$h .= '</ol>';

		return new \Panel(
			id: 'toolbox-composition-notice-panel',
			title: s("Comment se composent les paniers"),
			body: $h
		);

	}

	/**
	 * Des rangs de la grille réduits à quelques semaines, pour la présentation (`getNoticePanel()`) :
	 * le nom et sa vignette, sans prix, fréquence ni roue, et les cases telles que la grille les
	 * rend (`getGridCellAttributes()`), sans leur bulle ni leur quantité. Sans `$served`, les cases
	 * ne montrent que la saison, ce que le plan de culture donne avant tout panier.
	 *
	 * Les rangs ne portent pas l'identifiant des rangs de la grille, que les réponses ciblées
	 * remplacent : ils ne doivent pas pouvoir être pris pour eux.
	 */
	protected function getNoticeGrid(CompositionPlan $eCompositionPlan, array $rows, array $weeks, bool $served): string {

		$h = '<div class="toolbox-composition-grid">';
			$h .= '<table class="table-unstyled util-heat-table">';
				$h .= '<tbody>';

					foreach($rows as $row) {

						$h .= '<tr>';

							$h .= '<td class="toolbox-composition-grid-name">';
								$h .= '<div class="util-vignette">';
									$h .= \plant\PlantUi::getVignette($row['species']['plant'], '1.125rem');
									$h .= '<span class="toolbox-composition-grid-plant">'.encode($row['species']->getLabel()).'</span>';
								$h .= '</div>';
							$h .= '</td>';

							foreach($weeks as $week) {

								$attributes = $this->getGridCellAttributes($eCompositionPlan, $row, $week);
								$attributes['title'] = NULL;
								$attributes['data-quantity'] = NULL;

								if($served === FALSE) {
									$attributes['data-served'] = NULL;
								}

								$h .= '<td '.attrs($attributes).'></td>';

							}

						$h .= '</tr>';

					}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le rang d'une espèce et, ouvert sous lui, le menu de sa fréquence
	 * (`CompositionSpeciesUi::getFrequencyList()`), pour la présentation.
	 */
	protected function getNoticeRhythm(CompositionPlan $eCompositionPlan, array $row, array $weeks): string {

		$h = '<div class="toolbox-composition-notice-figure toolbox-composition-notice-rhythm" inert>';
			$h .= $this->getNoticeGrid($eCompositionPlan, [$row], $weeks, TRUE);
			$h .= CompositionSpeciesUi::getFrequencyList($row['eCompositionSpecies']);
		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte d'une semaine, pour la présentation (`getWeekCard()`).
	 */
	protected function getNoticeCard(CompositionPlan $eCompositionPlan, int $week): string {
		return '<div class="toolbox-composition-notice-figure" inert>'.$this->getWeekCard($eCompositionPlan, $eCompositionPlan['eCompositionFormula'], $week).'</div>';
	}

	/**
	 * L'accueil d'une structure qui n'a jamais composé ses paniers, commun aux outils de la Boîte
	 * (arbitrage Q18 A de la planche pf-l8-v6, repris par la planche r2, point 9) : la vue d'ensemble
	 * de l'outil, puis ce qu'il demandera et montrera (« Ce que vous y trouverez »), puis le geste,
	 * qui ouvre le départ en trois questions. Rien ne s'écrit avant l'envoi du départ.
	 */
	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['basket', s("Vos paniers"), s("leurs noms, leurs prix et combien vous en vendez")],
			['calendar-week', s("Vos semaines"), s("celles que vous livrez")],
			['leaf', s("Vos cultures"), s("les espèces que vous livrez et leur saison")],
		];

		$content = '<p>'.s("À partir de vos cultures et du prix de vos paniers, composez le contenu de chaque semaine livrée et voyez les quantités à produire sur l'année.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('basket'),
			s("Paniers de l'année"),
			$content,
			image: \Asset::image('main', 'friends/petit-pois.svg'),
			buttons: '<a href="'.self::urlStart($eStructure).'" class="btn btn-primary btn-xl">'.s("Composer mes paniers").'</a>'
		);

	}

	/**
	 * « Modifier les paniers » (r31, #96, #98), les tailles en vis-à-vis (r32, planche r32, question 1) :
	 * une colonne par taille dans l'ordre des onglets, un rang par champ du describer
	 * (`CompositionFormulaUi::p()`), dont le libellé se lit une fois à gauche, puis un seul
	 * « Enregistrer ». Le rang « Nom » sert d'intitulé aux colonnes. Chaque champ se nomme par sa taille
	 * (`price[<id>]`), que la page lit par `buildIndex()` (`composition.p.php`, `doSettings`), et porte
	 * son propre `data-wrapper` : un refus se range sous la case de la taille fautive, pas sous tout le
	 * rang.
	 *
	 * Le rang dit déjà l'unité : « paniers » et « produits » quittent les champs, « € » et « % »
	 * restent. Les rappels de bornes partent aussi, que le schéma tient et que le refus dit ; sous
	 * l'écart, chaque colonne garde le montant qu'il vaut pour son prix (`gapAmount`).
	 *
	 * Une taille qui porte des semaines corrigées à la main dit sur son nom, en tête de sa colonne, ce
	 * qu'une recomposition lui ferait perdre (`data-loss`, `CompositionFormulaUi::getRecomposeLoss()`),
	 * et l'envoi le fait confirmer si l'un de ses champs qui recomposent a changé (`composition.js`).
	 *
	 * Chaque taille se lit comme un bloc (r34b, #111, variante A) : ses cases portent un fond d'un
	 * seul tenant, du rang « Nom » au rang « S'appuie sur », qui portent les coins arrondis
	 * (`toolbox-composition-settings-row-first`, `-last`), et le blanc entre deux fonds sépare les
	 * tailles. Les groupes du panneau se marquent (`toolbox-composition-settings-group`) pour que leur
	 * espace vertical passe à la case, sans quoi le fond se couperait d'un rang à l'autre.
	 *
	 * En tête, à droite, « Ajouter un panier » (r51b, planche r51, piste A), à la place et sous la
	 * forme de « Ajouter une espèce » dans l'onglet « Espèces ». Il ouvre le panneau d'ajout
	 * (`CompositionFormulaUi::create()`) par-dessus celui-ci, qui se rend de nouveau après l'ajout,
	 * une colonne de plus (`compositionFormula:doCreate`) : un réglage tapé et pas encore enregistré
	 * n'y survit pas. Le lien porte la phrase qui le dit (`data-question`), que le clic ne pose en
	 * confirmation que si un champ a changé (`composition.js`), comme l'envoi le fait pour ce qu'il
	 * recompose.
	 *
	 * En bas, hors du formulaire, « Recommencer les paniers » dans un encadré à part
	 * (`util-block-optional`), avec la confirmation qui chiffre ce que le départ remplacera
	 * (`getRestartConfirm()`). C'est un lien vers le départ et non une action : l'ouvrir ne touche à
	 * rien, c'est son envoi qui remplace le plan. Le panneau se lit à chaque ouverture, donc le message
	 * est toujours celui du plan tel qu'il est.
	 */
	public function getSettingsPanel(CompositionPlan $eCompositionPlan, ?int $formula, ?string $origin): \Panel {

		$form = new \util\FormUi();

		$urlCreate = '/toolbox/compositionFormula:create?'.http_build_query([
			'structure' => $eCompositionPlan['structure']['id'],
			'formula' => $formula,
			'origin' => $origin,
		]);

		$h = '<div class="text-end mb-1">';
			$h .= '<a href="'.$urlCreate.'" class="btn btn-outline-primary" id="toolbox-composition-settings-add" '.attr('data-question', s("Les réglages que vous n'avez pas enregistrés ne seront pas gardés une fois le panier ajouté. Voulez-vous continuer ?")).'>'.\Asset::icon('plus-circle').' '.s("Ajouter un panier").'</a>';
		$h .= '</div>';

		$h .= $form->openAjax('/toolbox/composition:doSettings');

			$h .= $form->hidden('id', $eCompositionPlan['structure']['id']);
			$h .= $form->hidden('formula', $formula);
			$h .= $form->hidden('origin', $origin);

			$properties = ['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'base'];

			foreach($properties as $position => $property) {

				$row = '<div class="toolbox-composition-settings-row'.match($position) {
					array_key_first($properties) => ' toolbox-composition-settings-row-first',
					array_key_last($properties) => ' toolbox-composition-settings-row-last',
					default => '',
				}.'">';

					foreach($eCompositionPlan['cCompositionFormula'] as $formulaId => $eCompositionFormula) {

						$loss = ($property === 'name') ? CompositionFormulaUi::getRecomposeLoss($eCompositionFormula) : NULL;

						$row .= '<div '.attrs([
							'data-wrapper' => $property.'['.$formulaId.']',
							'data-formula' => ($loss === NULL) ? NULL : $formulaId,
							'data-loss' => $loss,
						]).'>';
							$row .= $form->dynamicField($eCompositionFormula, $property.'['.$formulaId.']', fn(\PropertyDescriber $d) => $this->applySettingsField($d, $property, $formulaId));
						$row .= '</div>';

					}

				$row .= '</div>';

				// Sans `data-wrapper` posé, `group()` prendrait celui du dernier champ écrit, la cellule
				// de la dernière taille, et le rang entier recevrait ses erreurs. Le nom nu de la
				// propriété n'attrape aucune erreur : `buildIndex()` les nomme toutes par la taille.
				$h .= $form->group(CompositionFormulaUi::p($property)->label, $row, ['for' => FALSE, 'data-wrapper' => $property, 'class' => 'toolbox-composition-settings-group']);

			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"), ['data-question' => s("Voulez-vous continuer ?")])
			);

		$h .= $form->close();

		$h .= '<div class="util-block-optional mt-2">';
			$h .= '<h3>'.s("Recommencer les paniers").'</h3>';
			$h .= '<p>'.s("Les trois questions du départ reviennent avec vos réponses, dont le plan de culture suivi.").'</p>';
			$h .= '<a href="'.self::urlStart($eCompositionPlan['structure']).'" '.attr('data-confirm', $this->getRestartConfirm($eCompositionPlan)).' class="btn btn-outline-primary">'.s("Recommencer les paniers").'</a>';
		$h .= '</div>';

		return new \Panel(
			id: 'toolbox-composition-settings-panel',
			title: s("Modifier les paniers"),
			body: $h
		);

	}

	/**
	 * Le champ d'une taille dans sa colonne de « Modifier les paniers » (`getSettingsPanel()`), réduit à
	 * ce qu'une colonne étroite porte : le describer reste celui du panneau d'ajout d'une taille, qui
	 * garde ses unités et ses rappels.
	 */
	protected function applySettingsField(\PropertyDescriber $d, string $property, int $formulaId): void {

		$d->after = NULL;

		switch($property) {

			case 'base' :
				$d->field = fn(\util\FormUi $form, CompositionFormula $e) => CompositionFormulaUi::getBaseField($form, $e, 'base['.$formulaId.']');
				break;

			case 'baskets' :
			case 'linesMin' :
			case 'linesMax' :
				$d->append = NULL;
				break;

			// Le montant d'un seul tenant : coupé après le « ± », il ne se lit plus dans une colonne
			// étroite.
			case 'gap' :
				$d->after = fn(\util\FormUi $form, CompositionFormula $e) => \util\FormUi::info(str_replace('± ', "±\u{a0}", s("soit ± {amount}", [
					'amount' => \util\TextUi::money($e['gapAmount'], precision: 2),
				])));
				break;

		}

	}

	/**
	 * Le message dit où l'on repart et ce que le prochain envoi du départ remplacera, au
	 * futur : rouvrir le départ ne perd rien, et un départ abandonné laisse le plan tel quel. Une
	 * confirmation du navigateur et non un panneau : le plan n'appartient qu'à celui qui clique
	 * (tranché le 21/09).
	 */
	public function getRestartConfirm(CompositionPlan $eCompositionPlan): string {

		$losses = $eCompositionPlan['losses'];

		$message = s("Vous allez repartir des trois questions du départ, avec vos réponses déjà posées.").' ';

		$message .= ($losses['amended'] > 0) ?
			s("Quand vous composerez vos paniers, ils remplaceront ceux d'aujourd'hui et toutes vos corrections.") :
			s("Quand vous composerez vos paniers, ils remplaceront ceux d'aujourd'hui.");

		return $message;

	}

	/**
	 * Les repères du prix d'un panier, lus sur les paniers que les structures partagent
	 * (`CompositionSourceAiLib::getBasketPriceQuartiles()`) : la moitié d'entre eux, du premier au
	 * troisième quartile. Ils se disent d'après ces paniers, sans nommer de médiane nationale : aucune
	 * mention de la mercuriale générale dans ce projet (verdict sur la planche r22, lot r22f).
	 */
	public static function getPrices(array $prices): string {

		return s("la moitié des paniers partagés coûtent entre {low} et {high}", [
			'low' => \util\TextUi::money($prices['low'], precision: 0),
			'high' => \util\TextUi::money($prices['high'], precision: 0),
		]);

	}

	/**
	 * Le nombre de légumes d'un panier, sous son libellé « Produits » qui dit déjà l'unité.
	 */
	protected function getLinesRange(int $minimum, int $maximum): string {

		if($minimum === $maximum) {
			return (string)$maximum;
		}

		return s("{minimum} à {maximum}", ['minimum' => $minimum, 'maximum' => $maximum]);

	}

	/**
	 * Un `util-rail` : `$ranges` en `[début, fin]`, en pourcentage de l'axe, et `$marks` en
	 * `[texte, classe]`, une case de graduation par pas. Une plage réduite à un point garde un pour
	 * cent de large, sans quoi elle ne se verrait pas.
	 */
	protected function getRail(array $ranges, array $marks, ?string $title): string {

		$h = '<div class="util-rail toolbox-composition-rail"'.($title === NULL ? '' : ' title="'.encode($title).'"').'>';

			$h .= '<div class="util-rail-track">';

				foreach($ranges as [$start, $stop]) {
					$h .= '<i class="util-rail-range" style="left: '.round($start, 3).'%; width: '.round(max($stop - $start, 1.0), 3).'%;"></i>';
				}

			$h .= '</div>';

			$h .= '<div class="util-graduation">';
				foreach($marks as [$text, $class]) {
					$h .= '<span class="util-graduation-mark'.$class.'">'.$text.'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Semaines livrées », la question qui vaut pour toutes les tailles, au-dessus des onglets et au
	 * même niveau que « Les paniers » (planche r2, point 4) : le titre de section porte le décompte et
	 * la roue, comme « Les paniers » dessous, et la carte ne garde que la frise de l'année (r51a,
	 * « Livraison devient Semaines livrées, la roue est déplacée au niveau du titre »). Le décompte
	 * change avec les semaines : une réponse rend donc le bloc entier, titre compris. Les espèces
	 * livrées, qui tenaient la carte voisine, ont leur onglet (r48a, `getSpecies()`). Le plan est celui
	 * de `CompositionPlanLib::getForScreen()`.
	 *
	 * La roue dit l'onglet ouvert (`origin`), que le panneau réémet : la réponse rend les paniers de
	 * l'onglet d'où le geste part. Qui ne peut pas modifier le plan ne la voit pas, et le titre reste
	 * seul sur sa ligne.
	 */
	public function getWeeks(CompositionPlan $eCompositionPlan, string $tab = CompositionPlan::TAB_FORMULA): string {

		$weeks = $eCompositionPlan['weeks'];
		$seasonWeeks = $eCompositionPlan['seasonWeeks'];

		// La bande de l'année dit déjà quelles semaines sont livrées : le texte « du … au … » qui la
		// suivait est retiré (r29, #81, « vire ce texte »). La période reste au sous-titre du PDF et du
		// panneau des semaines (`getPeriod()`).
		$h = '<div class="toolbox-composition-questions" id="toolbox-composition-weeks">';

			$h .= '<div class="util-action toolbox-composition-heading">';
				$h .= '<h2>'.s("Semaines livrées").' <span class="util-counter">'.count($weeks).'</span></h2>';
				if($eCompositionPlan->assertUpdate()) {
					$h .= '<a href="'.self::urlPanel('/toolbox/composition:weeks', $eCompositionPlan).'&origin='.$tab.'" class="btn btn-only-icon btn-color-primary" title="'.s("Semaine par semaine").'">'.\Asset::icon('gear-fill').'</a>';
				}
			$h .= '</div>';

			$h .= '<div class="util-card">';
				$h .= $this->getYear($eCompositionPlan['bands'], min($seasonWeeks), max($seasonWeeks));
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Du lundi de la première semaine livrée au dimanche de la dernière.
	 */
	public static function getPeriod(int $season, array $weeks): string {

		return s("du {from} au {to}", [
			'from' => \util\DateUi::textual(week_date_starts(self::getIsoWeek($season, min($weeks))), \util\DateUi::DAY_MONTH),
			'to' => \util\DateUi::textual(week_date_ends(self::getIsoWeek($season, max($weeks))), \util\DateUi::DAY_MONTH),
		]);

	}

	/**
	 * L'année sur un rail gradué (`util-rail`) : une case par semaine, les semaines livrées en plages,
	 * et le numéro des seules dizaines sous le rail, comme l'axe du plan de culture.
	 */
	public function getYear(array $bands, int $minimum, int $maximum): string {

		$span = $maximum - $minimum + 1;

		$ranges = [];

		foreach($bands as [$start, $stop]) {
			$ranges[] = [($start - $minimum) / $span * 100, ($stop - $minimum + 1) / $span * 100];
		}

		$marks = [];

		for($week = $minimum; $week <= $maximum; $week++) {

			if($week % 10 === 0) {
				$marks[] = [(string)$week, ' util-graduation-mark-decade'];
			} else if($week % 5 === 0) {
				$marks[] = ['', ' util-graduation-mark-five'];
			} else {
				$marks[] = ['', ''];
			}

		}

		$title = ($bands === []) ? NULL : s("Semaines {start} à {stop}", ['start' => $bands[0][0], 'stop' => $bands[array_key_last($bands)][1]]);

		return $this->getRail($ranges, $marks, $title);

	}

	/**
	 * Une énumération écrite : « A », « A et B », « A, B et C ».
	 */
	public static function getList(array $values): string {

		if(count($values) === 1) {
			return $values[0];
		}

		$last = array_pop($values);

		return s("{list} et {last}", ['list' => implode(', ', $values), 'last' => $last]);

	}

	/**
	 * Les semaines livrées, une à une : `util-dates`, un `util-dates-month` par mois et un jeton
	 * `util-date` par semaine, rangée au mois de son jeudi (`CompositionLib::getSeasonMonths()`).
	 *
	 * La case du `util-label` coche le mois entier (`ToolboxComposition.checkWeeks()`), et se pose en
	 * `indeterminate` sur un mois à moitié livré : un état qui n'existe qu'en propriété, que
	 * `ToolboxComposition.updateMonths()` pose au rendu et à chaque case cochée. « Toute l'année », en
	 * tête, fait de même sur toutes les semaines de la saison.
	 *
	 * Les jours s'écrivent au format court, le mois étant déjà dit par le groupe, et ne se coupent
	 * qu'après le tiret : « 27 avr - » puis « 3 mai », jamais un jour séparé de son mois.
	 */
	public function getWeeksPanel(CompositionPlan $eCompositionPlan, ?int $formula, string $origin = CompositionPlan::TAB_FORMULA): \Panel {

		$weeks = $eCompositionPlan['weeks'];
		$seasonWeeks = array_merge(...array_values($eCompositionPlan['seasonMonths']));

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->hidden('id', $eCompositionPlan['structure']['id']);
		$h .= $form->hidden('formula', $formula);
		$h .= $form->hidden('origin', $origin);

		$losses = [];

		foreach(array_keys($eCompositionPlan['formulasAmended']) as $week) {
			$losses[$week] = $this->getWeekLoss($eCompositionPlan, $week);
		}

		$dates = self::getWeeksDates($form, $eCompositionPlan['season'], $eCompositionPlan['seasonMonths'], $weeks, $losses);

		$h .= $form->group(content: $dates, attributes: ['data-wrapper' => 'weeks']);

		$subtitle = p("{value} semaine sur {total}", "{value} semaines sur {total}", count($weeks), ['total' => count($seasonWeeks)]).' · '.self::getPeriod($eCompositionPlan['season'], $weeks);

		return new \Panel(
			id: 'toolbox-composition-weeks-panel',
			title: s("Les semaines livrées"),
			subTitle: $subtitle,
			dialogOpen: $form->openAjax('/toolbox/composition:doWeeks', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			footer: $form->submit(s("Enregistrer"), ['data-question' => s("Voulez-vous continuer ?")]),
		);

	}

	/**
	 * Les jetons des semaines, que le panneau « Semaine par semaine » et l'étape « Vos semaines » du
	 * départ partagent. Glisser sur les jetons coche ou décoche toute la période parcourue
	 * (`ToolboxCompositionWeeks` dans `composition.js`, le geste des planches du plan de la ferme),
	 * et un clic bascule une semaine seule.
	 *
	 * `$losses`, `[semaine => phrase]`, dit ce que ne plus livrer une semaine corrigée ferait perdre
	 * (`getWeekLoss()`) : le jeton la porte, et l'envoi du panneau la fait confirmer si la semaine
	 * est décochée (écouteur de l'envoi dans `composition.js`). Le départ n'en a pas : il remplace
	 * tout le plan et se confirme lui-même (`getRestartConfirm()`).
	 *
	 * « Toute l'année » (lot r43) et la case de chaque mois n'ont pas de nom : elles ne postent rien,
	 * seules les semaines partent en `weeks[]`. Leur état ne s'écrit donc pas ici, il se dérive des
	 * semaines cochées (`ToolboxComposition.updateMonths()`, au rendu et à chaque geste).
	 */
	public static function getWeeksDates(\util\FormUi $form, int $season, array $seasonMonths, array $weeks, array $losses = []): string {

		$dates = '<div class="util-dates toolbox-composition-weeks" onrender="ToolboxComposition.updateMonths(this)">';

			$dates .= '<div class="util-label toolbox-composition-weeks-all">'.$form->checkbox(NULL, attributes: [
				'onclick' => 'ToolboxComposition.checkWeeks(this, \'.toolbox-composition-weeks\')',
				'callbackLabel' => fn(string $input) => $input.' '.s("Toute l'année"),
			]).'</div>';

			foreach($seasonMonths as $month => $monthWeeks) {

				$dates .= '<div class="util-dates-month">';

					$dates .= '<div class="util-label mb-1">'.$form->checkbox(NULL, attributes: [
						'onclick' => 'ToolboxComposition.checkWeeks(this, \'.util-dates-month\')',
						'callbackLabel' => fn(string $input) => $input.' '.ucfirst(\util\DateUi::getMonthName($month)),
					]).'</div>';

					$dates .= '<div class="util-dates-grid">';

						foreach($monthWeeks as $week) {

							[$from, $to] = explode(' - ', \util\DateUi::weekToDays(self::getIsoWeek($season, $week), short: TRUE, withYear: FALSE));

							$days = str_replace(' ', "\u{a0}", $from.' -').' '.str_replace(' ', "\u{a0}", $to);

							$dates .= $form->checkbox('weeks[]', $week, [
								'checked' => in_array($week, $weeks),
								'data-loss' => $losses[$week] ?? NULL,
								'callbackFieldAttributes' => ['class' => 'util-date'],
								'callbackLabel' => fn(string $input) => $input.'<b>'.s("s{week}", ['week' => $week]).'</b><span class="toolbox-composition-week-days">'.$days.'</span>',
							]);

						}

					$dates .= '</div>';

				$dates .= '</div>';

			}

		$dates .= '</div>';

		return $dates;

	}

	/**
	 * L'onglet « Espèces » (r48a, planche r48, question 3, piste B) : le titre et son décompte, le deck
	 * « Plan de culture » (`getSynchronizeBlock()`) et les espèces livrées en tableau, que
	 * `CompositionSpeciesLib::getForTab()` rend dans l'ordre alphabétique, une variante sous son
	 * espèce nue (r76). « Ajouter une espèce » (r48c), à droite du titre, ouvre le panneau par
	 * où une espèce entre dans la liste, qu'elle soit
	 * neuve ou qu'on l'ait retirée (`CompositionSpeciesUi::add()`) : une espèce retirée n'a plus de
	 * bloc à elle sous le tableau.
	 *
	 * `util-table-bordered`, parce que chaque rang porte ses gestes. Sous 575 px, le prix passe sous
	 * la quantité et l'axe ne garde que le premier mois de chaque trimestre (`composition.css`).
	 *
	 * La saison s'y lit sans s'y régler (r51a, « uniquement en readonly les mois car on peut modifier
	 * dans l'onglet d'un panier ») : elle se règle sur la grille d'une taille, à la semaine près
	 * (`ToolboxCompositionSeason`).
	 */
	public function getSpecies(CompositionPlan $eCompositionPlan): string {

		$cCompositionSpecies = $eCompositionPlan['cCompositionSpecies'];

		$h = '<div class="tab-panel selected" id="toolbox-composition-species">';

			$h .= '<div class="util-action toolbox-composition-heading">';
				$h .= '<h2>'.s("Espèces livrées dans les paniers").' <span class="util-counter" id="toolbox-composition-species-counter">'.$cCompositionSpecies->count().'</span></h2>';
				if($eCompositionPlan->assertUpdate()) {
					$h .= '<a href="/toolbox/composition:addSpecies?id='.$eCompositionPlan['structure']['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une espèce").'</a>';
				}
			$h .= '</div>';

			$h .= $this->getSynchronizeBlock($eCompositionPlan);

			$h .= '<div class="util-table util-table-bordered toolbox-composition-calendar-table" id="toolbox-composition-species-table">';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Espèce").'</div>';
					$h .= '<div class="text-end toolbox-composition-calendar-basket">'.s("Par panier").'</div>';
					$h .= '<div class="text-end hide-xs-down">'.s("Prix").'</div>';
					$h .= '<div>'.self::getMonthsAxis('toolbox-composition-calendar-axis').'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

				foreach($cCompositionSpecies as $eCompositionSpecies) {
					$h .= $this->getSpeciesRow($eCompositionPlan, $eCompositionSpecies);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le rang d'une espèce livrée : son nom, qui ouvre « Régler une espèce » comme dans la grille et
	 * dit l'onglet d'où il part (`origin`), sa quantité au panier, son prix, sa saison et la corbeille.
	 * Le lien couvre le nom et non le rang : `util-table` aligne les vignettes sur la ligne de base,
	 * qu'un lien étiré sur toute la hauteur romprait.
	 *
	 * La roue crantée ouvre le même panneau que le nom, comme celle de la grille (r59) : le lien du nom
	 * ne se devinait pas, et la colonne des boutons est là où l'on cherche le réglage d'un rang.
	 *
	 * Le « + » d'une espèce nue (r76 lot 2, piste C) ouvre « Ajouter une espèce » sur elle, choisie
	 * d'avance, pour lui ajouter une variante. Une variante n'en a pas : elle ne se décline pas.
	 *
	 * La corbeille retire l'espèce après confirmation, comme « Retirer de mes cultures » au pied de
	 * son panneau, dont elle reprend la phrase. Qui ne peut pas écrire le plan ne voit ni elle, ni la
	 * roue, ni le « + », ni le lien du nom, dont le panneau lui serait refusé.
	 */
	protected function getSpeciesRow(CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies): string {

		$speciesId = $eCompositionSpecies['id'];
		$structureId = $eCompositionPlan['structure']['id'];
		$formulaId = $eCompositionPlan['eCompositionFormula']['id'];

		$url = '/toolbox/compositionSpecies:update?'.http_build_query([
			'structure' => $structureId,
			'formula' => $formulaId,
			'id' => $eCompositionSpecies['id'],
			'origin' => CompositionPlan::TAB_SPECIES,
		]);

		$h = '<div class="util-table-row" id="toolbox-composition-species-row-'.$speciesId.'">';

			$name = \plant\PlantUi::getVignette($eCompositionSpecies['plant'], '1.375rem');
			$name .= '<span>'.encode($eCompositionSpecies->getLabel()).'</span>';

			$h .= '<div>';
				if($eCompositionPlan->assertUpdate()) {
					$h .= '<a href="'.$url.'" class="util-vignette toolbox-composition-species-link">'.$name.'</a>';
				} else {
					$h .= '<span class="util-vignette">'.$name.'</span>';
				}
			$h .= '</div>';

			$h .= '<div class="text-end toolbox-composition-calendar-basket">'.$this->getSpeciesBasket($eCompositionSpecies).'</div>';
			$h .= '<div class="text-end hide-xs-down toolbox-composition-calendar-price">'.CompositionSpeciesUi::getGridPrice($eCompositionSpecies).'</div>';
			$h .= '<div>'.$this->getSpeciesSeason($eCompositionSpecies).'</div>';

			$h .= '<div class="flex-buttons toolbox-composition-species-actions">';

				if($eCompositionPlan->assertUpdate()) {

					$h .= '<a href="'.$url.'" class="btn btn-only-icon btn-sm btn-color-primary toolbox-composition-species-update" title="'.s("Régler {plant}", ['plant' => encode($eCompositionSpecies->getLabel())]).'">'.\Asset::icon('gear-fill').'</a>';

					if($eCompositionSpecies['name'] === NULL) {

						$urlVariant = '/toolbox/composition:addSpecies?'.http_build_query([
							'id' => $structureId,
							'species' => $speciesId,
						]);

						$h .= '<a href="'.$urlVariant.'" class="btn btn-only-icon btn-sm btn-outline-primary toolbox-composition-species-variant" title="'.s("Ajouter une variante de {plant}", ['plant' => encode($eCompositionSpecies['plant']['name'])]).'">'.\Asset::icon('plus-lg').'</a>';

					}

					$posts = [
						'structure' => $structureId,
						'formula' => $formulaId,
						'origin' => CompositionPlan::TAB_SPECIES,
					];

					// Le message passe par `attr()`, qui l'encode : le nom y entre tel quel.
					$confirm = s("{plant} sort de tous vos paniers, sauf aux semaines que vous avez corrigées. Voulez-vous continuer ?", ['plant' => $eCompositionSpecies->getLabel()]);

					$h .= '<a data-ajax="/toolbox/compositionSpecies:doExclude" post-id="'.$eCompositionSpecies['id'].'" '.self::getPosts($posts).' '.attr('data-confirm', $confirm).' class="btn btn-only-icon btn-sm btn-outline-primary toolbox-composition-calendar-remove" title="'.s("Retirer {plant}", ['plant' => encode($eCompositionSpecies->getLabel())]).'">'.\Asset::icon('trash').'</a>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La quantité qu'une espèce met au panier, et dessous son prix, qui n'a plus sa colonne sous
	 * 575 px.
	 */
	protected function getSpeciesBasket(CompositionSpecies $eCompositionSpecies): string {

		$h = '<div class="toolbox-composition-species-quantity">'.self::getQuantity($eCompositionSpecies['basketQuantity'], $eCompositionSpecies['eUnit']).'</div>';
		$h .= '<div class="hide-sm-up util-annotation toolbox-composition-calendar-price">'.CompositionSpeciesUi::getGridPrice($eCompositionSpecies, breakable: TRUE).'</div>';

		return $h;

	}

	/**
	 * La saison d'une espèce, à lire seulement (r51a) : douze barres (`util-bars`), une par mois,
	 * hautes comme la part du mois en saison ; la part qui ne tient qu'à la conservation se peint dans
	 * la même teinte, éclaircie (`util-bars-light`), et la bulle d'un mois qui ne tient qu'à elle le
	 * dit. Ni lien, ni menu, ni glissé : le dessin est le même pour qui administre et pour qui
	 * consulte.
	 */
	protected function getSpeciesSeason(CompositionSpecies $eCompositionSpecies): string {

		return '<span class="toolbox-composition-calendar">'.self::getSeasonBars($eCompositionSpecies['months']).'</span>';

	}

	/**
	 * Les douze barres d'une saison, depuis ses mois (`CompositionLib::getSeasonShares()`) : l'onglet
	 * « Espèces » et le départ (`CompositionStartUi`) dessinent la même. Une saison vide rend douze
	 * barres sans hauteur.
	 */
	public static function getSeasonBars(array $months): string {

		$h = '<span class="util-bars util-bars-light">';

			foreach($months as $month => $values) {

				$name = mb_ucfirst(\util\DateUi::getMonthName($month));
				$title = $values['storage'] ? s("{month}, conservation", ['month' => $name]) : $name;

				$h .= '<span style="--bar: '.round($values['share'], 2).'; --short: '.round($values['stored'], 2).'" title="'.$title.'"></span>';

			}

		$h .= '</span>';

		return $h;

	}

	/**
	 * Ce que « Régler une espèce », ouvert depuis l'onglet « Espèces », y change : le rang de l'espèce.
	 */
	public function getSpeciesUpdated(CompositionPlan $eCompositionPlan, int $speciesId): array {

		return [
			'outer' => [
				'#toolbox-composition-species-row-'.$speciesId => $this->getSpeciesRow($eCompositionPlan, $eCompositionPlan['cCompositionSpecies'][$speciesId]),
			],
			'inner' => [],
			'attributes' => [],
		];

	}

	/**
	 * Ce qu'une espèce retirée change dans l'onglet « Espèces » : son rang part et le décompte du titre
	 * suit.
	 */
	public function getSpeciesRemoved(CompositionPlan $eCompositionPlan, int $speciesId): array {

		return [
			'remove' => ['#toolbox-composition-species-row-'.$speciesId],
			'outer' => [],
			'inner' => [
				'#toolbox-composition-species-counter' => (string)$eCompositionPlan['cCompositionSpecies']->count(),
			],
			'attributes' => [],
		];

	}

	/**
	 * Ce qu'une espèce ajoutée change dans l'onglet « Espèces » (r48c) : son rang entre à sa place
	 * dans l'ordre alphabétique, devant le rang de l'espèce qui la suit ou au bout du tableau, et le
	 * décompte du titre suit. `insert` porte, pour chaque nœud à poser,
	 * son repère, sa place (`insertAdjacentHTML`) et son contenu.
	 */
	public function getSpeciesAdded(CompositionPlan $eCompositionPlan, int $speciesId): array {

		$cCompositionSpecies = $eCompositionPlan['cCompositionSpecies'];

		$speciesIds = $cCompositionSpecies->getKeys();
		$position = array_search($speciesId, $speciesIds, TRUE) + 1;

		$row = $this->getSpeciesRow($eCompositionPlan, $cCompositionSpecies[$speciesId]);

		return [
			'insert' => [
				array_key_exists($position, $speciesIds) ?
					['#toolbox-composition-species-row-'.$speciesIds[$position], 'beforebegin', $row] :
					['#toolbox-composition-species-table', 'beforeend', $row],
			],
			'outer' => [],
			'inner' => [
				'#toolbox-composition-species-counter' => (string)$cCompositionSpecies->count(),
			],
			'attributes' => [],
		];

	}

	/**
	 * Le deck « Plan de culture » en tête de l'onglet « Espèces » (r29, #79, #80, monté du panneau à
	 * l'onglet par r48a, refait en `util-deck` dépliable par r51b, planche r51, piste B). Replié, il
	 * compte les espèces que le plan de culture date à d'autres mois que ceux des paniers
	 * (`CompositionPlanLib::getSynchronization()`), et à part celles dont les mois ont été choisis à
	 * la main. Déplié, il montre chaque espèce avec ses mois des deux côtés, une case par espèce,
	 * décochée quand ses mois ont été choisis à la main, et le bouton qui envoie les cases cochées
	 * (`composition:doSynchronize`), dont la réponse redessine l'onglet.
	 *
	 * Le libellé du bouton suit le nombre de cases cochées (`ToolboxCompositionSynchronize.update()`),
	 * qui le prend dans les trois formes que le bouton porte.
	 *
	 * Le deck ne paraît que si une espèce au moins est à cocher d'office. Seul qui peut écrire le
	 * plan le voit.
	 */
	protected function getSynchronizeBlock(CompositionPlan $eCompositionPlan): string {

		['cCompositionSpecies' => $cCompositionSpecies, 'plants' => $plants, 'custom' => $custom] = $eCompositionPlan['synchronization'];

		if($plants === 0 or $eCompositionPlan->assertUpdate() === FALSE) {
			return '';
		}

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/composition:doSynchronize', ['id' => 'toolbox-composition-synchronize']);

			$h .= $form->hidden('id', $eCompositionPlan['structure']['id']);

			$h .= '<div class="util-deck toolbox-composition-synchronize-deck">';

				$h .= '<a class="util-deck-head" '.attr('onclick', 'this.closest(".util-deck").classList.toggle("util-deck-open")').'>';
					$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('calendar3').'</div>';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.p("{value} espèce n'est pas aux mêmes mois dans vos paniers et dans votre plan de culture {season}", "{value} espèces ne sont pas aux mêmes mois dans vos paniers et dans votre plan de culture {season}", $plants, ['season' => $eCompositionPlan['season']]).'</div>';
						if($custom > 0) {
							$h .= '<p>'.p("Et {value} autre dont vous avez choisi les mois", "Et {value} autres dont vous avez choisi les mois", $custom).'</p>';
						}
					$h .= '</div>';
					$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
				$h .= '</a>';

				$h .= '<div class="util-deck-fold">';
					$h .= '<div class="util-deck-content">';

						$h .= '<div class="util-table toolbox-composition-synchronize-table">';

							$h .= '<div class="util-table-head">';
								$h .= '<div class="util-checkbox">';
									$h .= '<label title="'.s("Tout cocher / Tout décocher").'">';
										$h .= $form->inputCheckbox(NULL, '1', [
											'onclick' => 'CheckboxField.all(this.firstParent(\'#toolbox-composition-synchronize\'), this.checked, \'[name="species[]"]\', ToolboxCompositionSynchronize.update)'
										]);
									$h .= '</label>';
								$h .= '</div>';
								$h .= '<div>'.s("Espèce").'</div>';
								$h .= '<div>'.s("Vos paniers").self::getMonthsAxis('toolbox-composition-calendar-axis').'</div>';
								$h .= '<div>'.s("Plan de culture").self::getMonthsAxis('toolbox-composition-calendar-axis').'</div>';
							$h .= '</div>';

							foreach($cCompositionSpecies as $eCompositionSpecies) {

								$isCustom = ($eCompositionSpecies['status'] === CompositionSpecies::CUSTOM);

								$h .= '<div class="util-table-row">';
									$h .= '<div class="util-checkbox">';
										$h .= '<label>';
											$h .= $form->inputCheckbox('species[]', $eCompositionSpecies['id'], ['checked' => ($isCustom === FALSE)]);
										$h .= '</label>';
									$h .= '</div>';
									$h .= '<div>';
										$h .= '<span class="util-vignette">';
											$h .= \plant\PlantUi::getVignette($eCompositionSpecies['plant'], '1.375rem');
											$h .= '<span>';
												$h .= encode($eCompositionSpecies->getLabel());
												if($isCustom) {
													$h .= '<span class="util-annotation">'.s("mois choisis par vous").'</span>';
												}
											$h .= '</span>';
										$h .= '</span>';
									$h .= '</div>';
									$h .= '<div><span class="toolbox-composition-calendar">'.self::getSeasonBars($eCompositionSpecies['months']).'</span></div>';
									$h .= '<div><span class="toolbox-composition-calendar">'.self::getSeasonBars($eCompositionSpecies['monthsCultivation']).'</span></div>';
								$h .= '</div>';

							}

						$h .= '</div>';

						// L'état de la case de tête et le libellé se déduisent des cases cochées, par le même
						// calcul à l'ouverture et à chaque geste : il est posé sur le bouton, peint après les cases.
						$h .= '<div class="toolbox-composition-synchronize-submit">';
							$h .= $form->submit(p("Appliquer le plan de culture à l'espèce cochée", "Appliquer le plan de culture aux {value} espèces cochées", $plants), [
								'class' => 'btn btn-primary',
								'onrender' => 'ToolboxCompositionSynchronize.update()',
								'data-none' => s("Appliquer le plan de culture"),
								'data-one' => s("Appliquer le plan de culture à l'espèce cochée"),
								'data-many' => s("Appliquer le plan de culture aux {value} espèces cochées", ['value' => '{value}']),
							]);
						$h .= '</div>';

					$h .= '</div>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= $form->close();

		return $h;

	}

	/**
	 * Les paniers : le titre, puis les tailles en onglets, et après elles les onglets « Synthèse »
	 * (planche r2, point 5) et « Espèces » (r48a). L'onglet d'une taille porte ce qui la règle, ses
	 * comptes et la grille de l'année ; la synthèse, l'année en quantités, toutes tailles
	 * additionnées ; les espèces, ce que les paniers livrent et quand (`getSpecies()`). `$tab` nomme
	 * l'onglet ouvert (`CompositionPlan::TAB_*`). Un plan composé porte toujours une taille au moins :
	 * le départ en exige une.
	 *
	 * La roue du titre (r32, planche r32, question 2) ouvre le menu des paniers, qui appartient aux
	 * paniers et pas à l'onglet ouvert. « Modifier les paniers » vient en premier (r33, #109) : il
	 * ouvre le panneau du même nom (r31, #96, `getSettingsPanel()`), toutes tailles, et vaut donc
	 * sur tous les onglets : il dit d'où il part (`origin`) pour que la réponse rende le même
	 * onglet. C'est aussi par lui qu'on ajoute un panier (r51b, planche r51, piste A) : le geste vit
	 * en tête de « Modifier les paniers », et nulle part ailleurs. « Télécharger en PDF » ferme le menu
	 * et imprime toutes les tailles (lot L4, `CompositionPdfUi`). Qui ne peut pas modifier le plan ne
	 * voit pas la première entrée (`CLAUDE.md` du package : un geste que l'observateur ne peut pas
	 * faire ne s'affiche pas), et son menu ne garde que le PDF.
	 */
	public function getProposal(CompositionPlan $eCompositionPlan, string $tab = CompositionPlan::TAB_FORMULA): string {

		$h = '<div id="toolbox-composition-proposal">';

			$h .= '<div class="util-action toolbox-composition-heading">';
				$h .= '<h2>'.s("Les paniers").'</h2>';
				$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Les paniers").'">'.\Asset::icon('gear-fill').'</a>';
				$h .= '<div class="dropdown-list">';
					$h .= '<div class="dropdown-title">'.s("Les paniers").'</div>';
					if($eCompositionPlan->assertUpdate()) {
						$url = self::urlPanel('/toolbox/composition:settings', $eCompositionPlan).'&origin='.$tab;
						$h .= '<a href="'.$url.'" class="dropdown-item">'.s("Modifier les paniers").'</a>';
					}
					$h .= '<a href="'.self::urlPdf($eCompositionPlan['structure']).'" data-ajax-download="'.encode(CompositionPdfUi::getFilename($eCompositionPlan['season'])).'" data-waiter="'.s("Création en cours").'" data-waiter-timeout="8" class="dropdown-item">'.s("Télécharger en PDF").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="tabs tabs-h tabs-h-fold toolbox-composition-tabs">';

				$h .= $this->getFormulas($eCompositionPlan, $tab);

				$h .= match($tab) {
					CompositionPlan::TAB_FORMULA => $this->getFormulaPanel($eCompositionPlan),
					CompositionPlan::TAB_SYNTHESIS => '<div class="tab-panel selected" id="toolbox-composition-synthesis">'.$this->getQuantities($eCompositionPlan).'</div>',
					CompositionPlan::TAB_SPECIES => $this->getSpecies($eCompositionPlan),
				};

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'année en quantités (planche v12, Q6), l'onglet « Synthèse » : une colonne par taille, puis
	 * l'année, groupées par famille comme la grille (`CompositionLib::getQuantities()`). Sous 575 px,
	 * seule l'année reste. Une seule taille n'a qu'une colonne, à son nom, que rien ne masque : la
	 * colonne de l'année la répéterait (planche r2).
	 *
	 * Au-dessus, les chiffres de l'année en tuiles (`util-figures`, planche r26, question 4, option A),
	 * comme la tête d'une taille : le chiffre d'affaires se lit au même endroit d'un onglet à l'autre.
	 *
	 * La quantité s'arrondit à l'unité : sur une année, la décimale d'un kilo ne se commande pas.
	 *
	 * Une espèce comptée à la pièce ou à la botte se dit aussi en kilos sous sa quantité de l'année,
	 * ou propose d'indiquer son poids (lot L3, `getQuantitiesWeight()`).
	 *
	 * Après l'année, les douze mois en barres (lot r19, `getQuantitiesMonths()`), leurs initiales en
	 * tête de colonne. À 767 px et moins, la colonne disparaît et les barres passent sous le nom de
	 * l'espèce avec leurs propres initiales : une tête de colonne masquée ne dirait plus rien. Le seuil
	 * a été mesuré au lot r19, sur le tableau d'alors (767 px, 21 px de trop).
	 *
	 * Les tailles et le total de l'année se lisent ensemble (r33, #110, variante A) : un en-tête commun
	 * « Sur l'année » les couvre, et leurs cellules portent un fond de groupe. Cet en-tête n'est pas un
	 * `util-table-row`, que l'alternance compte : il décalerait la parité de tout le tableau. Sous
	 * 576 px, l'année reste seule, il n'y a plus rien à grouper : l'en-tête commun part, et la colonne
	 * reprend le nom « Sur l'année » qu'il lui donnait. Une taille seule n'a pas d'année, donc pas de groupe.
	 */
	public function getQuantities(CompositionPlan $eCompositionPlan): string {

		$quantities = $eCompositionPlan['quantities'];
		$cCompositionFormula = $eCompositionPlan['cCompositionFormula'];

		$single = ($cCompositionFormula->count() === 1);

		$figures = [
			[$quantities['baskets'], p("Panier par semaine", "Paniers par semaine", $quantities['baskets'])],
			[$quantities['weeks'], p("Semaine", "Semaines", $quantities['weeks'])],
			[\util\TextUi::number($quantities['basketsYear'], 0), p("Panier sur l'année", "Paniers sur l'année", $quantities['basketsYear'])],
			[\util\TextUi::money($quantities['turnover'], precision: 0), s("Chiffre d'affaires sur l'année")],
		];

		$h = '<div class="util-figures toolbox-composition-figures">';
			foreach($figures as [$value, $label]) {
				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-value">'.$value.'</div>';
					$h .= '<div class="util-figure-label">'.$label.'</div>';
				$h .= '</div>';
			}
		$h .= '</div>';

		if($quantities['groups'] !== []) {

			$h .= '<div class="util-table util-table-even util-table-hover toolbox-composition-quantities'.($single ? ' toolbox-composition-quantities-single' : '').'" style="--sizes: '.$cCompositionFormula->count().'">';

				if($single === FALSE) {
					$h .= '<div class="toolbox-composition-quantities-group">';
						$h .= '<div></div>';
						$h .= '<div class="toolbox-composition-quantities-group-name">'.s("Sur l'année").'</div>';
					$h .= '</div>';
				}

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Espèce").'</div>';
					foreach($cCompositionFormula as $eCompositionFormula) {
						$h .= '<div class="text-end'.($single ? '' : ' toolbox-composition-quantities-size util-table-veil').'">'.encode($eCompositionFormula['name']).'</div>';
					}
					if($single === FALSE) {
						$h .= '<div class="text-end toolbox-composition-quantities-year util-table-veil">';
							$h .= '<span class="hide-xs-down">'.s("Total").'</span>';
							$h .= '<span class="hide-sm-up">'.s("Sur l'année").'</span>';
						$h .= '</div>';
					}
					$h .= '<div class="hide-sm-down">'.self::getMonthsAxis().'</div>';
				$h .= '</div>';

				foreach($quantities['groups'] as $group) {

					$h .= '<div class="util-table-title">'.self::getOrganName($group['organ']).'</div>';

					foreach($group['rows'] as $row) {
						$h .= $this->getQuantitiesRow($eCompositionPlan, $row);
					}

				}

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Une espèce de l'année en quantités. Celle que la règle n'a servie dans aucun panier n'a pas
	 * d'unité : elle reste, grisée, et dit pourquoi elle n'a pas de quantité. Les autres portent leur
	 * espèce et leur unité dans leur `id`, par lequel un poids réglé depuis la synthèse les rend
	 * (`getWeighed()`) : une espèce peut entrer au panier dans deux unités, et avoir deux rangs.
	 */
	protected function getQuantitiesRow(CompositionPlan $eCompositionPlan, array $row): string {

		$cCompositionFormula = $eCompositionPlan['cCompositionFormula'];

		$single = ($cCompositionFormula->count() === 1);
		$never = $row['unit']->empty();

		$h = '<div class="util-table-row'.($never ? ' color-muted' : '').'"'.($never ? '' : ' id="toolbox-composition-quantities-'.$row['species']['id'].'-'.$row['unit']['id'].'"').'>';

			$h .= '<div>';
				$h .= '<div class="util-vignette">';
					$h .= \plant\PlantUi::getVignette($row['species']['plant'], '1.375rem');
					$h .= '<div>';
						$h .= encode($row['species']->getLabel());
						if($never === FALSE) {
							$h .= '<div class="hide-md-up toolbox-composition-quantities-months-under">'.$this->getQuantitiesMonths($eCompositionPlan, $row).self::getMonthsAxis().'</div>';
						}
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

			if($single) {

				$h .= '<div class="text-end">';
					$h .= $never ? s("Dans aucun panier") : self::getQuantity(round($row['total']), $row['unit']).$this->getQuantitiesWeight($eCompositionPlan, $row);
				$h .= '</div>';

			} else {

				foreach($cCompositionFormula as $eCompositionFormula) {

					$quantity = $row['formulas'][$eCompositionFormula['id']];

					$h .= '<div class="text-end toolbox-composition-quantities-size util-table-veil">';
						if($quantity !== NULL) {
							$h .= self::getQuantity(round($quantity), $row['unit']);
						}
					$h .= '</div>';

				}

				if($never) {
					$h .= '<div class="text-end toolbox-composition-quantities-year util-table-veil">'.s("Dans aucun panier").'</div>';
				} else {
					$h .= '<div class="text-end toolbox-composition-quantities-year util-table-veil"><b>'.self::getQuantity(round($row['total']), $row['unit']).'</b>'.$this->getQuantitiesWeight($eCompositionPlan, $row).'</div>';
				}

			}

			$h .= '<div class="hide-sm-down">'.($never ? '' : $this->getQuantitiesMonths($eCompositionPlan, $row)).'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qu'une espèce sert chaque mois (lot r19, planche paniers-maquette-r16, question 2, option A) :
	 * douze barres (`util-bars`) à l'échelle de l'espèce, le mois le plus chargé en haut
	 * (`CompositionLib::applyMonthsToQuantities()`). Au survol, la barre dit son mois et sa quantité.
	 *
	 * Au-dessus des barres, leur échelle (lot r19b, `util-bars-max`) : la quantité du mois le plus
	 * chargé, arrondie et dans l'unité de l'espèce comme « Sur l'année », avec un trait à la hauteur
	 * d'une barre pleine. Sans elle, une barre ne disait sa quantité qu'au survol.
	 */
	protected function getQuantitiesMonths(CompositionPlan $eCompositionPlan, array $row): string {

		$h = '<div class="util-bars-max">'.self::getQuantity(round($row['peak']), $row['unit']).'</div>';

		$h .= '<div class="util-bars">';

			foreach($row['months'] as $month => $values) {

				$name = mb_ucfirst(\util\DateUi::getMonthName($month));

				if($values['quantity'] === 0.0) {
					$title = s("{month}, aucun panier", ['month' => $name]);
				} else {
					$title = s("{month}, {quantity}", ['month' => $name, 'quantity' => self::getQuantity(round($values['quantity']), $row['unit'])]);
				}

				$h .= '<span style="--bar: '.$values['bar'].'" title="'.$title.'"></span>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les initiales des douze mois sous des barres (`util-bars-axis`) : en tête de la colonne, et sous
	 * chaque diagramme à 767 px et moins, où il passe sous le nom de l'espèce. `$class` est la classe
	 * que l'écran lui ajoute, celle de l'axe des saisons dans l'onglet « Espèces ».
	 */
	public static function getMonthsAxis(?string $class = NULL): string {

		$h = '<div class="util-bars-axis'.($class === NULL ? '' : ' '.$class).'">';
			foreach(range(1, 12) as $month) {
				$h .= '<span>'.mb_strtoupper(mb_substr(\util\DateUi::getMonthName($month), 0, 1)).'</span>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les kilos de l'année sous sa quantité, « ≈ 90 kg » sous 180 bottes (planche L3), pour une espèce
	 * comptée à la pièce ou à la botte dont on connaît le poids. Sans poids, le lien « poids d'une
	 * botte » ouvre le champ du panneau de l'espèce (`CompositionSpeciesUi::update()`), et dit d'où il
	 * part (`origin`) pour que la réponse rende ce rang et non la grille. Le poids vaut pour l'unité où
	 * l'espèce entre au panier : un rang dans une autre unité n'en dit rien.
	 *
	 * Les `post-*` se posent dans l'ordre des formulaires rapides de l'espèce (`Data::setQuickAttribute()`).
	 */
	protected function getQuantitiesWeight(CompositionPlan $eCompositionPlan, array $row): string {

		$eCompositionSpecies = $row['eCompositionSpecies'];

		if(
			$eCompositionSpecies->empty() or
			$eCompositionSpecies['weightReference']['weighed'] === FALSE or
			$row['unit']['id'] !== $eCompositionSpecies['eUnit']['id']
		) {
			return '';
		}

		$weight = $eCompositionSpecies['weightReference']['weight'];

		if($weight !== NULL) {
			return '<div class="util-annotation">'.s("≈ {kilos}", ['kilos' => CompositionSpeciesUi::getKilos(round($row['total'] * $weight), 0)]).'</div>';
		}

		if($eCompositionSpecies->assertUpdate() === FALSE) {
			return '';
		}

		$posts = [
			'structure' => $eCompositionPlan['structure']['id'],
			'formula' => $eCompositionPlan['eCompositionFormula']['id'],
			'week' => NULL,
			'species' => NULL,
			'origin' => 'synthesis',
		];

		foreach($posts as $name => $value) {
			CompositionSpecies::setQuickAttribute($name, $value);
		}

		return '<div class="util-annotation"><a class="util-quick" '.$eCompositionSpecies->getQuickAttributes('weight').'>'.s("poids d'une {unit}", ['unit' => encode($eCompositionSpecies['eUnit']['singular'])]).'</a></div>';

	}

	/**
	 * La rangée des onglets, une taille par onglet avec son prix, plus discret que le nom : deux
	 * tailles se distinguent sans qu'on ouvre leur onglet. « Synthèse » et « Espèces » (r48a) les
	 * suivent. Elle se rend seule après l'ajout d'une taille, qui ne change pas l'onglet ouvert
	 * (`$tab`). L'ajout n'y est plus (r51a, « Enlever Ajouter une taille ici ») : le geste vit en tête
	 * de « Modifier les paniers » (r51b, `getSettingsPanel()`).
	 *
	 * Un onglet s'ouvre sans recharger la page (`composition:tab`, chantier Performances, P14), et son
	 * adresse reste celle de la page, qu'un rechargement lit en entier.
	 */
	public function getFormulas(CompositionPlan $eCompositionPlan, string $tab = CompositionPlan::TAB_FORMULA): string {

		$eCompositionFormulaSelected = $eCompositionPlan['eCompositionFormula'];

		$h = '<div class="tabs-item" id="toolbox-composition-formulas">';

			foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {

				$h .= '<a href="'.self::urlFormula($eCompositionPlan['structure'], $eCompositionFormula).'" data-ajax="'.self::urlFormulaTab($eCompositionPlan['structure'], $eCompositionFormula).'" data-ajax-method="get" class="tab-item'.(($tab === CompositionPlan::TAB_FORMULA and $eCompositionFormula === $eCompositionFormulaSelected) ? ' selected' : '').'">';
					$h .= encode($eCompositionFormula['name']);
					$h .= '<span class="toolbox-composition-tab-price">'.\util\TextUi::money((float)$eCompositionFormula['price'], precision: 0).'</span>';
				$h .= '</a>';

			}

			$h .= '<a href="'.self::urlSynthesis($eCompositionPlan['structure']).'" data-ajax="'.self::urlSynthesisTab($eCompositionPlan['structure']).'" data-ajax-method="get" class="tab-item'.(($tab === CompositionPlan::TAB_SYNTHESIS) ? ' selected' : '').'">'.s("Synthèse").'</a>';
			$h .= '<a href="'.self::urlSpecies($eCompositionPlan['structure']).'" data-ajax="'.self::urlSpeciesTab($eCompositionPlan['structure']).'" data-ajax-method="get" class="tab-item'.(($tab === CompositionPlan::TAB_SPECIES) ? ' selected' : '').'">'.s("Espèces").'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'onglet de la formule ouverte, qui se rend entier quand toute sa proposition change
	 * (« Rejouer les paniers »).
	 */
	public function getFormulaPanel(CompositionPlan $eCompositionPlan): string {

		$h = '<div class="tab-panel selected" id="toolbox-composition-formula">';
			$h .= $this->getFormula($eCompositionPlan, $eCompositionPlan['eCompositionFormula']);
		$h .= '</div>';

		return $h;

	}

	protected function getFormula(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$h = $this->getSettings($eCompositionPlan, $eCompositionFormula);

		$h .= $this->getCounts($eCompositionPlan, $eCompositionFormula);

		$h .= $this->getLegend($eCompositionPlan);

		$h .= $this->getGrid($eCompositionPlan, $eCompositionFormula);

		$h .= $this->getWeekCards($eCompositionPlan, $eCompositionFormula);

		return $h;

	}

	/**
	 * Le panier de chaque semaine livrée, en carte sous la grille (planche r5, question 1, retour de
	 * du 26/09 : « on ne peut plus voir la composition des paniers semaine par semaine comme
	 * avant ») : chaque semaine se lit d'un coup et se compare à ses voisines en faisant défiler. Les
	 * réponses ciblées tiennent chaque carte à jour par son identifiant (`getWeekChanges()`, `getPriced()`).
	 * C'est revenir sur le poids que r2 avait retiré (215 Ko et 3 505 éléments pour les 41 cartes de
	 * la structure 7), en le sachant : la planche le chiffrait.
	 *
	 * Chaque semaine livrée a sa carte, qu'elle ait un panier ou non (lot r22, point 4) : une semaine
	 * de soudure sans aucune candidate n'a pas de panier (`CompositionLib::propose()`), et c'est
	 * justement celle que la soudure doit montrer. Au-dessus des cartes, un bloc par période de
	 * soudure (`getLean()`). Une taille sans aucun panier ni soudure s'ouvre sans cartes.
	 */
	protected function getWeekCards(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		if($eCompositionFormula['cCompositionWeek']->empty() and $eCompositionFormula['lean']['weeks'] === []) {
			return '';
		}

		$weeks = $eCompositionPlan['weeks'];
		sort($weeks);

		$h = '<h3 class="mt-2">'.s("Semaine par semaine").'</h3>';

		$h .= $this->getLean($eCompositionPlan, $eCompositionFormula);

		$h .= $this->getWeekQuantityTemplate($eCompositionPlan);

		$h .= '<div class="util-cards util-cards-2 util-cards-start toolbox-composition-weeks">';

			foreach($weeks as $week) {
				$h .= $this->getWeekCard($eCompositionPlan, $eCompositionFormula, $week);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une carte à liseré par période de soudure de la taille (`CompositionLib::getLean()`, planche r22,
	 * question 4, option B, et planche r26, question 5, option A), au-dessus des cartes : ce qui manque,
	 * et les deux gestes qui en sortent pour toute la période (`getLeanDoors()`). L'orange ne marque
	 * que le liseré et le titre, l'accent des cartes des semaines de soudure dessous. Le conteneur reste là sans soudure, pour qu'une réponse ciblée
	 * puisse le remplir (`getLeanChanges()`).
	 */
	protected function getLean(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$h = '<div id="toolbox-composition-lean">';

			foreach($eCompositionFormula['lean']['periods'] as $period) {
				$h .= $this->getLeanPeriod($eCompositionPlan, $eCompositionFormula, $period);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bloc d'une période de soudure (`getLean()`), que la présentation montre seul
	 * (`getNoticePanel()`).
	 */
	protected function getLeanPeriod(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $period): string {

		$weeks = $period['weeks'];

		$h = '<div class="util-card util-card-accent util-card-accent-warning mb-1">';
			$h .= '<div class="util-card-title">'.match(count($weeks)) {
				1 => s("Soudure, semaine {week}", ['week' => $weeks[0]]),
				2 => s("Soudure, semaines {from} et {to}", ['from' => min($weeks), 'to' => max($weeks)]),
				default => s("Soudure, semaines {from} à {to}", ['from' => min($weeks), 'to' => max($weeks)]),
			}.'</div>';
			$h .= '<p>'.s("Il n'y a pas assez d'espèces dans le plan de culture pour atteindre {price} à ± {gap} %.", [
				'price' => \util\TextUi::money((float)$eCompositionFormula['price'], precision: 0),
				'gap' => $eCompositionFormula['gap'],
			]).'</p>';
			$h .= $this->getLeanDoors($eCompositionPlan, $eCompositionFormula, $period, 'btn-outline-primary', 'flex-buttons');
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les deux gestes qui sortent de la soudure (planche r22, question 4), sur le bloc d'une période
	 * (`getLean()`) comme sur la carte de chacune de ses semaines (`getWeekCardLean()`) :
	 * - « Conserver plus », le menu des espèces conservées dont une conservation plus longue ouvrirait
	 *   la période jusqu'à sa dernière semaine (`CompositionLib::getStretch()`), chacune vers sa fiche
	 *   comme « Changer la durée de conservation » dans le panneau de l'espèce
	 *   (`CompositionSpeciesUi::update()`) ; absent quand aucune ne le ferait ;
	 * - « Retirer la semaine », qui la retire des semaines livrées de toutes les tailles comme le
	 *   panneau « Les semaines livrées » (`composition:doRemoveWeeks`), après confirmation. Sur une
	 *   carte, la semaine seule ; sur le bloc, toute la période.
	 *
	 * Le libellé « Conserver plus » est le plus long qui laisse les deux portes d'une carte côte à côte
	 * à 360 px (planche r22). Sans droit d'écrire le plan, il n'y a pas de geste.
	 */
	protected function getLeanDoors(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $period, string $button, string $class, ?int $week = NULL): string {

		if($eCompositionPlan->assertUpdate() === FALSE) {
			return '';
		}

		$weeks = ($week === NULL) ? $period['weeks'] : [$week];

		$posts = [
			'id' => $eCompositionPlan['structure']['id'],
			'formula' => $eCompositionFormula['id'],
			'from' => min($weeks),
			'to' => max($weeks),
		];

		$confirm = match(count($weeks)) {
			1 => s("La semaine {week} ne sera plus livrée, pour aucun de vos paniers. Voulez-vous continuer ?", ['week' => $weeks[0]]),
			2 => s("Les semaines {from} et {to} ne seront plus livrées, pour aucun de vos paniers. Voulez-vous continuer ?", ['from' => min($weeks), 'to' => max($weeks)]),
			default => s("Les semaines {from} à {to} ne seront plus livrées, pour aucun de vos paniers. Voulez-vous continuer ?", ['from' => min($weeks), 'to' => max($weeks)]),
		};

		$h = '<div class="'.$class.'">';

			if($period['stretch'] !== []) {

				$h .= '<a data-dropdown="bottom-start" class="btn '.$button.' dropdown-toggle">'.\Asset::icon('box-seam').' '.s("Conserver plus").'</a>';
				$h .= '<div class="dropdown-list">';
					$h .= '<div class="dropdown-title">'.s("Jusqu'à la semaine {week}", ['week' => max($period['weeks'])]).'</div>';
					foreach($period['stretch'] as $stretch) {
						$h .= '<a href="/plant/plant:update?id='.$stretch['copy']['id'].'&origin=composition" class="dropdown-item">'.s("{plant}, {needed} semaines au lieu de {weeks}", ['plant' => encode($stretch['species']->getLabel()), 'needed' => $stretch['needed'], 'weeks' => $stretch['weeks']]).'</a>';
					}
				$h .= '</div>';

			}

			$h .= '<a data-ajax="/toolbox/composition:doRemoveWeeks" '.self::getPosts($posts).' '.attr('data-confirm', $confirm).' class="btn '.$button.'">';
				$h .= \Asset::icon('calendar-x').' '.((count($weeks) === 1) ? s("Retirer la semaine") : s("Retirer ces semaines"));
			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qu'un geste change à la soudure, ajouté à `$changes` (`['outer' => …, 'attributes' => …]`,
	 * la forme de `getToggled()`) : les blocs des périodes
	 * (`getLean()`), et sur chaque carte son accent et sa bande (`getWeekCardLean()`).
	 *
	 * La soudure d'une semaine dépend du stock que les semaines d'avant lui laissent
	 * (`CompositionLib::getLeanWeeks()`) : une quantité baissée en semaine 9 peut rendre une espèce à la
	 * semaine 12, et le serveur ne sait pas quelles cartes l'écran montre en soudure. Chaque carte
	 * reçoit donc sa bande, vide hors soudure, et sa classe : une soixantaine d'octets par semaine
	 * sans soudure, plutôt que les 5 Ko d'une carte entière.
	 */
	public function getLeanChanges(CompositionPlan $eCompositionPlan, array $changes): array {

		// Sous la communauté, aucune semaine n'est de soudure (`CompositionLib::getLeanWeeks()`) : aucun
		// geste n'y change rien.
		if($eCompositionPlan['reference'] !== CompositionPlan::CULTIVATION) {
			return $changes;
		}

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$changes['outer']['#toolbox-composition-lean'] = $this->getLean($eCompositionPlan, $eCompositionFormula);

		foreach($eCompositionPlan['weeks'] as $week) {
			$changes['outer']['#toolbox-composition-card-lean-'.$week] = $this->getWeekCardLean($eCompositionPlan, $eCompositionFormula, $week);
			$changes['attributes']['#toolbox-composition-card-'.$week] = ['class' => self::getWeekCardClass($eCompositionFormula, $week)];
		}

		return $changes;

	}

	/**
	 * La tête de la formule en cinq chiffres clés (`util-figures`, planche r2, point 2, planche r31) : son
	 * prix, ses paniers vendus par semaine, ses semaines livrées, le chiffre d'affaires que ses
	 * paniers font sur l'année (r12) et son nombre de produits. La valeur des paniers n'a plus de tuile
	 * (r26, retour du 28/09, « vire ça ») : chaque carte de semaine la donne. L'écart non
	 * plus (r32, retour du 30/09, « vire ce bloc ») : il se règle dans « Régler les
	 * paniers », et les semaines qui le dépassent se lisent en orange sur le rang des prix, que la
	 * légende nomme avec lui (`getLegend()`).
	 *
	 * Les tuiles disent les chiffres et ne se règlent plus sur place (r31, #96) : tout réglage passe
	 * par « Modifier les paniers » (`getSettingsPanel()`). Elles sont cinq sur toutes les tailles, en
	 * colonnes fixes (`composition.css`) : la base d'une taille qui s'appuie sur une autre se lit en
	 * annotation du prix, qui la borne, et non sur une tuile à elle.
	 */
	public function getSettings(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$summary = $eCompositionFormula['summary'];

		$h = '<div class="util-figures toolbox-composition-figures" id="toolbox-composition-settings">';

			// La taille sur laquelle celle-ci s'appuie (lot r17), sous le prix qui la borne : sa base doit
			// coûter moins. Les produits qu'elle ajoute sont la médiane des espèces ajoutées à la base
			// (`CompositionWeekLib::applyBase()`).
			$eCompositionFormulaBase = $eCompositionFormula['eCompositionFormulaBase'];

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.\util\TextUi::money((float)$eCompositionFormula['price'], precision: 0).'</div>';
				$h .= '<div class="util-figure-label">'.s("Prix du panier").'</div>';
				if($eCompositionFormulaBase->notEmpty()) {
					$h .= '<div class="util-annotation">'.$this->getBaseAnnotation($eCompositionFormulaBase, $eCompositionFormula['linesAdded']).'</div>';
				}
			$h .= '</div>';

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.$eCompositionFormula['baskets'].'</div>';
				$h .= '<div class="util-figure-label">'.s("Paniers vendus par semaine").'</div>';
			$h .= '</div>';

			// Le décompte des semaines, qui avait sa ligne sous les tuiles (r31, #97) : entre les paniers
			// et le chiffre d'affaires, qu'il multiplie avec le prix. Les semaines à court d'espèces se
			// lisent dessous.
			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.$summary['weeks'].'</div>';
				$h .= '<div class="util-figure-label">'.s("Semaines livrées").'</div>';
				if($summary['weeksShort'] > 0) {
					$h .= '<div class="util-annotation">'.s("{value} à court d'espèces", ['value' => $summary['weeksShort']]).'</div>';
				}
			$h .= '</div>';

			// La valeur des paniers composés par leur nombre (r12, `CompositionLib::getTurnover()`) :
			// chaque geste qui change une quantité, un prix, le nombre de paniers ou les semaines
			// livrées rend cette tête, et la tuile avec elle.
			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.\util\TextUi::money($eCompositionFormula['turnover'], precision: 0).'</div>';
				$h .= '<div class="util-figure-label">'.s("Chiffre d'affaires sur l'année").'</div>';
			$h .= '</div>';

			// Les bornes que la règle ne franchit pas (r11) ; ce que les paniers composés portent vraiment
			// se lit dessous.
			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.s("{minimum} à {maximum}", [
					'minimum' => $eCompositionFormula['linesMin'],
					'maximum' => $eCompositionFormula['linesMax'],
				]).'</div>';
				$h .= '<div class="util-figure-label">'.s("Produits").'</div>';
				if($summary['linesMin'] !== NULL) {
					$h .= '<div class="util-annotation">'.s("{range} dans les paniers", ['range' => $this->getLinesRange($summary['linesMin'], $summary['linesMax'])]).'</div>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La base d'une taille sous son prix (r31, #97), « Sur « Petit panier », plus 2 produits », sans les
	 * produits quand la taille n'en ajoute aucun, ou qu'aucune de ses semaines ne suit la base
	 * (`CompositionWeekLib::applyBase()` rend alors `NULL`).
	 */
	protected function getBaseAnnotation(CompositionFormula $eCompositionFormulaBase, ?int $linesAdded): string {

		$name = encode($eCompositionFormulaBase['name']);

		if($linesAdded === NULL or $linesAdded === 0) {
			return s("Sur « {name} »", ['name' => $name]);
		}

		return p("Sur « {name} », plus {value} produit", "Sur « {name} », plus {value} produits", $linesAdded, ['name' => $name]);

	}

	/**
	 * L'écart maximum d'une taille, « ± 5 % », d'un seul tenant : coupé après le « ± », il ne se lit
	 * plus dans une légende qui passe à la ligne.
	 */
	protected static function getGap(int $gap): string {
		return str_replace(' ', "\u{a0}", s("± {value} %", ['value' => $gap]));
	}

	/**
	 * Sous la tête de la formule, l'interrupteur « Régler la saisonnalité », et au bout de la ligne
	 * « Rejouer les paniers » (planche v11, É13) : il agit sur la formule de l'onglet, et loin de
	 * « Recommencer les paniers », qui recommence toute la saison. Le décompte des semaines qui ouvrait
	 * la ligne est parti dans les tuiles (r31, #97, `getSettings()`).
	 *
	 * Le rejeu ne se confirme que si la formule porte une correction faite à la main : sans elle,
	 * il ne fait rien perdre (`CompositionFormulaUi::getReplayConfirm()`). La ligne se rend donc
	 * après chaque geste qui corrige ou rend une semaine.
	 */
	protected function getCounts(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$h = '<div class="util-action toolbox-composition-counts" id="toolbox-composition-counts">';

			if($eCompositionFormula->assertUpdate()) {

				$h .= $this->getSeasonSwitch();

				$posts = [
					'structure' => $eCompositionPlan['structure']['id'],
					'id' => $eCompositionFormula['id'],
				];

				$h .= '<a data-ajax="/toolbox/compositionFormula:doReplay" '.self::getPosts($posts).' '.attr('data-confirm', CompositionFormulaUi::getReplayConfirm($eCompositionFormula)).' data-waiter="'.s("Composition en cours").'" class="btn btn-sm btn-outline-primary toolbox-composition-replay">';
					$h .= \Asset::icon('arrow-repeat').' '.s("Rejouer les paniers");
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'interrupteur « Régler la saisonnalité » (lot r27, planche r27, question 1, option B) : allumé, la
	 * saison de vente de chaque espèce se dessine en barre sur son rang, que ses poignées étirent et
	 * que son corps déplace, et un glisser sur des cases grises ouvre une seconde saison
	 * (`ToolboxCompositionSeason`). Les ronds servis se masquent et le vert s'estompe, pour que la
	 * barre se lise ; une case n'ouvre plus son menu. Éteint, la grille est celle de tous les jours.
	 *
	 * L'état ne part pas au serveur : l'interrupteur et la grille rendus de nouveau par une réponse le
	 * reprennent du navigateur (`ToolboxCompositionSeason.renderSwitch()`,
	 * `ToolboxCompositionGrid.renderGrid()`).
	 */
	protected function getSeasonSwitch(): string {

		$h = '<div class="toolbox-composition-season-control">';
			$h .= \util\TextUi::switch([
				'class' => 'field-switch-sm',
				'id' => 'toolbox-composition-season-switch',
				'onrender' => 'ToolboxCompositionSeason.renderSwitch(this)',
			]);
			$h .= '<span>'.s("Régler la saisonnalité").'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La légende de la grille (lot r20, planche paniers-maquette-r16, question 4, retour du
	 * 27/09 « manque des légendes »), sur une ligne entre les décomptes et la grille, dans l'ordre de
	 * la lecture : ce qui est servi, ce qui est possible, ce qui ne l'est pas. Chaque marque reprend
	 * la teinte exacte de sa case (`composition.css`).
	 *
	 * La dernière entrée nomme l'orange du rang des prix, un panier au-delà de l'écart de la taille.
	 *
	 * « De saison » est seul pour dire le vert (r29, #87, retour du 29/09, « PAS de
	 * dégradé suivant usage ni affichage spécifique de la conservation ») : le dégradé « plus foncé si
	 * souvent proposé » et « Grâce à la conservation » sont partis avec ce qu'ils nommaient.
	 *
	 * La ligne ne défile pas avec la grille : elle est hors de `util-overflow`, et passe sur plusieurs
	 * lignes aux petites largeurs.
	 */
	protected function getLegend(CompositionPlan $eCompositionPlan): string {

		$items = [
			'served' => s("Servi"),
			'season' => s("De saison"),
			'empty' => s("Hors saison de l'espèce"),
			'off' => s("Semaine sans panier"),
		];

		// La case orange du rang des prix (planche r26, question 2, option B, retour du 28/09,
		// « on sait pas ce qu'est ce truc orange ») : toujours là, parce qu'un geste sur une case peut
		// faire passer une semaine au-delà de l'écart sans rendre la légende. Le prix et l'écart, eux, ne
		// changent qu'en rechargeant l'écran.
		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$items['gap'] = s("Au-delà de {price} à {gap}", [
			'price' => \util\TextUi::money((float)$eCompositionFormula['price'], precision: 0),
			'gap' => self::getGap($eCompositionFormula['gap']),
		]);

		$h = '<div class="util-legend toolbox-composition-legend" id="toolbox-composition-legend">';

			foreach($items as $key => $label) {
				$h .= '<span class="util-legend-item"><i class="util-legend-mark toolbox-composition-legend-'.$key.'"></i>'.$label.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La grille espèces × semaines, sur toutes les semaines de la saison et sous l'axe du plan de
	 * culture (`series\CultivationUi::getListSeason()`, une colonne par semaine). Trois états de
	 * case, dont un seul est un choix :
	 * - hors de la saison de vente de l'espèce, la trame ;
	 * - dans sa saison, le vert, d'un seul ton (`CompositionLib::getGrid()`, `weeksSeason`, r29,
	 *   #87) : c'est le réglage des saisons qui le dit, et rien d'autre ;
	 * - servie, un rond violet posé sur la teinte de sa semaine (lot r22, retour du 27/09,
	 *   « des ronds quand c'est violet plutôt que remplir la case ») : le vert dit ce qui est possible,
	 *   le rond ce que le panier sert. Hors de la fenêtre de l'espèce, le rond se pose sur le gris.
	 * Une semaine qu'on ne livre pas est une colonne sans case : elle montre la réponse à la question
	 * des semaines sans la redire.
	 *
	 * Le rond ne porte pas la quantité hors du survol : l'écart de quantité d'une semaine à l'autre vient du
	 * facteur de l'étape 8, qui ajuste chaque semaine au prix visé, et un dégradé le donnerait à lire
	 * comme un choix.
	 *
	 * Le nom de l'espèce est une cellule `td` et non un `th` : `reboot.css` passe les `th` en petites
	 * capitales, ce qui convient à l'en-tête des semaines et pas à un nom de légume.
	 *
	 * La semaine survolée (É10, `ToolboxCompositionGrid` dans `composition.js`) : chaque case d'une
	 * semaine porte `data-week`, et ce que le survol montre est rendu d'avance, masqué, pour que le
	 * survol n'aille rien chercher : dans le coin, la semaine, ses jours et sa valeur, une fois par
	 * semaine qui a un panier ; au bout du nom de chaque espèce, sa quantité dans chacune des
	 * semaines où elle est servie. Une semaine non livrée n'a ni l'un ni l'autre.
	 *
	 * La case (É11, revue au lot r24) : un clic sur une case servie ou de saison ouvre son menu
	 * (`getCellMenu()`), et c'est là seulement qu'une espèce entre au panier, en sort ou change de
	 * quantité. Un clic qui allumait la case ajoutait une espèce sans qu'on l'ait voulu. Le menu est un
	 * seul `dropdown-list`, rendu vide après le tableau pour qui peut écrire le plan, que chaque case
	 * remplit à son ouverture (`ToolboxCompositionGrid.menu()`) : la grille compte des centaines de
	 * cases, et aucune ne porte le balisage de ses gestes. La grille porte alors `data-editable`.
	 *
	 * La somme de l'année (lot r13, `getGridSum()`) ferme chaque ligne : la dernière case de l'en-tête,
	 * de chaque tête de groupe, de chaque rang et du pied, après les semaines. Un rang la dit dans
	 * l'unité de son espèce (lot r13b, `getGridRowSum()`), un groupe et le pied en kilos. La case de
	 * l'en-tête reste vide (r34f, retour du 30/09) : les unités écrites dans chaque somme
	 * disent déjà ce qu'elle compte.
	 *
	 * Les bornes de la saison (`data-season-first`, `data-season-last`) retiennent une saison qu'on
	 * déplace par son corps (`ToolboxCompositionSeason`, lot r27).
	 *
	 * La grille montre toujours toute l'année (lot r28, retour du 28/09) : les onglets de
	 * semestre du lot r16 sont partis, et avec eux la période que chaque geste postait.
	 *
	 * Les semaines livrées sont dans `data-weeks`, et leur graduation sur l'axe prend une pastille
	 * (`ToolboxCompositionGrid.decorate()`). La colonne ouverte des lots r8 à r8d, qui s'élargissait
	 * pour porter les quantités d'une semaine, est partie au lot r24 (retour du 27/09,
	 * « il n'y a plus de modification en colonne ») : chaque case porte ses gestes dans son menu, et
	 * « Revenir à la proposition » est passé sur la carte de la semaine (`getWeekCard()`).
	 */
	public function getGrid(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$seasonWeeks = $eCompositionPlan['seasonWeeks'];

		// L'axe en semaines ne lit pas la configuration de la structure, qui ne borne que l'axe au
		// jour près (`getListSeason()`, `$weekMonths`).
		$axis = new \series\CultivationUi()->getListSeason(new \map\Configuration(), $eCompositionPlan['season'], hasWeeks: TRUE, weekMonths: $eCompositionPlan['weekMonths']);

		// Une case allumée ou éteinte poste la structure, la saison et la formule : l'action relit la
		// semaine sur le plan de la structure, jamais par un `id` posté (`compositionLine.p.php`).
		$editable = $eCompositionPlan->assertUpdate();

		$h = '<div class="util-overflow toolbox-composition-grid" id="toolbox-composition-grid" data-structure="'.$eCompositionPlan['structure']['id'].'" data-formula="'.$eCompositionFormula['id'].'" data-weeks="'.implode(' ', $eCompositionPlan['weeks']).'" data-season-first="'.min($seasonWeeks).'" data-season-last="'.max($seasonWeeks).'"'.($editable ? ' data-editable' : '').' onrender="ToolboxCompositionGrid.renderGrid(this)">';

			$h .= '<table class="table-unstyled util-heat-table">';

				$h .= '<thead><tr>';
					$h .= '<th class="toolbox-composition-grid-corner"></th>';
					$h .= '<th class="toolbox-composition-grid-axis" colspan="'.count($seasonWeeks).'">';
						$h .= $axis;
					$h .= '</th>';
					$h .= '<th class="toolbox-composition-grid-sum"></th>';
				$h .= '</tr>';
				$h .= '<tr class="toolbox-composition-grid-prices" id="toolbox-composition-grid-prices">';
					$h .= $this->getGridPricesContent($eCompositionPlan, $eCompositionFormula);
				$h .= '</tr>';
				$h .= '</thead>';

				foreach($eCompositionFormula['grid'] as $group) {
					$h .= $this->getGridGroup($eCompositionPlan, $group);
				}

				$h .= '<tfoot>';
					$h .= '<tr class="toolbox-composition-grid-sum-total" id="toolbox-composition-grid-sum-total">';
						$h .= $this->getGridTotalContent($eCompositionPlan, $eCompositionFormula);
					$h .= '</tr>';
				$h .= '</tfoot>';

			$h .= '</table>';

			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
				$h .= $this->getGridTip($eCompositionPlan, $eCompositionFormula, $eCompositionWeek);
			}

			// `data-dropdown-keep` sur tout le menu : la quantité se tape et se règle d'un pas sans le
			// refermer. La poubelle le referme par sa réponse (`compositionLine.v.php`).
			if($editable) {
				$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light" id="toolbox-composition-cell-menu" data-dropdown-id="toolbox-composition-cell-menu-list" data-dropdown-keep></div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que vaut le panier de chaque semaine, sur un rang sous leurs numéros (lot r21, retour de
	 * du 27/09, « afficher le prix dans une ligne du tableau sous le numéro de semaine ») :
	 * l'unité dans le nom du rang (planche r26, question 2, « Ajoute € »). La grille montrant toute
	 * l'année (lot r28), une case de semaine est trop étroite pour deux chiffres : le rang n'y garde
	 * que l'alerte, et la valeur reste dans la bulle de la semaine (`getGridTip()`).
	 *
	 * Un panier qui s'écarte du prix de la taille au-delà de son écart (`CompositionFormula.gap`,
	 * `CompositionLib::isGap()`) se marque en alerte, comme sa carte dit le prix visé
	 * (`getWeekCard()`). Chaque case porte sa semaine (`data-week`) : elle allume sa semaine au survol
	 * comme les cases des espèces (`ToolboxCompositionGrid`).
	 *
	 * Rend les cases du rang `#toolbox-composition-grid-prices`, que les réponses ciblées remplacent par
	 * son contenu (`getGridChanges()`, `getPriced()`), pour la même raison qu'un rang.
	 */
	protected function getGridPricesContent(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$weeks = [];

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
			$weeks[$eCompositionWeek['week']] = $eCompositionWeek;
		}

		$h = '<td class="toolbox-composition-grid-name"><span class="util-label">'.s("Au panier (€)").'</span></td>';

		foreach($eCompositionPlan['seasonWeeks'] as $week) {

			$month = self::getMonthClass($eCompositionPlan, $week);

			if(in_array($week, $eCompositionPlan['weeks'], TRUE) === FALSE) {
				$h .= '<td class="toolbox-composition-grid-off'.$month.'" data-week="'.$week.'"></td>';
				continue;
			}

			if(isset($weeks[$week]) === FALSE) {
				$h .= '<td class="toolbox-composition-grid-price-week'.$month.'" data-week="'.$week.'"></td>';
				continue;
			}

			$gap = $weeks[$week]['gap'] ? ' toolbox-composition-grid-price-gap' : '';

			$h .= '<td class="toolbox-composition-grid-price-week'.$gap.$month.'" data-week="'.$week.'"></td>';

		}

		$h .= '<td class="toolbox-composition-grid-sum"></td>';

		return $h;

	}

	/**
	 * Ce que ne plus livrer la semaine corrigée `$week` fait perdre, en une phrase que le panneau
	 * « Semaine par semaine » enchaîne avec celles des autres semaines décochées.
	 */
	protected function getWeekLoss(CompositionPlan $eCompositionPlan, int $week): string {

		// Le message passe par `attr()`, qui l'encode : les noms y entrent tels quels.
		$names = array_map(fn(string $name) => s("« {name} »", ['name' => $name]), $eCompositionPlan['formulasAmended'][$week]);

		return p(
			"Vous avez corrigé la semaine {week} de {names}. Ne plus la livrer efface ses paniers et cette correction.",
			"Vous avez corrigé la semaine {week} de {names}. Ne plus la livrer efface ses paniers et ces corrections.",
			count($names),
			['week' => $week, 'names' => CompositionFormulaUi::getEnumeration($names)]
		);

	}

	/**
	 * La bulle d'une semaine qu'on survole, au-dessus de sa colonne et pointée sur sa graduation
	 * (planche r1) : son numéro et, en face, ses jours, puis deux chiffres côte à côte (r32, option A
	 * de la planche) : le nombre de produits de son panier (r30, #89, « ajouter le nb de produits au
	 * panier dans le tooltip ») et ce que vaut ce panier, dont le libellé rappelle le prix de la
	 * taille quand la semaine s'en écarte. Un geste sur une case rend la bulle de sa semaine
	 * (`getWeekChanges()`), nombre compris. Le coin de la grille, qui disait la semaine loin de sa colonne, reste vide.
	 *
	 * Rendue fermée, une par semaine qui a un panier, après le tableau : `util-tip` est en position
	 * fixe, et `ToolboxCompositionGrid.place()` (`composition.js`) l'ouvre et la place au survol.
	 * Posée dans une cellule d'en-tête, elle hériterait des petites capitales que `reboot.css` donne
	 * à `thead th`.
	 */
	protected function getGridTip(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, CompositionWeek $eCompositionWeek): string {

		$value = \util\TextUi::money($eCompositionWeek['value'], precision: 2);

		$label = $eCompositionWeek['gap'] ?
			s("au panier, {price} visé", ['price' => \util\TextUi::money((float)$eCompositionFormula['price'], precision: 0)]) :
			s("au panier");

		$h = '<div class="util-tip" data-week="'.$eCompositionWeek['week'].'">';
			$h .= '<div class="toolbox-composition-tip-head">';
				$h .= '<b>'.s("Semaine {value}", ['value' => $eCompositionWeek['week']]).'</b>';
				$h .= '<span>'.\util\DateUi::weekToDays(self::getIsoWeek($eCompositionPlan['season'], $eCompositionWeek['week']), withYear: FALSE).'</span>';
			$h .= '</div>';
			$h .= '<div class="toolbox-composition-tip-figures">';
				$h .= '<div>';
					$h .= '<div class="toolbox-composition-tip-value">'.$eCompositionWeek['lines'].'</div>';
					$h .= '<div class="toolbox-composition-tip-label">'.p("produit", "produits", $eCompositionWeek['lines']).'</div>';
				$h .= '</div>';
				$h .= '<div>';
					$h .= '<div class="toolbox-composition-tip-value">'.$value.'</div>';
					$h .= '<div class="toolbox-composition-tip-label">'.$label.'</div>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un organe de la grille, sous son propre `tbody`, avec son en-tête puis ses rangs. Un geste ne
	 * rend jamais un organe entier : il rend l'en-tête et les rangs touchés (`getGridChanges()`), et
	 * l'ordre des rangs ne se recalcule qu'au chargement suivant de l'écran.
	 */
	protected function getGridGroup(CompositionPlan $eCompositionPlan, array $group): string {

		$h = '<tbody>';

			$h .= $this->getGridGroupHead($eCompositionPlan, $group);

			foreach($group['species'] as $row) {
				$h .= $this->getGridRow($eCompositionPlan, $row);
			}

		$h .= '</tbody>';

		return $h;

	}

	protected function getGridGroupHead(CompositionPlan $eCompositionPlan, array $group): string {

		$h = '<tr class="toolbox-composition-grid-group" id="toolbox-composition-grid-'.self::getOrganKey($group['organ']).'-head">';
			$h .= $this->getGridGroupHeadContent($eCompositionPlan, $group);
		$h .= '</tr>';

		return $h;

	}

	protected function getGridGroupHeadContent(CompositionPlan $eCompositionPlan, array $group): string {

		$h = '<td colspan="'.(count($eCompositionPlan['seasonWeeks']) + 1).'">';
			$h .= '<div class="toolbox-composition-grid-group-name">';
				$h .= '<span class="util-label">'.self::getOrganName($group['organ']).'</span>';
				$h .= '<span class="util-annotation">'.p("{value} espèce", "{value} espèces", count($group['species'])).' · '.p("{value} mise au panier", "{value} mises au panier", $group['served']).'</span>';
			$h .= '</div>';
		$h .= '</td>';
		$h .= '<td class="toolbox-composition-grid-sum">'.$this->getGridSum($group['sum']).'</td>';

		return $h;

	}

	protected function getGridRow(CompositionPlan $eCompositionPlan, array $row): string {

		$h = '<tr id="toolbox-composition-grid-species-'.$row['species']['id'].'" data-species="'.$row['species']['id'].'"'.$this->getGridSeasonAttributes($row).'>';
			$h .= $this->getGridRowContent($eCompositionPlan, $row);
		$h .= '</tr>';

		return $h;

	}

	protected function getGridRowContent(CompositionPlan $eCompositionPlan, array $row): string {

		$h = '<td class="toolbox-composition-grid-name">'.$this->getGridName($eCompositionPlan, $row).'</td>';

		foreach($eCompositionPlan['seasonWeeks'] as $week) {
			$h .= '<td '.attrs($this->getGridCellAttributes($eCompositionPlan, $row, $week)).'></td>';
		}

		$h .= '<td class="toolbox-composition-grid-sum">'.$this->getGridRowSumContent($row).'</td>';

		return $h;

	}

	/**
	 * La saison de vente d'un rang (lot r27), que `ToolboxCompositionSeason` lit et réécrit : ses
	 * intervalles (`data-season`, « 14-28,38-44 »), l'espèce du plan étant celle que le rang porte déjà
	 * (`data-species`, r76). Un rang sans espèce réglable n'a pas de saison à régler et ne porte pas
	 * `data-season`, que les gestes de la saison sélectionnent ; une espèce retirée en a une vide,
	 * qu'un glisser ouvre.
	 *
	 * La barre de la saison se dessine d'après `data-season`, dans le navigateur et seulement avec
	 * « Régler la saisonnalité » allumé (`ToolboxCompositionSeason.draw()`) : rendue ici, elle portait un
	 * morceau par case de la saison, 711 éléments sur la structure 7, pour un réglage qu'on allume
	 * rarement (chantier Performances, P13).
	 */
	protected function getGridSeasonAttributes(array $row): string {

		if($row['eCompositionSpecies']->empty()) {
			return '';
		}

		return ' data-season="'.self::getSeasonData($row['season']).'"';

	}

	/**
	 * Une saison en intervalles, telle que `data-season` la porte : « 14-28,38-44 ».
	 */
	protected static function getSeasonData(array $season): string {
		return implode(',', array_map(fn(array $interval) => $interval[0].'-'.$interval[1], $season));
	}

	/**
	 * La saison d'une espèce qui n'est pas encore livrée, à régler aux poignées dans « Ajouter une
	 * espèce » (r59, le 03/10/2026, « à l'ajout permet de régler la saison avec les
	 * poignées ») : un rang de la grille, réduit à ses semaines sous l'axe des mois, que
	 * `ToolboxCompositionSeason` règle des mêmes gestes, réglage allumé d'office
	 * (`toolbox-composition-season-mode`). Le rang n'a pas d'espèce écrite (`data-species` vide) :
	 * au lâcher, la saison tracée s'écrit dans les champs `windowWeeks[…]` du formulaire
	 * (`data-season-field`), qui partent avec lui, au lieu d'être envoyée.
	 *
	 * Les champs rendus portent la saison proposée (`$windowWeeks`) : renvoyée telle quelle, elle
	 * reste celle de la source de l'espèce (`CompositionSpeciesLib::getSeasonAdded()`).
	 */
	public static function getSeasonField(CompositionPlan $eCompositionPlan, array $windowWeeks): string {

		$seasonWeeks = $eCompositionPlan['seasonWeeks'];

		$axis = new \series\CultivationUi()->getListSeason(new \map\Configuration(), $eCompositionPlan['season'], weekMonths: $eCompositionPlan['weekMonths']);

		$h = '<div class="toolbox-composition-grid toolbox-composition-season-mode toolbox-composition-season-field" data-season-first="'.min($seasonWeeks).'" data-season-last="'.max($seasonWeeks).'" data-season-field>';

			$h .= '<table class="table-unstyled">';

				$h .= '<thead><tr>';
					$h .= '<th class="toolbox-composition-grid-axis" colspan="'.count($seasonWeeks).'">'.$axis.'</th>';
				$h .= '</tr></thead>';

				$h .= '<tbody><tr data-species="" data-season="'.self::getSeasonData($windowWeeks).'" onrender="ToolboxCompositionSeason.draw(this)">';
					foreach($seasonWeeks as $week) {
						$h .= '<td data-week="'.$week.'"></td>';
					}
				$h .= '</tr></tbody>';

			$h .= '</table>';

			$h .= '<div class="toolbox-composition-season-inputs">'.self::getSeasonInputs($windowWeeks).'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les champs d'une saison en intervalles, `windowWeeks[<rang>][0]` et `[1]`, tels que la grille
	 * les poste (`ToolboxCompositionSeason.save()`). Une saison vide n'en a aucun.
	 */
	protected static function getSeasonInputs(array $windowWeeks): string {

		$form = new \util\FormUi();

		$h = '';

		foreach($windowWeeks as $position => [$start, $stop]) {
			$h .= $form->hidden('windowWeeks['.$position.'][0]', $start);
			$h .= $form->hidden('windowWeeks['.$position.'][1]', $stop);
		}

		return $h;

	}

	/**
	 * La case du nom d'un rang : sa vignette, son nom, son prix et sa roue crantée. Un geste sur une
	 * case la remplace par son contenu (`getGridChanges()`), sans toucher aux cases du rang.
	 */
	protected function getGridName(CompositionPlan $eCompositionPlan, array $row): string {

		$h = '<div class="util-vignette">';
			$h .= \plant\PlantUi::getVignette($row['species']['plant'], '1.125rem');
			if($row['eCompositionSpecies']->empty()) {
				$h .= '<span class="toolbox-composition-grid-plant">'.encode($row['species']->getLabel()).'</span>';
			} else {
				// Le nom et la roue crantée ouvrent le même panneau, « Régler une espèce » (lot r22e).
				$url = self::urlPanel('/toolbox/compositionSpecies:update', $eCompositionPlan, 'structure').'&id='.$row['eCompositionSpecies']['id'];
				$h .= '<a href="'.$url.'" class="toolbox-composition-grid-plant">'.encode($row['species']->getLabel()).'</a>';
				$h .= '<div class="util-annotation toolbox-composition-grid-price hide-sm-down">'.CompositionSpeciesUi::getGridPriceQuick($row['eCompositionSpecies'], $eCompositionPlan['eCompositionFormula']).'</div>';
				$h .= CompositionSpeciesUi::getGridFrequency($row['eCompositionSpecies'], $eCompositionPlan['eCompositionFormula']);
				// Une espèce qui a choisi sa fréquence (lot L9) se voit à sa roue pleine, à toutes les
				// largeurs ; son picto est le même que celui d'une fréquence qu'elle n'a pas choisie
				// (planche r6, question 4).
				$h .= '<a href="'.$url.'" class="btn btn-only-icon btn-xxs '.(($row['eCompositionSpecies']['frequency'] === NULL) ? 'btn-outline-primary' : 'btn-secondary').' toolbox-composition-grid-species" title="'.s("Régler {plant}", ['plant' => encode($row['species']->getLabel())]).'">'.\Asset::icon('gear-fill').'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	protected function getGridRowSumContent(array $row): string {
		return $row['sum']['served'] ? $this->getGridRowSum($row['sum']) : '';
	}

	/**
	 * Le pied de la grille, l'année de la taille entière sous la colonne des sommes. Sa ligne se
	 * remplace par son contenu, comme un rang (`getGridChanges()`).
	 */
	protected function getGridTotalContent(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula): string {

		$h = '<td class="toolbox-composition-grid-name"><span class="util-label">'.s("Sur l'année").'</span></td>';
		$h .= '<td colspan="'.count($eCompositionPlan['seasonWeeks']).'"></td>';
		$h .= '<td class="toolbox-composition-grid-sum">'.$this->getGridSum($eCompositionFormula['gridSum']).'</td>';

		return $h;

	}

	/**
	 * La somme de l'année d'un groupe ou de la taille (lot r13, planche r9, piste A de ses
	 * questions 6 et 7) : les kilos puis les euros côte à côte, alignés à droite sous leur unité, dans
	 * une colonne collée à droite de la grille (`composition.css`), qui reste en face de son espèce
	 * pendant que les semaines défilent.
	 *
	 * Des kilos inconnus (`CompositionLib::applySumsToGrid()`, une pièce ou une botte sans poids)
	 * laissent leur place vide plutôt qu'un chiffre faux ; les euros restent dans leur colonne.
	 */
	protected function getGridSum(array $sum): string {

		$kilos = ($sum['kilos'] === NULL) ? '' : CompositionSpeciesUi::getKilos($sum['kilos'], 0);

		return self::getGridSumPair($kilos, $sum['value']);

	}

	/**
	 * La somme de l'année d'une espèce (lot r13b) : sa quantité dans l'unité où elle entre au panier,
	 * écrite comme dans les cases des semaines (« 120 p. », « 85 kg »), arrondie à l'unité comme les
	 * kilos des groupes. Une quantité inconnue (`CompositionLib::applySumsToGrid()`) laisse sa place
	 * vide, comme des kilos inconnus.
	 */
	protected function getGridRowSum(array $sum): string {

		$quantity = ($sum['quantity'] === NULL) ? '' : self::getQuantity(round($sum['quantity']), $sum['eUnit']);

		return self::getGridSumPair($quantity, $sum['value']);

	}

	private static function getGridSumPair(string $quantity, float $value): string {
		return '<div class="toolbox-composition-grid-sum-pair"><span>'.$quantity.'</span><b>'.\util\TextUi::money($value, precision: 0).'</b></div>';
	}

	/**
	 * Toutes les sommes de la grille, pour un geste qui les change toutes sans rien changer d'autre
	 * à la grille : le nombre de paniers de la taille les multiplie (`compositionFormula.v.php`). Seule
	 * la case de la somme se remplace, par son contenu, dans chaque rang, chaque tête de groupe et le
	 * pied. Rend `[sélecteur => html]`.
	 */
	public function getGridSums(CompositionPlan $eCompositionPlan): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$inner = [];

		foreach($eCompositionFormula['grid'] as $group) {

			$inner['#toolbox-composition-grid-'.self::getOrganKey($group['organ']).'-head > td.toolbox-composition-grid-sum'] = $this->getGridSum($group['sum']);

			foreach($group['species'] as $row) {
				$inner['#toolbox-composition-grid-species-'.$row['species']['id'].' > td.toolbox-composition-grid-sum'] = $this->getGridRowSumContent($row);
			}

		}

		$inner['#toolbox-composition-grid-sum-total > td.toolbox-composition-grid-sum'] = $this->getGridSum($eCompositionFormula['gridSum']);

		return $inner;

	}

	/**
	 * Ce qu'un geste sur les rangs `$speciesIds` change aux sommes au-delà de ces rangs, que l'appelant
	 * rend lui-même : la tête du groupe de chacun, qui compte aussi ses mises au panier, et le pied de
	 * la grille. Rend `[sélecteur => html]`, à remplacer par leur contenu.
	 */
	protected function getGridSumChanges(CompositionPlan $eCompositionPlan, array $speciesIds): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$inner = [];

		foreach($eCompositionFormula['grid'] as $group) {

			foreach($group['species'] as $row) {

				if(in_array($row['species']['id'], $speciesIds, TRUE)) {
					$inner['#toolbox-composition-grid-'.self::getOrganKey($group['organ']).'-head'] = $this->getGridGroupHeadContent($eCompositionPlan, $group);
				}

			}

		}

		$inner['#toolbox-composition-grid-sum-total'] = $this->getGridTotalContent($eCompositionPlan, $eCompositionFormula);

		return $inner;

	}

	/**
	 * La quantité d'une ligne dans le panier d'une semaine, réglable en place : c'est le
	 * `quick(['quantity'])` de `compositionLine.p.php`, qui marque la semaine corrigée et ne touche
	 * que cette case (Q10). Elle se règle d'un pas de l'espèce par le moins et le plus qui
	 * l'entourent, et à la valeur tapée en `util-quick-inline` (planche r5, question 2) : au repos,
	 * les deux boutons disent que la quantité se change, et presque toute correction est d'un pas.
	 * Le moins s'éteint au minimum de l'espèce (`CompositionWeekLib::applySteps()`). Le balisage est
	 * celui de `shop-product-number` (`catalog\OfferUi`), élément pour élément, pour que sa montée
	 * dans le design system ne soit qu'un renommage.
	 *
	 * Les trois gestes désignent la case comme le fait la grille, par sa semaine et son espèce,
	 * jamais par l'`id` de la ligne (`compositionLine.p.php`). La même ligne se rend deux fois, dans
	 * la carte de sa semaine (`card`) et dans le menu de sa case de la grille (`menu`, lot r24) :
	 * `$place` garde distincts les identifiants de leurs champs. Dans la carte, la ligne ne porte pas
	 * son formulaire, recopié au premier clic depuis `getWeekQuantityTemplate()` (lot P10).
	 *
	 * Dans le menu de la case, une poubelle en rouge devant le moins retire l'espèce du panier
	 * (`compositionLine:doDelete`), quelle que soit sa quantité (lot r8c, poubelle au lot r21) : c'est
	 * le seul geste qui retire une ligne de la grille, aucune case ne s'éteignant d'un clic. Le moins
	 * s'arrête donc au minimum, éteint comme dans la carte, au lieu de retirer lui-même la ligne
	 * (lot r8b) : un clic répété sur le moins ne fait pas disparaître la ligne. La case vide reprend
	 * alors le champ de sa quantité (`getGridServe()`, lot r24b), prérempli au minimum de l'espèce.
	 */
	protected function getWeekQuantity(CompositionPlan $eCompositionPlan, CompositionLine $eCompositionLine, int $week, string $place): string {

		$quantity = self::getQuantity($eCompositionLine['quantity'], $eCompositionLine['unit']);

		if($eCompositionLine->assertUpdate() === FALSE) {
			return $quantity;
		}

		$url = \util\FormUi::getQuickUrl($eCompositionLine);

		$posts = [
			'property' => 'quantity',
			'structure' => $eCompositionPlan['structure']['id'],
			'formula' => $eCompositionPlan['eCompositionFormula']['id'],
			'week' => $week,
			'species' => $eCompositionLine['species']['id'],
		];

		$form = new \util\FormUi();

		$h = '<div class="toolbox-composition-number">';

			if($place === 'menu') {
				$h .= '<a data-ajax="/toolbox/compositionLine:doDelete" '.self::getPosts(array_diff_key($posts, ['property' => TRUE])).' class="btn btn-only-icon btn-xs btn-outline-danger toolbox-composition-number-remove" title="'.s("Retirer du panier").'">'.\Asset::icon('trash').'</a>';
			}

			if($eCompositionLine['quantityLess'] === NULL) {
				$h .= '<a class="btn btn-only-icon btn-xs btn-outline-primary toolbox-composition-number-decrease disabled" title="'.s("Moins").'">'.\Asset::icon('dash').'</a>';
			} else {
				$h .= '<a data-ajax="'.$url.'" '.self::getPosts($posts + ['quantity' => $eCompositionLine['quantityLess']]).' class="btn btn-only-icon btn-xs btn-outline-primary toolbox-composition-number-decrease" title="'.s("Moins").'">'.\Asset::icon('dash').'</a>';
			}

			$h .= '<span class="toolbox-composition-number-value">';

				$id = 'toolbox-composition-quantity-'.$place.'-'.$week.'-'.$eCompositionLine['species']['id'];

				if($place === 'card') {

					$h .= '<div class="util-quick-inline" id="'.$id.'" '.attrs([
						'data-week' => $week,
						'data-species' => $eCompositionLine['species']['id'],
						'data-quantity' => $eCompositionLine['quantity'],
						'data-unit' => \product\UnitUi::getSingular($eCompositionLine['unit'], short: TRUE),
					]).'>';
						$h .= '<span><a class="util-quick" data-composition-quantity>'.$quantity.'</a></span>';
					$h .= '</div>';

				} else {
					$h .= \main\QuickInlineAiUi::get(
						$id,
						\main\QuickInlineAiUi::open($quantity),
						$url,
						$posts,
						$form->dynamicField($eCompositionLine, 'quantity')
					);
				}

			$h .= '</span>';

			$h .= '<a data-ajax="'.$url.'" '.self::getPosts($posts + ['quantity' => $eCompositionLine['quantityMore']]).' class="btn btn-only-icon btn-xs btn-outline-primary toolbox-composition-number-increase" title="'.s("Plus").'">'.\Asset::icon('plus').'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le champ de la quantité d'une ligne des cartes des semaines, une fois pour toutes les lignes
	 * (lot P10) : chaque ligne ne porte que sa valeur et ce qui la distingue (`data-week`,
	 * `data-species`, `data-quantity`, `data-unit`, `getWeekQuantity()`), et `composition.js` recopie ce
	 * formulaire dans la ligne au premier clic, sans requête. Rendre le formulaire dans chaque ligne
	 * coûtait 1 Ko par ligne, 251 formulaires sur la structure 7 (environ 290 Ko), pour un champ
	 * qu'on ouvre rarement.
	 *
	 * C'est le formulaire de `QuickInlineAiUi::get()`, tel quel, dans un `<template>` : son contenu
	 * n'est pas dans le document, ses identifiants n'y entrent donc en conflit avec rien. L'unité
	 * change d'une ligne à l'autre, son addon est laissé vide et se remplit à la copie. La semaine,
	 * l'espèce et la valeur y sont vides pour la même raison.
	 *
	 * Le menu d'une case de la grille garde son formulaire rendu (`getCellMenu()`) : il arrive seul,
	 * à la demande, et n'a pas ce coût.
	 */
	protected function getWeekQuantityTemplate(CompositionPlan $eCompositionPlan): string {

		$eCompositionLine = new CompositionLine(['quantity' => NULL]);

		$posts = [
			'property' => 'quantity',
			'structure' => $eCompositionPlan['structure']['id'],
			'formula' => $eCompositionPlan['eCompositionFormula']['id'],
			'week' => '',
			'species' => '',
		];

		$field = new \util\FormUi()->dynamicField($eCompositionLine, 'quantity', function(\PropertyDescriber $d) {
			$d->append = fn(\util\FormUi $form) => $form->addon('');
		});

		$h = '<template id="toolbox-composition-quantity-template">';
			$h .= \main\QuickInlineAiUi::get('', '', \util\FormUi::getQuickUrl($eCompositionLine), $posts, $field);
		$h .= '</template>';

		return $h;

	}

	/**
	 * Les attributs d'une case de la grille, `NULL` pour un attribut que la case n'a pas : la grille
	 * les écrit (`getGridRowContent()`), et un geste sur la case les pose tels quels sur la case en
	 * place (`getGridCellChanges()`), sans la remplacer, parce qu'elle porte le menu ouvert
	 * (`Lime.Dropdown` referme le menu dont la case quitte le document).
	 *
	 * Une case de la saison de vente de l'espèce est verte, d'un seul ton
	 * (`toolbox-composition-grid-season`, `CompositionLib::getGrid()`, `weeksSeason`) : c'est le
	 * réglage des saisons seul qui la dit (r29, #87, retour du 29/09, « PAS de dégradé
	 * suivant usage ni affichage spécifique de la conservation »). Les cases servies et celles des
	 * semaines livrées où l'espèce peut entrer (`open`), de sa saison ou non (r29, #83), portent
	 * `toolbox-composition-grid-cell` : ce sont elles qui ouvrent le menu de la case (`getCellMenu()`,
	 * lot r24). Un chou s'ouvre aussi dans une semaine qui en sert déjà un (r34d, retour
	 * du 30/09, « le user fait ce qu'il veut »). Une case
	 * corrigée à la main se lit comme une case proposée (lot r21, retour du 27/09,
	 * « enlève cette distinction ») : la correction se dit sur la carte de sa semaine. Une case
	 * proposée qu'on a retirée redevient une case de saison. Une case servie garde le vert de sa
	 * semaine sous son rond (lot r22), ou le gris hors de la saison, qui est aussi le rendu d'une
	 * ligne posée hors saison à la main. Elle porte aussi sa quantité sans unité, `data-quantity` (lot r22, retour du 27/09, « au survol sur une ligne/colonne […]
	 * afficher les quantités dans la case ») : la feuille l'écrit dans le rond de la ligne et de la
	 * colonne survolées, et l'unité est celle que le prix du rang donne dans la colonne des noms.
	 *
	 * Le titre est en clair : `attrs()` l'encode, et une réponse ciblée le pose par `setAttribute()`.
	 */
	protected function getGridCellAttributes(CompositionPlan $eCompositionPlan, array $row, int $week): array {

		$month = self::getMonthClass($eCompositionPlan, $week);

		$attributes = [
			'class' => NULL,
			'title' => NULL,
			'data-week' => (string)$week,
			'data-served' => NULL,
			'data-quantity' => NULL,
		];

		if(in_array($week, $eCompositionPlan['weeks'], TRUE) === FALSE) {
			$attributes['class'] = 'toolbox-composition-grid-off'.$month;
			return $attributes;
		}

		$served = isset($row['lines'][$week]);
		$season = isset($row['weeksSeason'][$week]);
		$open = ($served or isset($row['open'][$week]));

		if($season === FALSE and $open === FALSE) {
			$attributes['class'] = ($month === '') ? NULL : 'toolbox-composition-grid-month';
			return $attributes;
		}

		$classes = [];

		if($season) {
			$classes[] = 'toolbox-composition-grid-season';
		}

		if($open) {
			$classes[] = 'toolbox-composition-grid-cell';
		}

		$attributes['title'] = s("{plant}, semaine {week}", ['plant' => $row['species']->getLabel(), 'week' => $week]);

		if($served) {
			$attributes['data-served'] = '';
			$attributes['data-quantity'] = \util\TextUi::number($row['lines'][$week]['quantity'], NULL);

		}

		$attributes['class'] = implode(' ', $classes).$month;

		return $attributes;

	}

	/**
	 * La classe d'une case de la semaine qui ouvre un mois (lot r21, retour du 27/09,
	 * « lignes verticales pour séparer les mois ») : la feuille tire devant elle le trait qui prolonge,
	 * dans la grille, la séparation des mois de l'axe. Vide pour les autres semaines, et à écrire en
	 * dernier dans l'attribut, précédée de son espace.
	 */
	protected static function getMonthClass(CompositionPlan $eCompositionPlan, int $week): string {

		if(in_array($week, $eCompositionPlan['monthStarts'], TRUE)) {
			return ' toolbox-composition-grid-month';
		} else {
			return '';
		}

	}

	/**
	 * Le menu d'une case de la grille (lot r24, retour du 27/09, « quand on clique sur une
	 * case, ça permet de modifier la quantité pour la case ou la supprimer du panier »), que la case
	 * demande à son ouverture (`compositionLine:getMenu`) et que chaque geste qu'il porte rend de
	 * nouveau (`compositionLine.v.php`) : l'espèce et la semaine en titre, puis, pour une case servie,
	 * la poubelle, le moins, la quantité et le plus (`getWeekQuantity()`), et pour une case de saison
	 * que la semaine ne sert pas, de saison ou non, le champ de sa quantité (`getGridServe()`, lot r24b).
	 *
	 * Vide pour une case qui n'a rien à proposer : un rang sans unité au panier, et à qui ne peut pas
	 * écrire le plan. Une case hors saison s'ouvre comme une autre (r29,
	 * #83, qui revient sur l'arbitrage du lot r24). Le menu ne s'ouvre
	 * pas alors. Une semaine non livrée n'arrive pas jusqu'ici : la page ne trouve pas sa semaine. `$speciesId` et `$week` viennent de la requête : une espèce
	 * que la grille n'a pas rend aussi un menu vide.
	 *
	 * Le plan doit avoir été lu pour la semaine `$week` (`CompositionPlanLib::getForCellMenu()` à
	 * l'ouverture, `getForScreen()` avec `$week` après un geste), qui pose sur chaque rang ce que sa
	 * case peut recevoir.
	 */
	public function getCellMenu(CompositionPlan $eCompositionPlan, int $speciesId, int $week): string {

		if($eCompositionPlan->assertUpdate() === FALSE) {
			return '';
		}

		foreach($eCompositionPlan['eCompositionFormula']['grid'] as $group) {

			foreach($group['species'] as $row) {

				if($row['species']['id'] !== $speciesId) {
					continue;
				}

				if(isset($row['lines'][$week])) {
					$content = $this->getWeekQuantity($eCompositionPlan, $row['lines'][$week], $week, 'menu');
				} else if($row['eCompositionLineAddable']->notEmpty()) {
					$content = $this->getGridServe($eCompositionPlan, $row['eCompositionLineAddable'], $week);
				} else {
					return '';
				}

				$h = '<div class="dropdown-title">'.s("{plant}, semaine {week}", ['plant' => encode($row['species']->getLabel()), 'week' => $week]).'</div>';
				$h .= '<div class="dropdown-block toolbox-composition-cell-menu">'.$content.'</div>';

				return $h;

			}

		}

		return '';

	}

	/**
	 * Le champ du menu d'une case que la semaine ne sert pas, de saison ou non (lot r24b, retour de
	 * du 27/09, « mets directement un champ avec la valeur à ajouter et un bouton valide ») : la quantité
	 * dans l'unité où l'espèce entre au panier, préremplie à son minimum, et le bouton qui ajoute
	 * l'espèce au panier de la semaine (`CompositionLineLib::create()`). Le menu prend alors la
	 * quantité avec ses gestes (`getWeekQuantity()`).
	 *
	 * L'espèce et son unité partent avec la quantité, que `build()` borne au minimum et au pas de
	 * l'espèce dans cette unité (`CompositionLine::properties()`). Un refus se dit sous le champ :
	 * le groupe prend le nom du dernier champ rendu (`FormUi::group()`), `quantity`.
	 */
	protected function getGridServe(CompositionPlan $eCompositionPlan, CompositionLine $eCompositionLine, int $week): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->openAjax('/toolbox/compositionLine:doCreate');

			$h .= $form->hidden('structure', $eCompositionPlan['structure']['id']);
			$h .= $form->hidden('formula', $eCompositionPlan['eCompositionFormula']['id']);
			$h .= $form->hidden('week', $week);
			$h .= $form->hidden('species', $eCompositionLine['species']);
			$h .= $form->hidden('unit', $eCompositionLine['unit']);

			$h .= $form->group(content: $form->dynamicField($eCompositionLine, 'quantity', function(\PropertyDescriber $d) {
				$d->append = [$d->append, fn(\util\FormUi $form) => $form->submit(s("Valider"), ['class' => 'btn btn-primary'])];
			}));

		$h .= $form->close();

		return $h;

	}

	/**
	 * Ce qu'une case allumée ou éteinte change à l'écran, comme une quantité réglée, sur le plan relu
	 * après le geste (`CompositionPlanLib::getForScreen()`) : la tête de la formule (le nombre de
	 * légumes et l'étendue des valeurs), les compteurs, ce que la semaine montre
	 * (`getWeekChanges()`) et, dans la grille, ce que le geste a touché (`getGridChanges()`).
	 * Rend `['outer' => [sélecteur => html], 'inner' => [sélecteur => html], 'attributes' =>
	 * [sélecteur => [attribut => valeur]]]`.
	 */
	public function getToggled(CompositionPlan $eCompositionPlan, int $week, int $speciesId): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$outer = [
			'#toolbox-composition-settings' => $this->getSettings($eCompositionPlan, $eCompositionFormula),
			'#toolbox-composition-counts' => $this->getCounts($eCompositionPlan, $eCompositionFormula),
		] + $this->getWeekChanges($eCompositionPlan, $week);

		return $this->getLeanChanges($eCompositionPlan, $this->getGridChanges($eCompositionPlan, $week, [$speciesId], $outer));

	}

	/**
	 * Ce qu'un prix, un pas ou un minimum changés depuis le menu d'une espèce changent à l'écran
	 * (lot M, planche r1), sur le plan relu après le geste. Rien ne se recompose (Q9) : les paniers
	 * restent, leur valeur bouge. Se rendent la tête de la formule (l'étendue des valeurs), le rang de
	 * l'espèce, dont le menu et le bout du nom écrivent le nouveau prix ou la nouvelle vente, la somme
	 * de son groupe et celle de la taille (`getGridSumChanges()`), la bulle et la carte de chaque
	 * semaine où elle entre, le rang des prix des semaines (lot r21), et la soudure, qu'un prix, un
	 * pas ou un minimum déplacent
	 * (`getLeanChanges()`). Rend `['outer' => [sélecteur => html], 'inner' => [sélecteur => html],
	 * 'attributes' => [sélecteur => [attribut => valeur]]]`, comme `getToggled()` et pour la même
	 * raison : un rang se remplace par son contenu.
	 *
	 * Seule la taille ouverte se rend : les autres tailles, dont la valeur a bougé aussi, se lisent
	 * sur leur onglet, qui recharge l'écran.
	 */
	public function getPriced(CompositionPlan $eCompositionPlan, int $speciesId): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$outer = [
			'#toolbox-composition-settings' => $this->getSettings($eCompositionPlan, $eCompositionFormula),
		];

		$inner = [];

		$weeks = [];

		foreach($eCompositionFormula['grid'] as $group) {

			foreach($group['species'] as $row) {

				if($row['species']['id'] === $speciesId) {
					$inner['#toolbox-composition-grid-species-'.$speciesId] = $this->getGridRowContent($eCompositionPlan, $row);
					$weeks = array_keys($row['lines']);
				}

			}

		}

		$inner += $this->getGridSumChanges($eCompositionPlan, [$speciesId]);

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			if(in_array($eCompositionWeek['week'], $weeks, TRUE)) {
				$outer['#toolbox-composition-grid > .util-tip[data-week="'.$eCompositionWeek['week'].'"]'] = $this->getGridTip($eCompositionPlan, $eCompositionFormula, $eCompositionWeek);
				$outer['#toolbox-composition-card-'.$eCompositionWeek['week']] = $this->getWeekCard($eCompositionPlan, $eCompositionFormula, $eCompositionWeek['week']);
			}

		}

		if($weeks !== []) {
			$inner['#toolbox-composition-grid-prices'] = $this->getGridPricesContent($eCompositionPlan, $eCompositionFormula);
		}

		return $this->getLeanChanges($eCompositionPlan, [
			'outer' => $outer,
			'inner' => $inner,
			'attributes' => [],
		]);

	}

	/**
	 * Ce qu'un poids réglé change à l'écran (lot L3), sous la même forme que `getPriced()` : le rang
	 * d'où il est parti. Dans la grille, le rang de l'espèce et ses kilos de l'année avec ceux de son
	 * groupe et de la taille (`getGridSumChanges()`) ; dans la synthèse (`$synthesis`), ses kilos de
	 * l'année et le plan de culture face aux paniers. Le poids ne change ni la valeur des semaines ni
	 * ce que les paniers portent.
	 */
	public function getWeighed(CompositionPlan $eCompositionPlan, int $speciesId, bool $synthesis): array {

		$outer = [];
		$inner = [];

		if($synthesis) {

			foreach($eCompositionPlan['quantities']['groups'] as $group) {

				foreach($group['rows'] as $row) {

					if($row['species']['id'] === $speciesId and $row['unit']->notEmpty()) {
						$outer['#toolbox-composition-quantities-'.$speciesId.'-'.$row['unit']['id']] = $this->getQuantitiesRow($eCompositionPlan, $row);
					}

				}

			}

		} else {

			foreach($eCompositionPlan['eCompositionFormula']['grid'] as $group) {

				foreach($group['species'] as $row) {

					if($row['species']['id'] === $speciesId) {
						$inner['#toolbox-composition-grid-species-'.$speciesId] = $this->getGridRowContent($eCompositionPlan, $row);
					}

				}

			}

			$inner += $this->getGridSumChanges($eCompositionPlan, [$speciesId]);

		}

		$changes = [
			'outer' => $outer,
			'inner' => $inner,
			'attributes' => [],
		];

		// Le poids pèse le stock d'une espèce comptée à la pièce ou à la botte, donc la soudure ; la
		// synthèse n'a pas de cartes.
		return $synthesis ? $changes : $this->getLeanChanges($eCompositionPlan, $changes);

	}

	/**
	 * Ce qu'un retour à la proposition change à l'écran, sous la même forme que `getToggled()`. La
	 * semaine remet et retire des espèces, dans plusieurs groupes : se rendent les rangs de
	 * `$speciesIds`, les espèces servies dans la semaine avant ou après le retour.
	 *
	 * Une semaine que la proposition d'aujourd'hui n'a plus a disparu
	 * (`CompositionLib::recomposeWeeks()`) : les paniers se rendent alors en entier.
	 */
	public function getReset(CompositionPlan $eCompositionPlan, int $week, array $speciesIds): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			if($eCompositionWeek['week'] === $week) {

				$outer = [
					'#toolbox-composition-settings' => $this->getSettings($eCompositionPlan, $eCompositionFormula),
					'#toolbox-composition-counts' => $this->getCounts($eCompositionPlan, $eCompositionFormula),
				] + $this->getWeekChanges($eCompositionPlan, $week);

				return $this->getLeanChanges($eCompositionPlan, $this->getGridChanges($eCompositionPlan, $week, $speciesIds, $outer));

			}

		}

		return [
			'outer' => [
				'#toolbox-composition-proposal' => $this->getProposal($eCompositionPlan),
			],
			'inner' => [],
			'attributes' => [],
		];

	}

	/**
	 * Ce qu'une saison de vente réglée sur la grille change à l'écran (lot r27), sous la même forme
	 * que `getToggled()`, sur le plan relu après le geste :
	 *
	 * - le rang de l'espèce, sa saison (`data-season`, d'où le navigateur redessine la barre) comprise,
	 *   et ses cases, que la saison passe au vert ou au gris ;
	 * - pour chaque semaine refaite (`$weeks`, `CompositionSpeciesLib::updateSeason()`), sa bulle, sa
	 *   carte, et la case de la semaine de chaque rang, la proposition ayant pu y mettre ou y retirer
	 *   n'importe quelle espèce ; avec elles, le nom et la somme de l'année de chaque rang, qui
	 *   comptent ce que la taille sert, et les têtes de groupe et le pied (`getGridSumChanges()`) ;
	 * - la tête de la taille et les décomptes, dès qu'une semaine est refaite, et la soudure, dont les
	 *   espèces sont celles de leur saison (`getLeanChanges()`).
	 *
	 * Une saison allongée ne refait aucune semaine : seuls le rang et la soudure se rendent. Les autres
	 * rangs ne changent pas : leur vert ne tient qu'à leur propre saison (r29, #87), et le lot r28, qui
	 * reteintait leurs cases aux semaines que la saison gagne ou quitte, n'a plus de teinte à poser. Une espèce que la grille relue n'a plus rend la grille entière, comme
	 * `getGridChanges()`.
	 *
	 * Le rang reste en place même quand sa saison allongée ou raccourcie le rangerait ailleurs
	 * (r72, le rang se compte sur la longueur de la saison) : comme pour `getGridChanges()`, un rang
	 * ne saute pas sous la poignée qu'on vient de lâcher, et l'ordre se refait au prochain chargement.
	 */
	public function getSeasoned(CompositionPlan $eCompositionPlan, int $speciesId, array $weeks): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$outer = [];
		$inner = [];
		$attributes = [];

		$rows = [];

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				$rows[$row['species']['id']] = $row;
			}
		}

		if(array_key_exists($speciesId, $rows) === FALSE) {

			return $this->getLeanChanges($eCompositionPlan, [
				'outer' => [
					'#toolbox-composition-grid' => $this->getGrid($eCompositionPlan, $eCompositionFormula),
				],
				'inner' => [],
				'attributes' => [],
			]);

		}

		// La tête se rend avant tout rang, comme dans `getProposal()` : les `post-*` des formulaires
		// rapides sont communs à toutes les classes (`Data::setQuickAttribute()`), et ceux qu'un rang
		// pose (la taille, la semaine, l'origine) passeraient sinon dans les réglages de la tête.
		if($weeks !== []) {
			$outer['#toolbox-composition-settings'] = $this->getSettings($eCompositionPlan, $eCompositionFormula);
			$outer['#toolbox-composition-counts'] = $this->getCounts($eCompositionPlan, $eCompositionFormula);
		}

		$selector = '#toolbox-composition-grid-species-'.$speciesId;

		$inner[$selector] = $this->getGridRowContent($eCompositionPlan, $rows[$speciesId]);
		$attributes[$selector] = ['data-season' => self::getSeasonData($rows[$speciesId]['season'])];

		if($weeks !== []) {

			foreach($weeks as $week) {
				$outer += $this->getWeekChanges($eCompositionPlan, $week);
			}

			foreach($rows as $rowSpeciesId => $row) {

				if($rowSpeciesId === $speciesId) {
					continue;
				}

				$selectorRow = '#toolbox-composition-grid-species-'.$rowSpeciesId;

				$inner[$selectorRow.' > td.toolbox-composition-grid-name'] = $this->getGridName($eCompositionPlan, $row);
				$inner[$selectorRow.' > td.toolbox-composition-grid-sum'] = $this->getGridRowSumContent($row);
				$attributes += $this->getGridCellChanges($eCompositionPlan, $row, $weeks);

			}

			$inner += $this->getGridSumChanges($eCompositionPlan, array_keys($rows));
			$inner['#toolbox-composition-grid-prices'] = $this->getGridPricesContent($eCompositionPlan, $eCompositionFormula);

		}

		return $this->getLeanChanges($eCompositionPlan, [
			'outer' => $outer,
			'inner' => $inner,
			'attributes' => $attributes,
		]);

	}

	/**
	 * Ce qu'une fréquence choisie ou rendue change à l'écran (lot r28), sur le plan relu après le
	 * geste et sous la même forme que `getToggled()`. Elle recompose toutes les tailles
	 * (`CompositionSpeciesLib::update()`), et `$changes` est ce que la taille ouverte a changé,
	 * `[semaine => [id d'espèce] ou NULL]` (`CompositionLib::recomposeWeeks()`) :
	 *
	 * - la tête de la taille et les décomptes ;
	 * - pour chaque semaine changée, sa bulle et sa carte (`getWeekChanges()`) ;
	 * - le rang entier de l'espèce, dont le nom dit la fréquence et l'unité, et de chaque espèce dont
	 *   une ligne a changé. Les autres rangs ne changent pas : leur vert ne tient qu'à la saison de
	 *   leur espèce (r29, #87), leur rond qu'à leurs lignes, et toutes leurs cases restent ouvertes,
	 *   un autre chou servi n'en fermant aucune (r34d) ;
	 * - les têtes de groupe, le pied, le rang des prix et la soudure.
	 *
	 * Un rang touché se rend d'une instruction, et non plus case par case : quatre attributs par case
	 * et par semaine changée faisaient 800 à 1 000 instructions pour une fréquence sur 999970
	 * (chantier Performances, P9). Le geste part du panneau ou du menu du nom, jamais du menu d'une
	 * case, que `Lime.Dropdown` refermerait si sa case quittait le document (`getGridChanges()`).
	 *
	 * Une unité ou le poids d'une unité choisie passent aussi par ici (P9). Ils peuvent rendre l'espèce
	 * vendable ou non (`CompositionLib::isSold()`), donc candidate ou non dans toute sa saison : c'est
	 * son rang, rendu entier. Une espèce qui n'a plus de rang se rattrape par le cas ci-dessous ; une
	 * espèce qui en gagne un n'en avait pas à l'écran, et ne s'ouvre alors que depuis l'onglet
	 * « Espèces », dont la réponse rend son rang dans le tableau (`getSpeciesUpdated()`).
	 *
	 * Mesuré sur un plan de 18 semaines livrées et 32 espèces, une fréquence change 9 à 15 semaines :
	 * les semaines restées identiques et leurs cartes, qui pèsent 16 Ko chacune, ne se rendent pas.
	 *
	 * Une semaine née ou disparue change les cartes et les bulles que l'écran porte, et une espèce
	 * que la grille relue n'a plus n'a plus de rang où se poser : les paniers se rendent alors entiers,
	 * comme avant le lot.
	 */
	public function getRecomposed(CompositionPlan $eCompositionPlan, int $speciesId, array $changes): array {

		$speciesIds = array_values(array_unique(array_merge([$speciesId], ...array_values(array_filter($changes, fn(?array $speciesIds) => $speciesIds !== NULL)))));

		$rows = $this->getGridRows($eCompositionPlan);

		if(
			in_array(NULL, $changes, TRUE) or
			array_diff($speciesIds, array_keys($rows)) !== []
		) {
			return $this->getProposalChanges($eCompositionPlan);
		}

		return $this->getRecomposedChanges($eCompositionPlan, $rows, $speciesIds, $changes);

	}

	/**
	 * Ce qu'une espèce retirée de mes cultures depuis le panneau « Régler une espèce » change à l'écran
	 * (`CompositionSpeciesLib::exclude()`), sur le plan relu après le geste, `$changes` étant ce que la
	 * taille ouverte a changé : ce que rend une fréquence (`getRecomposed()`), plus
	 *
	 * - la saison du rang (`data-season`), vidée, quand l'espèce garde un rang parce qu'une semaine
	 *   corrigée la sert encore ;
	 * - sinon le retrait du rang, sous `remove`, et la tête de son groupe, qui ne la compte plus. Un
	 *   groupe vidé n'a plus de tête où se poser : les paniers se rendent alors entiers.
	 *
	 * Le retrait rendait les paniers entiers, 630 Ko sur 999970 (chantier Performances, P9).
	 *
	 * La grille range l'espèce sous l'organe de l'espèce qu'elle lit, une variante (r76) sous celui
	 * de son espèce.
	 */
	public function getExcluded(CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies, array $changes): array {

		$eCompositionSpecies->expects(['plant' => ['organ']]);

		$speciesId = $eCompositionSpecies['id'];

		$rows = $this->getGridRows($eCompositionPlan);

		if(array_key_exists($speciesId, $rows)) {

			$recomposed = $this->getRecomposed($eCompositionPlan, $speciesId, $changes);

			if(isset($recomposed['outer']['#toolbox-composition-proposal']) === FALSE) {
				$recomposed['attributes']['#toolbox-composition-grid-species-'.$speciesId] = ['data-season' => self::getSeasonData($rows[$speciesId]['season'])];
			}

			return $recomposed + ['remove' => []];

		}

		$speciesIds = array_values(array_unique(array_merge(...array_values(array_filter($changes, fn(?array $speciesIds) => $speciesIds !== NULL)))));
		$speciesIds = array_values(array_diff($speciesIds, [$speciesId]));

		$groups = array_filter($eCompositionPlan['eCompositionFormula']['grid'], fn(array $group) => $group['organ'] === $eCompositionSpecies['plant']['organ']);

		if(
			in_array(NULL, $changes, TRUE) or
			array_diff($speciesIds, array_keys($rows)) !== [] or
			$groups === []
		) {
			return $this->getProposalChanges($eCompositionPlan) + ['remove' => []];
		}

		$group = $groups[array_key_first($groups)];

		$recomposed = $this->getRecomposedChanges($eCompositionPlan, $rows, $speciesIds, $changes);
		$recomposed['inner']['#toolbox-composition-grid-'.self::getOrganKey($group['organ']).'-head'] = $this->getGridGroupHeadContent($eCompositionPlan, $group);

		return $recomposed + [
			'remove' => ['#toolbox-composition-grid-species-'.$speciesId],
		];

	}

	/**
	 * Les rangs de la grille de la taille ouverte, `[id d'espèce => rang]`.
	 */
	protected function getGridRows(CompositionPlan $eCompositionPlan): array {

		$rows = [];

		foreach($eCompositionPlan['eCompositionFormula']['grid'] as $group) {
			foreach($group['species'] as $row) {
				$rows[$row['species']['id']] = $row;
			}
		}

		return $rows;

	}

	/**
	 * Les paniers rendus entiers, dans la forme des réponses ciblées.
	 */
	protected function getProposalChanges(CompositionPlan $eCompositionPlan): array {

		return [
			'outer' => [
				'#toolbox-composition-proposal' => $this->getProposal($eCompositionPlan),
			],
			'inner' => [],
			'attributes' => [],
		];

	}

	/**
	 * Ce que `getRecomposed()` rend quand chaque espèce de `$speciesIds` a son rang dans `$rows`.
	 */
	protected function getRecomposedChanges(CompositionPlan $eCompositionPlan, array $rows, array $speciesIds, array $changes): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		// La tête se rend avant tout rang, comme dans `getProposal()` : les `post-*` des formulaires
		// rapides sont communs à toutes les classes (`Data::setQuickAttribute()`), et ceux qu'un rang
		// pose passeraient sinon dans les réglages de la tête.
		$outer = [
			'#toolbox-composition-settings' => $this->getSettings($eCompositionPlan, $eCompositionFormula),
			'#toolbox-composition-counts' => $this->getCounts($eCompositionPlan, $eCompositionFormula),
		] + $this->getWeekCardsChanges($eCompositionPlan, array_keys($changes));

		$inner = [];

		foreach($rows as $rowSpeciesId => $row) {

			if(in_array($rowSpeciesId, $speciesIds, TRUE)) {
				$inner['#toolbox-composition-grid-species-'.$rowSpeciesId] = $this->getGridRowContent($eCompositionPlan, $row);
			}

		}

		$inner += $this->getGridSumChanges($eCompositionPlan, $speciesIds);
		$inner['#toolbox-composition-grid-prices'] = $this->getGridPricesContent($eCompositionPlan, $eCompositionFormula);

		return $this->getLeanChanges($eCompositionPlan, [
			'outer' => $outer,
			'inner' => $inner,
			'attributes' => [],
		]);

	}

	/**
	 * La bulle et la carte de chacune des semaines `$weeks` (`getWeekChanges()`).
	 */
	protected function getWeekCardsChanges(CompositionPlan $eCompositionPlan, array $weeks): array {

		$outer = [];

		foreach($weeks as $week) {
			$outer += $this->getWeekChanges($eCompositionPlan, $week);
		}

		return $outer;

	}

	/**
	 * Ce qu'une semaine montre d'elle-même et qu'un geste sur ses paniers change : sa bulle et sa
	 * carte sous la grille (`getWeekCard()`). Une semaine qui n'est pas livrée n'a ni bulle ni carte.
	 */
	protected function getWeekChanges(CompositionPlan $eCompositionPlan, int $week): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$outer = [];

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			if($eCompositionWeek['week'] === $week) {
				$outer['#toolbox-composition-grid > .util-tip[data-week="'.$week.'"]'] = $this->getGridTip($eCompositionPlan, $eCompositionFormula, $eCompositionWeek);
				$outer['#toolbox-composition-card-'.$week] = $this->getWeekCard($eCompositionPlan, $eCompositionFormula, $week);
			}

		}

		return $outer;

	}

	/**
	 * Ce qu'un geste sur la semaine `$week` change dans la grille, ajouté à `$outer`. Un geste ne
	 * réordonne pas la grille (E-Paniers, R1) : un rang qui sauterait ailleurs sous le curseur est un
	 * défaut, et l'ordre des rangs, qui se compte sur la longueur de la saison (r72), se refait au
	 * prochain chargement de l'écran. Chaque changement se pose donc en place :
	 *
	 * - pour chacune des espèces `$speciesIds`, le nom et la somme de l'année de son rang, la case de la
	 *   semaine (`getGridCellChanges()`) ; la tête de son groupe,
	 *   qui compte ses mises au panier et porte sa somme de l'année, avec le pied de la grille
	 *   (`getGridSumChanges()`) ;
	 * - le rang des prix des semaines (`getGridPricesContent()`, lot r21).
	 *
	 * Le rang d'une espèce touchée garde ses cases (lot r24) : la case du geste porte le menu ouvert,
	 * que `Lime.Dropdown` refermerait dès qu'elle quitte le document, et le menu reste ouvert pour le
	 * pas suivant. Une case ne change que ses attributs, qui la dessinent tout entière.
	 *
	 * Le nom, la somme d'un rang et la tête de groupe se remplacent par leur contenu (`inner`) et
	 * jamais en entier : `renderOuter()` (`lime.js`) analyse le html reçu par `DOMParser` comme un
	 * corps de document, où un `<tr>` ou un `<td>` hors de sa table perd sa balise ; `innerHTML` sur
	 * le `<tr>` les analyse dans leur contexte. Un rang garde son `id` et son `data-species`, qui ne
	 * changent pas.
	 *
	 * Une espèce que la grille relue n'a plus (servie à la main hors de sa fenêtre, et qu'on retire ou
	 * que la proposition retire) n'a plus de rang où se poser : la grille se rend alors entière, et
	 * c'est le seul cas.
	 */
	protected function getGridChanges(CompositionPlan $eCompositionPlan, int $week, array $speciesIds, array $outer): array {

		$eCompositionFormula = $eCompositionPlan['eCompositionFormula'];

		$rows = [];

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				if(in_array($row['species']['id'], $speciesIds, TRUE)) {
					$rows[$row['species']['id']] = $row;
				}
			}
		}

		if(count($rows) < count($speciesIds)) {

			$outer['#toolbox-composition-grid'] = $this->getGrid($eCompositionPlan, $eCompositionFormula);

			return [
				'outer' => $outer,
				'inner' => [],
				'attributes' => [],
			];

		}

		$inner = [];
		$attributes = [];

		foreach($rows as $speciesId => $row) {

			$selector = '#toolbox-composition-grid-species-'.$speciesId;

			$inner[$selector.' > td.toolbox-composition-grid-name'] = $this->getGridName($eCompositionPlan, $row);
			$inner[$selector.' > td.toolbox-composition-grid-sum'] = $this->getGridRowSumContent($row);
			$attributes += $this->getGridCellChanges($eCompositionPlan, $row, [$week]);

		}

		$inner += $this->getGridSumChanges($eCompositionPlan, array_keys($rows));
		$inner['#toolbox-composition-grid-prices'] = $this->getGridPricesContent($eCompositionPlan, $eCompositionFormula);

		return [
			'outer' => $outer,
			'inner' => $inner,
			'attributes' => $attributes,
		];

	}

	/**
	 * Les cases d'un rang qu'un geste redessine aux semaines `$weeks`, par leurs attributs
	 * (`getGridCellAttributes()`). Un attribut `NULL` se retire de la case. Rend
	 * `[sélecteur => [attribut => valeur]]`.
	 */
	protected function getGridCellChanges(CompositionPlan $eCompositionPlan, array $row, array $weeks): array {

		$attributes = [];

		foreach($weeks as $week) {
			$attributes['#toolbox-composition-grid-species-'.$row['species']['id'].' > td[data-week="'.$week.'"]'] = $this->getGridCellAttributes($eCompositionPlan, $row, $week);
		}

		return $attributes;

	}

	/**
	 * L'organe d'un groupe de la grille dans un identifiant, `other` pour les espèces dont l'organe
	 * n'est pas qualifié.
	 */
	protected static function getOrganKey(?string $organ): string {

		return match($organ) {
			\plant\Plant::LEAF => 'leaf',
			\plant\Plant::ROOT => 'root',
			\plant\Plant::FRUIT => 'fruit',
			NULL => 'other',
		};

	}

	/**
	 * La carte d'une semaine, sous la grille (planche r5, `getWeekCards()`) : sa tête, sa valeur, le
	 * prix visé quand elle s'en écarte et le badge « corrigée » (Q14), puis ce que le panier contient,
	 * une ligne par espèce avec sa quantité, qui se règle en place (`getWeekQuantity()`), et ce
	 * qu'elle vaut, dans l'ordre de la grille (`CompositionWeekLib::sortByGrid()`).
	 *
	 * Une semaine corrigée porte en bas sa porte « Revenir à la proposition » (lot r24), pour qui peut
	 * écrire le plan : elle était en tête de la colonne ouverte de la grille (lots r8 et r8b), partie
	 * avec elle, et c'est le seul endroit où le geste se fait.
	 *
	 * Une semaine qui a un panier porte aussi « Utiliser dans un produit » (lots r37b et r45,
	 * `CompositionSaleUi::getDoor()`), pour qui peut écrire le plan et composer une vente
	 * (`CompositionPlan::assertSell()`) : un observateur ne la voit pas.
	 *
	 * Une semaine de soudure (lot r22, point 4) prend l'accent orange et, sous sa tête, sa bande
	 * (`getWeekCardLean()`).
	 */
	public function getWeekCard(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, int $week): string {

		$eCompositionWeek = $eCompositionFormula['cCompositionWeek']->find(fn(CompositionWeek $eCompositionWeek) => $eCompositionWeek['week'] === $week, limit: 1, default: new CompositionWeek());

		$h = '<div class="'.self::getWeekCardClass($eCompositionFormula, $week).'" id="toolbox-composition-card-'.$week.'">';

			$h .= '<div class="util-deck-head">';

				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">';
						$h .= s("Semaine {value}", ['value' => $week]);
						if($eCompositionWeek->notEmpty() and $eCompositionWeek['amended'] and $eCompositionWeek['eCompositionFormulaLeft']->empty()) {
							$h .= ' <span class="badge badge-info">'.s("corrigée").'</span>';
						}
					$h .= '</div>';
					$h .= '<p>'.\util\DateUi::weekToDays(self::getIsoWeek($eCompositionPlan['season'], $week), withYear: FALSE).'</p>';
					if($eCompositionWeek->notEmpty() and $eCompositionWeek['eCompositionFormulaLeft']->notEmpty()) {
						$h .= '<p class="util-annotation">'.s("Corrigée, ne suit plus {name}", ['name' => encode($eCompositionWeek['eCompositionFormulaLeft']['name'])]).'</p>';
					}
				$h .= '</div>';

				if($eCompositionWeek->notEmpty()) {

					$h .= '<div class="util-deck-aside">';
						$h .= '<b class="util-deck-value">'.\util\TextUi::money($eCompositionWeek['value'], precision: 2).'</b>';
						if($eCompositionWeek['gap']) {
							$h .= '<div class="util-annotation">'.s("{price} visé", ['price' => \util\TextUi::money((float)$eCompositionFormula['price'], precision: 0)]).'</div>';
						}
					$h .= '</div>';

				}

			$h .= '</div>';

			$h .= $this->getWeekCardLean($eCompositionPlan, $eCompositionFormula, $week);

			if($eCompositionWeek->notEmpty() and $eCompositionWeek['cCompositionLine']->notEmpty()) {

				$h .= '<div class="util-deck-content">';
					$h .= '<div class="util-deck-bleed">';
						$h .= $this->getWeekLines($eCompositionPlan, $eCompositionWeek);
					$h .= '</div>';
				$h .= '</div>';

			}

			$doors = [];

			if($eCompositionPlan->assertUpdate() and $eCompositionWeek->notEmpty() and $eCompositionWeek['amended']) {

				$posts = [
					'structure' => $eCompositionPlan['structure']['id'],
					'formula' => $eCompositionFormula['id'],
					'week' => $week,
				];

				$doors[] = '<a data-ajax="/toolbox/compositionWeek:doReset" '.self::getPosts($posts).' class="btn btn-light">'.\Asset::icon('arrow-counterclockwise').' '.s("Revenir à la proposition").'</a>';

			}

			$sell = (
				$eCompositionWeek->notEmpty() and
				$eCompositionWeek['cCompositionLine']->notEmpty() and
				$eCompositionPlan->assertSell()
			);

			if($sell) {
				$doors[] = new CompositionSaleUi()->getDoor($eCompositionPlan, $eCompositionFormula, $eCompositionWeek);
			}

			// Deux pistes dès que « Utiliser dans un produit » est là, même seule : la liste de son menu, cachée,
			// la suit dans la bande, et `util-deck-band-doors-1` étire sur toute la largeur une porte qui
			// n'est pas seule enfant. Sur deux pistes, une porte seule tient la moitié, comme sur une.
			if($doors !== []) {
				$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-'.($sell ? 2 : 1).'">'.implode('', $doors).'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	protected static function getWeekCardClass(CompositionFormula $eCompositionFormula, int $week): string {
		return array_key_exists($week, $eCompositionFormula['lean']['weeks']) ? 'util-deck util-deck-accent-warning' : 'util-deck';
	}

	/**
	 * La bande de soudure d'une carte (planche r22, question 4, option A) : ce que le plan de culture
	 * donne encore cette semaine (`CompositionLib::getLeanWeeks()`), puis les gestes de sa période,
	 * portés à la semaine seule (`getLeanDoors()`). Hors soudure, le conteneur reste, vide, pour
	 * qu'une réponse ciblée le remplisse (`getLeanChanges()`) ; il prend alors les coins du bas
	 * quand il ferme la carte (`composition.css`).
	 */
	protected function getWeekCardLean(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, int $week): string {

		$h = '<div class="toolbox-composition-card-lean" id="toolbox-composition-card-lean-'.$week.'">';

			if(array_key_exists($week, $eCompositionFormula['lean']['weeks'])) {

				['cCompositionSpecies' => $cCompositionSpecies, 'period' => $position] = $eCompositionFormula['lean']['weeks'][$week];
				$period = $eCompositionFormula['lean']['periods'][$position];

				$h .= '<div class="util-deck-band util-deck-band-state">'.self::getLeanCause($cCompositionSpecies).'</div>';
				$h .= $this->getLeanDoors($eCompositionPlan, $eCompositionFormula, $period, 'btn-light', 'util-deck-band util-deck-band-doors '.(($period['stretch'] === []) ? 'util-deck-band-doors-1' : 'util-deck-band-doors-2'), $week);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que le plan de culture donne une semaine de soudure : les espèces que son stock porte encore,
	 * que le panier sert toutes, ou rien.
	 */
	protected static function getLeanCause(\Collection $cCompositionSpecies): string {

		$names = $cCompositionSpecies->makeArray(fn(CompositionSpecies $eCompositionSpecies) => encode($eCompositionSpecies->getLabel()));

		return match(count($names)) {
			0 => s("Aucune récolte ni réserve au plan de culture"),
			1 => s("{plant}, seule espèce au plan de culture", ['plant' => reset($names)]),
			default => s("{plants}, seules espèces au plan de culture", ['plants' => CompositionFormulaUi::getEnumeration(array_values($names))]),
		};

	}

	/**
	 * Ce que le panier d'une semaine contient, une ligne par espèce avec sa quantité, réglable en
	 * place, et ce qu'elle vaut.
	 *
	 * Dans une taille dérivée, une semaine qui suit sa base (lot r17, option B) range d'abord les
	 * espèces reprises de la base, puis sous « En plus de <base> » celles qu'elle ajoute
	 * (`CompositionWeekLib::applyBase()`). La grille, elle, ne distingue rien.
	 */
	protected function getWeekLines(CompositionPlan $eCompositionPlan, CompositionWeek $eCompositionWeek): string {

		$eCompositionFormulaFollowed = $eCompositionWeek['eCompositionFormulaFollowed'];
		$separated = $eCompositionFormulaFollowed->empty();

		$h = '<table class="tr-even toolbox-composition-week-lines">';
			$h .= '<tbody>';

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

					if($separated === FALSE and $eCompositionLine['inherited'] === FALSE) {
						$h .= '<tr class="toolbox-composition-week-inherited"><td colspan="3"><span class="util-label">'.s("En plus de {name}", ['name' => encode($eCompositionFormulaFollowed['name'])]).'</span></td></tr>';
						$separated = TRUE;
					}

					$h .= '<tr>';
						$h .= '<td>';
							$h .= '<div class="util-vignette">';
								$h .= \plant\PlantUi::getVignette($eCompositionLine['species']['plant'], '1.25rem');
								$h .= '<span>'.encode($eCompositionLine['species']->getLabel()).'</span>';
							$h .= '</div>';
						$h .= '</td>';
						$h .= '<td class="text-end">'.$this->getWeekQuantity($eCompositionPlan, $eCompositionLine, $eCompositionWeek['week'], 'card').'</td>';
						$h .= '<td class="text-end">'.(($eCompositionLine['value'] === NULL) ? '' : \util\TextUi::money($eCompositionLine['value'], precision: 2)).'</td>';
					$h .= '</tr>';

				}

			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	/**
	 * Les `post-*` d'un geste en `data-ajax`.
	 */
	public static function getPosts(array $posts): string {
		return implode(' ', array_map(fn(string $name) => attr('post-'.$name, $posts[$name]), array_keys($posts)));
	}

	public static function getOrganName(?string $organ): string {

		return match($organ) {
			\plant\Plant::LEAF => s("Feuille"),
			\plant\Plant::ROOT => s("Racine"),
			\plant\Plant::FRUIT => s("Fruit"),
			NULL => s("Autres"),
		};

	}

	/**
	 * Une quantité et l'abréviation de son unité de référence (planche r2, point 5), celle que la
	 * grille écrit au bout du prix d'une espèce : « 0,8 kg », « 2 bte ». `UnitUi::getValue()` écrit le
	 * nombre sans le formater, avec un point décimal.
	 */
	public static function getQuantity(float $quantity, \product\Unit $eUnit): string {
		return \util\TextUi::nonBreaking(\util\TextUi::number($quantity, NULL).' '.encode($eUnit['short']));
	}

	public static function getIsoWeek(int $season, int $week): string {
		return $season.'-W'.sprintf('%02d', $week);
	}

	public static function p(string $property): \PropertyDescriber {

		$d = CompositionPlan::model()->describer($property, [
			'reference' => s("Semaines que suivent les paniers"),
			'climate' => \structure\SystemUi::p('climate')->label,
		]);

		switch($property) {

			case 'reference' :
				$d->values = [
					CompositionPlan::CULTIVATION => s("Mes semaines de récolte"),
					CompositionPlan::CALENDAR => s("Semaines du calendrier type"),
					CompositionPlan::COMMUNITY => s("Semaines de la communauté"),
				];
				break;

			// Le climat du calendrier type est celui que la structure déclare dans son système
			// (`structure\System.climate`) : mêmes valeurs, mêmes mots.
			case 'climate' :
				$d->values = \structure\SystemUi::p('climate')->values;
				break;

		}

		return $d;

	}

}
?>
