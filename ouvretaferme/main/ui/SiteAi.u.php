<?php
namespace main;

/**
 * Les adresses du site principal, servies telles quelles sur son hôte et absolues ailleurs. Un lien
 * relatif suit l'hôte courant, que `<base href>` pose (`Root.php`) : servi par un autre domaine que le
 * site (boutiques, réseaux), il y mènerait à une page absente.
 *
 * Le gabarit (`RootTemplate::getSiteUrl()`) et les adresses de structure (`StructureUi::getMenuUrl()`)
 * passent tous les deux par ici : le test d'hôte n'existe qu'en un exemplaire.
 */
class SiteAiUi {

	public static function url(string $path, ?string $host = LIME_HOST): string {

		if(\Lime::getHost() === $host) {
			return $path;
		} else {
			return \Lime::getUrl().$path;
		}

	}

}
?>
