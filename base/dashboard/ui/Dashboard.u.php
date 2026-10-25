<?php
namespace dashboard;

/**
 * L'accueil d'une structure : l'en-tête qui dit où et quand on est, puis les blocs en trois colonnes,
 * ce qui demande un geste, ce qui se surveille et le bilan de la semaine passée.
 *
 * Ce fichier tient **le cadre** et rien d'autre : la tête, les colonnes, la cellule d'un bloc,
 * son titre, la teinte de son univers et le geste de pied qui mène à sa liste, et au groupe Bureau
 * du menu, la météo (L52). L'intérieur d'un bloc est rendu par le
 * `DashboardUi` de son package, que la convention résout depuis le préfixe de son nom. C'est ce
 * partage qui permet de déplacer un bloc sans le réécrire, et de porter demain le même contenu dans
 * une vignette du poste plutôt que dans une carte.
 *
 * Il ne nomme donc aucun bloc, et un package qui en ajoute un n'a aucune raison d'ouvrir ce fichier.
 */
class DashboardUi {

	public function __construct() {
		\Asset::css('dashboard', 'dashboard.css');
	}

	/**
	 * Une date telle que l'accueil l'écrit, dans tous les blocs qui en nomment une : le jour, et les deux
	 * seuls que le nom nomme mieux que la date. Ce sont eux que vient chercher quelqu'un qui ouvre cet
	 * écran chaque matin.
	 *
	 * « demain » garde sa date, les autres lignes d'une colonne en portant une : sans elle, la seule
	 * ligne qui n'en avait pas obligeait à la calculer depuis la date du jour, écrite en tête d'écran.
	 * « aujourd'hui » s'en passe, cette date-là étant justement celle que l'en-tête donne.
	 *
	 * `$today` et `$tomorrow` viennent du `load()` du bloc : l'heure est une donnée, et une UI qui lit
	 * l'horloge se met à décider ce qu'elle rend. Statique, parce que le constructeur charge la feuille
	 * du socle, qu'un bloc n'a pas à demander.
	 */
	public static function getDate(string $date, string $today, string $tomorrow): string {

		if($date === $today) {
			return s("aujourd'hui");
		}

		if($date === $tomorrow) {
			return s("demain {value}", \util\DateUi::textual($date, \util\DateUi::DAY_MONTH));
		}

		return lcfirst(\util\DateUi::getDayName((int)date('N', strtotime($date)))).' '.\util\DateUi::textual($date, \util\DateUi::DAY_MONTH);

	}

	public static function url(\structure\Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/accueil';
	}

	public static function urlPreference(\structure\Structure $eStructure): string {
		return self::url($eStructure).'/personnaliser';
	}

	/**
	 * L'adresse qui rend un bloc seul : celle que l'emplacement d'un bloc lourd appelle pour se
	 * remplir, et celle qu'un geste du bloc rappelle pour se redessiner.
	 *
	 * `$focus` est une chaîne que le socle **transporte sans l'interpréter**, du lien que le bloc écrit
	 * jusqu'à son `load()` : c'est le bloc qui dit ce qu'elle veut dire, une date pour les deux blocs
	 * qui portent une bande de jours aujourd'hui. Le moteur qui la nommerait « jour » rouvrirait la
	 * table de ce que les blocs montrent, que le cadre a retirée.
	 */
	public static function urlBlock(\structure\Structure $eStructure, string $block, ?string $focus = NULL, bool $setting = FALSE): string {

		$query = [];

		if($focus !== NULL) {
			$query['focus'] = $focus;
		}

		if($setting) {
			$query['setting'] = 1;
		}

		$url = self::url($eStructure).'/bloc/'.$block;

		return ($query === []) ? $url : $url.'?'.http_build_query($query);

	}

	/** La page des nouvelles, que l'entrée « Du nouveau » du groupe Bureau ouvre. */
	public static function urlNews(\structure\Structure $eStructure): string {
		return self::url($eStructure).'/nouveau';
	}

	/** Le panneau du « Choisir » d'un bloc, que seul un bloc qui déclare `choose` porte. */
	public static function urlChoice(\structure\Structure $eStructure, string $block): string {
		return self::url($eStructure).'/choisir/'.$block;
	}

	public static function getBlockId(string $block): string {
		return 'dashboard-block-'.$block;
	}

	/**
	 * L'écran entier, en un seul fragment : la page de structure le rend, et `/` le rendra tel quel
	 * sous son propre gabarit (lot 3).
	 *
	 * Un tableau sans bloc, sans file ni bilan ne rend que la tête. Rien ne vient dire qu'il est vide :
	 * ce serait une phrase sur l'interface elle-même, et l'écran d'un membre qui n'a encore rien n'est
	 * pas le lieu pour l'expliquer.
	 *
	 * `$setting` rend l'accueil en réglage (L43) : chaque titre prend sa poignée et son interrupteur,
	 * les pièces éteintes gardent leur place, et une barre ferme le réglage. Les pièces arrivent déjà
	 * chargées pour lui (`DashboardLib::load()`, `setting`), ce rendu ne décide rien de ce qu'il montre.
	 * `$sectionsChosen` est l'état de chaque univers (`DashboardLib::getSectionsChosen()`).
	 *
	 * Le réglage rend la même tête et les mêmes colonnes que l'accueil, par les mêmes méthodes
	 * (L45, lots e et f) : ce qu'on règle est ce qu'on verra. Ce qui ne se range pas dans les colonnes,
	 * les univers que les colonnes répartissent de part et d'autre, se règle entre les deux, dans un
	 * encadré (`getSettingBox()`).
	 *
	 * `$entryField` est le champ de saisie, que l'accueil porte en tête de page au lieu du menu (S6) et que le
	 * gabarit rend (`StructureTemplate::getEntryInPage()`). Le réglage ne le reçoit pas.
	 *
	 * `$team` est l'équipe en 3D de la tête (`DashboardLib::getTeam()`), vide quand
	 * elle n'est pas montrée. Le réglage ne la reçoit pas non plus : on y range des blocs, pas des personnes.
	 */
	public function getBoard(\structure\Structure $eStructure, array $blocks, array $contributions, array $today, bool $setting = FALSE, array $sectionsChosen = [], string $entryField = '', array $team = []): string {

		$h = '<div id="dashboard"'.($setting ? ' class="dashboard-setting"' : '').'>';

			$h .= $this->getHead($eStructure, $today, $contributions[DashboardSetting::FIGURE] ?? [], $setting, $entryField, $team);

			if($setting) {
				$h .= $this->getSettingBox($eStructure, $sectionsChosen);
			}

			$h .= $this->getColumns($eStructure, $blocks, $contributions, $setting);

			if($setting) {
				$h .= new PreferenceUi()->getBar($eStructure);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les trois colonnes de l'accueil (L45, direction retenue le 2026-10-14 ; trois depuis
	 * L69, demandé le 2026-10-19). Les blocs arrivent rangés par `DashboardLib::load()` et se
	 * répartissent ici sans être retriés : chaque colonne garde donc l'ordre que le membre a réglé,
	 * univers par univers.
	 *
	 * Les emplacements n'ont pas de colonne à déclarer, le socle les pose : la file ouvre « À faire »,
	 * c'est ce qui attend depuis le plus longtemps ; le bilan occupe seul « Bilan ». Les nouvelles n'y
	 * sont pas, le groupe Bureau les ouvre sur leur page (`getNews()`, L50).
	 *
	 * Une colonne sans cellule n'est pas rendue : son titre resterait seul au-dessus de rien. Sa place
	 * reste vide, les colonnes gardant leur largeur (`dashboard.css`).
	 *
	 * Chaque colonne range ses tuiles en une seule piste (`util-tiles-1`), que le membre lit de haut en
	 * bas. Les blocs de forme `OPEN` suivent les trois colonnes, sur toute la largeur et sous leur propre
	 * titre (L55, sous les colonnes depuis L69), mais dans la même racine : le réglage compte les blocs de chaque univers
	 * sous `.dashboard-columns` (`preference.js`), et `doUpdatePositions` la remplace entière.
	 *
	 * En réglage, chaque bloc prend sa poignée et son interrupteur, et chaque colonne se range seule : un
	 * bloc ne change pas de colonne, sa déclaration la nomme. Les colonnes se rendent seules quand les
	 * univers changent de rang (`doUpdatePositions`), leurs blocs suivant le rang de leur univers.
	 */
	public function getColumns(\structure\Structure $eStructure, array $blocks, array $contributions, bool $setting = FALSE): string {

		$cells = array_fill_keys(DashboardSetting::COLUMNS, '');
		$opens = '';

		if(isset($contributions[DashboardSetting::QUEUE])) {
			$cells[DashboardSetting::TODO] .= $this->getQueue($eStructure, $contributions[DashboardSetting::QUEUE]);
		}

		foreach($blocks as $block => $entry) {

			$column = $entry['declaration']['column'];
			$scope = $setting ? $entry['declaration']['section'] : NULL;

			if($entry['declaration']['shape'] === DashboardSetting::OPEN) {
				$opens .= $this->getBlock($eStructure, $block, $entry, $scope, $column);
			} else {
				$cells[$column] .= $this->getBlock($eStructure, $block, $entry, $scope, $column);
			}

		}

		// Le bilan, une tuile par occupant. Il n'a pas de réglage, et sa couleur vient de la `section` que
		// l'occupant déclare.
		foreach($contributions[DashboardSetting::BILAN] ?? [] as $name => $entry) {
			$cells[DashboardSetting::REVIEW] .= $this->getBlock($eStructure, $name, $entry, column: DashboardSetting::REVIEW);
		}

		$h = '';

		foreach($cells as $column => $columnCells) {

			if($columnCells === '') {
				continue;
			}

			// « Bilan » ne se trie pas : ses occupants sont posés par le socle, et le membre ne les règle pas.
			$h .= '<div class="dashboard-column">';
				$h .= '<h2 class="util-board-title">'.$this->getColumnName($column).'</h2>';
				$h .= '<div class="util-tiles util-tiles-1"'.(($setting and $column !== DashboardSetting::REVIEW) ? new PreferenceUi()->getColumnSort($eStructure) : '').'>'.$columnCells.'</div>';
			$h .= '</div>';

		}

		if($h === '' and $opens === '') {
			return '';
		}

		return '<div class="container dashboard-board dashboard-columns">'.
			(($h === '') ? '' : '<div class="dashboard-columns-grid">'.$h.'</div>').
			$opens.
		'</div>';

	}

	/**
	 * L'encadré du réglage, sous la tête (piste « encadré », tranchée le 2026-10-16) : la
	 * rangée des univers.
	 *
	 * Les univers se rangent, éteints compris (`doUpdatePositions`), dans l'ordre que le membre leur a
	 * donné (`$sectionsChosen`, rangé par `DashboardLib::load()`). Trois par rang : leur nom est un mot,
	 * qui ne passe pas à la ligne, et la piste de 14 rem de `util-cards-fit` est plus étroite qu'une
	 * poignée, une icône, « Paramètres » et un interrupteur.
	 */
	private function getSettingBox(\structure\Structure $eStructure, array $sectionsChosen): string {

		if($sectionsChosen === []) {
			return '';
		}

		$h = '<div class="container mt-2">';
			$h .= '<div class="util-block-info">';

				$h .= '<h4>'.s("Univers").'</h4>';
				$h .= '<div class="util-cards util-cards-3"'.new PreferenceUi()->getSectionSort($eStructure).'>';

					foreach($sectionsChosen as $section => $on) {
						$h .= $this->getSectionCard($eStructure, $section, $on);
					}

				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte d'un univers : la poignée, le nom, l'interrupteur. Elle se rend seule quand son
	 * interrupteur bascule (`doUpdateSection`), ou quand un bloc allumé ou éteint fait basculer
	 * l'univers entier (`doUpdateHidden`).
	 */
	public function getSectionCard(\structure\Structure $eStructure, string $section, bool $on): string {

		$h = '<div id="'.PreferenceUi::getSectionId($section).'" class="util-deck util-deck-sm dashboard-setting-section'.($on ? '' : ' is-off').'" data-sort-id="'.encode($section).'">';
			$h .= '<div class="util-deck-head">';
				$h .= \main\DesignUi::getDragHandle();
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">';
						$h .= '<span class="dashboard-setting-name">';
							$h .= '<span class="util-icon util-icon-sm util-icon-'.$section.'">'.$this->getSectionIcon($section).'</span>';
							$h .= $this->getSectionName($section);
						$h .= '</span>';
					$h .= '</div>';
				$h .= '</div>';
				$h .= '<div class="util-deck-aside">'.new PreferenceUi()->getSectionSwitch($eStructure, $section, $on).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La bande d'un univers sans la tête de l'accueil, pour le tableau de bord d'une AMAP
	 * (`DashboardLib::loadSection()`). Le nœud `#dashboard` est gardé : `dashboard.css` borne ses
	 * sélecteurs par lui, sa feuille s'accumulant en navigation ajax.
	 *
	 * `$underHero` dit que la page s'ouvre sur une bande d'accueil et non sur la tête ordinaire : le titre
	 * d'un bloc `WIDE` peut alors prendre le rang d'un `h2` sans dépasser le titre de la page (`getCell()`).
	 */
	public function getUnivers(\structure\Structure $eStructure, array $blocks, bool $underHero = FALSE): string {

		if($blocks === []) {
			return '';
		}

		$h = '<div id="dashboard">';
			$h .= '<div class="dashboard-board">';
				$h .= $this->getSections($eStructure, $blocks, $underHero);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La date en titre, la semaine au-dessus, la roue du réglage, et sous la date le chiffre de la
	 * semaine en une phrase (L62, demandé le 2026-10-18). La météo est au groupe Bureau du
	 * menu depuis L52 (`getHomeWeather()`), et ce qui attend un geste ouvre la colonne de gauche
	 * (`getColumns()`) : la tête dit où et quand on est, et où en est la semaine.
	 *
	 * Le chiffre n'est pas un lien, comme la phrase qu'il reprend : la tête ne mène nulle part, la roue
	 * mise à part. `$figures` sont les occupants de `DashboardSetting::FIGURE`, chacun une phrase.
	 *
	 * À la tablette, le groupe Bureau n'a pas de panneau : la météo revient sous la date (arbitré
	 * le 2026-10-17). La tête n'en porte que la place, où `pilotage.js` déplace le nœud du groupe
	 * plutôt que d'en rendre un second.
	 *
	 * `$today` porte `date`, `week` et les deux bornes `weekFrom`/`weekTo` de cette semaine, que la page
	 * a calculées.
	 *
	 * En réglage, la tête est celle de l'accueil sans la roue, l'écran étant déjà celui qu'elle ouvre.
	 *
	 * Le champ de saisie (`$entryField`) clôt la tête, sous la date et ses phrases : on y écrit ce qui vient
	 * d'être fait, et les colonnes qui suivent en montrent l'effet.
	 *
	 * L'équipe (`getTeam()`) la ferme : en pied sur le filet du bas, à droite de la date dès 992 px, sous le champ plus
	 * étroit.
	 */
	private function getHead(\structure\Structure $eStructure, array $today, array $figures, bool $setting, string $entryField, array $team): string {

		$title = \util\DateUi::getDayName((int)date('N', strtotime($today['date']))).' '.\util\DateUi::textual($today['date'], \util\DateUi::DAY_MONTH);
		$context = s("semaine {value}", (int)substr($today['week'], 6)).', '.self::getWeekDays($today['weekFrom'], $today['weekTo']);

		$h = '<div class="container dashboard-head-type">';

			$h .= '<div class="dashboard-head-type-top">';
				$h .= '<p class="dashboard-head-type-kicker">'.$context.'</p>';

				// À côté de la roue et non sous la liste des absences, qu'il ferait monter jusqu'à elle (arbitrage 26)
				if($team !== [] and $team['invite']) {
					$h .= '<a href="/user/avatar" class="btn btn-outline-primary dashboard-head-type-invite">'.\Asset::icon('person-bounding-box').' '.s("Faire mon avatar").'</a>';
				}

				if($setting === FALSE) {
					$h .= $this->getSettings($eStructure);
				}

			$h .= '</div>';

			$h .= '<h1 class="dashboard-head-type-date">'.$title.'</h1>';

			foreach($figures as $name => $entry) {
				$h .= '<p id="'.self::getBlockId($name).'" class="dashboard-head-type-sentence">'.$this->getPackageUi($name)->render($name, $eStructure, $entry['data']).'</p>';
			}

			\Asset::js('dashboard', 'pilotage.js');

			$h .= '<div id="'.PilotageUi::getHomePlaceId().'" data-structure="'.$eStructure['id'].'" onrender="DashboardPilotage.place()"></div>';

			$h .= $entryField;

			if($team !== []) {
				$h .= $this->getTeam($eStructure, $today['week'], $team);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'équipe vivante de la tête (A8 de 3d-avatars, planche v3, direction C) : les membres au planning en 3D, qui font
	 * ce que fait une équipe à ce moment de la journée. `DashboardTeam` (team.js) monte la scène dans le conteneur de
	 * `SceneUi`, et c'est lui qui choisit le moment, à l'heure de l'appareil, et combien de membres la largeur tient.
	 * Qui est absent toute la journée se tient derrière le rang, pâli et immobile (planche v6).
	 *
	 * À côté du rang, la liste des absences de la semaine qui ne sont pas finies (`getAbsences()`). La personne
	 * connectée qui n'a pas d'avatar n'est pas dans le rang : de quoi en faire un se tient à côté de la roue
	 * (`getHead()`).
	 */
	private function getTeam(\structure\Structure $eStructure, string $week, array $team): string {

		\Asset::js('dashboard', 'team.js');

		$members = [];

		foreach($team['cUser'] as $eUser) {

			// Le prénom brut et non `getFirstName()`, qui l'encode : il part en JSON dans un attribut encodé une fois,
			// puis la page le pose en `textContent`, où un second encodage s'afficherait tel quel
			$name = $eUser['firstName'] ?? $eUser['lastName'];

			$members[] = [
				'name' => $eUser['me'] ? s("{value} (vous)", $name) : $name,
				'me' => $eUser['me'],
				'fiche' => $eUser['fiche'],
				'away' => $eUser['away'],
				'absent' => $eUser['absent'],
				'upload' => $eUser['eUserAvatarUpload']->notEmpty() ? [
					'url' => \user\UserAvatarUploadUi::url($eUser['eUserAvatarUpload'], $eStructure),
					'index' => $eUser['eUserAvatarUpload']['bakedIndex']
				] : NULL
			];

		}

		$h = '<div id="dashboard-head-team" class="dashboard-head-team" data-team="'.encode(json_encode(['members' => $members, 'silhouette' => $team['silhouette']], JSON_UNESCAPED_UNICODE)).'" onrender="DashboardTeam.start(this)">';

			if($members !== []) {
				$h .= new \scene\SceneUi()->getContainer('dashboard-head-team-scene', []);
			}

			if($team['cUserAbsentListed']->notEmpty()) {

				$h .= '<div class="dashboard-head-team-aside">';
					$h .= $this->getAbsences($eStructure, $week, $team['cUserAbsentListed'], $team['absentOthers'], $team['absentOthersLink']);
				$h .= '</div>';

			}

			$h .= '<span class="dashboard-head-team-name" hidden></span>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne par personne, son prénom puis ses jours en mots, sans deux-points entre les deux (planche v6) :
	 * « toute la semaine » quand l'absence prend tous les jours de son contrat, sinon les jours qui restent, aujourd'hui
	 * compris mais jamais nommé ainsi, la date étant en tête de l'accueil. Une demi-journée ou une plage d'heures ne se
	 * coupe pas d'une ligne à l'autre. Au-delà de quatre, une dernière ligne compte les autres et mène à l'onglet
	 * Absences du planning de la semaine (arbitrage 26), ou se dit en texte quand l'onglet refuserait la semaine.
	 */
	private function getAbsences(\structure\Structure $eStructure, string $week, \Collection $cUserAbsent, int $others, bool $othersLink): string {

		$h = '<div class="dashboard-head-team-absences">';

			$h .= '<h4>'.s("Absences").'</h4>';

			$h .= '<dl>';

				foreach($cUserAbsent as $eUser) {

					$h .= '<div>';
						$h .= '<dt>'.$eUser->getFirstName().'</dt> ';
						$h .= '<dd>'.($eUser['absenceWeek'] ? $this->getAbsencePieces([s("toute la semaine")]) : $this->getAbsenceDays($eUser['absenceDays'], $eUser['absenceHours'])).'</dd>';
					$h .= '</div>';

				}

			$h .= '</dl>';

			if($others > 0) {

				$label = p("et {value} autre", "et {value} autres", $others);

				if($othersLink) {
					$h .= '<a href="'.\planning\AbsenceUi::url($eStructure, $week).'" class="dashboard-head-team-absences-more">'.$label.'</a>';
				} else {
					$h .= '<span class="dashboard-head-team-absences-more">'.$label.'</span>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les jours d'une absence en morceaux qui ne se coupent pas (planche v6, arbitrage 30) : les jours et leurs
	 * demi-journées d'un seul tenant, puis chaque plage d'heures, seule à pouvoir se détacher de son jour à la ligne
	 * (« vendredi » puis « de 14 h à 16 h »). Le mot qui lie deux absences reste dans le morceau qui le précède, pour
	 * qu'une ligne ne commence pas par « et ».
	 */
	private function getAbsenceDays(array $days, array $hours): string {

		$pieces = [];
		$last = count($days) - 1;

		foreach(array_keys($days) as $position => $date) {

			$day = lcfirst(\util\DateUi::getDayName((int)date('N', strtotime($date))));

			$absence = match($days[$date]) {
				DashboardSetting::ABSENCE_DAY => [$day],
				// Le jour seul quand une demi-journée s'ajoute aux heures (`DashboardLib::getAbsenceHours()`)
				DashboardSetting::ABSENCE_HOURS => ($hours[$date] === []) ? [$day] : [$day, $this->getAbsenceHours($hours[$date])],
				DashboardSetting::ABSENCE_MORNING => [s("{value} matin", $day)],
				DashboardSetting::ABSENCE_AFTERNOON => [s("{value} après-midi", $day)]
			};

			if($pieces !== []) {

				$previous = array_pop($pieces);
				$first = array_shift($absence);

				$pieces[] = ($position === $last) ? s("{list} et {last}", ['list' => $previous, 'last' => $first]) : $previous.', '.$first;

			}

			array_push($pieces, ...$absence);

		}

		return $this->getAbsencePieces($pieces);

	}

	private function getAbsencePieces(array $pieces): string {
		return implode(' ', array_map(fn(string $piece) => '<span>'.$piece.'</span>', $pieces));
	}

	/**
	 * Les plages d'un jour, dans la forme de `\hr\AbsenceUi::getDates()` : « de 14 h à 16 h ». Le jour les précède
	 * dans un morceau à part (`getAbsenceDays()`).
	 */
	private function getAbsenceHours(array $ranges): string {

		$labels = array_map(fn(array $range) => s("de {from} à {to}", [
			'from' => \hr\AbsenceUi::getHour($range['from']),
			'to' => \hr\AbsenceUi::getHour($range['to'])
		]), $ranges);

		$last = array_pop($labels);

		return ($labels === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $labels), 'last' => $last]);

	}

	private function getSettings(\structure\Structure $eStructure): string {

		// La liste reste le frère immédiat du bouton : `Lime.Dropdown.getListFromButton()` la cherche par
		// `nextElementSibling` (`framework/util/static/js/lime.js:2091`), et rien ne se glisse entre les
		// deux.
		$h = '<a data-dropdown="bottom-end" class="dropdown-toggle btn btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';

			$h .= '<a href="'.self::urlPreference($eStructure).'" class="dropdown-item">'.s("Personnaliser mon tableau de bord").'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La météo du groupe Bureau, sous ses entrées (L52). Le bloc cite lui-même sa source, au bas du menu
	 * de son modèle (L82) : le socle n'y ajoute pas de pied.
	 */
	public function getHomeWeather(\structure\Structure $eStructure, array $entries): string {

		$h = '';

		foreach($entries as $name => $entry) {

			$ui = $this->getPackageUi($name);

			$h .= '<div id="'.self::getBlockId($name).'" class="dashboard-weather">';
				$h .= $ui->render($name, $eStructure, $entry['data']);
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * La file de ce qui attend un geste, en tuile sur tout le rang, en tête de la colonne « À faire »
	 * (L46). Elle n'a pas de titre à elle, celui de la colonne disant déjà ce qu'elle porte (retour
	 * du 14/10). Elle mêle les univers, donc elle n'a pas de tête d'univers, et chaque ligne
	 * nomme le sien.
	 *
	 * Une saisie faite sur l'accueil la redessine entière (`entry/view/entry.v.php`) : l'ordre de ses lignes
	 * suit leur échéance, qu'une écriture peut changer.
	 */
	public function getQueue(\structure\Structure $eStructure, array $entries): string {

		$h = '<div class="util-tile util-tile-wide dashboard-queue">';

			$h .= '<ul>';

				foreach($entries as $name => $entry) {

					$ui = $this->getPackageUi($name);

					$h .= '<li id="'.self::getBlockId($name).'">';
						$h .= $ui->render($name, $eStructure, $entry['data']);
						$h .= '<a href="'.$entry['url'].'" class="btn btn-outline-secondary">'.$ui->getUrlLabel($name).'</a>';
					$h .= '</li>';

				}

			$h .= '</ul>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'écart d'un chiffre de cellule, posé sous `getValue()` : rien quand
	 * `DashboardLib::getVariation()` n'a rien trouvé, la période comparée n'ayant alors plus de sujet.
	 */
	public function getComparison(?int $variation, string $compared): string {

		if($variation === NULL) {
			return '';
		}

		return '<div class="util-figure-variation">'.$this->getVariation($variation, self::getPercent($variation)).' '.$compared.'</div>';

	}

	/**
	 * Une baisse est neutre et non rouge : l'accueil n'accuse personne, et le rouge y est la couleur
	 * d'une facture échue. Seule une hausse prend la teinte de succès.
	 */
	private function getVariation(int $variation, string $text): string {
		return ($variation > 0) ? '<span class="util-figure-variation-up">'.$text.'</span>' : $text;
	}

	private static function getPercent(int $variation): string {
		return (($variation > 0) ? '+' : '−').abs($variation).' %';
	}

	/**
	 * Le chiffre d'une cellule, en grand et en couleur d'univers, puis ce qu'il compte. Le package dit
	 * quoi, le socle dit comment, comme pour `getChart()` : les blocs se lisent pareil d'une bande à
	 * l'autre parce qu'aucun ne dessine son propre chiffre.
	 *
	 * Les trois arguments arrivent formatés et traduits. `$unit` est le complément en petit collé au
	 * chiffre (« TTC », « kg »), `$label` la ligne dessous, qui peut manquer quand le complément dit déjà
	 * tout. Deux chiffres d'une même cellule se posent côte à côte dans un `dashboard-univers-pair`.
	 *
	 * Le chiffre et son complément sont insécables, sans quoi « 45 108 € TTC » laisse « TTC » seul sur
	 * la ligne suivante dès que la tuile se resserre. Sans complément, il n'y a rien à tenir ensemble, et
	 * la valeur garde ses coupures : la date d'une distribution AMAP en tient lieu.
	 */
	public static function getValue(string $value, ?string $label = NULL, ?string $unit = NULL): string {

		$h = '<div class="util-tile-figure">';

			$h .= '<div class="util-figure-value">'.(($unit === NULL) ? $value : '<span class="util-unit">'.$value.' <small>'.$unit.'</small></span>').'</div>';

			if($label !== NULL) {
				$h .= '<div class="util-figure-label">'.$label.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une liste repliée sous son compte, que le clic déplie. `$open` la rend dépliée, pour le cas où le
	 * geste qui a recomposé le bloc visait justement ce qu'elle contient.
	 *
	 * `<details>` et non un bouton : le pli tient sans une ligne de JS. Une réponse qui remplace le bloc
	 * le rend replié, d'où `$open`, que seul le package sait poser.
	 */
	public static function getFold(string $summary, string $content, bool $open = FALSE): string {

		$h = '<details class="dashboard-fold"'.($open ? ' open' : '').'>';
			$h .= '<summary>'.\Asset::icon('chevron-right').' '.$summary.'</summary>';
			$h .= $content;
		$h .= '</details>';

		return $h;

	}

	/**
	 * La bande de jours d'un bloc, que le `render()` d'un bloc appelle : une tuile par jour, celle du
	 * jour choisi marquée par son fond, chacune rappelant le bloc sur son jour.
	 *
	 * Le package dit quoi, le socle dit comment, comme pour une cellule de chiffres : deux blocs la
	 * portent aujourd'hui, et la rangée doit se lire pareil de l'un à l'autre. Chaque entrée de `$days`
	 * porte `date`, `label`, `value` et `state`, les trois derniers déjà traduits ; `value` et `state`
	 * valent `NULL` quand le jour n'a rien à en dire. `date` est la chaîne que le clic rejoue en
	 * `?focus=`, transportée sans être interprétée : un package peut y poser une tuile qui n'est pas un
	 * jour, comme « Sans jour » sur la carte des interventions, et c'est son `load()` qui la reconnaît.
	 *
	 * Le geste est un `data-ajax` en GET, donc `Ajax.Query` avec son verrou anti-double-envoi et son
	 * effacement des erreurs précédentes, sans une ligne de JS à charger. La réponse ne rend que le nœud
	 * du bloc : c'est la route de `urlBlock()`, celle qui remplit déjà l'emplacement d'un bloc lourd.
	 */
	public function getDays(\structure\Structure $eStructure, string $block, array $days, string $selected): string {

		// Aujourd'hui se lit ici et non dans la donnée d'un bloc : la bande se lit pareil d'un bloc à
		// l'autre, et le jour qui est aujourd'hui pour l'un l'est pour tous. Un package qui aurait à le
		// dire pourrait l'oublier, et la marque tomberait sans qu'on voie pourquoi.
		$today = \Clock::date();

		$h = '<div class="util-figures dashboard-days">';

			foreach($days as $day) {
				$h .= $this->getDay($eStructure, $block, $day, ($day['date'] === $selected), ($day['date'] === $today));
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une tuile de la bande. Le nom du jour est sa première ligne les sept jours, ce qui change d'un
	 * jour à l'autre vient dessous : un jour sans rien garde sa tuile, qui sert à le choisir, et ne fait
	 * pas remonter son nom.
	 *
	 * Ce que la tuile dit de son jour vit dans son propre nœud, que le package peut remplacer seul quand
	 * un de ses gestes change un compte : la marque de sélection reste alors sur la tuile, que la
	 * réponse ciblée ne touche pas et n'a donc pas à connaître. Celle d'aujourd'hui est du même côté, et
	 * pour la même raison.
	 *
	 * Aujourd'hui et le jour choisi sont deux marques de nature différente : le jour choisi prend le
	 * fond, aujourd'hui prend le gras de son nom. Deux fonds sur une même bande se liraient comme deux
	 * jours choisis, et les six autres jours n'ont alors plus rien qui les distingue d'aujourd'hui dès
	 * qu'on regarde ailleurs.
	 */
	public function getDay(\structure\Structure $eStructure, string $block, array $day, bool $selected, bool $today): string {

		$classes = 'util-figure'.($selected ? ' dashboard-day-selected' : '').($today ? ' dashboard-day-today' : '');

		$h = '<a id="'.self::getDayId($block, $day['date']).'" class="'.$classes.'" data-ajax="'.self::urlBlock($eStructure, $block, $day['date']).'" data-ajax-method="get">';

			$h .= '<div class="util-figure-label">'.$day['label'].'</div>';
			$h .= $this->getDayState($block, $day);

		$h .= '</a>';

		return $h;

	}

	public function getDayState(string $block, array $day): string {

		$h = '<div id="'.self::getDayStateId($block, $day['date']).'" class="dashboard-day-state">';

			if($day['value'] !== NULL) {
				$h .= '<div class="util-figure-value">'.$day['value'].'</div>';
			}

			if($day['state'] !== NULL) {
				$h .= '<div class="util-annotation">'.$day['state'].'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public static function getDayId(string $block, string $date): string {
		return 'dashboard-day-'.$block.'-'.$date;
	}

	public static function getDayStateId(string $block, string $date): string {
		return 'dashboard-day-state-'.$block.'-'.$date;
	}

	/**
	 * Une ligne de ce qui attend un geste, que le `render()` d'une contribution appelle : la pastille de
	 * son univers, un nombre, ce que c'est et pourquoi ça attend. Le bouton est au socle, qui pose
	 * l'adresse et les mots du package.
	 *
	 * `$waitingDays` est la clé de rangement que le package a déjà rendue : une ligne qui attend depuis
	 * plus longtemps que son échéance porte son nombre dans un `badge-danger`, et la règle s'écrit ici
	 * une fois pour toute la file.
	 */
	public function getQueueLine(string $section, int $count, string $what, string $why, ?int $waitingDays): string {

		$late = ($waitingDays !== NULL and $waitingDays > 0);

		$h = '<div class="util-domain util-domain-'.$section.' util-domain-inline">'.$this->getSectionIcon($section).' '.$this->getSectionName($section).'</div>';

		$h .= '<span class="dashboard-queue-what">';
			$h .= ($late ? '<span class="badge badge-danger">'.$count.'</span>' : '<b>'.$count.'</b>').' '.$what;
			$h .= '<small>'.$why.'</small>';
		$h .= '</span>';

		return $h;

	}

	/**
	 * La semaine en toutes lettres, « du 21 au 27 septembre » quand elle tient dans un mois et « du 28
	 * septembre au 4 octobre » sinon. `DateUi::weekToDays()` rend les deux mêmes jours séparés d'un
	 * tiret ; la maquette arrêtée le 2026-09-28 écrit une phrase, et la phrase est ce que l'en-tête lit
	 * à voix haute.
	 */
	private static function getWeekDays(string $weekFrom, string $weekTo): string {

		if(substr($weekFrom, 5, 2) === substr($weekTo, 5, 2)) {
			return s("du {from} au {to}", ['from' => (int)substr($weekFrom, 8, 2), 'to' => \util\DateUi::textual($weekTo, \util\DateUi::DAY_MONTH)]);
		} else {
			return s("du {from} au {to}", ['from' => \util\DateUi::textual($weekFrom, \util\DateUi::DAY_MONTH), 'to' => \util\DateUi::textual($weekTo, \util\DateUi::DAY_MONTH)]);
		}

	}

	/**
	 * Une bande par univers, dans l'ordre où le moteur les a rangés, de toute la largeur de l'écran, son
	 * contenu dans le conteneur : la forme du tableau de bord d'une AMAP (`getUnivers()`), l'accueil et
	 * son réglage rangeant en colonnes (`getColumns()`). L'univers se lit au titre de sa bande et à la
	 * couleur des intitulés et des chiffres de ses cellules.
	 *
	 * Une seule bande prend un fond, la première de Vente ou de Gestion, avec le coup de pinceau du
	 * produit en haut et en bas (arbitré le 2026-10-05, planche
	 * `dashboard-l10-h2-une-bande-planche`, lecture 1). Les autres restent sur le fond de la page : une
	 * teinte par univers faisait autant de bandes que d'univers, et plus aucune ne ressortait. La règle
	 * se décide ici et non dans la feuille, `:first-of-type` ne sachant pas sauter une bande de
	 * Production ou d'AMAP placée avant.
	 *
	 * Les cellules sont une grille `auto-fit` : un univers à une cellule prend toute la largeur, et une
	 * cellule lourde qui retire son nœud après le rendu rend sa piste aux autres sans que la page ait à
	 * le savoir.
	 */
	private function getSections(\structure\Structure $eStructure, array $blocks, bool $underHero): string {

		$sections = [];

		foreach($blocks as $block => $entry) {
			$sections[$entry['declaration']['section']][$block] = $entry;
		}

		$colored = $this->getColoredSection(array_keys($sections));

		$h = '';

		foreach($sections as $section => $sectionBlocks) {
			$h .= $this->getSection($eStructure, $section, $sectionBlocks, ($section === $colored), $underHero);
		}

		return $h;

	}

	private function getSection(\structure\Structure $eStructure, string $section, array $blocks, bool $colored, bool $underHero): string {

		$h = '<section class="dashboard-univers util-domain-'.$section.($colored ? ' is-colored util-brushed' : '').'">';

			if($colored) {
				$h .= \main\DesignUi::getBrushSeparator('top');
			}

			$h .= '<div class="container util-board">';

				if($this->hasSectionTitle($section)) {

					$h .= '<div class="util-board-title">';
						$h .= '<span class="util-icon util-icon-sm util-icon-'.$section.'">'.$this->getSectionIcon($section).'</span>';
						$h .= $this->getSectionName($section);
					$h .= '</div>';

				}

				// Un bloc sur tout le rang (`WIDE`) passe avant la grille, sous le même titre d'univers : dans la
				// grille, `auto-fit` lui donnerait une piste comme aux autres.
				$blocksWide = array_filter($blocks, fn(array $entry) => $entry['declaration']['shape'] === DashboardSetting::WIDE);

				if($blocksWide !== []) {

					$h .= '<div class="dashboard-univers-wide">';

						foreach($blocksWide as $block => $entry) {
							$h .= $this->getBlock($eStructure, $block, $entry, underHero: $underHero);
						}

					$h .= '</div>';

				}

				$blocksGrid = array_diff_key($blocks, $blocksWide);

				if($blocksGrid !== []) {

					$h .= '<div class="util-board-columns">';

						foreach($blocksGrid as $block => $entry) {
							$h .= $this->getBlock($eStructure, $block, $entry);
						}

					$h .= '</div>';

				}

			$h .= '</div>';

			if($colored) {
				$h .= \main\DesignUi::getBrushSeparator('bottom');
			}

		$h .= '</section>';

		return $h;

	}

	/**
	 * Le geste de chaque occupant, dans l'en-tête de la page, y compris quand il n'a rien à dire : la page
	 * vide ne coupe pas le chemin vers la source. Il mène hors du produit (le blog), d'où le nouvel
	 * onglet que `getTarget()` décide.
	 */
	public function getNewsLinks(array $urls): string {

		$h = '';

		foreach($urls as $name => $url) {
			$h .= '<a href="'.$url.'" class="btn btn-outline-secondary"'.self::getTarget($url).'>'.\Asset::icon('box-arrow-up-right').' '.$this->getPackageUi($name)->getUrlLabel($name).'</a>';
		}

		return $h;

	}

	/**
	 * Le corps de la page « Du nouveau » (L50) : chaque occupant de l'emplacement des nouvelles rendu par
	 * sa paire. La page porte le nom de l'entrée qui l'ouvre ; un occupant ne pose son titre en tête de
	 * sa part que s'ils sont plusieurs, seul il redirait celui de la page.
	 *
	 * La page s'ouvre depuis n'importe quel écran de la structure : `#dashboard-news` borne la feuille du
	 * socle, que la navigation ajax laisse en place après elle.
	 */
	public function getNews(\structure\Structure $eStructure, array $entries): string {

		if($entries === []) {
			return '<div class="util-block-empty"><h3>'.s("Rien de neuf pour l'instant").'</h3></div>';
		}

		$h = '<div id="dashboard-news">';

			foreach($entries as $name => $entry) {

				$ui = $this->getPackageUi($name);

				if(count($entries) > 1) {
					$h .= '<h3'.($name === array_key_first($entries) ? '' : ' class="mt-2"').'>'.$ui->getTitle($name, $entry['data']).'</h3>';
				}

				$h .= $ui->render($name, $eStructure, $entry['data']);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le graphique du bilan, que le `render()` de son occupant appelle : dix barres en grand, la plus
	 * récente pleine et les autres atténuées, l'échelle au-dessus et les semaines dessous. Le package dit
	 * quoi (`$bars`, `$labels`, et `$scale` qui écrit le maximum dans son unité), le socle dit comment,
	 * comme pour `getValue()`.
	 *
	 * `dashboard-bilan-max` et `dashboard-bilan-axis` accompagnent les classes du système de design pour
	 * en relever la taille de texte à cette hauteur de barres (`dashboard.css`).
	 */
	public function getChart(array $bars, array $labels, \Closure $scale): string {

		// L'échelle des barres, et non une donnée : le maximum n'existe que parce que les hauteurs s'y
		// rapportent, et aucun Lib ne peut le rendre sans décider du dessin à la place du socle.
		$max = max($bars);

		$h = '<div class="dashboard-bilan-chart">';

			$h .= '<div class="util-bars-max dashboard-bilan-max">'.$scale($max).'</div>';

			$h .= '<div class="util-bars" style="--bars: '.count($bars).'">';
				foreach($bars as $bar) {
					$h .= '<span style="--bar: '.round(($max > 0) ? $bar / $max : 0, 3).'"></span>';
				}
			$h .= '</div>';

			$h .= '<div class="util-bars-axis dashboard-bilan-axis" style="--bars: '.count($labels).'">';
				foreach($labels as $label) {
					$h .= '<span>'.$label.'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/** Publique pour que le test la lise sans rendre l'écran entier. `NULL` quand aucune bande ne se colore. */
	public function getColoredSection(array $sections): ?string {

		foreach($sections as $section) {

			if(in_array($section, ['commercialisation', 'accounting'], TRUE)) {
				return $section;
			}

		}

		return NULL;

	}

	/**
	 * Un bloc de l'accueil : son intitulé en couleur d'univers, son intérieur rendu par son package, et
	 * ses gestes en pied.
	 *
	 * Il porte lui-même la teinte de son univers (`util-domain-<section>`), et non ce qui l'entoure : une
	 * colonne de l'accueil mêle les univers, et cette méthode rend aussi un bloc seul, quand sa route le
	 * remplit ou qu'un de ses gestes le redessine.
	 *
	 * `$column` est la colonne de l'accueil qui le reçoit, et en fait une tuile (L46), au filet sous la
	 * tête de son univers (L71, variante C6 du design system). Chaque appelant la nomme, parce que le
	 * bilan, qui passe aussi par ici, ne déclare pas de colonne. Sans colonne, le bloc est une cellule
	 * des bandes d'univers d'une AMAP (`getCell()`). La `shape` de la déclaration étend la tuile sur le
	 * rang, ou en fait la feuille épinglée du planning, que le socle pose avec sa punaise.
	 *
	 * Sans donnée, le bloc est lourd et son contenu arrive par sa propre route, qui remplace ce nœud ou
	 * le retire quand le bloc n'a finalement rien à dire. L'emplacement ne montre donc rien, ni titre ni
	 * pied : posés d'avance, ils paraissaient puis disparaissaient chez qui n'avait rien à voir (L38,
	 * « Ventes à venir et récoltes » de #3597). N'étant ni une tuile ni une colonne, il ne prend aucune
	 * piste, et une colonne qui n'a que lui reste cachée.
	 * L'appel est écrit en attribut plutôt qu'en JS : rien d'autre à charger, et la requête part dès
	 * que le nœud est rendu, y compris après une navigation ajax.
	 *
	 * `$scope` met le bloc en réglage (L43) : c'est l'univers que son interrupteur poste. Le titre prend
	 * alors sa poignée et son interrupteur, et le « Choisir » se retire : il règle ce que le bloc montre,
	 * et le réglage règle l'accueil.
	 */
	public function getBlock(\structure\Structure $eStructure, string $block, array $entry, ?string $scope = NULL, ?string $column = NULL, bool $underHero = FALSE): string {

		$setting = ($scope !== NULL);

		// Un bloc ne se range que parmi ceux de son univers : `data-section` est ce que `preference.js`
		// compare pendant le geste, sur la cible comme sur ce qui s'estompe. Un bloc éteint n'a pas de rang
		// à prendre, `doUpdatePositions` ne range que les allumés. Un bloc à découvert, sans poignée, reste
		// compté : `preference.js` envoie l'univers entier, que la route refuse incomplet.
		$sortable = ($setting and $entry['chosen']);

		$attributes = ($setting ? ' data-section="'.encode($scope).'"' : '').($sortable ? ' data-sort-id="'.encode($block).'"' : '');

		// Verrou au nom du bloc : sans lui, la clé est `Date.now()` posé au premier envoi (`getLockId()`,
		// `ajax.js`), deux emplacements rendus dans la même milliseconde partagent la même, et le second
		// lève LOCK_FAILED, que `window.onerror` avale. Il restait vide (L13, deux fois sur huit
		// chargements de #6947).
		if($entry['data'] === NULL and ($setting === FALSE or $entry['frame'] === FALSE)) {
			return '<div id="'.self::getBlockId($block).'" class="dashboard-block-pending"'.$attributes.' '.attr('onrender', 'new Ajax.Query(this).lockAs(\''.$block.'\').method(\'GET\').url(\''.self::urlBlock($eStructure, $block, setting: $setting).'\').fetch()').'></div>';
		}

		if($column === NULL) {
			return $this->getCell($eStructure, $block, $entry, $underHero);
		}

		$ui = $this->getPackageUi($block);

		$title = $ui->getTitle($block, $entry['data']);

		$shape = $entry['declaration']['shape'];

		$classes = match($shape) {
			DashboardSetting::TILE => 'util-tile',
			DashboardSetting::WIDE => 'util-tile util-tile-wide',
			DashboardSetting::NOTE => 'util-note util-note-wide',
			DashboardSetting::OPEN => 'dashboard-open'
		};

		// Seule une tuile prend la tête de son univers, avec l'icône qui seule distingue les cinq teintes :
		// la feuille garde son papier, et le bloc à découvert est titré comme une colonne, étant une section
		// de la page et non une tuile. En réglage, la tête passe à la rangée qui porte l'intitulé avec sa
		// poignée et son interrupteur, `util-tile-head` ne se posant que sur un enfant direct de la tuile.
		$tile = in_array($shape, [DashboardSetting::TILE, DashboardSetting::WIDE], TRUE);
		$open = ($shape === DashboardSetting::OPEN);

		if($open) {
			$heading = '<h2 class="util-board-title">'.$title.'</h2>';
		} else if($tile) {
			$heading = '<h3 class="util-domain'.($setting ? '' : ' util-tile-head').'">'.$this->getSectionIcon($entry['declaration']['section']).' '.$title.'</h3>';
		} else {
			$heading = '<h3 class="util-domain">'.$title.'</h3>';
		}

		$h = '<div id="'.self::getBlockId($block).'" class="'.$classes.' util-domain-'.$entry['declaration']['section'].(($setting and $entry['chosen'] === FALSE) ? ' dashboard-block-off' : '').'"'.$attributes.'>';

			// La punaise ouvre la feuille, au-dessus de son intitulé, comme sur la Semaine du planning.
			if($shape === DashboardSetting::NOTE) {
				$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';
			}

			if($setting) {

				// Hors de la grille, le bloc à découvert n'a pas de voisin parmi lesquels se glisser.
				$h .= '<div class="util-title dashboard-setting-title'.($tile ? ' util-tile-head' : '').'">';
					$h .= ($sortable and $open === FALSE) ? \main\DesignUi::getDragHandle() : '';
					$h .= $heading;
					$h .= new PreferenceUi()->getBlockSwitch($eStructure, $scope, $block, $entry['chosen']);
				$h .= '</div>';

				// Un cadre n'a ni contenu ni pied : il n'a rien lu, et un pied mènerait à une liste dont
				// l'accueil ne montre rien.
				if($entry['frame']) {
					return $h.'</div>';
				}

			} else {
				$h .= $heading;
			}

			// Dans une tuile ou une feuille, quelle que soit sa colonne, une carte que le package pose ferait une
			// boîte dans une boîte : `util-flat` les rend à plat. Sur le fond de la page, le bloc à découvert
			// garde les siennes, celles que le design system dessine hors de toute tuile.
			$h .= '<div class="util-board-column-body'.($open ? '' : ' util-flat').'">'.$ui->render($block, $eStructure, $entry['data']).'</div>';

			// Le « Choisir » ouvre un panneau par-dessus l'accueil, le lien suivant mène à l'écran qui porte
			// la liste : deux liens d'un même pied, en texte, là où un bouton pâle par bloc en posait sept au
			// même poids sur l'accueil (L46).
			$h .= '<div class="util-mores">';
				$h .= ($entry['declaration']['choose'] and $setting === FALSE) ? '<a href="'.self::urlChoice($eStructure, $block).'" class="util-more">'.s("Choisir").'</a>' : '';
				$h .= '<a href="'.$entry['url'].'" class="util-more"'.self::getTarget($entry['url']).'>'.$ui->getUrlLabel($block).'</a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La cellule d'un bloc dans une bande d'univers d'une AMAP (`getSections()`) : ni fond ni bordure, le
	 * tableau du socle la séparant de ses voisines par un filet. Les bandes n'ont pas de réglage.
	 *
	 * Un bloc `WIDE` passe avant la grille et ouvre la bande : il est titré comme une section d'une page du
	 * produit, un rang au-dessus des titres de colonne qu'il précède ; les autres sont titrés comme une
	 * colonne. Il ne doit jamais se lire comme le titre de la page : sous la tête ordinaire, celle de
	 * toutes les pages du produit, c'est un `h3`, le titre de section ordinaire ; sous une bande
	 * d'accueil, dont le titre domine la page, c'est un `h2` nu.
	 */
	private function getCell(\structure\Structure $eStructure, string $block, array $entry, bool $underHero): string {

		$ui = $this->getPackageUi($block);

		$title = $ui->getTitle($block, $entry['data']);

		$section = $entry['declaration']['section'];

		$heading = match($entry['declaration']['shape']) {
			DashboardSetting::WIDE => $underHero ? '<h2>'.$title.'</h2>' : '<h3>'.$title.'</h3>',
			DashboardSetting::TILE, DashboardSetting::NOTE, DashboardSetting::OPEN => '<h2 class="util-board-column-title">'.$title.'</h2>'
		};

		$h = '<div id="'.self::getBlockId($block).'" class="util-board-column util-domain-'.$section.'">';

			if($entry['declaration']['choose']) {

				// Le réglage de ce que le bloc montre se pose à droite de son titre, et non en pied : le
				// pied mène à un autre écran, ce bouton-ci ouvre un panneau par-dessus celui-ci.
				$h .= '<div class="util-title">';
					$h .= $heading;
					$h .= '<a href="'.self::urlChoice($eStructure, $block).'" class="btn btn-sm '.$this->getSectionButton($section).'">'.\Asset::icon('sliders').' '.s("Choisir").'</a>';
				$h .= '</div>';

			} else {
				$h .= $heading;
			}

			$h .= '<div class="util-board-column-body">'.$ui->render($block, $eStructure, $entry['data']).'</div>';

			// Le geste vers l'écran qui porte la liste est en pied, et non en haut à droite du titre : un
			// bouton posé là ferait quatre poids voisins sur les quatre premières lignes de la cellule, et
			// le titre cesserait de se lire comme un titre.
			$h .= '<div class="util-board-column-foot">';
				$h .= $this->getFootLink($entry['url'], $section, $ui->getUrlLabel($block));
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le geste de pied d'une cellule d'AMAP, vers l'écran qui porte sa liste.
	 */
	private function getFootLink(string $url, string $section, string $label): string {
		return '<a href="'.$url.'" class="btn '.$this->getSectionButton($section).'"'.self::getTarget($url).'>'.\Asset::icon('arrow-right-short').' '.$label.'</a>';
	}

	/**
	 * Une adresse qui porte un schéma est celle d'un tiers, les écrans du produit étant rendus en
	 * chemin : elle s'ouvre dans un nouvel onglet, comme tout lien externe du produit.
	 */
	private static function getTarget(string $url): string {
		return str_contains($url, '://') ? ' target="_blank"' : '';
	}

	/**
	 * Le panneau du « Choisir » d'un bloc : son titre est celui du bloc, son contenu celui que le package
	 * rend. Fermer ne recharge rien, chaque geste du panneau redessinant le bloc derrière lui.
	 */
	public function getChoice(\structure\Structure $eStructure, string $block, array $choice): \Panel {

		$ui = $this->getPackageUi($block);

		return new \Panel(
			id: 'panel-dashboard-choice',
			title: $ui->getTitle($block),
			body: $ui->getChoice($block, $eStructure, $choice)
		);

	}

	/**
	 * Le `DashboardUi` d'un package, résolu par le préfixe du nom du bloc.
	 *
	 * Rendu `object` et non un type nommé : ces classes n'ont ni ancêtre ni interface commune, c'est
	 * leur nom que la convention résout. Leur inventaire de méthodes est tenu par
	 * `test/DashboardBlockSentinel.t.php`, pas par le typage.
	 */
	private function getPackageUi(string $block): object {

		$class = '\\'.DashboardSetting::getPackage($block).'\\DashboardUi';

		return new $class();

	}

	/** Publique pour que la sentinelle vérifie qu'aucune colonne de `DashboardSetting::COLUMNS` n'est sans titre. */
	public function getColumnName(string $column): string {

		return match($column) {
			DashboardSetting::TODO => s("À faire"),
			DashboardSetting::FOLLOW => s("À suivre"),
			DashboardSetting::REVIEW => s("Bilan")
		};

	}

	/**
	 * Publique : le panneau de personnalisation nomme les mêmes univers avec les mêmes mots, et les
	 * recopier les ferait diverger le jour où l'un change.
	 */
	public function getSectionName(string $section): string {

		return match($section) {
			'production' => s("Production"),
			'amap' => s("AMAP"),
			'commercialisation' => s("Vente"),
			'accounting' => s("Gestion"),
			'settings' => s("Paramètres")
		};

	}

	/** Publique pour la même raison que `getSectionName()` : le panneau pose la même icône. */
	public function getSectionIcon(string $section): string {

		return match($section) {
			'production' => \Asset::icon('leaf'),
			'amap' => \Asset::icon('people'),
			'commercialisation' => \Asset::icon('basket3'),
			'accounting' => \Asset::icon('piggy-bank'),
			'settings' => \Asset::icon('sliders')
		};

	}

	/**
	 * La bande des paramètres ne se titre pas : ce qu'elle range en pied d'accueil, ce sont des
	 * fonctionnalités à essayer, et « Paramètres » au-dessus dirait le contraire (condition de
	 * V-Dashboard du 2026-10-05, L12). Le nom reste celui du menu, que la sentinelle compare, et le
	 * panneau de personnalisation continue de nommer la section.
	 *
	 * La bande de l'AMAP non plus : elle est seule sur le tableau de bord de l'AMAP
	 * (`DashboardLib::loadSection()`), et « AMAP » au-dessus redirait le nom de la page (retour
	 * du 2026-10-21).
	 */
	private function hasSectionTitle(string $section): bool {

		return match($section) {
			'production', 'commercialisation', 'accounting' => TRUE,
			'amap', 'settings' => FALSE
		};

	}

	/**
	 * La teinte du geste de pied, celle de l'univers du bloc.
	 *
	 * ⚠️ `settings` prend `btn-light`, la variante neutre que `component.css` documente, parce que
	 * `btn-settings-light` n'y est pas écrite : les quatre autres univers ont leur `.btn-*-light`,
	 * celui-là non. Le jour où elle y entre, cette branche rejoint les autres.
	 */
	private function getSectionButton(string $section): string {

		return match($section) {
			'production' => 'btn-production-light',
			'amap' => 'btn-amap-light',
			'commercialisation' => 'btn-commercialisation-light',
			'accounting' => 'btn-accounting-light',
			'settings' => 'btn-light'
		};

	}

}
?>
