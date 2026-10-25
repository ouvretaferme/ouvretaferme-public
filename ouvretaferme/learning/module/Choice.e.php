<?php
namespace learning;

/**
 * Un choix d'une question de quiz : un libellé, une image, ou les deux, et s'il est juste. Il ne
 * s'écrit jamais seul, mais avec sa question (`ChoiceLib::buildForQuestion()`), dont il suit les
 * droits.
 */
class Choice extends ChoiceElement {

	public function canRead(): bool {

		$this->expects(['question']);

		return $this['question']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['question']);

		return $this['question']->canWrite();

	}

	public static function properties(\PropertyList $l): void {

		/**
		 * `filled` lit le libellé construit juste avant : `ChoiceLib::getPropertiesCreate()` range
		 * `label` devant `image`, `build()` suivant l'ordre de la liste. Un libellé refusé n'a rien
		 * posé sur l'élément, d'où `skipIfInvalid()`.
		 *
		 * `owned` suit la règle de `Question::image.owned`, sur un hash qui change seulement.
		 */
		$l->property('image')
			->addAfterCheck('filled', function(?string $hash, \PropertyList $l): bool {

				$l->skipIfInvalid('label');

				return (
					$hash !== NULL or
					$this['label'] !== NULL
				);

			})
			->addAfterCheck('owned', fn(?string $hash): bool => (
				$hash === NULL or
				($this->exists() and $this['image'] === $hash) or
				Question::isImageOwned($hash)
			));

	}

}
?>
