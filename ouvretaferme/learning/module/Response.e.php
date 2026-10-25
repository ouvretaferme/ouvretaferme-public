<?php
namespace learning;

/**
 * Un choix coché par un lecteur à une tentative du quiz, ou une ligne sans choix pour une question
 * laissée sans réponse. Les lignes d'une tentative s'affichent ensemble par `attempt`, jamais une à
 * une, et ne sortent que par `ResponseLib`. Elles n'ont pas de droit propre : `attempt` n'est qu'un
 * numéro, donc la ligne suit ce qui la porte, le lecteur qui a répondu et la question qu'il lit.
 */
class Response extends ResponseElement {

	public function canRead(): bool {

		$this->expects(['user', 'question']);

		return (
			$this['user']->isOnline() and
			$this['question']->canRead()
		);

	}

}
?>
