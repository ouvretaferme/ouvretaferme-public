<?php
namespace learning;

class Lesson extends LessonElement {

	/**
	 * Le seuil que les réglages proposent à une leçon qui passe au quiz sans en avoir jamais eu : 4
	 * bonnes réponses sur 5 (planche L5).
	 */
	const QUIZ_THRESHOLD_DEFAULT = 80;

	/**
	 * La forme d'une leçon, que son contenu donne (`LessonLib::getFormat()`) et que rien ne saisit.
	 * `VIDEO` et `TEXT` gardent la valeur que l'API rendait quand la forme se déclarait.
	 */
	const VIDEO = 'video';
	const STEPS = 'steps';
	const TEXT = 'text';
	const EMPTY = 'empty';

	public static function getSelection(): array {

		return self::getSelectionWithoutCourse() + [
			'course' => Course::getSelectionWithCommunity(),
		];

	}

	/**
	 * Sans la formation, que l'appelant qui la tient déjà accroche à chaque ligne.
	 */
	public static function getSelectionWithoutCourse(): array {

		return parent::getSelection() + [
			'chapter' => ['name', 'position'],
		];

	}

	public function canRead(): bool {

		$this->expects(['course']);

		return $this['course']->canReadLessons();

	}

	public function canWrite(): bool {

		$this->expects(['course']);

		return $this['course']->canWrite();

	}

	public function acceptRead(): bool {

		$this->expects(['course']);

		return $this['course']->acceptRead();

	}

	public function acceptWrite(): bool {

		$this->expects(['course']);

		return $this['course']->acceptWrite();

	}

	/**
	 * Par l'API, une leçon neuve ne reçoit son module qu'avec le corps de la requête, après ce
	 * contrôle : l'étape `chapter.owned` en juge alors l'état. L'écran pose le module d'abord et
	 * le contrôle par `acceptWrite()`.
	 */
	public function acceptCreate(): bool {
		return TRUE;
	}

	/**
	 * Une formation publiée garde au moins une leçon en ligne, où elle ne se publie pas sans
	 * (`holdsProgram`, décision du 05/10), et en présentiel tant qu'un inscrit l'a suivie
	 * en entier (`holdsCompleted`, décision du 04/10). Jamais sur `acceptWrite()`, à quoi la classe
	 * délègue : ce serait interdire de modifier une leçon d'une formation publiée. Les deux se posent
	 * par `ChapterLib::fillHolds()`, et `parent::acceptDelete()` garde la règle d'écriture.
	 */
	public function acceptDelete(): bool {

		$this->expects(['holdsProgram', 'holdsCompleted']);

		return (
			parent::acceptDelete() and
			$this['holdsProgram'] === FALSE and
			$this['holdsCompleted'] === FALSE
		);

	}

	public function assertRead(): bool {

		$this->expects(['course']);

		return $this['course']->assertReadLessons();

	}

	/**
	 * Une leçon est écrite quand elle a quelque chose à lire ou à faire : un bloc lisible
	 * (`BlockLib::getReadable()`), ou un quiz, qui est à faire (arbitrage de V-Learning du 19/10).
	 * Seule règle de la leçon vide, que la colonne `written` retient (`LessonLib::synchronizeWritten()`)
	 * pour que les durées se somment en SQL sans lire les blocs.
	 */
	public function isWritten(\Collection $cBlockReadable): bool {

		$this->expects(['validation']);

		return (
			$cBlockReadable->notEmpty() or
			$this['validation'] === Lesson::QUIZ
		);

	}

	/**
	 * Une leçon du programme mène à sa page quand elle est écrite (`written`), ou quand le lecteur
	 * peut la marquer suivie (`Course::canFollow()`, posé par la page sous `canFollow`) : une leçon
	 * vide se coche comme une autre, et sans ce lien une leçon obligatoire vide ne s'atteindrait plus,
	 * donc la formation ne se terminerait pas (arbitrage (b) de V-Learning, 19/10). Seule règle des
	 * liens du programme, que lisent la colonne de la leçon, la fiche et son menu, et le sommaire des
	 * tutoriels.
	 */
	public function isLinked(Course $eCourse): bool {

		$this->expects(['written']);
		$eCourse->expects(['canFollow']);

		return (
			$this['written'] or
			$eCourse['canFollow']
		);

	}

	/**
	 * Une leçon à quiz se valide en réussissant le quiz, jamais par la déclaration du lecteur : ni
	 * « J'ai suivi » ni son retrait (arbitrage 4 de `commande.md`, L5). Une leçon réussie ne se
	 * retire pas, faute de quoi le lecteur referait un quiz qu'il a déjà passé.
	 */
	public function acceptDone(): bool {

		$this->expects(['validation']);

		return ($this['validation'] === Lesson::DECLARED);

	}

	public function acceptUndo(): bool {
		return $this->acceptDone();
	}

	/**
	 * Répondre au quiz. Sur une formation datée, le quiz n'est ouvert qu'aux personnes confirmées à
	 * une date dont le réseau a ouvert le quiz, et à l'équipe qui écrit la formation ; une formation
	 * sans date ne ferme rien. Le droit pèse le membre connecté : c'est lui que `doAnswer` et `retry`
	 * valident, et lui seul que `ResponseLib::fillQuiz()` lit pour dire le quiz ouvert.
	 */
	public function canAnswer(): bool {

		$this->expects(['course']);

		return (
			$this['course']->isDated() === FALSE or
			$this['course']->canWrite() or
			$this->isQuizOpened()
		);

	}

	/**
	 * Lue en dernier, après que les autres droits ont échoué : une requête, et aucune pour une
	 * formation sans date.
	 */
	private function isQuizOpened(): bool {

		return DateEnrollmentLib::hasQuizOpened(\user\ConnectionLib::getOnline(), $this['course']);

	}

	public function acceptAnswer(): bool {

		$this->expects(['validation']);

		return ($this['validation'] === Lesson::QUIZ);

	}

	public static function properties(\PropertyList $l): void {

		// La formation suit le module : elle se déduit ici, et n'est jamais postée.
		$l->property('chapter')
			->addAfterCheck('owned', function(Chapter $eChapter): bool {

				$this->expects(['cChapter']);

				if($this['cChapter']->offsetExists($eChapter['id']) === FALSE) {
					return FALSE;
				}

				$this['course'] = $this['cChapter'][$eChapter['id']]['course'];

				return $this['course']->acceptWrite();

			});

		/**
		 * Le contenu ne s'écrit plus que par l'API, et `LessonLib` le range dans les blocs de la leçon,
		 * jamais dans la colonne. Il passe par les options d'éditeur du bloc texte, seule source de ce
		 * qu'un texte de leçon accepte (`BlockOwnerLib::getEditorOptions()`). `blocks` refuse l'écriture
		 * sur une leçon composée (plusieurs blocs, ou un bloc qui n'est pas un texte) : un contenu d'un
		 * seul tenant y remplacerait la composition. `blocks` juge la composition en place : l'élément
		 * porte ses blocs, une leçon neuve n'en ayant aucun.
		 *
		 * La leçon n'a plus de colonne `content` depuis le lot B1. Sur une propriété absente du modèle,
		 * `build()` n'exécute que les étapes déclarées (`Property::compile()`) : le cast, le contrôle et
		 * la pose sont donc écrits ici, le cast et le contrôle étant ceux de `Block::content`, où le
		 * texte s'écrit.
		 */
		$l->property('content')
			->replaceCast(function(mixed &$value): bool {

				\block\Block::model()->cast('content', $value);
				$value = new \editor\XmlLib()->fromHtml($value, BlockOwnerLib::getEditorOptions(BlockOwnerLib::LESSON, \block\BlockTypeLib::TEXT));

				return TRUE;

			})
			->replaceCheck(fn(?string $value): bool => \block\Block::model()->check('content', $value))
			->replaceSet(function(?string $value): void {
				$this['content'] = $value;
			})
			->addAfterCheck('blocks', function(): bool {

				$this->expects(['cBlock']);

				return (
					$this['cBlock']->empty() or
					(
						$this['cBlock']->count() === 1 and
						$this['cBlock']->first()['type'] === \block\BlockTypeLib::TEXT
					)
				);

			});

		/**
		 * Pendant de `Course::status.classified` : une leçon n'a pas d'état propre, elle hérite de
		 * celui de sa formation, donc une leçon sans durée sous une formation publiée est aussitôt
		 * devant les lecteurs. La règle vaut à la création comme à la modification, `minutes`
		 * restant dans les deux listes de propriétés.
		 *
		 * La règle juge la formation, que `chapter.owned` pose quand c'est le module qui la porte :
		 * un module refusé n'en pose aucune, et `build()` passe pourtant à la propriété suivante.
		 * `skipIfInvalid()` retire la règle dans ce cas, plutôt que de la faire lire une clé que
		 * rien n'a posée.
		 *
		 * Elle ne tire que là où rien n'a posé la formation avant le build, c'est-à-dire sur la seule
		 * création d'API : partout ailleurs l'élément arrive relu, la formation vient avec lui et la
		 * règle a de quoi lire même quand le module est refusé. Et elle ne peut tirer que si `chapter` est
		 * construit avant `minutes`, `build()` suivant l'ordre de la liste de propriétés : les trois
		 * listes qui portent `chapter` le rangent donc en tête, les deux de `LessonLib` et celle de
		 * la route `doUpdateSettings`.
		 */
		$l->property('minutes')
			->addAfterCheck('classified', function(?int $minutes, \PropertyList $l): bool {

				$l->skipIfInvalid('chapter');

				return (
					$minutes !== NULL or
					$this['course']['status'] !== Course::PUBLISHED
				);

			});

		/**
		 * Le livrable et la présence restent dans l'enum pour des lots à venir : aucun écran ne sait
		 * encore les valider.
		 *
		 * `questioned` est le pendant de `minutes.classified` pour le quiz : sous une formation
		 * publiée, une leçon passée en quiz sans question serait aussitôt devant des lecteurs qui ne
		 * pourraient plus la valider. En brouillon, c'est la publication qui refuse
		 * (`Course::status.classified`). Même raison que `minutes.classified` pour `skipIfInvalid()`.
		 */
		$l->property('validation')
			->replaceCheck(fn(?string $validation): bool => in_array($validation, [Lesson::DECLARED, Lesson::QUIZ], TRUE))
			->addAfterCheck('questioned', function(string $validation, \PropertyList $l): bool {

				$l->skipIfInvalid('chapter');

				return (
					$validation !== Lesson::QUIZ or
					$this['course']['status'] !== Course::PUBLISHED or
					QuestionLib::countByLesson($this) > 0
				);

			});

		/**
		 * Le seuil n'est exigé que pour un quiz : une leçon rendue à la déclaration garde celui
		 * qu'elle avait, qui resservira si elle y revient. La règle lit `validation`, construite
		 * juste avant (les listes de `LessonLib` la rangent devant), et rien quand elle a été refusée.
		 * Un module refusé la laisse aussi sans valeur : l'étape `questioned` s'y retire par une
		 * `PropertySkip`, qui interrompt la propriété avant son `set` (`framework/core/Data.php`).
		 */
		$l->property('quizThreshold')
			->addAfterCheck('required', function(?int $quizThreshold, \PropertyList $l): bool {

				$l->skipIfInvalid(['chapter', 'validation']);

				return (
					$quizThreshold !== NULL or
					$this['validation'] !== Lesson::QUIZ
				);

			});

	}

}
?>
