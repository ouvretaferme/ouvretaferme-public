<?php
namespace plant;

class VarietyRating extends VarietyRatingElement {

	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

}
?>