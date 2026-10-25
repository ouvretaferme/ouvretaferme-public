<?php
namespace structure;

class Survey extends SurveyElement {

	const UNIVERSES = ['production', 'commercialisation', 'accounting'];

	const MISSING_MAX = 10;
	const MISSING_LENGTH = 1000;

	public function canRead(): bool {

		return (
			$this['structure']->empty() or
			$this['structure']->canManage()
		);

	}

	public static function properties(\PropertyList $l): void {

		// Une ligne par besoin, dans l'ordre de priorité choisi à l'écran : les lignes laissées vides
		// ne sont pas des réponses.
		$l->property('missing')
			->replaceCast(function(mixed &$value): bool {

				if(is_array($value) === FALSE) {
					return FALSE;
				}

				foreach($value as $line) {
					if(is_string($line) === FALSE) {
						return FALSE;
					}
				}

				$value = array_values(array_filter(
					array_map(trim(...), $value),
					fn(string $line) => $line !== ''
				));

				return TRUE;

			})
			->addAfterCheck('empty', fn(array $lines): bool => $lines !== [])
			->addAfterCheck('max', fn(array $lines): bool => count($lines) <= Survey::MISSING_MAX)
			->addAfterCheck('length', function(array $lines): bool {

				foreach($lines as $line) {
					if(mb_strlen($line) > Survey::MISSING_LENGTH) {
						return FALSE;
					}
				}

				return TRUE;

			});

	}

}
?>
