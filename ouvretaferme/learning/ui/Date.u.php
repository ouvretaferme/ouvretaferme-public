<?php
namespace learning;

/**
 * Les dates d'une formation de réseau : la section de l'éditeur, celle de la fiche, la bande des
 * cartes et les panneaux qui les écrivent.
 */
class DateUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * La section « Dates » de l'éditeur, au-dessus des leçons. Le compteur compte ce que la
	 * section liste, les dates à venir annulées comprises, là où la bande des cartes et
	 * « N dates à venir » ne comptent que les planifiées (`DateLib::applyUpcoming()`) : les deux
	 * nombres diffèrent à dessein, ne pas les aligner. Il ne paraît pas à zéro, l'état vide le dit
	 * déjà. Les dates passées suivent, sous leur titre.
	 */
	public function getEditionSection(Course $eCourse, \Collection $cDate): string {

		$cDateUpcoming = $cDate->find(fn(Date $eDate) => $eDate->isPast() === FALSE, clone: FALSE);
		$cDatePast = $cDate->find(fn(Date $eDate) => $eDate->isPast(), clone: FALSE);

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Dates").($cDateUpcoming->empty() ? '' : ' <span class="util-counter">'.$cDateUpcoming->count().'</span>').'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/date:create?course='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une date").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		if($cDateUpcoming->empty()) {

			$h .= '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune date à venir").'</h3>';
			$h .= '</div>';

		} else {

			foreach($cDateUpcoming as $eDate) {
				$h .= $this->getDeck($eDate, withGear: TRUE);
			}

		}

		if($cDatePast->notEmpty()) {

			$h .= '<h4 class="mt-2">'.s("Dates passées").'</h4>';

			foreach($cDatePast as $eDate) {
				$h .= $this->getDeck($eDate, withGear: TRUE);
			}

		}

		return $h;

	}

	/**
	 * Les dates à venir sur la fiche, annulées comprises : un lecteur qui comptait sur l'une d'elles
	 * voit qu'elle ne se tient plus. Rien sans date, la fiche n'annonce pas une section vide.
	 */
	public function getFicheSection(\Collection $cDate): string {

		if($cDate->empty()) {
			return '';
		}

		$h = '<div class="util-title mt-2">';
			$h .= '<h2>'.s("Dates").'</h2>';
		$h .= '</div>';

		foreach($cDate as $eDate) {
			$h .= $this->getDeck($eDate, withGear: FALSE);
		}

		return $h;

	}

	/**
	 * Une date sous forme de pile. Sur la fiche, la pile porte les places qui restent et, à droite,
	 * l'inscription de la personne (`DateEnrollmentUi::getFicheAction()`), en porte : sous 576 px
	 * elle passe sous la date, qu'un badge long écrasait à côté d'elle ; dans l'éditeur, les
	 * inscrits par état, la roue, et la porte vers la liste des inscrits dès qu'il y en a un, annulé
	 * compris. Les comptes sont posés par `DateEnrollmentLib::fillCounts()`. Une date annulée ne
	 * compte plus rien : elle garde son bandeau et sa roue.
	 *
	 * Dans l'éditeur, une visio à venir dont le réseau a un geste à faire avant la séance prend la
	 * teinte d'alerte, une bande d'état qui nomme le geste et la porte qui y mène : le lien à poser
	 * (`Date::isLinkMissing()`), puis le lien à envoyer aux personnes confirmées
	 * (`Date::acceptSendLink()`). Les deux s'excluent. Dès qu'un lien est posé (`Date::isLinkPosed()`),
	 * une bande le montre à l'équipe, en lien vers la visio, sous la teinte de ce qui est à venir
	 * (retour de Vincent du 02/10). La teinte se pose sur la pile ; quand la pile porte déjà l'alerte
	 * du lien à envoyer, elle se pose sur la bande même, comme sur une `util-deck-row-state`. Un lien
	 * envoyé dit son jour dans la ligne de la date (`getLinkState()`).
	 */
	protected function getDeck(Date $eDate, bool $withGear): string {

		// Le lien de la visio ne se montre qu'à qui a une place confirmée : avant, la personne n'a
		// accepté ni les engagements ni les conditions du réseau.
		$withLink = (
			$withGear === FALSE and
			$eDate['eDateEnrollment']['status'] === DateEnrollment::CONFIRMED
		);

		$linkMissing = (
			$withGear and
			$eDate->isLinkMissing()
		);

		$linkToSend = (
			$withGear and
			$eDate->acceptSendLink()
		);

		$linkPosed = (
			$withGear and
			$eDate->isLinkPosed()
		);

		if($linkMissing or $linkToSend) {
			$accent = ' util-deck-accent-warning';
		} else if($linkPosed) {
			$accent = ' util-deck-accent-info';
		} else {
			$accent = '';
		}

		$h = '<div class="util-deck'.$accent.' mb-1" id="learning-date-deck-'.$eDate['id'].'">';

			$h .= '<div class="util-deck-head">';

				// Sur la fiche, la pause de la formation se lit dans sa tête (`CourseUi::getFicheAccess()`) :
				// une date ne dit que la sienne, et pas à qui y a déjà sa ligne.
				$withPause = (
					$withGear === FALSE and
					$eDate['enrollmentPaused'] and
					$eDate['course']->isEnrollmentPaused() === FALSE and
					$eDate['eDateEnrollment']->isOpen() === FALSE
				);

				$h .= $this->getDeckMain($eDate, $withGear, $withLink, withExternal: ($withGear === FALSE), withPause: $withPause);

				if($withGear) {
					$h .= '<div class="util-deck-aside">'.$this->getGear($eDate).'</div>';
				} else if($eDate['status'] === Date::PLANNED) {

					$action = new DateEnrollmentUi()->getFicheAction($eDate);

					if($action !== '') {
						$h .= '<div class="util-deck-doors">'.$action.'</div>';
					}

				}

			$h .= '</div>';

			if($eDate['status'] === Date::CANCELED) {
				$h .= '<div class="util-deck-band util-deck-band-state">'.s("Annulée").'</div>';
			}

			if($linkMissing) {
				$h .= '<div class="util-deck-band util-deck-band-state">'.s("Lien de la visio à poser").'</div>';
			}

			if($linkToSend) {
				$h .= '<div class="util-deck-band util-deck-band-state">'.s("Lien de la visio à envoyer").'</div>';
			}

			if($linkPosed) {
				$h .= '<div class="util-deck-band util-deck-band-state'.($linkToSend ? ' util-deck-accent-info' : '').'">';
					$h .= '<a href="'.encode($eDate['link']).'" target="_blank" class="learning-date-link">'.encode($eDate['link']).'</a>';
				$h .= '</div>';
			}

			// L'état d'abord, puis le réglage, puis les portes, dans l'ordre du socle.
			if($withGear) {
				$h .= self::getEnrollmentBand($eDate);
			}

			$doors = [];

			if(
				$withGear and
				self::countEnrolled($eDate) > 0
			) {
				$doors[] = '<a href="'.DateEnrollmentUi::urlList($eDate).'" class="btn btn-light">'.\Asset::icon('people').' '.s("Inscrits").'</a>';
			}

			if($linkMissing) {
				$doors[] = '<a href="/learning/date:update?id='.$eDate['id'].'" class="btn btn-light">'.\Asset::icon('link-45deg').' '.s("Poser le lien de la visio").'</a>';
			}

			if($linkToSend) {
				$doors[] = '<a href="/learning/date:sendLink?id='.$eDate['id'].'" class="btn btn-light">'.\Asset::icon('envelope').' '.s("Envoyer le lien de la visio").'</a>';
			}

			if($doors !== []) {
				$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-'.count($doors).'">'.implode('', $doors).'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La date en tête du panneau d'inscription : la pile de la fiche sans le geste de droite, que
	 * le panneau porte lui-même. Elle dit la pause, que l'étape 1 de la page publique montre sans
	 * aucun geste ; le panneau du membre, lui, ne s'ouvre pas pendant la pause.
	 */
	public function getEnrollmentDeck(Date $eDate): string {

		$h = '<div class="util-deck mb-1">';
			$h .= '<div class="util-deck-head">';
				$h .= $this->getDeckMain($eDate, withGear: FALSE, withLink: FALSE, withPause: TRUE);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le calendrier, le jour, l'horaire et le lieu, et les comptes de la date quand elle se tient. Sur
	 * la fiche, l'adresse de l'organisme en dernière ligne (`getExternalLine()`), et la pause des
	 * inscriptions là où rien d'autre ne la dit (`$withPause`).
	 */
	protected function getDeckMain(Date $eDate, bool $withGear, bool $withLink, bool $withExternal = FALSE, bool $withPause = FALSE): string {

		$h = \util\DateUi::calendar($eDate['startAt']);

		$h .= '<div class="util-deck-body">';

			$h .= '<div class="util-deck-title">'.self::getDays($eDate).'</div>';
			$line = self::getLine($eDate, $withLink);

			if($withGear) {

				$linkState = self::getLinkState($eDate);

				if($linkState !== NULL) {
					$line .= ' · '.$linkState;
				}

			}

			$h .= '<p>'.$line.'</p>';

			if($eDate['status'] === Date::PLANNED) {

				$h .= $withGear ? $this->getEditionFigures($eDate) : $this->getFicheFigures($eDate, $withPause);

				if($withExternal) {
					$h .= self::getExternalLine($eDate);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une date dans l'onglet Diffusion de sa formation (lot L15a) : le calendrier,
	 * le jour, l'horaire et le lieu, puis le mode de diffusion, le montant que le paiement en ligne
	 * encaisse quand c'est le sien, et la porte qui change le mode de diffusion.
	 */
	public function getOfferDeck(Date $eDate): string {

		$h = '<div class="util-deck mb-1">';
			$h .= '<div class="util-deck-head">';

				$h .= \util\DateUi::calendar($eDate['startAt']);

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.self::getDays($eDate).'</div>';
					$h .= '<p>'.self::getLine($eDate, withLink: FALSE).'</p>';

					$h .= '<div class="util-deck-figures">';
						$h .= \main\DesignUi::getDeckFigure(s("Mode de diffusion"), CourseUi::getOfferValues()[$eDate['offer']]);

						if(
							$eDate['offer'] === Date::TRAINEE and
							$eDate->getPrice() !== NULL
						) {
							$h .= \main\DesignUi::getDeckFigure(s("Montant payé en ligne"), CourseUi::getAmount($eDate->getPrice()));
						}

					$h .= '</div>';

				$h .= '</div>';

				$h .= '<div class="util-deck-doors">';
					$h .= '<a href="/learning/date:updateOffer?id='.$eDate['id'].'" class="btn btn-outline-primary">'.\Asset::icon('pencil').' '.s("Modifier le mode de diffusion").'</a>';
				$h .= '</div>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Inscription ouverte, l'adresse de l'organisme se lit quand même, en lien discret sous la date
	 * (lot annuaire) : la personne y trouve ce que la fiche ne dit pas. Fermée, le lien sortant prend
	 * la place de « S'inscrire », et la ligne ferait doublon.
	 */
	public static function getExternalLine(Date $eDate): string {

		$url = $eDate->getExternalUrl();

		if(
			$url === NULL or
			$eDate['course']->isExternal()
		) {
			return '';
		}

		return '<p><a href="'.encode($url).'" target="_blank">'.s("Voir sur le site de l'organisme").'</a></p>';

	}

	protected function getFicheFigures(Date $eDate, bool $withPause): string {
		return self::getPublicFigures($eDate, withConditions: TRUE, withPause: $withPause);
	}

	/**
	 * Ce que le stagiaire lit d'une date, sur la fiche, sur la page publique du réseau et à l'étape 1
	 * de l'inscription : les places quand elles sont comptées, les montants et les financeurs, ceux
	 * de la date ou à défaut de sa formation (`Date::getPrice()`). Les conditions de financement ne
	 * se lisent que sur la fiche, sous les chiffres (`$withConditions`).
	 *
	 * Inscription sur le site de l'organisme (lot annuaire), pas de places : l'organisme les tient
	 * chez lui. Le prix reste, c'est ce que le visiteur compare avant de partir chez l'organisme.
	 *
	 * Inscriptions en pause (`$withPause`), un dernier chiffre le dit à la place du geste, que la date
	 * n'a plus. L'appelant le demande là où rien d'autre ne le dit, jamais à qui a déjà sa ligne.
	 */
	public static function getPublicFigures(Date $eDate, bool $withConditions = FALSE, bool $withPause = FALSE): string {

		$figures = '';

		if(
			$eDate['course']->isExternal() === FALSE and
			$eDate['seats'] !== NULL
		) {
			$figures .= \main\DesignUi::getDeckFigure(s("Places"), (string)$eDate['seats']);
			$figures .= \main\DesignUi::getDeckFigure(s("Restantes"), (string)$eDate['remaining']);
		}

		$figures .= CourseUi::getPriceFigures($eDate);

		if(
			$withPause and
			$eDate->isEnrollmentPaused()
		) {
			$figures .= CourseUi::getPausedFigure();
		}

		$h = ($figures === '') ? '' : '<div class="util-deck-figures">'.$figures.'</div>';

		if(
			$withConditions and
			$eDate->getFundingNote() !== NULL
		) {
			$h .= '<p class="util-annotation">'.encode($eDate->getFundingNote()).'</p>';
		}

		return $h;

	}

	/**
	 * Les confirmées toujours, les demandes à confirmer et l'attente seulement quand il y en a :
	 * ce sont les deux états qui appellent un geste du réseau.
	 */
	protected function getEditionFigures(Date $eDate): string {

		$h = '<div class="util-deck-figures">';

			if($eDate['seats'] !== NULL) {
				$h .= \main\DesignUi::getDeckFigure(s("Places"), (string)$eDate['seats']);
			}

			$h .= \main\DesignUi::getDeckFigure(s("Confirmées"), (string)$eDate['confirmed']);

			if($eDate['requested'] > 0) {
				$h .= \main\DesignUi::getDeckFigure(s("À confirmer"), (string)$eDate['requested']);
			}

			if($eDate['waiting'] > 0) {
				$h .= \main\DesignUi::getDeckFigure(s("En attente"), (string)$eDate['waiting']);
			}

			foreach(self::getEditionPriceRows($eDate) as $label => $value) {
				$h .= \main\DesignUi::getDeckFigure($label, $value);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le prix d'une date dans l'éditeur : la valeur qu'elle reprend de sa formation, ou la sienne
	 * précédée, barrée, de celle de la formation qu'elle remplace quand les deux diffèrent. Le réseau
	 * voit d'un coup d'œil quelles dates s'écartent de la formation.
	 *
	 * @return array<string, string>
	 */
	protected static function getEditionPriceRows(Date $eDate): array {

		$eCourse = $eDate['course'];

		$value = function(mixed $own, mixed $inherited, \Closure $format): ?string {

			if($own === NULL) {
				return ($inherited === NULL) ? NULL : $format($inherited);
			}

			// Comparé une fois mis en forme : un montant lu en base et un montant posté, comme deux
			// `\Set` de mêmes financeurs, ne sont pas égaux en PHP alors que l'écran les dit pareils.
			// Une valeur égale n'est pas un écart, elle s'écrit seule.
			if($inherited === NULL or $format($own) === $format($inherited)) {
				return $format($own);
			}

			return '<s class="color-muted">'.$format($inherited).'</s> '.$format($own);

		};

		$amount = fn(float $amount) => CourseUi::getAmount($amount);
		$funding = fn(\Set $funding) => implode(', ', CourseUi::getFundingLabels($funding));

		$rows = [
			s("Public financé") => $value($eDate['priceFunded'], $eCourse['priceFunded'], $amount),
			s("Autre public") => $value($eDate['price'], $eCourse['price'], $amount),
			s("Finançable par") => $value($eDate['funding'], $eCourse['funding'], $funding),
		];

		return array_filter($rows, fn(?string $row) => $row !== NULL);

	}

	/** Toutes les lignes de la date, annulées comprises : ce qui interdit de la supprimer. */
	protected static function countEnrolled(Date $eDate): int {
		return $eDate['requested'] + $eDate['confirmed'] + $eDate['waiting'] + $eDate['canceled'];
	}

	/** Les lignes encore ouvertes, que l'annulation de la date annule et prévient. */
	protected static function countOpen(Date $eDate): int {
		return $eDate['requested'] + $eDate['confirmed'] + $eDate['waiting'];
	}

	/**
	 * Une date de l'éditeur sous forme de pile, que la bascule de sa formation redessine entière :
	 * la bande de son interrupteur y paraît ou s'en retire.
	 */
	public function getEditionDeck(Date $eDate): string {
		return $this->getDeck($eDate, withGear: TRUE);
	}

	/**
	 * Mettre en pause les nouvelles inscriptions de la date seule, puis les rouvrir, dans une bande
	 * sous la tête : l'interrupteur porte son propre libellé, et le socle le pose alors dans une bande,
	 * la roue seule restant à droite de la tête. Hors des dates d'inscription, ou quand la formation
	 * entière est en pause, la bande se retire.
	 */
	public static function getEnrollmentBand(Date $eDate): string {

		if($eDate->assertUpdateEnrollmentPaused() === FALSE) {
			return '';
		}

		$paused = $eDate->isEnrollmentPaused();

		return '<div id="learning-date-pause-'.$eDate['id'].'" class="util-deck-band">'.\util\TextUi::switch([
			'data-ajax' => '/learning/date:doUpdateEnrollmentPaused',
			'post-id' => $eDate['id'],
			'post-enrollment-paused' => $paused ? 0 : 1
		], $paused === FALSE, s("Inscriptions ouvertes"), s("Inscriptions en pause")).'</div>';

	}

	/**
	 * Les gestes sur une date. Une date annulée ou passée garde sa roue : modifier et annuler y
	 * restent, refusés avec leur raison. Supprimer reste possible tant que la date n'a eu aucun
	 * inscrit ; la liste des inscrits ne s'ouvre pas d'ici mais par la porte de la pile.
	 */
	protected function getGear(Date $eDate): string {

		if($eDate->assertUpdate()) {

			$update = '<a href="/learning/date:update?id='.$eDate['id'].'" class="dropdown-item">'.s("Modifier la date").'</a>';
			$cancel = '<a href="/learning/date:cancel?id='.$eDate['id'].'" class="dropdown-item">'.s("Annuler la date").'</a>';

		} else {

			$reason = match($eDate['status']) {
				Date::CANCELED => s("La date est annulée"),
				Date::PLANNED => s("La date est passée")
			};

			$update = '<a class="dropdown-item disabled">';
				$update .= '<div class="util-item-label">'.s("Modifier la date").'</div>';
				$update .= '<div class="util-item-more">'.$reason.'</div>';
			$update .= '</a>';
			$cancel = '<a class="dropdown-item disabled">';
				$cancel .= '<div class="util-item-label">'.s("Annuler la date").'</div>';
				$cancel .= '<div class="util-item-more">'.$reason.'</div>';
			$cancel .= '</a>';

		}

		$items = $update;

		$items .= '<div class="dropdown-divider"></div>';
		$items .= '<div class="dropdown-subtitle">'.\Asset::icon('exclamation-circle').'  '.s("Zone de danger").'  '.\Asset::icon('exclamation-circle').'</div>';
		$items .= $cancel;

		// La même règle que `DateLib::delete()`, qui refuse une date qui a eu des inscrits.
		$enrolled = self::countEnrolled($eDate);

		if($enrolled > 0) {

			$items .= '<a class="dropdown-item disabled">';
				$items .= '<div class="util-item-label">'.s("Supprimer la date").'</div>';
				$items .= '<div class="util-item-more">'.($eDate['canceled'] > 0
					? p("{value} inscription, annulées comprises", "{value} inscriptions, annulées comprises", $enrolled)
					: p("{value} inscription sur cette date", "{value} inscriptions sur cette date", $enrolled)
				).'</div>';
			$items .= '</a>';

		} else {
			$items .= '<a data-ajax="/learning/date:doDelete" post-id="'.$eDate['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette date de la formation ?").'" data-waiter>'.s("Supprimer la date").'</a>';
		}

		return \main\DesignUi::getGear($items, self::getDays($eDate));

	}

	/**
	 * La prochaine date planifiée sous le titre de la carte d'une formation de réseau, que la
	 * pastille de tête annonce, et le compte des suivantes à la suite. `dates` et `dateNext` sont
	 * posées par `DateLib::fillUpcoming()`, `dateNext` vide quand aucune date n'est à venir.
	 */
	public static function getCardWhen(Course $eCourse): string {

		if($eCourse['dateNext'] === NULL) {
			return '';
		}

		$h = '<p class="learning-card-when">';
			$h .= self::getDayTime($eCourse['dateNext']);

			if($eCourse['dates'] > 1) {
				$h .= ' <span class="learning-card-more">'.p("et {value} autre date", "et {value} autres dates", $eCourse['dates'] - 1).'</span>';
			}

		$h .= '</p>';

		return $h;

	}

	/**
	 * « Jeudi 15 octobre 2026 », ou « Du mardi 17 au mercredi 18 novembre 2026 » sur deux jours.
	 * Sans l'année (`$withYear`) dans l'agenda du catalogue, dont le titre du mois la porte, sauf
	 * pour une date à cheval sur deux années, que ce titre ne dirait qu'à moitié.
	 */
	public static function getDays(Date $eDate, bool $withYear = TRUE): string {

		$start = substr($eDate['startAt'], 0, 10);
		$end = substr($eDate['endAt'], 0, 10);

		$sameMonth = (substr($start, 0, 7) === substr($end, 0, 7));
		$sameYear = (substr($start, 0, 4) === substr($end, 0, 4));

		$format = ($withYear or $sameYear === FALSE) ? \util\DateUi::DATE : \util\DateUi::DAY_MONTH;

		if($start === $end) {
			return self::getDayName($start).' '.\util\DateUi::textual($start, $format);
		}

		if($sameMonth) {
			$from = (int)substr($start, 8, 2);
		} else if($sameYear) {
			$from = \util\DateUi::textual($start, \util\DateUi::DAY_MONTH);
		} else {
			$from = \util\DateUi::textual($start, \util\DateUi::DATE);
		}

		$from = mb_strtolower(self::getDayName($start)).' '.$from;

		return s("Du {from} au {to}", [
			'from' => $from,
			'to' => mb_strtolower(self::getDayName($end)).' '.\util\DateUi::textual($end, $format)
		]);

	}

	/**
	 * Où se tient la date, d'un mot, pour les listes qui n'ont qu'une ligne à lui donner : son lieu
	 * quand toutes ses séances sont en présentiel au même endroit, « En visio », ou « Présentiel et
	 * visio ». `NULL` pour des séances en présentiel en plusieurs lieux, que seule la pile détaille.
	 */
	public static function getWhere(Date $eDate): ?string {

		$modes = $eDate->getModes();

		if($modes === [DateSequence::REMOTE]) {
			return s("En visio");
		}

		if(count($modes) > 1) {
			return s("Présentiel et visio");
		}

		$place = $eDate->getPlace();

		return ($place === NULL) ? NULL : encode($place);

	}

	/**
	 * Où se tient une séance : son lieu, ou « En visio ». `NULL` pour une séance en présentiel
	 * sans lieu, que la reprise tire d'une date écrite avant que le lieu soit exigé : la ligne
	 * garde alors ses seules heures, comme la date le faisait.
	 */
	public static function getSequenceWhere(DateSequence $eDateSequence): ?string {

		return match($eDateSequence['mode']) {
			DateSequence::ONSITE => ($eDateSequence['place'] === NULL) ? NULL : encode($eDateSequence['place']),
			DateSequence::REMOTE => s("En visio"),
		};

	}

	/**
	 * Les séances d'une date, sauf quand toutes se tiennent aux mêmes heures et au même endroit :
	 * la première suffit alors à les dire toutes, les jours étant portés par le titre.
	 *
	 * @return DateSequence[]
	 */
	public static function getDistinctSequences(Date $eDate): array {

		$eDate->expects(['cDateSequence']);

		$sequences = $eDate['cDateSequence']->makeArray(fn(DateSequence $eDateSequence) => $eDateSequence);

		$keys = [];

		foreach($sequences as $eDateSequence) {
			$keys[substr($eDateSequence['startAt'], 11).substr($eDateSequence['endAt'], 11).$eDateSequence['mode'].$eDateSequence['place']] = TRUE;
		}

		return (count($keys) === 1) ? [reset($sequences)] : $sequences;

	}

	/**
	 * « 14:00 à 16:30 · En visio », ou le lieu, une ligne par séance (lot date-mixte), le jour devant
	 * quand la date en couvre plusieurs ; des séances identiques n'en font qu'une. Une séance en
	 * visio à venir porte son lien quand l'appelant le demande et que le réseau l'a envoyé
	 * (`Date::isLinkMailed()`), ou dit, une seule fois, qu'il sera envoyé tant qu'il ne l'a pas fait,
	 * même posé ; une visio annulée ne mène nulle part.
	 */
	public static function getLine(Date $eDate, bool $withLink): string {

		$sequences = self::getDistinctSequences($eDate);

		$withDay = (
			count($sequences) > 1 and
			$eDate->countDays() > 1
		);

		$linkMailed = (
			$withLink and
			$eDate->isLinkMailed() and
			$eDate['status'] === Date::PLANNED
		);

		$linkAwaited = (
			$withLink and
			$eDate->isLinkAwaited()
		);

		$lines = [];

		foreach($sequences as $eDateSequence) {

			$where = self::getSequenceWhere($eDateSequence);

			if($eDateSequence['mode'] === DateSequence::REMOTE) {

				if($linkMailed) {
					$where = '<a href="'.encode($eDate['link']).'" target="_blank">'.$where.' '.\Asset::icon('box-arrow-up-right').'</a>';
				} else if($linkAwaited) {
					$where = s("En visio, le lien vous sera envoyé par e-mail avant la séance");
					$linkAwaited = FALSE;
				}

			}

			$line = ($where === NULL) ? self::getHours($eDateSequence) : self::getHours($eDateSequence).' · '.$where;

			if($withDay) {
				$line = self::getShortDay($eDateSequence->getDay()).' · '.$line;
			}

			$lines[] = $line;

		}

		return implode('<br/>', $lines);

	}

	/**
	 * Quand le lien d'une visio planifiée à venir est parti, pour le réseau, à la suite de la ligne de
	 * la date dans l'éditeur : « Lien envoyé le 14 octobre ». Rien tant qu'il n'est pas envoyé, la
	 * carte le disant par ses bandes (à poser, à envoyer, le lien lui-même), ni sur une date finie ou
	 * annulée, qui n'attend plus rien.
	 */
	public static function getLinkState(Date $eDate): ?string {

		if(
			$eDate->hasMode(DateSequence::REMOTE) === FALSE or
			$eDate['status'] !== Date::PLANNED or
			$eDate->isPast()
		) {
			return NULL;
		}

		if($eDate->isLinkMailed()) {
			return s("Lien envoyé le {value}", \util\DateUi::textual($eDate['linkMailedAt'], \util\DateUi::DAY_MONTH));
		}

		return NULL;

	}

	/**
	 * La phrase sous l'engagement de présence, qui dit ce à quoi la personne s'engage :
	 * « Samedi 7 et dimanche 8 novembre, de 09:30 à 17:00, Salle des fêtes, Crest. », sans le lieu
	 * en visio, que la case nomme déjà. Sans l'année, que la carte de l'étape 1 porte. Des séances
	 * qui diffèrent par leurs heures ou leur lieu se disent une phrase chacune, la visio nommée.
	 */
	public static function getCommitmentLine(Date $eDate): string {

		$sequences = self::getDistinctSequences($eDate);

		if(count($sequences) > 1) {

			$lines = [];

			foreach($sequences as $eDateSequence) {

				$where = self::getCommitmentWhere($eDateSequence);

				$lines[] = self::getDayName($eDateSequence->getDay()).' '.\util\DateUi::textual($eDateSequence->getDay(), \util\DateUi::DAY_MONTH).', '.self::getCommitmentHours($eDateSequence).(($where === NULL) ? '' : ', '.$where).'.';

			}

			return implode('<br/>', $lines);

		}

		$eDateSequence = $sequences[0];

		$days = $eDate->getDays();

		$start = $days[0];
		$end = $days[count($days) - 1];

		$startDay = self::getDayName($start).' '.\util\DateUi::textual($start, \util\DateUi::DAY_MONTH);

		if($start === $end) {
			$days = $startDay;
		} else {

			// Le mois ne se dit qu'une fois quand les deux jours le partagent : « Samedi 7 et dimanche 8 novembre ».
			$startNumber = (int)substr($start, 8, 2);
			$from = (substr($start, 0, 7) === substr($end, 0, 7))
				? self::getDayName($start).' '.(($startNumber === 1) ? s("1er") : $startNumber)
				: $startDay;
			$to = mb_strtolower(self::getDayName($end)).' '.\util\DateUi::textual($end, \util\DateUi::DAY_MONTH);

			$days = match(count($days)) {
				2 => s("{from} et {to}", ['from' => $from, 'to' => $to]),
				default => s("Du {from} au {to}", ['from' => mb_strtolower($from), 'to' => $to]),
			};

		}

		$hours = self::getCommitmentHours($eDateSequence);

		// Une date qui se tient tout entière en visio ne le redit pas : la phrase s'arrête aux heures.
		$where = ($eDateSequence['mode'] === DateSequence::ONSITE) ? self::getCommitmentWhere($eDateSequence) : NULL;

		return $days.', '.$hours.(($where === NULL) ? '' : ', '.$where).'.';

	}

	/**
	 * Où se tient une séance, dans le cours d'une phrase : son lieu, ou « en visio ». `NULL` pour une
	 * séance en présentiel sans lieu (cf. `getSequenceWhere()`), dont la phrase s'arrête aux heures.
	 */
	public static function getCommitmentWhere(DateSequence $eDateSequence): ?string {

		return match($eDateSequence['mode']) {
			DateSequence::ONSITE => ($eDateSequence['place'] === NULL) ? NULL : encode($eDateSequence['place']),
			DateSequence::REMOTE => s("en visio"),
		};

	}

	/** « de 09:30 à 17:00 » */
	protected static function getCommitmentHours(DateSequence $eDateSequence): string {

		return s("de {start} à {end}", [
			'start' => \util\DateUi::textual($eDateSequence['startAt'], \util\DateUi::TIME_HOUR_MINUTE),
			'end' => \util\DateUi::textual($eDateSequence['endAt'], \util\DateUi::TIME_HOUR_MINUTE)
		]);

	}

	/** « Jeudi 15 octobre à 14:00 », sous le titre des cartes. */
	protected static function getDayTime(string $datetime): string {

		return s("{day} à {time}", [
			'day' => self::getDayName($datetime).' '.\util\DateUi::textual($datetime, \util\DateUi::DAY_MONTH),
			'time' => \util\DateUi::textual($datetime, \util\DateUi::TIME_HOUR_MINUTE)
		]);

	}

	/**
	 * « Lundi 9 », le jour d'une séance devant sa ligne, le mois étant dans le titre de la date.
	 */
	public static function getShortDay(string $date): string {

		$number = (int)substr($date, 8, 2);

		return self::getDayName($date).' '.(($number === 1) ? s("1er") : $number);

	}

	/**
	 * « 09:30 à 17:00 »
	 */
	public static function getHours(DateSequence $eDateSequence): string {

		return s("{start} à {end}", [
			'start' => \util\DateUi::textual($eDateSequence['startAt'], \util\DateUi::TIME_HOUR_MINUTE),
			'end' => \util\DateUi::textual($eDateSequence['endAt'], \util\DateUi::TIME_HOUR_MINUTE)
		]);

	}

	public static function getDayName(string $date): string {
		return \util\DateUi::getDayName((int)date('N', strtotime($date)));
	}

	public function create(Date $eDate): \Panel {
		return $this->getPanel($eDate, '/learning/date:doCreate', s("Ajouter une date"), s("Ajouter la date"));
	}

	public function update(Date $eDate): \Panel {
		return $this->getPanel($eDate, '/learning/date:doUpdate', s("Modifier la date"), s("Enregistrer"));
	}

	protected function getPanel(Date $eDate, string $action, string $title, string $submit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			if($eDate->exists()) {
				$h .= $form->hidden('id', $eDate);
			} else {
				$h .= $form->hidden('course', $eDate['course']);
			}

			$h .= $this->getFields($form, $eDate);

			// Seulement dans le panneau : le parcours de création ne connaît pas encore le réglage de
			// l'inscription, qui se pose depuis l'onglet Organisation (lot annuaire).
			$h .= $form->dynamicGroup($eDate, $eDate->requiresExternalUrl() ? 'externalUrl*' : 'externalUrl');

			$h .= $form->dynamicGroups($eDate, ['priceFunded', 'price', 'funding', 'fundingNote']);

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-date',
			title: $title,
			subTitle: CourseUi::getTypeBadge($eDate['course']).encode($eDate['course']['name']),
			body: $h
		);

	}

	/**
	 * Les champs d'une date, que le panneau et l'étape « La première date » du parcours de création
	 * (`CourseStartUi`) partagent : les séances, chacune avec son lieu en présentiel, puis un seul lien
	 * pour toutes les séances en visio, qui peut attendre que la réunion soit créée.
	 */
	public function getFields(\util\FormUi $form, Date $eDate): string {

		return $form->dynamicGroups($eDate, ['sequences*', 'link', 'minutes', 'seats']);

	}

	/**
	 * Le panneau de l'offre d'une date, ouvert depuis l'onglet Diffusion de sa formation
	 * (lot L15a). La date porte son offre, que chaque date d'une même formation choisit seule.
	 */
	public function updateOffer(Date $eDate): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/date:doUpdateOffer');

			$h .= $form->hidden('id', $eDate);

			$h .= $form->dynamicGroup($eDate, 'offer*');

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-date-offer',
			title: self::getOfferTitle($eDate),
			subTitle: CourseUi::getTypeBadge($eDate['course']).encode($eDate['course']['name']),
			body: $h
		);

	}

	/** « Mode de diffusion du vendredi 23 octobre 2026 », ou « Mode de diffusion de la date du lundi 2 au mardi 3 novembre 2026 ». */
	protected static function getOfferTitle(Date $eDate): string {

		$days = self::getDays($eDate);
		$days = mb_strtolower(mb_substr($days, 0, 1)).mb_substr($days, 1);

		if(substr($eDate['startAt'], 0, 10) === substr($eDate['endAt'], 0, 10)) {
			return s("Mode de diffusion du {days}", ['days' => $days]);
		} else {
			return s("Mode de diffusion de la date {days}", ['days' => $days]);
		}

	}

	/**
	 * Annuler garde la date écrite, marquée annulée, là où supprimer l'efface : le panneau dit ce
	 * qui change pour les fermes du réseau, et que le geste ne se défait pas. Les deux premières
	 * lignes ne paraissent que si des inscriptions sont encore ouvertes (`fillCounts()` posé par la
	 * page), la troisième que si l'une d'elles perd l'accès aux leçons (`losesLessons`, posé par la
	 * page par `DateEnrollmentLib::fillLosesLessonsByDate()`).
	 */
	public function cancel(Date $eDate): \Panel {

		$lines = [];

		$open = self::countOpen($eDate);

		if($open > 0) {
			$lines[] = ['icon' => 'envelope', 'text' => p("La personne qui a demandé une place reçoit un e-mail qui annonce l'annulation.", "Les {value} personnes qui ont demandé une place reçoivent un e-mail qui annonce l'annulation.", $open)];
			$lines[] = ['icon' => 'x-circle', 'text' => p("L'inscription encore ouverte à cette date passe à « Annulée ». La liste reste lisible et s'exporte encore.", "Les {value} inscriptions encore ouvertes à cette date passent à « Annulée ». Leur liste reste lisible et s'exporte encore.", $open)];
		}

		if($eDate['losesLessons']) {
			$lines[] = ['icon' => 'journal-x', 'text' => s("Les stagiaires qui n'ont pas d'autre accès à la formation ne voient plus ses leçons.")];
		}

		$lines[] = ['icon' => 'eye', 'text' => s("La date reste sur la fiche de la formation, marquée annulée.")];
		$lines[] = ['icon' => 'calendar-x', 'text' => s("Elle ne compte plus parmi les dates à venir, et la carte de la formation ne l'annonce plus.")];
		$lines[] = ['icon' => 'lock', 'text' => s("Elle ne se modifie plus.")];

		$body = \main\DesignUi::getWeigh(
			'info',
			'calendar-x',
			self::getDays($eDate),
			self::getLine($eDate, withLink: FALSE),
			$lines,
			[
				s("Les autres dates de la formation et leurs inscrits ne changent pas."),
				s("La progression des stagiaires est conservée."),
			],
			FALSE
		);

		$body .= '<div class="flex-buttons">';
			$body .= '<a data-ajax="/learning/date:doCancel" post-id="'.$eDate['id'].'" class="btn btn-primary" data-waiter>'.s("Annuler la date").'</a>';
			$body .= '<a onclick="Lime.Panel.closeLast()" class="btn btn-outline-primary">'.s("Garder la date").'</a>';
		$body .= '</div>';

		return new \Panel(
			id: 'panel-learning-date-cancel',
			title: s("Annuler la date ?"),
			subTitle: encode($eDate['course']['name']),
			body: $body
		);

	}

	/**
	 * La confirmation de l'envoi du lien de la visio, depuis la carte de la date : le geste envoie
	 * un e-mail à chaque personne confirmée qui a une adresse (`linkRecipients`, posé par la page),
	 * que le panneau chiffre, et dit ce qui arrive ensuite au lien. Le nombre n'est pas celui des
	 * confirmées, un compte fermé gardant sa place sans rien recevoir : la phrase ne dit donc pas
	 * « les ».
	 */
	public function sendLink(Date $eDate): \Panel {

		$eDate->expects(['linkRecipients']);

		$text = p(
			"Une personne confirmée reçoit un e-mail avec le lien de la visio, qui s'affiche ensuite sur la fiche de la formation.",
			"{value} personnes confirmées reçoivent un e-mail avec le lien de la visio, qui s'affiche ensuite sur la fiche de la formation.",
			$eDate['linkRecipients']
		).' '.s("Les personnes confirmées plus tard le reçoivent avec leur confirmation.");

		return \main\DesignUi::getConfirmPanel(
			'panel-learning-date-link',
			s("Envoyer le lien de la visio ?"),
			self::getDays($eDate).' · '.encode($eDate['course']['name']),
			$text,
			'<a data-ajax="/learning/date:doSendLink" post-id="'.$eDate['id'].'" class="btn btn-primary" data-waiter>'.\Asset::icon('envelope').' '.s("Envoyer le lien de la visio").'</a>'
		);

	}

	/**
	 * La confirmation de l'envoi des attestations de présence (lot L8f), depuis la liste des inscrits.
	 * Au premier envoi, le panneau dit que les personnes pointées plus tard la recevront au suivant ;
	 * ensuite, il compte les seules nouvelles et dit que les attestations déjà parties ne repartent
	 * pas (`attestationMailed`, à la date du dernier envoi). Posé par
	 * `DateEnrollmentLib::fillAttestationRecipients()`.
	 */
	public function sendAttestations(Date $eDate): \Panel {

		$eDate->expects(['attestationRecipients', 'attestationMailed', 'attestationMailedAt']);

		$recipients = $eDate['attestationRecipients'];

		if($eDate['attestationMailed'] === 0) {

			$title = p("Envoyer l'attestation ?", "Envoyer les attestations ?", $recipients);
			$text = p(
				"Une personne pointée présente reçoit un e-mail avec le lien de son attestation de présence.",
				"{value} personnes pointées présentes reçoivent un e-mail avec le lien de leur attestation de présence.",
				$recipients
			).' '.s("Une personne pointée plus tard la reçoit au prochain envoi.");

		} else {

			$title = p("Envoyer {value} attestation ?", "Envoyer {value} attestations ?", $recipients);
			$text = p(
				"{value} personne pointée depuis le dernier envoi reçoit un e-mail avec le lien de son attestation.",
				"{value} personnes pointées depuis le dernier envoi reçoivent un e-mail avec le lien de leur attestation.",
				$recipients
			).' '.p(
				"L'attestation du {date} ne repart pas.",
				"Les {value} attestations du {date} ne repartent pas.",
				$eDate['attestationMailed'],
				['date' => \util\DateUi::textual($eDate['attestationMailedAt'], \util\DateUi::DAY_MONTH)]
			);

		}

		return \main\DesignUi::getConfirmPanel(
			'panel-learning-date-attestation',
			$title,
			self::getDays($eDate).' · '.encode($eDate['course']['name']),
			$text,
			'<a data-ajax="/learning/date:doSendAttestations" post-id="'.$eDate['id'].'" class="btn btn-primary" data-waiter>'.\Asset::icon('envelope').' '.p("Envoyer l'attestation", "Envoyer les attestations", $recipients).'</a>'
		);

	}

	/**
	 * La confirmation de l'ouverture du quiz, depuis la liste des inscrits : le geste ne se défait
	 * pas et envoie un e-mail à chaque personne confirmée (`confirmed`, posé par la page).
	 */
	public function openQuiz(Date $eDate): \Panel {

		$eDate->expects(['confirmed']);

		$text = p(
			"La personne confirmée pourra passer le quiz, et un e-mail la prévient.",
			"Les {value} personnes confirmées pourront passer le quiz, et un e-mail les prévient.",
			$eDate['confirmed']
		).' '.s("Le quiz reste ouvert ensuite.");

		return \main\DesignUi::getConfirmPanel(
			'panel-learning-date-quiz',
			s("Ouvrir le quiz ?"),
			self::getDays($eDate).' · '.encode($eDate['course']['name']),
			$text,
			'<a data-ajax="/learning/date:doOpenQuiz" post-id="'.$eDate['id'].'" class="btn btn-primary" data-waiter>'.\Asset::icon('unlock').' '.s("Ouvrir le quiz").'</a>'
		);

	}

	/**
	 * Un champ d'heures au pas de 0,5, pour une colonne qui compte en minutes : la durée d'une date et
	 * les heures d'une personne pointée. Vide, la valeur suit une autre durée, que l'indication
	 * montre sans la poster.
	 */
	public static function getHoursField(\util\FormUi $form, \ModuleModel $model, string $property, ?int $minutes, ?int $minutesDefault, array $attributes = []): string {

		$hours = fn(int $minutes) => (string)($minutes / 60);

		[$minutesMin] = $model->getPropertyRange($property);

		return $form->inputGroup(
			$form->number($property, $minutes === NULL ? NULL : $hours($minutes), [
				'step' => 0.5,
				'min' => $hours($minutesMin),
				'placeholder' => $minutesDefault === NULL ? NULL : self::getHoursPlaceholder($minutesDefault)
			] + $attributes).
			$form->addon(s("h"))
		);

	}

	/**
	 * L'indication d'un champ d'heures, au centième et écrite comme un texte : 500 minutes se lisent
	 * « 8,33 » et non « 8.3333333333333 », 480 minutes « 8 ». Sans précision demandée,
	 * `TextUi::number()` ne complète pas par des zéros. `LearningDate.getScheduleHours()` rend la
	 * même chaine côté navigateur, quand les horaires changent sous les yeux (`test/DateForm.t.php`).
	 */
	public static function getHoursPlaceholder(int $minutes): string {
		return \util\TextUi::number(round($minutes / 60, 2), NULL);
	}

	public static function p(string $property): \PropertyDescriber {

		$d = Date::model()->describer($property, [
			'sequences' => s("Séances"),
			'startAt' => s("Début"),
			'endAt' => s("Fin"),
			'place' => s("Lieu"),
			'link' => s("Lien de la visio"),
			'seats' => s("Places"),
			'offer' => s("Mode de diffusion"),
			'priceFunded' => s("Public financé"),
			'price' => s("Autre public"),
			'funding' => s("Financements possibles"),
			'fundingNote' => s("Conditions"),
			'externalUrl' => s("Site de l'organisme"),
			'minutes' => s("Durée de formation"),
		]);

		switch($property) {

			// Aucun `for` : le libellé ne désigne pas un champ, mais la liste des lignes. Le refus
			// d'ensemble (aucune séance, chevauchement) se rattache au groupe, celui d'une ligne à son
			// champ (`DateSequenceUi::getRow()`).
			case 'sequences' :
				$d->field = fn(\util\FormUi $form, Date $e) => new DateSequenceUi()->getField($form, $e);
				$d->group = ['for' => FALSE];
				$d->wrapper = 'sequences';
				break;

			// Masqué tant qu'aucune séance n'est en visio, et montré par `LearningDate.changeMode()` dès
			// qu'une ligne le devient : le Lib ne l'écrit pas sans séance en visio.
			case 'link' :
				$d->group = fn(Date $e) => $e->hasMode(DateSequence::REMOTE) ? [] : ['class' => 'hide'];
				$d->placeholder = 'https://';
				$d->after = \util\FormUi::info(s("Vous pouvez le poser plus tard. Les inscrits le reçoivent quand vous cliquez sur « Envoyer le lien de la visio », sur la date."));
				break;

			// Laissée vide, la durée reste celle des séances, additionnées, qui suit un horaire changé
			// ensuite : l'indication la montre sans la poster, et tient lieu de consigne (retour de
			// Vincent du 01/10, point 17). Elle suit chaque saisie d'un horaire, chaque ligne ajoutée ou
			// retirée, par `LearningDate.updateSchedule()`, qui la retrouve à `data-schedule`.
			case 'minutes' :
				$d->field = function(\util\FormUi $form, Date $e): string {

					return DateUi::getHoursField($form, Date::model(), 'minutes', $e['minutes'], $e->getScheduleMinutesProposed(), ['data-schedule']);

				};
				$d->after = \util\FormUi::info(s("Pauses exclues."));
				break;

			case 'seats' :
				$d->after = \util\FormUi::info(s("Sans nombre, les places ne sont pas limitées."));
				break;

			case 'externalUrl' :
				$d->placeholder = 'https://';
				$d->after = fn(\util\FormUi $form, Date $e) => ($e['course']['externalUrl'] === NULL)
					? ''
					: \util\FormUi::info(s("Sans adresse, la date renvoie à la page de la formation.<br/>{value}", encode($e['course']['externalUrl'])));
				break;

			// La date partage le choix de la formation en ligne, sans le verrou du catalogue, qui ne
			// regarde que la formation : une date datée se retire du catalogue avec toute sa formation.
			case 'offer' :
				$d->values = CourseUi::getOfferValues();
				$d->labelAfter = \util\FormUi::info(CourseUi::getOfferIntro());
				$d->field = fn(\util\FormUi $form, Date $e) => CourseUi::getOfferField($form, $e['offer'], $e['course'], $e['holdsCatalog'], $e);
				break;

			// Laissé vide, chaque champ du prix reprend la valeur de la formation, que la consigne
			// montre sous le champ (décision du 03/10, même forme que `externalUrl`).
			case 'priceFunded' :
			case 'price' :
				$d->append = s("€");
				$d->after = fn(\util\FormUi $form, Date $e) => ($e['course'][$property] === NULL)
					? ''
					: \util\FormUi::info(s("Sans montant, la date reprend celui de la formation.<br/>{value}", CourseUi::getAmount($e['course'][$property])));
				break;

			case 'funding' :
				$d->values = CourseUi::getFundingValues();
				$d->field = fn(\util\FormUi $form, Date $e) => $form->checkboxes('funding[]', $d->values, $e['funding'] ?? [], ['all' => FALSE, 'columns' => 2]);
				$d->after = fn(\util\FormUi $form, Date $e) => ($e['course']['funding'] === NULL)
					? ''
					: \util\FormUi::info(s("Sans case cochée, la date reprend ceux de la formation.<br/>{value}", implode(', ', CourseUi::getFundingLabels($e['course']['funding']))));
				break;

			case 'fundingNote' :
				$d->after = fn(\util\FormUi $form, Date $e) => ($e['course']['fundingNote'] === NULL)
					? ''
					: \util\FormUi::info(s("Sans conditions, la date reprend celles de la formation.<br/>{value}", encode($e['course']['fundingNote'])));
				break;

		}

		return $d;

	}

}
?>
