<?php
/**
 * The queue runner: one pass every two seconds over the families of tasks it was given.
 *
 * The parameter is mandatory and accepts several actions separated by commas, served in turn in the
 * order they are written. Every name is checked before the first pass, so a typo on the second
 * action does not let the first one run for an hour before anyone notices.
 *
 * ⚠️ **A broken queue stops the whole line until the next hour.** The failing family is held back
 * so that the others still get their turn, but that buys the round and not the hour: the exception
 * raised at the end of the round kills the process, `Page` catching only `Action` around a
 * permanent pass, and the crontab restarts it on the hour.
 *
 * ⚠️ **A family with slow tasks takes its own crontab line.** `BATCH` bounds the volume, not the
 * duration: in `action=A,B`, B waits at every round for as many as a hundred tasks of A. The comma
 * list is for grouping fast families, never for sharing a line with a slow one.
 *
 * php framework/lime.php -a ouvretaferme -e prod queue/cron/run action=notification.shop-sale,notification.shop-date-end
 */
new Page()
	->cron('index', function($data) {

		// An absent parameter reads back as an empty string and not as NULL, so without this check
		// it would come out below as "the action list carries an empty item" — a list nobody wrote.
		// A forgotten parameter and a trailing comma are two mistakes with two fixes.
		if(get_exists('action') === FALSE) {
			throw new \Exception('queue/cron/run: action= parameter is required');
		}

		$actions = array_map('trim', explode(',', GET('action')));

		foreach($actions as $action) {

			if($action === '') {
				throw new \Exception('queue/cron/run: the action list carries an empty item');
			}

			if(\queue\QueueLib::getConsumer($action) === NULL) {
				throw new \Exception('queue/cron/run: no consumer declared for action '.$action);
			}

		}

		if(count(array_unique($actions)) !== count($actions)) {
			throw new \Exception('queue/cron/run: the action list carries a duplicate');
		}

		$failed = [];

		foreach($actions as $action) {

			// ⚠️ `run()` only catches what a consumer raises; a migration behind, a lost connection
			// or an SQL error comes out of `release()`, `getPending()` or `reserve()`, and `Page`
			// only catches `Action` around a permanent pass, so it would kill the process before
			// the families after this one had their turn.
			try {
				\queue\QueueLib::run($action);
			} catch(\Throwable $e) {
				$failed[$action] = $e;
			}

		}

		if($failed !== []) {

			$reasons = [];

			foreach($failed as $action => $e) {
				$reasons[] = $action.' ('.$e->getMessage().')';
			}

			// Chained rather than only quoted: the trace of the exception raised here stops at this
			// page and says nothing of where the queue actually broke, on a path that does not
			// replay.
			throw new \Exception('queue/cron/run: the queue of '.implode(', ', $reasons).' could not be served', previous: array_values($failed)[0]);

		}

	}, interval: 'permanent@2');
?>
