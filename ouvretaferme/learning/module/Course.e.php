<?php
namespace learning;

class Course extends CourseElement {

	public static function getSelection(): array {

		return self::getSelectionWithCommunity() + [
			'theme' => ['name', 'icon', 'color'],
			'subtheme' => ['name'],
			'level' => ['name', 'position'],
		];

	}

	/**
	 * La formation que ses fils chargent avec eux : ses colonnes et son groupe, que les droits et
	 * l'état lisent. La thématique, la sous-thématique et le niveau restent des éléments à id seul :
	 * une formation lue à travers un fils ne montre jamais son classement, qui ne se donne à voir que
	 * sur la formation elle-même.
	 */
	public static function getSelectionWithCommunity(): array {

		return parent::getSelection() + [
			'community' => ['name', 'type', 'communityType', 'legalEmail'],
		];

	}

	public function hasObjectives(): bool {

		$this->expects(['objectives']);

		return ($this['objectives'] !== NULL);

	}

	public function isDone(): bool {

		$this->expects(['done', 'lessons']);

		return (
			$this['lessons'] > 0 and
			$this['done'] >= $this['lessons']
		);

	}

	/**
	 * L'attestation de suivi (F69) se délivre au compte inscrit qui a suivi toutes les leçons. Une
	 * seule condition pour le bouton de la fiche et pour la route qui rend le PDF : le bouton paraît
	 * exactement quand la route délivre, y compris sur une formation datée dont l'inscription à la
	 * date a été annulée après coup.
	 */
	public function acceptAttestation(Enrollment $eEnrollment): bool {

		return (
			$eEnrollment->notEmpty() and
			$this->isDone()
		);

	}

	public function isTeam(): bool {

		$this->expects(['community']);

		return $this['community']->empty();

	}

	/**
	 * Une formation vit tant que la structure qui l'écrit est ouverte aux formations
	 * (`Structure::acceptLearning()`). Celles de l'équipe n'ont pas de structure : elles suivent
	 * l'ouverture à tous. Lue par `acceptRead()` et `acceptWrite()`, où tous les modules de la
	 * formation et l'API la retrouvent, et par les pages qui la lisent avant tout droit.
	 */
	public function acceptLearning(): bool {

		$this->expects(['community']);

		if($this->isTeam()) {
			return LearningSetting::$active;
		}

		return $this['community']->acceptLearning();

	}

	/**
	 * Une formation écrite par un réseau, quel que soit son type : seule à pouvoir se tenir en
	 * présentiel ou en visio, et à parler des structures du réseau plutôt que des lecteurs du groupe.
	 */
	public function isNetwork(): bool {

		$this->expects(['community']);

		return (
			$this->isTeam() === FALSE and
			$this['community']->isNetwork()
		);

	}

	/**
	 * Une formation de réseau en présentiel ou en visio se tient à des dates (`Date::acceptWrite()`) ;
	 * en ligne, elle se suit à son rythme et n'en porte aucune. Ne pas s'en servir pour savoir si la
	 * formation est de réseau : c'est `isNetwork()`.
	 */
	public function isDated(): bool {

		$this->expects(['type']);

		return (
			$this->isNetwork() and
			$this['type'] !== Course::ONLINE
		);

	}

	/**
	 * La séance qu'une date neuve propose (lot date-mixte) : dans le mode de la formation, en
	 * présentiel pour une formation mixte, la plus courante.
	 */
	public function getDefaultSequence(): DateSequence {

		$this->expects(['type']);

		return new DateSequence([
			'startAt' => NULL,
			'endAt' => NULL,
			'mode' => match($this['type']) {
				Course::ONSITE, Course::MIXED => DateSequence::ONSITE,
				Course::REMOTE => DateSequence::REMOTE,
			},
			'place' => NULL
		]);

	}

	/**
	 * La pause se dit au public sous la condition de son interrupteur : une formation datée sans date
	 * à venir n'a rien à fermer, et sa pause attend en base la prochaine date sans s'afficher.
	 */
	public function isEnrollmentPauseShown(): bool {

		return (
			$this->isEnrollmentPaused() and
			$this->acceptUpdateEnrollmentPaused()
		);

	}

	/**
	 * L'inscription se fait sur le site de l'organisme (lot annuaire) : seulement pour une formation
	 * datée, que le réglage ne concerne pas autrement. Une formation passée en ligne ne peut donc pas
	 * rester fermée, même si la colonne le disait encore.
	 */
	public function isExternal(): bool {

		$this->expects(['externalOnly']);

		return (
			$this->isDated() and
			$this['externalOnly']
		);

	}

	/**
	 * Le panneau de l'inscription vit dans l'onglet Organisation, qui n'existe que pour une formation
	 * datée : le POST est tenu de même.
	 */
	public function acceptUpdateExternal(): bool {

		return (
			$this->acceptWrite() and
			$this->isDated()
		);

	}

	/**
	 * Les nouvelles inscriptions de la formation, et de toutes ses dates, sont en pause : celles déjà
	 * reçues continuent, la liste d'attente comprise. C'est la valeur en base, que lit l'interrupteur.
	 * Celui-ci se cache sur une formation datée sans date à venir : une pause qu'il ne montre pas n'a
	 * rien à fermer, et les gestes de la fiche, lien vers le site de l'organisme (`isExternal()`)
	 * compris, suivent donc la pause montrée (`isEnrollmentPauseShown()`).
	 */
	public function isEnrollmentPaused(): bool {

		$this->expects(['enrollmentPaused']);

		return $this['enrollmentPaused'];

	}

	/**
	 * La pause ne se règle que tant que la formation prend des inscriptions : publiée, d'un réseau ou
	 * d'un groupe de formation, hors tutoriel, et, datée, avec au moins une date à venir
	 * (`hasDateUpcoming`, posé par la page). Hors de là l'interrupteur se cache : il n'aurait rien à
	 * arrêter. La valeur reste en base et revient avec la prochaine date.
	 */
	public function acceptUpdateEnrollmentPaused(): bool {

		$this->expects(['community', 'status', 'type']);

		if(
			$this->isTeam() or
			$this->acceptTutorial() or
			$this->acceptWrite() === FALSE or
			$this['status'] !== Course::PUBLISHED
		) {
			return FALSE;
		}

		if($this->isDated()) {
			$this->expects(['hasDateUpcoming']);
			return $this['hasDateUpcoming'];
		}

		return TRUE;

	}

	public function assertUpdateEnrollmentPaused(): bool {

		return (
			$this->acceptUpdateEnrollmentPaused() and
			$this->canUpdate()
		);

	}

	/**
	 * L'équipe Ouvretaferme écrit pour tous, un groupe pour son équipe et pour les promotions à qui
	 * la formation est ouverte, à partir du jour d'ouverture. La fenêtre se lit en dernier : elle
	 * coûte une requête par requête HTTP (mémoïsée par compte), et aucune pour un visiteur.
	 *
	 * La même règle est recopiée pour un autre compte que le compte connecté, et pour une liste de
	 * comptes, par `DateEnrollmentLib::fillReadsCourse()` (e-mails d'annulation) : une branche ajoutée
	 * ici s'y ajoute, et le test `DateEnrollmentLessons` compare les deux réponses cas par cas.
	 *
	 * Une formation de réseau retenue au catalogue public se lit comme une formation de l'équipe : sa
	 * carte y mène tous les visiteurs (lot L14). L'équipe de Ouvretaferme lit aussi celle qu'un réseau
	 * lui propose, pour en juger, et celle qu'elle a refusée.
	 *
	 * Qui s'est inscrit à la formation en ligne d'un réseau la lit, membre ou non : c'est
	 * l'inscription qui ouvre les leçons, et elle s'offre à tous (`acceptPublic()`). L'ouverture aux
	 * membres n'ouvre aucune fenêtre, elle propose la formation (`Share::MEMBERS`).
	 *
	 * Un tutoriel de Ouvretaferme se lit par tous, comme une formation retenue au catalogue
	 * (`acceptTutorial()`). Le brouillon reste fermé par `acceptRead()`, comme celui de toute formation.
	 */
	public function canRead(): bool {

		$this->expects(['community', 'catalog']);

		return (
			$this->isTeam() or
			$this['catalog'] === Course::ACCEPTED or
			$this->acceptTutorial() or
			$this->canWrite() or
			($this['catalog'] !== Course::PRIVATE and $this->canDecide()) or
			$this->isOpenToOnline() or
			$this->isConfirmedTrainee() or
			$this->isEnrolledOnline()
		);

	}

	/**
	 * L'équipe de Ouvretaferme juge les demandes de mise au catalogue public, et retire une formation
	 * qu'elle y a retenue. `canRead()` lui ouvre aussi la formation dès qu'elle lui est proposée, sans
	 * qu'elle soit de l'équipe du réseau : elle doit la lire pour en juger.
	 */
	public function canDecide(): bool {
		return self::isDecider(\user\ConnectionLib::getOnline());
	}

	/**
	 * `canDecide()` posé pour un compte quelconque : la liste des inscrits d'une date le pose pour
	 * chacun de ses comptes (`DateEnrollmentLib::fillReadsCourse()`), et les deux doivent dire la même
	 * chose.
	 */
	public static function isDecider(\user\User $eUser): bool {
		return $eUser->isAdmin();
	}

	/**
	 * Une personne confirmée à l'une des dates d'une formation datée la lit comme un membre, même
	 * hors du réseau : c'est le sens du rattachement d'une inscription faite sans compte (lot L10c,
	 * arbitrage 3 a ; le retirer ramène `canRead()` à ses trois droits).
	 * Lue en dernier, après que les autres droits ont échoué, et jamais pour une formation en ligne,
	 * qui n'a pas de date : une requête, et aucune pour un visiteur.
	 */
	private function isConfirmedTrainee(): bool {

		$this->expects(['type']);

		return (
			$this->isDated() and
			DateEnrollmentLib::hasConfirmed(\user\ConnectionLib::getOnline(), $this)
		);

	}

	/**
	 * Bornée à la formation en ligne d'un réseau : une promotion qu'un groupe retire reprend la
	 * lecture à ses inscrits, et une formation datée la tient de la date confirmée
	 * (`isConfirmedTrainee()`), que l'annulation reprend aussi. Lue en dernier : une requête, et
	 * aucune pour un visiteur.
	 */
	private function isEnrolledOnline(): bool {

		$this->expects(['type']);

		return (
			$this->isNetwork() and
			$this->isDated() === FALSE and
			EnrollmentLib::isEnrolled(\user\ConnectionLib::getOnline(), $this)
		);

	}

	private function isOpenToOnline(): bool {

		$eShare = ShareLib::getWindowByUserAndCourse(\user\ConnectionLib::getOnline(), $this);

		return (
			$eShare->notEmpty() and
			$eShare->isOpen()
		);

	}

	/**
	 * Cocher une leçon, ou la décocher. Après l'échéance de sa fenêtre, un stagiaire relit la
	 * formation sans plus rien y cocher ; qui l'écrit n'a pas de fenêtre à respecter, et une
	 * formation lue sans partage (celle de l'équipe, ou d'un groupe par son équipe) n'en a pas.
	 * Celle d'un réseau non plus : l'échéance est celle d'une promotion, et un réseau n'en a pas.
	 * La règle est écrite ici une fois, et sert la lecture comme les gestes.
	 */
	public function canProgress(): bool {

		$this->expects(['community']);

		if(
			$this->isTeam() or
			$this->isNetwork() or
			$this->canWrite()
		) {
			return TRUE;
		}

		$eShare = ShareLib::getWindowByUserAndCourse(\user\ConnectionLib::getOnline(), $this);

		return (
			$eShare->empty() or
			$eShare->acceptProgress()
		);

	}

	/**
	 * Marquer une leçon suivie, ou la décocher : le droit de `doDone` et de `doUndo`, et de répondre
	 * au quiz qui la valide (`doAnswer`, `retry`). Il faut une inscription à la formation, celle que
	 * le geste écrit, et une progression encore ouverte (`canProgress()`). La colonne de la page d'une
	 * leçon n'ouvre une leçon vide qu'à ce lecteur : il ne la lit pas, il la coche.
	 */
	public function canFollow(Enrollment $eEnrollment): bool {

		return (
			$eEnrollment->notEmpty() and
			$this->canProgress()
		);

	}

	/**
	 * Les leçons demandent un compte, sauf en lecture libre (Q01) : la fiche, elle, se lit sans.
	 * `open` est un droit et non un état : l'auteur y choisit qui lit, et la règle pèse le lecteur
	 * connecté, qu'un `accept*` ne regarde jamais. `catalog` ne dit plus que la place au catalogue.
	 * Les leçons d'un tutoriel se lisent sans compte, quel que soit `open` : elles sont faites pour
	 * qui n'en a pas encore.
	 */
	public function canReadLessons(): bool {

		$this->expects(['open']);

		return (
			$this->canRead() and
			(
				$this['open'] or
				$this->acceptTutorial() or
				\user\ConnectionLib::isLogged()
			)
		);

	}

	public function canWrite(): bool {

		$this->expects(['community']);

		if($this->isTeam()) {
			return \user\ConnectionLib::getOnline()->isAdmin();
		}

		return $this['community']->getMember()->isStaff();

	}

	/**
	 * Une formation du réseau des tutoriels (`LearningSetting::$structureTutorial`). L'état seul :
	 * le statut se juge à côté, par `acceptRead()`, pour un tutoriel comme pour toute formation.
	 */
	public function acceptTutorial(): bool {

		$this->expects(['community']);

		return (
			$this->isTeam() === FALSE and
			$this['community']['id'] === LearningSetting::$structureTutorial
		);

	}

	public function acceptRead(): bool {

		$this->expects(['status']);

		return (
			$this->acceptLearning() and
			$this['status'] === Course::PUBLISHED
		);

	}

	/**
	 * La formation parait sur la page publique de son réseau et s'offre à l'inscription de tous,
	 * comme une date (`Date::acceptPublic()`) : en ligne, d'un réseau, publiée. Publier suffit, le
	 * réseau n'a pas à l'ouvrir à ses membres (Vincent, 09/10). Sa fiche se lit alors sans compte
	 * (`assertPublicRead()`), ses leçons seulement une fois inscrit (`canRead()`). La requête qui
	 * les liste est `CourseLib::getPublicOnlineByCommunity()`.
	 */
	public function acceptPublic(): bool {

		$this->expects(['type']);

		return (
			$this->acceptRead() and
			$this->isNetwork() and
			$this->isDated() === FALSE
		);

	}

	/**
	 * La personne doit s'inscrire avant de lire : la formation s'offre à l'inscription et aucun droit
	 * ne la lui ouvre encore. Son geste est alors « S'inscrire », qui ramène à la fiche, et non
	 * « Commencer la formation », qui ouvre une leçon ; qui la lit déjà (l'équipe du réseau, le
	 * catalogue public, un partage) garde le second. La fiche, le compte créé sur place et
	 * l'inscription posent la même question, cette dernière avant d'écrire : l'inscription faite, la
	 * personne lit.
	 */
	public function isEnrollFirst(): bool {

		return (
			$this->acceptPublic() and
			$this->canRead() === FALSE
		);

	}

	/**
	 * L'inscription en ligne peut partir en demande : le réseau confirme chaque inscription, ou celles
	 * qui demandent une prise en charge (`onlineConfirm`, posé par la page). Le panneau d'inscription
	 * se montre alors avant tout envoi, pour dire qui confirme ou poser la question.
	 */
	public function acceptEnrollmentRequest(): bool {

		$this->expects(['onlineConfirm']);

		return in_array($this['onlineConfirm'], [\structure\Community::FUNDING, \structure\Community::ALWAYS], TRUE);

	}

	/**
	 * Au catalogue public, la condition de `CourseLib::getCatalog()` : c'est là, et seulement là, que
	 * les avis sont publiés (lot L15c) ; hors catalogue, seul le réseau les lit.
	 */
	public function isInCatalog(): bool {

		$this->expects(['catalog', 'status']);

		return (
			$this['catalog'] === Course::ACCEPTED and
			$this['status'] === Course::PUBLISHED
		);

	}

	/**
	 * Une ferme n'écrit pas de formation : seuls l'équipe, un groupe de formation et un réseau en
	 * portent. Explicite, sans quoi l'écriture retomberait sur `acceptRead()` et fermerait les
	 * brouillons à leur auteur.
	 */
	public function acceptWrite(): bool {

		$this->expects(['community']);

		return (
			$this->acceptLearning() and (
				$this->isTeam() or
				$this['community']->isEducation() or
				$this['community']->isNetwork()
			)
		);

	}

	/**
	 * Une formation s'ouvre à des promotions ou aux membres d'un réseau quand elle est d'un groupe et
	 * publiée : un partage posé sur un brouillon serait un état que personne ne voit. Un état et non
	 * un droit, que le panneau et son action valident tous deux, un POST forgé contournant l'écran.
	 */
	public function acceptShare(): bool {

		$this->expects(['community', 'status']);

		return (
			$this->isTeam() === FALSE and
			$this->acceptWrite() and
			$this['status'] === Course::PUBLISHED
		);

	}

	/**
	 * Une formation qu'on peut ouvrir et qui a quelqu'un à qui s'ouvrir : seule condition sous
	 * laquelle un écran offre l'ouverture, le bouton comme le lien de « Pas encore ouverte », et
	 * sous laquelle `doShare` l'enregistre.
	 * Un réseau l'ouvre à ses membres hors de l'équipe (`members`, posé par la page par
	 * `ShareLib::countMembers()`) : celui dont l'équipe est seule membre n'a personne à prévenir.
	 * Un groupe l'ouvre à ses promotions (`sections`, son compteur), closes comprises : un groupe
	 * qui n'a plus que celles-là ouvre un panneau qui le dit, et où l'on retire encore un partage
	 * existant. Le panneau ouvert par son adresse reste sous `acceptShare()` seul, et dit lui-même
	 * qu'il n'a rien à proposer.
	 */
	public function acceptShareAudience(): bool {

		if($this->acceptShare() === FALSE) {
			return FALSE;
		}

		if($this->isNetwork()) {
			$this->expects(['members']);
			return ($this['members'] > 0);
		}

		$this->expects(['sections']);
		return ($this['sections'] > 0);

	}

	/**
	 * Le suivi des stagiaires n'existe que pour une formation de groupe, seule à s'ouvrir à des
	 * promotions. Il ne demande pas la publication : une formation dépubliée garde ses partages et
	 * la progression de ses stagiaires, que l'équipe continue de relire et de constater.
	 */
	public function acceptTrack(): bool {

		$this->expects(['community']);

		return (
			$this->isTeam() === FALSE and
			$this->acceptWrite()
		);

	}

	/**
	 * Les trois accès au suivi (le nombre de lecteurs d'une liste ou de la ligne sous le titre de
	 * l'éditeur, l'entrée de la roue) : qui peut ouvrir le suivi, sur une formation déjà ouverte à
	 * quelqu'un. La route ne demande pas de partage, parce qu'une formation dont on retire le
	 * dernier partage garde ses stagiaires ; mais un lien vers un suivi qui n'a encore personne à
	 * montrer n'apprend rien. `acceptTrack()` passe en premier : une formation de l'équipe, qui n'a
	 * pas de suivi, ne reçoit pas de `shares` de sa page. Elle décide de l'affichage du lien et ne
	 * doit jamais garder la route, dont la paire reste `canUpdate` et `acceptTrack`.
	 *
	 * Un réseau n'attend aucun partage : ses stagiaires s'inscrivent d'eux-mêmes, le partage ne dit
	 * donc rien de qui le suivi peut montrer.
	 */
	public function assertTrack(): bool {

		if($this->acceptTrack() === FALSE) {
			return FALSE;
		}

		if($this->isNetwork()) {
			return $this->canUpdate();
		}

		$this->expects(['shares']);

		return (
			$this['shares'] > 0 and
			$this->canUpdate()
		);

	}

	/**
	 * Le journal de lecture (lot L8c) ne se tient que pour une formation de groupe ou de réseau,
	 * encadrée par une équipe qui le relit : une formation de l'équipe Ouvretaferme n'a personne pour
	 * le lire (arbitrage « Portée »). Un état et non un droit : la page de la leçon, le signal et le
	 * panneau « Mon suivi » posent la même question.
	 */
	public function acceptReading(): bool {

		$this->expects(['community']);

		return ($this->isTeam() === FALSE);

	}

	/**
	 * Le Bilan (lot L7 du chantier api), à l'écran comme par l'API, ne se tient que pour une
	 * formation en ligne : ses comptes lisent les inscriptions et la progression en ligne, qu'une
	 * formation à dates ne connaît pas, ses stagiaires s'inscrivant à chaque date.
	 */
	public function acceptUsage(): bool {

		return ($this->isDated() === FALSE);

	}

	/**
	 * L'onglet Diffusion d'une formation (lot L14, page à part jusqu'au 03/10) : seul un réseau demande le catalogue pour
	 * ses formations. Celles de l'équipe y sont d'office, celles d'un groupe de formation servent ses
	 * promotions et n'y entrent pas.
	 *
	 * Le circuit se garde en deux questions, selon la règle du dépôt : le droit par `canUpdate` pour le
	 * réseau et par `canDecide` pour l'équipe, l'état par les `acceptCatalog*` ci-dessous, un par
	 * geste. `acceptUpdate` n'en porte aucun : il garde l'éditeur entier, que le circuit ne ferme pas.
	 *
	 * Le catalogue public ne s'ouvre qu'avec l'ouverture à tous (`LearningSetting::$active`) : une
	 * structure ouverte seule n'y propose rien, et n'a donc pas cette page.
	 */
	public function acceptCatalog(): bool {

		$this->expects(['community']);

		return (
			LearningSetting::$active and
			$this->isNetwork() and
			$this->acceptWrite()
		);

	}

	/**
	 * Le réseau demande la mise au catalogue d'une formation qui n'y est pas, ou la redemande après un
	 * refus. La publication, qui est aussi un état, est tenue par l'étape `Course::catalog.published`
	 * pour que le refus se dise avec son message.
	 */
	public function acceptCatalogRequest(): bool {

		$this->expects(['catalog']);

		return (
			$this->acceptCatalog() and
			in_array($this['catalog'], [Course::PRIVATE, Course::REFUSED], TRUE)
		);

	}

	/**
	 * Le réseau retire sa demande, ou sa formation du catalogue : l'une et l'autre la rendent privée.
	 */
	public function acceptCatalogWithdraw(): bool {

		$this->expects(['catalog']);

		return (
			$this->acceptCatalog() and
			in_array($this['catalog'], [Course::PROPOSED, Course::ACCEPTED], TRUE)
		);

	}

	/**
	 * L'offre d'une formation en ligne se choisit dans son onglet Diffusion (lot L15a). Une
	 * formation datée n'en a pas : chaque date porte la sienne (`Date::acceptUpdateOffer()`).
	 */
	public function acceptUpdateOffer(): bool {

		$this->expects(['type']);

		return (
			$this->acceptCatalog() and
			$this['type'] === Course::ONLINE
		);

	}

	/**
	 * Les deux offres du catalogue public ne s'ouvrent qu'avec l'ouverture à tous (lot ouverture-6659,
	 * décision de Vincent du 2026-09-29) : pour une structure ouverte par `LearningSetting::STRUCTURE_BETA`,
	 * le paiement en ligne n'encaisse rien (lot L15b suspendu). L'étape `offer.paid` de `Course` et de
	 * `Date` tient le POST, l'écran ferme les deux options avec la même raison. Aucune inscription ne
	 * fige l'offre d'une date tant qu'elles restent fermées (`Date::acceptUpdateOffer()`).
	 */
	public function acceptPaidOffer(): bool {
		return LearningSetting::$active;
	}

	/**
	 * Le prix et le financement d'une formation de réseau, sur la page de la formation en ligne comme
	 * sur celle d'une formation datée, que ses dates reprennent tant qu'elles n'ont pas les leurs.
	 * L'équipe et un groupe de formation n'en affichent pas.
	 */
	public function acceptUpdatePrice(): bool {

		return (
			$this->acceptWrite() and
			$this->isNetwork()
		);

	}

	/**
	 * Le paiement en ligne encaisse le montant « Autre public », et seulement un montant non nul : le
	 * public financé règle hors ligne avec l'organisme, et une formation gratuite n'a rien à encaisser.
	 */
	public function acceptTraineeOffer(): bool {

		$price = $this->getPrice();

		return (
			$price !== NULL and
			$price > 0
		);

	}

	/**
	 * Mêmes accesseurs que sur `Date`, qui y lit sa valeur et sinon celle de sa formation : une même
	 * présentation lit l'une ou l'autre.
	 */
	public function getPriceFunded(): ?float {

		$this->expects(['priceFunded']);

		return $this['priceFunded'];

	}

	public function getPrice(): ?float {

		$this->expects(['price']);

		return $this['price'];

	}

	/**
	 * Gratuite pour tous, ce que « Formation payante » à non écrit (`Course::paid`) : la fiche le dit
	 * sur une ligne, « Prix · Gratuit ». Même accesseur sur `Date`.
	 */
	public function isFree(): bool {

		return (
			$this->getPriceFunded() === 0.0 and
			$this->getPrice() === 0.0
		);

	}

	/**
	 * La réponse que le panneau du prix coche à son ouverture : NULL sans aucun montant, oui dès qu'un
	 * montant n'est pas nul.
	 */
	public function isPaid(): ?bool {

		$amounts = array_filter([$this->getPriceFunded(), $this->getPrice()], fn(?float $amount) => $amount !== NULL);

		if($amounts === []) {
			return NULL;
		}

		return (max($amounts) > 0);

	}

	public function getFunding(): ?\Set {

		$this->expects(['funding']);

		return $this['funding'];

	}

	public function getFundingNote(): ?string {

		$this->expects(['fundingNote']);

		return $this['fundingNote'];

	}

	/** L'équipe accepte ou refuse une demande, et seulement une demande en cours. */
	public function acceptCatalogDecide(): bool {

		$this->expects(['catalog']);

		return (
			$this->acceptCatalog() and
			$this['catalog'] === Course::PROPOSED
		);

	}

	/** L'équipe retire du catalogue une formation de réseau qu'elle y avait retenue. */
	public function acceptCatalogRemove(): bool {

		$this->expects(['catalog']);

		return (
			$this->acceptCatalog() and
			$this['catalog'] === Course::ACCEPTED
		);

	}

	/**
	 * L'équipe choisit pour « Premiers pas » une formation qui se lit au catalogue, de l'équipe comme
	 * d'un réseau. L'interrupteur des formations est lu à l'entrée de l'administration du catalogue,
	 * seule à porter ces gestes.
	 */
	public function acceptFeature(): bool {

		$this->expects(['featured']);

		return (
			$this->isInCatalog() and
			$this['featured'] === FALSE
		);

	}

	public function acceptUnfeature(): bool {

		$this->expects(['featured']);

		return $this['featured'];

	}

	/**
	 * Qui peut écrire une formation la lit dans tous ses états : l'auteur relit son brouillon.
	 */
	public function assertRead(): bool {

		return (
			$this->assertWrite() or
			parent::assertRead()
		);

	}

	/**
	 * La fiche d'une formation qui s'offre à l'inscription se lit par tous, visiteur compris : c'est
	 * d'elle qu'on s'inscrit. Les pages de la fiche, de l'inscription et du compte créé sur place
	 * posent cette question, les leçons gardent `assertReadLessons()`.
	 */
	public function assertPublicRead(): bool {

		return (
			$this->assertRead() or
			$this->acceptPublic()
		);

	}

	/**
	 * Mêmes lecteurs que la fiche, avec pour les leçons le compte que demande `canReadLessons()`.
	 */
	public function assertReadLessons(): bool {

		return (
			$this->assertWrite() or
			(
				$this->acceptRead() and
				$this->canReadLessons()
			)
		);

	}

	public static function properties(\PropertyList $l): void {

		/**
		 * Le type se choisit à la création, et seul un réseau tient des formations en présentiel ou
		 * en visio : une école ou l'équipe n'en écrivent qu'en ligne, et un client de l'API qui ne le
		 * dit pas crée une formation en ligne, comme avant que le type n'existe.
		 *
		 * Il se change encore tant que la formation n'a aucune date, annulées comprises : une date se
		 * tient sur place ou en visio selon le type de sa formation, qui ne peut plus basculer sous
		 * elle. Les réglages d'une formation datée ne postent plus le champ, et une valeur absente
		 * garde alors le type de la base ; `dated` refuse un autre type, posté par un panneau ouvert
		 * avant la première date.
		 */
		$l->property('type')
			->addAfterCast('online', function(?string &$type): bool {
				$type ??= $this->exists() ? $this['type'] : Course::ONLINE;
				return TRUE;
			})
			// « Présentiel et visio » se calcule sur les séances des dates (`CourseLib::synchronizeType()`)
			// et ne se choisit pas : il ne passe que reposté tel qu'il est en base.
			->addAfterCheck('chosen', fn(string $type): bool => (
				$type !== Course::MIXED or
				($this->exists() and $type === $this['type'])
			))
			->addAfterCheck('network', fn(string $type): bool => (
				$type === Course::ONLINE or
				$this->isNetwork()
			))
			->addAfterCheck('dated', fn(string $type): bool => (
				$this->exists() === FALSE or
				$type === $this['type'] or
				DateLib::existsByCourse($this) === FALSE
			))
			// Le pendant de `status.program` : une formation datée publiée sans leçon, passée en ligne,
			// serait une formation en ligne publiée sans leçon.
			->addAfterCheck('program', fn(string $type): bool => (
				$this->exists() === FALSE or
				$type !== Course::ONLINE or
				$type === $this['type'] or
				$this['status'] !== Course::PUBLISHED or
				Lesson::model()->whereCourse($this)->exists()
			));

		/**
		 * L'offre d'une formation en ligne, que `Date` reprend à l'identique pour chaque date d'une
		 * formation datée (lot L15a). « Payée par le stagiaire » exige un compte de reversement actif du
		 * réseau, sur lequel le paiement arrive, et un montant « Autre public » non nul, le seul que le
		 * paiement en ligne encaisse. L'écran verrouille l'option avec sa raison, ces étapes tiennent le
		 * POST.
		 *
		 * Seul le passage à une offre se refuse : une offre déjà choisie reste choisie quand le compte se
		 * dégrade après coup. `$this['offer']` porte encore la valeur de la base quand
		 * l'étape tourne, `build()` n'écrivant une propriété qu'après ses contrôles. Le montant, lui, ne
		 * se dégrade pas sous l'offre : `price.trainee` le refuse.
		 *
		 * Tant que les offres payantes sont fermées (`acceptPaidOffer()`), elles se refusent même déjà
		 * choisies : aucune formation ne doit annoncer un paiement qu'on n'encaisse pas.
		 *
		 * Une formation au catalogue public, ou qui l'a demandé, ne repasse pas sans offre : elle se
		 * retire d'abord du catalogue, pour que le catalogue ne porte jamais une formation qu'on ne
		 * peut pas suivre.
		 */
		$l->property('offer')
			->addAfterCheck('paid', fn(string $offer): bool => (
				$offer === Course::NONE or
				$this->acceptPaidOffer()
			))
			->addAfterCheck('account', fn(string $offer): bool => (
				$offer !== Course::TRAINEE or
				$offer === $this['offer'] or
				CommunityLib::hasPayoutAccount($this['community'])
			))
			->addAfterCheck('priced', fn(string $offer): bool => (
				$offer !== Course::TRAINEE or
				$this->acceptTraineeOffer()
			))
			->addAfterCheck('catalog', fn(string $offer): bool => (
				$offer !== Course::NONE or
				in_array($this['catalog'], [Course::PRIVATE, Course::REFUSED], TRUE)
			));

		/**
		 * « Formation payante » oui / non (choix 3 A du lot glacons-parcours), pseudo-propriété que le
		 * panneau du prix construit avant les quatre champs qu'elle gouverne. Sur non, leurs étapes
		 * `free` posent 0, 0, NULL et NULL quoi que postent les champs masqués par `field-toggle` : la
		 * règle vaut pour tout appelant qui la poste : l'API construit à leur tour ceux que son corps
		 * ne nomme pas (`Course_0_1betaApi`), et `CourseLib::update()` les écrit tous. Non postée (le
		 * panneau sous paiement en ligne par le stagiaire, qui ne pose pas la question, ou un client de
		 * l'API qui ne l'envoie pas), elle reste NULL et ne change rien.
		 *
		 * Non se refuse sous le paiement en ligne qui encaisse « Autre public », comme
		 * `price.trainee` : le refus se pose sous la question, les montants étant masqués, et les
		 * quatre champs ne se construisent pas.
		 */
		$l->property('paid')
			->replaceCast(function(mixed &$paid): bool {
				$paid = ($paid === NULL or $paid === '') ? NULL : (bool)$paid;
				return TRUE;
			})
			->addAfterCast('trainee', fn(?bool $paid): bool => (
				$paid !== FALSE or
				(
					$this['offer'] !== Course::TRAINEE and
					DateLib::existsTraineeInheriting($this) === FALSE
				)
			))
			->replaceSet(function(?bool $paid): void {
				$this['paid'] = $paid;
			});

		foreach(['priceFunded' => 0.0, 'price' => 0.0, 'funding' => NULL, 'fundingNote' => NULL] as $property => $free) {

			$l->property($property)
				->addBeforeCast('free', function(mixed &$value, \PropertyList $l) use($free): bool {

					if($l->isInvalid('paid')) {
						throw new \PropertySkip();
					}

					if(
						$l->isBuilt('paid') and
						$this['paid'] === FALSE
					) {
						$value = $free;
					}

					return TRUE;

				});

		}

		/**
		 * Oui demande au moins un montant ; `priceFunded` est construit avant `price` et se lit déjà à
		 * sa nouvelle valeur.
		 *
		 * « Autre public » ne se vide ni ne passe à 0 sous le paiement en ligne qui l'encaisse : sur la
		 * formation en ligne qui le propose, et sur une formation datée dont une date planifiée à venir
		 * le propose en reprenant ce montant. Une date passée ou annulée n'encaisse plus rien.
		 * `offer` n'est pas postée avec le prix et se lit à sa valeur de la base.
		 */
		$l->property('price')
			->addAfterCheck('amount', fn(?float $price, \PropertyList $l): bool => (
				$l->isBuilt('paid') === FALSE or
				$this['paid'] !== TRUE or
				$price !== NULL or
				$this['priceFunded'] !== NULL
			))
			->addAfterCheck('trainee', function(?float $price): bool {

				if(
					$price !== NULL and
					$price > 0
				) {
					return TRUE;
				}

				return (
					$this['offer'] !== Course::TRAINEE and
					DateLib::existsTraineeInheriting($this) === FALSE
				);

			});

		/**
		 * Aucune case cochée ne poste rien, et la propriété vaut alors NULL. Un POST forgé `funding=0`
		 * poserait un ensemble vide, qui sur une date voudrait dire « ne reprend aucun des financeurs de
		 * la formation » : un retrait que l'écran ne propose pas (décision 5 A du 03/10, une date
		 * surcharge une valeur et n'en retire aucune). L'étape le ramène à NULL sur les deux entités.
		 */
		$l->property('funding')
			->addAfterCast('none', function(?\Set &$funding): bool {
				if($funding !== NULL and $funding->get() === 0) {
					$funding = NULL;
				}
				return TRUE;
			});

		/**
		 * Fermer l'inscription (lot annuaire) ne vaut que pour les nouvelles inscriptions, et renvoie à
		 * l'adresse de chaque date, sinon à celle de la formation (`externalUrl.uncovered`).
		 * `externalOnly` est construit avant `externalUrl` et se lit donc déjà à sa nouvelle valeur.
		 *
		 * Rouvrir une formation au catalogue public lui rend l'exigence d'une offre, que fermée elle
		 * n'avait pas (`CourseLib::isOffered()`) : sans date à venir qui en porte une, elle se retire
		 * d'abord du catalogue, comme une formation qu'on passe sans offre (`offer.catalog`). Seul le
		 * passage de fermée à ouverte se refuse : une date qui passe d'elle-même ne bloque pas le
		 * réseau qui change l'adresse d'une formation déjà ouverte. L'étape ne passe pas par
		 * `isOffered()` : `externalOnly` y vaut encore la valeur de la base, `build()` n'écrivant une
		 * propriété qu'après ses contrôles, et la formation y serait toujours proposée.
		 */
		$l->property('externalOnly')
			->addAfterCheck('catalog', fn(bool $externalOnly): bool => (
				$externalOnly or
				$this['externalOnly'] === FALSE or
				in_array($this['catalog'], [Course::PRIVATE, Course::REFUSED], TRUE) or
				DateLib::existsOfferedByCourse($this)
			));

		/**
		 * La page de la formation n'est exigée que si une de ses dates à venir n'a pas la sienne (Vincent,
		 * 01/10/2026, « une des deux suffit ») : c'est là que le visiteur de cette date est envoyé
		 * (`Date::getExternalUrl()`). Sans date à venir, aucune date n'a sa page, et la formation garde
		 * la sienne : la fiche la pose alors en tête. Une date annulée ou passée ne reçoit plus personne
		 * et ne compte pas, sinon une date tenue sans page, qui ne se modifie plus, obligerait à jamais.
		 * La date neuve ou modifiée qui n'a pas sa page est tenue de son côté (`Date::externalUrl.uncovered`).
		 *
		 * La page n'a de sens qu'avec une inscription fermée sur Ouvretaferme : `unlinked` l'écarte avec
		 * toute réponse non, quel que soit l'appelant, avant que le schéma juge une valeur qui ne sera pas
		 * gardée. `externalOnly` est construite avant `externalUrl` et se lit donc déjà à sa nouvelle valeur.
		 */
		$l->property('externalUrl')
			->addAfterCast('unlinked', function(?string &$externalUrl): bool {
				if($this['externalOnly'] === FALSE) {
					$externalUrl = NULL;
				}
				return TRUE;
			})
			->addAfterCheck('uncovered', fn(?string $externalUrl): bool => (
				$externalUrl !== NULL or
				$this['externalOnly'] === FALSE or
				DateLib::hasExternalUrlOnEachUpcoming($this)
			));

		/**
		 * `catalog` n'est jamais posté : chaque geste du circuit (lot L14) le construit avec l'état qu'il
		 * vise, pour que ses étapes tournent comme sur tout formulaire. Une formation se propose au
		 * catalogue une fois publiée : un brouillon n'aurait rien à y montrer.
		 */
		$l->property('catalog')
			->addAfterCheck('published', fn(string $catalog): bool => (
				$catalog !== Course::PROPOSED or
				$this['status'] === Course::PUBLISHED
			))
			// Une formation sans offre n'a rien à proposer au visiteur du catalogue : ni prix, ni
			// personne qui paie son inscription (lot L15a).
			->addAfterCheck('offer', fn(string $catalog): bool => (
				$catalog !== Course::PROPOSED or
				CourseLib::isOffered($this)
			))
			// Dépublier ne retire pas la demande : le réseau peut repasser sa formation en brouillon
			// pendant qu'elle attend, et l'équipe ne doit pas faire entrer un brouillon au catalogue.
			->addAfterCheck('draft', fn(string $catalog): bool => (
				$catalog !== Course::ACCEPTED or
				$this['status'] === Course::PUBLISHED
			));

		/**
		 * Le motif n'est construit que par les deux gestes de l'équipe qui en exigent un, le refus d'une
		 * demande et le retrait du catalogue : le réseau le lit sur sa page, et une décision sans motif
		 * ne lui dirait pas quoi corriger. Les gestes du réseau l'effacent sans le construire.
		 */
		$l->property('catalogReason')
			->addAfterCheck('empty', fn(?string $reason): bool => $reason !== NULL);

		/**
		 * Le classement est facultatif à l'écriture et exigé à la publication, dans les deux sens :
		 * `status.classified` refuse de publier une formation incomplète, et `classified` sur les
		 * trois propriétés refuse de la vider tant qu'elle est publiée. Sans le second, on retombe
		 * sur un `Theme` vide côté lecteur.
		 *
		 * Les deux sens lisent l'autre propriété sur `$this` : l'ordre de `getPropertiesUpdate()`
		 * range `summary`, `theme` et `level` avant `status`, sans quoi un corps qui poste tout d'un
		 * coup ferait lire à `status` les valeurs d'avant.
		 */
		$l->property('summary')
			->addAfterCheck('classified', fn(?string $summary): bool => (
				$summary !== NULL or
				$this['status'] !== Course::PUBLISHED
			));

		// Un élément vide est une valeur : le retirer de `exists` rendrait « absent » et
		// « inconnu » indiscernables, la seconde étape ne gardant que la publication.
		$l->property('theme')
			->addAfterCheck('exists', fn(Theme $eTheme): bool => (
				$eTheme->empty() or
				Theme::model()->whereId($eTheme)->exists()
			))
			->addAfterCheck('classified', fn(Theme $eTheme): bool => (
				$eTheme->notEmpty() or
				$this['status'] !== Course::PUBLISHED
			));

		/**
		 * La sous-thématique est facultative, publication comprise, et toujours prise dans la
		 * thématique de la formation : une sous-thématique inconnue, posée sans thématique ou prise dans
		 * une autre tombent toutes ici. `theme` est construite avant ; refusée, elle a son propre
		 * message et n'est pas posée sur l'élément, d'où le passage sans rien juger.
		 */
		$l->property('subtheme')
			->addAfterCheck('consistency', function(Subtheme $eSubtheme, \PropertyList $l): bool {

				// `theme` précède `subtheme` dans chaque liste du Lib : elle ne manque aux propriétés construites
				// que si son contrôle l'a refusée, et n'a alors aucune valeur sûre à comparer. Son erreur suffit.
				if(
					$eSubtheme->empty() or
					$l->isBuilt('theme') === FALSE
				) {
					return TRUE;
				}

				$eSubthemeDb = Subtheme::model()
					->select('theme')
					->whereId($eSubtheme)
					->get();

				return (
					$eSubthemeDb->notEmpty() and
					$eSubthemeDb['theme']->is($this['theme'])
				);

			});

		// Le niveau est facultatif jusqu'au bout, publication comprise : pas d'étape `classified`
		// ici, contrairement à la présentation et à la thématique.
		$l->property('level')
			->addAfterCheck('exists', fn(Level $eLevel): bool => (
				$eLevel->empty() or
				Level::model()->whereId($eLevel)->exists()
			));

		/**
		 * Une formation publiée doit pouvoir se lire : sa présentation et sa thématique, la durée de
		 * chaque leçon, une question à chaque quiz, que le lecteur ne pourrait pas valider sans. En
		 * ligne, il lui faut aussi une leçon (`program`, décision de Vincent du 05/10) : elle n'a rien
		 * d'autre à suivre. En présentiel, elle se tient à ses dates et se publie sans leçon.
		 * L'inventaire que le panneau « Avant de publier » énumère est celui de ces trois étapes, prix
		 * compris, seules à refuser, et le retour de `program` est dans `LessonLib::delete()` et
		 * `ChapterLib::delete()`.
		 *
		 * La citation part de `new QuestionModel()` et se corrèle par l'alias extérieur `m1` : ses
		 * tables prennent le préfixe `s`.
		 */
		$l->property('status')
			->addAfterCheck('classified', function(string $status): bool {

				if($status !== Course::PUBLISHED) {
					return TRUE;
				}

				return (
					$this['summary'] !== NULL and
					$this['theme']->notEmpty() and
					Lesson::model()->whereCourse($this)->where('minutes IS NULL')->exists() === FALSE and
					Lesson::model()
						->whereCourse($this)
						->whereValidation(Lesson::QUIZ)
						->where('NOT '.new QuestionModel()->where('s1.lesson', new \Sql('m1.id'))->existsSql())
						->exists() === FALSE
				);

			})
			->addAfterCheck('program', fn(string $status): bool => (
				$status !== Course::PUBLISHED or
				$this['type'] !== Course::ONLINE or
				Lesson::model()->whereCourse($this)->exists()
			))
			// Le prix d'une formation de réseau est su avant toute diffusion (choix 3 A du lot
			// glacons-parcours) : un montant nul en est un, un financeur seul non. L'inventaire est
			// celui de `CourseLib::getMissingForPublication()`.
			->addAfterCheck('priced', fn(string $status): bool => (
				$status !== Course::PUBLISHED or
				$this->isNetwork() === FALSE or
				$this['priceFunded'] !== NULL or
				$this['price'] !== NULL
			));

	}

}
?>
