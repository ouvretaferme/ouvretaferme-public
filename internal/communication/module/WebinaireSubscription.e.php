<?php
namespace communication;

class WebinaireSubscription extends WebinaireSubscriptionElement {

	// L'inscription se prend sans compte, là où la relire demande d'être celui qui l'a prise.
	public function canCreate(): bool {

		return TRUE;

	}

	public function canRead(): bool {

		$this->expects(['email']);

		$eUserOnline = \user\ConnectionLib::getOnline();

		return (
			$eUserOnline->notEmpty() and
			$this['email'] === $eUserOnline['email']
		);

	}

	public function acceptDelete(): bool {

		$eUserConnected = \user\ConnectionLib::getOnline();
		$date = CommunicationSetting::WEBINAIRE_DATES[$this['date']];

		return (
			$eUserConnected->notEmpty() and
			$this['email'] === $eUserConnected['email'] and
			\Clock::format('Y-m-d H:i') <= date('Y-m-d H:i', strtotime($date['date'].' '.str_replace('h', ':', $date['hour'])))
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('email')
			->addAfterCheck('duplicate', function(?string $email) {

				if($email === NULL) {
					return TRUE;
				}

				return (
					WebinaireSubscription::model()
						->whereEmail($email)
						->exists() === FALSE
				);

			});

		$l->property('date')
			->replaceCheck(function(?int $date) {

				if($date === NULL or array_key_exists($date, CommunicationSetting::WEBINAIRE_DATES) === FALSE) {
					return FALSE;
				}

				return (WebinaireSubscription::model()
					->whereDate($date)
					->count() ?? 0) < CommunicationSetting::WEBINAIRE_MAX_SUBSCRIPTIONS;

			});

	}
}
?>
