<?php
namespace product;

class Configuration extends ConfigurationElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

}
?>
