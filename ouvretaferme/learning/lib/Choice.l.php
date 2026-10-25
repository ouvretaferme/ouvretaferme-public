<?php
namespace learning;

/**
 * Les choix d'une question. Ils se construisent tous ensemble depuis le panneau de la question
 * (`buildForQuestion()`), et s'écrivent avec elle, dans sa transaction (`saveForQuestion()`) : un
 * choix n'a ni route ni écriture propres.
 */
class ChoiceLib extends ChoiceCrud {

	/**
	 * `label` devant `image` : l'étape `Choice::image.filled` lit le libellé construit avant elle.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['label', 'image', 'correct'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['label', 'image', 'correct'];
	}

	public static function getByQuestion(Question $eQuestion): \Collection {

		return Choice::model()
			->select(Choice::getSelection())
			->whereQuestion($eQuestion)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Construit les choix postés avec une question, un par rang, dans l'ordre du formulaire, qui est
	 * celui de la poignée : la position est le rang. Le formulaire les poste sous `choices`, à part
	 * des champs de la question, dont `image` porte le même nom. Chaque rang porte `label[<rang>]`,
	 * `image[<rang>]`, `correct[<rang>]` et, pour un choix déjà enregistré, son id dans
	 * `id[<rang>]` ; les rangs sont ceux de `label`, que chaque ligne poste même vide.
	 *
	 * Un id posté qui n'est pas un choix de cette question est un appel forgé : il désignerait le
	 * choix d'une autre question, qu'`update()` écrirait. Les erreurs de rang passent par `$fw` comme
	 * celles de la question, et rien ne s'écrit tant qu'il en reste.
	 */
	public static function buildForQuestion(Question $eQuestion, array $input): \Collection {

		$labels = $input['label'] ?? [];
		$ids = $input['id'] ?? [];

		if(
			is_array($labels) === FALSE or
			is_array($ids) === FALSE
		) {
			throw new \NotExpectedAction('Choices');
		}

		$cChoiceExisting = $eQuestion->exists() ? self::getByQuestion($eQuestion) : new \Collection();

		$cChoice = new \Collection();
		$position = 0;

		foreach(array_keys($labels) as $index) {

			$id = (int)($ids[$index] ?? 0);

			if($id === 0) {
				$eChoice = new Choice(['question' => $eQuestion]);
				$properties = self::getPropertiesCreate();
			} else if($cChoiceExisting->offsetExists($id)) {
				$eChoice = $cChoiceExisting[$id];
				$properties = self::getPropertiesUpdate();
			} else {
				throw new \NotExpectedAction('Choice');
			}

			$eChoice->buildIndex($properties, $input, $index);

			$eChoice['position'] = $position++;

			$cChoice[] = $eChoice;

		}

		if(
			$cChoice->count() < Question::CHOICES_MIN or
			$cChoice->count() > Question::CHOICES_MAX
		) {
			Question::fail('choices.count');
		} else if(self::acceptCorrect($eQuestion['type'], $cChoice) === FALSE) {
			Question::fail('choices.correct');
		}

		return $cChoice;

	}

	/**
	 * Une question à une réponse en a exactement une juste, une question à plusieurs réponses au
	 * moins une. Le type est toujours l'un des deux : `build()` ne pose qu'un type accepté, et la
	 * question neuve part du type par défaut (`page/question.p.php`).
	 */
	private static function acceptCorrect(string $type, \Collection $cChoice): bool {

		$corrects = $cChoice->find(fn(Choice $eChoice) => $eChoice['correct'] === TRUE)->count();

		return match($type) {
			Question::SINGLE => ($corrects === 1),
			Question::MULTIPLE => ($corrects >= 1),
		};

	}

	/**
	 * Écrit les choix d'une question, déjà construits et contrôlés : un choix enregistré se met à
	 * jour, un choix neuf se crée, et un choix enregistré qui n'a pas été reposté part avec les
	 * réponses qui le cochaient. Appelé par `QuestionLib::create()` et `update()`, qui tiennent la
	 * transaction.
	 */
	public static function saveForQuestion(Question $eQuestion, \Collection $cChoice): void {

		$cChoiceExisting = Choice::model()
			->select('id')
			->whereQuestion($eQuestion)
			->getCollection(index: 'id');

		foreach($cChoice as $eChoice) {

			$eChoice['question'] = $eQuestion;

			if($eChoice->exists()) {
				self::update($eChoice, ['label', 'image', 'correct', 'position']);
				$cChoiceExisting->offsetUnset($eChoice['id']);
			} else {
				self::create($eChoice);
			}

		}

		foreach($cChoiceExisting as $eChoiceRemoved) {

			$eChoiceRemoved['question'] = $eQuestion;

			ResponseLib::deleteByChoice($eChoiceRemoved);
			self::delete($eChoiceRemoved);

		}

	}

	/**
	 * Les choix d'une question qu'on supprime, dans la transaction de `QuestionLib::deleteWithLesson()`.
	 */
	public static function deleteByQuestion(Question $eQuestion): void {

		$cChoice = Choice::model()
			->select('id')
			->whereQuestion($eQuestion)
			->getCollection();

		foreach($cChoice as $eChoice) {
			self::delete($eChoice);
		}

	}

}
?>
