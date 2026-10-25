<?php
namespace learning;

/**
 * Une date d'une formation de réseau : une ou plusieurs séances (`DateSequence`), chacune en
 * présentiel ou en visio, planifiée puis tenue, ou annulée. `startAt` et `endAt` bornent la première
 * et la dernière séance, et servent aux tris et aux échéances ; le reste se lit sur les séances
 * (`cDateSequence`). Elle se lit sur la fiche et sur la carte, et s'écrit depuis l'éditeur.
 */
class Date extends DateElement {

	public static function getSelection(): array {

		return self::getSelectionWithoutCourse() + [
			'course' => Course::getSelectionWithCommunity(),
		];

	}

	/**
	 * Sans la formation, que l'appelant qui la tient déjà accroche à chaque ligne.
	 */
	public static function getSelectionWithoutCourse(): array {

		return parent::getSelection() + [
			// `new DateSequenceModel()` : une date se lit aussi sous une sélection imbriquée
			// (`DateEnrollment::getSelection()`), qu'une requête sur le singleton interromprait.
			'cDateSequence' => new DateSequenceModel()
				->select(['id', 'startAt', 'endAt', 'mode', 'place'])
				->sort(['startAt' => SORT_ASC, 'id' => SORT_ASC])
				->delegateCollection('date'),
		];

	}

	public function canRead(): bool {

		$this->expects(['course']);

		return $this['course']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['course']);

		return $this['course']->canWrite();

	}

	public function acceptRead(): bool {

		$this->expects(['course']);

		return $this['course']->acceptRead();

	}

	/**
	 * Seul un réseau donne des dates à ses formations, et seulement en présentiel ou en visio :
	 * l'équipe, un groupe de formation et une formation en ligne n'en ont pas, et la section
	 * n'apparaît pas chez eux.
	 */
	public function acceptWrite(): bool {

		$this->expects(['course']);

		return (
			$this['course']->acceptWrite() and
			$this['course']->isDated()
		);

	}

	/**
	 * Une date annulée ou passée ne se modifie plus et ne s'annule plus : elle reste lisible telle
	 * qu'elle a été tenue ou retirée. Elle se supprime encore, par `acceptDelete()` qui retombe sur
	 * `acceptWrite()` : c'est le Lib qui refuse la dernière date planifiée d'une formation publiée
	 * sans leçon, une règle qui demande une requête.
	 */
	public function acceptUpdate(): bool {
		return $this->acceptCancel();
	}

	public function acceptCancel(): bool {

		$this->expects(['status']);

		return (
			$this->acceptWrite() and
			$this['status'] === Date::PLANNED and
			$this->isPast() === FALSE
		);

	}

	/**
	 * Les nouvelles inscriptions de la date sont en pause, par la sienne ou par celle de sa formation,
	 * qui l'emporte : les deux colonnes ne s'écrivent jamais l'une l'autre, et la date retrouve son
	 * propre réglage quand la formation reprend.
	 */
	public function isEnrollmentPaused(): bool {

		$this->expects(['enrollmentPaused', 'course' => ['enrollmentPaused']]);

		return (
			$this['course']->isEnrollmentPaused() or
			$this['enrollmentPaused']
		);

	}

	/**
	 * La pause d'une date se règle tant qu'elle prend des inscriptions : planifiée, à venir, d'une
	 * formation publiée dont les inscriptions ne sont pas déjà en pause. Celle de la formation
	 * gouverne alors toutes ses dates, et l'interrupteur de la date se cache.
	 */
	public function acceptUpdateEnrollmentPaused(): bool {

		$this->expects(['course' => ['status', 'enrollmentPaused']]);

		return (
			$this->acceptUpdate() and
			$this['course']['status'] === Course::PUBLISHED and
			$this['course']->isEnrollmentPaused() === FALSE
		);

	}

	public function assertUpdateEnrollmentPaused(): bool {

		return (
			$this->acceptUpdateEnrollmentPaused() and
			$this->canUpdate()
		);

	}

	/**
	 * La date paraît sur la page publique du réseau de l'adresse, et s'y inscrit : planifiée, à venir,
	 * d'une formation publiée et datée de ce réseau. Une date demandée par son id sous l'adresse
	 * d'un autre réseau n'y paraît pas. Jugée sur l'élément chargé, sans requête.
	 */
	public function acceptPublic(\structure\Structure $eStructureCommunity): bool {

		$this->expects(['status', 'endAt', 'course' => ['status', 'type', 'community']]);

		return (
			$this['status'] === Date::PLANNED and
			$this->isPast() === FALSE and
			$this['course']->acceptRead() and
			$this['course']->isDated() and
			$this['course']['community']['id'] === $eStructureCommunity['id']
		);

	}

	/**
	 * L'offre d'une date se change tant qu'elle se modifie. Aucune inscription ne la fige : aucune
	 * n'est payée tant que les offres payantes restent fermées (`Course::acceptPaidOffer()`), qui ne
	 * s'ouvrent pas avant que le paiement en ligne pose ce verrou (lot L15b). Inscription fermée (lot
	 * annuaire), l'offre ne se montre ni ne se saisit, et reste en base pour la réouverture.
	 */
	public function acceptUpdateOffer(): bool {

		return (
			$this->acceptUpdate() and
			$this['course']->isExternal() === FALSE
		);

	}

	/**
	 * Le réseau ouvre le quiz de la formation aux personnes confirmées de cette date, une fois et
	 * sans retour, à partir du premier jour : le formateur l'ouvre en salle. `confirmed` (le nombre
	 * de confirmées, `DateEnrollmentLib::fillCounts()`) et `eLessonQuiz` (la dernière leçon quiz du
	 * programme, `LessonLib::getLastQuiz()`) sont posés par la page, pour juger sans requête. Une
	 * date finie s'ouvre encore : le réseau qui a oublié le geste en salle le fait le lendemain.
	 */
	public function acceptOpenQuiz(): bool {

		$this->expects(['status', 'startAt', 'quizOpenedAt', 'confirmed', 'eLessonQuiz']);

		return (
			$this['status'] === Date::PLANNED and
			$this->isStarted() and
			$this['quizOpenedAt'] === NULL and
			$this['eLessonQuiz']->notEmpty() and
			$this['confirmed'] > 0
		);

	}

	/**
	 * La feuille d'émargement d'une date qui a une séance en présentiel, planifiée, qui a au moins une
	 * personne confirmée (lot L8e) : `confirmed` est posé par `DateEnrollmentLib::fillCounts()`. Une
	 * date passée se réédite, le réseau qui a égaré la feuille la réimprime pour la faire signer après
	 * coup. Ses colonnes sont celles des seules séances en présentiel (`DateLib::getHalfDays()`).
	 */
	public function acceptSheet(): bool {

		$this->expects(['status', 'confirmed']);

		return (
			$this->hasMode(DateSequence::ONSITE) and
			$this['status'] === Date::PLANNED and
			$this['confirmed'] > 0
		);

	}

	/**
	 * L'équipe pointe les présences et corrige la durée d'une date planifiée une fois qu'elle est
	 * finie (lot L8e) : avant, personne n'est encore venu. Une formation de plusieurs jours ne se
	 * pointe donc qu'à la fin. Hors de `acceptUpdate()`, qui refuse une date passée.
	 */
	public function acceptAttendance(): bool {

		$this->expects(['status', 'endAt']);

		return (
			$this->acceptWrite() and
			$this['status'] === Date::PLANNED and
			$this->isPast()
		);

	}

	/**
	 * La feuille signée d'une date qui a une séance en présentiel se dépose après la date, dix
	 * fichiers au plus : `sheets` est posé par la page (`DateSheetLib::countByDate()`), et le Lib
	 * recompte sous le verrou de la date. Une date tout en visio n'a pas de feuille à signer.
	 */
	public function acceptSheetUpload(): bool {

		$this->expects(['sheets']);

		return (
			$this->acceptAttendance() and
			$this->hasMode(DateSequence::ONSITE) and
			$this['sheets'] < DateSheet::MAX_BY_DATE
		);

	}

	/**
	 * La durée de formation, pauses exclues : celle que l'équipe a saisie, sinon celle des séances,
	 * additionnées.
	 */
	public function getMinutes(): int {

		$this->expects(['minutes']);

		if($this['minutes'] !== NULL) {
			return $this['minutes'];
		}

		return $this->getScheduleMinutes();

	}

	/**
	 * La durée des séances, additionnées, que le formulaire propose quand aucune durée n'est saisie.
	 * La séance qu'une date neuve propose n'a pas de jour (`Course::getDefaultSequence()`) : elle ne
	 * compte rien, comme dans le navigateur tant que le jour manque.
	 */
	public function getScheduleMinutes(): int {

		$this->expects(['cDateSequence']);

		return (int)$this['cDateSequence']->sum(fn(DateSequence $eDateSequence) => ($eDateSequence['startAt'] === NULL) ? 0 : $eDateSequence->getMinutes());

	}

	/**
	 * La durée des séances que le formulaire propose en indication, ou rien quand elle est nulle :
	 * une date sans séance, ou dont aucune séance ne finit après son début, ce que l'envoi refuse.
	 */
	public function getScheduleMinutesProposed(): ?int {

		$minutes = $this->getScheduleMinutes();

		return ($minutes === 0) ? NULL : $minutes;

	}

	/**
	 * Une séance de la date se tient-elle dans ce mode ? C'est ce qui décide du lien de la visio
	 * (`REMOTE`) et de la feuille d'émargement (`ONSITE`).
	 */
	public function hasMode(string $mode): bool {

		$this->expects(['cDateSequence']);

		return $this['cDateSequence']->contains(fn(DateSequence $eDateSequence) => $eDateSequence['mode'] === $mode);

	}

	/**
	 * Les modes des séances de la date, sans doublon, dans l'ordre de `DateSequence::mode`.
	 *
	 * @return string[]
	 */
	public function getModes(): array {

		$this->expects(['cDateSequence']);

		return array_values(array_filter(
			[DateSequence::ONSITE, DateSequence::REMOTE],
			fn(string $mode) => $this->hasMode($mode)
		));

	}

	/**
	 * Le lieu commun à toutes les séances en présentiel, NULL quand elles en ont plusieurs ou
	 * qu'aucune n'en porte : les listes et la page publique n'affichent un lieu que s'il est seul.
	 */
	public function getPlace(): ?string {

		$this->expects(['cDateSequence']);

		$places = [];

		foreach($this['cDateSequence'] as $eDateSequence) {
			if($eDateSequence['mode'] === DateSequence::ONSITE) {
				$places[$eDateSequence['place'] ?? ''] = TRUE;
			}
		}

		return (count($places) === 1 and array_key_first($places) !== '') ? (string)array_key_first($places) : NULL;

	}

	/**
	 * L'adresse où s'inscrire sur le site de l'organisme (lot annuaire) : celle de la date, sinon
	 * celle de la formation. Une date à venir en a toujours une des deux (`externalUrl.uncovered`) ;
	 * une date annulée ou passée peut n'en avoir aucune.
	 */
	public function getExternalUrl(): ?string {

		$this->expects(['externalUrl', 'course' => ['externalUrl']]);

		return $this['externalUrl'] ?? $this['course']['externalUrl'];

	}

	/**
	 * Les montants, les financeurs et les conditions d'une date : les siens, sinon ceux de sa
	 * formation, valeur par valeur. Une date surcharge une valeur sans pouvoir la retirer (décision du
	 * 03/10) : un montant à 0 dit « Gratuit », il n'efface pas celui de la formation.
	 */
	public function getPriceFunded(): ?float {

		$this->expects(['priceFunded', 'course' => ['priceFunded']]);

		return $this['priceFunded'] ?? $this['course']['priceFunded'];

	}

	public function getPrice(): ?float {

		$this->expects(['price', 'course' => ['price']]);

		return $this['price'] ?? $this['course']['price'];

	}

	public function isFree(): bool {

		return (
			$this->getPriceFunded() === 0.0 and
			$this->getPrice() === 0.0
		);

	}

	public function getFunding(): ?\Set {

		$this->expects(['funding', 'course' => ['funding']]);

		return $this['funding'] ?? $this['course']['funding'];

	}

	public function getFundingNote(): ?string {

		$this->expects(['fundingNote', 'course' => ['fundingNote']]);

		return $this['fundingNote'] ?? $this['course']['fundingNote'];

	}

	/**
	 * Le paiement en ligne encaisse le montant « Autre public » que la date affiche, le sien ou celui
	 * de sa formation, et seulement s'il n'est pas nul.
	 */
	public function acceptTraineeOffer(): bool {

		$price = $this->getPrice();

		return (
			$price !== NULL and
			$price > 0
		);

	}

	/**
	 * La date doit porter sa page sur le site de l'organisme quand sa formation y envoie ses
	 * inscriptions sans page à elle (lot annuaire) : le visiteur de la date n'aurait sinon nulle part
	 * où aller. Le formulaire marque le champ obligatoire, l'étape `externalUrl.uncovered` refuse.
	 */
	public function requiresExternalUrl(): bool {

		$this->expects(['course' => ['type', 'externalOnly', 'externalUrl']]);

		return (
			$this['course']->isExternal() and
			$this['course']['externalUrl'] === NULL
		);

	}

	/**
	 * Une date planifiée à venir qui a une séance en visio attend encore son lien : l'éditeur le signale au réseau, qui a un
	 * lien à poser. Une date passée ou annulée n'attend plus rien.
	 */
	public function isLinkMissing(): bool {

		$this->expects(['status', 'endAt', 'link']);

		return (
			$this->hasMode(DateSequence::REMOTE) and
			$this['link'] === NULL and
			$this['status'] === Date::PLANNED and
			$this->isPast() === FALSE
		);

	}

	/**
	 * Une date planifiée à venir qui a une séance en visio porte son lien, envoyé ou non : l'éditeur le montre à l'équipe du
	 * réseau dans la carte de la date. Une date passée ou annulée ne mène plus nulle part.
	 */
	public function isLinkPosed(): bool {

		$this->expects(['status', 'endAt', 'link']);

		return (
			$this->hasMode(DateSequence::REMOTE) and
			$this['link'] !== NULL and
			$this['status'] === Date::PLANNED and
			$this->isPast() === FALSE
		);

	}

	/**
	 * Le réseau a envoyé le lien de la visio aux personnes confirmées (`DateLib::sendLink()`). C'est
	 * à partir de là qu'un inscrit confirmé le lit, sur la fiche comme dans ses e-mails : un lien
	 * posé et pas envoyé reste entre les mains du réseau. La trace ne vit pas sans lien,
	 * `DateLib::update()` la remet à vide dès que le lien change ou s'efface.
	 */
	public function isLinkMailed(): bool {

		$this->expects(['linkMailedAt']);

		return ($this['linkMailedAt'] !== NULL);

	}

	/**
	 * Une date planifiée à venir qui a une séance en visio, dont les inscrits n'ont pas reçu le lien, qu'il soit à poser ou à
	 * envoyer : l'inscrit confirmé lit que le lien lui sera envoyé, et le rappel qui part sans lui
	 * alerte le réseau.
	 */
	public function isLinkAwaited(): bool {

		$this->expects(['status', 'endAt', 'linkMailedAt']);

		return (
			$this->hasMode(DateSequence::REMOTE) and
			$this['linkMailedAt'] === NULL and
			$this['status'] === Date::PLANNED and
			$this->isPast() === FALSE
		);

	}

	/**
	 * Le lien de la visio peut partir : il est posé, pas encore envoyé, la date est planifiée et pas
	 * finie, et au moins une personne le recevrait (`linkRecipients`, posé par
	 * `DateEnrollmentLib::fillLinkRecipients()`) : une confirmée dont le compte est fermé ne reçoit
	 * rien, et une date qui n'a qu'elles ne propose pas un envoi sans destinataire. Une date
	 * commencée l'accepte encore : le réseau qui a oublié le fait à l'heure de la séance.
	 */
	public function acceptSendLink(): bool {

		$this->expects(['link', 'linkRecipients']);

		return (
			$this->isLinkAwaited() and
			$this['link'] !== NULL and
			$this['linkRecipients'] > 0
		);

	}

	/**
	 * La date relève des attestations de présence (lot L8f) : elle se pointe, et sa formation n'a pas
	 * de leçon (`course.lessons`, posé par `DateEnrollmentLib::fillAttestationRecipients()`). La
	 * liste de ses inscrits en dit l'état, que les objectifs soient écrits ou non.
	 */
	public function hasPresenceAttestations(): bool {

		$this->expects(['course' => ['lessons']]);

		return (
			$this->acceptAttendance() and
			$this['course']['lessons'] === 0
		);

	}

	/**
	 * La date délivre ses attestations de présence : elle en relève, et sa formation a ses objectifs,
	 * que l'attestation cite (`DateEnrollment::acceptPresenceAttestation()`). C'est ce que juge
	 * l'envoi : qu'il ne reste personne à qui écrire se dit par un message, le panneau ayant pu être
	 * ouvert avant un envoi parti d'un autre onglet.
	 */
	public function acceptAttestations(): bool {

		$this->expects(['course' => ['objectives']]);

		return (
			$this->hasPresenceAttestations() and
			$this['course']->hasObjectives()
		);

	}

	/**
	 * Les attestations de présence peuvent partir : la date en délivre, et une place au moins attend
	 * la sienne (`attestationRecipients`). Le geste se refait pour une personne pointée après un
	 * premier envoi.
	 */
	public function acceptSendAttestations(): bool {

		$this->expects(['attestationRecipients']);

		return (
			$this->acceptAttestations() and
			$this['attestationRecipients'] > 0
		);

	}

	public function isQuizOpened(): bool {

		$this->expects(['quizOpenedAt']);

		return ($this['quizOpenedAt'] !== NULL);

	}

	/**
	 * Le premier jour de la date est atteint, dès 00:00 et non à l'heure de début : c'est à partir de
	 * ce jour que le quiz peut s'ouvrir.
	 */
	public function isStarted(): bool {

		$this->expects(['startAt']);

		return (substr($this['startAt'], 0, 10) <= \Clock::date());

	}

	public function isPast(): bool {

		$this->expects(['endAt']);

		return ($this['endAt'] < \Clock::datetime());

	}

	/**
	 * Les jours où la date tient une séance, sans doublon : deux séances le même jour en font un, et
	 * un jour sans séance entre deux autres ne compte pas.
	 */
	public function countDays(): int {

		return count($this->getDays());

	}

	/**
	 * @return string[] les jours des séances, `AAAA-MM-JJ`, dans l'ordre
	 */
	public function getDays(): array {

		$this->expects(['cDateSequence']);

		$days = [];

		foreach($this['cDateSequence'] as $eDateSequence) {
			$days[$eDateSequence->getDay()] = TRUE;
		}

		return array_keys($days);

	}

	public function assertRead(): bool {

		$this->expects(['course']);

		return $this['course']->assertRead();

	}

	/**
	 * Des heures saisies (« 7,5 » ou « 7.5 ») en minutes, pour `minutes` d'une date et
	 * `attendedMinutes` d'une inscription. Une chaîne vide passe telle quelle, pour que le cast en
	 * fasse NULL. Tout autre texte qui n'est pas un nombre est refusé ici : laissé au cast `int`,
	 * « 45 min » deviendrait 45 minutes sans que personne l'ait voulu.
	 */
	public static function castHours(mixed &$value): bool {

		if(is_string($value) === FALSE or trim($value) === '') {
			return TRUE;
		}

		$hours = str_replace(',', '.', trim($value));

		if(is_numeric($hours) === FALSE) {
			return FALSE;
		}

		$value = (int)round((float)$hours * 60);

		return TRUE;

	}

	public static function properties(\PropertyList $l): void {

		/**
		 * Les séances, postées toutes ensemble : `sequences[]` porte l'index de chaque ligne du
		 * formulaire, et les champs de la ligne sont `startAt[i]`, `endAt[i]`, `mode[i]` et
		 * `place[i]`. Chaque ligne se construit comme une séance (`DateSequence::properties()`), ses
		 * refus nommés sur son propre champ (`startAt[i]`), et toutes les lignes sont construites
		 * avant de conclure, pour que l'écran montre chaque refus d'un coup. Une ligne refusée
		 * arrête la propriété sans refus de plus (`PropertySkip`) : les contrôles d'ensemble, qui
		 * suivent, ne jugent que des lignes construites. La date reçoit ses séances et ses bornes,
		 * le début de la première et la fin de la dernière ; `DateLib` les écrit.
		 */
		$l->property('sequences')
			// Avant toute construction : une entrée de mille lignes ne construit rien.
			->addBeforeCast('tooMany', fn(mixed $sequences): bool => (
				is_array($sequences) === FALSE or
				count($sequences) <= DateSequence::MAX_BY_DATE
			))
			->replaceCheck(function(mixed &$sequences, \PropertyList $l): bool {

				if(is_array($sequences) === FALSE) {
					$sequences = new \Collection();
					return TRUE;
				}

				// array_unique() compare en chaîne : un index posté en tableau (`sequences[][x]`) doit
				// être refusé avant, sinon PHP lève au lieu de rendre le refus du schéma.
				foreach($sequences as $index) {
					if(is_string($index) === FALSE and is_int($index) === FALSE) {
						return FALSE;
					}
				}

				$built = [];

				$refused = FALSE;

				foreach(array_unique($sequences) as $index) {

					$eDateSequence = new DateSequence();

					$lDateSequence = new \PropertyList('create');
					$eDateSequence->buildIndex(DateSequenceLib::getPropertiesCreate(), $l->input, $index, $lDateSequence);

					if($lDateSequence->isBuilt(DateSequenceLib::getPropertiesCreate()) === FALSE) {
						$refused = TRUE;
						continue;
					}

					$built[] = $eDateSequence;

				}

				if($refused) {
					throw new \PropertySkip();
				}

				usort($built, fn(DateSequence $eDateSequence1, DateSequence $eDateSequence2) => strcmp($eDateSequence1['startAt'], $eDateSequence2['startAt']));

				$sequences = new \Collection($built);

				return TRUE;

			})
			->addAfterCheck('none', fn(\Collection $cDateSequence): bool => $cDateSequence->notEmpty())
			// Triées par leur début, deux séances se chevauchent quand l'une commence avant la fin de
			// la précédente : une séance ne passant pas minuit, seules celles d'un même jour le peuvent.
			->addAfterCheck('overlap', function(\Collection $cDateSequence): bool {

				$endAt = NULL;

				foreach($cDateSequence as $eDateSequence) {

					if($endAt !== NULL and $eDateSequence['startAt'] < $endAt) {
						return FALSE;
					}

					$endAt = $eDateSequence['endAt'];

				}

				return TRUE;

			})
			->replaceSet(function(\Collection $cDateSequence): void {

				$this['cDateSequence'] = $cDateSequence;
				$this['startAt'] = $cDateSequence->first()['startAt'];
				$this['endAt'] = $cDateSequence->last()['endAt'];

			});

		// Les mêmes règles que l'offre d'une formation en ligne, dont `Course::properties()` donne les
		// raisons. Au catalogue, une formation datée garde au moins une date à venir qui porte une
		// offre : seule la dernière se ferme à « Sans offre » (`DateLib::holdsCatalog()`), et les dates
		// qui passent d'elles-mêmes ne sont refusées à personne.
		$l->property('offer')
			->addAfterCheck('paid', fn(string $offer): bool => (
				$offer === Date::NONE or
				$this['course']->acceptPaidOffer()
			))
			->addAfterCheck('account', fn(string $offer): bool => (
				$offer !== Date::TRAINEE or
				$offer === $this['offer'] or
				CommunityLib::hasPayoutAccount($this['course']['community'])
			))
			->addAfterCheck('priced', fn(string $offer): bool => (
				$offer !== Date::TRAINEE or
				$this->acceptTraineeOffer()
			))
			->addAfterCheck('catalog', fn(string $offer): bool => (
				$offer !== Date::NONE or
				$offer === $this['offer'] or
				DateLib::holdsCatalog($this) === FALSE
			));

		// L'écran saisit des heures au pas de 0,5 et la colonne compte en minutes, comme les leçons
		// (`CourseUi::getDuration()`) : la conversion précède le cast.
		$l->property('minutes')
			->addBeforeCast('hours', fn(mixed &$value): bool => Date::castHours($value))
			// Une durée vidée retombe sur celle des horaires, qui ne doit pas plus passer sous les
			// heures déjà saisies d'une personne. Le refus nomme la personne : il passe par une
			// exception, seule voie qui porte des arguments jusqu'au message.
			->addAfterCheck('attended', function(?int $minutes): bool {

				if($this->exists() === FALSE) {
					return TRUE;
				}

				$eDateEnrollment = DateEnrollmentLib::getLongestAttendanceByDate($this);

				if(
					$eDateEnrollment->notEmpty() and
					($minutes ?? $this->getScheduleMinutes()) < $eDateEnrollment['attendedMinutes']
				) {
					throw new \FailException('learning\Date::minutes.attended', ['eDateEnrollment' => $eDateEnrollment]);
				}

				return TRUE;

			});

		// Le pendant de `Course::externalUrl.uncovered` : une date ajoutée ou modifiée après coup sur une
		// formation sans page redemande la sienne (Vincent, 01/10/2026).
		$l->property('externalUrl')
			->addAfterCheck('uncovered', fn(?string $externalUrl): bool => (
				$externalUrl !== NULL or
				$this->requiresExternalUrl() === FALSE
			));

		// Le pendant de `Course::price.trainee` pour une date payée par le stagiaire : son montant
		// « Autre public » ne se vide qu'en reprenant un montant non nul de la formation, et ne passe
		// pas à 0. À la création, `offer` n'est ni posté ni posé : `DateLib::create()` le décide après
		// le build, d'après le montant que la date reprend.
		$l->property('price')
			->addAfterCheck('trainee', function(?float $price): bool {

				if(
					$this->exists() === FALSE or
					$this['offer'] !== Date::TRAINEE
				) {
					return TRUE;
				}

				$price ??= $this['course']['price'];

				return (
					$price !== NULL and
					$price > 0
				);

			});

		// La raison est celle de `Course::funding.none`.
		$l->property('funding')
			->addAfterCast('none', function(?\Set &$funding): bool {
				if($funding !== NULL and $funding->get() === 0) {
					$funding = NULL;
				}
				return TRUE;
			});

	}

}
?>
