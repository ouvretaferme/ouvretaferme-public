<?php
namespace map;

/**
 * Le troisième niveau de la vue Carte : les planches d'un jardin, à cocher pour les dessiner sur la carte.
 * Il hérite de PlanUi pour nommer un jardin, ses planches et leur format comme les deux niveaux du dessus.
 */
class DrawUi extends PlanUi {

	// Le formulaire de la liste, où le geste lit les planches cochées
	const string FORM = 'plan-console-beds';

	// Le nœud du niveau dans le bloc, et la fiche du geste sur la carte, que la vue pose vides et que plot:drawing remplit
	const string LEVEL = 'plan-console-drawing';
	const string CARD = 'plan-console-card';

	// L'adresse du jardin aux planches ouvertes, qu'un rechargement rouvre sur elles (`beds`)
	public static function urlPlot(\structure\Structure $eStructure, int $season, Plot $ePlot, array $ids = []): string {

		$url = self::url($eStructure, $season, self::VIEW_MAP).'?zone='.$ePlot['zone']['id'].'&plot='.$ePlot['id'].'&beds=1';

		foreach($ids as $id) {
			$url .= '&ids[]='.(int)$id;
		}

		return $url;

	}

	/**
	 * Le niveau d'un jardin dans le bloc de la vue Carte. Un geste enregistré le remplace, avec la fiche de la carte
	 * (getCard()). Vide, il attend le clic sur un jardin.
	 */
	public function getLevel(\structure\Structure $eStructure, int $season, Zone $eZone, Plot $ePlot): string {

		if($ePlot->empty()) {
			return '<div class="plan-console-level hide" data-level="plot" id="'.self::LEVEL.'"></div>';
		}

		$cBed = $ePlot['cBed']->find(fn($eBed) => $eBed['plotFill'] === FALSE);

		$h = '<div class="plan-console-level" data-level="plot" data-zone="'.$eZone['id'].'" data-plot="'.$ePlot['id'].'" data-form="'.self::FORM.'" data-url="'.self::urlPlot($eStructure, $season, $ePlot).'" id="'.self::LEVEL.'">';
			$h .= $this->getPlotHead($eZone, $ePlot, $cBed);
			$h .= $this->getBeds($ePlot, $cBed, $season);
		$h .= '</div>';

		return $h;

	}

	protected function getPlotHead(Zone $eZone, Plot $ePlot, \Collection $cBed): string {

		$up = $this->getMapUp(attr('onclick', 'MapCartography.get("'.self::MAP.'").clickZone('.$eZone['id'].')'));

		// Des planches posées à même la parcelle en ont la surface
		$parts = [$ePlot['zoneFill'] ? $eZone->getArea() : $ePlot->getArea(), $cBed->empty() ? s("aucune planche") : p("{value} planche", "{value} planches", $cBed->count())];

		// Une mesure passe à la ligne entière, et sans le point qui la sépare de la précédente
		$measures = implode("\u{00A0}· ", array_map(fn(string $part) => '<span>'.$part.'</span>', $parts));

		return $this->getMapLevelHead($this->getMapPlotName($eZone, $ePlot), $measures, $up, $this->getPlotTag($ePlot));

	}

	/**
	 * Ce qui reste à tracer en tête, ce qui est tracé dessous, puis l'ajout de planches. Le geste porte sur ce qui est coché :
	 * rien d'office, seulement les planches que l'adresse désigne (`cBedSelected`). Un jardin sans contour liste ses planches
	 * sans case à cocher, faute de forme où les dessiner (`drawable`).
	 *
	 * Le formulaire porte aussi de quoi poster « Effacer le dessin » depuis la fiche de la carte.
	 */
	protected function getBeds(Plot $ePlot, \Collection $cBed, int $season): string {

		$cBedUndrawn = $ePlot['cBedUndrawn'];
		$cBedDrawn = $cBed->find(fn($eBed) => $eBed['drawn']);
		$cBedSelected = $ePlot['cBedSelected'];
		$drawable = $ePlot['drawable'];

		$form = new \util\FormUi();

		$h = $form->open(self::FORM, ['class' => 'plan-console-beds']);

			$h .= $form->hidden('plot', $ePlot);
			$h .= $form->hidden('season', $season);
			$h .= $form->hidden('origin', self::VIEW_MAP);

			if($cBedUndrawn->notEmpty()) {

				$h .= $this->getBedsAll($form, $cBedUndrawn, 'undrawn', s("Planches à dessiner"), $drawable);
				$h .= $this->getBedLines($form, $cBedUndrawn, $cBedSelected, $drawable);

			}

			if($cBedDrawn->notEmpty()) {

				// Sans planche à dessiner, il n'y a rien à distinguer
				$h .= $this->getBedsAll($form, $cBedDrawn, 'drawn', $cBedUndrawn->empty() ? s("Planches") : s("Planches dessinées"), $drawable);
				$h .= $this->getBedLines($form, $cBedDrawn, $cBedSelected, $drawable);

			}

			$h .= '<div class="plan-console-beds-add">';
				$h .= '<a href="/map/bed:create?plot='.$ePlot['id'].'&season='.$season.'&origin='.self::VIEW_MAP.'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('plus').' '.s("Ajouter des planches").'</a>';
			$h .= '</div>';

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le titre de chaque section porte la case qui coche ou décoche toutes ses planches (MapPlan.changeConsoleAll()), sans
	 * toucher à l'autre section. Elle se coche d'après les planches cochées, que MapPlan.refreshConsoleCard() relit à chaque
	 * ouverture du jardin et à chaque case.
	 */
	protected function getBedsAll(\util\FormUi $form, \Collection $cBed, string $section, string $label, bool $drawable): string {

		$sized = $cBed->contains(fn($eBed) => $eBed['length'] !== NULL and $eBed['width'] !== NULL);

		return $form->checkbox(NULL, '1', [
			'disabled' => ($drawable and $sized) ? NULL : 'disabled',
			'data-console-all' => $section,
			'callbackLabel' => fn(string $input) => $input.$label.' <span class="util-counter">'.$cBed->count().'</span>',
			'callbackFieldAttributes' => ['class' => 'plan-column-title plan-console-all']
		]);

	}

	protected function getBedLines(\util\FormUi $form, \Collection $cBed, \Collection $cBedSelected, bool $drawable): string {

		$h = '';

		foreach($cBed as $eBed) {

			// MapDrawingLine lit sur la case les dimensions dont il fait le rang. Une planche sans dimensions n'a pas de rectangle à poser
			$sized = ($drawable and $eBed['length'] !== NULL and $eBed['width'] !== NULL);

			$h .= $form->checkbox('ids[]', $eBed['id'], [
				'checked' => ($sized and $cBedSelected->offsetExists($eBed['id'])),
				'disabled' => $sized ? NULL : 'disabled',
				'data-name' => $eBed['name'],
				'data-length' => $eBed['length'],
				'data-width' => $eBed['width'],
				// Absent quand le plan ne le donne pas : la cote du bord tiré se montre alors sans aimant
				'data-alley' => $eBed['alleyWidth'],
				'data-section' => $eBed['drawn'] ? 'drawn' : 'undrawn',
				// Sa place, d'où MapDrawingLine rouvre une sélection de planches toutes tracées
				'data-corner' => ($eBed['drawCorner'] === NULL) ? NULL : json_encode($eBed['drawCorner']),
				'data-angle' => ($eBed['drawAngle'] === NULL) ? NULL : json_encode($eBed['drawAngle']),
				'callbackLabel' => fn(string $input) => $input.'<b>'.encode($eBed['name']).'</b><span class="color-muted">'.$this->getGroupLabel($eBed).'</span>',
				'callbackFieldAttributes' => ['class' => 'plan-console-bed']
			]);

		}

		return $h;

	}

	/**
	 * Les actions du jardin sur la carte, que MapPlan.refreshActions() pose au-dessus du contour qui reçoit ses planches
	 * (`data-anchor`). Elles ont deux faces, d'après ce qui est coché, et chacune nomme ce qu'elle désigne : sans planche
	 * cochée, le jardin et sa roue ; avec des planches cochées, leurs noms, le geste qui les dessine et la roue de ce qui
	 * se fait sur elles. MapPlan.refreshConsoleCard() montre la face de ce qui est coché et en réécrit les noms et le bouton.
	 *
	 * Le geste pose un rang neuf des planches cochées, qui les retire de leur place en s'enregistrant. MapDrawing y lit le
	 * contour, le trait d'où MapDrawingLine déduit où poser le rang (`lineNext`), et la route qui l'enregistre.
	 */
	public function getCard(\structure\Structure $eStructure, int $season, Zone $eZone, Plot $ePlot): string {

		if($ePlot->empty()) {
			return '<div class="util-card plan-mapview-actions hide" id="'.self::CARD.'"></div>';
		}

		$contour = json_encode($ePlot['zoneFill'] ? $eZone['coordinates'] : $ePlot['coordinates']);

		// Un jardin sans contour pose ses actions sur celui de sa parcelle, comme désigné (getMapPlotActions()), et sans l'un ni
		// l'autre n'en pose aucune : MapPlan.refreshActions() ne place qu'une fiche ancrée
		$anchor = $ePlot['coordinates'] ?? $eZone['coordinates'];

		// Les cases cochées d'office, que getBedLines() coche aussi : une planche sans dimensions ne se coche pas
		$cBedChecked = $ePlot['cBedSelected']->find(fn($eBed) => $eBed['length'] !== NULL and $eBed['width'] !== NULL);

		$labels = [
			'drawOne' => s("Dessiner la planche"),
			'drawMany' => s("Dessiner {value} planches", '{count}')
		];

		$hide = fn(bool $hidden) => $hidden ? ' hide' : '';

		$h = '<div class="util-card plan-mapview-actions" id="'.self::CARD.'" data-plot="'.$ePlot['id'].'"'.($anchor === NULL ? '' : ' '.attr('data-anchor', json_encode($anchor))).' '.attr('data-labels', json_encode($labels)).'>';

			$h .= '<span class="plan-mapview-actions-face'.$hide($cBedChecked->notEmpty()).'" data-console-face="plot">';

				// Des planches posées à même la parcelle sont celles de la parcelle : leurs gestes sont les siens
				if($ePlot['zoneFill']) {
					$h .= $this->getMapZoneGestures($eStructure, $season, $eZone, TRUE);
				} else {
					$h .= $this->getMapPlotGestures($season, $eZone, $ePlot, TRUE);
				}

			$h .= '</span>';

			$h .= '<span class="plan-mapview-actions-face'.$hide($cBedChecked->empty()).'" data-console-face="beds">';

				$h .= '<div class="util-mapbar util-mapbar-labels">';

				$h .= '<b class="util-mapbar-name" data-console-names>'.$this->getCheckedNames($cBedChecked).'</b>';

				$h .= '<a data-drawing="line" data-drawing-map="'.self::MAP.'" data-drawing-url="/map/bed:doUpdateBedLineCollection" data-drawing-target="beds" data-drawing-form="'.self::FORM.'"'.
					' data-drawing-id="'.$ePlot['id'].'" data-drawing-name="'.$this->getMapPlotName($eZone, $ePlot).'"'.
					' '.attr('data-drawing-contour', $contour).' '.attr('data-drawing-anchor', $contour).
					' data-drawing-coordinates="null" '.attr('data-drawing-last', json_encode($ePlot['lineNext'])).
					$this->getMapDrawingError('Draw::corner.placed').
					' class="util-mapbar-item">'.\Asset::icon('pencil').'<span data-console-label>'.$this->getDrawCheckedLabel($cBedChecked->count()).'</span></a>';

				$h .= $this->getBedsMenu($season, $ePlot, $cBedChecked);

				$h .= '</div>';

			$h .= '</span>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La roue des planches cochées : elles se modifient dans un panneau, quel que soit leur trait. MapPlan.refreshConsoleCard()
	 * écrit les planches cochées dans le lien qui l'ouvre. Les écritures postent la liste.
	 */
	protected function getBedsMenu(int $season, Plot $ePlot, \Collection $cBedChecked): string {

		// Effacer le dessin demande une planche dessinée, le recommencer deux, qui font un rang (MapPlan.refreshConsoleCard() suit les cases)
		$drawn = $cBedChecked->find(fn($eBed) => $eBed['drawn'])->count();

		$edit = function(string $page, string $label) use($season, $ePlot, $cBedChecked) {

			$url = '/map/bed:'.$page.'?plot='.$ePlot['id'].'&season='.$season.'&origin='.self::VIEW_MAP;
			$ids = implode('', array_map(fn(int $id) => '&ids[]='.$id, $cBedChecked->getIds()));

			return '<a href="'.$url.$ids.'" '.attr('data-console-url', $url).' class="dropdown-item">'.$label.'</a>';

		};

		$h = '<a data-dropdown="bottom-end" class="util-mapbar-item color-primary">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list bg-primary">';
			$h .= $edit('updateSizeCollection', s("Modifier les dimensions"));
			$h .= $edit('updateSeasonCollection', s("Modifier les saisons"));
			$h .= '<a data-ajax-submit="/map/bed:doUpdateBedLineSpacing" data-ajax-target="#'.self::FORM.'" data-ajax-method="post" class="dropdown-item'.(($drawn >= 2) ? '' : ' hide').'" data-console-drawn="2">'.s("Recommencer le dessin").'</a>';
			$h .= '<a data-ajax-submit="/map/bed:doDeleteBedLineCollection" data-ajax-target="#'.self::FORM.'" data-ajax-method="post" data-confirm="'.s("Le dessin des planches sélectionnées sera effacé de la carte, voulez-vous continuer ?").'" class="dropdown-item'.(($drawn >= 1) ? '' : ' hide').'" data-console-drawn="1">'.s("Effacer le dessin").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax-submit="/map/bed:doDeleteCollection" data-ajax-target="#'.self::FORM.'" data-ajax-method="post" data-confirm="'.s("Êtes-vous sûr de vouloir supprimer les planches sélectionnées ? Si vous souhaitez conserver un historique de votre plan de ferme, modifiez plutôt les saisons d'exploitation.").'" class="dropdown-item">'.s("Supprimer les planches").'</a>';
		$h .= '</div>';

		return $h;

	}

	// Les mêmes phrases que MapPlan.refreshConsoleCard() à chaque case
	protected function getDrawCheckedLabel(int $count): string {

		return match($count) {
			0, 1 => s("Dessiner la planche"),
			default => s("Dessiner {value} planches", $count)
		};

	}

	// Trois noms au plus, comme MapPlan.getConsoleNames() : le bouton voisin dit déjà combien de planches sont cochées
	protected function getCheckedNames(\Collection $cBed): string {

		$names = array_map(fn(string $name) => encode($name), array_values($cBed->getColumn('name')));

		return implode(', ', array_slice($names, 0, 3)).(count($names) > 3 ? '…' : '');

	}

	/**
	 * Ce que le mode de tracé (MapDrawing, drawing.js) prend dans la vue Carte pour un rang : la fiche du geste, qui
	 * prend la place des actions du jardin le temps d'un rang, et la rangée d'outils qu'il pose en haut de la carte.
	 */
	public function getMapDrawingLine(): string {

		// MapDrawingLine écrit le nombre de planches du rang à mesure qu'on coche
		$attributes = 'data-beds-one="'.s("{value} planche", '{value}').'" data-beds-many="'.s("{value} planches", '{value}').'"';
		// La cote de l'allée voisine du bord qu'on tire
		$attributes .= ' data-alley="'.s("{value} cm", '{value}').'"';

		$h = $this->getMapDrawingActions('line', 'coordinates ids season', $attributes);

		$h .= '<div class="hide" data-drawing-kit="line">';
			$h .= '<div class="util-mapbar plan-mapview-bar" data-drawing-tools>';
				$h .= $this->getMapModes('line');
				$h .= $this->getMapTool('flip', 'symmetry-horizontal', s("Inverser"));
				$h .= $this->getMapFinish();
				$h .= $this->getMapCancel();
			$h .= '</div>';
			$h .= $this->getMapUnpoint();
		$h .= '</div>';

		return $h;

	}

}
?>
