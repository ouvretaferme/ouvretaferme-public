<?php
namespace editor;

/**
 * Toute adresse qu'un média porte traverse-t-elle encore un filtre ?
 *
 * Le nettoyage de l'éditeur lit les adresses des médias dans des cartes d'attributs, une par type,
 * et le formateur les repose en `src` et en `href` chez le lecteur, telles quelles sauf celle d'une
 * vidéo, recomposée depuis la liste des hébergeurs. Celui qui
 * ajoutera un type de média, ou une adresse à un type existant, travaillera dans cette carte et
 * n'aura aucune raison de relire `isSafeUrl()` vingt lignes plus bas : le filtre s'oublie sans
 * qu'aucun écran ne bouge, et le contenu part sur la boutique publique.
 *
 * Deux inventaires, tous deux dans les deux sens. Le premier relève les attributs d'adresse que le
 * fichier nomme, le second ceux qu'il donne réellement à un filtre : un attribut qui apparaît sans
 * être déclaré échoue, un attribut déclaré qui disparaît aussi, et un filtre retiré fait tomber le
 * second.
 *
 * Lancement : dev/test package=editor file=MediaUrlSentinel
 */
class MediaUrlSentinelTest extends \Test {

	/**
	 * Les attributs que le nettoyage nomme et qui portent `url` ou `link` dans leur nom, avec ce
	 * qui les couvre. Un attribut de texte s'y trouve aussi : son nom le fait relever, et dire
	 * pourquoi il n'est pas filtré vaut mieux que de le taire.
	 */
	const ATTRIBUTES = [
		'data-url' => 'adresse d\'une image ou d\'une vidéo',
		'data-link' => 'lien posé sur une image',
	];

	/**
	 * Ce que chaque filtre reçoit. `isSafeSourceUrl()` ne laisse passer que http(s), pour les
	 * adresses que le navigateur charge seul ; `isSafeUrl()` garde la liste noire du lien de texte,
	 * pour celles que le lecteur suit d'un clic ; `EditorSetting::getVideoUrl()` recompose l'adresse
	 * d'une vidéo depuis la liste des hébergeurs, parce qu'une `<iframe>` charge une page entière
	 * et que http(s) ne dit rien de celle-là.
	 */
	const FILTERED = [
		'data-url' => ['getVideoUrl', 'isSafeSourceUrl'],
		'data-link' => ['isSafeUrl'],
		'href' => ['isSafeUrl'],
	];

	private function getSource(): string {
		return file_get_contents(new \ReflectionClass(\editor\XmlLib::class)->getFileName());
	}

	public function testEveryUrlAttributeIsDeclared(): void {

		preg_match_all('/\'(data-[a-z0-9-]*(?:url|link)[a-z0-9-]*)\??\'/i', $this->getSource(), $matches);

		$found = array_unique($matches[1]);
		$declared = array_keys(self::ATTRIBUTES);

		sort($found);
		sort($declared);

		$this->assertEquals([], array_values(array_diff($found, $declared)), 'attribut d\'adresse trouvé dans le nettoyage et non déclaré ici : '.implode(', ', array_diff($found, $declared)));
		$this->assertEquals([], array_values(array_diff($declared, $found)), 'attribut déclaré ici et absent du nettoyage, déclaration périmée : '.implode(', ', array_diff($declared, $found)));

	}

	public function testEveryFilteredAttributeIsDeclared(): void {

		preg_match_all('/(?:\$this->|EditorSetting::)((?:isSafe|get)[A-Za-z]*Url)\(\$node->getAttribute\(\'([a-z0-9-]+)\'\)/i', $this->getSource(), $matches, PREG_SET_ORDER);

		$found = [];

		foreach($matches as $match) {
			$found[$match[2]][] = $match[1];
		}

		foreach($found as $attribute => $filters) {
			$found[$attribute] = array_values(array_unique($filters));
			sort($found[$attribute]);
		}

		$declared = self::FILTERED;

		foreach($declared as $attribute => $filters) {
			sort($declared[$attribute]);
		}

		ksort($found);
		ksort($declared);

		$this->assertEquals([], array_values(array_diff_key($found, $declared)), 'attribut filtré par le nettoyage et non déclaré ici : '.implode(', ', array_keys(array_diff_key($found, $declared))));
		$this->assertEquals([], array_values(array_diff_key($declared, $found)), 'attribut déclaré filtré ici et que le nettoyage ne filtre plus : '.implode(', ', array_keys(array_diff_key($declared, $found))));

		foreach($declared as $attribute => $filters) {

			$this->assertEquals($filters, $found[$attribute] ?? [], 'le filtre de '.$attribute.' a changé : '.implode(', ', $found[$attribute] ?? []));

		}

	}

}
?>
