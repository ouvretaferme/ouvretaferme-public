<?php
namespace map;

class MapUi {


	public static function canCartography(\structure\Structure $eStructure): bool {
		return ($eStructure->getSystem('placeLngLat') !== NULL);
	}


	/**
	 * Les noms passent par json_encode(), qui échappe aussi la barre oblique : un nom en `</script>` ne ferme pas le
	 * script qui les porte. `$withUndrawn` pose sous chaque jardin le compte de ses planches à dessiner, que
	 * MapLib::putDrawingState() a calculé.
	 */
	public function addZone(Zone $eZone, bool $withUndrawn = FALSE): string {

		$eZone->expects(['cPlot']);

		$h = '.addZone('.$eZone['id'].', '.json_encode($eZone['name']).', '.json_encode($eZone['coordinates']).')';

		foreach($eZone['cPlot'] as $ePlot) {

			if($ePlot['zoneFill'] === FALSE) {
				$h .= '.addPlot('.$ePlot['id'].', '.json_encode($ePlot['name']).', '.$eZone['id'].', '.json_encode($ePlot['coordinates']).', true, "'.$ePlot['mode'].'", '.($withUndrawn ? $ePlot['bedsUndrawn'] : 0).')';
			}

		}

		return $h;

	}

	public function addZoneBeds(Zone $eZone): string {

		$eZone->expects(['cPlot']);

		$h = '';

		foreach($eZone['cPlot'] as $ePlot) {
			$h .= '.attachPlot('.$ePlot['id'].', '.$eZone['id'].', "'.$ePlot['mode'].'")';
			$h .= $this->addBeds($ePlot);
		}

		return $h;

	}

	/**
	 * La carte d'un panneau où une forme se place sans se tracer (MapDrawingEmbedded, drawing.js) : la parcelle du jardin, ses
	 * jardins et leurs planches de la saison, la rangée des outils et le lien qui ouvre la séance avec le panneau. La forme
	 * s'écrit dans le champ caché `$field` du formulaire `$formId`, qui part par son propre envoi. Sans jardin (`$ePlot` vide), la
	 * forme est celle d'un jardin à créer, et la carte cadre la parcelle.
	 *
	 * `$shape` nomme la forme de MapDrawing.shapes qui s'ouvre, `$attributes` porte ce qu'elle lit du lien (`data-drawing-*`),
	 * `$tools` les outils de la rangée après les modes, chacun nommé comme la forme l'attend (`getTools()`) avec son icône et son
	 * libellé.
	 *
	 * `$frame` est le contour que la carte cadre quand la forme ouverte n'est pas sur la carte de la saison.
	 */
	public function getDrawingEmbedded(\util\FormUi $form, string $formId, int $season, Zone $eZone, Plot $ePlot, string $shape, string $field, string $attributes, array $tools = [], ?array $frame = NULL): string {

		$eZone->expects(['cPlot']);

		MapboxUi::load();

		$container = $formId.'-map';

		if($frame !== NULL) {
			$fit = '.fitCoordinatesBounds('.json_encode($frame).', {duration: 0})';
		} else if($ePlot->empty()) {
			$fit = '.fitZoneBounds('.$eZone['id'].', {duration: 0})';
		} else {
			$fit = '.fitPlotBounds('.$ePlot['id'].', {duration: 0})';
		}

		$create = 'new MapCartography("'.$container.'", '.$season.', false, true)'.$this->addZone($eZone).$this->addZoneBeds($eZone).$fit;

		$trigger = '<span class="hide" data-drawing="'.$shape.'" data-drawing-map="'.$container.'" data-drawing-form="'.$formId.'" data-drawing-field="'.$field.'" '.$attributes.' onrender="'.encode('MapDrawingEmbedded.startPanel(this, () => '.$create.')').'"></span>';

		$h = $this->getDrawingBlock($container, $trigger, $shape, $tools);

		$h .= $form->hidden($field);

		return $h;

	}

	/**
	 * La carte d'une parcelle qui n'existe pas encore, dont le contour part avec le formulaire `$formId` qui la crée : rien à
	 * montrer autour, et la séance s'ouvre quand l'écran la montre (MapDrawingEmbedded.start()), la carte n'ayant pas de taille
	 * tant qu'elle est cachée. `$shape`, `$attributes` et `$tools` comme pour getDrawingEmbedded(). Un formulaire qui porte plusieurs
	 * cartes nomme chacune (`$container`).
	 */
	public function getDrawingZone(\util\FormUi $form, string $formId, string $shape, string $field, string $attributes, array $tools = [], ?string $container = NULL): string {

		MapboxUi::load();

		$container ??= $formId.'-map';

		$trigger = '<span class="hide" data-drawing="'.$shape.'" data-drawing-map="'.$container.'" data-drawing-form="'.$formId.'" data-drawing-field="'.$field.'" data-drawing-coordinates="null" '.$attributes.'></span>';

		$h = $this->getDrawingBlock($container, $trigger, $shape, $tools);

		$h .= $form->hidden($field);

		return $h;

	}

	/**
	 * La carte d'une parcelle à créer dans un panneau, comme getDrawingZone() : le panneau se montre en se rendant, la séance
	 * s'ouvre donc avec lui (MapDrawingEmbedded.startPanel()), sur `$center` sans rien autour.
	 */
	public function getDrawingZonePanel(\util\FormUi $form, string $formId, int $season, array $center, string $shape, string $field, string $attributes, array $tools = []): string {

		$create = 'new MapCartography("'.$formId.'-map", '.$season.', false, true, {center: '.json_encode($center).', zoom: 17})';

		return $this->getDrawingZone($form, $formId, $shape, $field, $attributes.' onrender="'.encode('MapDrawingEmbedded.startPanel(this, () => '.$create.')').'"', $tools);

	}

	/**
	 * L'interrupteur « Dessiner maintenant / plus tard » d'un tracé, dont le libellé allumé nomme ce qu'on dessine (`$labelOn`). Il ne
	 * poste rien : c'est le tracé désactivé qui ne part pas (MapDrawingEmbedded.refresh()), et la séance le lit par sa classe
	 * (MapDrawingEmbedded.isLater()).
	 */
	public function getDrawingSwitch(\util\FormUi $form, string $labelOn, string $onchange = 'MapDrawingEmbedded.changeLater(input)'): string {

		return $form->switch(NULL, 1, [
			'class' => 'map-drawing-switch',
			'valueOn' => 1,
			'valueOff' => 0,
			'labelOn' => $labelOn,
			'labelOff' => s("Dessiner plus tard"),
			'onchange' => $onchange
		]);

	}

	/**
	 * Le groupe du tracé d'un panneau, sous son interrupteur quand le tracé peut se remettre à plus tard : `$switchLabel` en porte
	 * alors le libellé allumé (getDrawingSwitch()), NULL quand le tracé est dû. Le bloc de la carte (`data-drawing-block`) se trouve
	 * depuis le formulaire, un seul par formulaire. `$error` est le refus que porte le bloc quand un envoi sans tracé doit être arrêté
	 * avant de partir (MapDrawingEmbedded.checkDrawing()), et le groupe nomme `$wrapper`, le champ du tracé, pour qu'un refus du
	 * serveur y tombe.
	 */
	public function getDrawingGroup(\util\FormUi $form, string $map, string $wrapper, ?string $switchLabel, ?string $error = NULL): string {

		$h = '';

		if($switchLabel !== NULL) {
			$h .= '<div class="mb-1">'.$this->getDrawingSwitch($form, $switchLabel).'</div>';
		}

		$h .= '<div data-drawing-block';
		if($error !== NULL) {
			$h .= ' data-error="'.encode(AlertUi::getError($error)).'" data-error-name="'.$error.'"';
		}
		$h .= '>'.$map.'</div>';

		return $form->group(
			content: $h,
			attributes: ['data-wrapper' => $wrapper]
		);

	}

	/**
	 * La surface d'une forme qui se trace (`$drawing`) : lue sur le tracé, elle part dans un champ caché que la séance remplit
	 * (MapDrawingEmbedded.refreshArea()) et sa ligne ne se montre que quand elle part (MapDrawingEmbedded.refreshSurface()). Elle
	 * ne se saisit que sans tracé, et seule celle qui se voit part : l'autre est désactivée (MapDrawingEmbedded.changeLater()). La
	 * ligne lue nomme le champ qu'elle porte, sans quoi un refus du serveur sur la surface tomberait dans la saisie cachée.
	 */
	public function getDrawingArea(\util\FormUi $form, \Element $e, string $label, bool $drawing): string {

		$h = '';

		if($drawing) {
			$h .= $this->getDrawingAreaDrawn($form, $label, ['data-wrapper' => 'area']);
		}

		$h .= $form->dynamicGroup($e, 'area', function(\PropertyDescriber $d) use($drawing) {
			$d->group = ['data-drawing-surface' => 'typed'] + ($drawing ? ['class' => 'hide'] : []);
			$d->attributes = $drawing ? ['disabled' => 'disabled'] : [];
		});

		return $h;

	}

	public function getDrawingAreaDrawn(\util\FormUi $form, string $label, array $attributes = []): string {

		return $form->group(
			$label,
			'<b data-drawing-area-text="'.s("{value} m²", '{value}').'"></b>'.$form->hidden('area', attributes: ['disabled' => 'disabled']),
			['data-drawing-surface' => 'drawn', 'class' => 'hide'] + $attributes
		);

	}

	// Un contour se trace aussi en rectangle : la paire des formes se pose à côté de sa rangée, comme sur la carte du plan. Le coin droit
	// de la carte tient ses boutons, la rangée s'ancre donc à gauche et la paire vient en tête (`util-mapbars-start`)
	protected function getDrawingBlock(string $container, string $trigger, string $shape, array $tools): string {

		$h = '<div class="mapbox-polygon-container">';

			$h .= '<div id="'.$container.'" class="mapbox-polygon-map"></div>';

			$h .= '<div class="mapbox-polygon-actions">';
				$h .= '<div class="util-mapbars util-mapbars-start" data-drawing-tools>';
					$h .= '<div class="util-mapbar" data-drawing-gestures>';
						if($shape === 'polygon') {
							$h .= new PlanUi()->getMapShapeChoice();
						}
						$h .= new PlanUi()->getMapModes($shape);
						foreach($tools as $tool => [$icon, $label]) {
							$h .= '<a class="util-mapbar-item" data-tool="'.$tool.'">'.\Asset::icon($icon).'<span>'.$label.'</span></a>';
						}
					$h .= '</div>';
					if($shape === 'polygon') {
						$h .= new PlanUi()->getMapShapes();
					}
				$h .= '</div>';
			$h .= '</div>';

			$h .= $trigger;

		$h .= '</div>';

		return $h;

	}

	public function addBeds(Plot $ePlot): string {

		$h = '';

		foreach($ePlot['bedsDrawn'] as $bedDrawn) {
			$h .= '.addBed('.$ePlot['id'].', '.$bedDrawn['id'].', '.json_encode($bedDrawn['bed']).', '.json_encode($bedDrawn['corner']).', '.json_encode($bedDrawn['angle']).')';
		}

		return $h;

	}

}
?>
