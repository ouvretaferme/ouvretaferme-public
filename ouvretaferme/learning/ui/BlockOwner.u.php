<?php
namespace learning;

/**
 * Les mots de la formation dans l'éditeur des blocs. La classe est résolue par son nom, comme
 * `BlockOwnerLib` : elle ne porte pas le suffixe `Ai`.
 */
class BlockOwnerUi {

	public function getPlanTitle(string $owner): string {

		return match($owner) {
			BlockOwnerLib::LESSON => s("Plan de la leçon"),
		};

	}

}
?>
