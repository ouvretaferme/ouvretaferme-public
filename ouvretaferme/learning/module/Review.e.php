<?php
namespace learning;

/**
 * L'avis d'une personne sur une formation qu'elle a suivie (lot L15c) : une note de 1 à 5, un texte
 * facultatif, et le jour où la formation a été suivie, figé à l'envoi. Au catalogue public, il est
 * publié sur la fiche ; hors catalogue, seul le réseau le lit, dans sa boite des avis (lot
 * glacons-parcours, choix 4 bis B).
 *
 * Un avis par personne et par formation (`UNIQUE [course, user]`), et un par place pour une personne
 * sans compte (`UNIQUE [dateEnrollment]`) : donner son avis une seconde fois modifie le premier
 * (`ReviewLib::create()`).
 *
 * Le droit pèse le compte connecté (`can*`), l'état ne regarde que l'avis et sa formation
 * (`accept*`). Qui voit un avis masqué se décide à la lecture du fil (`ReviewLib::getForCourse()`) ou
 * de la boite (`ReviewLib::getNewByStructure()`), pas ici. `canRead()` existe parce que le cadriciel l'exige
 * d'un élément, et ne dit que la lecture de la formation.
 *
 * Le chemin sans compte (le lien d'une inscription, `public.p.php`) n'a pas de `can*` : le jeton
 * tient lieu de droit, et seuls les `accept*` jouent.
 */
class Review extends ReviewElement {

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
			'user' => ['firstName', 'lastName', 'email', 'visibility', 'vignette'],
			'dateEnrollment' => ['firstName', 'lastName', 'email', 'token', 'user'],
			'reportedBy' => ['firstName', 'lastName'],
			'moderatedBy' => ['firstName', 'lastName'],
			'answeredBy' => ['firstName', 'lastName'],
			'readBy' => ['firstName', 'lastName'],
		];

	}

	public function canRead(): bool {

		$this->expects(['course']);

		return $this['course']->canRead();

	}

	/**
	 * L'auteur est le compte connecté, et il n'écrit pas la formation : le réseau ne publie pas ses
	 * propres étoiles, ni l'équipe sur une formation de l'équipe (arbitrage Q1 de la porte). La mention
	 * de la fiche dit que chaque auteur a suivi la formation, ce qu'un auteur de la formation n'a pas
	 * fait.
	 */
	public function canCreate(): bool {

		$this->expects(['user', 'course']);

		return (
			$this['user']->notEmpty() and
			$this['user']->isOnline() and
			$this['course']->canWrite() === FALSE
		);

	}

	/**
	 * Par le lien d'une inscription, le jeton tient lieu de connexion : le droit se pèse sur le compte
	 * de la place (`writesCourse`, posé par `ReviewLib::getCreateElementByDateEnrollment()`), pas sur le
	 * compte connecté. Le même refus que `canCreate()` (Q1) : un compte qui écrit la formation ne la
	 * note pas. Une place sans compte note, rien ne la rattachant au réseau (V-Learning, 28/09).
	 */
	public function canCreateByLink(): bool {

		$this->expects(['writesCourse']);

		return ($this['writesCourse'] === FALSE);

	}

	public function canUpdate(): bool {

		$this->expects(['user']);

		return (
			$this['user']->notEmpty() and
			$this['user']->isOnline()
		);

	}

	public function canDelete(): bool {
		return $this->canUpdate();
	}

	/**
	 * Répondre et signaler reviennent à qui écrit la formation : l'équipe du réseau, un administrateur
	 * pour une formation de l'équipe (`Course::canWrite()`).
	 */
	public function canAnswer(): bool {

		$this->expects(['course']);

		return $this['course']->canWrite();

	}

	public function canReport(): bool {
		return $this->canAnswer();
	}

	/**
	 * Garder, masquer, réafficher : l'équipe de Ouvretaferme, qui juge aussi le catalogue public.
	 */
	public function canModerate(): bool {

		$this->expects(['course']);

		return $this['course']->canDecide();

	}

	/**
	 * `experiencedAt` est posé par `ReviewLib::getCreateElement()` avant toute validation : `NULL` dit
	 * que la personne n'a pas suivi la formation (dernière leçon en ligne, date passée à place
	 * confirmée), et c'est un état que l'élément porte, pas une requête.
	 *
	 * `ReviewLib::create()` réécrit l'avis que la personne a déjà donné (`eReviewExisting`) : la
	 * création lui demande donc ce qu'une modification demanderait, sans quoi l'auteur d'un avis
	 * masqué le réécrirait par un second envoi du formulaire de création (Q4).
	 */
	public function acceptCreate(): bool {

		$this->expects(['experiencedAt', 'eReviewExisting']);

		return (
			$this['experiencedAt'] !== NULL and
			($this['eReviewExisting']->empty() or $this['eReviewExisting']->acceptUpdate())
		);

	}

	/**
	 * Un avis masqué ne se modifie plus (arbitrage Q4) : son auteur le lit avec le motif et peut le
	 * retirer. Hors du catalogue public, l'avis se donne et se modifie comme ailleurs, lu par le réseau
	 * seul (choix 4 bis B du lot glacons-parcours, qui remplace l'arbitrage Q8).
	 */
	public function acceptUpdate(): bool {

		$this->expects(['status']);

		return ($this['status'] !== Review::HIDDEN);

	}

	/**
	 * Explicite : l'auteur retire son avis dans tous les états, masqué compris, et `acceptDelete()`
	 * retomberait sinon sur `acceptWrite()`.
	 */
	public function acceptDelete(): bool {
		return TRUE;
	}

	/**
	 * Hors du catalogue public, la réponse n'est lue que par l'auteur de l'avis ; elle paraît sous
	 * l'avis si la formation entre au catalogue, la colonne étant la même.
	 */
	public function acceptAnswer(): bool {

		$this->expects(['status']);

		return ($this['status'] !== Review::HIDDEN);

	}

	/**
	 * Un signalement par avis (Q5) : un avis gardé ne se signale plus, sauf à redevenir publié quand son
	 * auteur le modifie. Une formation de l'équipe n'a pas de signalement (Q3) : c'est l'équipe qui
	 * l'écrit, elle masque directement.
	 */
	public function acceptReport(): bool {

		$this->expects(['status', 'course']);

		return (
			$this['status'] === Review::PUBLISHED and
			$this['course']->isTeam() === FALSE and
			$this->isCatalog()
		);

	}

	public function acceptHide(): bool {

		$this->expects(['status']);

		return ($this['status'] !== Review::HIDDEN);

	}

	public function acceptKeep(): bool {

		$this->expects(['status']);

		return ($this['status'] === Review::REPORTED);

	}

	public function acceptShow(): bool {

		$this->expects(['status']);

		return ($this['status'] === Review::HIDDEN);

	}

	public function assertCreateByLink(): bool {
		return $this->acceptCreate() and $this->canCreateByLink();
	}

	public function assertAnswer(): bool {
		return $this->acceptAnswer() and $this->canAnswer();
	}

	public function assertReport(): bool {
		return $this->acceptReport() and $this->canReport();
	}

	public function assertHide(): bool {
		return $this->acceptHide() and $this->canModerate();
	}

	public function assertKeep(): bool {
		return $this->acceptKeep() and $this->canModerate();
	}

	public function assertShow(): bool {
		return $this->acceptShow() and $this->canModerate();
	}

	public function isCatalog(): bool {

		$this->expects(['course']);

		return $this['course']->isInCatalog();

	}

	/**
	 * Le prénom et l'initiale du nom, « Léa R. », non échappés : lus sur le compte quand l'avis en a un,
	 * sur la saisie de la place sinon. Un compte fermé garde ses noms (`DropLib::closeNow()` ne vide
	 * que l'adresse), l'avis garde donc sa signature. Un compte sans prénom signe de l'initiale seule
	 * (Q14).
	 */
	public function getAuthorName(): string {

		$this->expects(['user', 'dateEnrollment']);

		if($this['user']->notEmpty()) {
			$firstName = $this['user']['firstName'];
			$lastName = $this['user']['lastName'];
		} else {
			$firstName = $this['dateEnrollment']['firstName'];
			$lastName = $this['dateEnrollment']['lastName'];
		}

		$initial = mb_strtoupper(mb_substr($lastName, 0, 1)).'.';

		return ($firstName === NULL) ? $initial : $firstName.' '.$initial;

	}

	/**
	 * L'adresse de l'auteur pour l'e-mail de masquage, `NULL` quand il n'en a plus : un compte fermé
	 * n'a plus d'adresse, et la saisie de la place ne se relit pas à sa place, la personne ayant
	 * fermé son compte.
	 */
	public function getAuthorEmail(): ?string {

		$this->expects(['user', 'dateEnrollment']);

		if($this['user']->notEmpty()) {
			return $this['user']['email'];
		}

		return $this['dateEnrollment']['email'];

	}

	/**
	 * Les motifs sont lus par l'auteur (masquage) et par l'équipe (signalement) : vides, ils ne disent
	 * rien. `addAfterCheck` et non `replaceCheck` : la règle ne borne qu'un côté, le schéma reste devant
	 * elle pour la longueur. La réponse a la même exigence : on la retire par son propre geste, jamais
	 * en l'envoyant vide.
	 */
	public static function properties(\PropertyList $l): void {

		foreach(['reportReason', 'hideReason', 'answer'] as $property) {
			$l->property($property)
				->addAfterCheck('empty', fn(?string $value): bool => $value !== NULL);
		}

	}

}
?>
