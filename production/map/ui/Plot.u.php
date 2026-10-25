<?php
namespace map;

class PlotUi {

	public function __construct() {

		\Asset::css('map', 'plot.css');

	}

	public function getPlots(\structure\Structure $eStructure, \Collection $cPlot, Zone $eZone, int $season, \series\Series|\series\Task $ePlaced, bool $print, bool $withForm = FALSE): string {

		$eZone->expects(['id', 'area']);

		if($cPlot->empty()) {
			return '';
		}

		$named = self::hasNamedPlots($cPlot);

		$h = '';

		[$startTs, $stopTs] = new \series\PlaceUi()->getBounds($eStructure->conf(Configuration::class), $season);

		$target = NULL;

		if($ePlaced->notEmpty()) {
			$target = ($ePlaced instanceof \series\Task) ?
				new \series\PlaceUi()->getTaskBounds($ePlaced, $startTs, $stopTs) :
				new \series\PlaceUi()->getSeriesBounds($ePlaced, $season, $startTs, $stopTs);
		}

		foreach($cPlot as $ePlot) {

			$beds = $ePlot['cBed']->find(fn(Bed $eBed) => $eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE)->count();

			$h .= '<div class="plot-wrapper util-card plot-card '.($ePlot['cBed']->count() <= 10 ? 'plot-wrapper-preserve' : '').' " data-plot="'.$ePlot['id'].'" '.\series\PlaceGardenAiUi::getPlotPath($ePlot, $beds).'>';


			$h .= $this->getPlotCardHead($eStructure, $ePlot, $season, $beds, $print, $named);


			$h .= '<div class="plot-card-body">';

				$h .= new BedUi()->displayBedsFromPlot($eStructure, $ePlot, $season, $ePlaced, $print, $withForm, $target);

			$h .= '</div>';

			$h .= '</div>';

			$h .= '</div>';

		}

		return $h;

	}

	protected function getPlotCardHead(\structure\Structure $eStructure, Plot $ePlot, int $season, int $beds, bool $print, bool $named = FALSE): string {

		$h = '<div data-ref="plot" id="plot-item-'.$ePlot['id'].'" class="plot-title" data-name="'.encode(\Asset::icon('chevron-right').' '.encode($ePlot['name'])).'">';

			$h .= self::getTitle($ePlot, $named);

			$h .= match($ePlot['mode']) {
				Plot::GREENHOUSE => '<span class="badge badge-greenhouse">'.s("Abri").'</span>',
				default => '<span class="badge badge-production">'.s("Plein champ").'</span>',
			};

			$h .= '<span class="plot-title-info">'.$ePlot->getArea().'</span>';

			if($beds > 0) {
				$h .= '<span class="plot-title-info">· '.p("{value} planche", "{value} planches", $beds).'</span>';
			}

		$h .= '</div>';

		$h .= '<div class="plot-card-scroll">';

		$h .= '<div class="plot-card-axis bed-item-grid bed-item-grid-plan bed-item-grid-header">';
			$h .= '<div></div>';
			$h .= '<div class="plot-card-months">';
				$h .= new \series\CultivationUi()->getListSeason($eStructure->conf(Configuration::class), $season, hasWeeks: $print === FALSE);
			$h .= '</div>';
		$h .= '</div>';

		$h .= new \series\CultivationUi()->getListGrid($eStructure->conf(Configuration::class), $season, hasWeeks: $print === FALSE);

		return $h;

	}

	public static function getLabel(Plot $ePlot): string {

		if($ePlot['name']) {
			return $ePlot->getLabel();
		}

		return match($ePlot['mode']) {
			Plot::GREENHOUSE => s("Abri"),
			default => s("Jardin"),
		};

	}

	/**
	 * Le titre d'un jardin, et rien quand il n'en porte pas : « Jardin » n'était
	 * pas un nom, c'était l'absence de nom, et la vignette de mode dit déjà s'il
	 * s'agit d'un abri ou de plein champ.
	 */
	public static function getTitle(Plot $ePlot, bool $named = FALSE): string {

		if($ePlot['name'] !== NULL) {
			return '<b>'.encode($ePlot->getLabel()).'</b>';
		}

		// Sans jardin nommé dans la parcelle, il n'y a pas de « hors jardin » : il
		// n'y a que la parcelle, et la nommer deux fois n'apprend rien.
		return $named ? '<b>'.s("Hors jardin").'</b>' : '';

	}

	// Un abri est un jardin : il entre dans le compte, et le compte dit combien sont couverts
	public static function getCount(int $openField, int $greenhouse): string {

		if($openField === 0 and $greenhouse > 0) {
			return p("{value} jardin sous abri", "{value} jardins sous abri", $greenhouse);
		}

		$count = p("{value} jardin", "{value} jardins", $openField + $greenhouse);

		if($greenhouse > 0) {
			return s("{count} dont {value} sous abri", ['count' => $count, 'value' => $greenhouse]);
		}

		return $count;

	}

	public static function hasNamedPlots(\Collection $cPlot): bool {

		foreach($cPlot as $ePlot) {

			if($ePlot['name'] !== NULL) {
				return TRUE;
			}

		}

		return FALSE;

	}

	public static function getName(Plot $ePlot): string {

		if($ePlot['name'] !== NULL) {
			return $ePlot['name'];
		}

		return self::getLabel($ePlot);

	}

	public function getRotations(\structure\Structure $eStructure, \Collection $cPlot, int $season): string {

		$h = '';

		$years = $eStructure->conf(Configuration::class)['rotationYears'];
		$named = self::hasNamedPlots($cPlot);

		foreach($cPlot as $ePlot) {

			$cBed = $ePlot['cBed']->find(fn(Bed $eBed) => $eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE);

			$watch = 0;

			foreach($cBed as $eBed) {

				if($eBed['familyReturns'] !== []) {
					$watch++;
				}

			}

			$h .= '<div class="plot-wrapper util-card plot-card plot-card-rotation">';

				// Même ancre que la chronologie : c'est elle que vise le sélecteur
				// d'emplacements quand on choisit un jardin.
				$h .= '<div id="plot-item-'.$ePlot['id'].'" class="plot-title">';

					$h .= self::getTitle($ePlot, $named);

					$h .= match($ePlot['mode']) {
						Plot::GREENHOUSE => '<span class="badge badge-greenhouse">'.s("Abri").'</span>',
						default => '<span class="badge badge-production">'.s("Plein champ").'</span>',
					};

					$h .= '<span class="plot-title-info">'.$ePlot->getArea().'</span>';

					if($cBed->count() > 0) {
						$h .= '<span class="plot-title-info">· '.p("{value} planche", "{value} planches", $cBed->count()).'</span>';
					}

					if($watch > 0) {
						$h .= '<span class="plot-title-watch">'.p("{value} planche à surveiller", "{value} planches à surveiller", $watch).'</span>';
					}

				$h .= '</div>';

				// Sous 992 px les colonnes d'années deviennent illisibles : la carte
				// défile plutôt que de les comprimer.
				$h .= '<div class="plot-card-scroll util-overflow-lg">';

					$h .= '<div>';

						$h .= '<div class="bed-item-grid bed-item-grid-rotation-'.$years.' bed-item-grid-header plot-card-axis">';
							$h .= '<div></div>';
							$h .= new ZoneUi()->displayHeaderByRotation($season, $years);
						$h .= '</div>';

						$h .= new BedUi()->getRotations($eStructure, $ePlot['cBed'], $season);

					$h .= '</div>';

				$h .= '</div>';

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Un jardin à créer se trace au rectangle sur la carte de sa parcelle dans la saison regardée (`$eZone`, relue avec
	 * ses jardins et leurs planches), comme sur la Carte : le panneau s'ouvre sur le tracé quand la parcelle a son contour. Tracé, un
	 * jardin a la surface lue sur le dessin, et un abri des dimensions qui se saisissent comme elles se lisent : le rectangle les
	 * suit, et elles suivent le rectangle (MapDrawing.changeDimensions(), refreshDimensions()). Remis à plus tard, il se saisit.
	 */
	public function create(Plot $ePlot, int $season, Zone $eZone): \Panel {

		$ePlot->expects(['zone', 'mode', 'coordinates', 'vertices']);

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/map/plot:doCreate', ['id' => 'plot-create', 'autocomplete' => 'off']);

			$h .= $form->hidden('zone', $eZone['id']);
			$h .= $form->hidden('season', \main\MainSetting::$onlineSeason);

			$h .= $form->group(
				s("Parcelle"),
				'<b>'.encode($eZone['name']).'</b>'
			);

			$h .= $this->write($form, $ePlot);

			// Le mode vient du lien cliqué : on ajoute soit un jardin, soit un abri
			$h .= $form->hidden('mode', $ePlot['mode']);

			$drawing = ($eZone['coordinates'] !== NULL);

			$h .= $form->dynamicGroup($ePlot, 'seasonFirst');

			// Ce qui fait l'abri se choisit avant de le dessiner
			if($ePlot['mode'] === Plot::GREENHOUSE) {
				$h .= $this->writeGreenhouse($form, $ePlot, drawing: TRUE);
			}

			if($drawing) {
				$h .= $this->getDrawing($form, 'plot-create', $season, $eZone, $ePlot, new Plot());
			} else {
				$h .= $form->group(content: $this->getZoneUndrawn($eZone, $ePlot), attributes: ['data-wrapper' => 'coordinates']);
			}

			if($ePlot['mode'] === Plot::OPEN_FIELD) {
				$h .= new MapUi()->getDrawingArea($form, $ePlot, s("Surface du jardin"), $drawing);
			}

			$h .= $form->group(
				content: $form->submit(s("Créer le jardin"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-plot-create',
			title: s("Ajouter un jardin"),
			body: $h
		);

	}

	/**
	 * Le tracé d'un jardin, aimanté au contour de sa parcelle (drawing.js) : un contour posé point par point, ou un
	 * rectangle s'il le préfère (MapDrawingPolygon). Un abri se trace en rectangle, qui porte ses dimensions, et celui qu'on modifie
	 * s'ouvre en rectangle sur les sommets que la page a préparés (`vertices`), ou tel qu'il est tracé, à reprendre en rectangle.
	 *
	 * `$ePlotSeason` est le jardin modifié relu parmi ceux de la carte (MapLib::loadDrawing()), qu'il cache le temps du geste, vide
	 * à la création ou hors de la saison regardée, où la carte cadre le contour enregistré. Seul un jardin sans contour se dessine plus
	 * tard.
	 */
	protected function getDrawing(\util\FormUi $form, string $formId, int $season, Zone $eZone, Plot $ePlot, Plot $ePlotSeason): string {

		$drawn = ($ePlot['coordinates'] !== NULL);

		$attributes = attr('data-drawing-coordinates', json_encode($ePlot['vertices'] === NULL ? $ePlot['coordinates'] : $ePlot['vertices'])).' '.attr('data-drawing-contour', json_encode($eZone['coordinates'])).' data-length="'.s("{value} m", '{value}').'"';

		if($ePlotSeason->notEmpty()) {
			$attributes .= ' data-drawing-target="plot" data-drawing-mask="plots plotLabels" data-drawing-id="'.$ePlotSeason['id'].'"';
		}

		if($ePlot['mode'] === Plot::OPEN_FIELD) {

			$shape = 'polygon';
			$attributes .= ' data-drawing-rectangle data-drawing-free data-drawing-area-field="area"';

		} else if($drawn and $ePlot['vertices'] === NULL) {

			$shape = 'polygon';
			$attributes .= ' data-drawing-rectangle data-drawing-dimensions';

		} else {

			$shape = 'rectangle';
			$attributes .= ' data-drawing-dimensions';

		}

		// Un envoi sans tracé, quand le jardin doit se dessiner maintenant, est arrêté avant de partir. Un jardin déjà tracé n'a pas
		// d'interrupteur, « Recommencer » le retrace
		if($drawn) {
			$error = 'Plot::coordinates.redraw';
		} else {
			$error = 'Plot::coordinates.drawing';
		}

		$frame = ($drawn and $ePlotSeason->empty()) ? $ePlot['coordinates'] : NULL;

		$map = new MapUi()->getDrawingEmbedded($form, $formId, $season, $eZone, $ePlotSeason, $shape, 'coordinates', $attributes, ['remove' => ['trash', s("Recommencer")]], frame: $frame);

		return new MapUi()->getDrawingGroup($form, $map, 'coordinates', $drawn ? NULL : s("Dessiner maintenant le jardin"), $error);

	}

	/**
	 * Un jardin se modifie sur la carte de sa parcelle dans la saison regardée (`$eZone`, relue avec ses jardins et leurs
	 * planches), comme il se crée (getDrawing()) : son contour s'y reprend, et les dimensions d'un abri rectangle se saisissent
	 * comme elles se lisent. Un abri d'une autre forme écrit les siennes sans les laisser saisir, jusqu'à ce qu'il soit repris en
	 * rectangle (MapDrawing.beginShape()). Un jardin sans contour se dessine maintenant ou plus tard, maintenant par défaut.
	 */
	public function update(Plot $ePlot, ?string $origin, int $season, Zone $eZone, Plot $ePlotSeason): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/map/plot:doUpdate', ['id' => 'plot-update', 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $ePlot['id']);
			$h .= $form->hidden('season', \main\MainSetting::$onlineSeason);
			$h .= $form->hidden('origin', $origin);

			$h .= $form->group(
				s("Parcelle"),
				'<u>'.encode($eZone['name']).'</u>'
			);

			$h .= $this->write($form, $ePlot);

			$h .= $form->group(
				s("Exploité"),
				new SeasonUi()->getField($form, $ePlot),
				['data-wrapper' => 'seasonFirst seasonLast']
			);

			$drawing = ($eZone['coordinates'] !== NULL);

			// Ce qui fait l'abri se lit avant son dessin, comme à la création
			if($ePlot['mode'] === Plot::GREENHOUSE) {
				$h .= $this->writeGreenhouse($form, $ePlot, drawing: $drawing);
			}

			if($drawing) {
				$h .= $this->getDrawing($form, 'plot-update', $season, $eZone, $ePlot, $ePlotSeason);
			} else {
				$h .= $form->group(content: $this->getZoneUndrawn($eZone, $ePlot, $origin), attributes: ['data-wrapper' => 'coordinates']);
			}

			if($ePlot['mode'] === Plot::OPEN_FIELD) {
				$h .= new MapUi()->getDrawingArea($form, $ePlot, s("Surface du jardin"), $drawing);
			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-plot-update',
			title: s("Modifier un jardin"),
			body: $h
		);

	}

	/**
	 * Un jardin dessiné se duplique sur la carte de la saison regardée (`$ePlotSeason`, le jardin relu parmi ceux de sa parcelle
	 * `$eZone`, vide hors de la saison) : sa copie se pose à côté de lui avec ses planches (MapDrawingCopy), à l'écart que donne
	 * MapSetting::COPY_GAP. Un abri, un jardin sans contour ou des planches posées à même la parcelle se dupliquent sans carte.
	 */
	public function duplicate(Plot $ePlot, int $season, Zone $eZone, Plot $ePlotSeason): \Panel {

		$form = new \util\FormUi();

		$eDuplicate = new Plot([
			'zone' => $ePlot['zone'],
			'mode' => $ePlot['mode']
		]);

		$h = $form->openAjax('/map/plot:doDuplicate', ['id' => 'plot-duplicate', 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $ePlot['id']);
			$h .= $form->hidden('season', $season);

			$h .= $form->group(
				s("Jardin"),
				'<b>'.encode($ePlot['name']).'</b>'
			);

			$h .= $form->dynamicGroup($eDuplicate, 'name', function(\PropertyDescriber $d) {
				$d->label = s("Nom du nouveau jardin");
			});

			if($ePlotSeason->notEmpty() and $ePlotSeason->acceptPlacement()) {

				$attributes = attr('data-drawing-coordinates', json_encode($ePlotSeason['coordinates']));
				$attributes .= ' '.attr('data-drawing-beds', json_encode($ePlotSeason['bedsDrawn']));
				$attributes .= ' data-drawing-gap="'.MapSetting::COPY_GAP.'"';

				$h .= $form->group(
					content: new MapUi()->getDrawingEmbedded($form, 'plot-duplicate', $season, $eZone, $ePlotSeason, 'copy', 'placement', $attributes),
					attributes: ['data-wrapper' => 'placement']
				);

			}

			$h .= $form->group(
				content: '<p class="util-info">'.s("Les planches gardent leurs dimensions et leurs saisons.").'</p>'.$form->submit(s("Dupliquer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-plot-duplicate',
			title: s("Dupliquer un jardin"),
			body: $h
		);

	}

	/**
	 * Couvrir ou découvrir un jardin. C'est une bascule : le mode visé se déduit de l'état
	 * enregistré, il n'est porté ni par un champ du formulaire ni par l'URL.
	 */
	public function updateGreenhouse(Plot $ePlot): \Panel {

		MapboxUi::loadCartography();

		$ePlot->expects(['mode']);

		$form = new \util\FormUi();

		$h = $form->openAjax('/map/plot:doUpdateGreenhouse', ['id' => 'plot-greenhouse']);

			$h .= $form->hidden('id', $ePlot['id']);
			$h .= $form->hidden('season', \main\MainSetting::$onlineSeason);

			$h .= $form->group(
				s("Jardin"),
				'<u>'.encode($ePlot['name'] ?? '').'</u>'
			);

			if($ePlot['mode'] === Plot::OPEN_FIELD) {

				$h .= $this->writeGreenhouseDrawn($form, $ePlot);

				$submit = s("Couvrir le jardin");

			} else {

				$h .= $form->group(content: '<p class="util-info">'.s("Les planches de ce jardin ne seront plus comptées sous abri.").'</p>');
				$h .= $form->dynamicGroup($ePlot, 'area');

				$submit = s("Revenir en plein champ");

			}

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-plot-greenhouse',
			title: match($ePlot['mode']) {
				Plot::OPEN_FIELD => s("Couvrir le jardin avec un abri"),
				Plot::GREENHOUSE => s("Revenir en plein champ")
			},
			body: $h
		);

	}

	public function write(\util\FormUi $form, Plot $ePlot): string {

		return $form->dynamicGroup($ePlot, 'name');

	}

	/* `$group` et `$attributes` vont aux deux dimensions. Sur une parcelle tracée (`$drawing`), elles se saisissent et suivent le
	   tracé, chacune marquée de la dimension qu'elle porte (`data-drawing-dimension`) ; sous un jardin qu'on couvre `$drawn` les lit
	   sur son contour (writeGreenhouseDrawn()) */
	public function writeGreenhouse(\util\FormUi $form, Plot $ePlot, array $group = [], array $attributes = [], string $drawn = '', bool $drawing = FALSE): string {

		$dimension = fn(string $property) => function(\PropertyDescriber $d) use($group, $attributes, $drawing, $property) {
			$d->group = $group;
			$d->attributes = $attributes + ($drawing ? ['data-drawing-dimension' => $property] : []);
		};

		$fields = $form->dynamicGroup($ePlot, 'decorGreenhouse');
		$fields .= $form->dynamicGroups($ePlot, ['length', 'width'], ['length' => $dimension('length'), 'width' => $dimension('width')]);
		$fields .= $drawn;

		return $form->fieldset(s("Abri"), $fields, ['class' => 'form-fieldset']);

	}

	/* Les dimensions d'un jardin qu'on couvre. Tracé, il les lit sur son contour (Plot::properties(), étape `drawn`) : la saisie ne reste
	   qu'au jardin sans contour, et elle est désactivée sinon, pour ne pas partir */
	protected function writeGreenhouseDrawn(\util\FormUi $form, Plot $ePlot): string {

		if($ePlot['dimensions'] === NULL) {
			return $this->writeGreenhouse($form, $ePlot);
		}

		$dimensions = $form->group(
			s("Dimensions de l'abri"),
			'<b>'.s("{length} × {width} m", ['length' => \util\TextUi::number($ePlot['dimensions'][0], NULL), 'width' => \util\TextUi::number($ePlot['dimensions'][1], NULL)]).'</b>',
			['data-wrapper' => 'length width']
		);

		return $this->writeGreenhouse($form, $ePlot, ['class' => 'hide'], ['disabled' => 'disabled'], $dimensions);

	}

	protected function getZoneUndrawn(Zone $eZone, Plot $ePlot, ?string $origin = NULL): string {

		$h = '<div class="form-control-block">';
			$h .= '<p>'.match($ePlot['mode']) {
				Plot::GREENHOUSE => s("Pour dessiner ce jardin sur la carte, vous devez d'abord cartographier la parcelle dans laquelle il se trouve. Vous pouvez aussi sauter cette étape."),
				Plot::OPEN_FIELD => s("Pour dessiner ce jardin sur la carte, vous devez d'abord cartographier la parcelle dans laquelle il se trouve. Vous pouvez aussi sauter cette étape et saisir directement la surface de ce jardin.")
			}.'</p>';
			$h .= '<a href="/map/zone:update?id='.$eZone['id'].'&season='.\main\MainSetting::$onlineSeason.($origin === NULL ? '' : '&origin='.$origin).'" class="btn btn-outline-primary">'.s("Cartographier la parcelle").'</a>';
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Plot::model()->describer($property, [
			'name' => s("Nom du jardin"),
			'structure' => s("Ferme"),
			'decorGreenhouse' => s("Modèle d'abri"),
			'length' => s("Longueur de l'abri"),
			'width' => s("Largeur de l'abri"),
			'area' => s("Surface du jardin"),
			'seasonFirst' => s("Jardin exploité depuis"),
			'seasonLast' => s("Jardin exploité jusqu'à"),
			'createdAt' => s("Créée le"),
		]);

		switch($property) {

			case 'name' :
				$d->attributes = fn(\util\FormUi $form, Plot $e) => [
					'placeholder' => match($e['mode']) {
						Plot::GREENHOUSE => s("Ex. : Tunnel 1"),
						Plot::OPEN_FIELD => s("Ex. : Jardin 1")
					}
				];
				break;

			case 'decorGreenhouse' :
				$d->values = [
					Plot::ROUND => s("Tunnel rond"),
					Plot::STRAIGHT => s("Pieds droits"),
					Plot::GOTHIC => s("Gothique"),
					Plot::CHAPEL_2 => s("2 chapelles"),
					Plot::CHAPEL_3 => s("3 chapelles"),
					Plot::CHAPEL_4 => s("4 chapelles"),
				];
				$d->field = 'select';
				break;

			case 'area' :
				$d->append = s("m²");
				break;

			case 'length' :
			case 'width' :
				$d->append = s("m");
				break;

			case 'seasonFirst' :
			case 'seasonLast' :
				$d->field = function(\util\FormUi $form, \Element $e, $property) {

					$e->expects(['zone', 'structure']);

					$placeholder = [
						'seasonFirst' => s("la création de la parcelle"),
						'seasonLast' => s("la disparition de la parcelle")
					][$property];

					return new SeasonUi()->getDescriberField($form, $e, $e['structure'], $e['zone'], NULL, $property, $placeholder);
				};
				break;

		}

		return $d;

	}

}
?>
