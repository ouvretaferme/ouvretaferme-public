<?php
namespace learning;

/**
 * L'inscription d'une personne à une date d'une formation de réseau, et ce qu'elle devient : à
 * confirmer par le réseau, confirmée, en liste d'attente, ou annulée par l'un ou l'autre.
 *
 * Une ligne par personne et par date (`UNIQUE [user, date]`) : se réinscrire rouvre la ligne annulée
 * au lieu d'en écrire une seconde (`DateEnrollmentLib::create()`). Elle ne crée pas d'`Enrollment`,
 * qui porte le suivi des leçons : suivre les leçons reste un geste à part.
 *
 * Une ligne venue de la page publique du réseau peut n'avoir pas de compte (`user` vide) : elle porte
 * alors l'identité saisie (`firstName`, `lastName`, `email`, `structureName`), unique par adresse et
 * par date, jusqu'à ce qu'un compte à la même adresse se connecte (`UserObserverLib::logIn()`). Toute
 * ligne publique porte un jeton (`token`), l'adresse de la ligne dans les e-mails, par laquelle elle
 * se relit et s'annule. Le nom et l'adresse d'une ligne se lisent toujours par `getName()` et
 * `getEmail()` (contrôle : `DateEnrollmentContactSentinel`).
 */
class DateEnrollment extends DateEnrollmentElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'date' => Date::getSelection(),
			'user' => ['firstName', 'lastName', 'email'],
			'structure' => ['name'],
		];

	}

	/**
	 * La personne lit sa propre ligne ; le réseau lit toutes celles de ses dates.
	 */
	public function canRead(): bool {

		$this->expects(['user']);

		return (
			$this['user']->isOnline() or
			$this->canManage()
		);

	}

	/**
	 * S'inscrire demande de lire la formation, comme la commencer (`Enrollment::canCreate()`) : un
	 * réseau qui ne l'a pas partagée avec la ferme ne reçoit pas d'inscription de sa part.
	 */
	public function canCreate(): bool {

		$this->expects(['user', 'date' => ['course']]);

		return (
			$this['user']->isOnline() and
			$this['date']['course']->canRead()
		);

	}

	/**
	 * La personne annule sa propre inscription depuis la fiche ; le réseau annule par `canManage()`,
	 * depuis la liste, et l'e-mail part alors dans l'autre sens.
	 */
	public function canCancel(): bool {

		$this->expects(['user']);

		return $this['user']->isOnline();

	}

	/**
	 * Confirmer, donner une place, annuler au nom du réseau, lire la liste et l'exporter : ce que
	 * peut l'équipe qui écrit la formation.
	 */
	public function canManage(): bool {

		$this->expects(['date' => ['course']]);

		return $this['date']['course']->canWrite();

	}

	/**
	 * La date prend des inscriptions (`acceptOpen()`), et elles ne sont pas en pause
	 * (`acceptPause()`). La ligne est celle que `DateEnrollmentPage::getCreateElement()` a chargée,
	 * existante ou neuve : aucune requête ici, l'API ne lisant que les `accept*`.
	 */
	public function acceptCreate(): bool {

		return (
			$this->acceptOpen() and
			$this->acceptPause()
		);

	}

	/**
	 * Une date planifiée et à venir d'une formation publiée, dont les inscriptions ne sont pas
	 * fermées sur le site de l'organisme (lot annuaire), sur laquelle la personne n'a pas de ligne
	 * encore ouverte. Un refus définitif, que la page dit avant celui, passager, de la pause.
	 */
	public function acceptOpen(): bool {

		$this->expects(['status', 'date' => ['status', 'endAt', 'course' => ['type', 'community', 'externalOnly']]]);

		return (
			$this->isOpen() === FALSE and
			$this['date']['status'] === Date::PLANNED and
			$this['date']->isPast() === FALSE and
			$this['date']['course']->isDated() and
			$this['date']['course']->isExternal() === FALSE and
			$this['date']['course']->acceptRead()
		);

	}

	/**
	 * Ni la date ni sa formation ne sont en pause (`Date::isEnrollmentPaused()`). Une question à part,
	 * pour que la page qui refuse dise quoi faire ensuite. Relu sous le verrou de la date
	 * (`DateEnrollmentLib::getClosedReason()`).
	 */
	public function acceptPause(): bool {

		$this->expects(['date']);

		return ($this['date']->isEnrollmentPaused() === FALSE);

	}

	/**
	 * L'inscription par la page publique du réseau de l'adresse (`$eStructureCommunity`, posé par
	 * `DateEnrollmentLib::getPublicCreateElement()`) : la date y paraît, la ligne chargée n'est pas
	 * déjà ouverte, et la formation n'inscrit pas sur le site de l'organisme. Aucun droit à tenir, la
	 * page est ouverte à tous ; le doublon, le plafond par adresse et la limite par poste se lisent
	 * sous le verrou, dans `createPublic()`. Pas d'inscription pendant une pause.
	 */
	public function acceptPublicCreate(): bool {

		$this->expects(['date' => ['course' => ['externalOnly']]]);

		return (
			$this->acceptPublicRead() and
			$this->acceptPause() and
			$this['date']['course']->isExternal() === FALSE
		);

	}

	/**
	 * La page d'une date sur l'adresse publique, avant toute inscription : la même règle, sans l'état de
	 * l'inscription. Fermée sur Ouvretaferme (lot annuaire), la date s'y ouvre encore, pour mener au
	 * site de l'organisme : une adresse déjà partagée par le réseau ne devient pas une 404.
	 */
	public function acceptPublicRead(): bool {

		$this->expects(['status', 'date', 'eStructureCommunity']);

		return (
			$this->isOpen() === FALSE and
			$this['date']->acceptPublic($this['eStructureCommunity'])
		);

	}

	/**
	 * Une ligne annulée ne s'annule pas deux fois, et une date passée garde ses inscrits tels qu'ils
	 * sont venus.
	 */
	public function acceptCancel(): bool {

		$this->expects(['date' => ['endAt']]);

		return (
			$this->isOpen() and
			$this['date']->isPast() === FALSE
		);

	}

	public function acceptConfirm(): bool {

		$this->expects(['status']);

		return (
			$this['status'] === DateEnrollment::REQUESTED and
			$this->acceptCancel()
		);

	}

	/**
	 * Qu'il reste une place se compte sous le verrou de la date, dans `DateEnrollmentLib::giveSeat()` :
	 * ici, seulement l'état de la ligne.
	 */
	public function acceptGiveSeat(): bool {

		$this->expects(['status']);

		return (
			$this['status'] === DateEnrollment::WAITING and
			$this->acceptCancel()
		);

	}

	/**
	 * L'équipe pointe une personne confirmée, une fois la date finie (`Date::acceptAttendance()`).
	 * Une demande restée à confirmer, une personne en attente ou une annulée ne sont pas venues au
	 * titre de la date. Ne passe pas par `acceptCancel()`, qui refuse la date passée.
	 */
	public function acceptAttend(): bool {

		$this->expects(['status', 'date']);

		return (
			$this['status'] === DateEnrollment::CONFIRMED and
			$this['date']->acceptAttendance()
		);

	}

	/**
	 * Les heures ne se corrigent que sur une personne pointée : une valeur posée sans pointage
	 * resterait en base sans que rien ne l'affiche, et reviendrait au pointage suivant.
	 */
	public function acceptAttendedMinutes(): bool {

		return (
			$this->acceptAttend() and
			$this->isAttended()
		);

	}

	/**
	 * La place relève de l'attestation de présence (lot L8f) : confirmée et pointée, sur une date
	 * planifiée et finie, d'une formation sans leçon. Une formation à leçons a l'attestation de suivi
	 * (`Course::acceptAttestation()`), qui additionne déjà les dates pointées, et les deux ne se
	 * délivrent jamais ensemble. `lessons` est posé sur la formation de la date par
	 * `DateEnrollmentLib::fillLessons()`.
	 */
	public function hasPresenceAttestation(): bool {

		$this->expects(['status', 'attendedAt', 'date' => ['status', 'endAt', 'course' => ['lessons']]]);

		return (
			$this['status'] === DateEnrollment::CONFIRMED and
			$this->isAttended() and
			$this['date']['status'] === Date::PLANNED and
			$this['date']->isPast() and
			$this['date']['course']['lessons'] === 0
		);

	}

	/**
	 * L'attestation de présence se délivre à une place qui en relève, et seulement quand la formation
	 * a ses objectifs : l'article L6353-1 du code du travail veut qu'elle les cite, et une attestation
	 * qui les tairait ne vaudrait rien. Aucun droit ici : la page à jeton n'a pas de compte, et la
	 * route de la fiche pose `canRead()` à côté.
	 */
	public function acceptPresenceAttestation(): bool {

		$this->expects(['date' => ['course' => ['objectives']]]);

		return (
			$this->hasPresenceAttestation() and
			$this['date']['course']->hasObjectives()
		);

	}

	/**
	 * L'attestation de présence attend encore son e-mail : la place en relève, elle a une adresse,
	 * et aucun envoi ne l'a encore portée. Les objectifs n'y entrent pas : une place qui attend la
	 * sienne sur une formation qui n'en a pas encore, c'est ce qui fait paraître « Écrire les
	 * objectifs » à l'équipe. Une place dépointée puis repointée a gardé sa trace
	 * d'envoi (`attestationMailedAt` ne se vide jamais) et ne la reçoit pas une seconde fois.
	 */
	public function isAttestationAwaited(): bool {

		$this->expects(['attestationMailedAt']);

		return (
			$this->hasPresenceAttestation() and
			$this['attestationMailedAt'] === NULL and
			$this->getEmail() !== NULL
		);

	}

	public function isAttended(): bool {

		$this->expects(['attendedAt']);

		return ($this['attendedAt'] !== NULL);

	}

	/**
	 * Les heures de la personne présente : celles que l'équipe a corrigées, sinon la durée de la date,
	 * qui suit donc une durée changée après le pointage.
	 */
	public function getAttendedMinutes(): int {

		$this->expects(['attendedMinutes', 'date']);

		return $this['attendedMinutes'] ?? $this['date']->getMinutes();

	}

	/**
	 * Une ligne neuve n'a pas encore d'état (`status` à `NULL`), elle n'est donc pas ouverte.
	 */
	public function isOpen(): bool {

		$this->expects(['status']);

		return in_array($this['status'], [DateEnrollment::REQUESTED, DateEnrollment::CONFIRMED, DateEnrollment::WAITING], TRUE);

	}

	/**
	 * Une demande tant qu'aucune place n'a été donnée, une inscription ensuite : c'est le mot que la
	 * personne lit à l'écran et dans les e-mails. L'annulation ne garde pas l'état d'avant, mais elle
	 * ne remet pas `confirmedAt` à `NULL`, qui dit donc aussi ce qu'était une ligne annulée. La
	 * réouverture le repose selon le nouvel état.
	 */
	public function isRequest(): bool {

		$this->expects(['confirmedAt']);

		return $this['confirmedAt'] === NULL;

	}

	/**
	 * Le compte d'abord, la saisie de la page publique sinon : une ligne rattachée garde sa saisie,
	 * mais c'est le compte que la personne tient à jour. Échappé, comme `User::getName()`.
	 */
	public function getName(): string {

		$this->expects(['user', 'firstName', 'lastName']);

		if($this['user']->notEmpty()) {
			return $this['user']->getName();
		}

		return encode($this['firstName']).' '.encode($this['lastName']);

	}

	/**
	 * Le prénom et le nom tels quels, non échappés, pour l'export : ils suivent `getName()`. Un compte
	 * peut n'avoir pas de prénom, une saisie de la page publique en a toujours un.
	 */
	public function getFirstName(): ?string {

		$this->expects(['user', 'firstName']);

		return $this['user']->notEmpty() ? $this['user']['firstName'] : $this['firstName'];

	}

	public function getLastName(): string {

		$this->expects(['user', 'lastName']);

		return $this['user']->notEmpty() ? $this['user']['lastName'] : $this['lastName'];

	}

	/**
	 * `NULL` quand le compte est fermé : `DropLib::closeNow()` vide son adresse, et la saisie de la
	 * ligne ne se relit pas à sa place, la personne ayant retiré son adresse en fermant son compte.
	 * Même forme que `Review::getAuthorEmail()`.
	 */
	public function getEmail(): ?string {

		$this->expects(['user', 'email']);

		if($this['user']->notEmpty()) {
			return $this['user']['email'];
		}

		return $this['email'];

	}

	/**
	 * Une ligne venue de la page publique : elle se relit et s'annule par son jeton, depuis l'e-mail,
	 * y compris quand elle a un compte (connecté hors du réseau, ou rattachée depuis).
	 */
	public function isPublic(): bool {

		$this->expects(['token']);

		return ($this['token'] !== NULL);

	}

	/**
	 * Quinze octets rendent trente caractères, la longueur exacte que la colonne fixe (précédent
	 * `quotation\Invitation::getNewToken()`).
	 */
	public function getNewToken(): string {
		return bin2hex(random_bytes(15));
	}

	public static function properties(\PropertyList $l): void {

		// Des heures saisies, comme la durée de la date (`Date::castHours()`), et jamais plus que
		// cette durée : une personne ne reste pas plus longtemps que la formation.
		$l->property('attendedMinutes')
			->addBeforeCast('hours', fn(mixed &$value): bool => Date::castHours($value))
			->addAfterCheck('tooHigh', fn(?int $minutes): bool => (
				$minutes === NULL or
				$minutes <= $this['date']->getMinutes()
			));

		// Les colonnes d'identité sont nullables pour les lignes de membre, qui n'en portent aucune ;
		// sur la page publique, elles sont exigées. `structureName` reste facultative (à titre
		// personnel), et le schéma juge sa longueur.
		$l->property('email')
			->required()
			->addAfterCast('lowercase', function(?string &$email): bool {

				if($email !== NULL) {
					$email = mb_strtolower($email);
				}

				return TRUE;

			});

		$l->property('firstName')->required();
		$l->property('lastName')->required();

		// Repris de `Enrollment::properties()` : « Aucune, à titre personnel » poste une chaîne vide,
		// rien de coché ne poste rien, et le cast ferait des deux le même élément vide.
		$l->property('structure')
			->addBeforeCast('chosen', fn(mixed $structure): bool => $structure !== NULL)
			->addAfterCheck('owned', function(\structure\Structure $eStructure): bool {

				if($eStructure->empty()) {
					return TRUE;
				}

				return EnrollmentLib::getStructures()->offsetExists($eStructure['id']);

			});

		// Les cases de l'étape 2 postent `commitment[]` : `presence` et `cancel` toujours,
		// `conditions` quand le réseau en a écrit. Les refus tournent avant le cast, où la valeur est
		// encore le tableau posté ; le cast fait ensuite de la valeur l'heure de l'engagement, que
		// le schéma juge comme un `datetime`. Rien de coché ne poste rien : `$value` vaut alors
		// `NULL`, et le refus s'applique.
		$l->property('committedAt')
			->replaceArgument(fn() => 'commitment')
			->addBeforeCast('commitment', function(mixed $value): bool {

				$commitments = is_array($value) ? $value : [];

				return (
					in_array('presence', $commitments, TRUE) and
					in_array('cancel', $commitments, TRUE)
				);

			})
			->replaceCast(function(mixed &$value): bool {

				$value = \Clock::datetime();

				return TRUE;

			});

		// La case des conditions est une pseudo-propriété et non une étape de `committedAt` : `build()`
		// arrête la chaîne d'une propriété à son premier refus, et les deux refus de l'étape 2 doivent
		// paraître ensemble quand rien n'est coché. Son nom est aussi celui du `data-wrapper` sous
		// lequel le refus se pose. N'étant pas au modèle, elle n'écrit rien sur l'élément.
		$l->property('conditions')
			->replaceArgument(fn() => 'commitment')
			->addBeforeCast('accepted', function(mixed $value): bool {

				$commitments = is_array($value) ? $value : [];

				return (
					$this->hasConditions() === FALSE or
					in_array('conditions', $commitments, TRUE)
				);

			});

	}

	/**
	 * Les cases que l'étape 2 demande de cocher, dans l'ordre du formulaire. L'étape 3 les reporte
	 * telles quelles, et `doCreate` les revalide avec le reste.
	 */
	public function getCommitments(): array {

		return $this->hasConditions() ? ['presence', 'cancel', 'conditions'] : ['presence', 'cancel'];

	}

	/**
	 * Le réseau a-t-il écrit des conditions que la personne doit accepter ? Le groupe est chargé avec
	 * la ligne par `DateEnrollmentLib::getCreateElement()`, aucune requête ici.
	 */
	public function hasConditions(): bool {

		$this->expects(['eStructureCommunity']);

		return $this['eStructureCommunity']->getCommunity('learningConditions') !== NULL;

	}

}
?>
