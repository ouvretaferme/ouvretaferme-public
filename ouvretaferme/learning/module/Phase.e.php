<?php
namespace learning;

/**
 * Une phase range les modules d'une même formation sous un titre commun (« Préparer la saison »),
 * sans rien changer à leur numérotation. Elle ne classe pas la formation au catalogue, ce qui est le
 * rôle de sa thématique (`Course.theme`). Un module n'en a pas forcément (`Chapter.phase`).
 *
 * Les droits et l'état sont ceux de la formation, comme pour un module.
 */
class Phase extends PhaseElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'course' => Course::getSelectionWithCommunity(),
		];

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
	 * Par l'API, une phase neuve ne reçoit sa formation qu'avec le corps de la requête, après ce
	 * contrôle : l'étape `course.owned` en juge alors l'état, comme pour un module.
	 */
	public function acceptCreate(): bool {
		return TRUE;
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

	}

}
?>
