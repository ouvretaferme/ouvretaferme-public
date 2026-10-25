<?php
namespace media;

/**
 * Quels fichiers media.ouvretaferme.org sert-il à qui en tient l'adresse ?
 *
 * **De quoi c'est l'inventaire.** Les types de `MediaSetting::$types`, chacun rangé public ou privé.
 * Un type public est une image qu'une page publique montre (boutique, site, vignette) ou qu'un
 * courriel embarque : son adresse se donne, et Nginx la sert sans session. Un type privé est un
 * document d'une structure (justificatif, relevé, facture, bilan, pièce jointe) : il se lit par la
 * route de son paquet, qui vérifie les droits, jamais par son adresse sur le stockage.
 *
 * **Pourquoi une sentinelle.** Le hash est imprévisible, mais une adresse qui le porte ouvre le fichier
 * à quiconque la reçoit, sans jeton ni session : une copie de base, un journal, un courriel transféré
 * suffisent. Le vhost `media` de `prod.cfg` et `dev.cfg` ne sert donc que les dossiers de `PUBLIC`, et
 * un type ajouté à `MediaSetting::$types` sans y être rangé serait refusé par Nginx sans que rien ne
 * le dise, ou servi à tous si on l'ajoutait au vhost sans y penser.
 *
 * Elle vérifie, dans les deux sens à chaque fois :
 * 1. chaque type est rangé dans `PUBLIC` ou dans `PRIVATE`, et chaque entrée désigne un type ;
 * 2. la liste du `location` du vhost `media` est exactement `PUBLIC`, en production comme en dev ;
 * 3. aucun fichier PHP ne demande l'adresse d'un type privé (`getUrlByHash()`, `getUrlByElement()`).
 *
 * La déclaration fait foi sur le vhost : un écart au point 2 se corrige dans le `.cfg`, et un écart au
 * point 1 se range ici, avec sa raison, avant d'ajouter le dossier au `.cfg`.
 *
 * Lecture seule, rien n'est écrit en base.
 *
 * Lancement : dev/test package=media file=MediaPublicSentinelAi
 */
class MediaPublicSentinelAiTest extends \Test {

	const PUBLIC = [
		'user-vignette' => 'la photo d\'un utilisateur, montrée à ses interlocuteurs et rendue par l\'API',
		'shop-logo' => 'le logo d\'une boutique en ligne publique',
		'shop-banner' => 'la bannière d\'une boutique en ligne publique',
		'amap-producer-photo' => 'la photo d\'un producteur sur la page publique de l\'AMAP',
		'editor' => 'les images d\'un texte riche, que les sites et les boutiques publient',
		'plant-vignette' => 'la vignette d\'une culture, commune à la bibliothèque',
		'product-vignette' => 'la photo d\'un produit en boutique',
		'learning-course' => 'la vignette d\'une formation du catalogue',
		'learning-quiz' => 'l\'image d\'une question de quiz',
		'block-item' => 'l\'image d\'une étape, d\'un volet, d\'un onglet ou d\'un chiffre, montrée avec le contenu qui la porte',
		// Les trois suivants sont des photos que seuls les membres voient. Elles restent servies tant
		// qu'aucune route authentifiée ne les remplace : leurs écrans les montrent par leur adresse.
		'maintenance-done' => 'les photos d\'un entretien de matériel, montrées par leur adresse',
		'tool-vignette' => 'la vignette d\'un outil, montrée par son adresse',
		'gallery' => 'les photos de la galerie d\'une structure, montrées par leur adresse',
		'structure-email-banner' => 'la bannière des courriels d\'une structure, que le client lit dans sa messagerie',
		'structure-banner' => 'la bannière d\'une structure sur ses pages publiques',
		'structure-logo' => 'le logo d\'une structure, sur ses pages publiques, ses courriels et ses PDF',
		'website-favicon' => 'l\'icône d\'un site public',
		'website-logo' => 'le logo d\'un site public',
		'website-banner' => 'la bannière d\'un site public',
		'webpage-banner' => 'la bannière d\'une page de site public',
	];

	const PRIVATE = [
		'amap-charter' => 'la charte d\'une AMAP, téléchargée par la page de configuration',
		'pdf-content' => 'les PDF de ventes et de factures, lus par le paquet selling',
		'association-document' => 'les reçus et factures d\'adhésion, lus par le paquet association',
		'invoicing-event' => 'les pièces jointes d\'une facture électronique reçue',
		'invoicing-pdf' => 'le PDF d\'une facture électronique',
		'financial-year-pdf' => 'les états financiers d\'un exercice, lus par l\'écran ou par la route financialYear/{id}/document de l\'API',
		'learning-attachment' => 'les pièces jointes d\'une leçon, servies par la route qui vérifie l\'inscription',
		'learning-date-sheet' => 'la feuille d\'émargement d\'une session de formation',
		'external-file' => 'les fichiers importés d\'une vente externe',
		'proof-file' => 'les justificatifs comptables',
		'bank-statement' => 'les relevés bancaires importés',
		'user-avatar-upload' => 'le modèle 3D téléversé par un utilisateur, servi par la route user/avatar/modele/{id} à son auteur et aux membres d\'une structure commune qui le montre dans ses scènes',
		'map-decor-upload' => 'le modèle 3D téléversé à la place d\'un bâtiment, servi par la route map/decorUpload:model à ceux qui gèrent la structure',
	];

	const VHOSTS = [
		'ouvretaferme/main/conf/rewrite/prod.cfg' => 'media.ouvretaferme.org',
		'ouvretaferme/main/conf/rewrite/dev.cfg' => 'media.dev-ouvretaferme.org',
	];

	public function testEveryTypeIsClassified(): void {

		$types = array_keys(MediaSetting::$types);
		$declared = array_merge(array_keys(self::PUBLIC), array_keys(self::PRIVATE));

		$both = array_values(array_intersect(array_keys(self::PUBLIC), array_keys(self::PRIVATE)));
		$this->assertEquals([], $both, 'type déclaré à la fois public et privé : '.implode(', ', $both));

		$undeclared = array_values(array_diff($types, $declared));
		$this->assertEquals([], $undeclared, 'type de MediaSetting::$types rangé ni dans PUBLIC ni dans PRIVATE de MediaPublicSentinelAiTest : '.implode(', ', $undeclared).'. Le ranger avec sa raison, puis, s\'il est public, ajouter son dossier au location du vhost media de prod.cfg et dev.cfg');

		$stale = array_values(array_diff($declared, $types));
		$this->assertEquals([], $stale, 'entrée de MediaPublicSentinelAiTest qui ne désigne plus aucun type de MediaSetting::$types, à retirer : '.implode(', ', $stale));

	}

	public function testVhostServesOnlyPublicTypes(): void {

		foreach(self::VHOSTS as $file => $serverName) {

			$served = self::getServedDirectories($file, $serverName);

			$this->assertTrue($served !== NULL, 'location des dossiers servis introuvable dans le server '.$serverName.' de '.$file);

			$missing = array_values(array_diff(array_keys(self::PUBLIC), $served ?? []));
			$this->assertEquals([], $missing, 'type public que le vhost '.$serverName.' de '.$file.' ne sert pas, à ajouter au .cfg : '.implode(', ', $missing));

			$extra = array_values(array_diff($served ?? [], array_keys(self::PUBLIC)));
			$this->assertEquals([], $extra, 'dossier servi par le vhost '.$serverName.' de '.$file.' sans être déclaré public : '.implode(', ', $extra).'. La déclaration fait foi : retirer le dossier du .cfg, ou le ranger dans PUBLIC si l\'adresse doit se donner à tous');

		}

	}

	public function testNoUrlForPrivateType(): void {

		$classes = [];

		foreach(self::PRIVATE as $type => $reason) {
			$classes[] = MediaSetting::$types[$type]['class'].'Ui';
		}

		$pattern = '/(?:new\s+\\\\?(?:media\\\\)?(?:'.implode('|', $classes).')\(\)|MediaUi::getInstance\(\s*\'(?:'.implode('|', array_map(fn($type) => preg_quote($type, '/'), array_keys(self::PRIVATE))).')\'\s*\))\s*->\s*getUrlBy(?:Hash|Element)\(/';

		$found = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $file) {

			$content = file_get_contents($file);

			if(preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) > 0) {
				foreach($matches[0] as [$match, $offset]) {
					$found[] = substr($file, strlen(\dev\SentinelLib::getRoot()) + 1).':'.(substr_count($content, "\n", 0, $offset) + 1);
				}
			}

		}

		$this->assertEquals([], $found, 'adresse sur media.ouvretaferme.org demandée pour un type privé, que Nginx refuse : '.implode(' · ', $found).'. Le fichier se lit sur le disque par getBasenameByHash() et se rend par une route qui vérifie les droits');

	}

	/** Les dossiers du `location ~ ^/(?:…)/` du server qui porte ce nom, ou NULL s'il n'y en a pas. */
	private static function getServedDirectories(string $file, string $serverName): ?array {

		$content = file_get_contents(\dev\SentinelLib::getRoot().'/'.$file);

		foreach(preg_split('/^server\s*\{/m', $content) as $block) {

			if(
				preg_match('/server_name\s+'.preg_quote($serverName, '/').'\s*;/', $block) !== 1 or
				preg_match('/location\s+~\s+\^\/\(\?:([a-z0-9|-]+)\)\/\s*\{/', $block, $match) !== 1
			) {
				continue;
			}

			return explode('|', $match[1]);

		}

		return NULL;

	}

}
?>
