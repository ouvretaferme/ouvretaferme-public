<?php
namespace learning;

/**
 * Les leçons des formations du groupe de la clé. Le module d'une leçon est contrôlé par l'étape
 * `Lesson::chapter.owned`, qui le cherche dans `cChapter` et en déduit la formation.
 *
 * Une leçon ne change jamais de formation : en modification, `cChapter` ne porte que les modules de
 * la sienne, et un module d'une autre formation est refusé comme celui d'un autre groupe. La
 * progression de ses lecteurs, qui porte la formation, n'a donc jamais à suivre.
 *
 * `format` se lit et ne s'écrit pas : c'est la forme que donne le contenu de la leçon
 * (`LessonLib::getFormat()`), `video`, `steps`, `text` ou `empty`. Un PUT qui le nomme seul est
 * refusé (`property_unknown`), et un corps entier qui le porte le voit ignoré.
 */
class Lesson_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->create('lesson');
		$this->read('lesson/{id}');
		$this->update('lesson/{id}');
		$this->delete('lesson/{id}');

		// Les blocs d'une leçon se listent ici, à plat, chacun désignant son parent : `listBy()` charge
		// `{id}` par le modèle de la classe qui déclare la route, donc dans le bornage des leçons. Un
		// bloc se rend par la déclaration de `Block_0_1betaApi`.
		$this->listBy('lesson/{id}/blocksList', fn(\ApiData $data) => new \block\BlockModel()
			->whereOwner(BlockOwnerLib::LESSON)
			->whereReference($data->e['id']));

		// `position` se lit, pour la même raison que celle d'un module.
		//
		// `content` se lit dans les blocs de la leçon, ses textes mis bout à bout sous une seule
		// enveloppe (`BlockLib::getText()`) : la colonne est figée depuis la reprise. Il s'écrit tel
		// quel, `LessonLib` le range dans le bloc texte.
		//
		// `format` se calcule sur les blocs lisibles de la leçon, chargés à plat par la sélection
		// (`getSelection()`, et celle de `chapter/{id}/lessonList`) : aucune requête par leçon.
		$this->element(
			'learning\Lesson',
			fn() => [
				'id', 'course', 'chapter',
				'name', 'objective', 'format', 'minutes', 'content',
				'validation', 'quizThreshold',
				'position', 'updatedAt'
			],
			[
				'content' => fn(\ApiData $data, mixed $value, string $direction) => match($direction) {
					'in' => $value,
					'out' => \block\BlockLib::getText($value['cBlock']),
				},
				'format' => fn(\ApiData $data, mixed $value, string $direction) => match($direction) {
					'out' => LessonLib::getFormat($value, \block\BlockLib::getReadableFromFlat($value['cBlockFlat'])),
				},
			]
		);

	}

	public function getNewModel(\ApiData $data): LessonModel {

		Course_0_1betaApi::validateStructure($data);

		return new LessonModel()
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($data->eStructure)->getSql());

	}

	public function getNewElement(\ApiData $data): Lesson {

		Course_0_1betaApi::validateStructure($data);

		return new Lesson([
			'cChapter' => ChapterLib::getByCommunity($data->eStructure),
			'cBlock' => new \Collection(),
		]);

	}

	/**
	 * Les blocs à plat de la forme, à part de l'arbre que `getById()` relit pour le contenu :
	 * `BlockLib::getReadableFromFlat()` bâtit son propre arbre sur une liste à plat.
	 */
	public function getSelection(\ModuleModel $m): array {

		return parent::getSelection($m) + [
			'cBlockFlat' => \block\BlockLib::delegateByOwner(BlockOwnerLib::LESSON),
		];

	}

	/**
	 * Comme `LessonPage->applyElement()` : sans les modules de sa formation, l'étape `chapter.owned`
	 * n'a rien où chercher. `cBlock` sert à la lecture du contenu et à l'étape `content.blocks`.
	 *
	 * `holdsProgram` et `holdsCompleted` se posent sur les leçons de la collection, pas sur l'élément
	 * que l'API a chargé à part, et `Lesson::acceptDelete()` les lit sur celui-là.
	 */
	public function getById(\ApiData $data): Lesson {

		$eLesson = parent::getById($data);
		$eLesson['cChapter'] = ChapterLib::getByCourse($eLesson['course']);

		ChapterLib::fillHolds($eLesson['course'], $eLesson['cChapter']);

		$eLessonProgram = $eLesson['cChapter'][$eLesson['chapter']['id']]['cLesson']
			->find(fn(Lesson $e) => $e['id'] === $eLesson['id'])
			->first();

		$eLesson['holdsProgram'] = $eLessonProgram['holdsProgram'];
		$eLesson['holdsCompleted'] = $eLessonProgram['holdsCompleted'];
		$eLesson['cBlock'] = \block\BlockLib::getByOwnerAndReference(BlockOwnerLib::LESSON, $eLesson['id']);

		return $eLesson;

	}

}
?>
