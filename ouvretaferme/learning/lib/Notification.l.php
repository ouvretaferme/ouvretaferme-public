<?php
namespace learning;

/**
 * Ce que le package `learning` apporte au centre de notifications, hors affichage : ce que les
 * stagiaires d'un groupe font de ses formations, reçu par les formateurs.
 *
 * Le contrat de la paire `NotificationLib` + `NotificationUi` est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 * Le nom de la classe est fixe, parce que c'est lui que la convention résout depuis le segment qui
 * précède le premier tiret du type (`learning-comment` → `learning`).
 *
 * Tout ce que ces types relisent vit dans la base commune (formations, inscriptions, commentaires,
 * promotions) : aucune relecture ne touche une base `structure_<id>`. Chacune vérifie que l'objet
 * relu est bien d'une formation du groupe qui reçoit, un identifiant seul ne disant pas à qui il est.
 *
 * Une formation de l'équipe Ouvretaferme n'a pas de groupe : rien ne part pour elle, ce que les
 * émetteurs gardent avant de mettre en file.
 */
class NotificationLib {

	/**
	 * Un stagiaire écrit un commentaire sous une leçon. `reference` porte le commentaire. Un message
	 * de l'équipe ne part pas : c'est elle qui reçoit.
	 */
	const string COMMENT = 'learning-comment';

	/**
	 * Un stagiaire s'inscrit à une formation. `reference` porte l'inscription, et `group` la
	 * formation : une tête par formation et par jour, comme les commandes d'une même livraison
	 * (arbitrage de V-Learning sur la planche du 22/09).
	 *
	 * Une demande que le réseau doit confirmer (`Enrollment::REQUESTED`) part sous le même type et la
	 * même référence (Q-C3) : `content.requested` la reformule et mène à la table « À confirmer », et
	 * elle ne se groupe pas avec les stagiaires qui ont commencé. La confirmation réémet la même
	 * référence, que `once` retranche : le réseau qui a confirmé n'a rien de plus à apprendre.
	 */
	const string ENROLLMENT = 'learning-enrollment';

	/**
	 * Un stagiaire a suivi toutes les leçons obligatoires d'une formation (`Lesson.mandatory`).
	 * `reference` porte l'inscription, comme le commencement : un même stagiaire ne termine qu'une
	 * fois, et `once` tient le rejeu d'une leçon décochée puis recochée.
	 */
	const string COMPLETED = 'learning-completed';

	/**
	 * Une personne donne son avis sur une formation du réseau, au catalogue public ou non (lot L15c,
	 * Q11). `reference` porte l'avis ; une modification ne prévient pas, seul l'avis neuf.
	 */
	const string REVIEW = 'learning-review';

	/**
	 * Les types que ce package émet (le détail des clés est dans `shop\NotificationLib::getTypes()`
	 * et `notification\NotificationLib::getTypes()`).
	 *
	 * Réservés à l'encadrement par le rôle et non par un droit : conseiller d'un réseau
	 * (`StructureSetting::ROLE_FQNS_STAFF`), l'administrateur passant toujours (`Member::isRole()`).
	 * Les formateurs d'un groupe de formation en sont les administrateurs (décidé le
	 * 22/09).
	 *
	 * Bornés aux groupes de formation et aux réseaux (`communityTypes`) : ce sont les seuls à écrire
	 * des formations (`Course::acceptWrite()`), et sans cette borne les réglages d'un groupe de
	 * producteurs porteraient un intertitre « Formations » qui ne commande rien.
	 *
	 * `production` est la fonctionnalité que porte tout groupe. Les quatre se coupent (`mandatory` à
	 * FALSE, la page Commentaires restant le filet de l'équipe) et sont allumés par défaut. Ils
	 * partent sans l'offre Pilotage (`pilotage` à FALSE, décidé le 22/09).
	 */
	public static function getTypes(): array {

		$declaration = [
			'right' => 'fqn',
			'rightValues' => \structure\StructureSetting::ROLE_FQNS_STAFF,
			'feature' => 'production',
			'family' => \notification\NotificationSetting::LEARNING,
			'structureTypes' => [\structure\Structure::COMMUNITY],
			'communityTypes' => [\structure\Structure::EDUCATION, \structure\Structure::NETWORK],
			'values' => [\notification\NotificationSetting::OFF, \notification\NotificationSetting::ON],
			'default' => \notification\NotificationSetting::ON,
			'once' => TRUE,
			'mandatory' => FALSE,
			'pilotage' => FALSE
		];

		return [
			self::COMMENT => $declaration,
			self::ENROLLMENT => $declaration,
			self::COMPLETED => $declaration,
			self::REVIEW => $declaration
		];

	}

	public static function commented(Comment $eComment): void {

		$eComment->expects(['id', 'lesson' => ['course']]);

		self::emit($eComment['lesson']['course'], self::COMMENT, $eComment['id']);

	}

	public static function enrolled(Enrollment $eEnrollment): void {

		$eEnrollment->expects(['id', 'course']);

		self::emit($eEnrollment['course'], self::ENROLLMENT, $eEnrollment['id']);

	}

	public static function completed(Enrollment $eEnrollment): void {

		$eEnrollment->expects(['id', 'course']);

		self::emit($eEnrollment['course'], self::COMPLETED, $eEnrollment['id']);

	}

	public static function reviewed(Review $eReview): void {

		$eReview->expects(['id', 'course']);

		self::emit($eReview['course'], self::REVIEW, $eReview['id']);

	}

	/**
	 * Le groupe à prévenir se relit ici, par l'id de la formation : les appelants construisent
	 * souvent la formation par son seul id (l'API, une inscription écrite depuis une page qui ne
	 * porte que l'identifiant), et exiger `community` de chacun ferait lever le geste lui-même,
	 * après l'écriture, pour une notification. Une lecture par clé primaire, au prix d'un geste rare.
	 */
	private static function emit(Course $eCourse, string $type, int $reference): void {

		$eCourse = Course::model()
			->select(['id', 'community'])
			->whereId($eCourse)
			->get();

		if($eCourse->isTeam()) {
			return;
		}

		\notification\NotificationLib::emit($eCourse['community'], $type, $reference);

	}

	/**
	 * Relit l'objet au moment du traitement, dans la base commune, et vérifie qu'il est encore là et
	 * qu'il est bien d'une formation du groupe qui reçoit : une inscription retirée avant le passage
	 * de la file n'est pas annoncée, un identifiant d'une autre formation non plus.
	 *
	 * Le contenu porte tout ce que l'écran montre : `NotificationUi` ne relit rien. La formation y
	 * est par son nom pour la phrase et par son id pour le clic, qui mène au suivi des stagiaires.
	 */
	public static function describe(string $type, \structure\Structure $eStructure, ?int $reference): \notification\Notification {

		return match($type) {
			self::COMMENT => self::describeComment($eStructure, $reference),
			self::ENROLLMENT => self::describeEnrollment($eStructure, $reference, grouped: TRUE),
			self::COMPLETED => self::describeEnrollment($eStructure, $reference, grouped: FALSE),
			self::REVIEW => self::describeReview($eStructure, $reference)
		};

	}

	private static function describeComment(\structure\Structure $eStructure, ?int $reference): \notification\Notification {

		$eComment = Comment::model()
			->select([
				'createdBy' => ['firstName', 'lastName'],
				'lesson' => [
					'name',
					'course' => ['name', 'community']
				]
			])
			->whereId($reference)
			->get();

		if(
			$eComment->empty() or
			self::isOwned($eComment['lesson']['course'], $eStructure) === FALSE
		) {
			return new \notification\Notification();
		}

		return new \notification\Notification([
			'group' => NULL,
			'content' => [
				'firstName' => $eComment['createdBy']['firstName'],
				'lastName' => $eComment['createdBy']['lastName'],
				'lesson' => $eComment['lesson']['name'],
				'course' => $eComment['lesson']['course']['name']
			]
		]);

	}

	/**
	 * Un avis masqué entre le geste et le traitement n'est pas annoncé : l'équipe du réseau ne le lit
	 * plus comme les autres.
	 */
	private static function describeReview(\structure\Structure $eStructure, ?int $reference): \notification\Notification {

		$eReview = Review::model()
			->select([
				'status', 'rating',
				'user' => ['firstName', 'lastName'],
				'dateEnrollment' => ['firstName', 'lastName'],
				'course' => ['name', 'community']
			])
			->whereId($reference)
			->get();

		if(
			$eReview->empty() or
			$eReview['status'] === Review::HIDDEN or
			self::isOwned($eReview['course'], $eStructure) === FALSE
		) {
			return new \notification\Notification();
		}

		return new \notification\Notification([
			'group' => NULL,
			'content' => [
				'author' => $eReview->getAuthorName(),
				'rating' => $eReview['rating'],
				'course' => $eReview['course']['name'],
				'courseId' => $eReview['course']['id']
			]
		]);

	}

	/**
	 * Une fin de formation n'est annoncée que si elle est encore vraie : une leçon obligatoire
	 * décochée entre le geste et le traitement la retire. Une inscription l'est confirmée ou en
	 * demande, dans l'état où le traitement la trouve : une demande confirmée entre-temps s'annonce
	 * commencée, une demande annulée ne s'annonce pas. Une fin ne vaut que pour une confirmée.
	 */
	private static function describeEnrollment(\structure\Structure $eStructure, ?int $reference, bool $grouped): \notification\Notification {

		$eEnrollment = Enrollment::model()
			->select([
				'status',
				'user' => ['firstName', 'lastName'],
				'course' => ['name', 'community']
			])
			->whereId($reference)
			->whereStatus('IN', $grouped ? [Enrollment::CONFIRMED, Enrollment::REQUESTED] : [Enrollment::CONFIRMED])
			->get();

		if(
			$eEnrollment->empty() or
			self::isOwned($eEnrollment['course'], $eStructure) === FALSE or
			(
				$grouped === FALSE and
				ProgressLib::countMandatoryLeft($eEnrollment) > 0
			)
		) {
			return new \notification\Notification();
		}

		$requested = ($eEnrollment['status'] === Enrollment::REQUESTED);

		return new \notification\Notification([
			'group' => ($grouped and $requested === FALSE) ? $eEnrollment['course']['id'] : NULL,
			'content' => [
				'requested' => $requested,
				'firstName' => $eEnrollment['user']['firstName'],
				'lastName' => $eEnrollment['user']['lastName'],
				'course' => $eEnrollment['course']['name'],
				'courseId' => $eEnrollment['course']['id'],
				'section' => self::getSectionName($eEnrollment, $eStructure)
			]
		]);

	}

	private static function isOwned(Course $eCourse, \structure\Structure $eStructure): bool {

		return (
			$eCourse['community']->notEmpty() and
			$eCourse['community']['id'] === $eStructure['id']
		);

	}

	/**
	 * La promotion du stagiaire parmi celles à qui la formation est ouverte : c'est la ligne grise
	 * de la planche, ce qui distingue deux stagiaires d'une même formation. Un stagiaire rangé dans
	 * deux de ces promotions, ou dans aucune, n'en a pas une seule : la ligne se vide (écart assumé
	 * au plan). Deux lectures dans la base commune, où vivent les promotions et les partages.
	 */
	private static function getSectionName(Enrollment $eEnrollment, \structure\Structure $eStructure): ?string {

		$cShare = ShareLib::getByCourse($eEnrollment['course']);

		$cSection = \structure\SectionLib::getByUser($eEnrollment['user'], $eStructure)
			->find(fn(\structure\Section $eSection) => $cShare->offsetExists($eSection['id']));

		if($cSection->count() !== 1) {
			return NULL;
		}

		return $cSection->first()['name'];

	}

	public static function getAmount(\notification\Notification $eNotification): ?float {

		return NULL;

	}

	/**
	 * La tête d'un groupe d'inscriptions nomme la formation dans sa phrase : rien à ajouter en
	 * dessous.
	 */
	public static function getGroupName(\notification\Notification $eNotification): ?string {

		return NULL;

	}

	/**
	 * Les commentaires reçus du groupe pour un commentaire, le suivi des stagiaires de la formation
	 * pour un commencement ou une fin, sa table « À confirmer » pour une demande, la boite des avis du groupe pour un avis, qu'elle montre au
	 * catalogue public ou non.
	 *
	 * ⚠️ Construite depuis la structure qui reçoit (`eStructure`) et l'id que porte le contenu, jamais
	 * depuis la structure connectée : le clic se traite sur une route du moteur, qui n'est d'aucune
	 * structure.
	 */
	public static function getUrl(\notification\Notification $eNotification): string {

		return match($eNotification['type']) {
			self::COMMENT => CommentUi::urlInbox(new \structure\Structure(['id' => $eNotification['eStructure']['id']])),
			self::ENROLLMENT => ProgressUi::urlTrack(new Course(['id' => $eNotification['content']['courseId']])).(NotificationUi::isRequest($eNotification) ? '#learning-enrollment-requests' : ''),
			self::COMPLETED => ProgressUi::urlTrack(new Course(['id' => $eNotification['content']['courseId']])),
			self::REVIEW => ReviewUi::urlInbox(new \structure\Structure(['id' => $eNotification['eStructure']['id']]))
		};

	}

}
?>
