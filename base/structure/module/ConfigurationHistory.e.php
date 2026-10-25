<?php
namespace structure;

class ConfigurationHistory extends ConfigurationHistoryElement {

	public function canRead(): bool {
		return TRUE;
	}

	public static function properties(\PropertyList $l): void {

		$l->property('effectiveAt')
			->addAfterCast('valid', function(?string &$effectiveAt): bool {

				$this->expects(['structure']);

				$effectiveAt = var_filter($effectiveAt, '?string');

				return $effectiveAt !== NULL and \util\DateLib::isValid($effectiveAt);

			});

	}

}
?>
