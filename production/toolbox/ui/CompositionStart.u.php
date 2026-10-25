<?php
namespace toolbox;

/**
 * Le départ des paniers de l'année (lot J, planche v12) : trois questions, les tailles de panier,
 * les semaines livrées et les cultures, posées dans le composant d'accueil du design system
 * (`main/onboarding.css`), avec l'aperçu à droite. Rien ne s'écrit avant « Composer mes paniers » :
 * un seul formulaire, envoyé à la fin (`composition:doStart`).
 *
 * Le même écran sert la roue crantée, « Recommencer les paniers » ou le choix d'un autre plan de
 * culture, les réponses du plan déjà posées (`CompositionPlanLib::getForStart()`, `restart`). Seul
 * l'en-tête change : il dit que les paniers ne bougent pas tant qu'on n'a pas composé, et ramène à
 * eux. La saison postée est celle du plan de culture que les paniers suivront.
 *
 * La troisième question dit aussi d'où partent les saisons (r48b) : le calendrier type d'un climat
 * ou le plan de culture (`reference`, `climate`).
 *
 * L'aperçu se dessine dans le navigateur (`ToolboxCompositionStart`, `compositionStart.js`) à partir
 * de ce que les champs portent : les nombres qu'il écrit sont ceux qu'on tape, rien n'y est calculé
 * côté serveur. Les phrases lui arrivent traduites dans `data-texts`, comme au plan de la ferme.
 */
class CompositionStartUi {

	public function __construct() {

		\Asset::css('main', 'onboarding.css');
		\Asset::js('main', 'onboarding.js');
		\Asset::css('toolbox', 'composition.css');
		\Asset::js('toolbox', 'composition.js');
		\Asset::js('toolbox', 'compositionStart.js');

	}

	public function getStart(CompositionPlan $eCompositionPlan): string {

		$form = new \util\FormUi(['columns' => 1]);

		$step = $eCompositionPlan['seasonAsked'] ? 3 : 1;

		$h = '<div id="toolbox-composition-start" class="onboarding toolbox-composition-start" data-step="'.$step.'" onrender="ToolboxCompositionStart.start(this)">';

			$h .= $this->getHeader($eCompositionPlan);

			// `novalidate` : le navigateur refuse d'envoyer un champ hors de ses bornes (`min` d'un nombre
			// de paniers) et ne peut pas afficher sa bulle sur une étape masquée, donc l'envoi ne partait
			// pas, sans rien dire. Le refus revient du serveur, sur son champ et dans son étape.
			$h .= $form->openAjax('/toolbox/composition:doStart', ['id' => 'toolbox-composition-start-form', 'autocomplete' => 'off', 'novalidate']);

				$h .= $form->hidden('id', $eCompositionPlan['structure']['id']);
				$h .= $form->hidden('season', $eCompositionPlan['season']);

				$h .= '<div class="util-card onboarding-panel">';

					$h .= '<div class="onboarding-steps">';
						$h .= $this->getProgress();
						$h .= $this->getStepFormulas($form, $eCompositionPlan);
						$h .= $this->getStepWeeks($form, $eCompositionPlan);
						$h .= $this->getStepSpecies($form, $eCompositionPlan);
					$h .= '</div>';

					$h .= $this->getPreview($eCompositionPlan);

				$h .= '</div>';

			$h .= $form->close();

			$eCompositionFormula = new CompositionFormula([
				'name' => NULL,
				'price' => NULL,
				'baskets' => NULL,
			]);

			$h .= '<template id="toolbox-composition-start-template">';
				$h .= $this->getFormula($form, $eCompositionFormula, '__i__');
			$h .= '</template>';

			// Le rang qu'ajoute le panneau « Ajouter une espèce » arrive ici, hors du formulaire, avant
			// d'être rangé dans la liste (`ToolboxCompositionStart.insert()`).
			$h .= '<div id="toolbox-composition-start-added" hidden></div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Au-dessus des questions. Un premier départ porte un `util-welcome` qui dit ce que l'outil est et
	 * à qui il sert (r56, texte du 02/10/2026). Il n'a ni titre ni mascotte : le titre de
	 * la page et le nom des étapes disent déjà où l'on est, et la mascotte est celle de l'accueil de
	 * l'outil, juste avant. Le `mb-0` laisse l'écart à `.onboarding`, qui espace ses enfants.
	 *
	 * Un nouveau départ garde l'en-tête du composant d'accueil, qui dit que les paniers ne bougent
	 * pas tant qu'on n'a pas composé et ramène à eux.
	 */
	protected function getHeader(CompositionPlan $eCompositionPlan): string {

		if($eCompositionPlan['restart'] === FALSE) {

			return \main\DesignUi::getWelcome('', '<p>'.s("Cet outil vous aide à décider, il ne remplace pas une étude de terrain. Il est recommandé quand vous vous installez ou que vous débutez votre activité.").'</p>', '', class: 'mb-0');

		}

		$text = $eCompositionPlan['followed'] ?
			s("Vos réponses sont déjà posées. Vos paniers ne changent qu'au moment où vous les composez.") :
			s("Vos paniers suivront le plan de culture {season}, avec vos réponses déjà posées. Ils ne changent qu'au moment où vous les composez.", ['season' => $eCompositionPlan['season']]);

		$h = '<div class="onboarding-header">';
			$h .= '<p class="onboarding-header-text">'.$text.'</p>';
			$h .= '<a href="'.CompositionUi::url($eCompositionPlan['structure']).'" class="onboarding-header-skip">'.s("Revenir à mes paniers").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La barre et le nom des étapes, qui sont aussi la façon d'y revenir : le parcours n'a pas de
	 * bouton Retour (`main/onboarding.js`), qui pose aussi le gras du nom de l'étape courante
	 * (`is-current`).
	 */
	protected function getProgress(): string {

		$names = [
			1 => s("Vos paniers"),
			2 => s("Vos semaines"),
			3 => s("Vos cultures"),
		];

		$h = '<div class="onboarding-progress">';

			$h .= '<div class="onboarding-stepper">';
				foreach($names as $step => $name) {
					$h .= '<span data-step="'.$step.'"></span>';
				}
			$h .= '</div>';

			$h .= '<div class="onboarding-progress-label onboarding-progress-names">';
				foreach($names as $step => $name) {
					$h .= '<span data-onboarding-name="'.$step.'">'.$name.'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getStepFormulas(\util\FormUi $form, CompositionPlan $eCompositionPlan): string {

		$h = '<section class="onboarding-step" data-step="1">';
			$h .= '<h2>'.s("Quels paniers vendez-vous ?").'</h2>';

			$h .= '<div class="toolbox-composition-start-formulas" id="toolbox-composition-start-formulas">';

				$index = 0;

				foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {
					$h .= $this->getFormula($form, $eCompositionFormula, (string)++$index);
				}

			$h .= '</div>';

			$h .= '<a class="btn btn-outline-primary toolbox-composition-start-add" data-action="toolbox-composition-start-add">'.\Asset::icon('plus-lg').' '.s("Ajouter un panier").'</a>';

			$h .= $this->getFooter($form, next: TRUE);
		$h .= '</section>';

		return $h;

	}

	/**
	 * Une taille de panier, sous les noms de propriété que la page construit (`buildIndex()`,
	 * `name[<index>]`) : une erreur retombe ainsi sur son champ, dans sa rangée.
	 *
	 * La croix de la seule taille restante est masquée par la feuille (`:only-child`) : un départ sans
	 * taille ne composerait rien.
	 *
	 * Le prix part vide, sans rien dessous : c'est celui de la structure, et une médiane nationale
	 * posée d'office se validait sans être lue (revue v12).
	 */
	protected function getFormula(\util\FormUi $form, CompositionFormula $eCompositionFormula, string $index): string {

		$h = '<div class="util-card onboarding-row toolbox-composition-start-formula" data-index="'.$index.'">';

			$h .= '<div class="onboarding-row-main">';
				$h .= '<div class="onboarding-field onboarding-field-name" data-wrapper="name['.$index.']">';
					$h .= $form->dynamicField($eCompositionFormula, 'name', function(\PropertyDescriber $d) use($index) {
						$d->name = 'name['.$index.']';
					});
				$h .= '</div>';
				$h .= '<a class="btn btn-only-icon btn-sm btn-outline-danger" data-onboarding-remove-row title="'.s("Retirer ce panier").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';

			$h .= '<div class="onboarding-row-details">';

				$h .= '<div class="onboarding-field" data-wrapper="price['.$index.']">';
					$h .= '<label class="onboarding-field-label">'.s("Prix du panier").'</label>';
					$h .= $form->dynamicField($eCompositionFormula, 'price', function(\PropertyDescriber $d) use($index) {
						$d->name = 'price['.$index.']';
					});
				$h .= '</div>';

				$h .= '<div class="onboarding-field" data-wrapper="baskets['.$index.']">';
					$h .= '<label class="onboarding-field-label">'.s("Paniers vendus par semaine").'</label>';
					$h .= $form->dynamicField($eCompositionFormula, 'baskets', function(\PropertyDescriber $d) use($index) {
						$d->name = 'baskets['.$index.']';
					});
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les semaines, cochées en glissant sur la période ou d'un clic pour un trou
	 * (`CompositionUi::getWeeksDates()`). Aucune n'est cochée sur un plan neuf : ce sont les
	 * semaines de la structure, que rien ne permet de deviner.
	 */
	protected function getStepWeeks(\util\FormUi $form, CompositionPlan $eCompositionPlan): string {

		$h = '<section class="onboarding-step" data-step="2">';
			$h .= '<h2>'.s("Quelles semaines livrez-vous ?").'</h2>';
			$h .= '<p class="onboarding-help" id="toolbox-composition-start-weeks"></p>';
			$h .= $form->group(content: CompositionUi::getWeeksDates($form, $eCompositionPlan['season'], $eCompositionPlan['seasonMonths'], $eCompositionPlan['weeks']), attributes: ['data-wrapper' => 'weeks']);
			$h .= $this->getFooter($form, next: TRUE);
		$h .= '</section>';

		return $h;

	}

	/**
	 * D'où partent les saisons, puis les espèces livrées (r48b, planche r48, pistes 1 A et 2 B).
	 *
	 * Deux tuiles en tête, le calendrier type et le plan de culture, chacune avec ce qu'elle règle :
	 * le climat sous la première, la saison du plan de culture sous la seconde. Une structure sans
	 * plan de culture n'a rien à choisir : il reste le climat (`getReference()`).
	 *
	 * La liste ne porte que ce qui est livré : le nom, la saison en douze barres, qui ouvre le menu des
	 * mois, et la corbeille (`getSpeciesRow()`). Toutes les espèces proposables y sont pourtant
	 * écrites, les retirées masquées (`is-off`), parce que le menu des saisons peut les remettre et que
	 * chacune poste son statut (`status[<espèce>]`). La corbeille ne fait que passer ce champ à
	 * « retirée ».
	 *
	 * « Ajouter une espèce » (r48e, planche r48, verdict 2 B), à droite du titre, ouvre le même panneau
	 * que l'onglet « Espèces » (`composition:addStartSpecies`, `CompositionSpeciesUi::addStart()`),
	 * par où toute espèce de la structure entre dans la liste, celle que la corbeille a retirée
	 * comprise. Son lien porte ce que le formulaire montre au moment du clic, que
	 * `ToolboxCompositionStart.renderSpecies()` lui écrit. Rien ne s'écrit avant l'envoi du départ :
	 * le rang ajouté arrive dans `toolbox-composition-start-added`, hors du formulaire, d'où
	 * `ToolboxCompositionStart.insert()` le range à sa place dans la liste.
	 *
	 * Chaque rang porte la saison que chaque source lui donnerait (`seasons`,
	 * `CompositionPlanLib::getForStart()`), et n'en montre qu'une, celle du choix en cours : changer
	 * de tuile ou de climat redessine la liste dans le navigateur
	 * (`ToolboxCompositionStart.renderSpecies()`), sans aller-retour, et sans perdre ce qui est tapé
	 * aux deux premières étapes. L'ordre des sources voyage dans `data-sources`.
	 *
	 * La saison ne se règle pas dans la liste (r57) : une note sous le titre dit qu'elle s'ajuste une
	 * fois les paniers composés (`getSeason()`). Elle reste vraie pour l'espèce ajoutée que rien ne
	 * date, dont le panneau a demandé la saison (r67) : celle-ci s'ajuste ensuite comme les autres.
	 *
	 * Sous le calendrier, tant que le climat n'est pas choisi, il n'y a aucune saison à montrer : ni
	 * liste, ni note, ni « Composer mes paniers » (`is-pending`).
	 */
	protected function getStepSpecies(\util\FormUi $form, CompositionPlan $eCompositionPlan): string {

		$cCompositionSpecies = $eCompositionPlan['cCompositionSpecies'];

		$delivered = $cCompositionSpecies->find(fn(CompositionSpecies $eCompositionSpecies) => $eCompositionSpecies['status'] !== CompositionSpecies::EXCLUDED)->count();

		$url = '/toolbox/composition:addStartSpecies?id='.$eCompositionPlan['structure']['id'];

		$h = '<section class="onboarding-step toolbox-composition-start-cultures'.(in_array(NULL, $this->getSourcesShown($eCompositionPlan), TRUE) ? ' is-pending' : '').'" data-step="3" data-sources="'.encode(json_encode($eCompositionPlan['sources'])).'">';
			$h .= '<h2>'.s("Que cultivez-vous ?").'</h2>';

			$h .= $this->getReference($form, $eCompositionPlan);

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Espèces livrées dans les paniers").' <span class="util-counter" id="toolbox-composition-start-count">'.$delivered.'</span></h3>';
				$h .= '<a href="'.$url.'" data-url="'.$url.'" class="btn btn-sm btn-outline-primary" id="toolbox-composition-start-species">'.\Asset::icon('plus-circle').' '.s("Ajouter une espèce").'</a>';
			$h .= '</div>';

			$h .= '<div class="util-info">'.s("Vous pourrez ajuster la saison de chaque espèce à l'étape suivante.").'</div>';

			$h .= '<div class="toolbox-composition-species-list toolbox-composition-calendar-list" data-status-on="'.CompositionSpecies::NATURAL.'" data-status-off="'.CompositionSpecies::EXCLUDED.'">';

				// Le rang de l'axe : la corbeille y garde sa colonne sans rien y montrer, pour que les
				// initiales des mois tombent sous les barres des rangs. Chaque rang est sa propre grille.
				$h .= '<div class="toolbox-composition-species toolbox-composition-calendar-head">';
					$h .= '<div></div>';
					$h .= '<div><span class="toolbox-composition-calendar">'.CompositionUi::getMonthsAxis('toolbox-composition-calendar-axis').'</span></div>';
					$h .= '<span class="btn btn-sm btn-outline-primary">'.\Asset::icon('trash').'</span>';
				$h .= '</div>';

				foreach($cCompositionSpecies as $eCompositionSpecies) {
					$h .= $this->getSpeciesRow($form, $eCompositionPlan, $eCompositionSpecies);
				}

			$h .= '</div>';

			$h .= $this->getFooter($form, next: FALSE);
		$h .= '</section>';

		return $h;

	}

	/**
	 * Le rang d'une espèce du départ : son nom, sa saison et la corbeille, avec ce qu'il poste. Le
	 * statut toujours (`status[<espèce>]`) ; pour une espèce ajoutée par le panneau (r48e), l'espèce de
	 * la structure qui a été choisie (`added[]`), que l'envoi relit, quand la communauté ne la compte
	 * pas (`typed`), son unité, sa quantité au panier et son prix, et quand aucune source ne la date,
	 * la saison tracée aux poignées du panneau (r67, `monthsTraced`), sous son index.
	 *
	 * `data-name` est le nom par lequel `ToolboxCompositionStart.insert()` range un rang ajouté.
	 */
	public function getSpeciesRow(\util\FormUi $form, CompositionPlan $eCompositionPlan, CompositionSpecies $eCompositionSpecies): string {

		$plantId = $eCompositionSpecies['plant']['id'];
		$name = encode($eCompositionSpecies['plant']['name']);

		$off = ($eCompositionSpecies['status'] === CompositionSpecies::EXCLUDED);

		$h = '<div class="toolbox-composition-species toolbox-composition-species-start'.($off ? ' is-off' : '').'" data-plant="'.$plantId.'" data-name="'.$name.'">';

			$h .= '<div class="toolbox-composition-species-name util-vignette">';
				$h .= \plant\PlantUi::getVignette($eCompositionSpecies['plant'], '1.375rem');
				$h .= '<span>'.$name.'</span>';
			$h .= '</div>';

			$h .= '<div>'.$this->getSeason($eCompositionSpecies, $this->getSourcesShown($eCompositionPlan)).'</div>';

			$h .= '<a class="btn btn-only-icon btn-sm btn-outline-primary toolbox-composition-calendar-remove" data-action="toolbox-composition-start-remove" title="'.s("Retirer {plant}", ['plant' => $name]).'">'.\Asset::icon('trash').'</a>';

			$h .= $form->hidden('status['.$plantId.']', $off ? CompositionSpecies::EXCLUDED : CompositionSpecies::NATURAL);

			if($eCompositionSpecies['ePlantStructure']->notEmpty()) {
				$h .= $form->hidden('added[]', $eCompositionSpecies['ePlantStructure']);
			}

			if($eCompositionSpecies['typed']) {
				$h .= $form->hidden('unit['.$plantId.']', $eCompositionSpecies['unit']);
				$h .= $form->hidden('quantityBasket['.$plantId.']', $eCompositionSpecies['quantityBasket']);
				$h .= $form->hidden('price['.$plantId.']', $eCompositionSpecies['price']);
			}

			if($eCompositionSpecies['monthsTraced'] !== NULL) {

				foreach($eCompositionSpecies['windowWeeks'] as $position => [$start, $stop]) {
					$h .= $form->hidden('windowWeeks['.$plantId.']['.$position.'][0]', $start);
					$h .= $form->hidden('windowWeeks['.$plantId.']['.$position.'][1]', $stop);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les sources du choix posé à l'arrivée, dans l'ordre où une espèce y prend sa saison
	 * (`sources`, `CompositionPlanLib::getStartSources()`), le climat du plan à la place de celui que
	 * seul le champ connait. `NULL` y reste quand le calendrier attend son climat.
	 */
	protected function getSourcesShown(CompositionPlan $eCompositionPlan): array {

		return array_map(fn(?string $source) => $source ?? $eCompositionPlan['climate'], $eCompositionPlan['sources'][$eCompositionPlan['reference']]);

	}

	/**
	 * La saison d'un rang, en lecture seule : au départ, elle ne se règle pas (r57, le
	 * 03/10/2026, « vire la personnalisation ici »). Elle se règle une fois les paniers composés, sur
	 * la grille des paniers (« Régler la saisonnalité », `ToolboxCompositionSeason`), ce que la note
	 * sous le titre de la liste annonce (`getStepSpecies()`).
	 *
	 * Le dessin porte une saison par source : deux sources qui donnent la même partagent leurs barres
	 * (`data-source` les nomme toutes), ce que font les quatre climats d'une espèce trop peu cultivée
	 * pour que le calendrier les distingue. Un seul est montré, celui de la première source de
	 * `$sources` qui date l'espèce, et changer de tuile ou de climat en montre un autre
	 * (`ToolboxCompositionStart.renderSpecies()`). Le dernier dessin, sans source, est celui d'une
	 * espèce qu'aucune ne date : douze barres vides.
	 *
	 * Une espèce ajoutée qu'aucune source ne datait a reçu sa saison des poignées du panneau (r67,
	 * `monthsTraced`) : elle n'a que ce dessin, sans source, qui se montre donc sous tous les choix,
	 * la saison tracée partant telle quelle (`composition:doStart`).
	 */
	protected function getSeason(CompositionSpecies $eCompositionSpecies, array $sources): string {

		if($eCompositionSpecies['monthsTraced'] !== NULL) {
			return '<span class="toolbox-composition-calendar toolbox-composition-start-season"><span class="toolbox-composition-calendar-source" data-source="">'.CompositionUi::getSeasonBars($eCompositionSpecies['monthsTraced']).'</span></span>';
		}

		$drawings = [];

		foreach($eCompositionSpecies['seasons'] as $source => $months) {
			$drawings[CompositionUi::getSeasonBars($months)][] = $source;
		}

		$shown = array_find($sources, fn(?string $source) => ($source !== NULL and array_key_exists($source, $eCompositionSpecies['seasons'])));

		$h = '<span class="toolbox-composition-calendar toolbox-composition-start-season">';

			foreach($drawings as $bars => $drawingSources) {
				$h .= '<span class="toolbox-composition-calendar-source'.(in_array($shown, $drawingSources, TRUE) ? '' : ' hide').'" data-source="'.implode(' ', $drawingSources).'">'.$bars.'</span>';
			}

			$h .= '<span class="toolbox-composition-calendar-source'.(($shown === NULL) ? '' : ' hide').'" data-source="">'.CompositionUi::getSeasonBars($eCompositionSpecies['plan']['seasonEmpty']).'</span>';

		$h .= '</span>';

		return $h;

	}

	/**
	 * Les deux tuiles (`onboarding-tiles`) quand la structure a un plan de culture, et sous chacune le
	 * champ qu'elle règle, que la feuille du composant ne montre que sous la tuile cochée. Le climat
	 * part avec le formulaire quelle que soit la tuile : `CompositionPlan::properties()` ne l'écrit que
	 * sous le calendrier.
	 *
	 * Sans plan de culture, il n'y a pas de choix, donc pas de tuile (planche r48, bancs sans plan de
	 * culture) : le climat se demande seul, et le calendrier part dans un champ caché.
	 */
	protected function getReference(\util\FormUi $form, CompositionPlan $eCompositionPlan): string {

		$climate = '<div class="onboarding-field toolbox-composition-start-climate" data-wrapper="climate">';
			$climate .= '<label class="onboarding-field-label" for="toolbox-composition-start-climate">'.CompositionUi::p('climate')->label.'</label>';
			$climate .= $form->select('climate', CompositionUi::p('climate')->values, $eCompositionPlan['climate'], [
				'id' => 'toolbox-composition-start-climate',
				'data-action' => 'toolbox-composition-start-climate',
			]);
		$climate .= '</div>';

		if($eCompositionPlan['tiles'] === FALSE) {
			return $form->hidden('reference', CompositionPlan::CALENDAR).$climate;
		}

		$tiles = [
			CompositionPlan::CALENDAR => [
				'title' => s("Calendrier type"),
				'text' => s("Les saisons habituelles de chaque espèce sous votre climat."),
				'fields' => $climate,
			],
			CompositionPlan::CULTIVATION => [
				'title' => s("Mon plan de culture"),
				'text' => s("Les espèces et les récoltes prévues de votre plan de culture."),
				'fields' => $this->getCultivatedSeasons($form, $eCompositionPlan),
			],
		];

		$references = array_combine(array_keys($tiles), array_keys($tiles));

		$h = '<div class="onboarding-tiles" data-wrapper="reference">';
			$h .= $form->radios('reference', $references, $eCompositionPlan['reference'], [
				'required' => TRUE,
				'callbackRadioContent' => function(string $reference) use($tiles) {

					$h = '<div class="onboarding-tile">';
						$h .= '<div class="onboarding-tile-text">';
							$h .= '<div class="onboarding-tile-title">'.$tiles[$reference]['title'].'</div>';
							$h .= '<div class="onboarding-tile-desc">'.$tiles[$reference]['text'].'</div>';
							$h .= '<div class="onboarding-tile-fields">'.$tiles[$reference]['fields'].'</div>';
						$h .= '</div>';
					$h .= '</div>';

					return $h;

				},
			]);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le menu des plans de culture, sous sa tuile : la saison que les paniers suivront.
	 *
	 * Le menu n'est pas envoyé (`name` nul) : la saison du plan est le champ `season`, que le départ
	 * écrit pour les espèces qu'il affiche. En recommençant, une autre saison rouvre donc le départ
	 * sur elle (`data-url`), avec les réponses du plan, et ce sont ses paniers qui la suivront. Au
	 * premier départ, rien n'est encore écrit et les réponses tapées se perdraient : le menu ne fait
	 * que choisir les espèces livrées, celles de la saison, ou celles des ventes (`data-sold`) quand
	 * elle est vide.
	 */
	protected function getCultivatedSeasons(\util\FormUi $form, CompositionPlan $eCompositionPlan): string {

		$values = [];

		foreach($eCompositionPlan['cultivatedSeasons'] as $season => $plantIds) {
			$values[] = [
				'value' => $season,
				'label' => s("Saison {year}", ['year' => $season]),
				'attributes' => [
					'data-plants' => implode(' ', $plantIds),
					'data-url' => $eCompositionPlan['restart'] ? CompositionUi::urlStart($eCompositionPlan['structure'], $season) : NULL,
				],
			];
		}

		$h = '<div class="onboarding-field toolbox-composition-start-cultivation">';
			$h .= $form->select(NULL, $values, $eCompositionPlan['season'], [
				'id' => 'toolbox-composition-start-cultivation',
				'required' => TRUE,
				'data-action' => 'toolbox-composition-start-cultivation',
				'data-sold' => implode(' ', $eCompositionPlan['sold']),
			]);
		$h .= '</div>';

		return $h;

	}

	protected function getFooter(\util\FormUi $form, bool $next): string {

		$h = '<div class="onboarding-footer">';
			$h .= '<div class="onboarding-footer-main">';
				if($next) {
					$h .= '<a class="btn btn-primary" data-onboarding-next>'.s("Continuer").' '.\Asset::icon('arrow-right').'</a>';
				} else {
					$h .= $form->submit(s("Composer mes paniers"), ['class' => 'btn btn-primary', 'data-waiter' => s("Composition en cours")]);
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'aperçu : une carte par question déjà posée, la frise de l'année vide que le navigateur
	 * remplit des semaines cochées, et au-dessus le compte des paniers, par semaine tant que les
	 * semaines ne sont pas posées, sur l'année ensuite. Il naît vide : les cartes des paniers et des
	 * semaines ne paraissent qu'avec une réponse, et le titre avec la première carte
	 * (`ToolboxCompositionStart.renderPreview()`).
	 */
	protected function getPreview(CompositionPlan $eCompositionPlan): string {

		$seasonWeeks = $eCompositionPlan['seasonWeeks'];

		$texts = [
			'basketsWeek' => [s("{value} panier par semaine", ['value' => '__value__']), s("{value} paniers par semaine", ['value' => '__value__'])],
			'basketsYear' => [s("{value} panier sur l'année", ['value' => '__value__']), s("{value} paniers sur l'année", ['value' => '__value__'])],
			'weeks' => [s("{value} semaine", ['value' => '__value__']), s("{value} semaines", ['value' => '__value__'])],
			'period' => s("{weeks}, du {from} au {to}", ['weeks' => '__weeks__', 'from' => '__from__', 'to' => '__to__']),
			'noWeek' => s("Aucune semaine livrée"),
			'grown' => s("{value} espèces sur {total}", ['value' => '__value__', 'total' => '__total__']),
			'days' => $this->getDays($eCompositionPlan['season'], $seasonWeeks),
		];

		$h = '<aside class="onboarding-preview">';

			$h .= '<div class="onboarding-preview-header hide" id="toolbox-composition-start-preview-header">';
				$h .= '<span class="onboarding-preview-title">'.s("Aperçu").'</span>';
				$h .= '<span class="onboarding-preview-summary" id="toolbox-composition-start-summary"></span>';
			$h .= '</div>';

			$h .= '<div class="onboarding-preview-body" data-texts="'.encode(json_encode($texts)).'">';

				$h .= '<div class="util-card" id="toolbox-composition-start-preview-formulas-card" hidden>';
					$h .= '<div class="util-label">'.s("Vos paniers").'</div>';
					$h .= '<div id="toolbox-composition-start-preview-formulas"></div>';
				$h .= '</div>';

				$h .= '<div class="util-card" id="toolbox-composition-start-preview-weeks-card" data-preview-step="2" hidden>';
					$h .= '<div class="util-label">'.s("Les semaines livrées").'</div>';
					$h .= new CompositionUi()->getYear([], min($seasonWeeks), max($seasonWeeks));
					$h .= '<p class="util-annotation" id="toolbox-composition-start-preview-weeks"></p>';
				$h .= '</div>';

				$h .= '<div class="util-card" id="toolbox-composition-start-preview-grown-card" data-preview-step="3">';
					$h .= '<div class="util-label">'.s("Ce que je cultive").'</div>';
					$h .= '<p class="util-annotation" id="toolbox-composition-start-preview-grown"></p>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</aside>';

		return $h;

	}

	/**
	 * Le lundi et le dimanche de chaque semaine de la saison, écrits en toutes lettres, pour que la
	 * phrase « du 6 avril au 29 novembre » se compose dans le navigateur sans calendrier.
	 */
	protected function getDays(int $season, array $seasonWeeks): array {

		$days = [];

		foreach($seasonWeeks as $week) {

			$isoWeek = CompositionUi::getIsoWeek($season, $week);

			$days[$week] = [
				\util\DateUi::textual(week_date_starts($isoWeek), \util\DateUi::DAY_MONTH),
				\util\DateUi::textual(week_date_ends($isoWeek), \util\DateUi::DAY_MONTH),
			];

		}

		return $days;

	}

}
?>
