<?php
namespace learning;

/**
 * Les formations : celles de l'équipe Ouvretaferme (`community` nul), toutes au catalogue dès leur
 * publication, et celles d'un groupe, lues par son équipe, et au catalogue une fois que l'équipe les
 * y a acceptées. `catalog` dit la place au catalogue, `open` la lecture sans compte, que seule
 * l'équipe accorde.
 *
 * Les compteurs (modules, leçons, durée, lecteurs) ne sont pas stockés : ils se lisent par une
 * requête groupée pour toute la liste, jamais par formation.
 */
class CourseLib extends CourseCrud {

	/**
	 * Les formations d'une rangée de thématique au catalogue sans filtre, une ligne de
	 * `util-cards-3` : au-delà, « Voir les N formations » mène à la thématique filtrée.
	 */
	const CATALOG_ROW = 3;

	/**
	 * Ce qu'une copie ne reprend pas de l'original, par module : ce que la base pose à l'écriture,
	 * ce qui rattache la ligne à son parent (reposé sur la copie), le rang (recalculé par `create()`),
	 * la vignette (dupliquée à part, sous un hash neuf) et ce que la copie pose elle-même.
	 * `CourseCopySentinel` compare cet inventaire aux propriétés des modules.
	 */
	const PROPERTIES_NOT_COPIED = [
		Course::class => ['id', 'community', 'name', 'vignette', 'status', 'catalog', 'catalogReason', 'catalogAt', 'featured', 'featuredAt', 'open', 'offer', 'priceFunded', 'price', 'funding', 'fundingNote', 'externalOnly', 'externalUrl', 'enrollmentPaused', 'copiedFrom', 'publishedAt', 'createdAt', 'createdBy', 'updatedAt'],
		Chapter::class => ['id', 'course', 'phase', 'position', 'createdAt'],
		Phase::class => ['id', 'course', 'position', 'createdAt'],
		Lesson::class => ['id', 'course', 'chapter', 'position', 'createdAt', 'updatedAt'],
		Staff::class => ['id', 'course', 'supervisor', 'position'],
		Attachment::class => ['id', 'lesson', 'course', 'position', 'createdAt', 'createdBy'],
		Question::class => ['id', 'lesson', 'position'],
		Choice::class => ['id', 'question'],
	];

	/**
	 * Une formation se crée avec son titre, et peut déjà se présenter et se classer : l'étape « La
	 * formation » du parcours de création pose les quatre, comme l'API. Seul le titre est exigé ;
	 * la publication réclame le reste par l'étape `Course::status.classified`, et une formation
	 * créée est toujours un brouillon (`getCreateElement()`), que les étapes `classified` de la
	 * présentation et de la thématique laissent donc passer vides.
	 *
	 * La sous-thématique ne s'écrit qu'à l'écran : dans la liste `api`, elle deviendrait exigible à
	 * chaque écriture (`framework/core/Api.php`) et casserait les clients qui l'ignorent.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		return ($origin === 'api')
			? ['name', 'type', 'summary', 'theme', 'level']
			: ['name', 'type', 'summary', 'theme', 'subtheme', 'level'];

	}

	/**
	 * La lecture sans compte (`open`) n'existe que pour l'équipe : une formation de groupe se lit par
	 * ses stagiaires, qui ont un compte. `catalog` n'est construit par aucun formulaire : `create()` le
	 * pose d'après l'auteur, et chaque geste du circuit du catalogue public le construit ensuite avec
	 * l'état qu'il vise (lot L14, `updateCatalog()`).
	 *
	 * `status` n'est construit que par l'API, qui n'a qu'une route d'écriture. Côté produit, publier
	 * et dépublier passent par `doUpdateStatus` : un formulaire qui construirait `status` écrirait
	 * l'état qu'il a lu en s'ouvrant, et dépublierait une formation publiée entre-temps.
	 *
	 * Le détail de la formation (enjeux, objectifs, public, prérequis et les trois mentions) ne fait
	 * plus partie des réglages de l'écran : il s'écrit par son propre panneau, `doUpdateDetails`, sur
	 * `getPropertiesDetails()`. Les réglages ne le postant pas, le construire ici le viderait
	 * (`framework/core/Data.php`, une valeur non postée vaut NULL).
	 *
	 * Les trois mentions de l'indicateur 1 de Qualiopi (`access`, `accessibility`, `recognition`)
	 * restent hors de la liste `api` : l'API les rend, mais une propriété de cette liste devient
	 * exigible à chaque PUT (`Course::access.missing`, `framework/core/Api.php`), ce qui casserait
	 * les clients qui écrivent déjà une formation sans les connaître.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {

		return function(Course $e) use($origin) {

			// `build()` construit dans l'ordre de la liste (`framework/core/Data.php`) : là où
			// `status` est construit, il passe après `summary` et `theme`, que les deux sens de
			// `status.classified` lisent sur l'élément, et après le prix d'une formation de réseau,
			// que `status.priced` lit.
			if($origin === 'api') {

				$properties = ['name', 'summary', 'theme', 'level'];

				// Le prix d'une formation de réseau s'écrit par la même `build()` que son panneau
				// (`doUpdatePrice`), étapes comprises : `paid` est construit avant les montants, que
				// ses étapes `free` ramènent à 0 sur une formation gratuite.
				if($e->isNetwork()) {
					$properties = [...$properties, 'paid', 'priceFunded', 'price'];
				}

				$properties = [...$properties, 'status', 'description', 'objectives', 'audience', 'prerequisites'];

			} else {
				$properties = ['name', 'summary', 'theme', 'subtheme', 'level'];
			}

			if($e->isTeam()) {
				$properties[] = 'open';
			}

			// `type` est construit même quand la formation a des dates, pour que `Course::type.dated`
			// refuse avec son message un type posté par un panneau ouvert avant la première date.
			// L'offre n'en fait pas partie, elle s'écrit dans l'onglet Diffusion (`doUpdateOffer`,
			// lot L15a). Le prix non plus à l'écran, il s'écrit par son panneau (`doUpdatePrice`).
			if(
				$origin !== 'api' and
				$e->isNetwork()
			) {
				$properties[] = 'type';
			}

			return $properties;

		};

	}

	/**
	 * La formation en détail, que l'éditeur montre dans son onglet et que son panneau écrit d'un seul
	 * envoi : les sept champs sont postés ensemble, un champ vidé repasse donc à NULL.
	 */
	public static function getPropertiesDetails(): array {
		return ['description', 'objectives', 'audience', 'prerequisites', 'access', 'accessibility', 'recognition'];
	}

	/**
	 * Le prix et le financement d'une formation de réseau, écrits d'un seul envoi par leur panneau :
	 * un champ vidé repasse à NULL. L'offre n'en fait pas partie et s'écrit sur la page Offre et
	 * catalogue, les deux se croisant par les étapes `offer.priced` et `price.trainee`.
	 */
	public static function getPropertiesPrice(): array {
		return ['priceFunded', 'price', 'funding', 'fundingNote'];
	}

	/**
	 * La formation a-t-elle de quoi être proposée au catalogue public (lot L15a) ? En ligne, une offre
	 * choisie ; datée, une date à venir qui en porte une, ou l'inscription sur le site de l'organisme
	 * (lot annuaire), qui n'a pas d'offre à choisir.
	 */
	public static function isOffered(Course $eCourse): bool {

		$eCourse->expects(['type', 'offer', 'externalOnly']);

		if($eCourse['type'] === Course::ONLINE) {
			return ($eCourse['offer'] !== Course::NONE);
		}

		if($eCourse->isExternal()) {
			return TRUE;
		}

		return DateLib::existsOfferedByCourse($eCourse);

	}

	/**
	 * Le catalogue : les formations publiées qui y sont retenues, celles de l'équipe d'office et celles
	 * d'un groupe que l'équipe a acceptées. Celles qui demandent un compte y restent pour le visiteur,
	 * avec leur cadenas : il voit ce que l'inscription lui ouvrira. Un groupe qui a éteint ses
	 * formations en retire les siennes sans perdre leur place, qu'elles retrouvent au rallumage.
	 */
	public static function getCatalog(): \Collection {

		return self::applyCatalog(Course::model())
			->select(Course::getSelection())
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

	/**
	 * La règle de `getCatalog()` sur un modèle que l'appelant termine : « Premiers pas » la cite
	 * (`getFeatured()`), une formation à la une n'ayant de lien que tant qu'elle se lit au catalogue.
	 */
	public static function applyCatalog(CourseModel $mCourse): CourseModel {

		return self::applyCommunityLearning($mCourse, 'm', 's')
			->whereCatalog(Course::ACCEPTED)
			->whereStatus(Course::PUBLISHED);

	}

	/**
	 * Garde les formations de l'équipe et celles des groupes qui acceptent les formations, la même
	 * règle que `Structure::acceptLearning()` posée en SQL : un groupe d'un type qui les permet
	 * (`CommunityFeatureAiLib::getCommunityTypes()`) et qui les a allumées. Une formation de l'équipe
	 * n'a pas de groupe, d'où le `IS NULL`. `$alias` est le préfixe des tables de la requête des
	 * formations, `$prefix` celui de la citation, qui change avec la profondeur d'imbrication. Les
	 * trois tables sont dans la base commune, la ligne se lit par l'index unique
	 * `structureFeature.structure` puis par la clé de `structure`.
	 */
	public static function applyCommunityLearning(CourseModel $mCourse, string $alias, string $prefix): CourseModel {

		$exists = new \structure\FeatureModel()
			->join(new \structure\StructureModel(), $prefix.'2.id = '.$prefix.'1.structure')
			->whereStructure(new \Sql($alias.'1.community'))
			->whereHasCommunityLearning(TRUE)
			->where($prefix.'2.type', \structure\Structure::COMMUNITY)
			->where($prefix.'2.communityType', 'IN', \structure\CommunityFeatureAiLib::getCommunityTypes('hasCommunityLearning'))
			->existsSql($prefix);

		return $mCourse->where('('.$alias.'1.community IS NULL OR '.$exists.')');

	}

	/**
	 * Les formations de réseau à un état du catalogue public, pour l'onglet de l'administration (lot
	 * L14) : les plus récentes d'abord, la demande qui attend depuis le moins longtemps en tête comme
	 * le refus le plus récent. Celles de l'équipe, toutes `accepted`, n'y sont pas : elles n'ont pas
	 * de circuit. Une requête par état, chacune sur l'index `(catalog, status)`.
	 */
	public static function getByCatalog(string $catalog): \Collection {

		return Course::model()
			->select(Course::getSelection())
			->whereCatalog($catalog)
			->whereCommunity('!=', NULL)
			->sort(['catalogAt' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection(index: 'id');

	}

	/**
	 * « Premiers pas », dans l'ordre où le catalogue les montre : l'ordre où l'équipe les a choisies.
	 * Seulement celles qui se lisent au catalogue (`applyCatalog()`) : une formation d'un groupe qui a
	 * éteint ses formations garde sa place à la une et la retrouve au rallumage. Par l'index
	 * `featured`.
	 */
	public static function getFeatured(): \Collection {

		return self::applyCatalog(Course::model())
			->select(Course::getSelection())
			->whereFeatured(TRUE)
			->sort(['featuredAt' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	public static function getForTeam(): \Collection {

		return Course::model()
			->select(Course::getSelection())
			->whereCommunity(NULL)
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les formations d'un groupe, ou de l'équipe pour un groupe vide : le parcours de création ne
	 * propose de revenir à la liste que si elle a quelque chose à montrer.
	 */
	public static function countByCommunity(\structure\Structure $eStructure): int {

		return Course::model()
			->whereCommunity($eStructure->empty() ? NULL : $eStructure)
			->count();

	}

	public static function getByCommunity(\structure\Structure $eStructure): \Collection {

		return Course::model()
			->select(Course::getSelection())
			->whereCommunity($eStructure)
			->sort(['name' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Les formations qu'un réseau propose au public : publiées, en visio ou sur place, dans l'ordre
	 * où il les a créées. Lue sans `Course::canRead()`, qui suppose un membre.
	 */
	public static function getPublicByCommunity(\structure\Structure $eStructureCommunity): \Collection {

		return self::applyPublicByCommunity(Course::model(), $eStructureCommunity)
			->select(Course::getSelection())
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Les formations en ligne qu'un réseau propose au public, sous ses dates sur sa page publique :
	 * publiées, dans l'ordre où il les a créées. C'est `Course::acceptPublic()` en requête pour un
	 * réseau que l'appelant a reconnu ouvert aux formations (`Structure::acceptLearning()`), lue
	 * comme `getPublicByCommunity()` sans `Course::canRead()` : chacun s'y inscrit, membre ou non. Une
	 * lecture à part et non un élargissement de `applyPublicByCommunity()`, dont les deux lecteurs
	 * attendent des formations à dates.
	 */
	public static function getPublicOnlineByCommunity(\structure\Structure $eStructureCommunity): \Collection {

		return self::applyPublicOnlineByCommunity(Course::model(), $eStructureCommunity)
			->select(Course::getSelection())
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * Le réseau propose-t-il au public une formation en ligne : la question du code d'intégration,
	 * qui n'a rien à montrer sans elle ni date publique.
	 */
	public static function existsPublicOnlineByCommunity(\structure\Structure $eStructureCommunity): bool {

		return self::applyPublicOnlineByCommunity(Course::model(), $eStructureCommunity)->exists();

	}

	private static function applyPublicOnlineByCommunity(CourseModel $m, \structure\Structure $eStructureCommunity): CourseModel {

		return $m
			->whereCommunity($eStructureCommunity)
			->whereStatus(Course::PUBLISHED)
			->whereType(Course::ONLINE);

	}

	/**
	 * La règle de `getPublicByCommunity()` sur un modèle que l'appelant termine : les dates publiques
	 * d'un réseau la citent (`DateLib::applyPublicByCommunity()`).
	 */
	public static function applyPublicByCommunity(CourseModel $m, \structure\Structure $eStructureCommunity): CourseModel {

		return $m
			->whereCommunity($eStructureCommunity)
			->whereStatus(Course::PUBLISHED)
			->whereType('!=', Course::ONLINE);

	}

	/**
	 * Range les formations par thématique, dans l'ordre des thématiques : chaque thématique porte
	 * ses formations dans `cCourse`, et celles qui n'en ont aucune sont écartées.
	 *
	 * Une formation non classée n'entre dans aucun groupe, et c'est l'appelant qui en décide le
	 * sort (`page/admin/course.p.php` les rassemble sous « Sans thématique »). Le délégué vide se
	 * reconnaît par `empty()` : sa clé `id` n'existe pas, et la lire lève une notice par thématique
	 * et par formation non classée.
	 */
	public static function groupByTheme(\Collection $cCourse, \Collection $cTheme): \Collection {

		$cThemeFilled = new \Collection();

		foreach($cTheme as $eTheme) {

			$cCourseTheme = $cCourse->find(fn(Course $eCourse) => (
				$eCourse['theme']->notEmpty() and
				$eCourse['theme']['id'] === $eTheme['id']
			));

			if($cCourseTheme->notEmpty()) {
				$cThemeFilled[$eTheme['id']] = $eTheme->merge(['cCourse' => $cCourseTheme]);
			}

		}

		return $cThemeFilled;

	}

	/**
	 * Le nombre de formations de chaque type dans une liste, les quatre types toujours présents, un
	 * type absent comptant zéro : le filtre « Type » en tire ses jetons.
	 *
	 * @return array<string, int>
	 */
	public static function countByType(\Collection $cCourse): array {

		$types = [Course::ONSITE => 0, Course::REMOTE => 0, Course::MIXED => 0, Course::ONLINE => 0];

		foreach($cCourse as $eCourse) {
			$types[$eCourse['type']]++;
		}

		return $types;

	}

	/**
	 * Pose sur chaque formation ses compteurs : `chapters`, `lessons` et `minutes`, et le résumé de ses
	 * avis que lit la carte (`reviews`, `rating`). Les leçons se comptent toutes, et seules les leçons
	 * écrites comptent leur durée (`ChapterLib::getByCourse()`).
	 */
	public static function fillCounts(\Collection $cCourse): void {

		if($cCourse->empty()) {
			return;
		}

		$cLessonCount = Lesson::model()
			->select([
				'course',
				'lessons' => new \Sql('COUNT(*)', 'int'),
				'minutes' => new \Sql('SUM(IF(written, minutes, NULL))', 'int')
			])
			->whereCourse('IN', $cCourse)
			->group('course')
			->getCollection(index: 'course');

		$cChapterCount = Chapter::model()
			->select([
				'course',
				'chapters' => new \Sql('COUNT(*)', 'int')
			])
			->whereCourse('IN', $cCourse)
			->group('course')
			->getCollection(index: 'course');

		foreach($cCourse as $eCourse) {

			// Deux chemins mènent à une durée non estimée, et tous deux la rendent `NULL` : une
			// formation sans leçon n'a pas de ligne dans le groupement, une formation dont aucune leçon
			// écrite n'est estimée en a une dont la somme vaut `NULL`. Zéro dirait « cette formation ne
			// dure rien » (`CourseUi::getDuration()`).
			if($cLessonCount->offsetExists($eCourse['id'])) {
				$eCourse['lessons'] = $cLessonCount[$eCourse['id']]['lessons'];
				$eCourse['minutes'] = $cLessonCount[$eCourse['id']]['minutes'];
			} else {
				$eCourse['lessons'] = 0;
				$eCourse['minutes'] = NULL;
			}

			$eCourse['chapters'] = $cChapterCount->offsetExists($eCourse['id']) ? $cChapterCount[$eCourse['id']]['chapters'] : 0;

		}

		DateLib::fillUpcoming($cCourse);
		ReviewLib::fillSummary($cCourse);

	}

	/**
	 * Ce qui manque à une formation pour pouvoir être publiée, dans l'ordre où le panneau l'énumère :
	 * les noms des propriétés du classement encore vides et du prix (`price`, que le panneau met en
	 * dernier), les leçons incomplètes, puis si une formation en ligne n'a encore aucune leçon.
	 * Chaque leçon incomplète porte ce qui lui manque (`missing`) : sa durée (`minutes`), et pour une
	 * leçon à quiz, une première question (`questions`). Rend des noms et des éléments, que l'UI
	 * traduit ; l'inventaire est le même que celui des étapes `Course::status.classified`,
	 * `Course::status.program` et `Course::status.priced`, seules à refuser.
	 */
	public static function getMissingForPublication(Course $eCourse, \Collection $cChapter): array {

		$properties = [];

		if($eCourse['summary'] === NULL) {
			$properties[] = 'summary';
		}

		if($eCourse['theme']->empty()) {
			$properties[] = 'theme';
		}

		$cLessonMissing = new \Collection();
		$lessons = [];

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$lessons[] = $eLesson['id'];
			}
		}

		$questions = QuestionLib::countByLessons($lessons);

		foreach($cChapter as $eChapter) {

			foreach($eChapter['cLesson'] as $eLesson) {

				$missing = [];

				if($eLesson['minutes'] === NULL) {
					$missing[] = 'minutes';
				}

				if(
					$eLesson['validation'] === Lesson::QUIZ and
					$questions[$eLesson['id']] === 0
				) {
					$missing[] = 'questions';
				}

				if($missing !== []) {
					$eLesson['missing'] = $missing;
					$cLessonMissing[$eLesson['id']] = $eLesson;
				}

			}

		}

		$programEmpty = (
			$eCourse['type'] === Course::ONLINE and
			$lessons === []
		);

		if(self::isPriceMissing($eCourse)) {
			$properties[] = 'price';
		}

		return [$properties, $cLessonMissing, $programEmpty];

	}

	/**
	 * Ce qui est recommandé avant de publier sans rien bloquer, dans l'ordre où le panneau
	 * l'énumère : la fiche technique quand aucune de ses rubriques n'est écrite (`details`). Le prix,
	 * recommandé jusqu'au lot glacons-parcours, est exigé (`isPriceMissing()`). Aucune étape de
	 * `build()` ne lit cet inventaire : la publication passe sans lui.
	 */
	public static function getRecommendedForPublication(Course $eCourse): array {

		$eCourse->expects(self::getPropertiesDetails());

		$recommended = [];

		$detailsEmpty = (array_filter($eCourse->extracts(self::getPropertiesDetails()), fn($value) => $value !== NULL) === []);

		if($detailsEmpty) {
			$recommended[] = 'details';
		}

		return $recommended;

	}

	/**
	 * Une formation de réseau sans aucun montant : un montant nul est un prix, un financeur sans
	 * montant n'en est pas un. Même inventaire que l'étape `Course::status.priced`.
	 */
	public static function isPriceMissing(Course $eCourse): bool {

		$eCourse->expects(['priceFunded', 'price']);

		return (
			$eCourse->isNetwork() and
			$eCourse['priceFunded'] === NULL and
			$eCourse['price'] === NULL
		);

	}

	/**
	 * Les deux premiers objectifs de chaque formation, en texte, pour les cartes du catalogue.
	 * La liste entière se lit sur la fiche, par l'éditeur.
	 */
	public static function fillGoals(\Collection $cCourse, int $limit = 2): void {

		foreach($cCourse as $eCourse) {

			$goals = [];

			if($eCourse['objectives'] !== NULL) {

				$document = new \DOMDocument();

				if(@$document->loadXML($eCourse['objectives'])) {

					foreach($document->getElementsByTagName('li') as $node) {

						$goals[] = trim($node->textContent);

						if(count($goals) === $limit) {
							break;
						}

					}

				}

			}

			$eCourse['goals'] = $goals;

		}

	}

	/**
	 * La leçon où reprendre : la première qui n'est pas suivie, dans l'ordre du programme, choisie
	 * comme `getFirstLinked()` choisit la première. Sans leçon à suivre, l'élément est vide.
	 */
	public static function getResume(\Collection $cChapter, \Collection $cProgress, Course $eCourse): Lesson {
		return self::findLinked($cChapter, $eCourse, fn(Lesson $eLesson): bool => $cProgress->offsetExists($eLesson['id']) === FALSE);
	}

	public static function getFirst(\Collection $cChapter): Lesson {

		foreach($cChapter as $eChapter) {

			if($eChapter['cLesson']->notEmpty()) {
				return $eChapter['cLesson']->first();
			}

		}

		return new Lesson();

	}

	/**
	 * La leçon où mènent les boutons de tête de la fiche. Sans leçon, l'élément est vide.
	 */
	public static function getFirstLinked(\Collection $cChapter, Course $eCourse): Lesson {
		return self::findLinked($cChapter, $eCourse, fn(Lesson $eLesson): bool => TRUE);
	}

	/**
	 * La première leçon retenue par `$filter` que le lecteur peut ouvrir, en préférant une leçon
	 * écrite (`written`) : un bouton de tête ne mène à une page
	 * vide que s'il n'y a rien d'autre, et seulement pour le stagiaire qui peut la cocher
	 * (`Lesson::isLinked()`), faute de quoi il ne terminerait jamais la formation.
	 */
	private static function findLinked(\Collection $cChapter, Course $eCourse, \Closure $filter): Lesson {

		$eLessonLinked = new Lesson();

		foreach($cChapter as $eChapter) {

			foreach($eChapter['cLesson'] as $eLesson) {

				if($filter($eLesson) === FALSE) {
					continue;
				}

				if($eLesson['written']) {
					return $eLesson;
				}

				if(
					$eLessonLinked->empty() and
					$eLesson->isLinked($eCourse)
				) {
					$eLessonLinked = $eLesson;
				}

			}

		}

		return $eLessonLinked;

	}

	/**
	 * Qui délivre l'attestation de suivi (F69, porte L8 question 6) : le groupe auteur dès qu'il a un
	 * lien avec la personne, une promotion qui lui ouvre la formation, une inscription confirmée à
	 * l'une de ses dates ou, pour un réseau, l'inscription à sa formation en ligne. Sans aucun des
	 * trois, Ouvretaferme la délivre seule, un élément vide : la formation de l'équipe, ou celle d'un
	 * groupe suivie sans promotion ni date.
	 *
	 * L'attestation ne se délivre qu'à un inscrit (`Course::acceptAttestation()`) : celle d'une
	 * formation en ligne de réseau est donc toujours au nom du réseau, qui compte la personne parmi
	 * ses stagiaires, qu'elle soit venue par l'onglet du réseau, par sa page publique ou par le
	 * catalogue public (V-Learning, 10/10). Ni le catalogue seul ni l'ouverture aux membres ne sont un
	 * lien : ils ne décident rien ici.
	 *
	 * Le compte est passé et non lu en session : le PDF se rend par une adresse signée, sans compte
	 * connecté.
	 */
	public static function getIssuer(Course $eCourse, \user\User $eUser): \structure\Structure {

		if($eCourse->isTeam()) {
			return new \structure\Structure();
		}

		$linked = (
			ShareLib::getWindowByUserAndCourse($eUser, $eCourse)->notEmpty() or
			(
				$eCourse->isDated() and
				DateEnrollmentLib::hasConfirmed($eUser, $eCourse)
			) or
			(
				$eCourse->isNetwork() and
				$eCourse->isDated() === FALSE and
				EnrollmentLib::isEnrolled($eUser, $eCourse)
			)
		);

		if($linked === FALSE) {
			return new \structure\Structure();
		}

		return \structure\StructureLib::getById($eCourse['community']);

	}

	/**
	 * Une formation naît chez son auteur : l'équipe quand aucun groupe n'est donné, le groupe sinon.
	 * Qui peut l'y créer, `Course::canCreate()` le dit ; que le groupe existe, l'appelant le vérifie,
	 * sans quoi un id inconnu se lirait comme l'équipe.
	 */
	public static function getCreateElement(\structure\Structure $eStructure = new \structure\Structure(), string $type = Course::ONLINE): Course {

		// `status` est posé ici, et non lu par un défaut au point d'usage : les deux sens de
		// `classified` interrogent l'état de la formation pendant `build()`, y compris à la création,
		// où l'API passe aussi par cette méthode (`Course_0_1betaApi::getNewElement()`). `type` est
		// celui que l'étape du type a retenu dans le parcours de création, et que `build()` reprend du
		// formulaire de la formation. `theme` est vide tant que l'étape de la formation ne l'a pas
		// choisi : les listes du formulaire en déduisent les sous-thématiques (`fillChoices()`).
		return new Course([
			'community' => $eStructure,
			'type' => $type,
			'status' => Course::DRAFT,
			'theme' => new Theme()
		]);

	}

	/**
	 * Pose sur la formation si elle a une date planifiée à venir, que la pause de ses inscriptions
	 * demande à une formation datée (`Course::acceptUpdateEnrollmentPaused()`). Une formation en
	 * ligne n'en a jamais : elle ne paie pas la requête.
	 */
	public static function fillHasDateUpcoming(Course $eCourse): void {

		$eCourse['hasDateUpcoming'] = (
			$eCourse->isDated() and
			DateLib::countUpcomingByCourse($eCourse) > 0
		);

	}

	/**
	 * Pose sur la formation les listes de ses champs thématique, sous-thématique et niveau, que tout
	 * formulaire de la formation lit au rendu. Elles se posent ici et nulle part ailleurs : une liste
	 * ajoutée au formulaire se pose ainsi pour chacun d'eux. Les sous-thématiques sont celles de la
	 * thématique retenue, aucune tant qu'elle n'est pas choisie ; un autre choix les recharge par
	 * `LearningCourse.changeTheme()`.
	 */
	public static function fillChoices(Course $eCourse): void {

		$eCourse['cTheme'] = ThemeLib::getAll();
		$eCourse['cSubtheme'] = SubthemeLib::getByTheme($eCourse['theme']);
		$eCourse['cLevel'] = LevelLib::getAll();

	}

	/**
	 * La copie d'une formation dans son groupe : son programme, ses encadrants et ses fichiers, en
	 * brouillon. Ni partage, ni lecteur, ni progression : la copie ne suit plus l'original.
	 *
	 * `copiedFrom` désigne une formation de l'équipe, ou rien : la copie d'une formation de l'équipe
	 * la désigne, la copie d'une formation de groupe reprend ce que celle-ci désignait. L'origine se
	 * lit donc en un saut, sans chaîne de copies à remonter.
	 *
	 * `$eCourseNew` porte le groupe et le titre construits par la page. Les fichiers gardent leur
	 * hash : le média reste actif tant qu'une ligne le porte (`LearningAttachmentAiLib::clean()`), et
	 * retirer un fichier de l'une ne touche que sa ligne. La vignette, elle, est une image que chaque
	 * formation remplace ou retire pour son compte : elle prend un hash neuf, en dernière écriture,
	 * parce que ses fichiers copiés sur le disque survivent à un rollback.
	 */
	public static function duplicate(Course $eCourse, Course $eCourseNew): Course {

		$eCourse->expects(['community', 'copiedFrom']);
		$eCourseNew->expects(['community', 'name']);

		$eCourseCopy = self::getClone($eCourse)->merge([
			'community' => $eCourseNew['community'],
			'name' => $eCourseNew['name'],
			'status' => Course::DRAFT,
			'copiedFrom' => $eCourse->isTeam() ? $eCourse : $eCourse['copiedFrom'],
		]);

		Course::model()->beginTransaction();

			self::create($eCourseCopy);

			// Les phases d'abord, que les modules copiés désignent ; `create()` les range dans l'ordre de
			// l'original.
			$cPhaseCopy = new \Collection();

			foreach(PhaseLib::getByCourse($eCourse) as $ePhase) {

				$ePhaseCopy = self::getClone($ePhase)->merge(['course' => $eCourseCopy]);

				PhaseLib::create($ePhaseCopy);

				$cPhaseCopy[$ePhase['id']] = $ePhaseCopy;

			}

			$cChapterCopy = new \Collection();

			foreach(ChapterLib::getByCourse($eCourse) as $eChapter) {

				$eChapterCopy = self::getClone($eChapter)->merge([
					'course' => $eCourseCopy,
					'phase' => $eChapter['phase']->empty() ? new Phase() : $cPhaseCopy[$eChapter['phase']['id']]
				]);

				ChapterLib::create($eChapterCopy);

				$cChapterCopy[$eChapter['id']] = $eChapterCopy;

			}

			$cLesson = LessonLib::getByCourse($eCourse);
			$cLessonCopy = new \Collection();

			foreach($cLesson as $eLesson) {

				$eLessonCopy = self::getClone($eLesson)->merge([
					'course' => $eCourseCopy,
					'chapter' => $cChapterCopy[$eLesson['chapter']['id']]
				]);

				LessonLib::create($eLessonCopy);
				\block\BlockLib::duplicateByOwnerAndReference(BlockOwnerLib::LESSON, $eLesson['id'], $eLessonCopy['id']);

				// La copie des blocs ne passe pas par `touch()`, qui daterait la leçon : l'état écrit se
				// recalcule ici, sur les blocs que la copie porte désormais.
				LessonLib::synchronizeWritten($eLessonCopy);

				$cLessonCopy[$eLesson['id']] = $eLessonCopy;

			}

			foreach(StaffLib::getByCourse($eCourse) as $eStaff) {

				StaffLib::create(self::getClone($eStaff)->merge([
					'course' => $eCourseCopy,
					'supervisor' => $eStaff['supervisor']
				]));

			}

			foreach(AttachmentLib::getByCourse($eCourse) as $eAttachment) {

				AttachmentLib::create(self::getClone($eAttachment)->merge([
					'course' => $eCourseCopy,
					'lesson' => $cLessonCopy[$eAttachment['lesson']['id']]
				]));

			}

			// Les questions d'une leçon rendue à la déclaration partent aussi : elles sont gardées,
			// donc copiées (arbitrage de la porte de L5). Les images gardent leur hash, comme les
			// fichiers. `create()` range chaque question en dernier, dans l'ordre de lecture.
			foreach(QuestionLib::getByLessons($cLesson) as $eQuestion) {

				$cChoiceCopy = new \Collection();

				foreach($eQuestion['cChoice'] as $eChoice) {
					$cChoiceCopy[] = self::getClone($eChoice);
				}

				QuestionLib::create(self::getClone($eQuestion)->merge([
					'lesson' => $cLessonCopy[$eQuestion['lesson']['id']]
				]), $cChoiceCopy);

			}

			if($eCourse['vignette'] !== NULL) {

				$eCourseCopy['vignette'] = $eCourse['vignette'];

				new \media\LearningCourseLib()->duplicate($eCourseCopy);

				self::update($eCourseCopy, ['vignette']);

			}

		Course::model()->commit();

		return $eCourseCopy;

	}

	/**
	 * Les propriétés de l'élément moins celles que la copie ne reprend pas, sur le modèle de
	 * `catalog\OfferLib::getClone()` : sans `build()`, ce sont des valeurs que l'original a déjà
	 * passées.
	 */
	private static function getClone(\Element $e): \Element {

		$class = $e->getModule();

		$propertiesKeep = array_diff(
			$class::model()->getProperties(),
			self::PROPERTIES_NOT_COPIED[$class]
		);

		$e->expects($propertiesKeep);

		return new $class($e->extracts($propertiesKeep));

	}

	/**
	 * La place au catalogue se décide à la naissance et d'après l'auteur : une formation de l'équipe y
	 * est d'office, une formation de réseau n'y entre que retenue par l'équipe (`updateCatalog()`).
	 */
	public static function create(Course $e): void {

		$e->expects(['community']);

		$e['catalog'] = $e->isTeam() ? Course::ACCEPTED : Course::PRIVATE;

		parent::create($e);

	}

	/**
	 * La marque de chaque formation dans la liste du réseau (`catalogBadge`, lot L14) : l'état du
	 * catalogue qu'elle signale, ou NULL. Une formation privée ne se signale pas, c'est l'état de
	 * toutes les autres, sauf retirée par l'équipe : le motif de la décision est sur sa page, et une
	 * décision de l'équipe ne doit pas rester invisible depuis la liste. Il tombe à la demande
	 * suivante, qui efface le motif (`updateCatalog()`). Seule une formation de réseau a un circuit :
	 * celles de l'équipe sont au catalogue d'office et n'ont pas de page.
	 *
	 * Un brouillon ne porte aucune marque : le catalogue ne montre pas une formation dépubliée, et
	 * l'acceptation qu'elle garde ne revaut qu'à sa republication.
	 */
	public static function fillCatalogBadge(\Collection $cCourse): void {

		foreach($cCourse as $eCourse) {

			$eCourse->expects(['status', 'catalog', 'catalogReason']);

			if(
				$eCourse['status'] === Course::DRAFT or
				$eCourse->acceptCatalog() === FALSE or
				($eCourse['catalog'] === Course::PRIVATE and $eCourse['catalogReason'] === NULL)
			) {
				$eCourse['catalogBadge'] = NULL;
			} else {
				$eCourse['catalogBadge'] = $eCourse['catalog'];
			}

		}

	}

	/**
	 * Un passage du circuit du catalogue public (lot L14), `catalog` construit par le geste. Il date
	 * le passage, que la page du réseau et l'onglet de l'administration disent (« Demande envoyée
	 * le », « Refusée le »…). Le motif n'accompagne qu'un refus ou un retrait par l'équipe
	 * (`$withReason`), qui l'ont construit ; tout autre passage efface celui d'avant, que le réseau
	 * ne doit plus lire une fois qu'il a redemandé ou que l'équipe a accepté.
	 *
	 * `updatedAt` ne bouge pas : la fiche date de là sa dernière mise à jour, et une décision sur le
	 * catalogue ne change rien de ce que la formation enseigne.
	 */
	public static function updateCatalog(Course $e, bool $withReason = FALSE): void {

		$e->expects(['catalog']);

		if($withReason === FALSE) {
			$e['catalogReason'] = NULL;
		}

		$e['catalogAt'] = new \Sql('NOW()');

		$properties = ['catalog', 'catalogReason', 'catalogAt'];

		// Une formation qui quitte le catalogue quitte aussi « Premiers pas » : y revenir est une
		// nouvelle demande, et un nouveau choix de l'équipe.
		if($e['catalog'] !== Course::ACCEPTED) {
			$e['featured'] = FALSE;
			$e['featuredAt'] = NULL;
			$properties[] = 'featured';
			$properties[] = 'featuredAt';
		}

		Course::model()
			->select($properties)
			->update($e);

	}

	/**
	 * L'entrée ou la sortie de « Premiers pas », `featured` construit par le geste. `featuredAt` date
	 * l'entrée, qui fait l'ordre de la rangée au catalogue : la dernière choisie passe en dernier.
	 * `updatedAt` ne bouge pas, pour la même raison que dans `updateCatalog()`.
	 */
	public static function updateFeatured(Course $e): void {

		$e->expects(['featured']);

		$e['featuredAt'] = $e['featured'] ? new \Sql('NOW()') : NULL;

		Course::model()
			->select(['featured', 'featuredAt'])
			->update($e);

	}

	public static function update(Course $e, array $properties): void {

		// « Formation payante » n'est pas une colonne : elle a déjà posé les quatre champs du prix, et
		// à non les écrit tous, même ceux que l'appelant n'a pas nommés.
		if(
			in_array('paid', $properties) and
			$e['paid'] === FALSE
		) {
			$properties = array_values(array_unique([...$properties, ...self::getPropertiesPrice()]));
		}

		array_delete($properties, 'paid');

		// La date de publication est celle de la première : repasser en brouillon puis republier
		// ne la déplace pas.
		if(
			in_array('status', $properties) and
			$e['status'] === Course::PUBLISHED
		) {

			$e['publishedAt'] = new \Sql('IFNULL(publishedAt, NOW())');
			$properties[] = 'publishedAt';

		}

		// Une formation dépubliée quitte « Premiers pas », comme celle qui sort du catalogue
		// (`updateCatalog()`) : republiée, elle n'y revient que par un nouveau choix de l'équipe.
		if(
			in_array('status', $properties) and
			$e['status'] === Course::DRAFT
		) {

			$e['featured'] = FALSE;
			$e['featuredAt'] = NULL;
			$properties[] = 'featured';
			$properties[] = 'featuredAt';

		}

		// Une formation en ligne ne se ferme pas à l'inscription (`Course::isExternal()`) : passée en
		// ligne avant sa première date, elle perd le réglage, qu'un retour en présentiel ne doit pas
		// retrouver fermé à son insu.
		if(
			in_array('type', $properties) and
			$e['type'] === Course::ONLINE
		) {

			$e['externalOnly'] = FALSE;
			$properties[] = 'externalOnly';

		}

		// L'adresse de l'organisme n'a de sens que sur son chemin (retour de Vincent du 30/09) : une
		// inscription qui revient sur Ouvretaferme l'efface, qu'elle ait été postée ou non, pour que
		// la formation ne garde pas une page que personne ne voit plus.
		if(
			in_array('externalOnly', $properties) and
			$e['externalOnly'] === FALSE
		) {

			$e['externalUrl'] = NULL;
			$properties = array_unique([...$properties, 'externalUrl']);

		}

		// Une formation ne porte qu'une sous-thématique de sa thématique : une thématique changée sans
		// sous-thématique construite (l'API, la fusion des thématiques) vide celle qui ne lui
		// appartient plus. Elle se lit en base, la fusion ne passant qu'un élément réduit à son id.
		if(
			in_array('theme', $properties) and
			in_array('subtheme', $properties) === FALSE
		) {

			$eSubtheme = Course::model()
				->select(['subtheme' => ['theme']])
				->whereId($e)
				->get()['subtheme'];

			if(
				$eSubtheme->notEmpty() and
				$eSubtheme['theme']->is($e['theme']) === FALSE
			) {

				$e['subtheme'] = new Subtheme();
				$properties[] = 'subtheme';

			}

		}

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * La marque d'une formation datée suit les modes des séances de ses dates (lot date-mixte,
	 * décision B de Vincent du 03/10) : ceux des dates planifiées à venir, sinon ceux de la dernière
	 * date planifiée, celle qu'on relit une fois tout passé ; sans date planifiée, la marque ne bouge
	 * pas, la formation se présentant encore comme elle s'est présentée. Les deux modes font
	 * `MIXED`, un seul fait son type. Appelée à chaque écriture d'une date, et chaque heure par le
	 * cron pour les dates qui viennent de finir (`synchronizeTypeOfEnded()`).
	 *
	 * L'écriture passe par le modèle et non par `update()` : `updatedAt` dit quand la formation a
	 * changé ce qu'elle enseigne, et une marque recalculée n'en change rien. Rien n'est écrit quand
	 * la marque est déjà la bonne.
	 */
	public static function synchronizeType(Course $e): void {

		$e->expects(['id', 'type']);

		if($e->isDated() === FALSE) {
			return;
		}

		$mDateUpcoming = DateLib::applyUpcoming(new DateModel())
			->select('id')
			->whereCourse($e);

		$modes = self::getSequenceModes($mDateUpcoming);

		if($modes === []) {

			// Lue d'abord : MySQL refuse un `LIMIT` dans une sous-requête `IN`.
			$eDateLast = new DateModel()
				->select('id')
				->whereCourse($e)
				->whereStatus(Date::PLANNED)
				->sort(['startAt' => SORT_DESC, 'id' => SORT_DESC])
				->get();

			if($eDateLast->empty()) {
				return;
			}

			$modes = self::getSequenceModes($eDateLast);

		}

		if($modes === []) {
			return;
		}

		$type = (count($modes) > 1) ? Course::MIXED : match($modes[0]) {
			DateSequence::ONSITE => Course::ONSITE,
			DateSequence::REMOTE => Course::REMOTE,
		};

		if($type === $e['type']) {
			return;
		}

		$e['type'] = $type;

		Course::model()
			->select(['type'])
			->update($e);

	}

	/**
	 * Les modes des séances d'une date, ou des dates que la requête désigne, sans doublon, dans
	 * l'ordre de `DateSequence::mode`. La requête des dates est citée (`getSql()`), dans la même base.
	 *
	 * @return string[]
	 */
	private static function getSequenceModes(DateModel|Date $date): array {

		$mDateSequence = new DateSequenceModel()
			->select('mode')
			->group('mode');

		if($date instanceof DateModel) {
			$mDateSequence->whereDate('IN', $date->getSql());
		} else {
			$mDateSequence->whereDate($date);
		}

		$cDateSequence = $mDateSequence->getCollection();

		$modes = [];

		foreach($cDateSequence as $eDateSequence) {
			$modes[] = $eDateSequence['mode'];
		}

		return array_values(array_intersect([DateSequence::ONSITE, DateSequence::REMOTE], $modes));

	}

	/**
	 * Le temps qui passe change la marque sans que personne écrive : une date qui finit sort des
	 * dates à venir. Recalcule les formations dont une date planifiée a fini dans les deux dernières
	 * heures, pour le cron horaire : le second passage rattrape un passage manqué. Les dates se
	 * lisent sur l'index `startAt`, borné des deux côtés pour que la plage n'emporte pas toutes les
	 * dates à venir : une date finie a commencé, et ne dure jamais 60 jours.
	 */
	public static function synchronizeTypeOfEnded(): void {

		$cCourse = Course::model()
			->select(Course::getSelection())
			->whereId('IN', new DateModel()
				->select('course')
				->whereStatus(Date::PLANNED)
				->where('startAt BETWEEN NOW() - INTERVAL 60 DAY AND NOW()')
				->where('endAt BETWEEN NOW() - INTERVAL 2 HOUR AND NOW()')
				->getSql())
			->getCollection();

		foreach($cCourse as $eCourse) {
			self::synchronizeType($eCourse);
		}

	}

	/**
	 * Une leçon écrite ou retirée change la formation : la fiche date sa dernière mise à jour de là.
	 */
	public static function touch(Course $e): void {

		self::update($e, []);

	}

	/**
	 * Une formation dont une date porte une feuille signée ne s'efface pas : les signatures sont la
	 * preuve des présences, elle se dépublie. Le refus vient en tête, avant tout effacement, pour ne
	 * pas laisser une formation à moitié retirée ; il se juge ici et non dans un `acceptDelete()`,
	 * que l'API et la page valident sur une formation qui ne porte pas le compte des feuilles.
	 */
	public static function delete(Course $e): void {

		Course::model()->beginTransaction();

			if(DateSheet::model()->whereCourse($e)->exists()) {
				Course::model()->commit();
				Course::fail('deleteSheets');
				return;
			}

			foreach(ChapterLib::getByCourse($e) as $eChapter) {
				ChapterLib::deleteWithCourse($eChapter);
			}

			PhaseLib::deleteByCourse($e);

			// Les liaisons partent, les personnes restent à leur réseau : elles encadrent ou encadreront
			// d'autres formations.
			foreach(StaffLib::getByCourse($e) as $eStaff) {
				StaffLib::delete($eStaff);
			}

			foreach(EnrollmentLib::getByCourse($e) as $eEnrollment) {
				EnrollmentLib::delete($eEnrollment);
			}

			ShareLib::deleteByCourse($e);
			AnnouncementLib::deleteByCourse($e);
			ReviewLib::deleteByCourse($e);
			DateLib::deleteByCourse($e);

			// Écriture de masse, donc un tableau de valeurs : un élément porteur d'un id la bornerait
			// à une ligne. Les copies survivent à leur original et cessent de le désigner.
			Course::model()
				->whereCopiedFrom($e)
				->update(['copiedFrom' => NULL]);

			parent::delete($e);

		Course::model()->commit();

	}

	/**
	 * Le menu du compte propose « Mes formations » quand les formations sont ouvertes à tous, ou quand
	 * le compte appartient à une structure de `LearningSetting::STRUCTURE_BETA` qui accepte les
	 * formations (`acceptLearning()` : le type du groupe et son interrupteur), ou à une ferme
	 * validement rattachée à une telle structure : c'est par ces structures que les formations lui
	 * parviennent (`ShareLib::getByUser()`). `$ccStructure` est la liste des structures du compte par
	 * type (`StructureLib::getOnline()`), déjà chargée pour le menu. La liste d'OTF est croisée
	 * d'abord : seules les structures qu'elle retient lisent leur fonctionnalité, et les groupes
	 * atteints par un rattachement ne se chargent que s'ils y figurent.
	 */
	public static function isOpenByStructures(\Collection $ccStructure): bool {

		if(LearningSetting::$active) {
			return TRUE;
		}

		if(LearningSetting::STRUCTURE_BETA === []) {
			return FALSE;
		}

		$cStructure = new \Collection();

		foreach($ccStructure as $cStructureType) {
			foreach($cStructureType as $eStructure) {
				$cStructure[$eStructure['id']] = $eStructure;
			}
		}

		foreach($cStructure as $eStructure) {

			if(
				in_array($eStructure['id'], LearningSetting::STRUCTURE_BETA) and
				$eStructure->acceptLearning()
			) {
				return TRUE;
			}

		}

		$communities = [];

		foreach(\structure\AffiliationAiLib::getValidByStructureProducers($cStructure) as $cAffiliation) {
			$communities = array_merge($communities, array_intersect($cAffiliation->getKeys(), LearningSetting::STRUCTURE_BETA));
		}

		foreach(\structure\StructureLib::getByIds(array_unique($communities)) as $eStructureCommunity) {

			if($eStructureCommunity->acceptLearning()) {
				return TRUE;
			}

		}

		return FALSE;

	}

}
?>
