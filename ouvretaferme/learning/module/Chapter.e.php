<?php
namespace learning;

class Chapter extends ChapterElement {

	/**
	 * Les leçons que le parcours de création pose avec le premier module, les suivantes s'ajoutant
	 * depuis l'éditeur. La borne arrête un envoi forgé, pas un auteur : les plus longs modules en
	 * comptaient 6 à la mesure du 26/09.
	 */
	const LESSONS_START_MAX = 20;

	public static function getSelection(): array {

		return self::getSelectionWithoutCourse() + [
			'course' => Course::getSelectionWithCommunity(),
		];

	}

	/**
	 * Sans la formation, que l'appelant qui la tient déjà accroche à chaque ligne.
	 */
	public static function getSelectionWithoutCourse(): array {
		return parent::getSelection();
	}

	public function canRead(): bool {

		$this->expects(['course']);

		return $this['course']->canRead();

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
	 * Par l'API, un module neuf ne reçoit sa formation qu'avec le corps de la requête, après ce
	 * contrôle : l'étape `course.owned` en juge alors l'état. L'écran pose la formation d'abord et
	 * la contrôle par `acceptWrite()`.
	 */
	public function acceptCreate(): bool {
		return TRUE;
	}

	/**
	 * Une formation publiée garde au moins une leçon en ligne, où elle ne se publie pas sans
	 * (`holdsProgram`, décision du 05/10), et en présentiel tant qu'un inscrit l'a suivie
	 * en entier (`holdsCompleted`, décision du 04/10). Jamais sur `acceptWrite()`, à quoi la classe
	 * délègue : ce serait interdire de modifier un module d'une formation publiée. Les deux se posent
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

		return $this['course']->assertRead();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('course')
			->addAfterCheck('owned', function(Course $eCourse): bool {

				$this->expects(['cCourse']);

				return (
					$this['cCourse']->offsetExists($eCourse['id']) and
					$this['cCourse'][$eCourse['id']]->acceptWrite()
				);

			});

		// La formation de la phase se lit dans `cPhase`, chargée dans la structure de l'appelant : la
		// phase postée ne porte que son id. `course` précède `phase` dans la liste de création, et se lit
		// sur le module existant en modification ; refusée, elle n'a aucune valeur sûre à comparer et
		// son erreur suffit.
		$l->property('phase')
			->addAfterCheck('consistent', function(Phase $ePhase, \PropertyList $l): bool {

				if(
					$ePhase->empty() or
					($l->hasProperty('course') and $l->isBuilt('course') === FALSE)
				) {
					return TRUE;
				}

				$this->expects(['course', 'cPhase']);

				return (
					$this['cPhase']->offsetExists($ePhase['id']) and
					$this['cPhase'][$ePhase['id']]['course']['id'] === $this['course']['id']
				);

			});

	}

}
?>
