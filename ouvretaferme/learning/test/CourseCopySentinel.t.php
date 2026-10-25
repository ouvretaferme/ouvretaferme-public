<?php
namespace learning;

/**
 * La copie d'une formation (`CourseLib::duplicate()`) reprend ses propriétés par soustraction : tout
 * ce que le modèle porte, moins `CourseLib::PROPERTIES_NOT_COPIED`. Une colonne ajoutée dans un lot
 * suivant part donc dans la copie sans que personne l'ait décidé, et une table fille ajoutée (un
 * partage, un commentaire) n'y part jamais. Aucun code du package ne le rappellera à son auteur.
 *
 * L'inventaire déclare, pour chaque module rattaché à une formation, s'il est copié, et pour chaque
 * propriété d'un module copié, si elle est reprise ou non, avec sa raison. Il est comparé dans les
 * deux sens au modèle et à la liste du Lib.
 *
 * Lancement : dev/test package=learning file=CourseCopySentinel
 */
class CourseCopySentinelTest extends \Test {

	/**
	 * Les modules qui portent une formation et que la copie laisse, avec leur raison.
	 */
	const MODULES_NOT_COPIED = [
		'Announcement' => 'les comptes prévenus de l\'ouverture de l\'original ; la copie naît en brouillon, et ses propres partages l\'annonceront',
		'Comment' => 'le fil de l\'original, écrit par ses lecteurs à propos de sa version à lui',
		'Date' => 'les sessions de l\'original, tenues à un jour et en un lieu qui sont les siens ; la copie naît en brouillon et se date à son tour',
		'DateSheet' => 'les feuilles signées des dates de l\'original, preuves de présences qui ont eu lieu ; elles suivent les dates, que la copie ne reprend pas',
		'Enrollment' => 'les lecteurs de l\'original, qui ne se sont pas inscrits à la copie',
		'Faq' => 'la FAQ appartient à la structure, pas à une formation : sa propriété `question` en est le texte, pas une question de quiz, et son renvoi vers un module reste sur le module de l\'original',
		'Progress' => 'la progression de ces lecteurs, qui suit leur inscription',
		'Reading' => 'les lectures des stagiaires de l\'original, qui suivent leur inscription',
		'Response' => 'les tentatives des lecteurs de l\'original, à ses questions à lui',
		'Review' => 'les avis de l\'original, donnés par ceux qui l\'ont suivi ; la copie naît en brouillon, hors du catalogue public',
		'Share' => 'les promotions de l\'original : une copie naît en brouillon, et seule une formation publiée se partage',
	];

	/**
	 * Chaque propriété des modules copiés : `TRUE` reprise, `FALSE` laissée, et pourquoi.
	 */
	const PROPERTIES = [
		'Course' => [
			'id' => [FALSE, 'posé par la base'],
			'community' => [FALSE, 'le groupe qui reçoit la copie, posé par la page'],
			'type' => [TRUE, 'le type, que la copie garde dans son groupe'],
			'name' => [FALSE, 'le titre de la copie, posté'],
			'summary' => [TRUE, 'la présentation'],
			'description' => [TRUE, 'les enjeux, lus avant de s\'inscrire à une date'],
			'audience' => [TRUE, 'le public visé'],
			'prerequisites' => [TRUE, 'les prérequis'],
			'objectives' => [TRUE, 'les objectifs'],
			'access' => [TRUE, 'les modalités d\'accès'],
			'accessibility' => [TRUE, 'l\'accessibilité'],
			'recognition' => [TRUE, 'la reconnaissance'],
			'theme' => [TRUE, 'le classement, que la copie garde pour être publiable'],
			'subtheme' => [TRUE, 'la sous-thématique, qui suit la thématique reprise'],
			'level' => [TRUE, 'le niveau'],
			'vignette' => [FALSE, 'dupliquée à part sous un hash neuf, pour que chacune la remplace sans toucher l\'autre'],
			'status' => [FALSE, 'la copie naît brouillon'],
			'catalog' => [FALSE, 'posé par create() d\'après l\'auteur de la copie'],
			'catalogReason' => [FALSE, 'le motif d\'une décision sur l\'original, qui ne regarde pas la copie (lot L14)'],
			'catalogAt' => [FALSE, 'la date d\'une demande ou d\'une décision sur l\'original (lot L14)'],
			'featured' => [FALSE, 'le choix de l\'équipe pour « Premiers pas » porte sur l\'original, et la copie naît hors catalogue (lot catalogue-attractif)'],
			'featuredAt' => [FALSE, 'la date de ce choix, qui ne regarde pas la copie (lot catalogue-attractif)'],
			'open' => [FALSE, 'la copie naît réservée aux comptes, la lecture libre se rouvre à la main'],
			'offer' => [FALSE, 'la copie naît sans offre : chaque réseau choisit la sienne, et ce qu\'il paie ou fait payer ne se décide pas par la copie d\'un autre (lot L15a)'],
			'priceFunded' => [FALSE, 'la copie naît sans prix : la reprise dans le même réseau, décidée le 02/10, reste à écrire (lot tarification)'],
			'price' => [FALSE, 'la copie naît sans prix : la reprise dans le même réseau, décidée le 02/10, reste à écrire (lot tarification)'],
			'funding' => [FALSE, 'la copie naît sans prix : la reprise dans le même réseau, décidée le 02/10, reste à écrire (lot tarification)'],
			'fundingNote' => [FALSE, 'la copie naît sans prix : la reprise dans le même réseau, décidée le 02/10, reste à écrire (lot tarification)'],
			'externalOnly' => [FALSE, 'la copie naît ouverte à l\'inscription sur Ouvretaferme : où s\'inscrire est le choix de chaque réseau, pas celui de l\'original (lot annuaire)'],
			'externalUrl' => [FALSE, 'l\'adresse de la formation sur le site de l\'organisme de l\'original, qui ne désigne pas la copie (lot annuaire)'],
			'enrollmentPaused' => [FALSE, 'la copie naît ouverte aux inscriptions : la pause est un geste du réseau sur sa formation, pas une propriété du contenu (lot T13)'],
			'copiedFrom' => [FALSE, 'posé : l\'original s\'il est de l\'équipe, sinon son origine'],
			'publishedAt' => [FALSE, 'la copie n\'a jamais été publiée'],
			'createdAt' => [FALSE, 'posé par la base'],
			'createdBy' => [FALSE, 'le compte qui copie, posé par le cadriciel'],
			'updatedAt' => [FALSE, 'posé par la base'],
		],
		'Chapter' => [
			'id' => [FALSE, 'posé par la base'],
			'course' => [FALSE, 'la copie'],
			'phase' => [FALSE, 'reposée sur la phase copiée qui correspond, par CourseLib::duplicate()'],
			'name' => [TRUE, 'le titre du module'],
			'summary' => [TRUE, 'son résumé'],
			'position' => [FALSE, 'recompté par ChapterLib::create(), dans l\'ordre de l\'original'],
			'createdAt' => [FALSE, 'posé par la base'],
		],
		'Phase' => [
			'id' => [FALSE, 'posé par la base'],
			'course' => [FALSE, 'la copie'],
			'name' => [TRUE, 'le titre de la phase'],
			'summary' => [TRUE, 'son résumé'],
			'position' => [FALSE, 'recompté par PhaseLib::create(), dans l\'ordre de l\'original'],
			'createdAt' => [FALSE, 'posé par la base'],
		],
		'Lesson' => [
			'id' => [FALSE, 'posé par la base'],
			'course' => [FALSE, 'la copie'],
			'chapter' => [FALSE, 'le module copié qui correspond'],
			'name' => [TRUE, 'le titre de la leçon'],
			'objective' => [TRUE, 'son objectif'],
			'format' => [TRUE, 'sa forme'],
			'minutes' => [TRUE, 'sa durée'],
			'activityMinutes' => [TRUE, 'la durée de son activité'],
			'validation' => [TRUE, 'sa validation'],
			'quizThreshold' => [TRUE, 'le seuil du quiz'],
			'deliverable' => [TRUE, 'le livrable attendu'],
			'mandatory' => [TRUE, 'son caractère obligatoire'],
			'written' => [TRUE, 'écrite ou vide, comme l\'original, dont les blocs sont copiés tels quels'],
			'position' => [FALSE, 'recompté par LessonLib::create(), dans l\'ordre de l\'original'],
			'createdAt' => [FALSE, 'posé par la base'],
			'updatedAt' => [FALSE, 'posé par la base'],
		],
		'Staff' => [
			'id' => [FALSE, 'posé par la base'],
			'course' => [FALSE, 'la copie'],
			'supervisor' => [FALSE, 'la personne de l\'original, la copie restant dans son groupe : posée par CourseLib::duplicate(), ligne par ligne'],
			'role' => [TRUE, 'son rôle dans la formation'],
			'contact' => [TRUE, 'le contact de la formation, seul de sa copie comme il l\'était de l\'original'],
			'position' => [FALSE, 'recompté par StaffLib::create(), dans l\'ordre de l\'original'],
		],
		'Attachment' => [
			'id' => [FALSE, 'posé par la base'],
			'lesson' => [FALSE, 'la leçon copiée qui correspond'],
			'course' => [FALSE, 'la copie'],
			'name' => [TRUE, 'le nom affiché'],
			'hash' => [TRUE, 'le même média, gardé par le nettoyage tant qu\'une ligne le porte'],
			'position' => [FALSE, 'recompté par AttachmentLib::create(), dans l\'ordre de l\'original'],
			'createdAt' => [FALSE, 'posé par la base'],
			'createdBy' => [FALSE, 'le compte qui copie, posé par le cadriciel'],
		],
		'Question' => [
			'id' => [FALSE, 'posé par la base'],
			'lesson' => [FALSE, 'la leçon copiée qui correspond'],
			'statement' => [TRUE, 'l\'énoncé'],
			'image' => [TRUE, 'la même image, gardée par le nettoyage tant qu\'une question ou un choix la porte'],
			'type' => [TRUE, 'une ou plusieurs réponses justes'],
			'explanation' => [TRUE, 'l\'explication montrée après la réponse'],
			'position' => [FALSE, 'recompté par QuestionLib::create(), dans l\'ordre de l\'original'],
		],
		'Choice' => [
			'id' => [FALSE, 'posé par la base'],
			'question' => [FALSE, 'la question copiée qui correspond'],
			'label' => [TRUE, 'le libellé'],
			'image' => [TRUE, 'la même image, comme celle de la question'],
			'correct' => [TRUE, 'juste ou non'],
			'position' => [TRUE, 'le rang dans la question, repris tel quel'],
		],
	];

	/**
	 * Tout module du package rattaché à une formation, directement ou par sa leçon ou sa question,
	 * est déclaré, copié ou laissé.
	 */
	public function testModules(): void {

		$modules = [];

		foreach(glob(__DIR__.'/../module/*.m.php') as $path) {

			$module = basename($path, '.m.php');
			$class = __NAMESPACE__.'\\'.$module;

			if($module !== 'Course' and array_intersect(['course', 'lesson', 'question'], $class::model()->getProperties()) === []) {
				continue;
			}

			$modules[] = $module;

		}

		$declared = array_merge(array_keys(self::PROPERTIES), array_keys(self::MODULES_NOT_COPIED));

		$undeclared = array_diff($modules, $declared);
		$obsolete = array_diff($declared, $modules);

		$this->assertEquals([], array_values($undeclared), 'module rattaché à une formation que la copie ne déclare ni copier ni laisser : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($obsolete), 'module déclaré qui n\'est plus rattaché à une formation : '.implode(', ', $obsolete));

		$copied = array_map(fn(string $class) => substr($class, strlen(__NAMESPACE__) + 1), array_keys(CourseLib::PROPERTIES_NOT_COPIED));

		$this->assertEquals([], array_values(array_diff(array_keys(self::PROPERTIES), $copied)), 'module déclaré copié sans liste dans CourseLib::PROPERTIES_NOT_COPIED : '.implode(', ', array_diff(array_keys(self::PROPERTIES), $copied)));
		$this->assertEquals([], array_values(array_diff($copied, array_keys(self::PROPERTIES))), 'module de CourseLib::PROPERTIES_NOT_COPIED absent de l\'inventaire : '.implode(', ', array_diff($copied, array_keys(self::PROPERTIES))));

	}

	/**
	 * Chaque propriété d'un module copié est déclarée, et ce qui est déclaré laissé est exactement
	 * ce que le Lib laisse.
	 */
	public function testProperties(): void {

		foreach(self::PROPERTIES as $module => $properties) {

			$class = __NAMESPACE__.'\\'.$module;

			$real = $class::model()->getProperties();

			$undeclared = array_diff($real, array_keys($properties));
			$obsolete = array_diff(array_keys($properties), $real);

			$this->assertEquals([], array_values($undeclared), $module.', propriété que la copie reprendrait sans l\'avoir décidé : '.implode(', ', $undeclared));
			$this->assertEquals([], array_values($obsolete), $module.', propriété déclarée qui n\'existe plus : '.implode(', ', $obsolete));

			$notCopied = array_keys(array_filter($properties, fn(array $declaration) => $declaration[0] === FALSE));
			$notCopiedLib = CourseLib::PROPERTIES_NOT_COPIED[$class];

			$missing = array_diff($notCopied, $notCopiedLib);
			$extra = array_diff($notCopiedLib, $notCopied);

			$this->assertEquals([], array_values($missing), $module.', déclarée laissée mais reprise par CourseLib::duplicate() : '.implode(', ', $missing));
			$this->assertEquals([], array_values($extra), $module.', laissée par CourseLib::duplicate() mais déclarée reprise : '.implode(', ', $extra));

		}

	}

}
?>
