<?php
namespace dev;

/**
 * \api\RateLimitLib::validateReached() est appelée avant que l'appel en cours soit enregistré : le
 * compte qu'elle lit est celui des appels déjà passés, donc atteindre le plafond suffit à refuser.
 * Comparé avec `>`, il laissait passer un appel de plus que le plafond annoncé, 31 par minute et
 * 501 par jour. La minute était aussi calée sur l'horloge, ce qui laissait 62 appels en quelques
 * secondes à cheval sur deux minutes ; elle glisse désormais sur les 60 dernières secondes, y compris
 * au passage de minuit, où le jour, lui, repart de zéro comme l'annonce son message.
 *
 * La transaction couvre le fichier entier : chaque cas prend sa propre référence, qu'aucune structure
 * ne porte.
 *
 * Lancement : dev/test package=dev file=ApiRateLimit
 */
class ApiRateLimitTest extends TransactionTest {

	const REFERENCE_MINUTE = 999100;
	const REFERENCE_SLIDING = 999101;
	const REFERENCE_MIDNIGHT = 999102;
	const REFERENCE_DAY = 999103;

	protected function getTransactionModel(): \ModuleModel {
		return new \api\RateLimitModel();
	}

	public function testMinuteLimitIsTheAnnouncedOne(): void {

		$this->setClock('2026-06-15 10:00:30');

		$this->seed(self::REFERENCE_MINUTE, '2026-06-15 10:00:10', \api\ApiSetting::RATE_LIMIT_MINUTE - 1);

		$this->assertAccepted(self::REFERENCE_MINUTE, 'le trentième appel de la minute');

		$this->seed(self::REFERENCE_MINUTE, '2026-06-15 10:00:20', 1);

		$this->assertRefused(self::REFERENCE_MINUTE, 'rate_limit_minute', 'le trente et unième appel de la minute');

	}

	/** Les appels de la fin de la minute précédente comptent encore, et sortent du compte 60 secondes plus tard. */
	public function testMinuteWindowSlides(): void {

		$this->seed(self::REFERENCE_SLIDING, '2026-06-15 10:00:50', \api\ApiSetting::RATE_LIMIT_MINUTE);

		$this->setClock('2026-06-15 10:01:05');
		$this->assertRefused(self::REFERENCE_SLIDING, 'rate_limit_minute', 'quinze secondes après, dans la minute suivante');

		$this->setClock('2026-06-15 10:01:49');
		$this->assertRefused(self::REFERENCE_SLIDING, 'rate_limit_minute', 'cinquante-neuf secondes après');

		$this->setClock('2026-06-15 10:01:50');
		$this->assertAccepted(self::REFERENCE_SLIDING, 'soixante secondes après');

	}

	/** Au passage de minuit, la minute garde les appels de la veille, et le jour repart de zéro. */
	public function testMinuteWindowCrossesMidnight(): void {

		$this->seed(self::REFERENCE_MIDNIGHT, '2026-06-15 23:59:50', \api\ApiSetting::RATE_LIMIT_MINUTE);

		$this->setClock('2026-06-16 00:00:10');

		$this->assertRefused(self::REFERENCE_MIDNIGHT, 'rate_limit_minute', 'vingt secondes après minuit');
		$this->assertEquals(0, \api\RateLimitLib::getLimitsReached(self::REFERENCE_MIDNIGHT)['day'], 'le jour repart de zéro');

	}

	public function testDayLimitIsTheAnnouncedOne(): void {

		$this->setClock('2026-06-15 18:00:00');

		$limit = \api\ApiSetting::getDayLimit(self::REFERENCE_DAY);

		$this->seed(self::REFERENCE_DAY, '2026-06-15 08:00:00', $limit - 1);

		$this->assertAccepted(self::REFERENCE_DAY, 'le dernier appel du jour');

		$this->seed(self::REFERENCE_DAY, '2026-06-15 09:00:00', 1);

		$this->assertRefused(self::REFERENCE_DAY, 'rate_limit_day', 'un appel au-delà du plafond du jour');

	}

	private function seed(int $reference, string $datetime, int $calls): void {

		\api\RateLimitLib::create(new \api\RateLimit([
			'reference' => $reference,
			'timestamp' => strtotime($datetime),
			'calls' => $calls
		]));

	}

	private function assertAccepted(int $reference, string $message): void {

		try {
			\api\RateLimitLib::validateReached($reference);
			$this->assertTrue(TRUE, $message);
		} catch(\RateLimitApiException $e) {
			$this->assertFail($message.' : refusé en '.$e->getCode());
		}

	}

	private function assertRefused(int $reference, string $code, string $message): void {

		try {
			\api\RateLimitLib::validateReached($reference);
			$this->assertFail($message.' : accepté');
		} catch(\RateLimitApiException $e) {
			$this->assertEquals($code, $e->getCode(), $message);
			$this->assertEquals(429, $e->getStatusCode(), $message);
		}

	}

}
?>
