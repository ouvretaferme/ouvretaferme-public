<?php
namespace planning;

/**
 * La carte d'une intervention, piste A de la planche des cartes retenue (maquette navigable,
 * `carteA()`) : la vignette de l'espèce dans la marge, le nom en tête, le lieu et la note dessous, et en
 * pied le jour et le prévu. À droite, le rond qui la coche (`getMark()`), vide pour une intervention à faire,
 * coché pour une faite, et devant lui, sur la ligne du nom, les personnes et le temps de la semaine (`getTop()`).
 *
 * Le clic sur la carte ouvre la page de l'intervention du nouveau planning (`TaskDetailUi`, L5b), qui
 * garde la semaine pour remettre la carte à jour quand un temps y change.
 *
 * La carte se rend seule à partir de l'intervention et des membres, et de rien d'autre : c'est elle que
 * la réponse d'un geste renvoie, et elle doit être octet pour octet celle de la page
 * (`FingerprintTest`). La bulle du temps passé (`getBubble()`) se pose juste après elle.
 *
 * La case de sélection à gauche (L5d, `TaskBatchUi::getCheckbox()`) n'est portée au Jour que par la carte
 * de la première colonne de l'intervention (`main`, `TaskDayLib::getColumns()`) : les autres gardent sa
 * place vide en mode sélection, pour que leurs cartes restent alignées.
 *
 * Le Jour (L5c) rend la même carte dans une colonne (`$column`, `TaskDayLib::getColumns()`) : une à faire
 * s'y glisse d'un jour à l'autre et sa pastille ouvre le menu du jour, une faite porte son temps devant le
 * rond, dont le clic ouvre sous elle le champ du temps, à la place de la bulle. Elle y porte sa clé de rang
 * (`data-sort`, `TaskWeekLib::getSort()`), d'où le glisser déduit sa place dans la colonne d'arrivée.
 *
 * L'Année ne rend pas cette carte : elle a sa carte réduite (`TaskYearUi::getCard()`), qui n'en reprend
 * que la marque.
 */
class TaskCardUi {

	const PLACES = 4;

	public function get(\structure\Structure $eStructure, string $week, \series\Task $eTask, \Collection $cUser, ?string $column = NULL): string {

		$eTask->expects(['times', 'timePeriod', 'search', 'cComment', 'keptCopy']);

		$done = $eTask->isDone();
		$cUserTask = $this->getUsers($eTask, $cUser);
		$timeDay = ($column !== NULL and $done and $this->canTimeDay($eStructure, $eTask, $column));

		// La copie qu'une carte gardée laisse dans « Fait » (`TaskWeekLib::getColumns()`) ne se sélectionne pas :
		// la carte gardée porte déjà la case de l'intervention.
		$select = (($column === NULL and $eTask['keptCopy'] === FALSE) or ($column !== NULL and $eTask['main']));
		$class = 'planning-task '.($done ? 'planning-task-done' : '').' '.($select ? 'batch-checkbox' : '');

		// L'espèce que coche un clic sur la vignette (R-Retours-1210 V44).
		$plant = $eTask['plant']->notEmpty() ? ' data-plant="'.$eTask['plant']['id'].'"' : '';

		if($column === NULL) {

			// Une récolte de série se saisit depuis la carte, comme sur l'écran des récoltes (retour 203) : la
			// culture est ce que la saisie écrit, et une récolte sans série n'en a pas.
			$harvest = ($eTask['action']['fqn'] === ACTION_RECOLTE and $eTask['cultivation']->notEmpty());

			$h = '<div class="'.$class.'" id="planning-task-'.$eTask['id'].($eTask['keptCopy'] ? '-kept' : '').'" data-task="'.$eTask['id'].'"'.$plant.($harvest ? ' data-cultivation="'.$eTask['cultivation']['id'].'"' : '').'>';

		} else {
			$h = '<div class="'.$class.'" id="'.self::getId($eTask, $column).'" data-task="'.$eTask['id'].'"'.$plant.' data-column="'.$column.'" data-sort="'.$eTask['sort'].'" '.(($done === FALSE and $eTask->canWrite()) ? 'draggable="true"' : '').' '.($eTask['kept'] ? 'data-kept="'.$eTask['keptTime'].'"' : '').'>';
		}

			$h .= $select ? new TaskBatchUi()->getCheckbox($eTask) : '<span class="planning-select"></span>';

			// Un droit limité voit la carte sans pouvoir ouvrir la page de l'intervention, que `Task::canRead()` lui refuse.
			$tag = $eTask->canRead() ? 'a' : 'div';

			// Un lien se glisse lui-même, et c'est son adresse qui partirait : dans le Jour, la carte se glisse entière.
			$h .= '<'.$tag.' '.($eTask->canRead() ? 'href="'.TaskDetailUi::url($eTask, $week).'"'.($column !== NULL ? ' draggable="false"' : '') : '').' class="planning-task-main '.($eTask['plant']->notEmpty() ? 'planning-task-with-vignette' : '').'" '.self::getSearch($eTask).self::getSearchPlant($eTask).'>';

				if($eTask['plant']->notEmpty()) {
					$h .= '<span class="planning-task-vignette">'.\plant\PlantUi::getVignette($eTask['plant'], '1.75rem').'</span>';
				}

				if($column !== NULL and $eTask['kept']) {

					// La carte gardée garde le titre qu'elle avait à faire : sans série, espèce ni note, elle le
					// perdrait sinon sous la main qui la coche, la coche ajoutant celle-ci à ses personnes.
					$eTaskBefore = $eTask['eTaskBefore'];
					$cUserBefore = $this->getUsers($eTaskBefore, $cUser);

					$topBefore = $this->getTop($eStructure, $eTaskBefore, $cUserBefore, before: TRUE);
					$top = $this->getTop($eStructure, $eTask, $cUserTask, $timeDay);
					$hasName = self::hasName($eTaskBefore, $cUserBefore);

					// Les deux groupes du haut, comme les deux pieds, partagent une case de grille (`planning.css`) : la
					// carte gardée prend la largeur du plus large et la hauteur du plus haut, et la coche ne la
					// rétrécit pas.
					$topKept = ($topBefore.$top !== '') ? '<span class="planning-task-top-kept">'.$topBefore.$top.'</span>' : '';

					$h .= $this->getContent($eTaskBefore, $week, $cUserBefore, $hasName ? $topKept : '');

					$h .= '<div class="planning-task-foot-kept">';
						$h .= $this->getFoot($eStructure, $week, $eTaskBefore, $column, before: TRUE, top: $hasName ? '' : $topBefore);
						$h .= $this->getFoot($eStructure, $week, $eTask, $column, top: $hasName ? '' : $top);
					$h .= '</div>';

				} else {

					$top = $this->getTop($eStructure, $eTask, $cUserTask, $timeDay);
					$hasName = self::hasName($eTask, $cUserTask);

					$h .= $this->getContent($eTask, $week, $cUserTask, $hasName ? $top : '');
					$h .= $this->getFoot($eStructure, $week, $eTask, $column, top: $hasName ? '' : $top);

				}

			$h .= '</'.$tag.'>';

			$h .= '<div class="planning-task-validate">';
				$h .= $this->getMark($eTask);
			$h .= '</div>';

			if($column !== NULL) {

				if($done === FALSE and $eTask->canWrite()) {
					$h .= $this->getDayMenu($week, $eTask, $column);
				}

				if($timeDay) {
					$h .= '<div class="planning-task-time-field">';
						$h .= '<div class="planning-task-time-head">';
							$h .= '<span class="util-label">'.s("Temps passé").'</span>';
							$h .= new TimeWeekUi()->getWho($eTask['eUserTime'], '1.25rem');
						$h .= '</div>';
						$h .= new DurationUi()->getStepBare('task-'.$eTask['id'].'-'.$column, $eTask['timeDay']);
					$h .= '</div>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'identifiant d'une carte du Jour : une intervention a une carte dans chaque colonne où elle va
	 * (`TaskDayLib::getKeys()`), donc la colonne fait partie de l'identifiant.
	 */
	public static function getId(\series\Task $eTask, string $column): string {

		return 'planning-task-'.$eTask['id'].'-'.$column;

	}

	/**
	 * Ce que la recherche des Interventions (`PlanningTaskSearch`) lit sur la carte, posé sur l'intervention
	 * par `TaskSearchLib::fill()`. La carte réduite de l'Année le porte aussi.
	 */
	public static function getSearch(\series\Task $eTask): string {

		return 'data-search="'.encode(self::getSearchValue($eTask)).'"';

	}

	/**
	 * La valeur de `data-search`, que la réponse de `taskWeek:doUser` repose sur la ligne de la grille
	 * (`setAttribute()`) : la même écriture que le rendu complet.
	 */
	public static function getSearchValue(\series\Task $eTask): string {

		return json_encode($eTask['search'], JSON_UNESCAPED_UNICODE);

	}

	/**
	 * La vignette que la suggestion de l'espèce de la carte porte dans la recherche de la Semaine et du Jour,
	 * qui relève ses suggestions sur les cartes : elle suit la carte, que chaque réponse redessine.
	 */
	public static function getSearchPlant(\series\Task $eTask): string {

		if($eTask['plant']->empty()) {
			return '';
		}

		return ' data-search-plant="'.encode(json_encode(self::getSearchVignette($eTask['plant']), JSON_UNESCAPED_UNICODE)).'"';

	}

	/**
	 * La vignette d'une espèce dans les suggestions de l'omnibox, comme sur sa carte : le symbole du
	 * référentiel (`icon`) ou, pour une espèce propre à la structure, sa photo ou ses initiales (`vignette`).
	 */
	public static function getSearchVignette(\plant\Plant $ePlant): array {

		return ($ePlant['fqn'] !== NULL) ?
			['icon' => \plant\PlantUi::getVignetteHref($ePlant['fqn'])] :
			['vignette' => \plant\PlantUi::getVignette($ePlant, '1.25rem')];

	}

	/**
	 * Le champ du temps d'une carte faite du Jour, que le clic sur son temps ouvre (contre-recette de L5c,
	 * « une quantité enregistrée se modifie par un clic sur la valeur ») : le temps de la personne
	 * connectée ce jour-là (écart a de L5c), que l'on écrit comme dans la bulle de la Semaine. Il n'existe
	 * que dans un jour passé ou aujourd'hui, `Timesheet.date` refusant un jour à venir, et pour qui peut
	 * écrire l'intervention, comme la bulle.
	 */
	protected function canTimeDay(\structure\Structure $eStructure, \series\Task $eTask, string $column): bool {

		return (
			$eStructure->hasTime() and
			$column !== TaskDayLib::NONE and
			$column <= \Clock::date() and
			$eTask->canWrite()
		);

	}

	/**
	 * Les personnes qui ont du temps sur l'intervention dans la période de la carte, la semaine ou le jour de
	 * sa colonne au Jour, parmi les membres : celles qui ont quitté la ferme ne s'affichent pas, comme dans le
	 * planning actuel.
	 */
	protected function getUsers(\series\Task $eTask, \Collection $cUser): \Collection {

		$cUserTask = new \Collection();

		foreach($eTask['times'] as $user => $time) {

			if($time > 0.0 and $cUser->offsetExists($user)) {
				$cUserTask[$user] = $cUser[$user];
			}

		}

		return $cUserTask;

	}

	/**
	 * Le corps de la carte. Le titre, du plus parlant au dernier recours (écart i, arbitré par V-Planning) :
	 * - la série, puis sa saison quand ce n'est pas celle de la semaine, et son lieu ;
	 * - sans série, l'espèce, et le lieu de l'intervention ;
	 * - sans série ni espèce, la note ;
	 * - sans rien de cela, aucun titre quand un membre y a passé du temps : la carte commence par
	 *   ses personnes et son temps, au pied, et jamais par le nom d'une personne ;
	 * - sinon ce que le planning actuel écrit sur la même intervention (`TaskUi::getMore()`), ou
	 *   l'action, pour qu'une carte sans temps ne soit pas vide.
	 * La note et le complément du planning actuel (variété, calibre, méthodes, quantité récoltée) suivent
	 * le titre quand il ne les porte pas déjà. La quantité d'une récolte et la valeur d'une fertilisation se
	 * lisent à côté du nom de la série ou de l'espèce, sur sa ligne, précédées du calibre et de la variété
	 * (`getNameComplement()`) ; seules les méthodes restent alors dessous, et la ligne n'a rien après le nom
	 * pour une autre action.
	 * Le matériel et les produits suivent ce complément, comme sur la ligne de la grille. Un semis ou une plantation
	 * les range plutôt dans son cadre, avec ses variétés et son espacement (`getFacts()`).
	 * Le groupe du haut (`$top`, `getTop()`) ouvre le nom : il flotte à droite, et un flottant ne monte sur la
	 * première ligne que s'il précède le texte qu'elle porte.
	 */
	protected function getContent(\series\Task $eTask, string $week, \Collection $cUserTask, string $top = ''): string {

		$uiTask = new \series\TaskUi();

		$note = $uiTask->getTaskDescription($eTask);
		$more = self::getMoreOutsideName($eTask);
		$tools = self::hasFacts($eTask) ? '' : $uiTask->getTaskTools($eTask);
		$subject = self::getSubject($eTask);

		$h = '';

		if($subject !== '') {

			$h .= '<div class="planning-task-name">'.$top.$subject.'</div>';
			$h .= $this->getPlace($eTask, $week);

		} else if($note !== '') {

			$h .= '<div class="planning-task-name">'.$top.$note.'</div>';
			$note = '';

		} else if($cUserTask->empty()) {

			$h .= '<div class="planning-task-name">'.$top.($more !== '' ? $more : encode($eTask['action']['name'])).'</div>';
			$more = '';

		}

		// Sans titre, le complément ouvre la carte et porte le groupe du haut sur sa ligne (`hasName()`).
		if($more !== '' or $tools !== '') {
			$h .= '<div class="planning-task-more">'.($h === '' ? $top : '').$more.$tools.'</div>';
		}

		$h .= self::getFacts($eTask);

		if($note !== '') {
			$h .= '<div class="planning-task-note">'.$note.'</div>';
		}

		if($eTask['cComment']->notEmpty()) {
			$h .= $this->getComments($eTask);
		}

		return $h;

	}

	/**
	 * Un semis ou une plantation porte des informations que le produit lit dans la série et le matériel, et non dans
	 * ce que l'utilisateur a écrit. Elles se rangent dans un cadre (`getFacts()`), pour se distinguer de la
	 * description libre qui suit (FN, retour 415). Les appelants demandent ici si le matériel y entre.
	 */
	public static function hasFacts(\series\Task $eTask): bool {
		return in_array($eTask['action']['fqn'], [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION], TRUE);
	}

	/**
	 * Le cadre d'un semis ou d'une plantation, sur la carte, la ligne de la grille et la carte de l'Année : les
	 * intrants et le matériel (`\series\TaskUi::getTaskTools()`), les variétés et l'espacement. Rien quand aucun
	 * des trois n'a de quoi s'écrire. Le cadre est dessiné par un bloc intérieur : une requête de conteneur ne
	 * peut pas styler son conteneur, et c'est la largeur du bloc extérieur qui décide si le cadre garde ses bords
	 * latéraux (`planning.css`).
	 */
	public static function getFacts(\series\Task $eTask): string {

		if(self::hasFacts($eTask) === FALSE) {
			return '';
		}

		$tools = new \series\TaskUi()->getTaskTools($eTask);

		$h = ($tools !== '') ? '<div class="planning-task-tools">'.$tools.'</div>' : '';
		$h .= self::getSeedling($eTask);
		$h .= self::getSpacing($eTask);

		return ($h !== '') ? '<div class="planning-task-facts"><div class="planning-task-facts-frame">'.$h.'</div></div>' : '';

	}

	/**
	 * Les variétés d'un semis ou d'une plantation, sur la carte comme sur la ligne de la grille (FF, retour 407),
	 * à partir de ce que `TaskWeekLib::fillSeedling()` a posé. Une pépinière garde le tableau de l'ancien planning
	 * (`\series\PlanningUi::getTaskSeedling()`) : plants, semences et plateaux en colonnes, avec le poids des
	 * plants et des graines quand la variété le connaît. Un semis direct et une plantation n'ont qu'une quantité,
	 * qui suit la variété sur sa ligne. Une série dont la surface n'est qu'un objectif écrit ses quantités en
	 * couleur d'avertissement, comme l'ancien planning.
	 */
	protected static function getSeedling(\series\Task $eTask): string {

		$fqn = $eTask['action']['fqn'];

		$h = '';

		foreach($eTask['cCultivation'] as $eCultivation) {

			$h .= match($fqn) {
				ACTION_SEMIS_PEPINIERE => self::getSeedlingTable($eCultivation),
				ACTION_SEMIS_DIRECT, ACTION_PLANTATION => self::getSeedlingLines($eCultivation, $fqn)
			};

		}

		return $h;

	}

	/**
	 * La pépinière a deux formes, que la largeur de ce qui la porte départage (`planning.css`) : le tableau de
	 * l'ancien planning, et sous lui la liste d'une ligne par variété de la piste B, sans semences ni surface,
	 * quand la colonne de la variété n'aurait plus la largeur de 12 caractères. La colonne des plateaux n'existe
	 * que si l'intervention a une plaque.
	 */
	protected static function getSeedlingTable(\series\Cultivation $eCultivation): string {

		$h = '<div class="planning-task-seedling planning-task-seedling-nursery'.($eCultivation['hasTrays'] ? ' planning-task-seedling-nursery-trays' : '').'">';
			$h .= '<div class="planning-task-seedling-grid">';

				$h .= '<div></div>';
				$h .= '<div class="util-table-header text-end">'.s("Plants").'</div>';
				$h .= '<div class="util-table-header text-end">'.s("Semences").'</div>';
				if($eCultivation['hasTrays']) {
					$h .= '<div class="util-table-header text-end">'.s("Plateaux").'</div>';
				}

				foreach($eCultivation['cSlice'] as $eSlice) {

					$eVariety = $eSlice['variety'];
					$warning = $eSlice['targeted'] ? ' color-warning' : '';

					$h .= '<div class="planning-task-seedling-variety">';
						$h .= self::getSeedlingVariety($eVariety);
						if($eSlice['area'] > 0) {
							$h .= '<div class="color-muted">'.s("{value} m²", \util\TextUi::number($eSlice['area'], NULL)).'</div>';
						}
					$h .= '</div>';

					$h .= '<div class="planning-task-seedling-value'.$warning.'">';
						$h .= ($eSlice['youngPlants'] > 0) ? \util\TextUi::number($eSlice['youngPlants']) : '?';
						if($eVariety->notEmpty() and $eSlice['youngPlants'] > 0 and $eVariety['numberPlantKilogram'] !== NULL) {
							$h .= '<div class="color-muted">'.\plant\VarietyUi::getPlantsWeight($eVariety, $eSlice['youngPlants']).'</div>';
						}
					$h .= '</div>';

					$h .= '<div class="planning-task-seedling-value'.$warning.'">';
						$h .= ($eSlice['seeds'] > 0) ? \util\TextUi::number($eSlice['seeds']) : '?';
						if($eVariety->notEmpty() and $eSlice['seeds'] > 0 and $eVariety['weightSeed1000'] !== NULL) {
							$h .= '<div class="color-muted">'.\plant\VarietyUi::getSeedsWeight1000($eVariety, $eSlice['seeds']).'</div>';
						}
					$h .= '</div>';

					if($eCultivation['hasTrays']) {
						$h .= '<div class="planning-task-seedling-value">';
							foreach($eSlice['trays'] as $trays) {
								$h .= '<div>'.($trays === NULL ? '?' : \util\TextUi::number($trays, NULL)).'</div>';
							}
						$h .= '</div>';
					}

				}

			$h .= '</div>';
			$h .= '<div class="planning-task-seedling-list">'.self::getSeedlingList($eCultivation, ACTION_SEMIS_PEPINIERE).'</div>';
		$h .= '</div>';

		return $h;

	}

	protected static function getSeedlingLines(\series\Cultivation $eCultivation, string $fqn): string {
		return '<div class="planning-task-seedling">'.self::getSeedlingList($eCultivation, $fqn).'</div>';
	}

	/**
	 * Une ligne par variété : la variété, puis ses quantités, « Armor · 1 200 plants · 11,6 plateaux ». Les
	 * plateaux ne prennent pas la couleur d'avertissement, comme dans le tableau.
	 */
	protected static function getSeedlingList(\series\Cultivation $eCultivation, string $fqn): string {

		$h = '';

		foreach($eCultivation['cSlice'] as $eSlice) {

			$class = $eSlice['targeted'] ? 'color-warning' : 'color-muted';

			$quantity = match($fqn) {
				ACTION_SEMIS_DIRECT => ($eSlice['seeds'] > 0) ? p("{value} graine", "{value} graines", $eSlice['seeds'], ['value' => \util\TextUi::number($eSlice['seeds'])]) : s("? graines"),
				ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION => ($eSlice['youngPlants'] > 0) ? p("{value} plant", "{value} plants", $eSlice['youngPlants'], ['value' => \util\TextUi::number($eSlice['youngPlants'])]) : s("? plants")
			};

			$h .= '<div>';
				$h .= self::getSeedlingVariety($eSlice['variety']);
				$h .= ' <span class="util-unit '.$class.'">· '.$quantity.'</span>';
				foreach($eSlice['trays'] as $trays) {
					$h .= ' <span class="util-unit color-muted">· '.($trays === NULL ? s("? plateaux") : p("{value} plateau", "{value} plateaux", $trays, ['value' => \util\TextUi::number($trays, NULL)])).'</span>';
				}
			$h .= '</div>';

		}

		return $h;

	}

	protected static function getSeedlingVariety(\plant\Variety $eVariety): string {
		return $eVariety->empty() ? '<i>'.s("Variété non renseignée").'</i>' : encode($eVariety['name']);
	}

	/**
	 * L'espacement d'un semis direct ou d'une plantation (FF, demande nouvelle du retour 407 : l'ancien planning ne
	 * l'affichait pas). En planches, le nombre de rangs, l'espace sur le rang et l'espace entre les rangs, pour ce
	 * qui en est renseigné ; une culture conduite à la densité dit sa densité, au libellé de la fiche de la culture
	 * (`\series\CultivationUi::getImplantation()`). Rien quand rien n'est renseigné.
	 */
	protected static function getSpacing(\series\Task $eTask): string {

		if(in_array($eTask['action']['fqn'], [ACTION_SEMIS_DIRECT, ACTION_PLANTATION], TRUE) === FALSE) {
			return '';
		}

		$h = '';

		foreach($eTask['cCultivation'] as $eCultivation) {

			$parts = [];

			if($eCultivation['distance'] === \series\Cultivation::DENSITY) {

				if($eCultivation['density'] !== NULL) {
					$parts[] = s("Densité {value}", ['value' => '<span class="util-unit">'.s("{value} / m²", \util\TextUi::number($eCultivation['density'], ($eCultivation['density'] < 10) ? 1 : 0)).'</span>']);
				}

			} else {

				if($eCultivation['rows'] !== NULL) {
					$parts[] = '<span class="util-unit">'.p("{value} rang", "{value} rangs", $eCultivation['rows']).'</span>';
				}

				if($eCultivation['plantSpacing'] !== NULL) {
					$parts[] = s("{value} sur le rang", ['value' => '<span class="util-unit">'.s("{value} cm", \util\TextUi::number($eCultivation['plantSpacing'], NULL)).'</span>']);
				}

				if($eCultivation['rowSpacing'] !== NULL) {
					$parts[] = s("{value} entre rangs", ['value' => '<span class="util-unit">'.s("{value} cm", \util\TextUi::number($eCultivation['rowSpacing'], NULL)).'</span>']);
				}

			}

			if($parts !== []) {
				$h .= '<div class="planning-task-spacing">'.implode(' · ', $parts).'</div>';
			}

		}

		return $h;

	}

	/**
	 * Les commentaires sous la description, sur la carte comme sur la ligne de la grille (DW) : la liste de
	 * l'ancien planning (`\series\TaskUi::getComments()`) sans son conteneur, dont l'id se répéterait sur les
	 * cartes d'une même intervention dans plusieurs colonnes du Jour. Ni lien ni bouton : la carte est
	 * elle-même un lien, et la ligne mène à l'intervention par son nom.
	 */
	public function getComments(\series\Task $eTask): string {

		return '<div class="planning-task-comments">'.new \series\CommentUi()->getList($eTask['cComment']).'</div>';

	}

	/**
	 * Ce qui nomme une intervention sur la carte comme sur la fiche de temps, déjà échappé : le nom de sa série,
	 * à défaut celui de son espèce, suivi de son complément (`getNameComplement()`). Vide sans série ni espèce.
	 * La grille de la Semaine (CW) le prend sans la quantité récoltée, que sa colonne « Récolté » porte.
	 */
	public static function getSubject(\series\Task $eTask, bool $harvested = TRUE): string {

		if($eTask['series']->notEmpty()) {
			return \series\SeriesUi::name($eTask['series']).self::getNameComplement($eTask, $harvested);
		} else if($eTask['plant']->notEmpty()) {
			return encode($eTask['plant']['name']).self::getNameComplement($eTask, $harvested);
		} else {
			return '';
		}

	}

	/**
	 * Ce qui suit le nom de la série ou de l'espèce sur sa ligne, sur la carte comme sur la fiche de temps :
	 * ce que le planning actuel écrit d'une récolte ou d'une fertilisation (`\sequence\FlowUi::getMore()`),
	 * la quantité récoltée ou la valeur de l'apport, précédé du calibre et de la variété (N, image 105 :
	 * « Patate douce Déclassée Beauregard 5 kg »). Le calibre et la variété y montent aussi sans quantité, pour
	 * qu'une récolte à faire et une récolte faite d'une même série se lisent au même endroit. Vide pour une
	 * autre action, ou sans série ni espèce pour le porter. Le calibre et la variété se séparent par une espace,
	 * et la quantité garde sa marge (`planning.css`). Sans `$harvested`, une récolte n'y porte pas sa quantité.
	 */
	public static function getNameComplement(\series\Task $eTask, bool $harvested = TRUE): string {

		if(
			in_array($eTask['action']['fqn'], [ACTION_RECOLTE, ACTION_FERTILISATION], TRUE) === FALSE or
			($eTask['series']->empty() and $eTask['plant']->empty())
		) {
			return '';
		}

		$flow = ($harvested or $eTask['action']['fqn'] !== ACTION_RECOLTE) ? new \sequence\FlowUi()->getMore($eTask) : '';
		$parts = new \series\TaskUi()->getTaskSizeVariety($eTask);

		return ($parts === []) ? $flow : ' '.implode(' ', $parts).$flow;

	}

	/**
	 * Ce que le planning actuel écrit d'une intervention (`\series\TaskUi::getMore()`), sans ce que la ligne du nom
	 * porte déjà (`getNameComplement()`) : il n'en reste alors que les méthodes. La carte et la ligne de la grille le
	 * posent sous leur titre.
	 */
	public static function getMoreOutsideName(\series\Task $eTask): string {

		$uiTask = new \series\TaskUi();

		return (self::getNameComplement($eTask) === '') ? $uiTask->getMore($eTask) : $uiTask->getTaskMethods($eTask);

	}

	public function getPlace(\series\Task $eTask, string $week): string {

		$parts = [];

		if($eTask['series']->notEmpty()) {

			if($eTask['series']['season'] !== week_year($week)) {
				$parts[] = s("Saison {value}", $eTask['series']['season']);
			}

			if($eTask['series']['cccPlace']->notEmpty()) {
				$parts[] = $this->getPlaces($eTask['series']['use'], $eTask['series']['cccPlace']);
			}

		} else if($eTask['cccPlace']->notEmpty()) {
			$parts[] = $this->getPlaces(\series\Series::BED, $eTask['cccPlace']);
		}

		if($parts === []) {
			return '';
		}

		return '<div class="planning-task-place">'.implode(' · ', $parts).'</div>';

	}

	/**
	 * L'assolement, partout où le planning le montre : au-delà de `PLACES` noms (planches, ou parcelles qui
	 * s'affichent seules), la liste s'arrête sur « … » et se lit entière dans la page de l'intervention
	 * (retour 103 ; plus d'info-bulle depuis R-Retours-0410/title-2). Mesuré le 2026-10-01 sur les 81 207
	 * séries en planches des saisons 2025 et 2026 : 4,9 % en dépassent quatre.
	 * Quatre tiennent sur une ligne sous le nom de la série tant qu'ils tombent dans un ou deux groupes ; six en
	 * prenaient deux (contre-recette du retour 103).
	 */
	public function getPlaces(string $use, \Collection $cccPlace): string {
		return new \series\CultivationUi()->displayPlaces($use, $cccPlace, self::PLACES);
	}

	/**
	 * La semaine prévue d'une intervention à faire, quand ce n'est pas celle de l'écran (L8, FZ) : en retard, ou
	 * décalée à une semaine suivante et restée affichée par le temps ou la récolte qu'on y a saisis. Seule la zone
	 * « En retard » tait un retard d'une semaine, que sa tête dit déjà ; ailleurs, la même intervention montrée
	 * dans la semaine semblerait y être prévue. La zone est celle de la Lib qui a lu l'intervention
	 * (`TaskLateLib::getTasks()`, `TaskWeekLib::getTasksByWeeks()`), et la règle ne lit qu'elle et la semaine
	 * affichée : une carte ou une ligne redessinée seule porte la même marque qu'au rechargement. Le pied de la
	 * carte, celui du Jour compris, et la ligne de la grille la reprennent (DZ).
	 */
	public static function getOtherWeek(\series\Task $eTask, string $week): string {

		if(self::hasOtherWeek($eTask, $week)) {
			return '<span class="badge badge-outline">'.s("s{value}", week_number($eTask['plannedWeek'])).'</span>';
		} else {
			return '';
		}

	}

	public static function hasOtherWeek(\series\Task $eTask, string $week): bool {

		$eTask->expects(['zone']);

		if(
			$eTask->isTodo() === FALSE or
			$eTask['plannedWeek'] === NULL or
			$eTask['plannedWeek'] === $week
		) {
			return FALSE;
		}

		return (
			$eTask['zone'] !== TaskLateLib::LATE or
			$eTask['plannedWeek'] !== toWeek(week_date_starts($week).' - 7 DAYS')
		);

	}

	/**
	 * Une carte a-t-elle une ligne de tête (`getContent()`) pour son groupe du haut (`getTop()`) : son titre, ou à
	 * défaut le complément de son action et ses outils, qui ouvrent alors la carte. Elle n'en a aucune sans sujet,
	 * note, complément ni outil quand un membre y a passé du temps : le groupe va au pied, la seule ligne qu'elle ait.
	 */
	protected static function hasName(\series\Task $eTask, \Collection $cUserTask): bool {
		return (
			self::getSubject($eTask) !== '' or
			new \series\TaskUi()->getTaskDescription($eTask) !== '' or
			$cUserTask->empty() or
			self::getMoreOutsideName($eTask) !== '' or
			(self::hasFacts($eTask) === FALSE and new \series\TaskUi()->getTaskTools($eTask) !== '')
		);
	}

	/**
	 * Le groupe du haut : les personnes qui ont passé du temps dans la semaine, puis ce temps, sur la ligne du
	 * titre, contre le rond qui coche (FO, retour 416, piste A de la planche), ou au pied d'une carte
	 * sans titre (`hasName()`). Les personnes suivent le temps de travail dont elles sont l'auteur (R-Retours-0810,
	 * 236), à gauche de lui. Rien quand la carte n'a ni temps ni personnes.
	 *
	 * Le temps d'une faite que l'on peut saisir ce jour-là au Jour (`$timeDay`) s'écrit même nul, et son clic
	 * ouvre le champ sous la carte : le total de l'intervention ce jour-là (retour 360,
	 * `TaskDayLib::getColumns()`), que le champ change de l'écart qu'il écrit pour la personne connectée.
	 *
	 * Le groupe d'avant la coche d'une carte gardée (`$before`) n'est qu'une place, comme son pied.
	 */
	protected function getTop(\structure\Structure $eStructure, \series\Task $eTask, \Collection $cUserTask, bool $timeDay = FALSE, bool $before = FALSE): string {

		$h = '';

		if($cUserTask->notEmpty()) {

			$h .= '<span class="planning-task-users">';

				foreach($cUserTask as $eUser) {
					$h .= \user\UserUi::getVignette($eUser, '1.25rem');
				}

			$h .= '</span>';

		}

		if($eStructure->hasTime()) {

			if($timeDay) {
				// Dans le lien de la carte : `never` écarte la navigation ajax du socle (`ajax.js`, délégué de
				// `a[href]`), et le délégué du Jour annule ensuite la navigation du navigateur.
				$h .= '<span class="planning-task-time util-quick" data-planning-task-time-open data-ajax-navigation="never">'.$this->getSpent($eTask['timePeriod']).'</span>';
			} else if($eTask['timePeriod'] > 0.0) {
				$h .= '<span class="planning-task-time">'.DurationUi::getSpent($eTask['timePeriod']).'</span>';
			}

		}

		if($h === '') {
			return '';
		}

		return '<span class="planning-task-top '.($before ? 'not-visible' : '').'" '.($before ? 'inert' : '').'>'.$h.'</span>';

	}

	/**
	 * Le pied : le jour prévu d'une intervention à faire, puis le prévu qui borne son temps, et le groupe du haut
	 * d'une carte sans titre (`$top`). Une faite n'y porte pas de jour (retour 104) : le moment où l'on
	 * coche ne dit rien du temps de travail, qui peut s'étaler sur plusieurs jours.
	 *
	 * Le jour est une pastille courte, « Sam. », sans geste (R3 de la passe ui, tranché par V-Planning) :
	 * le nom court du jour, dans la pastille de la maquette (`boutonJour()`). Comme le planning actuel
	 * (`series/ui/Planning.u.php`, `getTaskDetails()`), il ne se montre que dans la semaine où
	 * l'intervention est prévue : une intervention commencée ici et prévue une autre semaine n'y a pas
	 * de jour.
	 *
	 * Dans le Jour (`$column`), la pastille ouvre le menu du jour de la carte (`getDayMenu()`, maquette
	 * navigable, `boutonJour()`), « Jour » sur une intervention sans jour.
	 */
	protected function getFoot(\structure\Structure $eStructure, string $week, \series\Task $eTask, ?string $column = NULL, bool $before = FALSE, string $top = ''): string {

		$h = '';

		$day = ($eTask->isTodo() and $eTask['plannedDate'] !== NULL and $eTask['plannedWeek'] === $week) ?
			\util\DateUi::getDayNameShort((int)date('N', strtotime($eTask['plannedDate']))) :
			NULL;

		if($column !== NULL and $eTask->isTodo() and $eTask->canWrite()) {

			// Le pied d'avant la coche ne garde de la pastille que sa largeur : sans identifiant, il ne doublerait pas celui de la pastille réelle, et un élément en `visibility: hidden` ne reçoit aucun clic.
			$id = $before ? '' : 'id="'.self::getId($eTask, $column).'-day" data-dropdown-id="'.self::getId($eTask, $column).'-day"';

			$h .= '<span class="btn btn-badge btn-xs badge badge-outline dropdown-toggle planning-task-day-pick" '.$id.' data-dropdown="bottom-start">'.($day ?? s("Jour")).'</span>';

		} else if($day !== NULL) {
			$h .= '<span class="badge badge-outline">'.$day.'</span>';
		}

		$h .= self::getOtherWeek($eTask, $week);

		if($eStructure->hasTime() and $eTask->isTodo() and $eTask['timeExpected'] > 0.0) {
			$h .= '<span>'.s("prévu {value}", \series\TaskUi::convertTime($eTask['timeExpected'])).'</span>';
		}

		$h .= $top;

		return '<div class="planning-task-foot '.($before ? 'not-visible' : '').'" '.($before ? 'inert' : '').'>'.$h.'</div>';

	}

	/**
	 * Une durée écrite, vide comprise : une durée que personne n'a saisie s'écrit « ? », partout où elle
	 * s'affiche (retour 107, « mets un ? si rien », étendu par V-Planning à tous les vides de
	 * cette méthode). Le « ? » remplace le « -h-- » du 29/09. Une durée saisie prend la marque d'un temps
	 * passé (`DurationUi::getSpent()`), le « ? » reste neutre.
	 */
	public function getSpent(?float $time): string {

		if($time !== NULL and $time > 0.0) {
			return DurationUi::getSpent($time);
		} else {
			return '?';
		}

	}

	/**
	 * Le menu du jour d'une carte à faire du Jour (`menuJour()` de la maquette navigable) : « Sans jour »,
	 * les sept jours de la semaine, puis la semaine suivante. Il vit hors du lien de la carte, qu'un
	 * second lien imbriqué fermerait, et le socle l'apparie à sa pastille par `data-dropdown-id`
	 * (`Lime.Dropdown.getListFromButton()`). Ses entrées partent par `PlanningTaskDay.plan()`, qui
	 * réémet la semaine de l'écran : la carte n'en sait rien, pour rester la même partout.
	 */
	protected function getDayMenu(string $week, \series\Task $eTask, string $column): string {

		$today = \Clock::date();

		$h = '<div class="dropdown-list planning-task-day-menu" data-dropdown-id="'.self::getId($eTask, $column).'-day-list" data-task="'.$eTask['id'].'" data-column="'.$column.'">';

			$h .= '<div class="dropdown-title">'.s("Semaine {value}", week_number($week)).'</div>';

			$h .= '<a class="dropdown-item '.(($eTask['plannedWeek'] === $week and $eTask['plannedDate'] === NULL) ? 'selected' : '').'" data-planning-task-plan="'.TaskDayLib::NONE.'">'.s("Non défini").'</a>';

			foreach(week_dates($week) as $position => $date) {

				$annotation = \util\DateUi::numeric($date, \util\DateUi::DAY_MONTH);

				if($date === $today) {
					$annotation = s("{value}, aujourd'hui", $annotation);
				}

				$h .= '<a class="dropdown-item '.($eTask['plannedDate'] === $date ? 'selected' : '').'" data-planning-task-plan="'.$date.'">';
					$h .= \util\DateUi::getDayName($position + 1).' <span class="util-annotation">'.$annotation.'</span>';
				$h .= '</a>';

			}

			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a class="dropdown-item" data-planning-task-plan="next">'.s("Semaine suivante").'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La marque d'une carte, un rond au dessin du cadre des cachets du package : vide pour une intervention à
	 * faire, coché pour une faite (planche planning-a-faire-cliquable, retenue le 12/10). Le clic
	 * coche ou décoche, et ne se donne qu'avec le droit d'écrire l'intervention, que les deux actions valident
	 * (`Task::canWrite()`) : un droit limité voit le rond éteint, ou coché sans geste. La marque a la largeur du
	 * rond, quel que soit l'état, et laisse le reste de la carte au nom (retour 266).
	 */
	public function getMark(\series\Task $eTask): string {

		$done = $eTask->isDone();
		$class = 'planning-task-mark'.($done ? ' planning-task-mark-done' : '');

		if($eTask->canWrite() === FALSE) {
			return '<span class="'.$class.' planning-task-readonly">'.self::getMarkRing($done).'</span>';
		}

		return '<a class="'.$class.'" '.($done ? 'data-planning-task-todo' : 'data-planning-task-done').'="'.$eTask['id'].'">'.self::getMarkRing($done, back: $done).'</a>';

	}

	/**
	 * Le rond, et sa coche pour une faite. `$back` y ajoute la flèche du retour, que le survol montre à la place de
	 * la coche (retour 267) : elle dit que le clic remet l'intervention à faire, et n'a donc de sens que
	 * sur un rond coché qui se clique.
	 */
	private static function getMarkRing(bool $done, bool $back = FALSE): string {

		$h = '<span class="planning-task-mark-ring">';

			if($done) {
				$h .= \Asset::icon('check-lg');
			}

			if($back) {
				$h .= \Asset::icon('arrow-counterclockwise');
			}

		$h .= '</span>';

		return $h;

	}

	/**
	 * La bulle du temps passé, piste 3 de la planche planning-tache-2 retenue : le temps de la
	 * personne connectée au jour du fait, qui part du prévu posé d'office à sa première coche, et de zéro
	 * si elle avait déjà du temps sur l'intervention ; la flèche remet ce jour à zéro, la croix ferme
	 * (R-Retours-0610, point 3). Ses autres jours n'y comptent pas, le groupe du haut de la carte garde la
	 * durée de l'intervention entière (écart g, `getTop()`). Le prévu n'y est pas rappelé, il est déjà dans le total (écart d).
	 *
	 * Le temps se saisit dans la fenêtre des pas, ouverte dans la bulle (`DurationUi::getStepBare()`,
	 * R-Retours-0810, 212). Seul « Enregistrer » écrit (`PlanningTask.saveTime()`), puis ferme la bulle une fois
	 * le temps accepté ; la carte suit. La croix ferme sans rien écrire.
	 *
	 * La tête porte le rond et le nom de la personne dont le temps s'écrit, comme la fiche de temps
	 * (`TimeWeekUi::getWho()`, retour 122 : « confirmer la personne qui saisit »), le rond
	 * à la hauteur du libellé pour que la tête ne grandisse pas.
	 */
	public function getBubble(\series\Task $eTask, float $time, \user\User $eUser): string {

		$h = '<div class="planning-task-bubble" id="planning-task-bubble-'.$eTask['id'].'" data-task="'.$eTask['id'].'" onrender="PlanningTask.anchor(this)">';
			$h .= $this->getBubbleHead($eUser);
			$h .= new DurationUi()->getStepBare('bubble-'.$eTask['id'], $time);
		$h .= '</div>';

		return $h;

	}

	/**
	 * La bulle au téléphone, en Colonnes (GQ) : la carte cochée part dans « Fait », que l'onglet cache, et la
	 * bulle n'aurait plus de carte sous laquelle s'ouvrir. La même saisie monte alors du bas, dans la feuille du
	 * design system, sous le nom de l'intervention et de sa série. Sa seule croix est celle de la tête.
	 */
	public function getSheet(\series\Task $eTask, float $time, \user\User $eUser): string {

		// Le sous-titre nomme l'intervention comme sa carte, partie sous l'onglet caché : sa série ou son espèce,
		// à défaut sa note (`getContent()`).
		$subject = self::getSubject($eTask, harvested: FALSE);

		if($subject === '') {
			$subject = new \series\TaskUi()->getTaskDescription($eTask);
		}

		$h = '<div class="util-sheet" id="planning-task-sheet" data-task="'.$eTask['id'].'" onrender="PlanningTaskSheet.start(this)">';

			$h .= '<div class="util-sheet-scrim" data-planning-task-sheet-close></div>';

			$h .= '<div class="util-sheet-panel" tabindex="-1">';

				$h .= '<div class="util-sheet-grab"></div>';

				$h .= '<div class="util-sheet-head">';
					$h .= '<div>';
						$h .= '<h4>'.encode($eTask['action']['name']).'</h4>';
						if($subject !== '') {
							$h .= '<div class="util-annotation">'.$subject.'</div>';
						}
					$h .= '</div>';
					$h .= '<a class="util-sheet-close" data-planning-task-sheet-close>'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

				$h .= '<div class="util-sheet-body">';
					$h .= $this->getBubbleHead($eUser);
					$h .= new DurationUi()->getStepBare('sheet-'.$eTask['id'], $time, close: FALSE);
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getBubbleHead(\user\User $eUser): string {

		$h = '<div class="planning-task-bubble-head">';
			$h .= '<span class="util-label">'.s("Temps passé").'</span>';
			$h .= new TimeWeekUi()->getWho($eUser, '1.25rem');
		$h .= '</div>';

		return $h;

	}

}
?>
