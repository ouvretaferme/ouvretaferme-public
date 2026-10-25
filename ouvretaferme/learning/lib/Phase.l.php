<?php
namespace learning;

/**
 * Les phases d'une formation, rangées par `position` à partir de 0, sans trou, comme les modules
 * (`ChapterLib`) : une phase créée prend le nombre de phases pour position, ce qui la range en
 * dernière, et une suppression fait remonter les suivantes d'un rang.
 *
 * Une phase ne change jamais de formation : ses modules la désignent, et une phase déplacée les
 * laisserait rangés sous celle d'une autre formation.
 */
class PhaseLib extends PhaseCrud {

	/**
	 * Le nombre de teintes de phase du design system, `util-tint-phase-1` à `util-tint-phase-4`
	 * (`component.css`) : au-delà, les phases reprennent la première teinte.
	 */
	const TINTS = 4;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return array_merge(['course'], self::getPropertiesUpdate($origin));
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'summary'];
	}

	public static function getByCourse(Course $eCourse): \Collection {

		return Phase::model()
			->select(Phase::getSelection())
			->whereCourse($eCourse)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Le programme d'une formation rangé par phase : tous ses modules (`cChapter`), ses phases dans
	 * l'ordre (`cPhase`), chacune avec ses modules (`cChapter`), et les modules sans phase à part
	 * (`cChapterWithoutPhase`). Les modules viennent de `ChapterLib::getByCourse()` rangés par phase
	 * (`byPhase`), donc déjà rangés et numérotés : chaque phase en garde l'ordre. Ce sont les mêmes
	 * éléments, et non des copies : ce qu'on pose ensuite sur un module de `$cChapter` se lit aussi
	 * dans sa phase.
	 *
	 * Une phase qui a des modules reçoit son rang parmi elles (`number`), sa teinte (`tint`), le
	 * numéro de son premier et de son dernier module (`chapterFrom`, `chapterTo`, suivis d'une phase
	 * à l'autre parce que les modules sont rangés par phase), ses leçons et sa durée. Une phase vide
	 * n'en reçoit rien : le sommaire ne la montre pas, et elle ne prend pas de rang.
	 */
	public static function fillChapters(Course $eCourse, \Collection $cChapter): void {

		$cPhase = self::getByCourse($eCourse);

		$number = 0;

		foreach($cPhase as $ePhase) {

			$ePhase['cChapter'] = $cChapter->find(fn(Chapter $eChapter) => ($eChapter['phase']->notEmpty() and $eChapter['phase']['id'] === $ePhase['id']), clone: FALSE);

			if($ePhase['cChapter']->empty()) {
				continue;
			}

			$ePhase['number'] = ++$number;
			$ePhase['tint'] = ($ePhase['number'] - 1) % self::TINTS + 1;
			$ePhase['chapterFrom'] = $ePhase['cChapter']->first()['number'];
			$ePhase['chapterTo'] = $ePhase['cChapter']->last()['number'];
			$ePhase['lessons'] = ChapterLib::countLessons($ePhase['cChapter']);
			$ePhase['minutes'] = ChapterLib::sumMinutes($ePhase['cChapter']);

		}

		$eCourse['cChapter'] = $cChapter;
		$eCourse['cPhase'] = $cPhase;
		$eCourse['cChapterWithoutPhase'] = $cChapter->find(fn(Chapter $eChapter) => $eChapter['phase']->empty(), clone: FALSE);

	}

	/**
	 * Les phases de toutes les formations d'un groupe : c'est la liste où l'étape
	 * `Chapter::phase.consistent` cherche la phase postée par l'API.
	 */
	public static function getByCommunity(\structure\Structure $eStructure): \Collection {

		return Phase::model()
			->select(Phase::getSelection())
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($eStructure)->getSql())
			->getCollection(index: 'id');

	}

	public static function create(Phase $e): void {

		$e->expects(['course']);

		Phase::model()->beginTransaction();

			$e['position'] = Phase::model()
				->whereCourse($e['course'])
				->count();

			parent::create($e);

			CourseLib::touch($e['course']);

		Phase::model()->commit();

	}

	public static function update(Phase $e, array $properties): void {

		$e->expects(['course']);

		Phase::model()->beginTransaction();

			parent::update($e, $properties);

			CourseLib::touch($e['course']);

		Phase::model()->commit();

	}

	/**
	 * Les modules de la phase restent dans la formation, sans phase. Les phases suivantes remontent
	 * d'un rang, pour que la liste reste sans trou. L'écriture de masse garde un tableau de valeurs :
	 * un élément porteur d'un id la bornerait à une ligne.
	 */
	public static function delete(Phase $e): void {

		$e->expects(['course', 'position']);

		Phase::model()->beginTransaction();

			ChapterLib::removePhase($e);

			parent::delete($e);

			Phase::model()
				->whereCourse($e['course'])
				->wherePosition('>', $e['position'])
				->update([
					'position' => new \Sql('position - 1')
				]);

			CourseLib::touch($e['course']);

		Phase::model()->commit();

	}

	/**
	 * Avec la formation, dans la transaction de `CourseLib::delete()`, après ses modules : il n'y a
	 * plus de module à retirer de la phase, de rang à resserrer ni de formation à dater.
	 */
	public static function deleteByCourse(Course $eCourse): void {

		$cPhase = Phase::model()
			->select('id')
			->whereCourse($eCourse)
			->getCollection();

		foreach($cPhase as $ePhase) {
			parent::delete($ePhase);
		}

	}

}
?>
