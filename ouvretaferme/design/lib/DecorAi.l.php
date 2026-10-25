<?php
namespace design;

/**
 * Le décor des tests, mis en forme pour l'onglet Tests du design system.
 *
 * Tout se lit dans `FixtureAiLib::DECOR` et `USERS`, sans requête : la page décrit ce que le décor
 * déclare, et le décor est tenu égal à la base par `FixtureSentinelAi` et le script qui le pose.
 * Lire la base ici montrerait l'état d'une base de dev, qui peut être en retard sur la déclaration.
 */
class DecorAiLib {

	/**
	 * Les structures du décor, par code (`F1`, `G3`…), dans l'ordre de `DECOR`.
	 *
	 * `kind` est le type de la structure, précisé par son sous-type quand le type en a un
	 * (`producer-real`, `community-network`) : c'est ce que l'icône de la fiche distingue.
	 *
	 * Chaque compte porte la clé de rôle telle que `DECOR` l'écrit. Les fantômes y entrent avec la
	 * clé `ghost`, celle que `MemberLib::createGhostUser()` leur pose : pour qui lit la fiche, un
	 * ancien membre est un compte de la structure au même titre que les autres.
	 */
	public static function getStructures(): array {

		$structures = [];

		foreach(\structure\FixtureAiLib::DECOR as $entry) {

			$members = [];

			foreach($entry['members'] as $email => $role) {
				$members[] = self::getMember($email, $role);
			}

			foreach($entry['ghosts'] as $email) {
				$members[] = self::getMember($email, 'ghost');
			}

			$structures[$entry['code']] = [
				'code' => $entry['code'],
				'name' => $entry['name'],
				'kind' => self::getKind($entry),
				'members' => $members,
			];

		}

		return $structures;

	}

	/**
	 * Les comptes du décor, par courriel, dans l'ordre de `USERS`, chacun avec son rôle dans chaque
	 * structure où il figure (code => clé de rôle). Un compte peut n'en avoir aucune.
	 */
	public static function getUsers(): array {

		$users = [];

		foreach(\structure\FixtureAiLib::USERS as $email => $user) {
			$users[$email] = [
				'email' => $email,
				'firstName' => $user['firstName'],
				'lastName' => $user['lastName'],
				'roles' => [],
			];
		}

		foreach(self::getStructures() as $code => $structure) {
			foreach($structure['members'] as $member) {
				$users[$member['email']]['roles'][$code] = $member['role'];
			}
		}

		// L'ordre de `DECOR` suit les identifiants, qui ne disent rien au lecteur : G3 y précède G1.
		foreach($users as $email => $user) {
			uksort($users[$email]['roles'], 'strnatcmp');
		}

		return $users;

	}

	private static function getKind(array $entry): string {

		return match($entry['type']) {
			\structure\Structure::PRODUCER => $entry['type'].'-'.$entry['producerType'],
			\structure\Structure::COMMUNITY => $entry['type'].'-'.$entry['communityType'],
			\structure\Structure::SHOP, \structure\Structure::AMAP => $entry['type'],
		};

	}

	private static function getMember(string $email, string $role): array {

		$user = \structure\FixtureAiLib::USERS[$email];

		return [
			'email' => $email,
			'firstName' => $user['firstName'],
			'lastName' => $user['lastName'],
			'role' => $role,
		];

	}

}
?>
