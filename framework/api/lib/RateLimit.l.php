<?php
namespace api;

class RateLimitLib extends RateLimitCrud {

	public static function getLimitsReached(int $reference): array {

		return RateLimit::model()
			->select([
				'day' => new \Sql('SUM(IF(timestamp >= UNIX_TIMESTAMP(CURRENT_DATE), calls, 0))', 'int'),
				'minute' => new \Sql('SUM(IF(timestamp > UNIX_TIMESTAMP(NOW()) - 60, calls, 0))', 'int'),
			])
			->whereReference($reference)
			->whereTimestamp('>', new \Sql('LEAST(UNIX_TIMESTAMP(CURRENT_DATE) - 1, UNIX_TIMESTAMP(NOW()) - 60)'))
			->get()
			->getArrayCopy();

	}

	public static function validate(int $reference): void {

		if(LIME_ENV === 'dev') {
			return;
		}

		$timestamp = RateLimit::model()->now('timestamp');

		$eRateLimit = new RateLimit([
			'reference' => $reference,
			'timestamp' => $timestamp
		]);

		self::validateReached($reference);

		try {

			RateLimit::model()->insert($eRateLimit);

		} catch(\DuplicateException) {

			if(
				RateLimit::model()
					->whereReference($reference)
					->whereTimestamp($timestamp)
					->whereCalls('<', ApiSetting::RATE_LIMIT_SECOND)
					->update([
						'calls' => new \Sql('calls + 1')
					]) === 0
			) {
				throw new \RateLimitApiException('Rate limit exceeded (max '.ApiSetting::RATE_LIMIT_SECOND.' par second)', 'rate_limit_second', ['limit' => ApiSetting::RATE_LIMIT_SECOND]);
			}

		}

	}

	public static function validateReached(int $reference): void {

		$limitsReached = self::getLimitsReached($reference);

		if($limitsReached['minute'] >= ApiSetting::RATE_LIMIT_MINUTE) {
			throw new \RateLimitApiException('Rate limit exceeded (max '.ApiSetting::RATE_LIMIT_MINUTE.' par minute)', 'rate_limit_minute', ['limit' => ApiSetting::RATE_LIMIT_MINUTE], 60);
		}

		$limitDay = ApiSetting::getDayLimit($reference);

		if($limitsReached['day'] >= $limitDay) {
			throw new \RateLimitApiException('Rate limit exceeded (max '.$limitDay.' par day, reset at midnight)', 'rate_limit_day', ['limit' => $limitDay], strtotime('tomorrow', \Clock::timestamp()) - \Clock::timestamp());
		}

	}

	public static function clean(): void {

		RateLimit::model()
			->where('timestamp < UNIX_TIMESTAMP(NOW() - INTERVAL 14 DAY)')
			->delete();

	}

}
?>
