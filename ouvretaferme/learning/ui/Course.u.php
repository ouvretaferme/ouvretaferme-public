<?php
namespace learning;

/**
 * Une formation, du côté du lecteur (sa carte, sa fiche, son programme) et du côté de son auteur
 * (la liste de ses formations, l'éditeur).
 *
 * L'auteur est l'équipe Ouvretaferme ou l'équipe d'un groupe : les mêmes méthodes servent les deux,
 * seul le gabarit qui les entoure change, et les adresses de retour suivent `urlList()`.
 */
class CourseUi {

	/**
	 * Les onglets de l'éditeur, dans leur ordre. La page y lit les noms qu'une redirection peut
	 * demander (`?tab=`) : un onglet ajouté ici sans son contenu dans `getEdition()` lève sur le `match`.
	 */
	const EDITION_TABS = ['dates', 'lessons', 'details', 'staff', 'diffusion'];

	public function __construct() {
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	public static function url(Course $eCourse, array $query = []): string {
		return '/formation/'.$eCourse['id'].($query === [] ? '' : '?'.http_build_query($query));
	}

	public static function urlEdition(Course $eCourse): string {
		return self::url($eCourse).'/edition';
	}

	public static function urlUsage(Course $eCourse, array $arguments = []): string {
		return self::url($eCourse).'/bilan'.($arguments === [] ? '' : '?'.http_build_query($arguments));
	}

	public static function urlAttestation(Course $eCourse): string {
		return self::url($eCourse).'/attestation';
	}

	public static function urlStart(Course $eCourse): string {
		return self::url($eCourse).'/demarrer';
	}

	public static function urlDuplicate(Course $eCourse): string {
		return '/learning/course:duplicate?id='.$eCourse['id'];
	}

	public static function urlList(Course $eCourse): string {

		if($eCourse->isTeam()) {
			return '/learning/admin/course';
		}

		return '/groupe/'.$eCourse['community']['id'].'/formations';

	}

	/**
	 * Une durée non estimée n'est pas une donnée manquante : c'est une valeur, et elle se rend par le
	 * tiret muet de la maquette, qui garde remplie la colonne d'un tableau. Les lignes de texte qui
	 * composent plusieurs informations écartent le morceau au lieu d'y poser ce tiret.
	 */
	public static function getDuration(?int $minutes): string {

		if($minutes === NULL) {
			return '<span class="util-annotation">-</span>';
		}

		$hours = intdiv($minutes, 60);
		$rest = $minutes % 60;

		if($hours === 0) {
			return s("{value}&nbsp;min", $rest);
		}

		if($rest === 0) {
			return s("{value}&nbsp;h", $hours);
		}

		return s("{hours}&nbsp;h&nbsp;{minutes}", ['hours' => $hours, 'minutes' => sprintf('%02d', $rest)]);

	}

	/**
	 * Le visuel d'une formation : l'image déposée, recadrée au centre dans la boîte, ou à défaut
	 * l'aplat de sa thématique. Seules les marques de `getCardBadges()` s'y posent, en haut à gauche
	 * (retour de Vincent du 03/10) : aucun autre texte ne se lit sur une image. La carte lit le grand
	 * format : sa bande s'étire sur la largeur de la page sous 991 px, bien au-delà des 480 px du petit.
	 */
	public static function getCover(Course $eCourse, string $class = '', string $badges = '', ?int $friend = NULL): string {

		$eCourse->expects(['vignette', 'theme']);

		// Sous des marques, l'icône et le nom de la thématique descendent (learning.css).
		$classCover = ($badges === '') ? $class : ltrim($class.' learning-cover-badges');

		if($eCourse['vignette'] === NULL) {
			return ThemeUi::getCover($eCourse['theme'], $classCover, $badges, $friend);
		}

		$format = match($class) {
			'learning-cover-xs', 'learning-cover-md' => 's',
			'', 'learning-cover-lg' => 'l'
		};

		return '<div class="learning-cover media-banner-view '.$classCover.'" style="background-image: url('.new \media\LearningCourseUi()->getUrlByElement($eCourse, $format).')">'.$badges.'</div>';

	}

	/**
	 * Ce qui se pose sur la couverture d'une carte ou d'une fiche (retour de Vincent du 03/10, sur la
	 * carte « Stage sans leçon ») : le type, que seul un réseau distingue, puis l'auteur. Le type est
	 * plein dans sa teinte, que les aplats des sections, tous clairs, ne reprennent pas : dilué, il
	 * se fondait dans l'aplat de sa propre famille de couleur. L'auteur est la marque grise du socle,
	 * qui qualifie sans rivaliser avec le type, pleine sur l'aplat neutre d'une formation sans
	 * thématique (tranché par Vincent le 04/10) : ce gris est celui de la marque diluée, qui s'y
	 * fondait. Rien sans type ni auteur.
	 *
	 * Sur une carte, un nom de réseau qui ne tient pas à côté du type se coupe. `$wrap` est pour la
	 * tête de la fiche, seul endroit où ce nom se lit en entier.
	 */
	public static function getCardBadges(Course $eCourse, bool $withAuthor = TRUE, bool $wrap = FALSE): string {

		$eCourse->expects(['vignette', 'theme']);

		$badges = '';

		if($eCourse->isNetwork()) {
			$badges .= self::getTypeMark($eCourse['type'], solid: TRUE);
		}

		if($withAuthor) {
			$solid = ($eCourse['vignette'] === NULL and $eCourse['theme']->empty());
			$badges .= '<span class="badge '.($solid ? 'badge-solid ' : '').'badge-neutral">'.self::getAuthor($eCourse).'</span>';
		}

		return ($badges === '') ? '' : '<div class="util-card-badges'.($wrap ? ' util-card-badges-wrap' : '').'">'.$badges.'</div>';

	}

	/**
	 * Le visuel d'une ligne des trois listes de formations (l'équipe, un groupe, un réseau), qui
	 * mène à la fiche telle qu'un stagiaire la lit (retour 15 de Vincent du 01/10, forme A de la
	 * planche) : le bouton de lecture se pose en son centre, sur l'image comme sur l'aplat. « Voir
	 * la fiche » reste dans la roue.
	 */
	public static function getCoverPlay(Course $eCourse): string {

		$h = '<a href="'.self::url($eCourse).'" class="learning-cover-play" title="'.s("Voir la fiche").'">';
			$h .= self::getCover($eCourse, 'learning-cover-xs');
			$h .= '<span class="btn btn-xs btn-white">'.\Asset::icon('play-fill').'</span>';
		$h .= '</a>';

		return $h;

	}

	public static function getAuthor(Course $eCourse): string {

		if($eCourse->isTeam()) {
			return s("Ouvretaferme");
		}

		return encode($eCourse['community']['name']);

	}

	/**
	 * Une formation de groupe qui descend d'une formation de l'équipe le dit ; une formation de
	 * l'équipe, une formation née dans le groupe, ou une copie dont l'original a été supprimé, ne
	 * disent rien.
	 */
	public static function getOrigin(Course $eCourse): ?string {

		$eCourse->expects(['copiedFrom']);

		if($eCourse->isTeam() or $eCourse['copiedFrom']->empty()) {
			return NULL;
		}

		return s("D'après une formation de Ouvretaferme");

	}

	public static function getStatus(Course $eCourse): string {

		$statusClass = match($eCourse['status']) {
			Course::PUBLISHED => 'badge-success',
			Course::DRAFT => 'badge-draft',
		};

		return '<span class="badge '.$statusClass.'">'.self::p('status')->values[$eCourse['status']].'</span>';

	}

	/**
	 * Les comptes d'une carte : ce que la formation fait lire. Les dates à venir ne s'y comptent pas,
	 * le pied de la carte dit déjà la prochaine (retour de Vincent du 03/10, sur « 1 date à venir »).
	 * Aucun compte ne se dit à zéro : une formation sans rien encore à suivre le dit par ce qui la
	 * fait vivre, ses dates si elle en porte, ses leçons sinon. Une formation à des dates qui en a une
	 * à venir et pas encore de leçon n'a rien à compter, son pied parle pour elle.
	 * `dates` est posée par `DateLib::fillUpcoming()`, planifiées seulement.
	 */
	public static function getCounts(Course $eCourse): ?string {

		$volume = self::getVolume($eCourse);

		if($volume !== NULL) {
			return $volume;
		}

		if($eCourse->isDated()) {
			return ($eCourse['dates'] === 0) ? s("Aucune date à venir") : NULL;
		}

		return s("Aucune leçon");

	}

	/**
	 * Ce que la formation fait lire : ses leçons, et leur durée quand elle est estimée. Rien tant
	 * qu'aucune leçon n'est écrite.
	 */
	public static function getVolume(Course $eCourse): ?string {

		if($eCourse['lessons'] === 0) {
			return NULL;
		}

		$volume = p("{value} leçon", "{value} leçons", $eCourse['lessons']);

		if($eCourse['minutes'] !== NULL) {
			$volume .= ' · '.self::getDuration($eCourse['minutes']);
		}

		return $volume;

	}

	/**
	 * Les icônes, la teinte et la définition d'un type : le seul endroit où elles s'écrivent, que la
	 * marque, le menu de création, le filtre et l'état vide reprennent. La définition est une phrase
	 * (retour de Vincent du 03/10, point 3, « une phrase simple est claire plutôt qu'une suite de
	 * mot ») ; celle de la visio dit « à distance » et non « en ligne », qui est le nom d'un autre type. Une formation mixte, dont les
	 * séances se tiennent en présentiel et en visio (lot date-mixte), porte les icônes des deux.
	 *
	 * @return array{0: string[], 1: string, 2: string}
	 */
	public static function getType(string $type): array {

		return match($type) {
			Course::ONSITE => [['geo-alt-fill'], 'badge-greenhouse', s("Elle se tient à des dates, dans une salle.")],
			Course::REMOTE => [['camera-video-fill'], 'badge-harvest', s("Elle se tient à des dates, à distance, par un lien de visio.")],
			Course::MIXED => [['geo-alt-fill', 'camera-video-fill'], 'badge-info', s("Elle se tient à des dates, dans une salle et par un lien de visio.")],
			Course::ONLINE => [['globe-europe-africa-fill'], 'badge-selling', s("Elle se lit à son rythme, leçon par leçon, sans date.")],
		};

	}

	public static function getTypeIcons(string $type): string {

		return implode('', array_map(fn(string $icon) => \Asset::icon($icon), self::getType($type)[0]));

	}

	/**
	 * La marque du type, l'icône puis le nom, suivie de son espace comme celle du niveau. Seul un
	 * réseau tient des formations en présentiel ou en visio : ailleurs, toutes sont en ligne et la
	 * marque ne distinguerait rien.
	 */
	public static function getTypeBadge(Course $eCourse): string {

		$eCourse->expects(['community', 'type']);

		if($eCourse->isNetwork() === FALSE) {
			return '';
		}

		return self::getTypeMark($eCourse['type']).' ';

	}

	public static function getTypeMark(string $type, bool $solid = FALSE): string {

		$class = ($solid ? 'badge-solid ' : '').self::getType($type)[1];

		return '<span class="badge '.$class.'">'.self::getTypeIcons($type).' '.self::p('type')->values[$type].'</span>';

	}

	/**
	 * Le niveau, en texte : la marque du type est la seule couleur de la ligne qui situe une
	 * formation, et une pastille grise la concurrençait. Absent tant que la formation n'est pas
	 * classée. Ses mots ne se séparent pas en fin de ligne : « Porteur de / projet » se lirait
	 * comme deux données.
	 */
	public static function getLevel(Course $eCourse): ?string {

		if($eCourse['level']->empty()) {
			return NULL;
		}

		return str_replace(' ', '&nbsp;', encode($eCourse['level']['name']));

	}

	/**
	 * La sous-thématique, qui suit le niveau dans la ligne d'annotation de la carte et de la fiche
	 * (planche T1, A1). Absente tant que la formation n'en porte pas. Lue seule, sans sa thématique :
	 * au catalogue, la thématique est déjà le titre de la rangée.
	 */
	public static function getSubtheme(Course $eCourse): ?string {

		if($eCourse['subtheme']->empty()) {
			return NULL;
		}

		return encode($eCourse['subtheme']['name']);

	}

	/**
	 * La ligne d'une carte sous la ligne forte, au catalogue et dans « Mes formations » : le niveau
	 * et, sur une formation à des dates, les comptes, en annotation. Le type est passé sur la
	 * couverture (`getCardBadges()`). Ceux d'une formation en ligne passent dans la ligne forte
	 * (`getCardWhen()`), qui la mesure. Chaque élément garde ses mots ensemble, la ligne ne se coupe
	 * qu'entre deux éléments : « 15 min » seul à la ligne se lisait sans la leçon qu'il mesure
	 * (retour de Vincent du 03/10, point 2).
	 */
	protected static function getCardContext(Course $eCourse): string {

		$parts = [self::getLevel($eCourse), self::getSubtheme($eCourse)];

		if($eCourse->isDated()) {
			$parts[] = self::getCounts($eCourse);
		}

		$parts = array_filter($parts, fn(?string $part) => $part !== NULL);

		if($parts === []) {
			return '';
		}

		return '<p class="util-annotation">'.implode(' · ', array_map(fn(string $part) => str_replace(' ', '&nbsp;', $part), $parts)).'</p>';

	}

	/**
	 * La ligne forte sous le titre de la carte (planche carte-hierarchie du 02/10, piste B) : le jour
	 * de la prochaine date d'une formation à des dates, que la pastille de tête annonce, ou ce que
	 * mesure une formation en ligne, ses leçons et leur durée. Une formation à des dates sans date à
	 * venir n'a rien à y dire, sa ligne de type le porte.
	 */
	protected static function getCardWhen(Course $eCourse): string {

		if($eCourse->isDated()) {
			return DateUi::getCardWhen($eCourse);
		}

		return '<p class="learning-card-when">'.str_replace(' ', '&nbsp;', self::getCounts($eCourse)).'</p>';

	}

	/**
	 * Au visiteur, la condition pour lire la formation, en annotation : un renseignement, pas un
	 * geste (retour de Vincent du 03/10, point 2, sur une condition en pastille bordée qui se lisait
	 * comme un bouton). Elle ne se coupe pas. Rien quand la ligne d'accès de la carte dit quelque
	 * chose (`getCardAccess()`) : elle n'a pas à rappeler sa condition.
	 */
	protected static function getCardCondition(Course $eCourse, bool $isLogged, string $access): string {

		if($isLogged or $access !== '') {
			return '';
		}

		return '<p class="util-annotation">'.self::getAccess($eCourse).'</p>';

	}

	/**
	 * La carte d'une formation, au catalogue et dans « Mes formations » (planche carte-hierarchie du
	 * 02/10 : piste B, bandes C). La prochaine date se lit en tête, par sa pastille à côté du titre et
	 * son jour dessous, et non plus dans une bande de pied. Le pied ne dit plus que la situation du
	 * lecteur, en une seule bande : la fenêtre de sa promotion tant qu'elle n'est pas ouverte, sinon
	 * sa progression et l'échéance à sa suite. Le visiteur y lit à la place la condition pour la lire.
	 * Le type et l'auteur se posent sur la couverture, en haut à gauche (`getCardBadges()`).
	 *
	 * Une formation que la promotion du stagiaire lui ouvre porte sa fenêtre de lecture (`$eShare`).
	 * Tant qu'elle n'est pas ouverte, la carte n'est pas un lien et ne dit pas de progression : la
	 * fiche lui serait refusée.
	 *
	 * Sans vignette, l'aplat de la thématique porte son ami (`ThemeUi::getFriend()`), et `$position`,
	 * le rang de la carte dans sa rangée, l'alterne avec le second.
	 *
	 * La page publique d'un réseau vit sur un autre domaine que la fiche : elle passe l'adresse
	 * entière du site (`$host`), et `$blank` quand elle est intégrée au site du réseau, d'où la fiche
	 * ne doit pas s'ouvrir dans l'iframe. Sur les pages du réseau, son bandeau le nomme déjà, et la
	 * carte ne porte pas sa marque (`$withAuthor`).
	 *
	 * Une formation que le réseau propose au membre qui n'y est pas inscrit (`$proposed`) porte à la
	 * place de la progression la bande « S'inscrire », ou « Demande envoyée » quand sa demande attend,
	 * en ligne ou à une date (`eEnrollmentRequest`, `eDateEnrollmentRequest`, posées par la page).
	 * Elle mène à la fiche d'une formation en ligne, le panneau d'inscription ouvert (`?enroll=1`)
	 * tant qu'aucune demande n'attend, et à la page publique du réseau pour une formation datée
	 * (`eCommunity`, posé par la page), d'où l'on s'inscrit à une date : sa fiche ne se lit qu'une
	 * fois confirmé.
	 */
	public function getCard(Course $eCourse, bool $isLogged, bool $withGoals, string $annotation = '', Share $eShare = new Share(), int $position = 0, string $host = '', bool $blank = FALSE, bool $withAuthor = TRUE, bool $proposed = FALSE): string {

		$isAnnounced = (
			$eShare->notEmpty() and
			$eShare->isOpen() === FALSE
		);

		$access = self::getCardAccess($eCourse);

		$class = 'util-deck util-deck-top learning-card'.($eCourse->isDone() ? ' util-deck-accent-success' : '');

		if($isAnnounced) {
			$h = '<div class="'.$class.'">';
		} else {

			if($proposed === FALSE) {
				$url = $host.self::url($eCourse);
			} else if($eCourse->isDated()) {
				$url = DatePublicUi::urlList($eCourse['eCommunity']);
			} else if($eCourse['eEnrollmentRequest']->notEmpty()) {
				$url = $host.self::url($eCourse);
			} else {
				$url = $host.self::url($eCourse, ['enroll' => 1]);
			}

			$h = '<a href="'.$url.'" class="'.$class.'"'.($blank ? ' target="_blank"' : '').'>';

		}

			$h .= self::getCover($eCourse, badges: self::getCardBadges($eCourse, $withAuthor), friend: $position);

			$h .= '<div class="util-deck-head">';

				if(
					$eCourse->isDated() and
					$eCourse['dateNext'] !== NULL
				) {
					$h .= \util\DateUi::calendar($eCourse['dateNext']);
				}

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.encode($eCourse['name']).'</div>';

					$h .= self::getCardWhen($eCourse);

					$h .= ReviewUi::getCardRating($eCourse);

					$h .= self::getCardContext($eCourse);

					$h .= self::getCardCondition($eCourse, $isLogged, $access);

					if($withGoals and $eCourse['goals']) {

						$h .= '<ul class="learning-card-goals">';
							foreach($eCourse['goals'] as $goal) {
								$h .= '<li>'.encode($goal).'</li>';
							}
						$h .= '</ul>';

					}

					if($annotation !== '') {
						$h .= '<p class="util-annotation">'.$annotation.'</p>';
					}

					$h .= $access;

				$h .= '</div>';
			$h .= '</div>';

			if($isAnnounced) {
				$h .= ShareUi::getOpensBand($eShare);
			} else if($proposed) {
				$h .= self::getProposedBand($eCourse);
			} else if($isLogged) {

				$due = ShareUi::getCardDue($eCourse, $eShare);

				$h .= new ProgressUi()->getBand($eCourse, $due, duePast: ($due !== NULL and $eShare->acceptProgress() === FALSE));

			}

		$h .= $isAnnounced ? '</div>' : '</a>';

		return $h;

	}

	protected static function getProposedBand(Course $eCourse): string {

		$eCourse->expects(['eEnrollmentRequest', 'eDateEnrollmentRequest']);

		if($eCourse['eEnrollmentRequest']->notEmpty() or $eCourse['eDateEnrollmentRequest']->notEmpty()) {
			return '<div class="util-deck-band"><span>'.s("Demande envoyée").'</span></div>';
		}

		if($eCourse->isEnrollmentPauseShown()) {
			return '<div class="util-deck-band util-deck-band-state">'.s("Fermé pour le moment").'</div>';
		}

		return '<div class="util-deck-band"><span>'.s("S'inscrire").'</span>'.\Asset::icon('arrow-right').'</div>';

	}

	/**
	 * Ce que la carte et la fiche disent du prix : celui de la formation en ligne, ou celui de la
	 * prochaine date d'une formation datée (`eDateNext`, `DateLib::fillUpcoming()`), qui reprend
	 * les valeurs de sa formation qu'elle n'a pas. Seule une formation de réseau a une tarification.
	 */
	protected static function getAccessSource(Course $eCourse): Course|Date {

		if($eCourse->isNetwork() === FALSE) {
			return new Date();
		}

		return $eCourse->isDated() ? $eCourse['eDateNext'] : $eCourse;

	}

	/**
	 * La ligne d'accès de la carte (banc carte-acces du 03/10, variante D) : les chiffres de la pile
	 * d'une date, chaque montant sous l'intitulé de son public, le paiement en dernier chiffre, puis
	 * les financeurs en annotation, pour que la carte ne prenne pas un rang de plus. Un montant seul
	 * n'a pas d'autre public, il s'intitule « Prix ». Rien quand rien n'est saisi : un montant vide
	 * ne s'affiche pas, jamais en « Gratuit ».
	 */
	public static function getCardAccess(Course $eCourse): string {

		$e = self::getAccessSource($eCourse);

		if($e->empty()) {
			return '';
		}

		$figures = '';

		if($e->isFree()) {
			$figures .= \main\DesignUi::getDeckFigure(s("Prix"), s("Gratuit"));
		} else if($e->getPriceFunded() !== NULL) {
			$figures .= \main\DesignUi::getDeckFigure(s("Public financé"), self::getAmount($e->getPriceFunded()));
		}

		if(
			$e->isFree() === FALSE and
			$e->getPrice() !== NULL
		) {
			$figures .= \main\DesignUi::getDeckFigure(($e->getPriceFunded() === NULL) ? s("Prix") : s("Autre public"), self::getAmount($e->getPrice()));
		}

		$figures .= self::getPaymentFigure($eCourse, $e);

		$h = ($figures === '') ? '' : '<div class="util-deck-figures">'.$figures.'</div>';

		if($e->getFunding() !== NULL) {
			$h .= '<p class="util-annotation">'.s("Finançable par {value}", implode(', ', self::getFundingLabels($e->getFunding()))).'</p>';
		}

		return $h;

	}

	/**
	 * La ligne d'accès dans la tête de la fiche : la pile d'une date telle quelle
	 * (`getPriceFigures()`), financeurs compris, et le paiement en dernier chiffre. Le prix d'une
	 * formation datée est celui de la prochaine date, comme sur la carte.
	 *
	 * Inscriptions en pause, le dernier chiffre le dit à la place du paiement, sur toute formation qui
	 * s'y prête, tarifée ou non : la fiche n'a plus de geste pour s'inscrire, ni sur ses dates. Comme
	 * sur une date, la pause ne se dit pas à qui a déjà sa ligne (`hasEnrollmentLine`, posé par la
	 * page).
	 */
	public static function getFicheAccess(Course $eCourse): string {

		$eCourse->expects(['hasEnrollmentLine']);

		$e = self::getAccessSource($eCourse);

		if(
			$eCourse->isEnrollmentPauseShown() and
			$eCourse['hasEnrollmentLine'] === FALSE
		) {
			$figures = ($e->empty() ? '' : self::getPriceFigures($e)).self::getPausedFigure();
		} else if($e->empty()) {
			return '';
		} else {
			$figures = self::getPriceFigures($e).self::getPaymentFigure($eCourse, $e);
		}

		return ($figures === '') ? '' : '<div class="util-deck-figures">'.$figures.'</div>';

	}

	/**
	 * La pause des inscriptions en chiffre, dans la tête de la fiche et sur une date
	 * (`DateUi::getPublicFigures()`).
	 */
	public static function getPausedFigure(): string {
		return \main\DesignUi::getDeckFigure(s("Inscription"), s("Fermé pour le moment"));
	}

	/**
	 * Les montants et les financeurs en chiffres, sous leurs intitulés : la pile d'une date
	 * (`DateUi::getPublicFigures()`) et la tête de la fiche.
	 */
	public static function getPriceFigures(Course|Date $e): string {

		$h = '';

		foreach(self::getPriceRows($e) as $label => $value) {
			$h .= \main\DesignUi::getDeckFigure($label, $value);
		}

		return $h;

	}

	/**
	 * Comment le stagiaire paie, en chiffre, sur la carte et dans la tête de la fiche : en ligne, en
	 * direct avec l'organisme, ou sur le site de l'organisme quand l'inscription s'y fait. Rien hors
	 * catalogue public, ni quand chaque montant affiché est gratuit : « Gratuit » suivi d'un mode de
	 * paiement se contredirait. Le paiement en ligne n'encaisse que « Autre public » (`learning.yml`),
	 * le public financé réglant toujours avec l'organisme : avec deux montants, le chiffre le dit.
	 */
	protected static function getPaymentFigure(Course $eCourse, Course|Date $e): string {

		if($e['offer'] === $e::NONE) {
			return '';
		}

		$amounts = array_filter([$e->getPriceFunded(), $e->getPrice()], fn(?float $amount) => $amount !== NULL);

		if(
			$amounts !== [] and
			array_filter($amounts, fn(float $amount) => $amount !== 0.0) === []
		) {
			return '';
		}

		if($eCourse->isExternal()) {
			return \main\DesignUi::getDeckFigure(s("Inscription"), s("Sur le site de l'organisme"));
		}

		if(self::isPaidOnline($eCourse, $e)) {

			return ($e->getPriceFunded() === NULL)
				? \main\DesignUi::getDeckFigure(s("Paiement"), s("En ligne"))
				: \main\DesignUi::getDeckFigure(s("Paiement en ligne"), s("Autre public"));

		}

		return \main\DesignUi::getDeckFigure(s("Paiement"), s("Avec l'organisme"));

	}

	/**
	 * Le stagiaire paie en ligne la formation en ligne ou la date qui en a le mode de diffusion, tant
	 * que l'inscription se fait sur Ouvretaferme. La formation et la date partagent les valeurs de
	 * leur `offer`, comme `getOfferValues()` les lit pour l'une et l'autre.
	 */
	public static function isPaidOnline(Course $eCourse, Course|Date $eCourseOrDate): bool {

		return (
			$eCourse->isExternal() === FALSE and
			$eCourseOrDate['offer'] === Course::TRAINEE
		);

	}

	/**
	 * « 42,00 € pour le public financé · 210,00 € pour les autres publics », dans les e-mails. Un
	 * montant seul garde son libellé, et 0 s'écrit « Gratuit ».
	 */
	public static function getPriceAmounts(Course|Date $e): string {

		if($e->isFree()) {
			return s("Gratuit");
		}

		$amounts = [];

		if($e->getPriceFunded() !== NULL) {
			$amounts[] = s("{value} pour le public financé", self::getAmount($e->getPriceFunded()));
		}

		if($e->getPrice() !== NULL) {
			$amounts[] = s("{value} pour les autres publics", self::getAmount($e->getPrice()));
		}

		return implode(' · ', $amounts);

	}

	/**
	 * Les lignes du prix, sous leur libellé, sur une pile de date comme dans le détail d'une
	 * formation en ligne. Seules les valeurs saisies s'affichent.
	 *
	 * @return array<string, string>
	 */
	public static function getPriceRows(Course|Date $e): array {

		if($e->isFree()) {
			return [s("Prix") => s("Gratuit")];
		}

		$rows = [];

		if($e->getPriceFunded() !== NULL) {
			$rows[s("Public financé")] = self::getAmount($e->getPriceFunded());
		}

		if($e->getPrice() !== NULL) {
			$rows[s("Autre public")] = self::getAmount($e->getPrice());
		}

		if($e->getFunding() !== NULL) {
			$rows[s("Finançable par")] = implode(', ', self::getFundingLabels($e->getFunding()));
		}

		return $rows;

	}

	public static function getAmount(float $value): string {
		return ($value === 0.0) ? s("Gratuit") : \util\TextUi::money($value);
	}

	/**
	 * Les six financeurs, dans l'ordre du schéma. Une date porte les mêmes valeurs que sa formation.
	 */
	public static function getFundingValues(): array {

		return [
			Course::VIVEA => s("VIVEA"),
			Course::OCAPIAT => s("OCAPIAT"),
			Course::FRANCE_TRAVAIL => s("France Travail"),
			Course::CPF => s("CPF"),
			Course::REGION => s("Région"),
			Course::OTHER => s("Autre fonds"),
		];

	}

	/** @return string[] les libellés des financeurs cochés, dans l'ordre du schéma */
	public static function getFundingLabels(\Set $funding): array {

		$labels = [];

		foreach(self::getFundingValues() as $bit => $label) {

			if($funding->value($bit)) {
				$labels[] = $label;
			}

		}

		return $labels;

	}

	/** L'introduction du mode de diffusion, sous le libellé des deux panneaux et sous le titre de l'onglet Diffusion. */
	public static function getOfferIntro(): string {
		return s("Permet de diffuser la formation auprès de tous les producteurs de Ouvretaferme, dans le catalogue public.");
	}

	/**
	 * Les trois modes de diffusion et leur texte, que la formation en ligne et chaque date partagent (lot L15a).
	 */
	public static function getOfferValues(): array {

		return [
			Course::NETWORK => s("Paiement en direct avec l'organisme"),
			Course::TRAINEE => s("Paiement en ligne par le stagiaire"),
			Course::NONE => s("Hors catalogue public"),
		];

	}

	public static function getOfferText(string $offer): string {

		return match($offer) {
			Course::NETWORK => s("Le stagiaire paie l'organisme directement, hors de Ouvretaferme."),
			Course::TRAINEE => s("Le stagiaire paie en ligne à l'organisme le prix que vous fixez."),
			Course::NONE => s("Non diffusée dans le catalogue public de Ouvretaferme. Le prix se règle entre l'organisme et le stagiaire."),
		};

	}

	/**
	 * Le choix du mode de diffusion, une option par valeur avec son texte. « Paiement en ligne par le
	 * stagiaire », tant que le réseau ne peut pas encore le choisir, reste à sa place, fermé, son texte
	 * suivi de sa raison (`Course::offer.account` tient le POST) ; déjà choisi, il reste ouvert, comme
	 * l'étape le laisse passer. Tant que les offres payantes sont fermées
	 * (`acceptPaidOffer()`, étape `offer.paid`), les deux options se ferment avec cette seule raison,
	 * y compris l'offre déjà choisie, que l'étape refuse aussi. « Hors catalogue public » se ferme
	 * quand elle retirerait au catalogue sa dernière offre (`$holdsCatalog`, étapes
	 * `Course::offer.catalog` et `Date::offer.catalog`). `hasPayoutAccount` est posé par la page sur
	 * la formation.
	 *
	 * « Paiement en ligne par le stagiaire » se ferme aussi, choisi ou non, tant que la formation en
	 * ligne ou la date (`$eCourseOrDate`) n'a pas de montant « Autre public » non nul à encaisser
	 * (étape `offer.priced`) : la raison mène au panneau qui le saisit. Elle s'écrit sous celle du
	 * compte plutôt qu'à sa place, les deux manques se réparant à deux endroits différents.
	 */
	public static function getOfferField(\util\FormUi $form, string $offer, Course $eCourse, bool $holdsCatalog, Course|Date $eCourseOrDate): string {

		$eCourse->expects(['community', 'catalog', 'hasPayoutAccount']);

		$values = self::getOfferValues();

		$option = function(string $value, ?string $reason, bool $closed = FALSE) use($values, $offer): array {

			$locked = ($reason !== NULL and ($closed or $value !== $offer));

			return [
				'value' => $value,
				'label' => '<div class="field-radio-group-label">'.$values[$value].'</div><div class="field-radio-group-reason">'.self::getOfferText($value).'</div>'.($locked ? '<div class="field-radio-group-reason">'.$reason.'</div>' : ''),
				'attributes' => $locked ? ['disabled' => 'disabled'] : []
			];

		};

		$paid = $eCourse->acceptPaidOffer() ? NULL : s("Pas encore disponible.");
		$account = $eCourse['hasPayoutAccount'] ? NULL : s("Il manque le compte de reversement du réseau.");
		$catalog = $holdsCatalog ? s("Retirez d'abord la formation du catalogue public.") : NULL;

		$urlPrice = ($eCourseOrDate instanceof Date) ? '/learning/date:update?id='.$eCourseOrDate['id'] : '/learning/course:updatePrice?id='.$eCourseOrDate['id'];
		$priced = $eCourseOrDate->acceptTraineeOffer() ? NULL : s("Il manque le prix « Autre public ». <link>Le renseigner</link>", ['link' => '<a href="'.$urlPrice.'">']);

		// Tous les manques s'affichent ensemble, chacun se réparant ailleurs. Seul celui du prix ferme
		// l'option déjà choisie, l'étape `offer.priced` la refusant même inchangée.
		$traineeMissing = array_filter([$account, $priced]);
		$traineeReason = $paid ?? (($traineeMissing === []) ? NULL : implode('<br/>', $traineeMissing));
		$traineeClosed = ($paid !== NULL or $priced !== NULL);

		return $form->radios('offer', [
			$option(Course::NETWORK, $paid, $paid !== NULL),
			$option(Course::TRAINEE, $traineeReason, $traineeClosed),
			$option(Course::NONE, $catalog),
		], $offer, [
			'required' => TRUE
		]);

	}

	/** Le panneau de l'offre d'une formation en ligne, ouvert depuis l'onglet Diffusion de son éditeur. */
	public function updateOffer(Course $eCourse): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/course:doUpdateOffer');

			$h .= $form->hidden('id', $eCourse);

			$h .= $form->dynamicGroup($eCourse, 'offer*');

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-course-offer',
			title: s("Mode de diffusion de la formation"),
			subTitle: self::getTypeBadge($eCourse).encode($eCourse['name']),
			body: $h
		);

	}

	public static function getAccess(Course $eCourse): string {

		return match($eCourse['open']) {
			TRUE => \Asset::icon('unlock').'&nbsp;'.str_replace(' ', '&nbsp;', s("Lecture libre")),
			FALSE => \Asset::icon('lock-fill').'&nbsp;'.str_replace(' ', '&nbsp;', s("Avec un compte gratuit")),
		};

	}

	/**
	 * Le menu de gauche de la fiche : le catalogue, puis le programme de la formation, les leçons
	 * suivies cochées, les leçons vides « À venir », liées selon la règle du programme
	 * (`Lesson::isLinked()`). Chaque module s'y nomme comme sur la fiche (`getChapterName()`).
	 *
	 * Le catalogue public ne s'ouvre qu'avec l'ouverture à tous, alors que la fiche d'une structure
	 * listée se lit sans elle : `$hasCatalog` vient de la page, et son lien ne paraît qu'avec lui. La
	 * section « Formations » ne garde son titre que si une entrée le suit.
	 */
	public function getMenu(Course $eCourse, \Collection $cChapter, \Collection $cProgress, bool $isLogged, bool $hasCatalog): string {

		$h = '<div class="util-filter-options util-filter-options-column util-filter-options-nav util-filter-options-menu">';

		if(
			$hasCatalog or
			$isLogged
		) {
			$h .= '<div class="util-filter-options-title learning-menu-section">'.s("Formations").'</div>';
		}

		if($hasCatalog) {
			$h .= '<a href="/formations" class="util-filter-option">'.s("Tout le catalogue").'</a>';
		}

		if($isLogged) {
			$h .= '<a href="/mes-formations" class="util-filter-option">'.s("Mes formations").'</a>';
		}

		$h .= '<div class="util-filter-options-title util-filter-options-title-subject">'.encode($eCourse['name']).'</div>';
		$h .= '<a href="'.self::url($eCourse, $eCourse['customizeQuery'] ?? []).'" class="util-filter-option is-selected">'.s("Fiche de la formation").'</a>';

		// Un module sans leçon n'a rien à ouvrir dans le menu (planche fiche-publique) : une visio dont
		// le programme n'est fait que de points n'y garde que la fiche.
		foreach($cChapter as $eChapter) {

			if($eChapter['cLesson']->empty()) {
				continue;
			}

			$h .= '<div class="util-filter-options-title util-filter-options-title-group">'.self::getChapterName($eCourse, $eChapter).'</div>';

			foreach($eChapter['cLesson'] as $eLesson) {

				$linked = $eLesson->isLinked($eCourse);

				if($linked) {
					$h .= '<a href="'.LessonUi::url($eLesson, $eCourse['customizeQuery'] ?? []).'" class="util-filter-option">';
				} else {
					$h .= '<span class="util-filter-option color-muted">';
				}

					$h .= match($eCourse['type']) {
						Course::ONLINE => self::getLessonNumber($eCourse, $eLesson).' ',
						Course::REMOTE, Course::ONSITE, Course::MIXED => self::getLessonNumber($eCourse, $eLesson).'. '
					};
					$h .= encode($eLesson['name']);
					if($cProgress->offsetExists($eLesson['id'])) {
						$h .= ' '.\Asset::icon('check-circle-fill', ['class' => 'color-success']);
					} else if($eLesson['written'] === FALSE) {
						$h .= ' '.LessonUi::getSoon();
					}

				$h .= $linked ? '</a>' : '</span>';

			}

		}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La fiche de la formation : ce qu'elle est, où en est le lecteur, son programme et son
	 * détail. Son auteur y trouve « Gérer la formation » à droite du titre, ou dessous quand ses gestes prennent plus de la moitié de la rangée (`util-action-fold`), en contour : le geste
	 * plein de la fiche reste celui du lecteur. Le bouton mène à l'éditeur, pas au panneau : il ne
	 * s'appelle donc pas « Modifier », qui dans le package ne désigne que le panneau. C'est aussi le seul chemin vers l'éditeur sur un brouillon, dont
	 * le bandeau ne fait plus que dire l'état.
	 *
	 * Sous le résumé, ce que coûte la formation et comment on la paie, sous la forme de la pile d'une
	 * date (`getFicheAccess()`, banc carte-acces du 03/10), puis au visiteur la condition que pose le
	 * bouton de tête (`getFicheAccount()`).
	 */
	public function getFiche(Course $eCourse, \Collection $cChapter, \Collection $cStaff, Enrollment $eEnrollment, \Collection $cProgress, \Collection $cStructure, bool $isLogged, \Collection $cDate = new \Collection()): string {

		$h = '';

		if($eCourse['status'] === Course::DRAFT) {

			$h .= '<div class="util-block-info mb-2">';
				$h .= \Asset::icon('info-circle', ['class' => 'util-block-icon']);
				$h .= '<div class="util-block-info-text">';
					$h .= '<div class="util-block-info-title">'.s("Cette formation est un brouillon").'</div>';
					$h .= '<div class="util-block-info-help">'.s("Seuls ses auteurs la voient, jusqu'à ce qu'elle soit publiée.").'</div>';
				$h .= '</div>';
			$h .= '</div>';

		}

		$h .= self::getFicheEnrolled($eCourse, $eEnrollment, $cProgress);

		$h .= '<div class="learning-fiche-head">';

			$h .= self::getCover($eCourse, 'learning-cover-lg', self::getCardBadges($eCourse, wrap: TRUE));

			$h .= '<div>';

				$canUpdate = $eCourse->assertUpdate();
				$canCustomize = $eCourse['canCustomize'];

				if($canUpdate or $canCustomize) {

					$h .= '<div class="util-action util-action-fold">';
						$h .= '<div><h1>'.encode($eCourse['name']).'</h1></div>';
						$h .= '<div class="util-action-fold-aside">';
							if($canUpdate) {
								$h .= '<a href="'.self::urlEdition($eCourse).'" class="btn btn-outline-primary">'.s("Gérer la formation").'</a>';
							}
							if($canCustomize) {
								$h .= '<a href="'.self::url($eCourse, ['customize' => 1]).'" class="btn btn-outline-primary">'.\Asset::icon('palette').' '.s("Personnaliser l'apparence").'</a>';
							}
						$h .= '</div>';
					$h .= '</div>';

				} else {
					$h .= '<h1>'.encode($eCourse['name']).'</h1>';
				}

				$h .= ReviewUi::getFicheRating($eCourse);

				$h .= self::getFicheContext($eCourse);

				if($eCourse['summary'] !== NULL) {
					$h .= '<p>'.encode($eCourse['summary']).'</p>';
				}

				$h .= self::getFicheAccess($eCourse);

				$h .= self::getFicheAccount($eCourse, $isLogged);

				// Le lien de tête ne paraît que sans date à venir, là où la pause ne se dit pas : la
				// condition l'écrit pour que la règle se lise ici, elle ne coupe rien de plus.
				$h .= $this->getStart($eCourse, $eEnrollment, $cProgress, $cStructure, $isLogged, ($eCourse->isExternal() and $eCourse->isEnrollmentPauseShown() === FALSE and $eCourse['hasDatePlanned'] === FALSE and $eCourse['externalUrl'] !== NULL));

			$h .= '</div>';

		$h .= '</div>';

		$h .= self::getFicheBand($eCourse, $eEnrollment);

		$h .= new DateUi()->getFicheSection($cDate);

		$h .= '<div class="util-title mt-2">';
			$h .= '<h2>'.s("Programme").'</h2>';
		$h .= '</div>';

		$h .= $this->getProgram($eCourse, $cChapter, $cProgress, $eCourse['withEnroll'] === FALSE);

		$h .= '<div class="util-title mt-2">';
			$h .= '<h2>'.s("La formation en détail").'</h2>';
		$h .= '</div>';

		$h .= $this->getDetails($eCourse, $cStaff);

		$h .= new ReviewUi()->getSection($eCourse);

		return $h;

	}

	/**
	 * L'inscrit d'une formation qui s'offre à l'inscription n'a encore rien suivi : c'est l'état où
	 * la fiche revient après « S'inscrire », et il tient jusqu'à la première leçon suivie. La fiche le
	 * dit en tête et son bouton devient « Commencer la formation » (planche personnes, banc q2-a).
	 */
	protected static function isEnrolledOnly(Course $eCourse, Enrollment $eEnrollment, \Collection $cProgress): bool {

		return (
			$eEnrollment->notEmpty() and
			$cProgress->empty() and
			$eCourse->acceptPublic()
		);

	}

	protected static function getFicheEnrolled(Course $eCourse, Enrollment $eEnrollment, \Collection $cProgress): string {

		if(self::isEnrolledOnly($eCourse, $eEnrollment, $cProgress) === FALSE) {
			return '';
		}

		$firstName = $eEnrollment['user']['firstName'];

		$h = '<div class="util-block-success mb-2">';
			$h .= '<h3>'.($firstName === NULL ? s("Votre inscription est faite") : s("Votre inscription est faite, {value}", encode($firstName))).'</h3>';
			$h .= '<p>'.s("Vous pouvez commencer quand vous voulez.").'</p>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Au visiteur, sous la ligne d'accès, la condition que pose le bouton de tête « Lire la première
	 * leçon » (lot L4e) : sur une formation qui demande un compte, il mène à la connexion. La mention
	 * suit ce bouton et non `open` seul, qu'une formation datée sans leçon, où le visiteur s'inscrit
	 * sans compte, porte aussi.
	 */
	protected static function getFicheAccount(Course $eCourse, bool $isLogged): string {

		if(
			self::hasVisitorStart($eCourse, $isLogged) === FALSE or
			$eCourse['open']
		) {
			return '';
		}

		return '<p class="util-annotation">'.self::getAccess($eCourse).'</p>';

	}

	/**
	 * Le visiteur a pour bouton de tête « Lire la première leçon » (`getStart()`).
	 */
	protected static function hasVisitorStart(Course $eCourse, bool $isLogged): bool {

		return (
			$isLogged === FALSE and
			$eCourse['eLessonFirst']->notEmpty()
		);

	}

	/**
	 * La ligne qui situe la formation sous le titre de la fiche, en annotation : le niveau, le
	 * domaine, le volume et l'origine d'une copie. Le type et l'auteur sont sur la couverture, comme
	 * sur la carte (`getCardBadges()`). Le domaine n'y est que sous une vignette : sans elle, la
	 * couverture l'écrit déjà (`getCover()`). Les dates à venir n'y sont pas, leur section les montre
	 * juste dessous, ni la forme des leçons, que le programme donne ligne à ligne.
	 */
	public static function getFicheContext(Course $eCourse): string {

		$context = [
			self::getLevel($eCourse),
			self::getSubtheme($eCourse),
			($eCourse['vignette'] === NULL or $eCourse['theme']->empty()) ? NULL : encode($eCourse['theme']['name']),
			self::getVolume($eCourse),
			self::getOrigin($eCourse),
		];

		$context = array_filter($context);

		if($context === []) {
			return '';
		}

		return '<p class="util-annotation">'.implode(' · ', $context).'</p>';

	}

	/**
	 * La bande sous la tête de la fiche : la progression de l'inscrit, et l'échéance de sa promotion
	 * à la suite. Figée sans avoir été commencée, la formation n'a plus de progression à montrer :
	 * l'échéance passée se lit seule, sur le fond d'un état. L'inscrit d'une formation qui tient le
	 * journal de lecture y trouve « Mon suivi ».
	 */
	public static function getFicheBand(Course $eCourse, Enrollment $eEnrollment): string {

		$due = self::getFicheDue($eCourse);

		if(
			$eEnrollment->empty() and
			$eCourse['canProgress'] === FALSE
		) {
			return '<div class="util-deck-band util-deck-band-state">'.$due.'</div>';
		}

		if(
			$eEnrollment->notEmpty() or
			$due !== NULL
		) {
			return new ProgressUi()->getBand($eCourse, $due, ($eEnrollment->notEmpty() and $eCourse->acceptReading()));
		}

		return '';

	}

	/**
	 * L'échéance de la promotion, que la bande de progression porte à la suite. L'échéance passée
	 * se lit sur `canProgress` et non sur la fenêtre, qui n'engage pas celui qui écrit la formation.
	 * L'échéance à venir suit la règle de la carte (`ShareUi::getCardDue()`) : rien sans échéance ni
	 * une fois la formation suivie en entier.
	 */
	protected static function getFicheDue(Course $eCourse): ?string {

		if($eCourse['canProgress'] === FALSE) {
			return ShareUi::getDuePast($eCourse['eShare']);
		}

		if(
			$eCourse['eShare']->notEmpty() and
			$eCourse['eShare']['dueAt'] !== NULL and
			$eCourse['eShare']->acceptProgress() and
			$eCourse->isDone() === FALSE
		) {
			return ShareUi::getDue($eCourse['eShare']);
		}

		return NULL;

	}

	/**
	 * Une attestation de présence par place pointée d'une formation sans leçon (lot L8f), sous le
	 * nom de son jour dès que la personne en a deux, pour les distinguer. Les places sont posées par
	 * la page, déjà réduites à celles qui l'acceptent (`DateEnrollment::acceptPresenceAttestation()`).
	 */
	protected function getPresenceButtons(\Collection $cDateEnrollment): string {

		$h = '';

		foreach($cDateEnrollment as $eDateEnrollment) {

			$label = ($cDateEnrollment->count() === 1)
				? s("Attestation de présence")
				: s("Attestation du {value}", \util\DateUi::textual($eDateEnrollment['date']['startAt'], \util\DateUi::DAY_MONTH));

			$h .= '<a href="'.DateEnrollmentUi::urlPresence($eDateEnrollment).'" class="btn btn-outline-primary btn-lg" data-ajax-download data-waiter>'.\Asset::icon('download').' '.$label.'</a>';

		}

		return $h;

	}

	/**
	 * Le bouton de tête de la fiche. Il commence la formation, la reprend où le lecteur l'a
	 * laissée, ou mène le visiteur à la première leçon d'une formation ouverte sans compte. À côté,
	 * le compte inscrit qui a suivi toutes les leçons télécharge son attestation de suivi (F69),
	 * échéance passée comprise.
	 *
	 * Une formation datée sans inscription confirmée à l'une de ses dates n'a pas de bouton de tête :
	 * le geste de la fiche est « S'inscrire » sur une date, et « Commencer » n'arrive qu'avec
	 * l'inscription confirmée (`Enrollment::acceptCreate()`). La garde lit la confirmation et non
	 * `acceptEnrollment`, qui refuse aussi pour une échéance passée : une personne confirmée dont le
	 * partage est échu garde « Voir la formation », comme sur toute formation.
	 *
	 * Une formation datée dont le programme n'est que le quiz n'a rien à lire avant que le réseau
	 * l'ouvre : pas de bouton, puis « Passer le quiz » (`ResponseLib::fillCourseQuiz()`).
	 *
	 * Une formation qui inscrit sur le site de l'organisme et n'a aucune date à venir (lot annuaire)
	 * n'a pas de pile de date où poser le lien sortant : il prend la tête de la rangée, en bouton
	 * principal, et « Lire la première leçon » passe en contour à côté de lui. Sans page de la
	 * formation, permise tant que chaque date à venir avait la sienne, il n'y a pas de lien à poser.
	 *
	 * Sur une formation où l'on s'inscrit avant de lire (`withEnroll`, posé par la page depuis
	 * `Course::isEnrollFirst()`), le geste est « S'inscrire » : le compte s'inscrit d'un clic, ou choisit sa
	 * structure dans le panneau ; le visiteur crée son compte sur place, ou se connecte quand la
	 * page qui le crée est fermée, et revient à la fiche. Inscrit, il la retrouve avec « Commencer
	 * la formation » tant qu'il n'a rien suivi.
	 */
	protected function getStart(Course $eCourse, Enrollment $eEnrollment, \Collection $cProgress, \Collection $cStructure, bool $isLogged, bool $withExternal = FALSE): string {

		// L'avis suit chaque sortie : une formation datée sans leçon se note comme les autres.
		$review = ReviewUi::getStartButton($eCourse);

		$external = $withExternal
			? self::getExternalLink($eCourse['externalUrl'], 'btn btn-primary btn-lg')
			: '';

		if($eCourse['lessons'] === 0) {
			$presence = $this->getPresenceButtons($eCourse['cDateEnrollmentAttended']);
			return ($external.$presence.$review === '') ? '' : '<div class="flex-buttons mt-1">'.$external.$presence.$review.'</div>';
		}

		$attestation = $eCourse->acceptAttestation($eEnrollment)
			? '<a href="'.self::urlAttestation($eCourse).'" class="btn btn-outline-primary btn-lg" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Attestation de suivi").'</a>'
			: '';

		$quizOnly = ($eCourse->isDated() and $eCourse['quizOnly']);

		// Sans bouton de tête, l'attestation reste : une inscription à la date annulée après le quiz
		// réussi n'efface pas les leçons suivies, et la route la délivre toujours. De même quand
		// aucune leçon ne s'ouvre au lecteur (`CourseLib::getFirstLinked()`), hors « S'inscrire », qui
		// ne mène à aucune leçon et après quoi le stagiaire peut les cocher.
		if(
			(
				$eCourse['eLessonFirst']->empty() and
				$eCourse['withEnroll'] === FALSE
			) or
			(
				$isLogged and
				(
					(
						$eCourse->isDated() and
						$eEnrollment->empty() and
						$eCourse['isDateConfirmed'] === FALSE
					) or
					(
						$quizOnly and
						$eCourse['quiz'] !== ResponseLib::QUIZ_OPEN
					)
				)
			)
		) {
			return ($external.$attestation.$review === '') ? '' : '<div class="flex-buttons mt-1">'.$external.$attestation.$review.'</div>';
		}

		$h = '<div class="flex-buttons mt-1">';

			if($eCourse['withEnroll']) {

				// En pause, aucun geste : la tête de la fiche le dit (`getFicheAccess()`). La demande déjà
				// envoyée reste montrée.
				if($eCourse['eEnrollmentRequest']->notEmpty()) {
					$h .= new EnrollmentUi()->getRequest($eCourse['eEnrollmentRequest']);
				} else if($eCourse->isEnrollmentPaused() === FALSE) {

					if($isLogged) {

						$h .= new EnrollmentUi()->getEnroll($eCourse, $cStructure);

						if($eCourse['withEnrollPanel']) {
							$h .= new EnrollmentUi()->getPanelOnArrival($eCourse);
						}

					} else {

						$url = $eCourse['withAccountCreate']
							? '/learning/account:create?course='.$eCourse['id']
							: \Lime::getUrl().'/user/log:form?redirect='.urlencode(\Lime::getUrl().self::url($eCourse));

						$h .= '<a href="'.$url.'" class="btn btn-primary btn-lg">'.s("S'inscrire").'</a>';

					}

				}

			} else if(self::hasVisitorStart($eCourse, $isLogged)) {

				$h .= $external;

				// Une pause montrée laisse sans geste une formation sans lecture libre : sa première leçon
				// demande une inscription que la pause refuse, et la tête de la fiche dit la pause
				// (`getFicheAccess()`). Une pause qui ne se montre pas ne coupe rien.
				if($eCourse['open'] or $eCourse->isEnrollmentPauseShown() === FALSE) {

					// Sans lecture libre, la première leçon demande un compte : le panneau le crée sur place,
					// quand la page qui crée le compte est ouverte.
					$url = ($eCourse['open'] or $eCourse['withAccountCreate'] === FALSE)
						? LessonUi::url($eCourse['eLessonFirst'], $eCourse['customizeQuery'] ?? [])
						: '/learning/account:create?course='.$eCourse['id'];

					$h .= '<a href="'.$url.'" class="btn '.($external === '' ? 'btn-primary' : 'btn-outline-primary').' btn-lg">'.s("Lire la première leçon").'</a>';

				}

			} else if(
				$eCourse['canProgress'] === FALSE or
				(
					$eEnrollment->empty() and
					$eCourse['acceptEnrollment'] === FALSE
				)
			) {

				// L'échéance passée se lit dans la bande : la rangée ne porte que le geste, qui mène à
				// la première leçon. La leçon de reprise est la première non suivie, qu'on ne relit pas.
				// Celui qui écrit la formation progresse sans fenêtre, mais ne s'y inscrit plus après
				// l'échéance de sa promotion : `acceptEnrollment` lui retire aussi « Commencer ».
				$h .= '<a href="'.LessonUi::url($eCourse['eLessonFirst'], $eCourse['customizeQuery'] ?? []).'" class="btn btn-outline-primary btn-lg">'.($eEnrollment->empty() ? s("Voir la formation") : s("Relire la formation")).'</a>';

			} else if(
				$quizOnly and
				$cProgress->empty()
			) {

				$h .= '<a href="'.QuizUi::url($eCourse['eLessonFirst'], $eCourse['customizeQuery'] ?? []).'" class="btn btn-primary btn-lg">'.s("Passer le quiz").'</a>';

			} else if($eCourse['eEnrollmentRequest']->notEmpty()) {

				// Une formation que la personne lit déjà (Q-C1, le catalogue public) : la demande attend
				// le réseau comme sur une formation où l'on s'inscrit avant de lire.
				$h .= new EnrollmentUi()->getRequest($eCourse['eEnrollmentRequest']);

			} else if($eEnrollment->empty()) {

				$h .= new EnrollmentUi()->getStart($eCourse, $cStructure);

				if($eCourse['withEnrollPanel']) {
					$h .= new EnrollmentUi()->getPanelOnArrival($eCourse);
				}

			} else if($eCourse['eLessonResume']->empty()) {

				$h .= '<a href="'.LessonUi::url($eCourse['eLessonFirst'], $eCourse['customizeQuery'] ?? []).'" class="btn btn-outline-primary btn-lg">'.s("Relire la formation").'</a>';

			} else if(self::isEnrolledOnly($eCourse, $eEnrollment, $cProgress)) {

				$h .= '<a href="'.LessonUi::url($eCourse['eLessonResume'], $eCourse['customizeQuery'] ?? []).'" class="btn btn-primary btn-lg">'.s("Commencer la formation").'</a>';

			} else if($cProgress->empty()) {

				$h .= '<a href="'.LessonUi::url($eCourse['eLessonResume'], $eCourse['customizeQuery'] ?? []).'" class="btn btn-primary btn-lg">'.s("Lire la leçon {value}", self::getLessonNumber($eCourse, $eCourse['eLessonResume'])).'</a>';

			} else {

				$h .= '<a href="'.LessonUi::url($eCourse['eLessonResume'], $eCourse['customizeQuery'] ?? []).'" class="btn btn-primary btn-lg">'.s("Reprendre à la leçon {value}", self::getLessonNumber($eCourse, $eCourse['eLessonResume'])).'</a>';

			}

			$h .= $attestation;

			$h .= $review;

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien vers le site de l'organisme qui prend la place de « S'inscrire » quand la formation y
	 * envoie ses inscriptions (lot annuaire). Un nouvel onglet, pour que la fiche reste ouverte.
	 */
	public static function getExternalLink(string $url, string $class = 'btn btn-outline-primary'): string {
		return '<a href="'.encode($url).'" target="_blank" class="'.$class.'">'.\Asset::icon('box-arrow-up-right').' '.s("S'inscrire sur le site de l'organisme").'</a>';
	}

	/**
	 * Une leçon quiz qui n'est pas encore ouverte à la personne confirmée le dit à la place de sa
	 * durée, comme à la personne inscrite dont l'inscription attend encore ; qui n'est inscrit à
	 * aucune date garde la durée, le quiz lui dit à quoi il est réservé. En ligne, des modules sans
	 * leçon n'ont encore rien à lire : le programme n'est pas écrit. À des dates, ils sont les points
	 * abordés le jour même et se montrent.
	 *
	 * Les leçons ne s'ouvrent qu'à qui les lit : sans `$withLinks`, le programme les montre sans y
	 * mener, pour que la fiche d'une formation où l'on s'inscrit d'abord n'envoie pas à un refus. Une
	 * leçon vide se lit « À venir » et ne mène nulle part, sauf pour qui peut la cocher
	 * (`Lesson::isLinked()`).
	 *
	 * Chaque module porte l'ancre `module-{id}`, où mène le module lié d'une question de la FAQ.
	 */
	public function getProgram(Course $eCourse, \Collection $cChapter, \Collection $cProgress, bool $withLinks = TRUE): string {

		$eCourse->expects(['quiz', 'type', 'lessons']);

		if(
			$cChapter->empty() or
			($eCourse['type'] === Course::ONLINE and $eCourse['lessons'] === 0)
		) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Le programme n'est pas encore écrit").'</h3>';
			$h .= '</div>';

			return $h;

		}

		$h = '';

		foreach($cChapter as $eChapter) {

			$h .= '<div class="util-deck util-deck-open learning-program-chapter mb-1'.($eChapter['isDone'] ? ' util-deck-accent-success' : '').'" id="module-'.$eChapter['id'].'">';

				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.self::getChapterName($eCourse, $eChapter).'</div>';
						$h .= '<p class="util-annotation">'.implode(' · ', array_filter([
							encode($eChapter['summary']),
							$eChapter['minutes'] === NULL ? NULL : self::getDuration($eChapter['minutes'])
						])).'</p>';
					$h .= '</div>';
				$h .= '</div>';

				if($eChapter['cLesson']->notEmpty()) {

					$h .= '<div class="util-deck-fold">';

						foreach($eChapter['cLesson'] as $eLesson) {

							// En ligne, le numéro « 1.2 » prend sa colonne ; ailleurs, « 2. » reste devant le nom.
							$number = self::getLessonNumber($eCourse, $eLesson);

							[$class, $index, $prefix] = match($eCourse['type']) {
								Course::ONLINE => ['util-deck-row util-deck-row-numbered', '<span class="util-deck-row-index">'.$number.'</span>', ''],
								Course::REMOTE, Course::ONSITE, Course::MIXED => ['util-deck-row', '', $number.'. ']
							};

							$linked = ($withLinks and $eLesson->isLinked($eCourse));

							$h .= $linked
								? '<a href="'.LessonUi::url($eLesson, $eCourse['customizeQuery'] ?? []).'" class="'.$class.'">'
								: '<div class="'.$class.'">';
								$h .= $index;
								$h .= '<span class="util-deck-row-name">';
									$h .= $prefix.encode($eLesson['name']);
									// Une leçon vide porte déjà « À venir » : elle n'a pas de forme à dire.
									if($eLesson['format'] !== Lesson::EMPTY) {
										$h .= '<span class="learning-row-meta">'.LessonUi::getFormat($eLesson['format']).'</span>';
									}
									if($eLesson['objective'] !== NULL) {
										$h .= '<span class="learning-row-meta"><span class="util-annotation">'.encode($eLesson['objective']).'</span></span>';
									}
								$h .= '</span>';
								$h .= self::getLessonState($eCourse, $eLesson, $cProgress);
								// Une leçon sans lien garde la place du chevron, pour que l'état de chaque ligne s'aligne sur le même bord droit
								if($linked) {
									$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
								} else if($withLinks) {
									$h .= '<span class="util-deck-arrow not-visible">'.\Asset::icon('chevron-right').'</span>';
								}
							$h .= $linked ? '</a>' : '</div>';

						}

					$h .= '</div>';

				}

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Le numéro d'une leçon, qui suit le type de la formation comme le nom d'un module
	 * (`getChapterName()`) : en ligne, « 1.2 » se lit avec « Module 1 », ailleurs le module n'a pas
	 * de numéro et la leçon garde son rang dans la formation, « 2 ».
	 */
	public static function getLessonNumber(Course $eCourse, Lesson $eLesson): string {

		return match($eCourse['type']) {
			Course::ONLINE => $eLesson['chapterNumber'].'.'.$eLesson['numberInChapter'],
			Course::REMOTE, Course::ONSITE, Course::MIXED => (string)$eLesson['number'],
		};

	}

	/**
	 * Ce qui se lit à droite d'une leçon du programme, sur la fiche comme sur le sommaire des
	 * tutoriels : suivie, pas encore écrite, quiz fermé, inscription à confirmer, ou sa durée.
	 */
	public static function getLessonState(Course $eCourse, Lesson $eLesson, \Collection $cProgress): string {

		if($cProgress->offsetExists($eLesson['id'])) {
			return '<span class="color-success">'.\Asset::icon('check-circle-fill').' '.s("Suivie").'</span>';
		}

		if($eLesson['written'] === FALSE) {
			return LessonUi::getSoon();
		}

		if($eLesson['validation'] === Lesson::QUIZ) {

			if($eCourse['quiz'] === ResponseLib::QUIZ_NOT_OPENED) {
				return '<span class="color-muted">'.\Asset::icon('lock-fill').' '.s("Pas encore ouvert").'</span>';
			}

			if($eCourse['quiz'] === ResponseLib::QUIZ_UNCONFIRMED) {
				return '<span class="color-muted">'.\Asset::icon('lock-fill').' '.s("Inscription non confirmée").'</span>';
			}

		}

		return '<span class="color-muted">'.self::getDuration($eLesson['minutes']).'</span>';

	}

	/**
	 * Un module tel qu'il se lit, sur la fiche, à l'inscription, dans le menu, au-dessus du titre d'une
	 * leçon, sur l'attestation et sur le bilan. Le mot Module et son numéro sont réservés aux
	 * formations en ligne (Vincent, 01/10/2026) : en visio ou en présentiel, le module ne se lit que
	 * par son nom. C'est le type de la formation qui décide, jamais l'écran. L'espace insécable lie
	 * le point médian au numéro, pour qu'aucune ligne ne commence par lui.
	 */
	public static function getChapterName(Course $eCourse, Chapter $eChapter): string {

		return match($eCourse['type']) {
			Course::ONLINE => s("Module {number}&nbsp;· {name}", ['number' => $eChapter['number'], 'name' => encode($eChapter['name'])]),
			Course::REMOTE, Course::ONSITE, Course::MIXED => encode($eChapter['name']),
		};

	}

	public function getDetails(Course $eCourse, \Collection $cStaff): string {

		$rows = $this->getFicheRows($eCourse, $cStaff, TRUE);
		$rows[s("Dernière mise à jour")] = \util\DateUi::numeric($eCourse['updatedAt'], \util\DateUi::DATE);

		return self::getFicheTable($rows);

	}

	/**
	 * « Pour qui » dans le panneau d'inscription : ce que la fiche dit du public, sans les objectifs,
	 * que le panneau montre sous leur propre titre. Les prérequis s'y lisent toujours, « Sans
	 * prérequis » quand la formation n'en a pas écrit : le tableau n'est donc jamais vide.
	 */
	public function getAudience(Course $eCourse, \Collection $cStaff): string {
		return self::getFicheTable($this->getFicheRows($eCourse, $cStaff, FALSE));
	}

	/**
	 * Les lignes communes à la fiche et au panneau d'inscription, dans l'ordre de la planche L8. La fiche
	 * ouvre sur les Enjeux et les Objectifs, les deux textes d'éditeur côte à côte, dans l'ordre du
	 * panneau d'inscription (planche fiche-publique, variante A) ; le panneau les porte sous ses propres
	 * titres (`DateEnrollmentUi::getReading()`) et ne les reprend pas ici. Ensuite,
	 * les mentions de l'indicateur 1 de Qualiopi (accès, accessibilité, reconnaissance) ne
	 * s'affichent que remplies, le référent handicap se dit après le rôle de l'encadrant, sa
	 * présentation sous son nom (lot contact-mails), et le contact est l'encadrant désigné comme
	 * tel, avec son adresse, à défaut le contact de son réseau, à défaut l'adresse légale du groupe
	 * auteur (`StaffUi::getContact()`). Une formation de l'équipe n'a de contact que par un encadrant
	 * (porte L8, question 4, et lot contact-encadrant).
	 */
	protected function getFicheRows(Course $eCourse, \Collection $cStaff, bool $withObjectives): array {

		$rows = [];

		if($withObjectives) {

			if($eCourse['description'] !== NULL) {
				$rows[s("Enjeux")] = new \editor\EditorUi()->value($eCourse['description']);
			}

			if($eCourse['objectives'] !== NULL) {
				$rows[s("Objectifs")] = new \editor\EditorUi()->value($eCourse['objectives']);
			}

		}

		if($eCourse['audience'] !== NULL) {
			$rows[s("Public visé")] = encode($eCourse['audience']);
		}

		if($eCourse['prerequisites'] !== NULL) {
			$rows[s("Prérequis")] = encode($eCourse['prerequisites']);
		} else {
			$rows[s("Prérequis")] = s("Sans prérequis");
		}

		if($eCourse['access'] !== NULL) {
			$rows[self::p('access')->label] = encode($eCourse['access']);
		}

		if($eCourse['accessibility'] !== NULL) {
			$rows[self::p('accessibility')->label] = nl2br(encode($eCourse['accessibility']));
		}

		if($eCourse['recognition'] !== NULL) {
			$rows[self::p('recognition')->label] = encode($eCourse['recognition']);
		}

		// Une formation datée dit son prix sur chaque date, qui peut s'écarter de celui de la formation.
		if($eCourse->isDated() === FALSE) {

			$rows += self::getPriceRows($eCourse);

			if(
				$withObjectives and
				$eCourse['fundingNote'] !== NULL
			) {
				$rows[self::p('fundingNote')->label] = encode($eCourse['fundingNote']);
			}

		}

		if($cStaff->notEmpty()) {

			$staff = fn(Staff $eStaff) => '<b>'.encode($eStaff['supervisor']['name']).'</b>, '.encode($eStaff['role']).($eStaff['supervisor']['handicap'] ? ', '.s("référent handicap") : '');

			// Une présentation ne tient pas dans une ligne séparée par « · » : dès qu'un encadrant en a une,
			// chacun prend son bloc, la présentation sous son nom comme le résumé d'un module du programme.
			if($cStaff->contains(fn(Staff $eStaff) => $eStaff['supervisor']['description'] !== NULL)) {
				$rows[s("Encadrants")] = implode('', $cStaff->makeArray(fn(Staff $eStaff) => '<div class="learning-fiche-staff"><div>'.$staff($eStaff).'</div>'.($eStaff['supervisor']['description'] === NULL ? '' : '<p class="util-annotation util-annotation-long mb-0">'.encode($eStaff['supervisor']['description']).'</p>').'</div>'));
			} else {
				$rows[s("Encadrants")] = implode(' · ', $cStaff->makeArray($staff));
			}

		}

		$contact = StaffUi::getContact($eCourse, $cStaff);

		if($contact !== '') {
			$rows[s("Contact")] = $contact;
		}

		return $rows;

	}

	public static function getFicheTable(array $rows, string $class = ''): string {

		$h = '<div class="util-table util-table-bordered learning-table-fiche'.($class === '' ? '' : ' '.$class).'">';

			foreach($rows as $label => $value) {

				$h .= '<div class="util-table-row">';
					$h .= '<div class="util-label">'.$label.'</div>';
					$h .= '<div>'.$value.'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les têtes de module, à l'étape 1 de l'inscription, dans le panneau du membre comme sur la page
	 * publique du réseau : les leçons se lisent sur la fiche. Nommées comme sur la fiche.
	 */
	public function getProgramHeads(Course $eCourse, \Collection $cChapter): string {

		$h = '';

		foreach($cChapter as $eChapter) {

			$h .= '<div class="util-deck mb-1">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.self::getChapterName($eCourse, $eChapter).'</div>';
						$h .= '<p class="util-annotation">'.implode(' · ', array_filter([
							p("{value} leçon", "{value} leçons", $eChapter['cLesson']->count()),
							$eChapter['minutes'] === NULL ? NULL : self::getDuration($eChapter['minutes'])
						])).'</p>';
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Les formations de l'équipe, dans l'administration, rangées par thématique. Celles qui n'en ont
	 * pas encore ouvrent la liste : rangées par thématique, elles en sortiraient, et une formation
	 * qui n'est plus nulle part n'a plus d'éditeur à qui revenir.
	 */
	public function getTeamList(\Collection $cThemeCourse, \Collection $cCourseUnclassified): string {

		$h = '<div class="util-action">';
			$h .= '<h1>'.s("Formations").'</h1>';
			$h .= '<div>';
				$h .= '<a href="/learning/course:create" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Nouvelle formation").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= self::getAdminTabs('courses');

		if($cThemeCourse->empty() and $cCourseUnclassified->empty()) {

			$h .= '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune formation pour le moment").'</h3>';
				$h .= '<p>'.s("Une formation créée ici reste un brouillon jusqu'à ce que vous la publiiez au catalogue.").'</p>';
				$h .= '<div class="util-buttons">';
					$h .= '<a href="/learning/course:create" class="btn btn-primary">'.s("Créer une formation").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			return $h;

		}

		$groups = [];

		if($cCourseUnclassified->notEmpty()) {
			$groups[] = [s("Sans thématique"), $cCourseUnclassified];
		}

		foreach($cThemeCourse as $eTheme) {
			$groups[] = [encode($eTheme['name']), $eTheme['cCourse']];
		}

		$h .= '<div class="util-table util-table-hover learning-table-courses">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Formation").'</div>';
				$h .= '<div class="text-end hide-xs-down">'.s("Modules").'</div>';
				$h .= '<div class="text-end hide-xs-down">'.s("Leçons").'</div>';
				$h .= '<div class="text-end hide-xs-down">'.s("Durée").'</div>';
				$h .= '<div class="text-end hide-xs-down">'.s("Lecteurs").'</div>';
				$h .= '<div>'.s("État").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($groups as [$title, $cCourseGroup]) {

				$h .= '<div class="util-table-title">'.$title.'</div>';

				foreach($cCourseGroup as $eCourse) {

					$h .= '<div class="util-table-row">';
						$h .= '<div>'.self::getCoverPlay($eCourse).'</div>';
						$h .= '<div>';
							$h .= '<a href="'.self::urlEdition($eCourse).'"><b>'.encode($eCourse['name']).'</b></a>';
							if($eCourse['level']->notEmpty()) {
								$h .= '<div class="util-annotation">'.encode($eCourse['level']['name']).'</div>';
							}
						$h .= '</div>';
						$h .= '<div class="text-end hide-xs-down">'.$eCourse['chapters'].'</div>';
						$h .= '<div class="text-end hide-xs-down">'.$eCourse['lessons'].'</div>';
						$h .= '<div class="text-end hide-xs-down">'.self::getDuration($eCourse['minutes']).'</div>';
						$h .= '<div class="text-end hide-xs-down">'.$eCourse['readers'].'</div>';
						$h .= '<div>'.self::getStatus($eCourse).'</div>';
						$h .= '<div>'.$this->getActions($eCourse).'</div>';
					$h .= '</div>';

				}

			}

		$h .= '</div>';

		return $h;

	}

	public static function getAdminTabs(string $selected): string {

		$tabs = [
			'courses' => ['/learning/admin/course', s("Formations de l'équipe")],
			'themes' => ['/learning/admin/theme', s("Thématiques et niveaux")],
			'supervisors' => [SupervisorUi::urlAdmin(), s("Encadrants")],
			'catalog' => [CatalogUi::urlAdmin(), s("Catalogue public")],
			'reviews' => [ReviewUi::urlAdmin(), s("Avis")],
		];

		$h = '<div class="tabs">';
			$h .= '<div class="tabs-item">';
				foreach($tabs as $key => [$url, $label]) {
					$h .= '<a href="'.$url.'" class="tab-item'.($key === $selected ? ' selected' : '').'">'.$label.'</a>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La roue d'une formation, la seule : les tableaux de l'équipe, d'un réseau et d'un groupe, et
	 * l'éditeur, l'appellent tous, pour qu'une même formation montre partout le même menu (retour de
	 * Vincent du 29/09 sur trois menus qui divergeaient). « Modifier la formation » ouvre le panneau de
	 * la formation ; l'éditeur s'ouvre par le nom, dans les listes comme au retour du titre. Les
	 * gestes qui portent sur la formation la nomment, comme les roues du dépôt. Chaque
	 * entrée conditionnelle garde sa condition : le catalogue pour un réseau (`acceptCatalog()`), le
	 * suivi pour une formation ouverte à quelqu'un (`assertTrack()`), la dépublication pour une
	 * formation publiée. Dupliquer, qui crée une autre formation au lieu de régler celle-ci, a sa
	 * propre section (retour de Vincent du 03/10, point 4). Supprimer, séparé, énumère ce qui part
	 * avec la formation : c'est le seul moyen de le rendre lisible avant le clic.
	 */
	public function getActions(Course $eCourse): string {

		$eCourse->expects(['sheets']);

		$items = '<a href="/learning/course:updateSettings?id='.$eCourse['id'].'" class="dropdown-item">'.s("Modifier la formation").'</a>';
		$items .= '<a href="'.self::url($eCourse).'" class="dropdown-item">'.s("Voir la fiche").'</a>';
		$items .= CatalogUi::getCourseItem($eCourse);
		if($eCourse->assertTrack()) {
			$items .= '<a href="'.ProgressUi::urlTrack($eCourse).'" class="dropdown-item">'.s("Suivi des stagiaires").'</a>';
		}
		if($eCourse['status'] === Course::PUBLISHED) {
			$items .= '<a href="/learning/course:unpublish?id='.$eCourse['id'].'" class="dropdown-item">'.s("Dépublier la formation").'</a>';
		}
		$items .= '<div class="dropdown-divider"></div>';
		$items .= '<a href="'.self::urlDuplicate($eCourse).'" class="dropdown-item">'.s("Dupliquer la formation").'</a>';
		$items .= '<div class="dropdown-divider"></div>';
		$items .= '<div class="dropdown-subtitle">'.\Asset::icon('exclamation-circle').'  '.s("Zone de danger").'  '.\Asset::icon('exclamation-circle').'</div>';
		// Une feuille signée garde la formation (`CourseLib::delete()`) : le geste se montre fermé,
		// avec ce qui le ferme. `sheets` est posé par la page (`DateSheetLib::fillCountByCourse()`).
		if($eCourse['sheets'] > 0) {
			$items .= '<a class="dropdown-item disabled">';
				$items .= '<div class="util-item-label">'.s("Supprimer la formation").'</div>';
				$items .= '<div class="util-item-more">'.p("{value} feuille signée sur ses dates. Dépubliez-la plutôt.", "{value} feuilles signées sur ses dates. Dépubliez-la plutôt.", $eCourse['sheets']).'</div>';
			$items .= '</a>';
		} else {
			$items .= '<a data-ajax="/learning/course:doDelete" post-id="'.$eCourse['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette formation, ses modules, ses leçons et la progression de ses lecteurs ?").'" data-waiter>'.s("Supprimer la formation").'</a>';
		}

		return \main\DesignUi::getGear($items, encode($eCourse['name']));

	}

	/**
	 * Les formations d'un groupe, dans son onglet « Formations », en tableau. La liste d'un réseau
	 * reçoit le compte de ses formations par type (`types`, posé sur toute la liste) et le type
	 * retenu par les onglets de la bande (`type`, `getCommunityTypeTabs()`), `$cCourse` étant déjà
	 * réduite à ce type. `$withRequested` dit si le tableau d'un réseau porte la colonne
	 * « À confirmer ».
	 *
	 * Un groupe sans aucune formation ne passe pas par ici : son onglet est le parcours de création
	 * (`CourseStartUi`).
	 */
	public function getCommunityList(\structure\Structure $eStructure, \Collection $cCourse, array $types, ?string $type, bool $withRequested): string {

		if($eStructure->isNetwork()) {

			if($cCourse->empty()) {

				$definition = self::getType($type)[2];

				$h = '<div class="util-block-cta">';
					$h .= '<h3>'.self::getTypeIcons($type).' '.self::p('type')->values[$type].'</h3>';
					$h .= '<p>'.$definition.'</p>';
					// Une formation devient mixte par ses séances, elle ne se crée pas ainsi.
					if($type !== Course::MIXED) {
						$h .= '<div class="util-buttons">';
							$h .= '<a href="'.self::urlCreate($eStructure, $type).'" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.self::getCreateTitle($type).'</a>';
						$h .= '</div>';
					}
				$h .= '</div>';

				return $h;

			}

			return $this->getNetworkTable($cCourse, $withRequested, $types[Course::ONLINE] > 0);

		}

		$h = '<div class="util-table util-table-hover learning-table-community">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Formation").'</div>';
				$h .= '<div class="hide-xs-down">'.s("Ouverte à").'</div>';
				$h .= '<div class="text-end hide-xs-down">'.s("Lecteurs").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cCourse as $eCourse) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.self::getCoverPlay($eCourse).'</div>';
					$h .= '<div>';
						$h .= '<a href="'.self::urlEdition($eCourse).'"><b>'.encode($eCourse['name']).'</b></a>';

						// Ce que la formation n'a pas encore ne laisse pas de trace : une ligne à trous
						// se lirait comme une ligne mal rendue.
						$context = array_filter([
							$eCourse['theme']->empty() ? NULL : encode($eCourse['theme']['name']),
							$eCourse['level']->empty() ? NULL : encode($eCourse['level']['name']),
							p("{value} module", "{value} modules", $eCourse['chapters'])
						]);

						$h .= '<div>';
							$h .= self::getDraftBadge($eCourse);
							$h .= '<span class="util-annotation">'.implode(' · ', $context).'</span>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.self::getSharedTo($eCourse['cShare']).'</div>';
					$h .= '<div class="text-end hide-xs-down">'.$eCourse['readers'].'</div>';
					$h .= '<div>'.$this->getActions($eCourse).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le tableau des formations d'un réseau. Le type se lit sous le nom, à toutes les largeurs, et non
	 * en colonne : la colonne ne disait rien que la marque et le filtre au-dessus de la liste ne disent
	 * déjà, et elle prenait sa place au nom (contre-recette du 28/09, 158 px au lieu de 281 à 1400 px).
	 * Le visuel ouvre chaque ligne, comme dans les listes de l'équipe et d'un groupe, et reste jusqu'au
	 * téléphone. Sous 575 px, il ne garde que le visuel, le nom et la roue : la prochaine date passe
	 * sous le nom. Une formation en ligne n'a ni date ni demande, et porte un tiret dans ces deux
	 * colonnes plutôt qu'un zéro qui se lirait comme un compte.
	 *
	 * L'état et la place au catalogue public n'ont pas de colonne : ils se lisent sous le nom, dans
	 * l'ordre de la tête de l'éditeur, « Brouillon » s'il y a lieu, le type, la marque du catalogue.
	 * Une formation publiée et une formation privée ne se signalent pas, ce sont les états de départ
	 * (retour du 30/09).
	 *
	 * Cette liste ne dit pas le niveau, retiré à la demande de Vincent le 02/10. Le retrait ne s'étend
	 * ni à la liste d'un groupe de formation ni à l'administration du catalogue, qui le disent encore.
	 *
	 * « À confirmer » ajoute une piste à la grille, que porte `learning-table-network-requested`.
	 *
	 * Les leçons et les lecteurs ne concernent qu'une formation en ligne : leurs colonnes n'existent
	 * que dans un réseau qui en a une (`$withOnline`, compté avant le filtre comme les demandes, pour
	 * qu'elles ne paraissent ni ne disparaissent d'un onglet à l'autre), et une formation à dates y
	 * porte le tiret. Sans elles, la grille est `-dated`, ou `-dated-requested`.
	 */
	protected function getNetworkTable(\Collection $cCourse, bool $withRequested, bool $withOnline): string {

		$none = '<span class="color-muted">-</span>';

		if($withOnline) {
			$grid = $withRequested ? ' learning-table-network-requested' : '';
		} else {
			$grid = $withRequested ? ' learning-table-network-dated-requested' : ' learning-table-network-dated';
		}

		$h = '<div class="util-table util-table-even util-table-hover learning-table-network'.$grid.'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Formation").'</div>';
				$h .= '<div class="hide-xs-down">'.s("Prochaine date").'</div>';
				if($withRequested) {
					$h .= '<div class="text-end hide-xs-down">'.s("À confirmer").'</div>';
				}
				if($withOnline) {
					$h .= '<div class="text-end hide-lg-down">'.s("Leçons").'</div>';
					$h .= '<div class="text-end hide-xs-down">'.s("Lecteurs").'</div>';
				}
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cCourse as $eCourse) {

				if($eCourse->isDated() and $eCourse['dateNext'] !== NULL) {
					$dateNext = \util\DateUi::textual($eCourse['dateNext'], \util\DateUi::DAY_MONTH);
					$dates = p("{value} date à venir", "{value} dates à venir", $eCourse['dates']);
				} else {
					$dateNext = NULL;
					$dates = NULL;
				}

				$h .= '<div class="util-table-row">';

					$h .= '<div>'.self::getCoverPlay($eCourse).'</div>';

					$h .= '<div>';
						$h .= '<a href="'.self::urlEdition($eCourse).'"><b>'.encode($eCourse['name']).'</b></a>';
						$h .= '<div>';
							$h .= self::getDraftBadge($eCourse);
							$h .= self::getClosedBadge($eCourse);
							$h .= self::getTypeBadge($eCourse);
							if($eCourse['catalogBadge'] !== NULL) {
								$h .= CatalogUi::getBadge($eCourse).' ';
							}
							if($eCourse['theme']->notEmpty()) {
								$h .= '<span class="util-annotation">'.encode($eCourse['theme']['name']).'</span>';
							}
						$h .= '</div>';
						if($dateNext !== NULL) {
							$h .= '<div class="util-annotation hide-sm-up">'.$dateNext.' · '.$dates.'</div>';
						}
					$h .= '</div>';

					$h .= '<div class="hide-xs-down">';
						if($eCourse->isDated() === FALSE) {
							$h .= $none;
						} else if($dateNext === NULL) {
							$h .= '<span class="util-empty">'.s("Aucune date à venir").'</span>';
						} else {
							$h .= $dateNext;
							$h .= '<div class="util-annotation">'.$dates.'</div>';
						}
					$h .= '</div>';

					if($withRequested) {
						$h .= '<div class="text-end hide-xs-down">';
							if($eCourse->isDated() or $eCourse['requested'] === 0) {
								$h .= $eCourse['requested'];
							} else {
								$h .= self::getRequestedTrack($eCourse, (string)$eCourse['requested']);
							}
						$h .= '</div>';
					}
					if($withOnline) {
						$h .= '<div class="text-end hide-lg-down">'.($eCourse->isDated() ? $none : $eCourse['lessons']).'</div>';
						$h .= '<div class="text-end hide-xs-down">'.($eCourse->isDated() ? $none : $eCourse['readers']).'</div>';
					}
					$h .= '<div>'.$this->getActions($eCourse).'</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Brouillon » sous le nom d'une formation, dans les tableaux d'un groupe, suivi de son espace
	 * comme la marque du type. Une formation publiée ne le dit pas : c'est l'état attendu.
	 */
	private static function getDraftBadge(Course $eCourse): string {

		if($eCourse['status'] !== Course::DRAFT) {
			return '';
		}

		return self::getStatus($eCourse).' ';

	}

	/**
	 * « Pas encore ouverte aux membres » sous le nom d'une formation publiée de réseau que personne
	 * ne voit encore, à la place de « Brouillon » : l'équipe lit sur la liste ce qui lui reste à faire
	 * sans ouvrir chaque formation. Une formation ouverte ne le dit pas, c'est l'état attendu. La
	 * marque estompée est celle d'un état pas encore atteint.
	 */
	private static function getClosedBadge(Course $eCourse): string {

		if(
			$eCourse['status'] !== Course::PUBLISHED or
			$eCourse['cShare']->notEmpty()
		) {
			return '';
		}

		return '<span class="badge badge-primary badge-off">'.s("Pas encore ouverte aux membres").'</span> ';

	}

	/**
	 * À qui une formation d'un groupe de formation est ouverte, dans son tableau : le nom de la
	 * promotion quand il n'y en a qu'une, le nombre au-delà, pour que la colonne garde la largeur
	 * d'un nom. Un groupe de formation ne partage qu'à ses promotions (`Share::SECTION`).
	 */
	private static function getSharedTo(\Collection $cShare): string {

		if($cShare->empty()) {
			return '<span class="color-muted">-</span>';
		}

		if($cShare->count() === 1) {
			return encode($cShare->first()['section']['name']);
		}

		return p("{value} promotion", "{value} promotions", $cShare->count());

	}

	/**
	 * La tête de l'onglet « Formations », dans la bande de l'écran : les commentaires et les avis du
	 * groupe en barre avec la roue, puis la création d'une formation. La roue porte les encadrants du
	 * groupe, que toute son équipe ouvre, et pour qui gère un réseau les réglages de ses inscriptions
	 * et l'intégration de sa page publique. Le type d'une formation de réseau se choisit dans le
	 * parcours de création, à sa première étape. Ces gestes ne s'affichent qu'au-dessus d'une liste :
	 * un groupe sans formation a pour onglet le parcours lui-même.
	 */
	public static function getCommunityTitle(\structure\Structure $eStructure, int $commentsNew, int $reviewsNew): string {

		$items = '<a href="'.SupervisorUi::urlCommunity($eStructure).'" class="dropdown-item">'.s("Encadrants").'</a>';
		if(
			$eStructure->isNetwork() and
			$eStructure->canManage()
		) {
			$items .= '<a href="/learning/community:updateEnrollment?id='.$eStructure['id'].'" class="dropdown-item">'.s("Réglages des inscriptions").'</a>';
			$items .= '<a href="'.DateEnrollmentUi::urlIntegration($eStructure).'" class="dropdown-item">'.s("Intégrer sur votre site").'</a>';
		}

		$settings = \main\DesignUi::getGear($items);

		return \main\DesignUi::getPageHeader(
			s("Formations"),
			tools: CommentUi::getInboxButton($eStructure, $commentsNew).ReviewUi::getInboxButton($eStructure, $reviewsNew),
			create: '<a href="/learning/course:create?community='.$eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.s("Nouvelle formation").'</a>',
			settings: $settings
		);

	}

	/**
	 * Les types de formation d'un réseau, en onglets de la bande de l'écran, chacun avec son compte :
	 * « Toutes », puis les types de `getTypesShown()`. Un type sans formation se crée depuis
	 * « Nouvelle formation ». `$types` compte toute la liste, quel que soit le type retenu. Un groupe
	 * qui n'est pas un réseau n'a qu'un type, et pas d'onglets.
	 */
	public static function getCommunityTypeTabs(\structure\Structure $eStructure, array $types, ?string $type): string {

		if($eStructure->isNetwork() === FALSE) {
			return '';
		}

		$url = '/groupe/'.$eStructure['id'].'/formations';

		$tab = fn(?string $option, string $label, int $count): string => '<a href="'.$url.($option === NULL ? '' : '?type='.$option).'" class="tab-item'.($option === $type ? ' selected' : '').'"><span class="tab-item-name">'.$label.'</span> <small class="util-counter">'.$count.'</small></a>';

		$h = $tab(NULL, s("Toutes"), array_sum($types));

		foreach(self::getTypesShown($types, $type) as $typeOption) {
			$h .= $tab($typeOption, self::getTypeIcons($typeOption).'&nbsp;'.self::p('type')->values[$typeOption], $types[$typeOption]);
		}

		return $h;

	}

	/**
	 * Les types qu'une liste de réseau propose, aux onglets de l'équipe comme au filtre du membre :
	 * ceux qui ont des formations, et celui que l'adresse retient même s'il n'en a pas.
	 */
	protected static function getTypesShown(array $types, ?string $type): array {

		return array_values(array_filter(
			[Course::ONSITE, Course::REMOTE, Course::MIXED, Course::ONLINE],
			fn(string $typeOption) => ($types[$typeOption] > 0 or $typeOption === $type)
		));

	}

	public static function urlCreate(\structure\Structure $eStructure, string $type): string {
		return '/learning/course:create?community='.$eStructure['id'].'&type='.$type;
	}

	protected static function getCreateTitle(string $type): string {

		return match($type) {
			Course::ONSITE => s("Nouvelle formation en présentiel"),
			Course::REMOTE => s("Nouvelle formation en visio"),
			Course::ONLINE => s("Nouvelle formation en ligne"),
		};

	}

	/**
	 * Le filtre « Type » d'une liste de formations de réseau : « Toutes », puis un jeton par type de
	 * `getTypesShown()`, chacun avec son compte. `$types` compte toute la liste, quel que soit le
	 * type retenu. `$link` rend l'attribut qui mène à une option, `NULL` pour « Toutes ».
	 */
	public static function getTypeFilter(array $types, ?string $type, \Closure $link): string {

		$option = function(?string $option, string $label, int $count) use($type, $link): string {

			$selected = ($option === $type);

			$h = '<a '.$link($option).' class="util-filter-option'.($selected ? ' is-selected' : '').'">';
				$h .= $label;
				if($count > 0) {
					$h .= '<span class="util-counter'.($selected ? ' util-counter-solid' : '').'">'.$count.'</span>';
				}
			$h .= '</a>';

			return $h;

		};

		$h = '<div class="util-filters learning-filters mb-2">';
			$h .= '<div class="util-filter">';
				$h .= '<div class="util-filter-label">'.self::p('type')->label.'</div>';
				$h .= '<div class="util-filter-options">';

					$h .= $option(NULL, s("Toutes"), array_sum($types));

					foreach(self::getTypesShown($types, $type) as $typeOption) {
						$h .= $option($typeOption, self::getTypeIcons($typeOption).'&nbsp;'.self::p('type')->values[$typeOption], $types[$typeOption]);
					}

				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La tête de l'éditeur, la même sous les deux gabarits : l'en-tête d'écran du socle, avec le
	 * visuel de la formation en logo, ou l'aplat de sa thématique, comme dans les listes. Le
	 * disque de retour précède le titre et ramène à la liste d'où la formation s'édite, celle du groupe
	 * ou celle de l'équipe (retour de Vincent du 01/10, remarque 24). L'état ne passe pas par `state`
	 * mais ouvre la ligne de contexte (avis ui du 30/09) : il y rejoint la marque du type, et les
	 * deux pastilles tiennent sur un rang quelle que soit la longueur du titre, là où `state` les
	 * aurait séparées sur deux rangs. Le Bilan y pose sa période, à côté du titre (`$season`), parce
	 * qu'elle gouverne tout l'écran (Vincent, 23/10).
	 */
	public function getEditionHead(Course $eCourse, string $season = ''): string {

		return \main\DesignUi::getPageHeader(
			encode($eCourse['name']),
			context: $this->getEditionContext($eCourse),
			logo: self::getCover($eCourse, 'learning-cover-md'),
			back: self::urlList($eCourse),
			create: $this->getEditionCreate($eCourse),
			settings: $this->getActions($eCourse),
			season: $season,
			backLabel: s("Formations")
		);

	}

	/**
	 * Ce qui situe la formation, sous son titre : son état et son type en marque, puis son niveau, son
	 * domaine, son origine, à qui elle est ouverte et, en ligne, combien de lecteurs l'ont commencée :
	 * une formation à dates se suit à ses dates, pas à son rythme. Les autres
	 * comptes n'y sont pas : chacun est dans le titre de sa section, sous l'onglet Organisation ou
	 * Leçons. Le nombre de lecteurs ouvre le suivi sous la même condition que dans les tableaux, et
	 * reste du texte sinon ; l'espace insécable le garde d'un bloc quand la ligne se replie.
	 */
	public function getEditionContext(Course $eCourse): string {

		$context = array_filter([
			self::getLevel($eCourse),
			$eCourse['theme']->empty() ? NULL : encode($eCourse['theme']['name']),
			self::getOrigin($eCourse),
			self::getSharedSummary($eCourse),
			$eCourse->isDated() ? NULL : self::getEditionRequested($eCourse),
			$eCourse->isDated() ? NULL : self::getEditionReaders($eCourse)
		]);

		$h = self::getStatus($eCourse).' ';
		$h .= self::getTypeBadge($eCourse);

		if($context !== []) {
			$h .= '<span class="util-annotation">'.implode(' · ', $context).'</span>';
		}

		return $h;

	}

	/**
	 * Les demandes d'inscription qui attendent l'équipe, que rien ne signale tant qu'il n'y en a
	 * pas. Une formation à dates les compte à chacune de ses dates, sous l'onglet Organisation.
	 */
	private static function getEditionRequested(Course $eCourse): ?string {

		$requested = $eCourse['requested'];

		if($requested === 0) {
			return NULL;
		}

		return self::getRequestedTrack($eCourse, s("{value}&nbsp;à confirmer", $requested));

	}

	/**
	 * Les demandes d'une formation en ligne se confirment en tête de son suivi : leur compte y mène
	 * sous la même condition que le nombre de lecteurs, et reste du texte sinon.
	 */
	private static function getRequestedTrack(Course $eCourse, string $label): string {

		if($eCourse->assertTrack()) {
			return '<a href="'.ProgressUi::urlTrack($eCourse).'">'.$label.'</a>';
		}

		return $label;

	}

	private static function getEditionReaders(Course $eCourse): string {

		$readers = p("{value}&nbsp;lecteur", "{value}&nbsp;lecteurs", $eCourse['readers']);

		if($eCourse->assertTrack()) {
			return '<a href="'.ProgressUi::urlTrack($eCourse).'">'.$readers.'</a>';
		}

		return $readers;

	}

	/**
	 * À qui la formation est ouverte, dit sous le titre de l'éditeur, le bouton « Ouvrir aux
	 * membres » ou « Ouvrir à une promotion » à côté étant le geste qui le change. L'ouverture est
	 * rangée derrière ce bouton : sans la ligne, l'auteur ne sait pas qu'une formation publiée n'est
	 * encore ouverte à personne.
	 *
	 * Une formation de l'équipe n'a pas de partage, c'est un catalogue : rien, et `cShare` n'est pas
	 * lu, la page ne le posant que sur les formations d'un groupe. Un brouillon non plus : il garde
	 * les partages d'avant sa dépublication, mais seuls ses auteurs le voient, et la marque
	 * « Brouillon » le dit juste avant. « Pas encore ouverte » suit la condition du bouton
	 * (`Course::acceptShareAudience()`) et ouvre le même panneau. Sans elle, un groupe sans promotion
	 * ne lit rien, un état qu'il ne peut pas défaire ; un réseau sans membre hors de son équipe lit
	 * l'état sans lien, le panneau n'ayant personne à proposer.
	 * Une formation de réseau ouverte n'a plus de bouton : il proposerait ce qui est déjà fait, et
	 * « Ouverte à tous les membres » devient sous la même condition le lien du panneau, où l'on
	 * referme. L'ouverture d'un réseau n'a pas de période. Celle d'un groupe n'est dite que si tous ses
	 * partages ont la même, un groupe réglant la sienne par promotion.
	 */
	private static function getSharedSummary(Course $eCourse): ?string {

		if(
			$eCourse->isTeam() or
			$eCourse['status'] === Course::DRAFT
		) {
			return NULL;
		}

		$cShare = $eCourse['cShare'];

		if($cShare->empty()) {

			$label = $eCourse->isNetwork() ? s("Pas encore ouverte aux membres") : s("Pas encore ouverte");

			if($eCourse->acceptShareAudience()) {
				return '<a href="/learning/course:share?id='.$eCourse['id'].'">'.$label.'</a>';
			}

			return $eCourse->isNetwork() ? $label : NULL;

		}

		if($eCourse->isNetwork()) {

			$label = s("Ouverte à tous les membres");

			return $eCourse->acceptShareAudience()
				? '<a href="/learning/course:share?id='.$eCourse['id'].'">'.$label.'</a>'
				: $label;

		}

		$eShareFirst = $cShare->first();
		$periods = [];

		foreach($cShare as $eShare) {
			$periods[$eShare['opensAt'].'|'.$eShare['dueAt']] = TRUE;
		}

		$audience = ($cShare->count() === 1)
			? encode($eShareFirst['section']['name'])
			: p("{value} promotion", "{value} promotions", $cShare->count());

		if(count($periods) > 1) {
			return s("Ouverte à {audience}", ['audience' => $audience]);
		}

		$opensAt = \util\DateUi::numeric($eShareFirst['opensAt'], \util\DateUi::DATE);
		$dueAt = ($eShareFirst['dueAt'] === NULL) ? NULL : \util\DateUi::numeric($eShareFirst['dueAt'], \util\DateUi::DATE);

		if($eShareFirst->isOpen() === FALSE) {

			return ($dueAt === NULL)
				? s("Ouverte à {audience} à partir du&nbsp;{opensAt}", ['audience' => $audience, 'opensAt' => $opensAt])
				: s("Ouverte à {audience} du&nbsp;{opensAt} au&nbsp;{dueAt}", ['audience' => $audience, 'opensAt' => $opensAt, 'dueAt' => $dueAt]);

		}

		if($dueAt === NULL) {
			return s("Ouverte à {audience}", ['audience' => $audience]);
		}

		return $eShareFirst->acceptProgress()
			? s("Ouverte à {audience} jusqu'au&nbsp;{dueAt}", ['audience' => $audience, 'dueAt' => $dueAt])
			: s("Ouverte à {audience}, échéance passée le&nbsp;{dueAt}", ['audience' => $audience, 'dueAt' => $dueAt]);

	}

	/**
	 * Les gestes qui font avancer la formation, à gauche de la roue dans l'en-tête de l'éditeur. Un
	 * seul geste plein : « Publier » en brouillon, « Ouvrir aux membres » ou « Ouvrir à une
	 * promotion » quand il est proposé, les deux ne coexistant jamais (l'ouverture demande une
	 * formation publiée). Un réseau n'ouvre qu'une fois, à tous ses membres : ouverte, sa formation
	 * se rouvre par la ligne sous le titre (`getSharedSummary()`), alors qu'un groupe ouvre encore à
	 * une promotion de plus. La roue, à côté, est celle des
	 * listes (`getActions()`), un réglage et jamais ce qui fait avancer l'écran : elle reste en
	 * contour, même seule. Ce qui ne se fait pas tous les jours, dont la dépublication, vit sous elle.
	 * Le suivi des stagiaires a deux accès : le nombre de lecteurs au bout de la ligne sous le titre,
	 * et l'entrée de la roue, seul libellé qui se lit au doigt. L'interrupteur des inscriptions, un
	 * réglage lui aussi, se montre tant que la formation en prend (`getEnrollmentSwitch()`).
	 */
	private function getEditionCreate(Course $eCourse): string {

		$h = '';

		if($eCourse['status'] === Course::DRAFT) {
			$h .= '<a href="/learning/course:publish?id='.$eCourse['id'].'" class="btn btn-secondary">'.s("Publier").'</a>';
		}

		if(
			$eCourse->acceptShareAudience() and
			($eCourse->isNetwork() === FALSE or $eCourse['cShare']->empty())
		) {
			$label = $eCourse->isNetwork() ? s("Ouvrir aux membres") : s("Ouvrir à une promotion");
			$h .= '<a href="/learning/course:share?id='.$eCourse['id'].'" class="btn btn-secondary">'.\Asset::icon('people-fill').' '.$label.'</a>';
		}

		if($eCourse->assertUpdateEnrollmentPaused()) {
			$h .= self::getEnrollmentSwitch($eCourse);
		}

		return $h;

	}

	/**
	 * Mettre en pause les nouvelles inscriptions de la formation et de toutes ses dates, puis les
	 * rouvrir, d'un geste appliqué tout de suite : celles déjà reçues continuent, rien ne s'efface.
	 */
	public static function getEnrollmentSwitch(Course $eCourse): string {

		$paused = $eCourse->isEnrollmentPaused();

		return \util\TextUi::switch([
			'id' => 'learning-course-enrollment-'.$eCourse['id'],
			'data-ajax' => '/learning/course:doUpdateEnrollmentPaused',
			'post-id' => $eCourse['id'],
			'post-enrollment-paused' => $paused ? 0 : 1
		], $paused === FALSE, s("Inscriptions ouvertes"), s("Inscriptions en pause"));

	}

	/**
	 * Le corps de l'éditeur, en onglets : l'organisation (les dates), les leçons, la fiche technique
	 * et les encadrants, venus de la roue en dernier (retour de Vincent du 30/09, point 5), puis la
	 * diffusion, qui était une page à part (retour de Vincent du 03/10, point 5).
	 * L'organisation n'existe que pour une formation à dates, qui s'ouvre dessus ; les autres
	 * s'ouvrent sur les leçons. La diffusion n'existe que pour une formation qui a une offre et un
	 * circuit, celle d'un réseau, quel que soit son type : la page en décide (`$withDiffusion`, par
	 * `acceptCatalog()`) et prépare alors ses dates planifiées à venir (`$cDatePlanned`). `Lime.Tab.restore()` reprend l'onglet retenu par le navigateur, sauf
	 * quand une redirection en demande un (`$tab`). La rangée porte l'identifiant de la formation :
	 * `Lime.Tab` range l'onglet retenu sous l'identifiant de la rangée, donc sous une clé par
	 * formation (retour de Vincent du 01/10, remarque 23). Le nombre de leçons et leur durée, que le
	 * titre « Leçons » porte, sont posés par la page.
	 */
	public function getEdition(Course $eCourse, \Collection $cChapter, \Collection $cDate = new \Collection(), \Collection $cStaff = new \Collection(), string $tab = '', bool $withDiffusion = FALSE, \Collection $cDatePlanned = new \Collection()): string {

		$edition = $this->getEditionTabs($eCourse, $cChapter, $cDate, $cStaff, $tab, $withDiffusion, $cDatePlanned);

		$h = '<div class="tabs tabs-h tabs-h-fold" '.attrs($edition['attributes']).'>';
			$h .= '<div class="tabs-item">'.$edition['items'].'</div>';
			$h .= $edition['panels'];
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les onglets de l'éditeur en trois morceaux : les attributs de leur `.tabs`, les onglets et les
	 * panneaux. Sous un groupe, les onglets sont dans la bande de l'écran et le gabarit pose le
	 * `.tabs` autour de la bande et du corps (`GroupTemplate::$mainTabsAttributes`) ; `getEdition()`
	 * les assemble pour l'équipe. Le Bilan, page à part, ferme la rangée en lien (`getEditionUsageTab()`).
	 */
	public function getEditionTabs(Course $eCourse, \Collection $cChapter, \Collection $cDate = new \Collection(), \Collection $cStaff = new \Collection(), string $tab = '', bool $withDiffusion = FALSE, \Collection $cDatePlanned = new \Collection()): array {

		$eCourse->expects(['lessons', 'minutes']);

		$tabs = $this->getEditionLabels($eCourse, $withDiffusion);

		$default = array_key_first($tabs);

		$items = '';

		foreach($tabs as $name => $label) {
			$items .= '<a class="tab-item'.($name === $default ? ' selected' : '').'" data-tab="'.$name.'" onclick="Lime.Tab.select(this)">'.$label.'</a>';
		}

		$items .= $this->getEditionUsageTab($eCourse, FALSE);

		$panels = '';

		foreach($tabs as $name => $label) {

			$panels .= '<div class="tab-panel'.($name === $default ? ' selected' : '').'" data-tab="'.$name.'">';
				$panels .= match($name) {
					'dates' => $this->getEditionExternal($eCourse).$this->getEditionPrice($eCourse).new DateUi()->getEditionSection($eCourse, $cDate),
					'lessons' => $this->getEditionLessons($eCourse, $cChapter),
					'details' => $this->getEditionDetails($eCourse).($eCourse->isDated() ? '' : $this->getEditionPrice($eCourse)),
					'staff' => new StaffUi()->getEditionSection($eCourse, $cStaff),
					'diffusion' => new CatalogUi()->getCourse($eCourse, $cDatePlanned),
				};
			$panels .= '</div>';

		}

		return [
			'attributes' => [
				'id' => 'learning-course-tabs-'.$eCourse['id'],
				'onrender' => 'Lime.Tab.restore(this, "'.$default.'", "'.$tab.'")'
			],
			'items' => $items,
			'panels' => $panels
		];

	}

	/**
	 * Les onglets de l'éditeur vus du Bilan, qui est une page à part : chacun y est un lien qui rouvre
	 * l'éditeur sur lui (`?tab=`), et le Bilan est l'onglet choisi. Sans `data-tab` ni `onrender`,
	 * `Lime.Tab` ne touche pas à la rangée.
	 */
	public function getEditionLinks(Course $eCourse, bool $withDiffusion): array {

		$items = '';

		foreach($this->getEditionLabels($eCourse, $withDiffusion) as $name => $label) {
			$items .= '<a href="'.self::urlEdition($eCourse).'?tab='.$name.'" class="tab-item">'.$label.'</a>';
		}

		$items .= $this->getEditionUsageTab($eCourse, TRUE);

		return [
			'attributes' => [],
			'items' => $items
		];

	}

	/**
	 * Les onglets qui existent pour la formation, dans l'ordre de `EDITION_TABS`, sous leur libellé.
	 * L'organisation n'existe que pour une formation à dates, la diffusion que pour une formation qui a
	 * une offre et un circuit, ce que la page décide (`$withDiffusion`, par `acceptCatalog()`).
	 */
	protected function getEditionLabels(Course $eCourse, bool $withDiffusion): array {

		$tabs = [];

		foreach(self::EDITION_TABS as $name) {

			if($name === 'dates' and $eCourse->isDated() === FALSE) {
				continue;
			}

			if($name === 'diffusion' and $withDiffusion === FALSE) {
				continue;
			}

			$tabs[$name] = match($name) {
				'dates' => s("Organisation"),
				'lessons' => s("Leçons"),
				'details' => self::getDetailsWording()['title'],
				'staff' => s("Encadrants"),
				'diffusion' => s("Diffusion"),
			};

		}

		return $tabs;

	}

	/**
	 * Le Bilan, en dernier, en lien vers sa page : sa période ne peut régner que sur une page à elle.
	 * Une formation à dates n'en a pas (`acceptUsage()`).
	 */
	protected function getEditionUsageTab(Course $eCourse, bool $selected): string {

		if($eCourse->acceptUsage() === FALSE) {
			return '';
		}

		return '<a href="'.self::urlUsage($eCourse).'" class="tab-item'.($selected ? ' selected' : '').'">'.s("Bilan").'</a>';

	}

	/**
	 * Les libellés de la fiche technique, en un seul endroit : l'onglet, qui est aussi le titre de sa
	 * section, et le titre de son panneau (retour de Vincent du 30/09, point 6). Le message de succès
	 * `Course::detailsUpdated` y est écrit en dur, dans `Alert.u.php` : il se change à la main avec eux. La fiche
	 * publique garde « La formation en détail », qui s'adresse au lecteur et non à l'auteur.
	 */
	protected static function getDetailsWording(): array {

		return [
			'title' => s("Fiche technique"),
			'panel' => s("Modifier la fiche technique"),
		];

	}

	/**
	 * Les leçons, sous leur titre qui porte leur nombre et leur durée.
	 */
	protected function getEditionLessons(Course $eCourse, \Collection $cChapter): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>';
				$h .= s("Leçons");
				if($eCourse['lessons'] > 0) {
					$h .= ' <span class="util-counter">'.$eCourse['lessons'].'</span>';
					if($eCourse['minutes'] !== NULL) {
						$h .= ' <span class="util-annotation">'.self::getDuration($eCourse['minutes']).'</span>';
					}
				}
			$h .= '</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/chapter:create?course='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter un module").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= $this->getEditionProgram($eCourse, $cChapter);

		return $h;

	}

	/**
	 * Le détail en lecture, champ par champ, dans l'ordre du panneau qui le modifie. Contrairement à la
	 * fiche, qui tait un champ vide, l'éditeur le dit : c'est ce qui reste à écrire.
	 */
	protected function getEditionDetails(Course $eCourse): string {

		$properties = ['description', 'objectives', 'audience', 'prerequisites', 'access', 'accessibility', 'recognition'];

		$eCourse->expects($properties);

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.self::getDetailsWording()['title'].'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/course:updateDetails?id='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$rows = [];

		foreach($properties as $property) {

			if($eCourse[$property] === NULL) {
				$value = '<span class="util-empty">'.s("Non renseigné").'</span>';
			} else {
				$value = match($property) {
					'description', 'objectives' => new \editor\EditorUi()->value($eCourse[$property]),
					'audience', 'prerequisites', 'access', 'accessibility', 'recognition' => nl2br(encode($eCourse[$property]))
				};
			}

			$rows[self::p($property)->label] = $value;

		}

		$h .= self::getFicheTable($rows);

		return $h;

	}

	/**
	 * Où se font les nouvelles inscriptions (lot annuaire), en tête de l'onglet Organisation, en une
	 * seule ligne : sur Ouvretaferme, ou sur le site de l'organisme suivi de la page de la formation
	 * (retour de Vincent du 30/09, point 1). Même forme que le détail, une table et « Modifier » à
	 * droite du titre. Le lien ne suit que s'il y a une page : une formation fermée peut n'en avoir
	 * aucune, quand chacune de ses dates à venir porte la sienne (`externalUrl.uncovered`).
	 */
	protected function getEditionExternal(Course $eCourse): string {

		$eCourse->expects(['externalOnly', 'externalUrl']);

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Inscription").'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/course:updateExternal?id='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$value = self::p('externalOnly')->values[$eCourse['externalOnly']];

		if(
			$eCourse['externalOnly'] and
			$eCourse['externalUrl'] !== NULL
		) {
			$value .= '<br/><a href="'.encode($eCourse['externalUrl']).'" target="_blank">'.encode($eCourse['externalUrl']).'</a>';
		}

		$h .= self::getFicheTable([
			s("Nouvelles inscriptions") => $value,
		]);

		return $h;

	}

	/**
	 * Le prix et le financement d'une formation de réseau, sous l'inscription dans l'onglet
	 * Organisation d'une formation datée, après la fiche technique d'une formation en ligne : même
	 * forme que ces deux sections, une table et « Modifier » à droite du titre. Comme la fiche
	 * technique, l'éditeur dit ce qui reste à écrire. Les conditions ne s'y lisent que saisies : elles
	 * précisent les financeurs, qui disent déjà « Non renseigné ». L'éditeur ne s'ouvre qu'à qui
	 * écrit la formation, comme ses autres sections : seul le réseau se vérifie ici, la page de
	 * `updatePrice` vérifiant le reste de `acceptUpdatePrice()`.
	 */
	protected function getEditionPrice(Course $eCourse): string {

		if($eCourse->isNetwork() === FALSE) {
			return '';
		}

		$eCourse->expects(['priceFunded', 'price', 'funding', 'fundingNote']);

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Prix et financement").'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/course:updatePrice?id='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$empty = '<span class="util-empty">'.s("Non renseigné").'</span>';

		$rows = [
			self::p('priceFunded')->label => ($eCourse['priceFunded'] === NULL) ? $empty : self::getAmount($eCourse['priceFunded']),
			self::p('price')->label => ($eCourse['price'] === NULL) ? $empty : self::getAmount($eCourse['price']),
			s("Finançable par") => ($eCourse['funding'] === NULL) ? $empty : implode(', ', self::getFundingLabels($eCourse['funding'])),
		];

		if($eCourse['fundingNote'] !== NULL) {
			$rows[self::p('fundingNote')->label] = encode($eCourse['fundingNote']);
		}

		$h .= self::getFicheTable($rows);

		return $h;

	}

	/**
	 * « Formation payante » oui / non en tête (choix 3 A du lot glacons-parcours) : les quatre champs
	 * ne paraissent que sur oui, par `field-toggle` du socle, et sur non `Course::paid` les écrit
	 * gratuits quoi qu'ils postent. Sous le paiement en ligne par le stagiaire, la question ne se pose
	 * pas : « Autre public » est ce que le paiement encaisse, et il est exigé (`price.trainee`).
	 */
	public function updatePrice(Course $eCourse): \Panel {

		$eCourse->expects(['offer', 'paid']);

		$form = new \util\FormUi();

		if($eCourse['offer'] === Course::TRAINEE) {

			$h = $form->openAjax('/learning/course:doUpdatePrice');

				$h .= $form->hidden('id', $eCourse);

				$h .= $form->dynamicGroups($eCourse, ['priceFunded', 'price*', 'funding', 'fundingNote']);

		} else {

			$toggle = function(\PropertyDescriber $d) {
				$d->group = ['class' => 'field-toggle'];
			};

			$h = $form->openAjax('/learning/course:doUpdatePrice', ['class' => 'field-toggle-wrapper']);

				$h .= $form->hidden('id', $eCourse);

				$h .= $form->dynamicGroup($eCourse, 'paid');

				$h .= $form->dynamicGroups($eCourse, ['priceFunded', 'price', 'funding', 'fundingNote'], array_fill_keys(['priceFunded', 'price', 'funding', 'fundingNote'], $toggle));

		}

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-course-price',
			title: s("Prix et financement"),
			subTitle: self::getTypeBadge($eCourse).encode($eCourse['name']),
			body: $h
		);

	}

	/**
	 * Le réglage de l'inscription, en une ligne oui / non à l'infinitif, « Envoyer les nouvelles
	 * inscriptions sur le site de l'organisme » (retours de Vincent du 01/10). La page de la
	 * formation ne paraît que sur oui, par `field-toggle` du socle (`component.css`), sans script.
	 * Masquée, elle est postée quand même : `externalUrl.unlinked` la met à NULL avant le contrôle du
	 * schéma, et c'est ce NULL qui s'écrit. L'effacement que porte `CourseLib::update()` tient un
	 * autre chemin, le passage en ligne (`doUpdateStartType`), qui rouvre l'inscription sans
	 * construire l'adresse. `novalidate` pour la même raison que `unlinked` : le navigateur
	 * refuserait l'envoi sur une adresse mal formée restée dans le champ masqué, sans pouvoir montrer
	 * où (`shop/ui/StartAi.u.php`). Le schéma juge l'adresse à sa place.
	 */
	public function updateExternal(Course $eCourse): \Panel {

		$eCourse->expects(['externalOnly', 'externalUrl', 'externalUrlRequired']);

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/course:doUpdateExternal', ['class' => 'field-toggle-wrapper', 'novalidate']);

			$h .= $form->hidden('id', $eCourse);

			$h .= $form->dynamicGroup($eCourse, 'externalOnly');

			$h .= $form->dynamicGroup($eCourse, $eCourse['externalUrlRequired'] ? 'externalUrl*' : 'externalUrl', function(\PropertyDescriber $d) {
				$d->group = ['class' => 'field-toggle'];
			});

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-course-external',
			title: s("Modifier l'inscription"),
			body: $h
		);

	}

	public function updateDetails(Course $eCourse): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/course:doUpdateDetails');

			$h .= $form->hidden('id', $eCourse);

			$h .= $form->dynamicGroups($eCourse, ['description', 'objectives', 'audience', 'prerequisites']);

			$h .= '<h4 class="mt-1">'.s("Accès et accessibilité").'</h4>';

			$h .= $form->dynamicGroups($eCourse, ['access', 'accessibility', 'recognition']);

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-course-details',
			title: self::getDetailsWording()['panel'],
			body: $h
		);

	}

	public function updateSettings(Course $eCourse): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/course:doUpdateSettings');

			$h .= $form->hidden('id', $eCourse);

			$h .= $form->dynamicGroups($eCourse, $eCourse->isTeam() ? ['name*', 'summary', 'open*'] : ['name*', 'summary']);

			// Le type se change tant que la formation n'a aucune date (`dated`, posé par la page) ; au
			// delà, l'étape `Course::type.dated` refuse un autre type posté. Chaque choix porte la marque
			// du type, celle des listes, comme au parcours de création (`CourseStart.u.php`). Le réglage
			// se fait ici et non dans le describer : `radios()` passe au rendu de l'option sa valeur, le
			// libellé, et non sa clé (`framework/util/ui/Form.u.php`), d'où les clés prises pour valeurs.
			if(
				$eCourse->isNetwork() and
				$eCourse['dated'] === FALSE
			) {
				$h .= $form->dynamicGroup($eCourse, 'type*', function(\PropertyDescriber $d) {
					// Une formation devient mixte par ses séances (`CourseLib::synchronizeType()`) : le
					// type ne se choisit pas, `Course::type.chosen` le refuse.
					$types = array_values(array_diff(array_keys($d->values), [Course::MIXED]));
					$d->values = array_combine($types, $types);
					$d->attributes['callbackRadioContent'] = fn(string $type) => CourseUi::getTypeMark($type);
				});
			}

			// Thématique, niveau et visuel classent la formation partout où elle se liste, catalogue ou
			// non : l'intertitre ne promet rien du catalogue public, que la page du même nom décide (L14).
			$h .= '<h4 class="mt-1">'.s("Classement").'</h4>';

			$h .= $form->dynamicGroup($eCourse, 'theme');
			$h .= self::getSubthemeField($form, $eCourse);
			$h .= $form->dynamicGroup($eCourse, 'level');

			$h .= $form->group(
				CourseUi::p('vignette')->label,
				new \media\LearningCourseUi()->getCamera($eCourse, width: '16rem', height: 'auto')
			);

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-course-settings',
			title: s("Modifier la formation"),
			body: $h
		);

	}

	/**
	 * Le groupe de la sous-thématique, que `LearningCourse.changeTheme()` recharge quand la
	 * thématique change (`/learning/course:getSubthemeField`). Le conteneur reste dans la page même
	 * vide, sans thématique ou sous une thématique qui n'a pas de sous-thématique : la réponse a
	 * besoin d'un nœud à remplacer.
	 */
	public static function getSubthemeField(\util\FormUi $form, Course $eCourse): string {

		$eCourse->expects(['cSubtheme']);

		$h = '<div id="learning-course-subtheme-field">';

			if($eCourse['cSubtheme']->notEmpty()) {
				$h .= $form->dynamicGroup($eCourse, 'subtheme');
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que la formation pèse, sous les deux titres de publication. Le nombre de leçons est posé sur
	 * l'élément par la page, `lessons` étant déjà la clé que la fiche et la liste lisent. Une
	 * formation de réseau compte ses dates à la place de ses modules.
	 */
	public static function getPublicationSubTitle(Course $eCourse, \Collection $cChapter): string {

		$counts = [];

		if($eCourse->isDated()) {
			$counts[] = p("{value} date", "{value} dates", $eCourse['dates']);
		} else {
			$counts[] = p("{value} module", "{value} modules", $cChapter->count());
		}

		$counts[] = p("{value} leçon", "{value} leçons", $eCourse['lessons']);

		return implode(' · ', $counts);

	}

	/**
	 * Le panneau de publication, dans ses deux états. Ce qui manque est nommé et cliquable : chaque
	 * ligne mène au panneau où on le répare, le classement dans le panneau de la formation, la
	 * durée dans celui de la leçon, où elle se saisit, la première question d'un quiz dans
	 * l'écran de la leçon, où la section du quiz l'appelle, la première leçon d'une formation en
	 * ligne dans le panneau qui la crée.
	 *
	 * Ce qui bloque vit dans la carte « Pour publier », ce qui est seulement recommandé (`$recommended`,
	 * `CourseLib::getRecommendedForPublication()`) dans l'encadré en pointillé qui la suit, ou, quand
	 * rien ne bloque, entre le texte et les boutons de la confirmation (planche B du lot « publier »,
	 * P1). `DesignUi::getConfirmPanel()` enveloppe son texte dans un paragraphe, où l'encadré ne peut
	 * pas se loger : avec un encadré, la confirmation s'écrit ici, au même balisage.
	 */
	public function publish(Course $eCourse, \Collection $cChapter, array $propertiesMissing, \Collection $cLessonMissing, bool $programEmpty, array $recommended): \Panel {

		$subTitle = self::getPublicationSubTitle($eCourse, $cChapter);

		$button = '<a data-ajax="/learning/course:doUpdateStatus" post-id="'.$eCourse['id'].'" post-status="'.Course::PUBLISHED.'" class="btn btn-primary" data-waiter>'.\Asset::icon('unlock').' '.s("Publier").'</a>';

		$optional = self::getPublishRecommended($eCourse, $recommended);

		if($propertiesMissing === [] and $cLessonMissing->empty() and $programEmpty === FALSE) {

			if($optional === '') {
				return \main\DesignUi::getConfirmPanel('panel-learning-course-publish', s("Publier la formation ?"), $subTitle, self::getPublishText($eCourse), $button);
			}

			$h = '<p>'.self::getPublishText($eCourse).'</p>';
			$h .= $optional;
			$h .= '<div class="flex-buttons">';
				$h .= $button;
				$h .= '<a onclick="Lime.Panel.closeLast()" class="btn btn-outline-primary">'.s("Annuler").'</a>';
			$h .= '</div>';

			return new \Panel(
				id: 'panel-learning-course-publish',
				title: s("Publier la formation ?"),
				subTitle: $subTitle,
				body: $h
			);

		}

		$items = '';

		foreach(array_diff($propertiesMissing, ['price']) as $property) {
			$items .= '<li><a href="/learning/course:updateSettings?id='.$eCourse['id'].'">'.self::p($property)->label.'</a></li>';
		}

		foreach($cLessonMissing as $eLesson) {

			foreach($eLesson['missing'] as $missing) {

				$items .= match($missing) {
					'minutes' => '<li><a href="/learning/lesson:updateSettings?id='.$eLesson['id'].'">'.s("Durée de la leçon « {value} »", encode($eLesson['name'])).'</a></li>',
					'questions' => '<li><a href="'.LessonUi::urlEdition($eLesson).'?tab=quiz">'.s("Une question au quiz de la leçon « {value} »", encode($eLesson['name'])).'</a></li>',
				};

			}

		}

		// Sans module, il n'y a nulle part où poser une leçon : c'est un module qu'on va ajouter.
		if($programEmpty) {

			$url = $cChapter->empty()
				? '/learning/chapter:create?course='.$eCourse['id']
				: '/learning/lesson:create?chapter='.$cChapter->first()['id'];

			$items .= '<li><a href="'.$url.'">'.s("Une première leçon").'</a></li>';

		}

		if(in_array('price', $propertiesMissing)) {
			$items .= '<li><a href="/learning/course:updatePrice?id='.$eCourse['id'].'">'.s("Prix et financement").'</a></li>';
		}

		$body = \main\DesignUi::getAccentCard('todo', NULL, s("Pour publier"), '<ul>'.$items.'</ul>');
		$body .= $optional;

		return new \Panel(
			id: 'panel-learning-course-publish',
			title: s("Avant de publier"),
			subTitle: $subTitle,
			body: $body
		);

	}

	/** L'encadré « Recommandé » du panneau de publication, vide quand rien n'est recommandé. */
	protected static function getPublishRecommended(Course $eCourse, array $recommended): string {

		if($recommended === []) {
			return '';
		}

		$items = '';

		foreach($recommended as $section) {

			$items .= match($section) {
				'details' => '<li><a href="/learning/course:updateDetails?id='.$eCourse['id'].'">'.self::getDetailsWording()['title'].'</a></li>',
			};

		}

		$h = '<div class="util-block-optional">';
			$h .= '<div class="util-card-title"><span>'.s("Recommandé").'</span></div>';
			$h .= '<ul class="mb-0">'.$items.'</ul>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre de la copie part de celui de l'original, coupé à la longueur que le schéma accepte :
	 * un titre déjà long ne refuse pas la copie, il se reprend dans le champ.
	 */
	public function duplicate(Course $eCourse, Course $eCourseNew, \Collection $cChapter): \Panel {

		[, $max] = Course::model()->getPropertyRange('name');

		$eCourseNew['name'] = mb_substr(s("{value} (copie)", $eCourse['name']), 0, $max);

		$form = new \util\FormUi();

		if($eCourse->isTeam()) {
			$text = s("Une nouvelle formation en brouillon est créée, avec les modules, les leçons et les fichiers de celle-ci.");
		} else {
			$text = s("Une nouvelle formation en brouillon est créée dans le groupe, avec les modules, les leçons et les fichiers de celle-ci.");
		}

		$text .= ' '.($eCourse->isNetwork()
			? s("Elle ne suit pas les changements de l'original, et elle n'est pas encore ouverte aux membres.")
			: s("Elle ne suit pas les changements de l'original, et elle n'est partagée avec personne."));

		$h = '<p>'.$text.'</p>';

		$h .= $form->hidden('id', $eCourse);

		$h .= $form->dynamicGroups($eCourseNew, ['name*'], [
			'name*' => function(\PropertyDescriber $d) {
				$d->label = s("Titre de la copie");
			}
		]);

		$h .= $form->group(
			content: $form->submit(\Asset::icon('copy').' '.s("Dupliquer"), ['data-waiter' => s("Copie en cours...")])
		);

		return new \Panel(
			id: 'panel-learning-course-duplicate',
			title: s("Dupliquer la formation ?"),
			subTitle: self::getPublicationSubTitle($eCourse, $cChapter),
			dialogOpen: $form->openAjax('/learning/course:doDuplicate', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	/**
	 * Une formation de groupe ne s'ouvre à personne en paraissant : elle attend son partage, que la
	 * page compte (`shares`), avec pour un réseau ses membres hors de l'équipe (`members`), et pour
	 * un groupe de formation ses promotions (`sections`), sans lesquels l'ouverture ne s'offre pas.
	 * Republiée, elle garde ceux qu'elle avait. Une formation de réseau s'offre en paraissant à
	 * l'inscription depuis la page publique du réseau, et un réseau qui ne l'a pas encore ouverte à
	 * ses membres enchaîne sur ce panneau (`course:doUpdateStatus`). Une formation de l'équipe va au
	 * catalogue et ne lit aucun de ces comptes.
	 */
	protected static function getPublishText(Course $eCourse): string {

		if($eCourse->isTeam()) {
			return s("La formation paraît au catalogue. Vous pourrez la dépublier à tout moment.");
		}

		$eCourse->expects(['shares']);

		if($eCourse->isNetwork()) {

			$eCourse->expects(['members']);

			if($eCourse['shares'] > 0) {
				return s("La formation reste ouverte à tous les membres du réseau. Vous pourrez la dépublier à tout moment.");
			}

			if($eCourse['members'] === 0) {
				return s("Chacun pourra s'inscrire depuis la page publique du réseau. Vous pourrez la dépublier à tout moment.");
			}

			return s("Chacun pourra s'inscrire depuis la page publique du réseau. Vous choisirez ensuite si vous l'ouvrez à vos membres.");

		}

		if($eCourse['shares'] > 0) {
			return s("La formation reste partagée avec les promotions que vous aviez choisies. Vous pourrez la dépublier à tout moment.");
		}

		$eCourse->expects(['sections']);

		if($eCourse['sections'] === 0) {
			return s("Le groupe n'a pas encore de promotion. La formation pourra être partagée dès que vous en aurez créé une, et dépubliée à tout moment.");
		}

		return s("Les stagiaires la trouveront dès que vous l'aurez partagée avec leur promotion. Vous pourrez la dépublier à tout moment.");

	}

	public function unpublish(Course $eCourse, \Collection $cChapter): \Panel {

		if($eCourse->isTeam()) {
			$text = s("La formation sort du catalogue. Les inscriptions et la progression déjà enregistrées sont conservées, et vous pouvez la republier à tout moment.");
		} else if($eCourse->isNetwork()) {
			$text = s("La formation sort de la page publique et de l'onglet du réseau. Les inscriptions et la progression déjà enregistrées sont conservées, et vous pouvez la republier à tout moment.");
		} else {
			$text = s("La formation sort de la vue des lecteurs du groupe. Les inscriptions et la progression déjà enregistrées sont conservées, et vous pouvez la republier à tout moment.");
		}

		if($eCourse['readers'] > 0) {
			$text .= ' '.p("{value} lecteur l'a commencée.", "{value} lecteurs l'ont commencée.", $eCourse['readers']);
		}

		return \main\DesignUi::getConfirmPanel(
			'panel-learning-course-unpublish',
			s("Dépublier la formation ?"),
			self::getPublicationSubTitle($eCourse, $cChapter),
			$text,
			'<a data-ajax="/learning/course:doUpdateStatus" post-id="'.$eCourse['id'].'" post-status="'.Course::DRAFT.'" class="btn btn-primary" data-waiter>'.\Asset::icon('lock-fill').' '.s("Dépublier").'</a>'
		);

	}

	/**
	 * Le programme dans l'éditeur. Un module se déplace par la poignée de sa ligne de titre, une
	 * leçon par la sienne, sans quitter son module. Sans module, l'écran vide porte le geste plein
	 * « Ajouter un module », celui du titre « Leçons » restant en contour.
	 */
	protected function getEditionProgram(Course $eCourse, \Collection $cChapter): string {

		if($cChapter->empty()) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucun module pour le moment").'</h3>';
				$h .= '<p>'.s("Une formation se découpe en modules, et chaque module en leçons.").'</p>';
				$h .= '<div class="util-buttons">';
					$h .= '<a href="/learning/chapter:create?course='.$eCourse['id'].'" class="btn btn-primary">'.s("Ajouter un module").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			return $h;

		}

		$comments = ($eCourse['comments'] > 0);

		$h = '<div class="util-overflow">';
		$h .= '<table class="tr-even td-vertical-align-middle learning-table-program" '.attr('onrender', 'LearningSort.init(this)').' data-sort-numbering data-sort-url="/learning/course:doUpdatePositions" data-sort-handle=".tr-title .util-drag-handle" post-id="'.$eCourse['id'].'">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th class="td-min-content"></th>';
					$h .= '<th class="td-min-content"></th>';
					$h .= '<th>'.s("Leçon").'</th>';
					$h .= '<th class="hide-xs-down">'.s("Forme").'</th>';
					if($comments) {
						$h .= '<th class="text-end td-min-content hide-xs-down">'.s("Commentaires").'</th>';
					}
					$h .= '<th class="text-end td-min-content">'.s("Durée").'</th>';
					$h .= '<th class="td-min-content"></th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($cChapter as $eChapter) {

				$h .= '<tbody data-sort-id="'.$eChapter['id'].'" '.attr('onrender', 'LearningSort.init(this)').' data-sort-url="/learning/lesson:doUpdatePositions" data-sort-handle="tr[data-sort-id] .util-drag-handle" post-chapter="'.$eChapter['id'].'">';

					$h .= '<tr class="tr-title">';
						$h .= '<td class="td-min-content">'.\main\DesignUi::getDragHandle().'</td>';
						$h .= '<td class="td-min-content text-end learning-program-number"><span class="hide-xxs-down">'.s("Module").' </span><span data-number="chapter">'.$eChapter['number'].'</span></td>';
						$h .= '<td colspan="'.($comments ? 5 : 4).'">';
							$h .= '<div class="flex-justify-space-between flex-align-center">';
								$h .= '<div>';
									$h .= encode($eChapter['name']);
									$h .= '<div class="util-annotation">'.implode(' · ', array_filter([encode($eChapter['summary']), $eChapter['minutes'] === NULL ? NULL : self::getDuration($eChapter['minutes'])])).'</div>';
								$h .= '</div>';
								$h .= '<div class="learning-row-actions">';
									$h .= '<a href="/learning/lesson:create?chapter='.$eChapter['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Ajouter une leçon").'</span></a>';
									$h .= new ChapterUi()->getActions($eChapter);
								$h .= '</div>';
							$h .= '</div>';
						$h .= '</td>';
					$h .= '</tr>';

					foreach($eChapter['cLesson'] as $eLesson) {

						// L'ancre où « Enregistrer » ramène depuis l'écran de la leçon.
						$h .= '<tr id="lesson-'.$eLesson['id'].'" data-sort-id="'.$eLesson['id'].'">';
							$h .= '<td class="td-min-content">'.\main\DesignUi::getDragHandle().'</td>';
							$h .= '<td class="td-min-content text-end learning-program-number" data-number="lesson">'.$eLesson['number'].'</td>';
							// L'état et la taille de la leçon sous son nom, comme le résumé sous celui du module : la
							// colonne de la forme se masque sur téléphone, pas eux.
							$h .= '<td>';
								$h .= '<a href="'.LessonUi::urlEdition($eLesson).'">'.encode($eLesson['name']).'</a>';
								if($eLesson['format'] === Lesson::EMPTY) {
									$h .= '<div class="util-annotation">'.LessonUi::p('format')->values[Lesson::EMPTY].'</div>';
								} else if($eLesson['steps'] > 0) {
									$h .= '<div class="util-annotation">'.LessonUi::getSteps($eLesson['steps']).'</div>';
								}
							$h .= '</td>';
							$h .= '<td class="hide-xs-down">';
								if($eLesson['format'] !== Lesson::EMPTY) {
									$h .= LessonUi::getFormat($eLesson['format']);
								}
							$h .= '</td>';
							if($comments) {
								$h .= '<td class="text-end td-min-content hide-xs-down">';
									if($eLesson['comments'] > 0) {
										$h .= '<a href="'.LessonUi::url($eLesson).'#comments" class="util-counter">'.$eLesson['comments'].'</a>';
									}
								$h .= '</td>';
							}
							// Une leçon vide ne compte pas dans la durée du module : elle tait la sienne.
							$h .= '<td class="text-end td-min-content">';
								if($eLesson['written']) {
									$h .= self::getDuration($eLesson['minutes']);
								}
							$h .= '</td>';
							$h .= '<td class="td-min-content">'.new LessonUi()->getActions($eLesson).'</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			}

		$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Course::model()->describer($property, [
			'name' => s("Titre"),
			'summary' => s("Présentation"),
			'theme' => s("Thématique"),
			'subtheme' => s("Sous-thématique"),
			'level' => s("Niveau"),
			'vignette' => s("Visuel"),
			'type' => s("Type"),
			'status' => s("État"),
			'open' => s("Lecture sans compte"),
			'offer' => s("Mode de diffusion"),
			'paid' => s("Formation payante"),
			'priceFunded' => s("Public financé"),
			'price' => s("Autre public"),
			'funding' => s("Financements possibles"),
			'fundingNote' => s("Conditions"),
			'externalOnly' => s("Envoyer les nouvelles inscriptions sur le site de l'organisme"),
			'externalUrl' => s("Page de la formation"),
			'description' => s("Enjeux"),
			'objectives' => s("Objectifs"),
			'audience' => s("Public visé"),
			'prerequisites' => s("Prérequis"),
			'access' => s("Modalités et délais d'accès"),
			'accessibility' => s("Accessibilité"),
			'recognition' => s("Reconnaissance"),
			'catalogReason' => s("Motif"),
		]);

		switch($property) {

			case 'name' :
				$d->placeholder = s("Ex. : Réussir sa certification bio");
				break;

			case 'summary' :
			case 'audience' :
			case 'prerequisites' :
			case 'accessibility' :
				$d->field = 'textarea';
				break;

			case 'recognition' :
				$d->placeholder = s("Ex. : attestation de suivi, sans certification");
				break;

			case 'theme' :
				$d->field = 'select';
				$d->values = fn(Course $e) => $e['cTheme'] ?? $e->expects(['cTheme']);
				$d->attributes['onchange'] = 'LearningCourse.changeTheme(this)';
				break;

			case 'subtheme' :
				$d->field = 'select';
				$d->values = fn(Course $e) => $e['cSubtheme'] ?? $e->expects(['cSubtheme']);
				break;

			case 'level' :
				$d->field = 'select';
				$d->values = fn(Course $e) => $e['cLevel'] ?? $e->expects(['cLevel']);
				break;

			case 'type' :
				$d->field = 'radio';
				$d->values = [
					Course::ONSITE => s("Présentiel"),
					Course::REMOTE => s("Visio"),
					Course::MIXED => s("Présentiel et visio"),
					Course::ONLINE => s("En ligne"),
				];
				$d->required = TRUE;
				break;

			case 'status' :
				$d->field = 'radio';
				$d->values = [
					Course::DRAFT => s("Brouillon"),
					Course::PUBLISHED => s("Publiée"),
				];
				$d->required = TRUE;
				break;

			case 'open' :
				$d->field = 'radio';
				$d->values = [
					TRUE => s("Oui, la formation se lit sans être connecté"),
					FALSE => s("Non, réservé aux utilisateurs connectés"),
				];
				$d->required = TRUE;
				break;

			case 'externalOnly' :
				$d->field = 'yesNo';
				$d->values = [
					FALSE => s("Sur Ouvretaferme"),
					TRUE => s("Sur le site de l'organisme"),
				];
				break;

			case 'externalUrl' :
				$d->placeholder = 'https://';
				break;

			case 'catalogReason' :
				$d->field = 'textarea';
				$d->after = \util\FormUi::info(s("Le réseau le lit dans l'onglet Diffusion de sa formation."));
				break;

			case 'offer' :
				$d->values = self::getOfferValues();
				$d->labelAfter = \util\FormUi::info(self::getOfferIntro());
				$d->field = fn(\util\FormUi $form, Course $e) => CourseUi::getOfferField($form, $e['offer'], $e, in_array($e['catalog'], [Course::PROPOSED, Course::ACCEPTED], TRUE), $e);
				break;

			case 'paid' :
				$d->field = 'yesNo';
				break;

			case 'priceFunded' :
				$d->append = s("€");
				$d->labelAfter = \util\FormUi::info(s("Ce que paie une personne dont la formation est prise en charge."));
				break;

			case 'price' :
				$d->append = s("€");
				$d->labelAfter = \util\FormUi::info(s("Ce que paie une personne sans prise en charge, et ce que le paiement en ligne encaisse."));
				break;

			case 'funding' :
				$d->values = self::getFundingValues();
				$d->field = fn(\util\FormUi $form, Course $e) => $form->checkboxes('funding[]', $d->values, $e['funding'] ?? [], ['all' => FALSE, 'columns' => 2]);
				break;

			case 'fundingNote' :
				$d->placeholder = s("Ex. : prise en charge totale pour les contributeurs VIVEA");
				break;

		}

		return $d;

	}

}
?>
