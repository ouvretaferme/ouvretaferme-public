<?php
namespace structure;

Class ConfigurationHistoryUi {

	public static function p(string $property): \PropertyDescriber {

		$d = ConfigurationHistory::model()->describer($property, [
			'effectiveAt' => s("Quand ce changement de la redevabilité à la TVA a-t-il eu lieu ?"),
		]);

		switch($property) {

			case 'effectiveAt' :
				$d->prepend = \Asset::icon('calendar-date');
				break;

		}

		return $d;

	}
}
