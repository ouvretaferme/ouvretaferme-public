<?php
namespace structure;

class Production extends ProductionElement {

	const CROP = ['maraichage', 'legume', 'arboriculture', 'grande-culture', 'vigne', 'petit-fruit', 'fleur', 'semence', 'horticulture', 'ppam', 'champignon'];
	const ANIMAL = ['bovin', 'caprin', 'ovin', 'porcin', 'volaille', 'apiculture'];
	const CRAFT = ['boulangerie', 'brasserie', 'conserverie', 'cosmetique', 'restauration', 'transformation'];
	const OTHER = ['other'];

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function getAll(): array {
		return array_merge(self::CROP, self::ANIMAL, self::CRAFT, self::OTHER);
	}

	/**
	 * Les métiers auxquels un champ s'applique.
	 *
	 * `match` sans `default` : une clé inconnue échoue franchement. La forme précédente rendait
	 * `NULL` sur un warning, et `in_array($fqn, NULL)` est une fatale depuis PHP 8.4 — en ajax,
	 * le warning avait déjà cassé le JSON avant qu'elle ne survienne. Les trois champs qui
	 * valaient `NULL` valent la liste complète : ils s'appliquent à tous les métiers.
	 */
	public static function getOptions(string $option): array {

		return match($option) {

			'description', 'startedAt', 'organic' => self::getAll(),

			'area' => ['maraichage', 'legume', 'arboriculture', 'grande-culture', 'vigne', 'petit-fruit', 'fleur', 'semence', 'ppam'],
			'areaGreenhouse' => ['maraichage', 'legume', 'petit-fruit', 'fleur', 'semence', 'ppam'],
			'mechanisation' => ['maraichage', 'fleur', 'semence', 'ppam'],

			'systemFarming' => array_merge(self::CROP, self::ANIMAL)

		};

	}

	public function acceptOption(string $option): bool {
		return in_array($this['fqn'], self::getOptions($option), TRUE);
	}

	public static function properties(\PropertyList $l): void {

		$l->property('fqn')
			->replaceCheck(function(?string $fqn): bool {

				// fqn est un text8 côté schéma, mais c'est un vocabulaire fermé : hors de cette liste,
				// acceptOption() renvoie FALSE partout et l'atelier perd silencieusement ses champs.
				return in_array($fqn, self::getAll(), TRUE);

			});

		$l->property('area')
			->addAfterCast('option', function(?float &$area, \PropertyList $l) {

				// acceptOption() lit fqn : sans lui, rien à décider.
				$l->skipIfInvalid('fqn');

				if($this->acceptOption('area') === FALSE) {
					$area = NULL;
				}

				return TRUE;

			});

		$l->property('areaGreenhouse')
			->addAfterCast('option', function(?int &$area, \PropertyList $l) {

				$l->skipIfInvalid('fqn');

				if($this->acceptOption('areaGreenhouse') === FALSE) {
					$area = NULL;
				}

				return TRUE;

			});

	}

}
?>