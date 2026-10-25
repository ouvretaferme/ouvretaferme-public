<?php
namespace map;

/**
 * Une tuile du relief d'une structure, que le cron lit à l'IGN (ReliefLib::synchronize()) et que la scène 3D
 * reçoit telle quelle. Elle n'a pas d'écran : aucun formulaire ne la construit, aucune route ne l'écrit.
 */
class Relief extends ReliefElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

}
?>
