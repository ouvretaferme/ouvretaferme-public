<?php
namespace learning;

/**
 * Une question du quiz d'une leçon, à une ou plusieurs bonnes réponses parmi ses choix. Elle se lit
 * et s'écrit comme sa leçon.
 *
 * `lesson` est nullable pour les questions de parcours (L8) : en L5, toute question porte sa leçon.
 */
class Question extends QuestionElement {

	/**
	 * Au-dessous, une question n'offre pas de choix ; au-dessus, elle devient une liste qu'on lit
	 * sans la peser (arbitrage QZ4 de la planche, 21/09).
	 */
	const CHOICES_MIN = 2;
	const CHOICES_MAX = 6;

	public static function getSelection(): array {

		return parent::getSelection() + [
			'lesson' => Lesson::getSelection(),
		];

	}

	public function canRead(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->canWrite();

	}

	public function acceptRead(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->acceptRead();

	}

	public function acceptWrite(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->acceptWrite();

	}

	/**
	 * Une image de quiz se dépose par `/media/:put` sans élément : le hash revient au navigateur, qui
	 * le poste avec la question. Un hash posté est donc celui d'un média que le compte a lui-même
	 * déposé sous ce type, la même règle que `/media/:put` applique à un remplacement
	 * (`base/media/page/index.p.php`). Sans elle, on poserait sur sa question l'image d'un autre
	 * compte en recopiant son hash.
	 */
	public static function isImageOwned(string $hash): bool {

		return \media\Media::model()
			->whereType('learning-quiz')
			->whereHash($hash)
			->whereUser(\user\ConnectionLib::getOnline())
			->whereStatus('!=', \media\Media::DELETED)
			->exists();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('type')
			->replaceCheck(fn(?string $type): bool => in_array($type, [Question::SINGLE, Question::MULTIPLE], TRUE));

		/**
		 * Le contrôle ne tire que sur un hash qui change : une question copiée d'une autre formation,
		 * ou écrite par un autre membre de l'équipe, garde une image que le compte connecté n'a pas
		 * déposée, et doit pouvoir se modifier sans la perdre. La valeur de comparaison est celle que
		 * la page a relue avant `build()`, `image` étant dans la sélection de la question.
		 */
		$l->property('image')
			->addAfterCheck('owned', fn(?string $hash): bool => (
				$hash === NULL or
				($this->exists() and $this['image'] === $hash) or
				Question::isImageOwned($hash)
			));

	}

}
?>
