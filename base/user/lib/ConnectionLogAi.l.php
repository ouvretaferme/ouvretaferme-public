<?php
namespace user;

/**
 * Les connexions des administrateurs, lues dans `userLog` pour l'écran « Connexions admin ».
 *
 * Rien ne s'écrit ici : les traces sont posées par `LogLib::add()` depuis
 * `ConnectionLib::doLogIn()`. Une ligne `LOGIN_EXTERNAL` porte le compte emprunté dans `user` et
 * l'administrateur qui l'emprunte dans `userAction` : l'écran lit donc `user` pour un mot de passe
 * ou une reconnexion automatique, et `userAction` pour une connexion en tant qu'un autre compte
 * (arbitré le 23/09/2026, point 2 : 1 165 lignes contre 10 si l'on ne lisait que `user`).
 */
class ConnectionLogAiLib {

	const FILTER_PASSWORD = 'password';
	const FILTER_AUTO = 'auto';
	const FILTER_UNKNOWN = 'unknown';
	const FILTER_EXTERNAL = 'external';

	/**
	 * Une reconnexion automatique depuis une adresse que le compte n'a pas montrée dans cette
	 * fenêtre est marquée à l'écran. Seules les reconnexions depuis un ordinateur le sont : les
	 * réseaux mobiles changent d'adresse en permanence (53 reconnexions mobiles sur 202 auraient été
	 * marquées sur la copie de dev, contre 3 sur 118 depuis un ordinateur ; arbitré le 23/09/2026,
	 * point 3).
	 */
	const KNOWN_ADDRESS_IN_DAYS = 30;

	public static function getAll(\Search $search, int $page): array {

		self::applySearch($search);

		$cLog = Log::model()
			->select([
				'user' => ['firstName', 'lastName'],
				'userAction' => ['firstName', 'lastName'],
				'action', 'ip', 'device', 'createdAt'
			])
			->sort(['createdAt' => SORT_DESC])
			->option('count')
			->getCollection($page * UserSetting::MAX_BY_PAGE, UserSetting::MAX_BY_PAGE);

		$nLog = Log::model()->found();

		foreach($cLog as $eLog) {

			if($eLog['action'] === Log::LOGIN_EXTERNAL) {
				$eLog['eUserAdministrator'] = $eLog['userAction'];
				$eLog['eUserBorrowed'] = $eLog['user'];
			} else {
				$eLog['eUserAdministrator'] = $eLog['user'];
				$eLog['eUserBorrowed'] = new User();
			}

		}

		self::markUnknownAddresses($cLog);

		return [$cLog, $nLog];

	}

	public static function applySearch(\Search $search): void {

		$administrators = UserSetting::$admin;

		if($search->get('user')) {
			$administrators = [(int)$search->get('user')];
		}

		switch($search->get('action')) {

			case self::FILTER_PASSWORD :
				Log::model()
					->whereAction(Log::LOGIN)
					->whereUser('IN', $administrators);
				break;

			case self::FILTER_AUTO :
				Log::model()
					->whereAction(Log::LOGIN_AUTO)
					->whereUser('IN', $administrators);
				break;

			case self::FILTER_UNKNOWN :
				Log::model()
					->whereAction(Log::LOGIN_AUTO)
					->whereUser('IN', $administrators)
					->whereDevice(Log::WEB)
					->where('m1.createdAt', '>=', self::getMarkableFrom())
					->where('NOT '.self::getKnownAddressModel()->existsSql());
				break;

			case self::FILTER_EXTERNAL :
				Log::model()
					->whereAction(Log::LOGIN_EXTERNAL)
					->whereUserAction('IN', $administrators);
				break;

			default :
				$in = '('.implode(', ', $administrators).')';
				Log::model()->where(
					'(m1.action IN ("'.Log::LOGIN.'", "'.Log::LOGIN_AUTO.'") AND m1.user IN '.$in.')'.
					' OR (m1.action = "'.Log::LOGIN_EXTERNAL.'" AND m1.userAction IN '.$in.')'
				);
				break;

		}

		Log::model()
			->whereIp($search->get('ip'), if: $search->get('ip'))
			->where('m1.createdAt', '>=', $search->get('from'), if: $search->get('from'))
			->where('m1.createdAt', '<', new \Sql(Log::model()->format($search->get('to')).' + INTERVAL 1 DAY'), if: $search->get('to'));

	}

	/**
	 * La citation part d'un modèle neuf, jamais de `Log::model()` : le modèle est un singleton et la
	 * lecture consomme la requête, les deux se videraient l'une l'autre.
	 */
	protected static function getKnownAddressModel(): LogModel {

		return new LogModel()
			->where('s1.user = m1.user')
			->where('s1.ip = m1.ip')
			->whereAction('IN', [Log::LOGIN, Log::LOGIN_AUTO])
			->where('s1.createdAt >= m1.createdAt - INTERVAL '.self::KNOWN_ADDRESS_IN_DAYS.' DAY')
			->where('s1.createdAt < m1.createdAt');

	}

	/**
	 * Avant cette date, la table ne porte plus les 30 jours qui précèdent une ligne
	 * (`LogLib::clean()` ne garde que `UserSetting::KEEP_LOGS` jours) : une reconnexion y paraîtrait
	 * venir d'une adresse inconnue faute d'historique, pas faute d'avoir été vue.
	 */
	public static function getMarkableFrom(): string {

		return date('Y-m-d H:i:s', \Clock::shift('-'.(UserSetting::KEEP_LOGS - self::KNOWN_ADDRESS_IN_DAYS).' days'));

	}

	public static function acceptUnknownAddressMark(Log $eLog): bool {

		return (
			$eLog['action'] === Log::LOGIN_AUTO and
			$eLog['device'] === Log::WEB and
			$eLog['createdAt'] >= self::getMarkableFrom()
		);

	}

	/**
	 * Pose `unknownAddress` sur chaque ligne de la page : TRUE pour une reconnexion automatique
	 * depuis un ordinateur dont le compte n'a pas montré l'adresse dans les 30 jours qui précèdent.
	 * Une seule requête pour la page, sur l'index (user, createdAt), puis la comparaison en PHP :
	 * une sous-requête corrélée dans la sélection serait évaluée par le tri sur toutes les lignes
	 * de la recherche, pas sur les 50 de la page.
	 */
	public static function markUnknownAddresses(\Collection $cLog): void {

		$cLogCandidate = new \Collection();

		foreach($cLog as $eLog) {

			$eLog['unknownAddress'] = FALSE;

			if(self::acceptUnknownAddressMark($eLog)) {
				$cLogCandidate[] = $eLog;
			}

		}

		if($cLogCandidate->empty()) {
			return;
		}

		$from = min($cLogCandidate->getColumn('createdAt'));
		$to = max($cLogCandidate->getColumn('createdAt'));

		$cLogSeen = Log::model()
			->select(['user', 'ip', 'createdAt'])
			->whereUser('IN', $cLogCandidate->getColumnCollection('user')->getIds())
			->whereIp('IN', array_unique($cLogCandidate->getColumn('ip')))
			->whereAction('IN', [Log::LOGIN, Log::LOGIN_AUTO])
			->where('m1.createdAt', '>=', date('Y-m-d H:i:s', strtotime($from) - self::KNOWN_ADDRESS_IN_DAYS * 86400))
			->where('m1.createdAt', '<', $to)
			->getCollection();

		foreach($cLogCandidate as $eLog) {

			$since = date('Y-m-d H:i:s', strtotime($eLog['createdAt']) - self::KNOWN_ADDRESS_IN_DAYS * 86400);

			$eLog['unknownAddress'] = TRUE;

			foreach($cLogSeen as $eLogSeen) {

				if(
					$eLogSeen['user']['id'] === $eLog['user']['id'] and
					$eLogSeen['ip'] === $eLog['ip'] and
					$eLogSeen['createdAt'] >= $since and
					$eLogSeen['createdAt'] < $eLog['createdAt']
				) {
					$eLog['unknownAddress'] = FALSE;
					break;
				}

			}

		}

	}

}
?>
