<?php
namespace learning;

/**
 * Les modules des formations du groupe de la clé. Le groupe est gardé par
 * `Course_0_1betaApi::validateStructure()`, la formation d'un module par l'étape `Chapter::course.owned`,
 * qui cherche la formation postée parmi celles du groupe.
 *
 * Les leçons d'un module se listent ici (`chapter/{id}/lessonList`), pour la raison que donne
 * `Course_0_1betaApi` de ses modules.
 */
class Chapter_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->create('chapter');
		$this->read('chapter/{id}');
		$this->update('chapter/{id}');
		$this->delete('chapter/{id}');

		// Le contenu d'une leçon se lit dans tous ses blocs, et sa forme dans ses blocs lisibles
		// (`Lesson_0_1betaApi`), chargés pour toute la page de la liste en une requête chacun. Deux
		// listes et non une : les closures de `Lesson_0_1betaApi` servent aussi `lesson/{id}`, où
		// `content` lit l'arbre des blocs (`cBlock`) et `format` la liste à plat (`cBlockFlat`), que
		// `BlockLib::getReadableFromFlat()` rebâtit en arbre lui-même. `listBy()` ajoute cette
		// sélection à la sienne et ne la lit pas pour compter.
		$this->listBy('chapter/{id}/lessonList', fn(\ApiData $data) => new LessonModel()
			->select([
				'cBlock' => \block\BlockLib::delegateByOwner(BlockOwnerLib::LESSON),
				'cBlockFlat' => \block\BlockLib::delegateByOwner(BlockOwnerLib::LESSON)
			])
			->whereChapter($data->e));

		// `position` se lit : l'ordre du programme ne se change qu'à l'écran.
		$this->element(
			'learning\Chapter',
			fn() => [
				'id', 'course', 'phase',
				'name', 'summary',
				'position'
			]
		);

	}

	public function getNewModel(\ApiData $data): ChapterModel {

		Course_0_1betaApi::validateStructure($data);

		return new ChapterModel()
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($data->eStructure)->getSql());

	}

	public function getNewElement(\ApiData $data): Chapter {

		Course_0_1betaApi::validateStructure($data);

		return new Chapter([
			'cCourse' => CourseLib::getByCommunity($data->eStructure),
			'cPhase' => PhaseLib::getByCommunity($data->eStructure)
		]);

	}

	/**
	 * `holdsProgram` et `holdsCompleted` se posent sur les modules de la collection que rend
	 * `ChapterLib::getByCourse()`, pas sur l'élément que l'API a chargé à part, et
	 * `Chapter::acceptDelete()` les lit sur celui-là.
	 * Seule la route `DELETE` appelle `acceptDelete()` : les autres ne chargent pas le programme.
	 *
	 * `cPhase` est la liste où l'étape `Chapter::phase.consistent` cherche la phase postée par un `PUT`.
	 */
	public function getById(\ApiData $data): Chapter {

		$eChapter = parent::getById($data);

		if(\Route::getRequestMethod() === 'PUT') {
			$eChapter['cPhase'] = PhaseLib::getByCourse($eChapter['course']);
		}

		if(\Route::getRequestMethod() !== 'DELETE') {
			return $eChapter;
		}

		$cChapter = ChapterLib::getByCourse($eChapter['course']);
		ChapterLib::fillHolds($eChapter['course'], $cChapter);

		$eChapter['holdsProgram'] = $cChapter[$eChapter['id']]['holdsProgram'];
		$eChapter['holdsCompleted'] = $cChapter[$eChapter['id']]['holdsCompleted'];

		return $eChapter;

	}

}
?>
