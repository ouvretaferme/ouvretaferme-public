<?php
namespace aid;

class AreaUi {

	public static function getDepartmentBadge(string $department, bool $isOff = FALSE): string {
		return '<span class="badge badge-reference'.($isOff ? ' badge-off' : '').'">'.$department.'</span> '.encode(AidSetting::DEPARTMENTS[$department][0]);
	}

	// Le nom de chaque département, par son code.
	public static function getDepartments(): array {
		return array_map(fn(array $department) => $department[0], AidSetting::DEPARTMENTS);
	}

	public static function p(string $property): \PropertyDescriber {

		$d = Area::model()->describer($property, [
			'department' => s("Département"),
			'note' => s("Précision"),
		]);

		switch($property) {

			case 'department' :
				$d->field = 'select';
				// Un département déjà au périmètre ne se propose pas une seconde fois (Area::department.duplicate).
				$d->values = fn(Area $e) => array_diff_key(
					self::getDepartments(),
					array_flip($e['aid']['cArea']->getColumn('department'))
				);
				$d->placeholder = s("Département");
				break;

			case 'note' :
				$d->placeholder = s("Zone de montagne, aire de captage...");
				break;

		}

		return $d;

	}

}
?>
