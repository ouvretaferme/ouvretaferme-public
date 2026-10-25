<?php
namespace learning;

class Theme extends ThemeElement {

	const COLORS = [
		'production' => '#0b694c',
		'commercialisation' => '#3b4790',
		'accounting' => '#1a6277',
		'settings' => '#653585',
	];

	const ICONS = ['laptop', 'leaf', 'basket3', 'piggy-bank', 'compass', 'signpost-2', 'egg', 'tree', 'award', 'house-door', 'people', 'heart-pulse'];

	/**
	 * La section du socle dont la thématique emprunte la couleur, ou `NULL` quand la formation n'est
	 * pas encore classée. Aucune section par défaut : elle peindrait une formation non classée aux
	 * couleurs d'une thématique qu'elle n'a pas.
	 */
	public function getSection(): ?string {

		if($this->empty()) {
			return NULL;
		}

		$this->expects(['color']);

		return array_search($this['color'], Theme::COLORS, TRUE);

	}

	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Une thématique qui porte des formations ne se supprime pas : elle se fusionne dans une autre.
	 * Le parent garde la règle d'écriture (`acceptWrite()`), qu'une surcharge qui ne le
	 * rappellerait pas perdrait.
	 */
	public function acceptDelete(): bool {

		$this->expects(['courses']);

		return (
			parent::acceptDelete() and
			$this['courses'] === 0
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('icon')
			->replaceCheck(fn(?string $icon): bool => in_array($icon, Theme::ICONS, TRUE));

		$l->property('color')
			->replaceCheck(fn(?string $color): bool => in_array($color, Theme::COLORS, TRUE));

	}

}
?>
