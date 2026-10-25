<?php
namespace learning;

/**
 * Le quiz d'une leçon : ses questions, rangées à la poignée, chacune avec ses choix. Une question
 * s'écrit toujours avec ses choix, dans une seule transaction (`create()`, `update()`), et part avec
 * eux et avec les réponses des lecteurs.
 *
 * Les questions ne dépendent pas de la validation de la leçon : une leçon rendue à la déclaration
 * garde les siennes, qui ne sont plus posées (arbitrage 5 de `commande.md`, L5).
 */
class QuestionLib extends QuestionCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['statement', 'image', 'type', 'explanation'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['statement', 'image', 'type', 'explanation'];
	}

	/**
	 * Les questions d'une leçon dans l'ordre, chacune avec ses choix dans le leur (`cChoice`), son
	 * numéro dans le quiz (`number`), et deux marques pour l'affichage : `hasChoiceImage` (un de ses
	 * choix porte une image, ils se rangent alors côte à côte) et `hasImage` (elle ou un de ses choix).
	 */
	public static function getByLesson(Lesson $eLesson): \Collection {

		$cQuestion = Question::model()
			->select(Question::model()->getProperties() + [
				'cChoice' => Choice::model()
					->select(['id', 'question', 'label', 'image', 'correct', 'position'])
					->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('question', index: 'id')
			])
			->whereLesson($eLesson)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

		$number = 0;

		foreach($cQuestion as $eQuestion) {

			$eQuestion['number'] = ++$number;
			$eQuestion['hasChoiceImage'] = $eQuestion['cChoice']->find(fn(Choice $eChoice) => $eChoice['image'] !== NULL)->notEmpty();
			$eQuestion['hasImage'] = ($eQuestion['image'] !== NULL or $eQuestion['hasChoiceImage']);

		}

		return $cQuestion;

	}

	/**
	 * Pose sur la leçon ce que la section du quiz de son écran d'auteur montre : ses questions
	 * (`cQuestion`), et pour un quiz posé, le nombre de bonnes réponses que son seuil demande
	 * (`needed`). Une leçon rendue à la déclaration garde ses questions, qui ne sont pas posées, et
	 * n'a pas de seuil à dire.
	 */
	public static function fillEditor(Lesson $eLesson): void {

		$eLesson->expects(['validation', 'quizThreshold']);

		$eLesson['cQuestion'] = self::getByLesson($eLesson);

		$eLesson['needed'] = match($eLesson['validation']) {
			Lesson::QUIZ => ResponseLib::getNeeded($eLesson['cQuestion']->count(), $eLesson['quizThreshold']),
			Lesson::DECLARED => NULL,
		};

	}

	/**
	 * Lue pour la copie, comme `AttachmentLib::getByCourse()`, et pour le journal de lecture, qui
	 * recalcule le score de chaque tentative : chaque colonne du modèle et les choix de chaque
	 * question, rangées par leçon et indexées par question.
	 */
	public static function getByLessons(\Collection $cLesson): \Collection {

		if($cLesson->empty()) {
			return new \Collection();
		}

		return Question::model()
			->select(Question::model()->getProperties() + [
				'cChoice' => Choice::model()
					->select(Choice::model()->getProperties())
					->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
					->delegateCollection('question')
			])
			->whereLesson('IN', $cLesson)
			->sort(['lesson' => SORT_ASC, 'position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Le nombre de questions de chaque leçon, indexé par leçon, en une requête pour toute la liste.
	 * Une leçon sans question y figure à 0.
	 */
	public static function countByLessons(array $lessons): array {

		if($lessons === []) {
			return [];
		}

		$cQuestion = Question::model()
			->select([
				'lesson',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereLesson('IN', $lessons)
			->group('lesson')
			->getCollection();

		$counts = array_fill_keys($lessons, 0);

		foreach($cQuestion as $eQuestion) {
			$counts[$eQuestion['lesson']['id']] = $eQuestion['count'];
		}

		return $counts;

	}

	/**
	 * Une leçon qui n'est pas encore enregistrée n'a pas de question. Elle se compte sans requête :
	 * `whereLesson()` sur une leçon vide lirait `lesson IS NULL`, où se rangeront les questions de
	 * parcours (L8).
	 */
	public static function countByLesson(Lesson $eLesson): int {

		if($eLesson->exists() === FALSE) {
			return 0;
		}

		return Question::model()
			->whereLesson($eLesson)
			->count();

	}

	/**
	 * La question se range en dernier, et ses choix, déjà construits et contrôlés par
	 * `ChoiceLib::buildForQuestion()`, s'écrivent avec elle.
	 */
	public static function create(Question $e, \Collection $cChoice = new \Collection()): void {

		$e->expects(['lesson']);

		Question::model()->beginTransaction();

			$e['position'] = Question::model()
				->whereLesson($e['lesson'])
				->count();

			parent::create($e);

			ChoiceLib::saveForQuestion($e, $cChoice);

			CourseLib::touch($e['lesson']['course']);

		Question::model()->commit();

	}

	public static function update(Question $e, array $properties, \Collection $cChoice = new \Collection()): void {

		$e->expects(['lesson']);

		Question::model()->beginTransaction();

			parent::update($e, $properties);

			ChoiceLib::saveForQuestion($e, $cChoice);

			CourseLib::touch($e['lesson']['course']);

		Question::model()->commit();

	}

	public static function updatePositions(Lesson $eLesson, array $ids): void {

		$cQuestion = Question::model()
			->select('id')
			->whereLesson($eLesson)
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			count($ids) !== $cQuestion->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cQuestion->getIds()) !== []
		) {
			Question::fail('positionsIncomplete');
			return;
		}

		Question::model()->beginTransaction();

			foreach($cQuestion as $eQuestion) {

				$eQuestion['position'] = $positions[$eQuestion['id']];

				Question::model()
					->select('position')
					->update($eQuestion);

			}

		Question::model()->commit();

	}

	/**
	 * La dernière question d'une leçon à quiz ne se retire pas sous une formation publiée : la leçon
	 * serait devant des lecteurs qui ne pourraient plus la valider. Pendant de
	 * `Lesson::validation.questioned`, qui refuse l'autre chemin vers le même état.
	 *
	 * Le refus n'est pas un `acceptDelete()` : `ModulePage::doDelete()` valide avant d'ouvrir son
	 * `FailWatch`, et le refus sortirait muet. La règle se rejuge donc ici, par sa propre requête et
	 * dans la transaction, et sort par `commit()`, puis le `fail` : ne rien faire laisserait le
	 * compteur de transactions à 1 et le COMMIT suivant ne partirait jamais. Le refus enregistre
	 * donc le `touch()` du verrou et avance la date de la formation : un `rollBack()` remet toute la
	 * pile de transactions à zéro (`framework/core/Module.php`), celle d'un appelant comprise.
	 *
	 * Le cadriciel n'a pas de `SELECT … FOR UPDATE` : c'est `CourseLib::touch()`, posé avant le
	 * recompte, qui verrouille la ligne de la formation jusqu'au `commit()`. Deux suppressions des
	 * deux dernières questions s'attendent alors, et la seconde recompte après la première. L'état
	 * de la leçon et de la formation se relit sous ce verrou, pas depuis l'élément chargé avant.
	 *
	 * Les questions suivantes remontent d'un rang, comme les fichiers de `AttachmentLib::delete()`.
	 * L'écriture de masse garde un tableau de valeurs : un élément porteur d'un id la bornerait à une
	 * ligne.
	 */
	public static function delete(Question $e): void {

		$e->expects(['lesson' => ['course'], 'position']);

		Question::model()->beginTransaction();

			CourseLib::touch($e['lesson']['course']);

			$eLesson = Lesson::model()
				->select(['id', 'validation', 'course' => ['status']])
				->whereId($e['lesson'])
				->get();

			if(
				$eLesson['validation'] === Lesson::QUIZ and
				$eLesson['course']['status'] === Course::PUBLISHED and
				Question::model()->whereLesson($eLesson)->whereId('!=', $e)->exists() === FALSE
			) {
				Question::model()->commit();
				Question::fail('deleteLastPublished');
				return;
			}

			self::deleteWithLesson($e);

			Question::model()
				->whereLesson($eLesson)
				->wherePosition('>', $e['position'])
				->update([
					'position' => new \Sql('position - 1')
				]);

		Question::model()->commit();

	}

	/**
	 * La question, ses choix et les réponses des lecteurs, sans règle ni transaction propre : ses
	 * appelants, `delete()` et `deleteByLesson()` sous `LessonLib::deleteWithCourse()`, en tiennent
	 * une. Supprimer une leçon ou une formation entière emporte donc aussi la dernière question.
	 */
	public static function deleteWithLesson(Question $e): void {

		ResponseLib::deleteByQuestion($e);
		ChoiceLib::deleteByQuestion($e);

		parent::delete($e);

	}

	public static function deleteByLesson(Lesson $eLesson): void {

		$cQuestion = Question::model()
			->select('id')
			->whereLesson($eLesson)
			->getCollection();

		foreach($cQuestion as $eQuestion) {
			self::deleteWithLesson($eQuestion);
		}

	}

}
?>
