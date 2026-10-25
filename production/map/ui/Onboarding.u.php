<?php
namespace map;

/**
 * Démarrage guidé du plan de la ferme, pour une structure sans parcelle : quatre ou cinq
 * questions à gauche, l'aperçu du plan qui se construit à droite au fil des réponses,
 * et une seule création à la fin (OnboardingLib::create()).
 */
class OnboardingUi {

	public function __construct() {

		// La structure (deux colonnes, étapes, tuiles, aperçu, bandeau d'arrivée) est un composant
		// du design system ; ici seulement ce qui parle de parcelles, de jardins et de planches
		\Asset::css('main', 'onboarding.css');
		\Asset::js('main', 'onboarding.js');
		\Asset::css('map', 'onboarding.css');
		\Asset::js('map', 'onboarding.js');

	}

	public static function url(\structure\Structure $eStructure, ?int $season = NULL): string {

		return \structure\StructureUi::urlStructure($eStructure).'/plan/demarrer'.($season ? '?season='.$season : '');

	}

	public function getTitle(): string {

		return \main\DesignUi::getPageHeader(s("Plan de la ferme"));

	}

	public function getStart(\structure\Structure $eStructure, \structure\System $eSystem, int $season): string {

		if($eStructure->canManage() === FALSE) {
			return $this->getReadOnly($eStructure);
		}

		$form = new \util\FormUi();

		$eZone = new Zone([
			'structure' => $eStructure
		]);

		$h = '<div id="onboarding" class="onboarding" data-mode="'.OnboardingLib::PERMANENT.'" data-step="1" onrender="MapOnboarding.start(this)">';

			$h .= '<div class="onboarding-header">';
				$h .= '<p class="onboarding-header-text">'.s("Nous allons créer le plan de votre ferme en quelques questions.").'</p>';
				$h .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure).'" class="onboarding-header-skip">'.s("Je préfère commencer par une culture, sans plan").'</a>';
			$h .= '</div>';

			$h .= $form->openAjax('/map/onboarding:doCreate', ['id' => 'onboarding-form', 'autocomplete' => 'off']);

				$h .= $form->hidden('id', $eStructure['id']);
				$h .= $form->hidden('season', $season);

				// Sans aperçu à côté des questions, la carte où se dessine la parcelle prend toute la largeur du panneau
				$h .= '<div class="util-card onboarding-panel onboarding-panel-plain">';

					$h .= '<div class="onboarding-steps">';
						$h .= $this->getProgress();
						$h .= $this->getStepPlace($form, $eSystem);
						$h .= $this->getStepMode($form);
						$h .= $this->getStepZone($form, $eZone);
						$h .= $this->getStepPlots($form, $eStructure);
						$h .= $this->getStepBeds($form, $eStructure);
					$h .= '</div>';

				$h .= '</div>';

			$h .= $form->close();

			$h .= $this->getBedTemplate($form, $eStructure);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bandeau d'arrivée sur le plan juste après la création (?onboarded=1) : ce qui a été
	 * créé, et les deux suites naturelles. La page du plan l'affiche au-dessus du plan.
	 */
	public function getDone(\structure\Structure $eStructure, int $season, \Collection $cZone): string {

		$plots = 0;
		$greenhouses = 0;
		$beds = 0;

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				if($ePlot['zoneFill'] === FALSE) {
					($ePlot['mode'] === Plot::GREENHOUSE) ? $greenhouses++ : $plots++;
				}

				foreach($ePlot['cBed'] as $eBed) {
					if($eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE) {
						$beds++;
					}
				}

			}

		}

		$created = [p("{value} parcelle", "{value} parcelles", $cZone->count())];

		if($plots + $greenhouses > 0) {
			$created[] = PlotUi::getCount($plots, $greenhouses);
		}

		if($beds > 0) {
			$created[] = p("{value} planche", "{value} planches", $beds);
		}

		$h = '<div class="util-block-info">';
			$h .= \Asset::icon('check-lg', ['class' => 'util-block-icon']);
			$h .= '<div class="util-block-info-text">';
				$h .= '<div class="util-block-info-title">'.s("Votre plan est prêt avec {value} !", implode(', ', $created)).'</div>';
				$h .= '<div class="util-block-info-help">'.s("Tout se modifie et se complète en un clic ici : dupliquer, renommer, ajouter des planches, dessiner sur la carte. Et maintenant ?").'</div>';
			$h .= '</div>';
			$h .= '<div class="util-block-info-actions">';
				$h .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure).'" class="btn btn-secondary">'.s("Placer une première culture").'</a>';
				$h .= '<a onclick="this.closest(\'.util-block-info\').remove()" class="util-block-info-close" title="'.s("Fermer").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * ⚠️ **Deux lecteurs sans droit d'écriture, et non un.** Ce repli ne connaissait que le membre
	 * non responsable, à qui la raison — « seul un responsable peut le démarrer » — est utile : il
	 * peut aller demander ce droit. Un visiteur venu d'un groupe n'a rien à demander, et la même
	 * phrase ferait de l'absence un refus qui le vise. Il lit donc l'état de la ferme, sans
	 * explication et sans lien.
	 *
	 * Le lien vers le plan de culture reste au membre : c'est un écran de sa ferme, où il va déjà.
	 */
	protected function getReadOnly(\structure\Structure $eStructure): string {

		return \main\DesignUi::getEmptyRead(
			$eStructure,
			s("Le plan de la ferme n'a pas encore été créé. Seul un responsable de la structure peut le démarrer."),
			s("Cette ferme n'a pas encore créé son plan."),
			$eStructure->isMember()
				? '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure).'" class="btn btn-outline-primary">'.s("Voir le plan de culture").'</a>'
				: ''
		);

	}

	protected function getProgress(): string {

		$names = [
			1 => s("Le lieu"),
			2 => s("La culture"),
			3 => s("La parcelle"),
			4 => s("Jardins"),
			5 => s("Les planches")
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

	protected function getStepPlace(\util\FormUi $form, \structure\System $eSystem): string {

		$h = '<section class="onboarding-step" data-step="1" data-label="'.s("Votre ferme").'">';
			$h .= '<h2>'.s("Où se trouve votre ferme ?").'</h2>';
			$h .= '<p class="onboarding-help">'.s("Cette étape est facultative. Tapez votre commune ou son code postal, puis déplacez le repère sur la carte jusqu'à vos terres.").'</p>';
			$h .= $form->dynamicGroup($eSystem, 'place', function(\PropertyDescriber $d) {
				$d->labelAfter = NULL;
			});
			$h .= $form->hidden('altitude', $eSystem['altitude']);
			$h .= $this->getStepFooter($form, next: s("Continuer"));
		$h .= '</section>';

		return $h;

	}

	protected function getStepMode(\util\FormUi $form): string {

		$tiles = [
			OnboardingLib::PERMANENT => [
				'title' => s("Planches permanentes"),
				'text' => s("Des planches fixes d'une année sur l'autre, numérotées. Vos cultures se placent planche par planche."),
				'mini' => '<div class="onboarding-mini onboarding-mini-beds"><span></span><span></span><span></span><span></span><span></span><span></span></div>'
			],
			OnboardingLib::TEMPORARY => [
				'title' => s("Planches temporaires ou en plein"),
				'text' => s("Du plein champ retravaillé chaque saison, des rangs, des prairies. Vos cultures se placent librement sur une surface."),
				'mini' => '<div class="onboarding-mini onboarding-mini-surface"><span></span></div>'
			]
		];

		$modes = array_combine(array_keys($tiles), array_keys($tiles));

		$content = '<div class="onboarding-tiles">'.$form->radios('mode', $modes, OnboardingLib::PERMANENT, [
			'required' => TRUE,
			'callbackRadioContent' => function(string $mode) use($tiles) {
				$h = '<div class="onboarding-tile">';
					$h .= $tiles[$mode]['mini'];
					$h .= '<div class="onboarding-tile-text">';
						$h .= '<div class="onboarding-tile-title">'.$tiles[$mode]['title'].'</div>';
						$h .= '<div class="onboarding-tile-desc">'.$tiles[$mode]['text'].'</div>';
					$h .= '</div>';
				$h .= '</div>';
				return $h;
			}
		]).'</div>';

		$h = '<section class="onboarding-step" data-step="2" data-label="'.s("Comment cultivez-vous ?").'">';
			$h .= '<h2>'.s("Comment cultivez-vous ?").'</h2>';
			$h .= '<p class="onboarding-help">'.s("Faites un premier choix. Rien n'est figé, vous pourrez changer d'avis plus tard.").'</p>';
			$h .= $content;
			$h .= $this->getStepFooter($form, next: s("Continuer"));
		$h .= '</section>';

		return $h;

	}

	protected function getStepZone(\util\FormUi $form, Zone $eZone): string {

		// L'étape porte le tracé de la parcelle, et chaque jardin de l'étape suivante le sien : chacun lit son interrupteur et sa carte
		// dans son bloc (`data-drawing-scope`)
		$h = '<section class="onboarding-step" data-step="3" data-label="'.s("Votre parcelle").'" data-drawing-scope>';
			$h .= '<h2>'.s("Créez votre première parcelle").'</h2>';
			// Le lieu se choisit à l'étape 1 de ce même écran : c'est MapOnboarding.refreshZone() qui sait s'il y a une carte où dessiner
			$h .= '<p class="onboarding-help" data-onboarding-help="later">'.s("La parcelle est le terrain que vous cultivez. Vous y rangerez ensuite vos jardins puis vos planches, et votre plan de ferme se construit à partir d'elle. Vous pourrez dessiner son contour sur la carte plus tard.").'</p>';
			$h .= '<p class="onboarding-help hide" data-onboarding-help="now">'.s("La parcelle est le terrain que vous cultivez. Vous y rangerez ensuite vos jardins puis vos planches, et votre plan de ferme se construit à partir d'elle. Dessinez son contour sur la carte, sa surface se calcule d'après le dessin.").'</p>';
			// Un seul bloc pour les champs, que l'écart de l'étape ne sépare pas (onboarding.css)
			$h .= '<div>';
				$h .= '<div class="onboarding-drawing-switch hide" data-onboarding-drawing>'.new MapUi()->getDrawingSwitch($form, s("Dessiner maintenant la parcelle"), 'MapOnboarding.changeDrawing(input)').'</div>';
				// La parcelle se trace d'abord en contour, en rectangle si on le préfère, dont les cotes disent les deux côtés en mètres, ou en
				// parcelles déclarées à la PAC, dont la cote dit la surface ajoutée ou retirée. Le refus du contour se pose sous la carte (`data-wrapper`)
				$attributes = 'data-drawing-rectangle data-drawing-free data-drawing-pac data-drawing-area-field="area" data-length="'.s("{value} m", '{value}').'"';
				$attributes .= ' data-area-small="'.s("{value} m²", '{value}').'" data-area-large="'.s("{value} ha", '{value}').'"'.new PlanUi()->getMapPac($eZone['structure']);
				$h .= '<div id="onboarding-zone-map" class="hide" data-drawing-block data-wrapper="coordinates" data-error="'.encode(AlertUi::getError('Zone::coordinates.check')).'">';
					$h .= new MapUi()->getDrawingZone($form, 'onboarding-form', 'polygon', 'coordinates', $attributes, [
						'remove' => ['trash', s("Recommencer")]
					]);
					// La phrase suit la carte : remis à plus tard, le contour n'est plus montré et sa provenance n'a plus à être dite
					$h .= '<div class="form-info hide" '.attr('data-drawing-pac-info', s("Contour déclaré à la PAC en {value}. Retouchez-le s'il a changé depuis.", '{value}')).'></div>';
				$h .= '</div>';
				// Saisie quand le contour est remis à plus tard, la surface se lit sur le dessin sinon : MapOnboarding.refreshZone() désactive
				// celui des deux champs qui ne doit pas partir
				$h .= $form->dynamicGroup($eZone, 'area*', function(\PropertyDescriber $d) {
					$d->group = ['data-onboarding-area' => 'later'];
					$d->attributes = [
						'data-error' => AlertUi::getError('Zone::area.check')
					];
				});
				$h .= new MapUi()->getDrawingAreaDrawn($form, s("Surface de la parcelle"));
				$h .= $form->dynamicGroup($eZone, 'name', function(\PropertyDescriber $d) {
					$d->attributes = [
						'placeholder' => s("Ex. : {value}", OnboardingLib::getDefaultZoneName())
					];
				});
			$h .= '</div>';
			$h .= '<p class="util-info mb-0">'.s("Vous ajouterez les autres parcelles depuis votre plan de ferme.").'</p>';
			$h .= $this->getStepFooter($form, next: s("Continuer"));
		$h .= '</section>';

		return $h;

	}

	/**
	 * Le jardin de départ, un seul, en plein champ ou sous abri : le bloc est là d'office, en groupes comme la parcelle, et « Passer cette étape » le met de
	 * côté sans le perdre (MapOnboarding.skip()). Le bloc est un fieldset pour qu'un seul `disabled` retire tous ses champs de l'envoi,
	 * chacun gardant le sien pour le retour : la surface lue sur le dessin et le contour se désactivent seuls tant qu'il n'y a pas de tracé
	 */
	protected function getStepPlots(\util\FormUi $form, \structure\Structure $eStructure): string {

		$h = '<section class="onboarding-step" data-step="4" data-label="'.s("Vos jardins").'">';
			$h .= '<h2>'.s("Comment est divisée la parcelle ?").'</h2>';
			$h .= '<p class="onboarding-help">'.s("Votre parcelle peut se découper en différents lieux de culture appelés jardins, en plein champ ou sous abri. C'est là que vos planches se rangent.").'</p>';
			$h .= $this->getPlot($form, $eStructure, 1);
			$h .= '<p class="util-info mb-0">'.s("Vous ajouterez les autres jardins depuis votre plan de ferme.").'</p>';
			$h .= $this->getStepFooter($form, next: s("Continuer vers les planches"), submit: TRUE, skip: TRUE);
		$h .= '</section>';

		return $h;

	}

	protected function getStepBeds(\util\FormUi $form, \structure\Structure $eStructure): string {

		$h = '<section class="onboarding-step" data-step="5" data-label="'.s("Vos planches").'">';
			$h .= '<h2>'.s("Quelles dimensions pour vos planches ?").'</h2>';
			$h .= '<div id="onboarding-beds" class="onboarding-beds" data-label-direct="'.s("Directement sur la parcelle").'" data-label-open-field="'.s("Jardin").'" data-label-greenhouse="'.s("Jardin sous abri").'"></div>';
			$h .= '<p class="util-info mb-0">'.s("Vous avez des planches de dimensions différentes ?").'<br/>'.s("Vous pourrez compléter votre plan à l'étape suivante.").'</p>';
			$hasDefaults = ($eStructure->conf(Configuration::class)['defaultBedLength'] !== NULL or $eStructure->conf(Configuration::class)['defaultBedWidth'] !== NULL or $eStructure->conf(Configuration::class)['defaultAlleyWidth'] !== NULL);
			$h .= $form->checkbox('saveDefaults', 1, ['checked' => ($hasDefaults === FALSE), 'callbackLabel' => fn($input) => $input.' '.s("Retenir ces dimensions comme valeurs par défaut pour les prochaines planches")]);
			$h .= $this->getStepFooter($form, next: NULL, submit: TRUE);
		$h .= '</section>';

		return $h;

	}

	protected function getStepFooter(\util\FormUi $form, ?string $next, bool $submit = FALSE, bool $skip = FALSE): string {

		$h = '<div class="onboarding-footer">';
			if($skip) {
				$h .= '<div class="onboarding-footer-side">';
					$h .= '<a class="btn btn-outline-primary" data-onboarding-skip>'.s("Passer cette étape").'</a>';
				$h .= '</div>';
			}
			$h .= '<div class="onboarding-footer-main">';
				if($next !== NULL) {
					$h .= '<a class="btn btn-primary" data-onboarding-next>'.$next.' '.\Asset::icon('arrow-right').'</a>';
				}
				if($submit) {
					$h .= $form->submit(s("Créer mon plan"), ['class' => 'btn btn-primary', 'data-onboarding-submit' => '']);
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le jardin ou l'abri de l'étape 4. Le nom du champ porte la propriété et l'indice (plotName[1]) pour que les erreurs de
	 * buildIndex() retombent sur leur groupe.
	 */
	protected function getPlot(\util\FormUi $form, \structure\Structure $eStructure, int $index): string {

		$ePlot = new Plot([
			'structure' => $eStructure,
			'mode' => Plot::OPEN_FIELD
		]);

		$h = '<fieldset id="onboarding-plot" class="onboarding-plot" data-index="'.$index.'" data-plot-mode="'.Plot::OPEN_FIELD.'" data-drawing-scope data-texts="'.encode(json_encode(['example' => s("Ex. : {value}", ['value' => '__value__'])])).'">';

			$h .= $form->group(
				s("Jardin"),
				'<div class="onboarding-seg">'.$form->radios('plotMode['.$index.']', [
					Plot::OPEN_FIELD => s("Plein champ"),
					Plot::GREENHOUSE => s("Abri")
				], Plot::OPEN_FIELD, ['required' => TRUE]).'</div>',
				['data-wrapper' => 'plotMode['.$index.']']
			);

			$h .= $form->dynamicGroup($ePlot, 'name['.$index.']', function(\PropertyDescriber $d) use($index) {
				$d->name = 'plotName['.$index.']';
				$d->label = s("Nom");
				$d->attributes = [
					'placeholder' => s("Ex. : {value}", OnboardingLib::getDefaultPlotName(Plot::OPEN_FIELD, 1)),
					'data-default-open-field' => OnboardingLib::getDefaultPlotName(Plot::OPEN_FIELD, '__rank__'),
					'data-default-greenhouse' => OnboardingLib::getDefaultPlotName(Plot::GREENHOUSE, '__rank__')
				];
			});

			// Un abri tracé écrit ses dimensions dans ces champs, qui le redimensionnent aussi (MapDrawing.refreshDimensions())
			$dimension = fn(string $property, array $attributes = []) => function(\PropertyDescriber $d) use($property, $attributes, $index) {
				$d->name = 'plot'.ucfirst($property).'['.$index.']';
				$d->group = ['data-wrapper' => $d->name, 'data-plot-field' => Plot::GREENHOUSE];
				$d->attributes = $attributes + ['data-drawing-dimension' => $property];
			};

			$h .= $form->dynamicGroup($ePlot, 'length['.$index.']', $dimension('length', ['placeholder' => s("Ex. : 30"), 'data-error' => AlertUi::getError('Plot::length.required')]));
			$h .= $form->dynamicGroup($ePlot, 'width['.$index.']', $dimension('width', ['data-error' => AlertUi::getError('Plot::width.required')]));

			$h .= $this->getPlotDrawing($form, $index);

			// Tracé, le jardin part avec la surface de son dessin, que la séance écrit dans le champ caché, et la saisie se désactive en la
			// montrant (MapOnboarding.refreshPlot()). Les deux champs portent le même nom : le groupe dit le sien
			$h .= $form->dynamicGroup($ePlot, 'area['.$index.']', function(\PropertyDescriber $d) use($form, $index) {
				$d->name = 'plotArea['.$index.']';
				$d->group = ['data-wrapper' => 'plotArea['.$index.']', 'data-plot-field' => Plot::OPEN_FIELD];
				$d->attributes = ['data-error' => AlertUi::getError('Plot::area.check')];
				$d->after = $form->hidden('plotArea['.$index.']', attributes: ['disabled' => 'disabled']);
			});

		$h .= '</fieldset>';

		return $h;

	}

	/**
	 * Le tracé d'un jardin, offert quand la parcelle est tracée (MapOnboarding.refreshPlot()). Tracé, le bloc se replie avec
	 * ses champs en lecture seule et se rouvre par « Modifier le tracé ». Le contour de la parcelle, qui aimante le tracé, et la forme,
	 * contour ou rectangle selon le mode, se posent sur le lien à l'ouverture, la parcelle et le mode pouvant changer jusque-là. Le refus
	 * d'un envoi sans tracé suit aussi le mode.
	 */
	protected function getPlotDrawing(\util\FormUi $form, int $index): string {

		$attributes = 'data-drawing-property="coordinates" data-length="'.s("{value} m", '{value}').'"';
		$attributes .= ' data-area-small="'.s("{value} m²", '{value}').'" data-area-large="'.s("{value} ha", '{value}').'"';

		$h = '<div class="hide" data-onboarding-plot-drawing>';
			$h .= '<div class="onboarding-drawing-switch">'.new MapUi()->getDrawingSwitch($form, s("Dessiner maintenant le jardin"), 'MapOnboarding.changePlotDrawing(input)').'</div>';
			$h .= '<div data-drawing-block data-wrapper="plotCoordinates['.$index.']" '.attr('data-error', AlertUi::getError('Plot::coordinates.drawing')).' data-error-name="Plot::coordinates.drawing">';
				$h .= new MapUi()->getDrawingZone($form, 'onboarding-form', 'polygon', 'plotCoordinates['.$index.']', $attributes, [
					'remove' => ['trash', s("Recommencer")]
				], container: 'onboarding-plot-'.$index.'-map');
			$h .= '</div>';
			$h .= '<a class="btn btn-sm btn-outline-primary hide" data-onboarding-plot-edit>'.\Asset::icon('pencil').' '.s("Modifier le tracé").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le rang des planches d'un emplacement, offert quand l'emplacement est tracé (MapOnboarding.refreshBeds()) : il se pose d'office
	 * dans son contour, qui se lit sur le lien à l'ouverture, et suit le nombre et le format saisis (MapDrawingLine.getCreated()).
	 * Sans tracé, les planches se créent à dessiner, comme sur la Carte.
	 */
	protected function getBedDrawing(\util\FormUi $form): string {

		$fields = [
			'number' => 'beds[__i__]',
			'length' => 'bedLength[__i__]',
			'width' => 'bedWidth[__i__]',
			'alleyWidth' => 'bedAlleyWidth[__i__]',
			'name' => NULL
		];

		$attributes = 'data-drawing-create data-drawing-property="draw" data-drawing-last="null" '.attr('data-drawing-fields', json_encode($fields));
		$attributes .= ' data-length="'.s("{value} m", '{value}').'" data-alley="'.s("{value} cm", '{value}').'"';

		$h = '<div class="hide" data-onboarding-bed-drawing>';
			$h .= '<div class="onboarding-drawing-switch">'.new MapUi()->getDrawingSwitch($form, s("Dessiner maintenant les planches"), 'MapOnboarding.changeBedDrawing(input)').'</div>';
			$h .= '<div data-drawing-block data-wrapper="bedDraw[__i__]">';
				$h .= new MapUi()->getDrawingZone($form, 'onboarding-form', 'line', 'bedDraw[__i__]', $attributes, [
					'flip' => ['symmetry-horizontal', s("Inverser")]
				], container: 'onboarding-bed-__i__-map');
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne de format de planches à l'étape 5, une par emplacement (l'indice 0 est la
	 * parcelle elle-même, quand elle n'a ni jardin ni abri). Longueur, largeur et passe-pied
	 * partent des valeurs par défaut de la structure.
	 */
	protected function getBedTemplate(\util\FormUi $form, \structure\Structure $eStructure): string {

		$eBed = new Bed([
			'length' => $eStructure->conf(Configuration::class)['defaultBedLength'],
			'width' => $eStructure->conf(Configuration::class)['defaultBedWidth'],
			'alleyWidth' => $eStructure->conf(Configuration::class)['defaultAlleyWidth']
		]);

		$h = '<template id="onboarding-bed-template">';
			$h .= '<div class="util-card onboarding-row onboarding-bed" data-index="__i__" data-drawing-scope>';
				$h .= '<div class="onboarding-bed-label"></div>';
				$h .= '<div class="onboarding-bed-fields">';
					$h .= '<div data-wrapper="beds[__i__]" class="onboarding-field">';
						$h .= '<label class="onboarding-field-label">'.s("Nombre de planches").'</label>';
						$h .= $form->number('beds[__i__]', NULL, ['min' => 1, 'max' => MapSetting::$onboardingBedsMax, 'placeholder' => s("Ex. : {value}", 12), 'data-onboarding-count' => '']);
					$h .= '</div>';
					$h .= '<div data-wrapper="bedLength[__i__]" class="onboarding-field">';
						$h .= '<label class="onboarding-field-label">'.s("Longueur d'une planche").'</label>';
						$h .= $form->dynamicField($eBed, 'length[__i__]', function(\PropertyDescriber $d) {
							$d->name = 'bedLength[__i__]';
							$d->prepend = NULL;
						});
					$h .= '</div>';
					$h .= '<div data-wrapper="bedWidth[__i__]" class="onboarding-field">';
						$h .= '<label class="onboarding-field-label">'.s("Largeur travaillée").'</label>';
						$h .= $form->dynamicField($eBed, 'width[__i__]', function(\PropertyDescriber $d) {
							$d->name = 'bedWidth[__i__]';
							$d->prepend = NULL;
						});
					$h .= '</div>';
					$h .= '<div data-wrapper="bedAlleyWidth[__i__]" class="onboarding-field">';
						$h .= '<label class="onboarding-field-label">'.s("Passe-pied entre deux planches").'</label>';
						$h .= $form->dynamicField($eBed, 'alleyWidth[__i__]', function(\PropertyDescriber $d) {
							$d->name = 'bedAlleyWidth[__i__]';
							$d->prepend = NULL;
						});
					$h .= '</div>';
				$h .= '</div>';
				$h .= $this->getBedDrawing($form);
			$h .= '</div>';
		$h .= '</template>';

		return $h;

	}

}
?>
