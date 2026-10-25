<?php
namespace toolbox;

/**
 * Un document publié dont le référentiel des besoins en fertilisation tire ses valeurs. Comme le reste
 * du référentiel, il ne s'écrit que par `toolbox/configure/fertilizationReference` et se lit sans condition.
 */
class FertilizationSource extends FertilizationSourceElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return FALSE;
	}

}
?>
