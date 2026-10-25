<?php
namespace map;

class SceneUi {

	public function __construct() {

		// La fiche d'une série est celle du plan d'assolement : son interface pose ses feuilles et son script
		new \series\SeriesSheetUi();

		\Asset::css('map', 'scene.css');
		\Asset::js('scene', 'plan.js');
		\Asset::js('scene', 'crew.js');
		\Asset::js('map', 'scene.js');
		\Asset::js('map', 'sceneLayer.js');
		\Asset::js('scene', 'dropdown.js');
		\Asset::js('scene', 'speed.js');
		\Asset::js('map', 'sceneMore.js');
		\Asset::js('map', 'sceneRecord.js');
		\Asset::js('map', 'sceneDecor.js');
		\Asset::js('map', 'sceneDecorDefault.js');
		\Asset::js('map', 'sceneFauna.js');
		\Asset::js('map', 'sceneHedgehog.js');
		\Asset::js('map', 'sceneLimits.js');

	}

	/**
	 * Le plan de la saison descend entier, au format de `SceneLib::getByStructure()` : la projection,
	 * le rangement des planches et tout ce qui dépend de la date se calculent dans `MapScene`.
	 *
	 * `$zones` porte les zones tracées, id vers nom.
	 *
	 * `$bake` est ce que rend `\scene\BakeLib::getCatalogAndVersions()`, passé tel quel au conteneur
	 * du socle.
	 *
	 * `$eUser` est l'utilisateur connecté, à qui appartient ce que la carte des calques masque.
	 */
	public function get(\structure\Structure $eStructure, \user\User $eUser, int $season, array $scene, array $zones, array $bake, int $week, int $weeks): string {

		// Sans zone tracée, la scène n'a ni repère ni sol à poser
		if($scene['center'] === NULL) {

			$h = '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune parcelle n'est encore cartographiée").'</h3>';
				$h .= '<p>'.s("La vue en 3D se construit à partir des parcelles cartographiées.").'</p>';
				if($eStructure->canManage()) {
					$h .= '<div class="util-buttons">';
						$h .= '<a href="'.PlanUi::url($eStructure, $season, PlanUi::VIEW_MAP).'" class="btn btn-primary">'.s("Cartographier les parcelles").'</a>';
					$h .= '</div>';
				}
			$h .= '</div>';

			return $h;

		}

		$form = new \util\FormUi();

		$decor = $eStructure->canManage();

		// Le binaire d'un modèle se lit par la route qui contrôle les droits, comme pour la tête de l'accueil (`DashboardUi::getTeam()`)
		$scene['team'] = array_map(fn(?array $member) => ($member === NULL) ? NULL : [
			'fiche' => $member['fiche'],
			'upload' => $member['eUserAvatarUpload']->notEmpty() ? [
				'url' => \user\UserAvatarUploadUi::url($member['eUserAvatarUpload'], $eStructure),
				'index' => $member['eUserAvatarUpload']['bakedIndex']
			] : NULL
		], $scene['team']);

		$h = '<div id="map-scene" class="plan-mapview scene-frame" data-scene="'.encode(json_encode($scene, JSON_UNESCAPED_UNICODE)).'" data-error="'.encode(s("La vue en 3D n'a pas pu s'ouvrir. Rechargez la page pour réessayer.")).'" data-plants="'.encode(\Asset::getPath('plant', 'plants.svg', 'image')).'" data-diameter-label="'.encode(s("Ø {value}", ['value' => '{value}'])).'" onrender="'.encode('MapScene.start(this)').'">';

			$h .= \main\DesignUi::getLogoWait();

			$h .= new \scene\SceneUi()->getContainer('map-scene-canvas', $bake);

			$h .= '<div class="map-scene-bar">';

				// Une seule zone tracée : la proposer referait l'entrée qui montre toute la structure
				if(count($zones) > 1) {
					$h .= '<div class="map-scene-zone">';
						$h .= $form->select('zone', $zones, NULL, ['placeholder' => s("Toute la ferme"), 'data-scene-zone' => 'true']);
					$h .= '</div>';
				}

				// L'aspect du sélecteur voisin, pour former avec lui une seule rangée posée sur la scène
				$h .= '<div class="map-scene-screen">';
					$h .= '<button type="button" class="btn btn-form" data-scene-photo="true">'.\Asset::icon('camera-video').'<span class="hide-sm-down">'.s("Enregistrer").'</span></button>';
					$h .= new DecorLayerUi()->getButton();
					$h .= $this->getAvatarButton($eUser);
					if($decor) {
						$h .= '<button type="button" class="btn btn-form" data-scene-decor="true">'.\Asset::icon('tree-fill').'<span class="hide-sm-down">'.s("Décor").'</span></button>';
						// La fin de la pose prend la place des réglages de la lecture, que la pose cache
						$h .= '<button type="button" class="btn btn-form hide" data-scene-decor-cancel="true">'.s("Annuler").'</button>';
						$h .= '<button type="button" class="btn btn-primary hide" data-scene-decor-done="true">'.s("Terminer").'</button>';
					}
					$h .= '<button type="button" class="btn btn-form hide" data-scene-fullscreen="true">'.\Asset::icon('fullscreen').'<span class="hide-sm-down">'.s("Plein écran").'</span></button>';
					$h .= '<button type="button" class="btn btn-form hide" data-scene-fullscreen-exit="true">'.\Asset::icon('fullscreen-exit').'<span class="hide-sm-down">'.s("Quitter le plein écran").'</span></button>';
					$h .= $this->getMore($eUser, $decor);
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-card map-scene-timeline">';
				// La scène l'affiche quand elle pose le relief, que l'utilisateur peut éteindre
				if($scene['relief'] !== NULL) {
					$h .= '<div class="util-helper map-scene-attribution hide" data-scene-attribution="true">'.s("© IGN, RGE ALTI").'</div>';
				}
				$h .= '<div class="map-scene-timeline-week">';
					$h .= new \scene\SceneUi()->getPlay(TRUE);
					// La semaine choisie se lit dans la bulle qui suit le curseur, les mois sous la frise ; les deux s'écrivent dans MapScene
					$h .= '<div class="map-scene-timeline-track">';
						$h .= $form->range('week', 1, $weeks, 1, $week, ['data-scene-week' => 'true']);
						$h .= '<span class="map-scene-timeline-date" data-scene-date="true"></span>';
						$h .= '<div class="map-scene-timeline-months" data-scene-months="true"></div>';
					$h .= '</div>';
					$h .= '<div class="util-days map-scene-days" data-scene-days="true"></div>';
				$h .= '</div>';
				// La vitesse de lecture tient au bout de la clé ; sous 768 px, scene.css la remonte à droite de Lecture
				$h .= '<div class="map-scene-legend-row">';
					$h .= '<div class="util-legend map-scene-legend" data-scene-legend="true"></div>';
					$h .= new \scene\SceneUi()->getSpeed(['0.5' => s("Lente"), '1' => s("Normale"), '2' => s("Rapide")], '1');
				$h .= '</div>';
				$h .= $this->getScrollArrows('legend');
			$h .= '</div>';

			$h .= $this->getSheet();

			$h .= new DecorLayerUi()->getCard($eStructure, $eUser, $scene['relief'] !== NULL);

			$h .= '<div class="map-scene-photo-bar hide" data-scene-photo-bar="true">';
				$h .= '<button type="button" class="btn btn-primary" data-scene-photo-save="true">'.\Asset::icon('camera').'<span>'.s("Prendre une photo").'</span></button>';
				$h .= '<button type="button" class="btn btn-form" data-scene-record="true" data-hidden-error="'.encode(s("Le film s'est arrêté parce que l'onglet a été quitté. Restez sur l'onglet pendant tout le film.")).'" data-resized-error="'.encode(s("Le film s'est arrêté parce que la vue 3D a changé de taille. Gardez la même taille pendant tout le film.")).'">'.\Asset::icon('camera-video').'<span class="hide-sm-down">'.s("Filmer l'année").'</span></button>';
				$h .= '<button type="button" class="btn btn-form" data-scene-photo-close="true">'.s("Quitter").'</button>';
				$h .= '<div class="map-scene-record-progress hide" data-scene-record-progress="true">';
					$h .= \Asset::icon('record-circle');
					$h .= '<div class="util-progress"><div class="util-progress-part bg-production" style="width: 0%"></div></div>';
					$h .= '<span></span>';
				$h .= '</div>';
				$h .= '<button type="button" class="btn btn-form hide" data-scene-record-stop="true">'.\Asset::icon('stop-fill').'<span class="hide-sm-down">'.s("Arrêter").'</span></button>';
			$h .= '</div>';

			// La durée vient de MapSceneRecord, qui règle la vitesse du film sur elle
			$h .= '<div class="map-scene-record-countdown hide" data-scene-record-countdown="true">';
				$h .= '<strong data-scene-record-count="true"></strong>';
				$h .= '<p>'.s("Le film suivra votre année et vos mouvements de caméra et durera {seconds} secondes. Il sera téléchargé à la fin.", ['seconds' => '<span data-scene-record-seconds="true"></span>']).'</p>';
			$h .= '</div>';

			$h .= $this->getRuins();

			if($decor) {
				$h .= $this->getDecor($eStructure, $scene['buildings'], $scene['objects']);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'avatar se fait depuis la scène où il travaille. Le bouton montre son image quand elle est sur `User.vignette`,
	 * c'est-à-dire quand l'avatar remplace la photo, et sinon celui d'où part l'éditeur.
	 */
	protected function getAvatarButton(\user\User $eUser): string {

		return '<a href="/user/avatar" class="btn btn-form" data-scene-avatar="true">'.$this->getAvatarFace($eUser, 24).'<span class="hide-sm-down">'.s("Avatar").'</span></a>';

	}

	protected function getAvatarFace(\user\User $eUser, int $size): string {

		if($eUser['eUserAvatar']->isReplacingPhoto()) {
			return \user\UserUi::getVignette($eUser, $size.'px');
		} else {
			return \Asset::image('user', 'avatar/visage.png', ['width' => $size, 'height' => $size]);
		}

	}

	/**
	 * Le menu ⋮ en bout de rangée, où `MapSceneMore` range les boutons qui n'y tiennent plus. Chaque entrée porte l'icône et le
	 * libellé de son bouton, dans l'ordre de la rangée, et reste cachée tant que son bouton s'y trouve. Le plein écran n'a pas
	 * d'entrée : il ne quitte jamais la rangée.
	 */
	protected function getMore(\user\User $eUser, bool $decor): string {

		$h = '<a class="btn btn-form map-scene-folded dropdown-toggle" data-dropdown="bottom-end" data-scene-more="true" onrender="'.encode('MapSceneMore.start(this)').'">'.\Asset::icon('three-dots-vertical').'</a>';

		$h .= '<div class="dropdown-list dropdown-list-unstyled dropdown-list-light map-scene-more-list">';
			$h .= '<a class="dropdown-item hide" data-scene-more-item="photo">'.\Asset::icon('camera-video').s("Enregistrer").'</a>';
			$h .= '<a class="dropdown-item hide" data-scene-more-item="layers">'.\Asset::icon('layers').s("Calques").'</a>';
			$h .= '<a class="dropdown-item hide" data-scene-more-item="avatar">'.$this->getAvatarFace($eUser, 16).s("Avatar").'</a>';
			if($decor) {
				$h .= '<a class="dropdown-item hide" data-scene-more-item="decor">'.\Asset::icon('tree-fill').s("Décor").'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les flèches d'une rangée qui défile sans barre, que `MapSceneScroll` montre au bord où il en reste, sous un pointeur fin. Le
	 * clavier n'en a pas besoin : une entrée qui reçoit le focus amène la rangée jusqu'à elle.
	 */
	protected function getScrollArrows(string $row): string {

		$h = '<a class="map-scene-scroll hide" data-scene-scroll="'.$row.'" data-direction="-1">'.\Asset::icon('chevron-left').'</a>';
		$h .= '<a class="map-scene-scroll hide" data-scene-scroll="'.$row.'" data-direction="1">'.\Asset::icon('chevron-right').'</a>';

		return $h;

	}

	/**
	 * Les trois effets que propose l'appui long sur un élément de la scène, ouverts à tous ceux qui la voient. `MapSceneHold` les
	 * pose au point d'appui.
	 */
	protected function getRuins(): string {

		$h = '<div class="map-scene-decor-tools map-scene-ruins-tools hide" data-scene-ruins-tools="true">';
			$h .= '<a class="map-scene-decor-tool" data-ruin="burn"><span>'.\Asset::icon('fire').'</span><small>'.s("Feu").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-ruin="bomb"><span>'.\Asset::icon('asterisk').'</span><small>'.s("Bombe").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-ruin="quake"><span>'.\Asset::icon('activity').'</span><small>'.s("Séisme").'</small></a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La fiche d'une planche ou d'une série choisie sur la scène, cachée tant que rien n'est choisi.
	 * `MapSceneSheet` la remplit d'après le plan déjà descendu : elle ne lit rien de plus du serveur, et
	 * ses libellés partent ici, prêts à recevoir leurs valeurs.
	 */
	protected function getSheet(): string {

		// La partie série de la fiche est celle du plan d'assolement (`\series\SeriesSheetUi`), la planche, le jardin et la parcelle sont au plan 3D
		$texts = array_replace_recursive(\series\SeriesSheetUi::getTexts(), [
			'kind' => [
				'bed' => s("Planche"),
				'plot' => s("Jardin entier"),
				'zone' => s("Parcelle entière")
			],
			'place' => s("Emplacement"),
			'size' => s("Dimensions"),
			'sizeValue' => s("{length} × {width} m", ['length' => '{length}', 'width' => '{width}']),
			'seasonSeries' => s("Séries {season}", ['season' => '{season}']),
			'noSeries' => s("Aucune série"),
			'undated' => s("dates non saisies"),
			'weekRange' => s("{from} à {to}", ['from' => '{from}', 'to' => '{to}']),
			'since' => s("depuis {week}", ['week' => '{week}']),
			'until' => s("jusqu'à {week}", ['week' => '{week}']),
			'photo' => s("plan-de-culture-S{week}.png", ['week' => '{week}']),
			'video' => s("plan-de-culture-{season}", ['season' => '{season}'])
		]);

		$h = '<div class="util-card map-scene-sheet series-sheet hide" data-scene-sheet="true" data-texts="'.encode(json_encode($texts, JSON_UNESCAPED_UNICODE)).'" data-series-url="'.encode(\series\SeriesUi::url(new \series\Series(['id' => '{id}']))).'">';
			$h .= '<div class="series-sheet-head">';
				$h .= '<div>';
					$h .= '<div class="util-label" data-scene-sheet-kind="true"></div>';
					$h .= '<h3 data-scene-sheet-title="true"></h3>';
				$h .= '</div>';
				$h .= '<button type="button" class="btn btn-only-icon" data-scene-sheet-close="true">'.\Asset::icon('x-lg').'</button>';
			$h .= '</div>';
			$h .= '<div class="util-mores hide" data-scene-sheet-open="true"><a class="util-more util-more-lg">'.s("Voir la série").'</a></div>';
			$h .= '<div data-scene-sheet-body="true"></div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le catalogue du décor à la place de la frise, et les outils de l'élément choisi, cachés tant que
	 * la pose n'est pas ouverte. La famille d'un kit vit dans `MapSceneDecor.KITS`, qui en tire aussi
	 * ses règles de chevauchement : une vignette dont la scène ne sait pas monter le kit reste cachée.
	 */
	protected function getDecor(\structure\Structure $eStructure, array $buildings, array $objects): string {

		$h = self::getDecorSheet($eStructure);

			$h .= '<div class="map-scene-decor-families">';

				foreach(self::getDecorFamilies() as $family => $name) {
					$h .= '<a class="btn btn-form" data-family="'.$family.'">'.$name.'</a>';
				}

			$h .= '</div>';

			// Un calque ajouté n'a pas de familles, un calque masqué rien à poser : la feuille porte son nom à leur place
			$h .= '<div class="map-scene-decor-layer hide" data-scene-decor-layer="true">';
				$h .= '<strong data-scene-decor-layer-name="true"></strong>';
			$h .= '</div>';

			// Une parcelle ou un jardin choisi sur la scène ne propose que ce qui le clôt : son nom à la place des familles, les
			// clôtures et la haie à la place des vignettes. Un clic pose ce qu'il désigne, un second le retire ; la croix rend le
			// catalogue, comme celle de la fiche d'une planche la referme
			$h .= '<div class="map-scene-decor-area map-scene-decor-behind" data-scene-decor-area="true">';
				$h .= '<h4 data-scene-decor-area-name="true"></h4>';
				$h .= '<button type="button" class="btn btn-only-icon" data-scene-decor-area-close="true">'.\Asset::icon('x-lg').'</button>';
			$h .= '</div>';

			$h .= '<div class="map-scene-decor-tiles" data-scene-decor-kits="true">';

				$h .= '<p class="map-scene-decor-masked hide" data-scene-decor-masked="true">'.s("Ce calque est masqué, rien ne s'y pose.").'</p>';

				$kitsBuilding = self::getDecorBuildingKits();

				foreach($kitsBuilding as $kit => $name) {
					$h .= $this->getDecorTile($kit, $name);
				}

				// La rangée Bâtiments finit sur ceux de la structure, composés ou importés, puis sur la tuile qui en crée un. Un bâtiment
				// enregistré depuis l'éditeur entre dans la rangée par le gabarit, sans que la scène se recharge (`MapSceneDecor.saved()`)
				foreach($buildings as $building) {
					$h .= $this->getDecorBuilding($building['id'], encode($building['name']));
				}

				$h .= '<template data-scene-decor-building="true">'.$this->getDecorBuilding(NULL, '').'</template>';

				$h .= '<a href="/map/decorBuilding:create?structure='.$eStructure['id'].'" class="map-scene-decor-tile map-scene-decor-tile-create hide" data-scene-decor-create="true">';
					$h .= '<span class="map-scene-decor-tile-icon">'.\Asset::icon('plus-circle').'</span>';
					$h .= '<span>'.s("Créer").'</span>';
				$h .= '</a>';

				foreach(array_diff_key(self::getDecorKits($eStructure), $kitsBuilding) as $kit => $name) {
					$h .= $this->getDecorTile($kit, $name);
				}

				// Les rangées Eau, Nature et Matériel finissent sur les éléments importés par la structure, puis sur la tuile qui en importe
				// un : la feuille ne montre que ceux de la rangée ouverte. Un élément importé depuis le panneau y entre par le gabarit
				// (`MapSceneDecor.objectSaved()`). L'import suit le drapeau des modèles téléversés, un élément en place se montre sans lui
				foreach($objects as $object) {
					$h .= $this->getDecorObject($object['id'], $object['family'], encode($object['name']));
				}

				$h .= '<template data-scene-decor-object="true">'.$this->getDecorObject(NULL, NULL, '').'</template>';

				if(MapSetting::isDecorUploadEnabled($eStructure)) {

					foreach(DecorObject::KITS as $family => $kit) {
						$h .= '<a href="/map/decorObject:create?structure='.$eStructure['id'].'&family='.$family.'" class="map-scene-decor-tile map-scene-decor-tile-create hide" data-scene-decor-import="'.$family.'">';
							$h .= '<span class="map-scene-decor-tile-icon">'.\Asset::icon('plus-circle').'</span>';
							$h .= '<span>'.s("Importer").'</span>';
						$h .= '</a>';
					}

				}

				// Le terrain se choisit d'un clic, sans rien poser : sa vignette se peint d'après les couleurs de la scène
				$h .= $this->getTerrainTiles(['grass', 'soil', 'meadow'], 'data-terrain', FALSE);

			$h .= '</div>';

			$h .= $this->getDecorPipe();
			$h .= $this->getDecorBuildingBand();

			$h .= '<div class="map-scene-decor-tiles map-scene-decor-fences hide" data-scene-decor-enclosure="true">';

				// La haie en tête : elle se cumule à toute clôture, et sur un téléphone, où la rangée défile, la dernière vignette
				// sort de la feuille
				$h .= '<button type="button" class="map-scene-decor-tile" data-hedge="true">';
					$h .= '<img alt="" draggable="false">';
					$h .= '<span>'.DecorUi::p('hedge')->label.'</span>';
				$h .= '</button>';

				foreach(DecorUi::p('model')->values as $model => $name) {
					$h .= '<button type="button" class="map-scene-decor-tile" data-fence="'.$model.'">';
						$h .= '<img alt="" draggable="false">';
						$h .= '<span>'.$name.'</span>';
					$h .= '</button>';
				}

				// Le terrain d'une parcelle, après ses clôtures
				$h .= $this->getTerrainTiles(['grass', 'soil', 'meadow'], 'data-area-terrain', TRUE);

			$h .= '</div>';

			// Les rangées défilent sans barre : à la souris, deux flèches se posent à leurs bords (MapSceneScroll)
			foreach(['families', 'kits', 'diameters', 'enclosure'] as $row) {
				$h .= $this->getScrollArrows($row);
			}

			// Sur un téléphone, la rangée du haut quitte l'écran quand on descend jusqu'à la feuille : le tracé s'y termine, à la
			// place du « Terminer » de la rangée du haut (scene.css)
			$h .= '<a class="btn btn-primary map-scene-decor-finish" data-scene-decor-finish="true">'.s("Terminer").'</a>';

		$h .= '</div>';

		$h .= '<div class="map-scene-decor-tools hide" data-scene-decor-tools="true">';
			$h .= '<a class="map-scene-decor-tool" data-tool="rotate"><span>'.\Asset::icon('arrow-clockwise').'</span><small>'.s("Tourner").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-tool="size"><span>'.\Asset::icon('arrows-angle-expand').'</span><small>'.s("Taille").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-tool="wide"><span>'.\Asset::icon('arrow-left-right').'</span><small>'.s("Largeur").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-tool="model"><span>'.\Asset::icon('bricks').'</span><small>'.s("Modèle").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-tool="add"><span>'.\Asset::icon('plus-lg').'</span><small>'.s("Continuer").'</small></a>';
			// Le lien de l'éditeur suit l'élément choisi : un hangar du catalogue se personnalise, un bâtiment composé se reprend
			$h .= '<a class="map-scene-decor-tool" data-tool="edit"><span>'.\Asset::icon('pencil').'</span><small>'.s("Modifier").'</small></a>';
			$h .= '<a class="map-scene-decor-tool" data-tool="remove"><span>'.\Asset::icon('trash').'</span><small>'.s("Retirer").'</small></a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les groupes de vignettes du terrain, une par valeur de `$terrains`, chacun marqué par l'attribut `$attribute` qui dit à la scène
	 * où il se montre. `$inherit` ajoute en tête de chaque groupe la vignette d'une parcelle qui reprend le terrain de la structure.
	 */
	protected function getTerrainTiles(array $terrains, string $attribute, bool $inherit): string {

		$properties = ['grass' => 'decorGrass', 'soil' => 'decorSoil', 'meadow' => 'decorMeadow'];

		$h = '';

		foreach($terrains as $terrain) {

			$d = ConfigurationUi::p($properties[$terrain]);
			$values = ($inherit ? ['' => s("Comme la ferme")] : []) + $d->values;

			$h .= '<div class="map-scene-decor-group hide" '.$attribute.'="'.$terrain.'">';
				$h .= '<h5>'.$d->label.'</h5>';

				foreach($values as $value => $name) {
					$h .= '<button type="button" class="map-scene-decor-tile" data-'.$terrain.'="'.$value.'">';
						$h .= '<img alt="" draggable="false">';
						$h .= '<span>'.$name.'</span>';
					$h .= '</button>';
				}

			$h .= '</div>';

		}

		return $h;

	}

	protected function getDecorTile(string $kit, string $name): string {

		$h = '<button type="button" class="map-scene-decor-tile hide" data-kit="'.$kit.'">';
			$h .= '<img alt="" draggable="false">';
			$h .= '<span>'.$name.'</span>';
		$h .= '</button>';

		return $h;

	}

	/**
	 * Un bâtiment de la structure se pose sous le kit `batiment`, qui cite son id. Sa poubelle de coin le supprime, et ses poses
	 * avec lui : un lien ne se pose pas dans un bouton, d'où le conteneur, que la feuille cache avec la vignette (scene.css).
	 * `MapSceneDecor` choisit laquelle des deux confirmations poser, selon qu'une pose le cite, et y met son nom.
	 */
	protected function getDecorBuilding(?int $id, string $name): string {

		$h = '<div class="map-scene-decor-tile-own">';

			$h .= '<button type="button" class="map-scene-decor-tile hide" data-kit="batiment" data-building="'.$id.'">';
				$h .= '<img alt="" draggable="false">';
				$h .= '<span>'.$name.'</span>';
			$h .= '</button>';

			$h .= '<a class="map-scene-decor-tile-delete" data-ajax="/map/decorBuilding:doDelete" post-id="'.$id.'" data-confirm-free="'.encode(s("Supprimer « {value} » ?", '{value}')).'" data-confirm-posed="'.encode(s("Supprimer « {value} » ? Il sera aussi retiré du plan de la ferme.", '{value}')).'">'.\Asset::icon('trash').'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un élément importé se pose sous le kit de sa rangée (`DecorObject::KITS`), qui cite son id, et se supprime comme un bâtiment
	 * de la structure, ses poses avec lui. Le gabarit n'a pas de rangée : `MapSceneDecor.objectSaved()` lui donne le kit.
	 */
	protected function getDecorObject(?int $id, ?string $family, string $name): string {

		$kit = ($family === NULL) ? '' : DecorObject::KITS[$family];

		$h = '<div class="map-scene-decor-tile-own">';

			$h .= '<button type="button" class="map-scene-decor-tile hide" data-kit="'.$kit.'" data-object="'.$id.'">';
				$h .= '<img alt="" draggable="false">';
				$h .= '<span>'.$name.'</span>';
			$h .= '</button>';

			$h .= '<a class="map-scene-decor-tile-delete" data-ajax="/map/decorObject:doDelete" post-id="'.$id.'" data-confirm-free="'.encode(s("Supprimer « {value} » ?", '{value}')).'" data-confirm-posed="'.encode(s("Supprimer « {value} » ? Il sera aussi retiré du plan de la ferme.", '{value}')).'">'.\Asset::icon('trash').'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le diamètre et la pose du tuyau qu'on a en main ou qu'on a choisi : un diamètre normalisé des
	 * tubes PE, ou « Autre » qui en saisit un en mm.
	 */
	protected function getDecorPipe(): string {

		$form = new \util\FormUi();

		$h = '<div class="map-scene-decor-pipe hide" data-scene-decor-pipe="true">';

			$h .= '<div class="map-scene-decor-pipe-group">';
				$h .= '<h5>'.DecorUi::p('diameter')->label.'</h5>';

				$h .= '<div class="map-scene-decor-pipe-values" data-scene-decor-diameters="true">';

					foreach(MapSetting::DECOR_PIPE_SIZES as $diameter) {
						$h .= '<a class="btn btn-form" data-diameter="'.$diameter.'"><span class="util-unit">'.s("{value} mm", $diameter).'</span></a>';
					}

					$h .= '<a class="btn btn-form" data-diameter="">'.s("Autre").'</a>';
					$h .= '<div class="map-scene-decor-pipe-other hide" data-scene-decor-pipe-other="true">';
						$h .= $form->dynamicField(new Decor(['diameter' => NULL]), 'diameter');
					$h .= '</div>';

				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="map-scene-decor-pipe-group">';
				$h .= '<h5>'.DecorUi::p('laying')->label.'</h5>';

				$h .= '<div class="map-scene-decor-pipe-values">';

					foreach(DecorUi::p('laying')->values as $laying => $name) {
						$h .= '<a class="btn btn-form" data-laying="'.$laying.'">'.$name.'</a>';
					}

				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bâtiment de la structure qu'on a en main, dans la bande du tuyau : son nom, et « Modifier », qui le reprend dans
	 * l'éditeur. `MapSceneDecor.showBuilding()` y pose le bâtiment armé, et cache « Modifier » pour un bâtiment importé, qui ne
	 * se modifie pas (`DecorBuilding::acceptUpdate()`).
	 */
	protected function getDecorBuildingBand(): string {

		$h = '<div class="map-scene-decor-pipe hide" data-scene-decor-building-band="true">';

			$h .= '<div class="map-scene-decor-pipe-group">';
				$h .= '<h5 data-scene-decor-building-name="true"></h5>';

				$h .= '<div class="map-scene-decor-pipe-values">';
					$h .= '<a class="btn btn-form" data-scene-decor-building-edit="true">'.s("Modifier").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La feuille du décor, qui porte ce que la scène lit pour juger une pose comme le serveur la jugera : les mots de ses refus,
	 * le nombre de points d'un tracé et le plafond du décor.
	 */
	protected static function getDecorSheet(\structure\Structure $eStructure): string {
		return '<div class="util-card map-scene-decor hide" data-scene-decor-sheet="true" data-structure="'.$eStructure['id'].'" data-refusals="'.encode(json_encode(self::getDecorRefusals(), JSON_UNESCAPED_UNICODE)).'" data-models="'.encode(json_encode(DecorUi::p('model')->values, JSON_UNESCAPED_UNICODE)).'" data-measure="'.encode(s("{width} × {depth} m", ['width' => '{width}', 'depth' => '{depth}'])).'" data-length="'.encode(s("{value} m", ['value' => '{value}'])).'" data-trace-points="'.MapSetting::DECOR_TRACE_POINTS.'" data-trace-limit="'.encode(json_encode(self::getDecorTraceLimit())).'" data-wide="'.encode(s("{value} m de large", ['value' => '{value}'])).'" data-layer="'.encode(s("Dans le calque {value}", ['value' => '{value}'])).'" data-adopted="'.encode(AlertUi::getError('Decor::structure.adopted')).'" data-unadopted="'.encode(AlertUi::getError('Decor::structure.unadopted')).'" data-unrestored="'.encode(s("Le décor par défaut n'a pas pu être rétabli. Rechargez la page pour retrouver celui qui est enregistré.")).'">';
	}

	/**
	 * Ce que l'étiquette dit d'une pose refusée, sous la raison que `MapSceneDecor` rend : que le geste est refusé, et ce qui
	 * l'empêche. Le trait blanc entre deux groupes de parcelles est à l'écran dès qu'un tracé est en cours ou choisi, seuls gestes
	 * qui butent dessus. `{value}` est la longueur que le kit ne dépasse pas, ou le nombre de points que le serveur enregistre au plus.
	 * Le plafond du décor dit les mots du serveur, qui le juge aussi : l'écran refuse avant lui, et dans les mêmes termes.
	 */
	protected static function getDecorRefusals(): array {

		return [
			'outside' => s("Impossible de poser si loin de vos parcelles"),
			'apart' => s("Impossible de traverser le trait blanc"),
			'bed' => s("Impossible de poser sur une planche ou une serre"),
			'element' => s("Impossible de poser sur un autre élément"),
			'long' => s("Impossible de tracer plus de {value} m", ['value' => '{value}']),
			'points' => s("Impossible de poser plus de {value} points", ['value' => '{value}']),
			'limit' => AlertUi::getError('Decor::trace.limit'),
			'short' => s("Impossible de tracer si court")
		];

	}

	/**
	 * Ce que `DecorLib::getTraceCost()` compte d'un élément sous `MapSetting::DECOR_TRACE_LENGTH`, pour que l'écran refuse au
	 * tracé ce que le serveur refuserait à l'enregistrement (`MapSceneDecor.cost()`).
	 */
	protected static function getDecorTraceLimit(): array {

		return [
			'length' => MapSetting::DECOR_TRACE_LENGTH,
			'corner' => DecorLib::CORNER,
			'weights' => DecorLib::TRACE_WEIGHT,
			'starts' => DecorLib::TRACE_START,
			'hedgeWeight' => DecorLib::HEDGE_WEIGHT,
			'hedgeStart' => DecorLib::HEDGE_START,
			'irrigation' => DecorLayer::IRRIGATION
		];

	}

	protected static function getDecorFamilies(): array {

		return [
			'building' => s("Bâtiments"),
			'water' => s("Eau"),
			'tree' => s("Nature"),
			'fence' => s("Circulation"),
			'other' => s("Matériel"),
			'terrain' => s("Terrain")
		];

	}

	// Les kits de la rangée Bâtiments, que suivent les bâtiments de la structure
	protected static function getDecorBuildingKits(): array {

		return [
			'hangar' => s("Hangar"),
			'bureau' => s("Bureau"),
			'abri' => s("Abri"),
			'stabulation' => s("Stabulation"),
			'bergerie' => s("Bergerie"),
			'fenil' => s("Fenil"),
			'hangar-solaire' => s("Hangar solaire"),
			'laverie' => s("Laverie"),
			'chambre-froide' => s("Chambre froide"),
			'magasin' => s("Magasin"),
			'poulailler-mobile' => s("Poulailler mobile"),
			'sechoir' => s("Séchoir"),
		];

	}

	/**
	 * Les kits du catalogue, sous la clé que `map\Decor.kit` enregistre, dans l'ordre du catalogue. Ceux
	 * de l'irrigation ne sont proposés que sous son drapeau.
	 */
	protected static function getDecorKits(\structure\Structure $eStructure): array {

		$kits = self::getDecorBuildingKits() + [
			'mare' => s("Mare"),
			'bassin-bache' => s("Bassin bâché"),
			'cuve' => s("Cuve"),
			'puits' => s("Puits"),
			'cours-eau' => s("Cours d'eau"),
			'bande-fleurie' => s("Bande fleurie"),
			'arbre' => s("Arbre"),
			'fruitier' => s("Arbre fruitier"),
			'bosquet' => s("Bosquet"),
			'verger' => s("Verger"),
			'buissons' => s("Buissons"),
			'alignement' => s("Alignement d'arbres"),
			'haie' => s("Haie"),
			'chene' => s("Chêne isolé"),
			'saule' => s("Saule têtard"),
			'bouleau' => s("Bouleau"),
			'arbre-mort' => s("Arbre mort"),
			'souche' => s("Souche"),
			'roncier' => s("Roncier"),
			'fougeres' => s("Fougères"),
			'roseaux' => s("Roseaux"),
			'rocher' => s("Rocher"),
			'pierrier' => s("Pierrier"),
			'cloture' => s("Clôture"),
			'portail' => s("Portail"),
			'chemin' => s("Chemin"),
			'route' => s("Route"),
			'panneau' => s("Panneau"),
			'compost' => s("Compost"),
			'caisses' => s("Caisses"),
			'tracteur' => s("Tracteur"),
			'utilitaire' => s("Utilitaire"),
			'pick-up' => s("Pick-up"),
			'remorque' => s("Remorque"),
			'pompe' => s("Pompe"),
			'vanne' => s("Vanne manuelle"),
			'electrovanne' => s("Électrovanne"),
			'tuyau' => s("Tuyau"),
			'rampe' => s("Rampe d'aspersion")
		];

		if(MapSetting::isIrrigationEnabled($eStructure) === FALSE) {
			$kits = array_diff_key($kits, array_flip(MapSetting::DECOR_IRRIGATION_KITS));
		}

		return $kits;

	}

}
?>
