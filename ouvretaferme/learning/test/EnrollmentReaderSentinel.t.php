<?php
namespace learning;

/**
 * Les lecteurs d'une inscription en ligne hors de `EnrollmentLib`, inventaire de chaque appel qui
 * lit `learningEnrollment` : un accesseur de `EnrollmentLib`, ou le modèle lui-même.
 *
 * Une inscription peut être une demande, que le réseau n'a pas encore confirmée (`Enrollment::REQUESTED`).
 * Elle ne donne ni la lecture ni un compte dans les chiffres : les accesseurs de `EnrollmentLib` ne
 * rendent que les confirmées, et les demandes se lisent par des méthodes nommées
 * (`getRequestByUserAndCourse()`, `getRequestsByCourse()`, `fillRequests()`,
 * `getOpenByCourseAndUsers()`). Un lecteur neuf, qui lirait le modèle en direct ou par un
 * accesseur sans état, rendrait la lecture à une demande sans qu'aucun test de comportement ne le
 * voie : il se déclare ici, avec ce qu'il fait d'une demande.
 *
 * Le Lib lui-même et le module généré ne sont pas relevés : ses accesseurs sont éprouvés un à un par
 * `EnrollmentRequest.t.php`. Les fichiers de test non plus, qui écrivent leur décor.
 *
 * La clé est `fichier:appel`, sans la ligne, pour qu'une déclaration tienne quand le fichier bouge :
 * un second appel à une méthode déjà déclarée dans le même fichier ne rougit donc pas. Qui l'ajoute
 * relit la raison déclarée, qui doit valoir pour les deux.
 *
 * Le code fait foi : un appel relevé et non déclaré se déclare, après avoir vérifié ce qu'il fait
 * d'une demande ; une déclaration qui ne désigne plus rien se retire.
 *
 * Lancement : dev/test package=learning file=EnrollmentReaderSentinel
 */
class EnrollmentReaderSentinelTest extends \dev\SentinelTest {

	const RULE = 'enrollment-reader';

	const SKIPPED = [
		'ouvretaferme/learning/lib/Enrollment.l.php',
		'ouvretaferme/learning/module/Enrollment.m.php',
	];

	const READERS = [
		'accounting/training/page/farm.p.php:EnrollmentLib::getById' => 'lit l\'inscription par son id sans état : le compte peut semer une ferme d\'exercice depuis sa propre demande ou son inscription annulée. Code fermé, connu et non traité, à porter à la session de accounting/training',
		'accounting/training/page/farm.p.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/api/Course_0_1beta.a.php:EnrollmentLib::fillReaders' => 'les confirmées',
		'ouvretaferme/learning/lib/Course.l.php:EnrollmentLib::delete' => 'supprime chaque inscription de la formation supprimée, demandes comprises',
		'ouvretaferme/learning/lib/Course.l.php:EnrollmentLib::getByCourse' => 'tous les états, pour supprimer la formation',
		'ouvretaferme/learning/lib/Course.l.php:EnrollmentLib::isEnrolled' => 'les confirmées',
		'ouvretaferme/learning/lib/Notification.l.php:Enrollment::model()' => 'les confirmées et les demandes, chacune annoncée seule au réseau',
		'ouvretaferme/learning/lib/Progress.l.php:EnrollmentLib::getByCourseAndUsers' => 'les confirmées',
		'ouvretaferme/learning/lib/Review.l.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées : une demande ne donne pas d\'avis',
		'ouvretaferme/learning/lib/Share.l.php:EnrollmentLib::getOpenByCourseAndUsers' => 'demandées et confirmées : une personne qui a demandé ne reçoit pas l\'annonce de l\'ouverture',
		'ouvretaferme/learning/lib/Share.l.php:EnrollmentLib::getOpenByUserAndCourses' => 'demandées et confirmées : une formation où le compte est inscrit ne lui est plus proposée, celle où il a demandé porte « Demande envoyée »',
		'ouvretaferme/learning/lib/Usage.l.php:EnrollmentLib::countBySteps' => 'les confirmées, par pas de confirmation',
		'ouvretaferme/learning/lib/Usage.l.php:EnrollmentLib::getFirstConfirmedAt' => 'la première confirmée, qui ouvre la période « Depuis l\'ouverture »',
		'ouvretaferme/learning/module/Course.e.php:EnrollmentLib::isEnrolled' => 'les confirmées : une demande ne donne pas la lecture',
		'ouvretaferme/learning/module/DateEnrollment.e.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/module/Enrollment.e.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/page/account.p.php:EnrollmentLib::create' => 'écrit une inscription',
		'ouvretaferme/learning/page/account.p.php:EnrollmentLib::getOnlineConfirm' => 'le réglage du réseau, sans lire une inscription',
		'ouvretaferme/learning/page/admin/course.p.php:EnrollmentLib::fillReaders' => 'les confirmées',
		'ouvretaferme/learning/page/community.p.php:EnrollmentLib::fillReaders' => 'les confirmées',
		'ouvretaferme/learning/page/community.p.php:EnrollmentLib::fillRequests' => 'les demandes, pour la colonne « À confirmer »',
		'ouvretaferme/learning/page/course.p.php:EnrollmentLib::fillReaders' => 'les confirmées',
		'ouvretaferme/learning/page/course.p.php:EnrollmentLib::fillRequests' => 'les demandes, pour le compteur « N à confirmer » sous le titre de l\'éditeur',
		'ouvretaferme/learning/page/course.p.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées',
		'ouvretaferme/learning/page/course.p.php:EnrollmentLib::getOnlineConfirmByCommunity' => 'le réglage du réseau, sans lire une inscription',
		'ouvretaferme/learning/page/course.p.php:EnrollmentLib::getRequestByUserAndCourse' => 'la demande du compte, montrée « Demande envoyée »',
		'ouvretaferme/learning/page/course.p.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/page/dateEnrollment.p.php:EnrollmentLib::getPreselected' => 'la structure cochée d\'avance, sans lire une inscription',
		'ouvretaferme/learning/page/dateEnrollment.p.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::cancel' => 'retire une demande',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::cancelByCommunity' => 'annule une demande',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::confirm' => 'confirme une demande',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::getOnlineConfirm' => 'le réglage du réseau, sans lire une inscription',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::getPreselected' => 'la structure cochée d\'avance, sans lire une inscription',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::getRequestsByCourse' => 'les demandes, que l\'annulation redessine',
		'ouvretaferme/learning/page/enrollment.p.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/page/lesson.p.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées : une demande ne lit pas une leçon',
		'ouvretaferme/learning/page/lesson.p.php:EnrollmentLib::getOnlineConfirmByCommunity' => 'le réglage du réseau, sans lire une inscription',
		'ouvretaferme/learning/page/lesson.p.php:EnrollmentLib::getRequestByUserAndCourse' => 'la demande du compte, montrée « Demande envoyée » sous la leçon',
		'ouvretaferme/learning/page/lesson.p.php:EnrollmentLib::getStructures' => 'les structures du compte connecté, sans lire une inscription',
		'ouvretaferme/learning/page/mine.p.php:EnrollmentLib::getByUser' => 'les confirmées',
		'ouvretaferme/learning/page/pdf.p.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées : une demande n\'a pas d\'attestation',
		'ouvretaferme/learning/page/progress.p.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées',
		'ouvretaferme/learning/page/progress.p.php:EnrollmentLib::getLearners' => 'les confirmées',
		'ouvretaferme/learning/page/progress.p.php:EnrollmentLib::getRequestsByCourse' => 'les demandes, en tête du suivi',
		'ouvretaferme/learning/page/progress.p.php:EnrollmentLib::getStructuresByCourse' => 'les confirmées',
		'ouvretaferme/learning/page/reading.p.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées : une demande ne lit pas',
		'ouvretaferme/learning/page/response.p.php:EnrollmentLib::getByUserAndCourse' => 'les confirmées : une demande ne répond pas au quiz',
		'ouvretaferme/learning/ui/Enrollment.u.php:Enrollment::model()' => 'le describer, sans lire une inscription',
	];

	public function init(): void {

		foreach($this->getPhpTokens() as $file => $tokens) {

			if(
				str_ends_with($file, '.t.php') or
				in_array($file, self::SKIPPED, TRUE)
			) {
				continue;
			}

			foreach(self::findReaders($tokens) as [$key, $line]) {
				$this->addFoundKey(self::RULE, $file, $key, $line);
			}

		}

	}

	/**
	 * Les appels à `EnrollmentLib::<méthode>`, `Enrollment::model()` et `new EnrollmentModel()`, quel
	 * que soit l'espace de noms écrit devant. `DateEnrollment` et ses voisins ne sont pas relevés :
	 * le nom se compare au dernier segment, entier.
	 *
	 * @return array<int, array{0: string, 1: int}>
	 */
	protected static function findReaders(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, $text, $line]) {

			if(in_array($type, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], TRUE) === FALSE) {
				continue;
			}

			$name = substr(strrchr('\\'.$text, '\\'), 1);
			$next = $tokens[$i + 1] ?? [NULL, NULL, NULL];
			$after = $tokens[$i + 2] ?? [NULL, NULL, NULL];
			$previous = $tokens[$i - 1] ?? [NULL, NULL, NULL];

			if($name === 'EnrollmentLib' and $next[0] === T_DOUBLE_COLON and $after[0] === T_STRING) {
				$found[] = ['EnrollmentLib::'.$after[1], $line];
			} else if($name === 'Enrollment' and $next[0] === T_DOUBLE_COLON and $after[1] === 'model') {
				$found[] = ['Enrollment::model()', $line];
			} else if($name === 'EnrollmentModel' and $previous[0] === T_NEW) {
				$found[] = ['new EnrollmentModel()', $line];
			}

		}

		return $found;

	}

	public function testDetector(): void {

		$code = '<?php
			\learning\EnrollmentLib::getByUser($eUser);
			EnrollmentLib::isEnrolled($eUser, $eCourse);
			Enrollment::model()->whereId(1)->get();
			new \learning\EnrollmentModel();
			DateEnrollmentLib::getByDate($eDate);
			DateEnrollment::model()->get();
			new DateEnrollmentModel();
			// EnrollmentLib::getByCourse($eCourse);
		';

		$this->assertEquals([
			['EnrollmentLib::getByUser', 2],
			['EnrollmentLib::isEnrolled', 3],
			['Enrollment::model()', 4],
			['new EnrollmentModel()', 5],
		], self::findReaders(\dev\SentinelLib::getTokens($code)), 'les trois formes, avec ou sans espace de noms, et ni DateEnrollment ni un commentaire');

	}

	public function testReaders(): void {
		$this->assertDeclared(self::RULE, self::READERS, 'lecteur d\'une inscription en ligne non déclaré, vérifier ce qu\'il fait d\'une demande (Enrollment::REQUESTED) puis le déclarer dans EnrollmentReaderSentinel::READERS');
	}

}
?>
