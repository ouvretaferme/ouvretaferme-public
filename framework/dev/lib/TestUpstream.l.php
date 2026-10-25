<?php
namespace dev;

/**
 * Ce que vaut chaque rouge d'une passe de sentinelles face à l'amont : « amont » quand la sentinelle
 * rougit aussi sur `origin/main`, « lot » quand elle n'y rougit pas, « non concluant » quand le rejeu
 * ne tranche pas. Le tri à la main a laissé partir des rouges de lot rangés « hors lot » à l'œil
 * (8e303b791), et la passe le fait désormais seule.
 *
 * Le rejeu est celui de `.claude/push/rouge.py amont`, qui le garde en cache par tête d'amont. Le
 * cadriciel n'en dépend pas : un script absent, qui échoue ou rend autre chose que l'objet attendu
 * laisse la passe telle qu'elle était, chaque rouge dit « non concluant ».
 */
class TestUpstreamLib {

	const SCRIPT = '.claude/push/rouge.py';

	/**
	 * Posée sur le rejeu, qui lance lui-même une passe de sentinelles : sans elle, chaque rouge de
	 * cette passe relancerait le rejeu.
	 */
	const REPLAY = 'OTF_TEST_REPLAY';

	const UPSTREAM = 'amont';
	const BATCH = 'lot';
	const INCONCLUSIVE = 'non concluant';

	public static function isReplay(): bool {
		return getenv(self::REPLAY) !== FALSE;
	}

	/**
	 * La tête d'amont rejouée, ou NULL quand elle ne s'est pas lue, et le verdict de chaque fichier
	 * avec sa raison.
	 *
	 * @param string[] $files Les fichiers de test rouges, depuis la racine du dépôt
	 * @return array{0: ?string, 1: array<string, array{string, string}>}
	 */
	public static function getVerdicts(array $files): array {

		$verdicts = array_fill_keys($files, [self::INCONCLUSIVE, 'amont non relu']);

		if(is_file(LIME_DIRECTORY.'/'.self::SCRIPT) === FALSE) {
			return [NULL, $verdicts];
		}

		$process = proc_open(['python3', self::SCRIPT, 'amont', ...$files, '--json'], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, LIME_DIRECTORY, getenv() + [self::REPLAY => '1']);

		if($process === FALSE) {
			return [NULL, $verdicts];
		}

		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		if(proc_close($process) !== 0) {
			return [NULL, $verdicts];
		}

		$result = json_decode($output, TRUE);

		if(is_array($result) === FALSE or is_string($result['amont'] ?? NULL) === FALSE or is_array($result['verdicts'] ?? NULL) === FALSE) {
			return [NULL, $verdicts];
		}

		foreach($files as $file) {

			$verdict = $result['verdicts'][$file] ?? NULL;

			if(in_array($verdict, [self::UPSTREAM, self::BATCH, self::INCONCLUSIVE], TRUE)) {
				$verdicts[$file] = [$verdict, is_string($result['raisons'][$file] ?? NULL) ? $result['raisons'][$file] : ''];
			}

		}

		return [$result['amont'], $verdicts];

	}

}
?>
