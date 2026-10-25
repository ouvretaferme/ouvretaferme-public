<?php
namespace structure;

/**
 * La structure visée par une URL peut s'écrire « x » au lieu de son id, dans les deux formes
 * d'adresse qui en portent une : /ferme/x/assolement et /x/cahier-de-caisse. Celui qui n'a
 * qu'une structure y est envoyé directement, celui qui en a plusieurs choisit, et son choix
 * rejoue l'adresse demandée avec le bon id.
 *
 * Le segment se lit sur LIME_REQUEST_PATH et non sur LIME_PAGE_REQUESTED : la seconde porte le
 * nom de page après réécriture nginx, qui transforme /{id}/… en /@/… et ne reconnaît pas le
 * segment sentinelle. Seule la première garde l'adresse telle qu'elle a été demandée.
 *
 * La réécriture n'a donc rien à connaître de la sentinelle : la requête lui échappe, atteint
 * PHP par la règle générique, et en ressort sous une adresse numérique qu'elle sait déjà
 * traiter.
 */
class StructureSentinelAiLib {

	const SENTINEL = 'x';

	/**
	 * 302 et non le 301 par défaut de RedirectAction : l'adresse est la même pour tout le monde
	 * et sa cible dépend de qui est connecté. Un 301 la figerait dans le cache du navigateur,
	 * y compris sur un poste partagé.
	 */
	const HTTP_STATUS = 'HTTP/1.1 302 Found';

	public static function resolve(\stdClass $data): void {

		$prefix = self::getPrefix();

		if($prefix === NULL) {
			return;
		}

		// Une adresse sentinelle se tape ou se met en signet. Résoudre un POST supposerait de
		// reposter son corps vers une autre adresse, ce que ni le navigateur ni la couche ajax
		// ne savent faire : la page reprend la main et refuse d'elle-même un id qu'elle ne
		// trouve pas. Lever ici une NotAction rejouerait la page d'erreur, qui repasserait par
		// ce constructeur et relèverait la même action jusqu'à épuiser la mémoire.
		if(\Route::getRequestMethod() !== 'GET') {
			return;
		}

		\user\ConnectionLib::checkLogged();

		$cStructure = self::getStructures($data->ccStructureUser, $prefix);

		if($cStructure->empty()) {
			throw new \RedirectAction('/', httpStatus: self::HTTP_STATUS);
		}

		foreach($cStructure as $eStructure) {
			$eStructure['urlRequested'] = self::getUrlRequested($prefix, $eStructure);
		}

		if($cStructure->count() === 1) {
			throw new \RedirectAction($cStructure->first()['urlRequested'], httpStatus: self::HTTP_STATUS);
		}

		$data->cStructureSentinel = $cStructure;

		throw new \ViewAction($data, path: 'structure/structureSentinelAi:index');

	}

	/**
	 * Le préfixe qui précède le segment sentinelle, qui distingue les deux formes d'adresse :
	 * 'ferme/' pour /ferme/x/…, la chaîne vide pour /x/…. NULL quand l'adresse n'en porte pas.
	 *
	 * La seconde forme exige un chemin derrière elle là où la première s'en passe : /ferme/x
	 * mène à la fiche d'une structure, alors que /x seul n'a pas de cible, nginx ne réécrivant
	 * vers la comptabilité que les adresses en /{id}/quelque-chose. Sans cette borne, le choix
	 * proposerait des cartes qui mènent toutes à un 404.
	 */
	private static function getPrefix(): ?string {

		$patterns = [
			'ferme/' => '#^/ferme/'.self::SENTINEL.'(?:/|$)#',
			'' => '#^/'.self::SENTINEL.'/#'
		];

		foreach($patterns as $prefix => $pattern) {

			if(preg_match($pattern, LIME_REQUEST_PATH) === 1) {
				return $prefix;
			}

		}

		return NULL;

	}

	/**
	 * L'adresse demandée, son seul segment sentinelle remplacé par l'id de la structure.
	 *
	 * Une découpe et non un preg_replace : dans un remplacement, « $1 » suivi de l'id se lirait
	 * comme le groupe 1 suivi de ses chiffres dès que l'id commence par un chiffre.
	 *
	 * Le résultat commence toujours par « / » suivi du préfixe puis d'un id numérique, puisque
	 * getPrefix() vient de le vérifier : aucune adresse absolue ni protocole-relative ne peut
	 * en sortir.
	 */
	private static function getUrlRequested(string $prefix, Structure $eStructure): string {

		$sentinel = '/'.$prefix.self::SENTINEL;

		return '/'.$prefix.$eStructure['id'].substr(LIME_REQUEST_PATH, strlen($sentinel)).LIME_REQUEST_ARGS;

	}

	/**
	 * Les structures de l'utilisateur connecté que l'adresse demandée peut ouvrir, à plat :
	 * StructureLib::getOnline() les indexe par type puis par id, et l'ordre des types y est
	 * déjà celui du produit.
	 *
	 * Le préfixe dit lui-même quelles structures la page sert, puisque c'est la règle que
	 * StructureUi::url() applique déjà en sens inverse : un groupe s'ouvre sous /groupe/ et
	 * jamais sous /ferme/. Sans ce tri, le choix proposerait des structures dont on sait
	 * d'avance que la page leur répondrait 404.
	 */
	private static function getStructures(\Collection $ccStructure, string $prefix): \Collection {

		$cStructure = new \Collection();

		foreach($ccStructure as $cStructureByType) {
			$cStructure->mergeCollection($cStructureByType, reindex: FALSE);
		}

		return $cStructure->find(fn(Structure $eStructure) => match($prefix) {
			'ferme/' => $eStructure['type'] !== Structure::COMMUNITY,
			'' => $eStructure->assertAccounting()
		});

	}

}
?>
