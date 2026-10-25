<?php
namespace structure;

/**
 * Les interstitiels : un message plein écran servi une fois, à un moment précis.
 *
 * Remplace le dispositif précédent, qui redirigeait vers une page dédiée et se
 * pilotait par des constantes de dates dans `StructureSetting` — donc une seule
 * campagne à la fois, la même pour tout le monde, et un `Structure::run()` qui
 * détournait n'importe quelle page vers l'écran de collecte.
 *
 * Le renversement : un interstitiel ne s'impose plus, il est **demandé** par
 * l'URL qui y mène (`?interstitial=<nom>`). C'est celui qui vient de faire
 * quelque chose — s'inscrire depuis les relevés de prix, par exemple — qui l'appelle
 * dans sa redirection. La page, elle, ne sait rien de la campagne en cours.
 *
 * Ce que la structure a déjà vu est mémorisé dans `Structure.interstitials`, une
 * map nom → date : un interstitiel ne se sert qu'une fois, même si l'URL est
 * rejouée, partagée ou remise en favori.
 */
class InterstitialLib {

	// Servi à qui vient de créer sa ferme depuis le tunnel des relevés de prix : ils
	// sont sa porte d'entrée, et il ne sait pas encore que le reste du logiciel
	// existe.
	const SIGNUP_QUOTATION = 'signup-quotation';

	// Le paramètre qui demande un interstitiel, et la liste de ceux qui existent.
	// Un nom absent de cette liste est ignoré sans bruit : l'URL est publique, elle
	// se tape et se partage.
	const PARAMETER = 'interstitial';

	/**
	 * Les interstitiels servis, et leurs règles.
	 *
	 * `repeatable` décide si le message se rejoue : à FALSE il ne sort qu'une fois
	 * par ferme, quoi qu'on fasse de l'URL ; à TRUE il ressort à chaque demande.
	 *
	 * Rejouable pour l'arrivée par les relevés de prix : ce n'est pas une campagne mais un
	 * mode d'emploi, et c'est l'URL qui décide de le montrer. Personne n'y retombe
	 * par hasard — il faut le paramètre —, et une équipe qui se le partage ou une
	 * démonstration qui le rejoue vaut mieux qu'un message perdu au premier
	 * rafraîchissement.
	 */
	public static function getAll(): array {

		return [
			self::SIGNUP_QUOTATION => ['repeatable' => TRUE],
		];

	}

	public static function getNames(): array {
		return array_keys(self::getAll());
	}

	public static function isRepeatable(string $name): bool {
		return (self::getAll()[$name]['repeatable'] ?? FALSE);
	}

	/**
	 * L'interstitiel demandé par l'URL courante, s'il est servi.
	 *
	 * Rend NULL dans tous les autres cas — pas de paramètre, nom inconnu, structure
	 * qui l'a déjà vu, ou visiteur qui ne gère pas cette ferme.
	 */
	public static function getRequested(Structure $eStructure): ?string {

		if(get_exists(self::PARAMETER) === FALSE) {
			return NULL;
		}

		$name = GET(self::PARAMETER, 'string');

		return self::accept($eStructure, $name) ? $name : NULL;

	}

	public static function accept(Structure $eStructure, string $name): bool {

		if(in_array($name, self::getNames(), TRUE) === FALSE) {
			return FALSE;
		}

		if($eStructure->empty() or $eStructure->canManage() === FALSE) {
			return FALSE;
		}

		if(self::isRepeatable($name)) {
			return TRUE;
		}

		return (array_key_exists($name, self::getSeen($eStructure)) === FALSE);

	}

	/**
	 * L'interstitiel a été servi.
	 *
	 * Écrit au moment où le contenu est rendu, et non au chargement de la page qui
	 * le demande : une modale qu'on n'a pas vue ne compte pas. La date est notée même
	 * pour un interstitiel rejouable — elle ne lui ferme aucune porte, et elle dit
	 * quand la ferme a vu le message pour la dernière fois.
	 */
	public static function ping(Structure $eStructure, string $name): void {

		$seen = self::getSeen($eStructure);

		if(($seen[$name] ?? NULL) === \Clock::date()) {
			return;
		}

		$seen[$name] = \Clock::date();

		// La valeur est portée sur l'élément avant d'être extraite : `Module::update()`
		// clone la donnée qu'on lui passe, l'élément de l'appelant resterait sinon
		// périmé pour la suite de la requête.
		$eStructure['interstitials'] = $seen;

		StructureLib::update($eStructure, ['interstitials']);

	}

	private static function getSeen(Structure $eStructure): array {

		$eStructure->expects(['interstitials']);

		return $eStructure['interstitials'];

	}

}
?>
