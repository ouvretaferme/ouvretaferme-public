<?php
namespace learning;

/**
 * Deux contrats de l'API des formations, qu'aucun code ne rappelle à qui ajoutera une propriété ou
 * une ressource dans un lot suivant :
 * - ce qui s'écrit se relit. Une propriété de `getPropertiesCreate('api')` ou de
 *   `getPropertiesUpdate('api')` absente de `element()` s'écrirait sans que le client puisse
 *   jamais relire ce qu'il a envoyé ; une propriété rendue sans s'écrire est déclarée ici en
 *   lecture seule, avec sa raison ;
 * - chaque fichier `api/*.a.php` pose `Course_0_1betaApi::validateStructure()` dans `getNewModel()` et
 *   `getNewElement()`, par où passe toute route. Ses routes se déclarent avant toute clé, donc pour
 *   toute structure : un fichier qui l'oublierait s'ouvrirait à une structure fermée aux formations ;
 * - les ressources déclarées en lecture seule n'ont aucune route d'écriture. `ModuleApi` n'appelle
 *   aucun `accept*` en lecture : une route d'écriture ajoutée à une ressource pensée pour être lue
 *   n'aurait aucune règle d'état devant elle ;
 * - certaines propriétés ne sont jamais rendues, quelle que soit la ressource qui les porte.
 *
 * Lancement : dev/test package=learning file=ApiPropertySentinel
 */
class ApiPropertySentinelTest extends \Test {

	/**
	 * Les propriétés rendues par l'API qui ne s'y écrivent pas, et pourquoi.
	 */
	const READ_ONLY = [
		'Course.id' => 'posé par la base',
		'Course.publishedAt' => 'date de la première publication, posée par CourseLib::update() quand l\'état passe à publié',
		'Course.updatedAt' => 'posé par CourseLib::update() à chaque écriture de la formation, de ses modules ou de ses leçons',
		'Course.access' => 'mention de l\'indicateur 1 de Qualiopi, écrite à l\'écran seulement ; dans la liste d\'API, elle deviendrait exigible à chaque PUT (framework/core/Api.php, convertProperties())',
		'Course.accessibility' => 'mention de l\'indicateur 1 de Qualiopi, écrite à l\'écran seulement, pour la même raison que Course.access',
		'Course.recognition' => 'mention de l\'indicateur 1 de Qualiopi, écrite à l\'écran seulement, pour la même raison que Course.access',
		'Course.offer' => 'l\'offre d\'une formation en ligne (lot L15a), choisie à l\'écran seulement, où vivent ses verrous : compte de reversement, catalogue public',
		'Course.externalOnly' => 'le réglage de l\'inscription (lot annuaire), posé à l\'écran seulement, où l\'étape externalOnly.catalog garde le catalogue public',
		'Course.externalUrl' => 'l\'adresse de la formation sur le site de l\'organisme, posée avec le réglage à l\'écran seulement, pour la même raison que Course.externalOnly',
		'Course.enrollmentPaused' => 'la pause des inscriptions (lot T13), réglée à l\'écran seulement, sous Course::acceptUpdateEnrollmentPaused() qui la cache hors des dates d\'inscription ; dans la liste d\'API, elle deviendrait exigible à chaque PUT (framework/core/Api.php, convertProperties())',
		'Course.subtheme' => 'la sous-thématique (lot T1), rendue en lecture et choisie à l\'écran seulement : dans la liste d\'API, elle deviendrait exigible à chaque PUT (framework/core/Api.php, convertProperties()), et un PUT qui change la thématique la vide (CourseLib::update())',
		'Chapter.id' => 'posé par la base',
		'Chapter.position' => 'l\'ordre du programme, posé par ChapterLib::create() et changé seulement à l\'écran',
		'Faq.id' => 'posé par la base',
		'Faq.community' => 'la structure de la clé, posée à la création ; une question ne change pas de FAQ',
		'Lesson.id' => 'posé par la base',
		'Lesson.course' => 'la formation du module, que l\'étape chapter.owned déduit ; une leçon ne change jamais de formation',
		'Lesson.position' => 'l\'ordre dans le module, posé par LessonLib::create() et update() et changé seulement à l\'écran',
		'Lesson.updatedAt' => 'posé par LessonLib::update()',
		'Lesson.format' => 'la forme que donne le contenu de la leçon, calculée à la lecture par LessonLib::getFormat()',
		'Lesson.objective' => 'écrit à l\'écran seulement ; dans la liste d\'API, il deviendrait exigible à chaque PUT d\'une leçon (framework/core/Api.php, convertProperties())',
	];

	/**
	 * Les propriétés que l'API écrit sans les rendre, et pourquoi.
	 */
	const WRITE_ONLY = [
		'Course.paid' => '« Formation payante », pseudo-propriété sans colonne que `build()` lit pour ramener les montants à 0 : priceFunded et price la rendent',
	];

	/**
	 * Les fichiers d'API du package, qui doivent tous poser `Course_0_1betaApi::validateStructure()`.
	 */
	const FILES = [
		'Block_0_1beta.a.php',
		'Chapter_0_1beta.a.php',
		'Course_0_1beta.a.php',
		'DateEnrollment_0_1beta.a.php',
		'Date_0_1beta.a.php',
		'Faq_0_1beta.a.php',
		'Lesson_0_1beta.a.php',
		'Phase_0_1beta.a.php',
	];

	/**
	 * Les ressources qui ne s'écrivent pas par l'API, et pourquoi.
	 */
	const READ_ONLY_RESOURCES = [
		'Date' => 'une date se programme et s\'annule à l\'écran, qui prévient les inscrits par e-mail (lot L10e)',
		'DateEnrollment' => 'une inscription se fait par la personne et se confirme ou s\'annule à l\'écran du réseau, qui lui écrit (lot L10e)',
	];

	/**
	 * Les propriétés qu'aucune déclaration `element()` ne rend, et pourquoi.
	 */
	const FORBIDDEN = [
		'DateEnrollment.token' => 'adresse porteuse qui relit et annule la ligne depuis l\'e-mail : la rendre donnerait ce geste à tout détenteur de la clé',
		'DateEnrollment.user' => 'id d\'un compte Ouvretaferme, dont le réseau n\'a pas l\'usage et qu\'il ne lit ni à l\'écran ni dans l\'export',
		'DateEnrollment.attendedBy' => 'id du compte qui a pointé la présence, même raison que `user`',
	];

	/**
	 * La première déclaration `element()` de chaque classe d'API du package, par module.
	 */
	private function getElements(): array {

		foreach(self::FILES as $file) {
			$class = __NAMESPACE__.'\\'.basename($file, '.a.php').'Api';
			new $class();
		}

		$property = new \ReflectionProperty(\Api::class, 'elements');

		$elements = [];

		foreach($property->getValue() as $module => $list) {

			if(str_starts_with($module, __NAMESPACE__.'\\') === FALSE) {
				continue;
			}

			$elements[substr($module, strlen(__NAMESPACE__) + 1)] ??= ($list[0]['properties'])();

		}

		return $elements;

	}

	private function getWritable(string $module): array {

		$lib = __NAMESPACE__.'\\'.$module.'Lib';

		$properties = [];

		foreach([$lib::getPropertiesCreate('api'), $lib::getPropertiesUpdate('api')] as $list) {

			// La liste de modification d'une formation dépend de son auteur : ce sont celles d'une école
			// et d'un réseau que lit l'API, jamais celle de l'équipe. Le réseau y ajoute le prix.
			if($list instanceof \Closure) {

				foreach([\structure\Structure::EDUCATION, \structure\Structure::NETWORK] as $communityType) {
					$properties = array_merge($properties, $list(new Course(['community' => new \structure\Structure(['id' => 1, 'type' => \structure\Structure::COMMUNITY, 'communityType' => $communityType])])));
				}

				continue;

			}

			$properties = array_merge($properties, $list);

		}

		return array_values(array_unique($properties));

	}

	public function testWrittenIsRendered(): void {

		if(LearningSetting::$active === FALSE) {
			$this->assertFail('LearningSetting::$active est faux : aucune route ni aucun élément n\'est déclaré');
			return;
		}

		$elements = $this->getElements();

		$notRendered = [];
		$undeclared = [];
		$stale = [];

		foreach(['Course', 'Chapter', 'Lesson'] as $module) {

			$rendered = $elements[$module] ?? [];
			$writable = $this->getWritable($module);

			foreach(array_diff($writable, $rendered) as $property) {

				if(array_key_exists($module.'.'.$property, self::WRITE_ONLY) === FALSE) {
					$notRendered[] = $module.'.'.$property;
				}

			}

			foreach(array_diff($rendered, $writable) as $property) {

				if(array_key_exists($module.'.'.$property, self::READ_ONLY) === FALSE) {
					$undeclared[] = $module.'.'.$property;
				}

			}

			foreach(array_keys(self::READ_ONLY) as $key) {

				[$moduleDeclared, $property] = explode('.', $key);

				if(
					$moduleDeclared === $module and
					(in_array($property, $rendered, TRUE) === FALSE or in_array($property, $writable, TRUE))
				) {
					$stale[] = $key;
				}

			}

		}

		foreach(array_keys(self::WRITE_ONLY) as $key) {

			[$module, $property] = explode('.', $key);

			if(
				in_array($property, $elements[$module] ?? [], TRUE) or
				in_array($property, $this->getWritable($module), TRUE) === FALSE
			) {
				$stale[] = $key;
			}

		}

		$this->assertEquals([], $notRendered, 'écrites par l\'API sans être rendues : '.implode(', ', $notRendered));
		$this->assertEquals([], $undeclared, 'rendues sans s\'écrire, à déclarer dans READ_ONLY avec leur raison : '.implode(', ', $undeclared));
		$this->assertEquals([], $stale, 'déclarées en lecture ou en écriture seule, et qui ne le sont plus : '.implode(', ', $stale));

	}

	public function testReadOnlyResources(): void {

		if(LearningSetting::$active === FALSE) {
			$this->assertFail('LearningSetting::$active est faux : aucune route n\'est déclarée');
			return;
		}

		$this->getElements();

		$routes = new \ReflectionProperty(\Api::class, 'routes')->getValue();

		$written = [];

		foreach(['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {

			foreach($routes[$method] as $route) {
				$written[$route['class']][] = $method.' '.$route['requestRoute'];
			}

		}

		$readOnly = [];
		$writtenDeclared = [];

		foreach(self::FILES as $file) {

			$module = substr($file, 0, strpos($file, '_'));
			$class = __NAMESPACE__.'\\'.basename($file, '.a.php').'Api';

			if(array_key_exists($class, $written) === FALSE) {
				$readOnly[] = $module;
			} else if(array_key_exists($module, self::READ_ONLY_RESOURCES)) {
				$writtenDeclared[] = $module.' ('.implode(', ', $written[$class]).')';
			}

		}

		$undeclared = array_values(array_diff($readOnly, array_keys(self::READ_ONLY_RESOURCES)));
		$stale = array_values(array_diff(array_keys(self::READ_ONLY_RESOURCES), $readOnly));

		$this->assertEquals([], $writtenDeclared, 'déclarées en lecture seule, mais écrites par une route : '.implode(', ', $writtenDeclared));
		$this->assertEquals([], $undeclared, 'sans route d\'écriture, à déclarer dans READ_ONLY_RESOURCES avec leur raison : '.implode(', ', $undeclared));
		$this->assertEquals([], $stale, 'déclarées en lecture seule, mais sans fichier d\'API ou écrites : '.implode(', ', $stale));

	}

	public function testForbiddenNotRendered(): void {

		if(LearningSetting::$active === FALSE) {
			$this->assertFail('LearningSetting::$active est faux : aucun élément n\'est déclaré');
			return;
		}

		$this->getElements();

		$elements = new \ReflectionProperty(\Api::class, 'elements')->getValue();

		$rendered = [];
		$stale = [];

		foreach(array_keys(self::FORBIDDEN) as $key) {

			[$module, $property] = explode('.', $key);

			$class = __NAMESPACE__.'\\'.$module;

			// Une déclaration sur une propriété que le module ne porte plus ne protège plus rien.
			if(in_array($property, $class::model()->getProperties(), TRUE) === FALSE) {
				$stale[] = $key;
				continue;
			}

			foreach($elements[$class] ?? [] as $element) {

				if(
					in_array($property, ($element['properties'])(), TRUE) or
					array_key_exists($property, $element['correlations'])
				) {
					$rendered[] = $key;
				}

			}

		}

		// Chaque instanciation de la classe d'API redéclare ses éléments : une propriété rendue se nomme une fois.
		$rendered = array_values(array_unique($rendered));

		$this->assertEquals([], $rendered, 'rendues par l\'API alors qu\'elles ne doivent jamais l\'être : '.implode(', ', $rendered));
		$this->assertEquals([], $stale, 'déclarées dans FORBIDDEN sur une propriété que le module ne porte plus : '.implode(', ', $stale));

	}

	public function testFilesValidateStructure(): void {

		$found = array_map('basename', glob(__DIR__.'/../api/*.a.php'));

		$undeclared = array_values(array_diff($found, self::FILES));
		$missing = array_values(array_diff(self::FILES, $found));

		$this->assertEquals([], $undeclared, 'fichiers d\'API à déclarer dans FILES : '.implode(', ', $undeclared));
		$this->assertEquals([], $missing, 'fichiers déclarés dans FILES qui n\'existent plus : '.implode(', ', $missing));

		$unguarded = [];

		foreach($found as $file) {

			$content = file_get_contents(__DIR__.'/../api/'.$file);

			foreach(['getNewModel', 'getNewElement'] as $method) {

				// Le corps de la méthode, jusqu'à la méthode suivante ou la fin de la classe, puis l'appel
				// sur une ligne de code à lui seul : une garde commentée ne compte pas.
				if(
					preg_match('/public function '.$method.'\([^)]*\)[^{]*\{(.*?)\n\t\}\n/s', $content, $match) === 0 or
					preg_match('/^\t\t(self|Course_0_1betaApi)::validateStructure\(\$data\);$/m', $match[1]) === 0
				) {
					$unguarded[] = $file.' '.$method.'()';
				}

			}

		}

		$this->assertEquals([], $unguarded, 'méthodes d\'API qui ne posent pas validateStructure() : '.implode(', ', $unguarded));

	}

}
?>
