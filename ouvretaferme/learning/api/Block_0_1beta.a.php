<?php
namespace learning;

/**
 * Les blocs des leçons des formations du groupe de la clé, un par un : un enfant (une étape, une
 * réponse, un volet…) désigne son parent par `parent`, et les blocs d'une leçon se listent à plat
 * dans `Lesson_0_1betaApi` (`lesson/{id}/blocksList`). C'est le moteur des blocs qui juge et qui écrit,
 * comme à l'écran : la route pose ce que pose la page de l'éditeur (`base/block/page/block.p.php`),
 * la leçon dans `eOwner` et le parent entier, et construit ce que le type déclare.
 *
 * Un bloc neuf se range en dernier dans sa fratrie, et l'ordre ne se change qu'à l'écran. Un parent
 * naît avec son premier enfant, que le corps décrit par `title` et `content` (`BlockLib::create()`),
 * et le retrait de son dernier enfant l'emporte. `type`, `lesson` et `parent` ne changent jamais.
 *
 * `settings` est exigé à la création d'un `block-item` et à sa modification complète : `{}`, ou pour
 * une étape une ou deux clés (`\block\BlockTypeLib::castItem()`) :
 * - `at`, l'instant de la vidéo de la leçon où son geste se montre, en secondes ou écrit `m:ss`, rendu
 *   toujours en secondes ;
 * - `marks`, les repères de sa capture, en pourcentage de l'image : `{"type": "box", "x", "y", "w",
 *   "h"}` pour un cadre (coin haut gauche, largeur, hauteur), `{"type": "ring", "x", "y"}` pour un
 *   anneau (son centre), au plus `\block\BlockSetting::STEP_MARKS_MAX`.
 * Le réglage posté remplace le réglage entier : qui ne change que les repères renvoie l'instant. L'un
 * comme l'autre sous un autre parent qu'une étape est refusé, et une leçon ne porte qu'une vidéo
 * (`Block::url.video`).
 *
 * `media` se lit, et ne s'écrit que par `block/{id}/media`, sur un `block-item` seul
 * (`Block::acceptMedia()`) : aucune liste de construction ne le porte, et un `media` posté avec le
 * reste du corps est ignoré. Une capture remplacée garde les repères, une capture retirée les emporte.
 */
class Block_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->create('block');
		$this->read('block/{id}');
		$this->update('block/{id}');
		$this->delete('block/{id}');

		// Poser et remplacer sont le même geste, d'où PUT, comme pour la vignette d'un produit
		// (`Product_0_1betaApi`), et l'image a sa propre route pour la même raison : elle ne se mêle pas
		// aux quelques kilooctets des autres champs.
		$this->requestPut('block/{id}/media', [], function(\ApiData $data) {

			self::validateMedia($data->e);

			$file = $this->getMediaFile($data);

			try {
				\block\BlockLib::putMedia($data->e, $file);
			} finally {
				unlink($file);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestDelete('block/{id}/media', [], function(\ApiData $data) {

			self::validateMedia($data->e);

			\block\BlockLib::deleteMedia($data->e);

			$data->pushObject(NULL, $this->getById($data));

		});

		// `lesson` est la leçon qui porte le bloc (`reference`), `media` l'adresse de son image.
		$this->element(
			'block\Block',
			fn() => [
				'id', 'lesson', 'parent',
				'type', 'variant', 'title', 'content', 'url', 'settings', 'media',
				'position'
			],
			[
				'lesson' => fn(\ApiData $data, \block\Block $e) => $e['reference'],
				'media' => fn(\ApiData $data, \block\Block $e) => new \media\BlockItemUi()->getUrlByHash($e['media'], 'm'),
			]
		);

	}

	/**
	 * Un bloc n'atteint qu'une leçon d'une formation du groupe : l'usage et l'objet bornent ensemble,
	 * `reference` n'ayant de sens que sous son usage.
	 */
	public function getNewModel(\ApiData $data): \block\BlockModel {

		Course_0_1betaApi::validateStructure($data);

		return new \block\BlockModel()
			->whereOwner(BlockOwnerLib::LESSON)
			->whereReference('IN', $this->getLessonModel($data)->select('id')->getSql());

	}

	/**
	 * La leçon et le parent se posent dans `load()`, une fois le corps lu.
	 */
	public function getNewElement(\ApiData $data): \block\Block {

		Course_0_1betaApi::validateStructure($data);

		return new \block\Block([
			'owner' => BlockOwnerLib::LESSON,
			'type' => NULL,
		]);

	}

	/**
	 * Comme `applyElement()` de la page de l'éditeur : les droits et l'état sont ceux de la leçon
	 * (`Block::acceptWrite()`), et le parent entier porte le type que lisent `title.missing` et le
	 * plafond de ses enfants.
	 */
	public function getById(\ApiData $data): \block\Block {

		$eBlock = parent::getById($data);
		$eBlock['eOwner'] = BlockOwnerLib::getOwner($eBlock['owner'], $eBlock['reference']);
		$eBlock['parent'] = \block\BlockLib::getParent($eBlock);

		return $eBlock;

	}

	/**
	 * Un bloc neuf a besoin de sa leçon et de son parent avant que ses propriétés soient construites :
	 * `acceptCreate()` juge l'état de la leçon, et la place décide des propriétés construites
	 * (`getPropertiesCreate()`). `getNewElement()` ne peut pas s'en charger, `ModuleApi::load()`
	 * l'appelant avant d'avoir lu le corps.
	 *
	 * Les deux se relisent dans le bornage de la clé : une leçon d'un autre groupe est introuvable, comme
	 * un parent d'une autre leçon.
	 */
	public function load(\ApiData $data, array $routeInfo): void {

		parent::load($data, $routeInfo);

		if($routeInfo['id']) {
			return;
		}

		$fw = new \FailWatch();

		$this->getArgument($data, $data->body, 'lesson', new \FilterValidator('serial'));

		// Sans parent, ou un parent à `null`, le bloc est de premier niveau.
		if(($data->body['parent'] ?? NULL) !== NULL) {
			$this->getArgument($data, $data->body, 'parent', new \FilterValidator('serial'));
		}

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \ResourceApiException($fw));

		$eLesson = $this->getLessonModel($data)
			->select(Lesson::getSelection())
			->whereId($data->get('lesson'))
			->get();

		if($eLesson->empty()) {
			throw new \ApiException('Unknown lesson', 'unknown_lesson', 404);
		}

		if($data->exists('parent')) {

			$eBlockParent = $this->getNewModel($data)
				->select(\block\Block::getSelection())
				->whereReference($eLesson['id'])
				->whereId($data->get('parent'))
				->get();

			if($eBlockParent->empty()) {
				throw new \ApiException('Unknown parent', 'unknown_parent', 404);
			}

		} else {
			$eBlockParent = new \block\Block();
		}

		$data->e->merge([
			'reference' => $eLesson['id'],
			'eOwner' => $eLesson,
			'parent' => $eBlockParent,
		]);

	}

	/**
	 * Le type posté, puis ce qu'il écrit s'il est admis à cette place, comme à l'écran. Un parent reçoit
	 * aussi le titre et le texte de son premier enfant (`BlockTypeLib::getPropertiesCreate()`).
	 */
	public function getPropertiesCreate(\ApiData $data): \Closure {
		return fn(\block\Block $e) => \block\BlockLib::getPropertiesCreateByType($e, $data->body['type'] ?? NULL);
	}

	/**
	 * Ce que le type du bloc écrit, comme à l'écran : un type se choisit à la création et ne change plus.
	 */
	public function getPropertiesUpdate(\ApiData $data): \Closure {
		return fn(\block\Block $e) => \block\BlockSetting::resolve($e['type'], 'BlockTypeLib')::getProperties($e['type']);
	}

	/**
	 * Les leçons des formations du groupe. La requête est citée sous celle des blocs : la formation
	 * prend le troisième préfixe d'alias.
	 */
	private function getLessonModel(\ApiData $data): LessonModel {

		return new LessonModel()
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($data->eStructure)->getSql('t'));

	}

	/**
	 * Le transport de l'image, seul endroit de la ressource qui le lit : le fichier rendu est confié à
	 * `BlockLib::putMedia()`, qui ne sait pas d'où il vient. Le corps porte l'image en base64 dans
	 * `content`, comme la vignette d'un produit.
	 */
	private function getMediaFile(\ApiData $data): string {

		$content = $data->body['content'] ?? NULL;

		if(is_string($content) === FALSE or $content === '') {
			throw new \ApiException('Missing content', 'missing_content', 400);
		}

		return \media\ApiAiLib::decode($content);

	}

	private static function validateMedia(\block\Block $eBlock): void {

		if($eBlock->acceptUpdate() === FALSE) {
			throw new \ApiException('Can not update', 'accept_update', 412);
		}

		if($eBlock->acceptMedia() === FALSE) {
			throw new \ApiException('Only an item carries a media', 'accept_media', 412);
		}

	}

}
?>
