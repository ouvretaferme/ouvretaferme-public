<?php
namespace learning;

/**
 * Le catalogue des formations et « Mes formations ».
 *
 * Les filtres passent par l'adresse : un niveau se choisit par un lien, une thématique par une
 * liste qui renvoie le formulaire dès qu'elle change.
 *
 * Le catalogue public suit la planche catalogue-attractif v3 : la tête (`getHead()`), la barre
 * d'onglets quand l'agenda a des dates (`getTabs()`), les filtres (`getSearch()`), puis l'onglet
 * ouvert (`getFormations()` ou `getAgenda()`), assemblés par la vue.
 */
class CatalogUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * La tête du catalogue, pour tous, visiteur ou compte : le titre dans `util-welcome` avec trois
	 * amis. La loupe ne s'y pose que sans barre d'onglets (`$withSearch`), un onglet seul ne
	 * choisissant rien ; sinon elle est à droite de la barre (`getTabs()`).
	 */
	public function getHead(bool $isLogged, bool $withSearch): string {

		$buttons = '';

		if($withSearch) {
			$buttons .= self::getSearchButton();
		}

		if($isLogged) {
			$buttons .= '<a href="/mes-formations" class="btn btn-outline-primary">'.\Asset::icon('mortarboard').' '.s("Mes formations").'</a>';
		}

		// Écrit à la main et non par DesignUi::getWelcome() : le titre est celui de la page, en h1,
		// et la mascotte une rangée de trois personnages, pas un portrait.
		$h = '<div class="util-welcome">';
			$h .= '<div class="util-welcome-text">';
				$h .= '<h1 class="util-welcome-title">'.s("Se former à Ouvretaferme et à l'agriculture").'</h1>';
				if($buttons !== '') {
					$h .= '<div class="util-welcome-buttons">'.$buttons.'</div>';
				}
			$h .= '</div>';
			$h .= '<div class="util-welcome-mascot learning-friends">';
				foreach(['escargot', 'abeille', 'coccinelle-rouge'] as $friend) {
					$h .= \Asset::svg('main', 'friends/'.$friend.'.svg');
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function getEmpty(): string {

		$h = '<div class="util-block util-block-empty">';
			$h .= '<h3>'.s("Aucune formation publiée pour le moment").'</h3>';
			$h .= '<p>'.s("Les formations de l'équipe Ouvretaferme paraîtront ici dès leur publication.").'</p>';
		$h .= '</div>';

		return $h;

	}

	protected static function getSearchButton(): string {
		return '<a '.attr('onclick', 'Lime.Search.toggle("#learning-catalog-search")').' class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('search').'</a>';
	}

	/**
	 * Les deux onglets et leurs compteurs, chacun comptant ce qu'il montre : des formations d'un côté,
	 * des dates de l'autre. Les liens gardent les filtres posés, et la loupe se pose à droite de la
	 * barre, sur la même ligne jusqu'à 360 px (`learning-tabs`).
	 */
	public function getTabs(Theme $eThemeSelected, Subtheme $eSubthemeSelected, Level $eLevelSelected, string $tab, int $courses, int $dates): string {

		$h = '<div class="tabs learning-tabs">';
			$h .= '<div class="tabs-item-wrapper">';
				$h .= '<div class="tabs-item">';
					foreach([
						'formations' => [s("Formations"), $courses],
						'dates' => [s("Prochaines dates"), $dates],
					] as $key => [$label, $count]) {
						$h .= '<a href="'.self::urlFilter($eThemeSelected, $eSubthemeSelected, $eLevelSelected, $key).'" class="tab-item'.($key === $tab ? ' selected' : '').'">'.$label.' <span class="util-counter util-counter-solid">'.$count.'</span></a>';
					}
				$h .= '</div>';
				$h .= '<div class="tabs-item-label">'.self::getSearchButton().'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les filtres, repliés derrière la loupe et ouverts dès qu'un filtre est posé. Ils valent pour les
	 * deux onglets : celui des dates se garde en champ caché.
	 */
	public function getSearch(\Collection $cTheme, \Collection $cSubtheme, \Collection $cLevel, Theme $eThemeSelected, Subtheme $eSubthemeSelected, Level $eLevelSelected, string $tab, bool $isFiltered): string {

		$h = '<div id="learning-catalog-search" class="util-block-search'.($isFiltered ? '' : ' hide').'">';
			$h .= $this->getFilters($cTheme, $cSubtheme, $cLevel, $eThemeSelected, $eSubthemeSelected, $eLevelSelected, $tab);
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'onglet Formations. Sans filtre, « Premiers pas », choisis par l'équipe, puis une rangée par
	 * thématique sans eux, tronquée par la page, que « Voir les N formations » ouvre sur la
	 * thématique filtrée. Une carte sans vignette porte l'ami de sa thématique, alterné dans la rangée.
	 */
	public function getFormations(\Collection $cCourseFeatured, \Collection $cThemeCatalog, int $courses, bool $isLogged): string {

		if($courses === 0) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune formation ne répond à ces filtres").'</h3>';
				$h .= '<p>'.s("Élargissez la thématique, la sous-thématique ou le niveau pour retrouver le catalogue.").'</p>';
			$h .= '</div>';

			return $h;

		}

		$courseUi = new CourseUi();

		$h = '';

		if($cCourseFeatured->notEmpty()) {

			$h .= '<div class="util-title mt-2">';
				$h .= '<h2>'.s("Premiers pas").'</h2>';
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-3">';
				$position = 0;
				foreach($cCourseFeatured as $eCourse) {
					$h .= $courseUi->getCard($eCourse, $isLogged, FALSE, position: $position++);
				}
			$h .= '</div>';

		}

		foreach($cThemeCatalog as $eTheme) {

			$h .= '<div class="util-title mt-2">';
				$h .= '<h2 class="learning-theme-title">'.ThemeUi::getIcon($eTheme).' '.encode($eTheme['name']).'</h2>';
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-3">';
				$position = 0;
				foreach($eTheme['cCourse'] as $eCourse) {
					$h .= $courseUi->getCard($eCourse, $isLogged, FALSE, position: $position++);
				}
			$h .= '</div>';

			if($eTheme['more']) {
				$h .= '<a href="'.self::urlFilter($eTheme, new Subtheme(), new Level()).'" class="btn btn-outline-primary mt-1">'.p("Voir la formation", "Voir les {value} formations", $eTheme['courses']).'</a>';
			}

		}

		return $h;

	}

	/**
	 * L'onglet Prochaines dates : une ligne par date à venir, rangée sous son mois, jusqu'à la borne
	 * que la page pose, puis « Voir les N autres dates ». Une formation à plusieurs dates paraît une
	 * fois par date.
	 */
	public function getAgenda(\Collection $ccDateMonth, int $dates, int $datesRemaining, Theme $eThemeSelected, Subtheme $eSubthemeSelected, Level $eLevelSelected): string {

		if($dates === 0) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune date à venir ne répond à ces filtres").'</h3>';
				$h .= '<p>'.s("Élargissez la thématique, la sous-thématique ou le niveau pour retrouver les prochaines dates.").'</p>';
			$h .= '</div>';

			return $h;

		}

		$h = '';
		$first = TRUE;

		foreach($ccDateMonth as $month => $cDate) {

			$h .= '<h3'.($first ? '' : ' class="mt-2"').'>'.ucfirst(\util\DateUi::textual($month.'-01', \util\DateUi::MONTH_YEAR)).'</h3>';
			$h .= '<div class="util-cards util-cards-2 mb-1">';
				foreach($cDate as $eDate) {
					$h .= $this->getAgendaDate($eDate);
				}
			$h .= '</div>';

			$first = FALSE;

		}

		if($datesRemaining > 0) {
			$h .= '<a href="'.self::urlFilter($eThemeSelected, $eSubthemeSelected, $eLevelSelected, 'dates', TRUE).'" class="btn btn-outline-primary">'.p("Voir l'autre date", "Voir les {value} autres dates", $datesRemaining).'</a>';
		}

		return $h;

	}

	/**
	 * Une date de l'agenda, dans la forme des dates de la fiche publique : la pastille pleine à la
	 * teinte de la section, le titre et la marque du type, les jours et les heures, puis le lieu et
	 * l'auteur, le visuel à droite. La ligne ne redit ni l'année, portée par le titre du mois, ni
	 * « En visio », porté par la marque.
	 */
	protected function getAgendaDate(Date $eDate): string {

		$eCourse = $eDate['course'];

		$section = $eCourse['theme']->getSection();
		$sequences = DateUi::getDistinctSequences($eDate);

		if(count($sequences) === 1) {

			$eDateSequence = $sequences[0];

			$line = DateUi::getDays($eDate, withYear: FALSE).' · '.str_replace(' ', '&nbsp;', DateUi::getHours($eDateSequence));
			$where = match($eDateSequence['mode']) {
				DateSequence::ONSITE => DateUi::getSequenceWhere($eDateSequence),
				DateSequence::REMOTE => NULL
			};

		} else {
			$line = DateUi::getDays($eDate, withYear: FALSE).'<br/>'.DateUi::getLine($eDate, FALSE);
			$where = NULL;
		}

		$annotation = ($where === NULL) ? CourseUi::getAuthor($eCourse) : $where.' · '.CourseUi::getAuthor($eCourse);

		$h = '<a href="'.CourseUi::url($eCourse).'" class="util-deck learning-date'.($section === NULL ? '' : ' learning-date-'.$section).'">';
			$h .= '<div class="util-deck-head">';
				$h .= \util\DateUi::calendar($eDate['startAt'], selected: TRUE);
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.encode($eCourse['name']).' '.CourseUi::getTypeMark($eCourse['type']).'</div>';
					$h .= '<p>'.$line.'</p>';
					$h .= '<p><span class="util-annotation">'.$annotation.'</span></p>';
				$h .= '</div>';
				$h .= '<div class="util-deck-aside">'.CourseUi::getCover($eCourse, 'learning-cover-md', friend: 0).'</div>';
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	protected function getFilters(\Collection $cTheme, \Collection $cSubtheme, \Collection $cLevel, Theme $eThemeSelected, Subtheme $eSubthemeSelected, Level $eLevelSelected, string $tab): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/formations', ['method' => 'get', 'class' => 'util-filters learning-filters']);

			if($tab === 'dates') {
				$h .= $form->hidden('tab', $tab);
			}

			if($eLevelSelected->notEmpty()) {
				$h .= $form->hidden('level', $eLevelSelected);
			}

			$h .= '<div class="util-filter">';
				$h .= '<div class="util-filter-label">'.s("Thématique").'</div>';
				$h .= $form->select('theme', $cTheme, $eThemeSelected, [
					'placeholder' => s("Toutes les thématiques"),
					'onchange' => 'this.form.requestSubmit()'
				]);
			$h .= '</div>';

			if($cSubtheme->notEmpty()) {

				$h .= '<div class="util-filter">';
					$h .= '<div class="util-filter-label">'.s("Sous-thématique").'</div>';
					$h .= $form->select('subtheme', $cSubtheme, $eSubthemeSelected, [
						'placeholder' => s("Toutes les sous-thématiques"),
						'onchange' => 'this.form.requestSubmit()'
					]);
				$h .= '</div>';

			}

			$h .= '<div class="util-filter">';
				$h .= '<div class="util-filter-label">'.s("Niveau").'</div>';
				$h .= '<div class="util-filter-options">';

					$h .= '<a href="'.self::urlFilter($eThemeSelected, $eSubthemeSelected, new Level(), $tab).'" class="util-filter-option'.($eLevelSelected->empty() ? ' is-selected' : '').'">'.s("Tous").'</a>';

					foreach($cLevel as $eLevel) {
						$selected = ($eLevelSelected->notEmpty() and $eLevelSelected['id'] === $eLevel['id']);
						$h .= '<a href="'.self::urlFilter($eThemeSelected, $eSubthemeSelected, $eLevel, $tab).'" class="util-filter-option'.($selected ? ' is-selected' : '').'">'.encode($eLevel['name']).'</a>';
					}

				$h .= '</div>';
			$h .= '</div>';

		$h .= $form->close();

		return $h;

	}

	/** L'adresse du catalogue filtré, sur l'onglet `$tab` ; `$all` ouvre l'agenda au-delà de sa borne. */
	public static function urlFilter(Theme $eTheme, Subtheme $eSubtheme, Level $eLevel, string $tab = 'formations', bool $all = FALSE): string {

		$arguments = [];

		if($tab === 'dates') {
			$arguments['tab'] = $tab;
		}

		if($eTheme->notEmpty()) {
			$arguments['theme'] = $eTheme['id'];
		}

		if($eSubtheme->notEmpty()) {
			$arguments['subtheme'] = $eSubtheme['id'];
		}

		if($eLevel->notEmpty()) {
			$arguments['level'] = $eLevel['id'];
		}

		if($all) {
			$arguments['all'] = 1;
		}

		return '/formations'.($arguments ? '?'.http_build_query($arguments) : '');

	}

	/**
	 * L'onglet « Formations » d'un membre du réseau qui n'est pas de l'équipe : toutes les
	 * formations que le réseau ouvre à ses membres, sans le nom du réseau, que le bandeau du groupe
	 * porte déjà. Celles où il est inscrit portent son avancement, les autres « S'inscrire »
	 * (`proposed`, `ShareLib::fillProposed()`). Le filtre « Type » y mène aux mêmes adresses que les
	 * onglets de l'équipe (`CourseUi::getCommunityTypeTabs()`).
	 */
	public function getCommunityMember(\structure\Structure $eStructure, \Collection $cShareShown, array $types, ?string $type): string {

		$h = '';

		if(count(array_filter($types)) > 1) {

			$url = '/groupe/'.$eStructure['id'].'/formations';

			$h .= CourseUi::getTypeFilter($types, $type, fn(?string $option) => 'href="'.$url.($option === NULL ? '' : '?type='.$option).'"');

		}

		$h .= '<div class="util-cards util-cards-3">';
			foreach($cShareShown as $eShare) {
				$h .= new CourseUi()->getCard($eShare['course'], TRUE, FALSE, withAuthor: FALSE, proposed: $eShare['course']['proposed']);
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * La section d'une promotion dans « Mes formations », sans filtre : un compte n'y reçoit jamais
	 * assez de formations pour avoir à les trier.
	 */
	private function getMineSection(\Collection $cShare): string {

		$eSection = $cShare->first()['section'];

		$h = '<div class="util-title mt-2">';
			$h .= '<h2>'.encode($eSection['name']).'</h2>';
			$h .= '<div>';
				$h .= '<span class="util-annotation">'.encode($eSection['structure']['name']).'</span>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-3">';
			foreach($cShare as $eShare) {
				$h .= new CourseUi()->getCard($eShare['course'], TRUE, FALSE, eShare: $eShare['course']['eShare']);
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * La section d'un réseau dans « Mes formations » : les formations qu'il propose à ses membres et
	 * où le compte ne s'est pas inscrit, chacune avec « S'inscrire » ou « Demande envoyée ». Le titre
	 * nomme le réseau, et la carte ne le redit pas, comme dans l'onglet du réseau
	 * (`getCommunityMember()`).
	 */
	private function getMineProposed(\Collection $cShare): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h2>'.s("Proposées par {value}", encode($cShare->first()['course']['community']['name'])).'</h2>';
		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-3">';
			foreach($cShare as $eShare) {
				$h .= new CourseUi()->getCard($eShare['course'], TRUE, FALSE, withAuthor: FALSE, proposed: TRUE);
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * « Mes formations ».
	 *
	 * Les formations que les promotions du compte lui ouvrent viennent d'abord, une section par
	 * promotion : c'est le parcours qu'on attend de lui, et chaque carte porte la fenêtre de lecture
	 * de la formation, la plus large de ses promotions. Puis celles qu'il suit et n'a pas finies, où
	 * qu'il s'y soit inscrit, puis celles que ses réseaux lui proposent et où il ne s'est pas inscrit,
	 * une section par réseau, et celles qu'il a suivies en entier pour finir.
	 *
	 * La page s'ouvre dès qu'une structure est ouverte, le catalogue public seulement avec
	 * l'ouverture à tous : son bouton ne paraît qu'avec `$hasCatalog`, que la page pose.
	 */
	public function getMine(\Collection $ccShareSection, \Collection $ccShareProposed, \Collection $cCourseRunning, \Collection $cCourseDone, int $started, bool $hasCatalog): string {

		$h = '<div class="util-action">';
			$h .= '<h1>'.s("Mes formations").'</h1>';
			if($hasCatalog) {
				$h .= '<div>';
					$h .= '<a href="/formations" class="btn btn-outline-primary">'.s("Tout le catalogue").'</a>';
				$h .= '</div>';
			}
		$h .= '</div>';

		if(
			$ccShareSection->empty() and
			$ccShareProposed->empty() and
			$cCourseRunning->empty() and
			$cCourseDone->empty()
		) {

			$h .= '<div class="util-block util-block-empty mt-2">';
				if($hasCatalog) {
					$h .= '<h3>'.s("Aucune formation commencée").'</h3>';
					$h .= '<p>'.s("Une formation se commence depuis sa fiche, dans le catalogue.").'</p>';
				} else {
					$h .= '<h3>'.s("Aucune formation").'</h3>';
					$h .= '<p>'.s("Les formations que vous suivez paraitront ici.").'</p>';
				}
			$h .= '</div>';

			return $h;

		}

		$courseUi = new CourseUi();

		foreach($ccShareSection as $cShare) {
			$h .= $this->getMineSection($cShare);
		}

		if($cCourseRunning->notEmpty()) {

			$h .= '<div class="util-title mt-2">';
				$h .= '<h2>'.s("À suivre").'</h2>';
				if($started > 0) {
					$h .= '<div>';
						$h .= '<span class="util-annotation">'.p("{value} formation commencée", "{value} formations commencées", $started).'</span>';
					$h .= '</div>';
				}
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-3">';

				foreach($cCourseRunning as $eCourse) {

					if($eCourse['remaining'] === 0) {
						$annotation = '';
					} else if($eCourse['remainingMinutes'] === NULL) {
						$annotation = p("Il reste {value} leçon", "Il reste {value} leçons", $eCourse['remaining']);
					} else {
						$annotation = p("Il reste {value} leçon, {duration}", "Il reste {value} leçons, {duration}", $eCourse['remaining'], ['duration' => CourseUi::getDuration($eCourse['remainingMinutes'])]);
					}

					$h .= $courseUi->getCard($eCourse, TRUE, FALSE, $annotation);

				}

			$h .= '</div>';

		}

		foreach($ccShareProposed as $cShare) {
			$h .= $this->getMineProposed($cShare);
		}

		if($cCourseDone->notEmpty()) {

			$h .= '<div class="util-title mt-2">';
				$h .= '<h2>'.s("Suivies en entier").'</h2>';
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-3">';
				foreach($cCourseDone as $eCourse) {
					$h .= $courseUi->getCard($eCourse, TRUE, FALSE);
				}
			$h .= '</div>';

		}

		return $h;

	}

	/** L'onglet Diffusion de l'éditeur, qui a pris la place de la page Mode de diffusion et catalogue. */
	public static function urlCourse(Course $eCourse): string {
		return CourseUi::urlEdition($eCourse).'?tab=diffusion';
	}

	public static function urlAdmin(): string {
		return '/learning/admin/catalogue';
	}

	/**
	 * L'entrée « Mode de diffusion et catalogue » des roues d'une formation (ligne de la liste du réseau,
	 * éditeur), sur la seule formation qui a une offre et un circuit (`acceptCatalog()`) : une
	 * formation de réseau.
	 */
	public static function getCourseItem(Course $eCourse): string {

		if($eCourse->acceptCatalog() === FALSE) {
			return '';
		}

		return '<a href="'.self::urlCourse($eCourse).'" class="dropdown-item">'.s("Mode de diffusion et catalogue").'</a>';

	}

	/**
	 * La marque de la colonne « Catalogue » de la liste du réseau : où en est la formation au
	 * catalogue, et le lien vers sa page. L'en-tête nomme le catalogue, la marque n'a plus à le
	 * redire. `catalogBadge` est posé par `CourseLib::fillCatalogBadge()` ; `private` y veut dire
	 * « retirée par l'équipe », la seule formation privée qui se signale, parce que sa page porte le
	 * motif.
	 */
	public static function getBadge(Course $eCourse): string {

		if($eCourse['catalogBadge'] === NULL) {
			return '';
		}

		[$class, $label] = match($eCourse['catalogBadge']) {
			Course::PROPOSED => ['badge-info', s("Demandée")],
			Course::ACCEPTED => ['badge-success', s("Au catalogue")],
			Course::REFUSED => ['badge-warning', s("Refusée")],
			Course::PRIVATE => ['badge-warning', s("Retirée")],
		};

		return '<a href="'.self::urlCourse($eCourse).'" class="btn btn-badge btn-xs badge '.$class.'">'.$label.'</a>';

	}

	/**
	 * L'onglet Diffusion de l'éditeur d'une formation de réseau (retour de Vincent du 03/10, point 5,
	 * qui y range l'ancienne page Mode de diffusion et catalogue) : son offre (lot L15a), la carte de
	 * son état au catalogue et son geste, puis la carte de la formation telle qu'un visiteur la lira
	 * au catalogue. Le classement et la présentation se corrigent dans le panneau de la formation,
	 * que l'aperçu ouvre.
	 *
	 * Hors catalogue public (`offered`, posé par la page), la formation ne peut pas y entrer : la
	 * section Catalogue public n'aurait rien à proposer et ne paraît pas (retour de Vincent du 03/10,
	 * point 1). L'aperçu reste, parce que la carte se lit aussi ailleurs qu'au catalogue (« Mes
	 * formations » des stagiaires d'une promotion) et que son panneau corrige la présentation.
	 * `$cDate` porte les dates planifiées à venir d'une formation datée, vide pour une formation en
	 * ligne.
	 */
	public function getCourse(Course $eCourse, \Collection $cDate): string {

		$eCourse->expects(['offered']);

		$h = $eCourse->isDated() ? $this->getDatesOffer($eCourse, $cDate) : $this->getCourseOffer($eCourse);

		if($eCourse['offered']) {

			$h .= '<div class="util-title mt-2">';
				$h .= '<h3>'.s("Catalogue public").'</h3>';
			$h .= '</div>';

			$h .= $this->getCourseState($eCourse);

		}

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Aperçu au catalogue").'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/course:updateSettings?id='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier la présentation").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-cards util-cards-2 util-cards-start">';
			$h .= new CourseUi()->getCard($eCourse, FALSE, TRUE);
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'offre d'une formation en ligne : une carte, titrée de l'offre et de son texte, avec le montant
	 * que le paiement en ligne encaisse quand c'est son offre. Le geste qui la change est à droite du
	 * titre de la section.
	 */
	protected function getCourseOffer(Course $eCourse): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Mode de diffusion").'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/course:updateOffer?id='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier le mode de diffusion").'</a>';
			$h .= '</div>';
		$h .= '</div>';
		$h .= '<p class="util-annotation mb-2">'.CourseUi::getOfferIntro().'</p>';

		$content = '<p>'.CourseUi::getOfferText($eCourse['offer']).'</p>';

		if(
			$eCourse['offer'] === Course::TRAINEE and
			$eCourse['price'] !== NULL
		) {
			$content .= '<div class="util-deck-figures">';
				$content .= \main\DesignUi::getDeckFigure(s("Montant payé en ligne"), CourseUi::getAmount($eCourse['price']));
			$content .= '</div>';
		}

		$h .= $this->getStateCard(NULL, CourseUi::getOfferValues()[$eCourse['offer']], $content);

		return $h;

	}

	/**
	 * L'offre d'une formation datée : une pile par date à venir, dans la forme des dates de la fiche,
	 * chacune avec sa porte.
	 *
	 * Inscription sur le site de l'organisme (lot annuaire), l'offre s'y tient : une carte le dit, à
	 * son adresse, sans geste. Une formation dont chaque date a sa page peut ne pas en avoir
	 * (`Course::externalUrl.uncovered`), et la carte se passe alors d'adresse. Les offres des dates
	 * restent en base et reviennent à la réouverture.
	 */
	protected function getDatesOffer(Course $eCourse, \Collection $cDate): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Mode de diffusion").'</h3>';
		$h .= '</div>';

		if($eCourse->isExternal()) {

			$link = ($eCourse['externalUrl'] === NULL)
				? ''
				: '<p class="text-break"><a href="'.encode($eCourse['externalUrl']).'" target="_blank">'.encode($eCourse['externalUrl']).'</a></p>';

			return $h.$this->getStateCard(NULL, s("Sur le site de l'organisme"), $link);

		}

		if($cDate->empty()) {
			return $h.'<div class="util-block util-block-empty"><h3>'.s("Aucune date à venir").'</h3></div>';
		}

		$h .= '<p class="util-annotation mb-2">'.CourseUi::getOfferIntro().'</p>';

		foreach($cDate as $eDate) {
			$h .= new DateUi()->getOfferDeck($eDate);
		}

		return $h;

	}

	/**
	 * Une carte par état, avec son geste. Une formation en brouillon ne se propose pas au catalogue
	 * (`Course::catalog.published`) : à sa place, la carte offre de la publier. Retenue puis
	 * dépubliée, elle garde sa place au catalogue sans y paraître, et la carte le dit.
	 *
	 * Une formation privée qui porte un motif en a été retirée par l'équipe : la carte le dit, avec
	 * le motif, jusqu'à la demande suivante, qui l'efface.
	 *
	 * Elle ne se rend que pour une formation qui peut se proposer au catalogue (`getCourse()`).
	 */
	protected function getCourseState(Course $eCourse): string {

		$isPublished = ($eCourse['status'] === Course::PUBLISHED);

		$publish = '<a href="/learning/course:publish?id='.$eCourse['id'].'" class="btn btn-primary">'.s("Publier").'</a>';
		$draft = '<p>'.s("Une formation se propose au catalogue une fois publiée.").'</p>';
		$again = $this->getButtons($this->getRequestButton($eCourse, s("Demander à nouveau")));

		switch($eCourse['catalog']) {

			case Course::PRIVATE :

				if($eCourse['catalogReason'] === NULL) {

					if($isPublished) {
						$content = '<p>'.s("Au catalogue public, la carte de la formation est présentée à tous les visiteurs de Ouvretaferme, parmi les formations de l'équipe. L'équipe de Ouvretaferme valide chaque demande.").'</p>';
						$content .= $this->getButtons($this->getRequestButton($eCourse, s("Demander la mise au catalogue")));
					} else {
						$content = $draft.$this->getButtons($publish);
					}

					return $this->getStateCard(NULL, s("Hors catalogue"), $content);

				}

				$content = $this->getReason($eCourse);
				$content .= $isPublished ? $again : $draft.$this->getButtons($publish);

				return $this->getStateCard('warning', s("Retirée du catalogue le {value}", $this->getDate($eCourse)), $content);

			case Course::PROPOSED :

				$content = '<p>'.s("L'équipe de Ouvretaferme relit la formation avant de la mettre au catalogue.").'</p>';
				$content .= $this->getButtons(
					'<a data-ajax="/learning/course:doCatalogWithdraw" post-id="'.$eCourse['id'].'" class="btn btn-outline-primary" data-confirm="'.s("Retirer la demande de mise au catalogue ?").'" data-waiter>'.s("Retirer la demande").'</a>'
				);

				return $this->getStateCard('info', s("Demande envoyée le {value}", $this->getDate($eCourse)), $content);

			case Course::ACCEPTED :

				$withdraw = '<a data-ajax="/learning/course:doCatalogWithdraw" post-id="'.$eCourse['id'].'" class="btn btn-outline-primary" data-confirm="'.s("Retirer la formation du catalogue ? Pour l'y remettre, il faudra une nouvelle demande.").'" data-waiter>'.s("Retirer du catalogue").'</a>';

				if($isPublished) {
					$content = $this->getButtons(
						'<a href="'.CourseUi::url($eCourse).'" class="btn btn-outline-primary">'.\Asset::icon('eye').' '.s("Voir la fiche publique").'</a>',
						$withdraw
					);
				} else {
					$content = '<p>'.s("Tant qu'elle est en brouillon, la formation n'y paraît pas.").'</p>';
					$content .= $this->getButtons($publish, $withdraw);
				}

				return $this->getStateCard('success', s("Au catalogue depuis le {value}", $this->getDate($eCourse)), $content);

			case Course::REFUSED :

				$content = $this->getReason($eCourse);
				$content .= $isPublished ? $again : $draft.$this->getButtons($publish);

				return $this->getStateCard('warning', s("Refusée le {value}", $this->getDate($eCourse)), $content);

		}

	}

	/** La carte d'accent de `DesignUi`, et sans accent pour l'état ordinaire, hors catalogue. */
	protected function getStateCard(?string $color, string $title, string $content): string {

		if($color !== NULL) {
			return \main\DesignUi::getAccentCard($color, NULL, $title, $content);
		}

		$h = '<div class="util-cards mb-2">';
			$h .= '<div class="util-card">';
				$h .= '<div class="util-card-title"><span>'.$title.'</span></div>';
				$h .= $content;
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected function getButtons(string ...$buttons): string {
		return '<div class="flex-buttons">'.implode('', $buttons).'</div>';
	}

	protected function getRequestButton(Course $eCourse, string $label): string {
		return '<a data-ajax="/learning/course:doCatalogRequest" post-id="'.$eCourse['id'].'" class="btn btn-primary" data-waiter>'.$label.'</a>';
	}

	protected function getReason(Course $eCourse): string {
		return '<div class="util-label">'.s("Motif").'</div><p>'.nl2br(encode($eCourse['catalogReason'])).'</p>';
	}

	protected function getDate(Course $eCourse): string {
		return \util\DateUi::textual($eCourse['catalogAt'], \util\DateUi::DATE);
	}

	/**
	 * L'onglet Catalogue public de l'administration : les demandes à juger d'abord, puis les
	 * formations de réseau retenues, puis « Premiers pas » (lot catalogue-attractif), que l'équipe
	 * choisit parmi tout le catalogue, puis les refus en cours, que le réseau n'a pas encore corrigés.
	 */
	public function getAdmin(\Collection $cCourseProposed, \Collection $cCourseAccepted, \Collection $cCourseFeatured, \Collection $cCourseRefused): string {

		$h = '<div class="util-action">';
			$h .= '<h1>'.s("Formations").'</h1>';
		$h .= '</div>';

		$h .= CourseUi::getAdminTabs('catalog');

		$h .= $this->getAdminList($cCourseProposed, s("Demandes"), s("Envoyée le"), s("Aucune demande en attente."), '');
		$h .= $this->getAdminList($cCourseAccepted, s("Au catalogue"), s("Depuis le"), s("Aucune formation de réseau au catalogue."), 'mt-2');
		$h .= $this->getAdminList($cCourseFeatured, s("Premiers pas"), s("Depuis le"), s("Aucune formation choisie pour ouvrir le catalogue."), 'mt-2', featured: TRUE);
		$h .= $this->getAdminList($cCourseRefused, s("Refus"), s("Refusée le"), s("Aucun refus en cours."), 'mt-2');

		return $h;

	}

	/**
	 * Un tableau par état, aux mêmes colonnes : l'auteur et la date passent sous le nom sous 575 px.
	 * Les gestes suivent l'état de la ligne, que les trois listes ne mêlent pas. « Premiers pas »
	 * (`$featured`) mêle l'équipe et les réseaux : la colonne dit l'auteur et non plus le réseau, la
	 * date est celle du choix, et le titre porte « Ajouter ».
	 */
	protected function getAdminList(\Collection $cCourse, string $title, string $dateLabel, string $empty, string $class, bool $featured = FALSE): string {

		$h = '<div class="util-title '.$class.'">';
			$h .= '<h3>'.$title.' <span class="util-counter">'.$cCourse->count().'</span></h3>';
			if($featured) {
				$h .= '<div>';
					$h .= '<a href="/learning/admin/catalog:feature" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter").'</a>';
				$h .= '</div>';
			}
		$h .= '</div>';

		if($cCourse->empty()) {
			return $h.'<div class="util-empty">'.$empty.'</div>';
		}

		$h .= '<div class="util-table util-table-hover learning-table-catalog">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Formation").'</div>';
				$h .= '<div class="hide-xs-down">'.($featured ? s("Auteur") : s("Réseau")).'</div>';
				$h .= '<div class="hide-xs-down">'.$dateLabel.'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cCourse as $eCourse) {

				$context = array_filter([
					$eCourse['theme']->empty() ? NULL : encode($eCourse['theme']['name']),
					CourseUi::getLevel($eCourse)
				]);

				$date = \util\DateUi::numeric($featured ? $eCourse['featuredAt'] : $eCourse['catalogAt'], \util\DateUi::DATE);
				$author = CourseUi::getAuthor($eCourse);

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= '<a href="'.CourseUi::url($eCourse).'"><b>'.encode($eCourse['name']).'</b></a>';
						$h .= '<div>'.CourseUi::getTypeBadge($eCourse).'<span class="util-annotation">'.implode(' · ', $context).'</span></div>';
						$h .= '<div class="util-annotation hide-sm-up">'.$author.' · '.$date.'</div>';
						if($eCourse['catalog'] === Course::REFUSED) {
							$h .= '<div class="mt-1">'.nl2br(encode($eCourse['catalogReason'])).'</div>';
						}
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.$author.'</div>';
					$h .= '<div class="hide-xs-down">'.$date.'</div>';
					$h .= '<div class="learning-row-actions">'.($featured ? $this->getFeaturedActions($eCourse) : $this->getAdminActions($eCourse)).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/** « Accepter » passe sans panneau ; refuser et retirer demandent un motif. */
	protected function getAdminActions(Course $eCourse): string {

		$h = '<a href="'.CourseUi::url($eCourse).'" class="btn btn-only-icon btn-outline-primary" title="'.s("Voir la fiche").'">'.\Asset::icon('eye').'</a>';

		switch($eCourse['catalog']) {

			case Course::PROPOSED :
				$h .= '<a data-ajax="/learning/admin/catalog:doAccept" post-id="'.$eCourse['id'].'" class="btn btn-outline-primary" data-waiter>'.s("Accepter").'</a>';
				$h .= '<a href="/learning/admin/catalog:refuse?id='.$eCourse['id'].'" class="btn btn-outline-primary">'.s("Refuser").'</a>';
				break;

			case Course::ACCEPTED :
				$items = '<a href="/learning/admin/catalog:remove?id='.$eCourse['id'].'" class="dropdown-item">'.s("Retirer du catalogue").'</a>';
				$h .= \main\DesignUi::getGear($items, encode($eCourse['name']));
				break;

		}

		return $h;

	}

	/** Retirer des premiers pas se défait par « Ajouter » : sans motif ni confirmation. */
	protected function getFeaturedActions(Course $eCourse): string {

		$h = '<a href="'.CourseUi::url($eCourse).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('eye').'</a>';
		$items = '<a data-ajax="/learning/admin/catalog:doUnfeature" post-id="'.$eCourse['id'].'" class="dropdown-item" data-waiter>'.s("Retirer des premiers pas").'</a>';
		$h .= \main\DesignUi::getGear($items, encode($eCourse['name']));

		return $h;

	}

	/**
	 * Le panneau « Ajouter » de « Premiers pas » : une formation du catalogue à choisir, qui prend la
	 * dernière place de la rangée.
	 */
	public function feature(\Collection $cCourse): \Panel {

		if($cCourse->empty()) {
			$h = '<div class="util-empty">'.s("Toutes les formations du catalogue sont déjà dans les premiers pas.").'</div>';
		} else {

			$form = new \util\FormUi();

			$h = $form->openAjax('/learning/admin/catalog:doFeature');

				$h .= $form->group(
					s("Formation"),
					$form->select('id', $cCourse->makeArray(fn(Course $eCourse) => [
						'value' => $eCourse['id'],
						'label' => $eCourse['name']
					]), NULL, ['required' => TRUE])
				);

				$h .= $form->group(
					content: $form->submit(s("Ajouter aux premiers pas"))
				);

			$h .= $form->close();

		}

		return new \Panel(
			id: 'panel-learning-catalog-feature',
			title: s("Ajouter aux premiers pas"),
			body: $h
		);

	}

	/**
	 * Le panneau du motif, pour refuser une demande (`refuse`) ou retirer une formation du catalogue
	 * (`remove`). Le bouton porte le même libellé que le titre : c'est le geste, et non un
	 * enregistrement.
	 */
	public function decide(Course $eCourse, string $gesture): \Panel {

		[$title, $action] = match($gesture) {
			'refuse' => [s("Refuser la demande"), '/learning/admin/catalog:doRefuse'],
			'remove' => [s("Retirer du catalogue"), '/learning/admin/catalog:doRemove'],
		};

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			$h .= $form->hidden('id', $eCourse);

			$h .= $form->dynamicGroup($eCourse, 'catalogReason*');

			$h .= $form->group(
				content: $form->submit($title)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-catalog-'.$gesture,
			title: $title,
			subTitle: CourseUi::getTypeBadge($eCourse).encode($eCourse['name']).' · '.encode($eCourse['community']['name']),
			body: $h
		);

	}

}
?>
