<?php
namespace website;

class ContactLib extends ContactCrud {

	use \Notifiable;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'email', 'title', 'content'];
	}

	public static function checkThrottle(): bool {

		if(\user\ThrottleAiLib::acceptContact() === FALSE) {
			return Contact::fail('throttled');
		}

		return TRUE;

	}

	public static function create(Contact $e): void {

		$structureEmail = $e['structure']['legalEmail'];

		if($structureEmail === NULL) {
			throw new \Exception('Missing structure email');
		}

		parent::create($e);

		\structure\Structure::model()
			->select(\structure\StructureElement::getSelection())
			->get($e['structure']);

		$libSend = \main\MailLib::getInstance()
			->setStructure($e['structure'])
			->setFromName($e['structure']['name'])
			->setReplyTo($structureEmail)
			->setTo($e['email'])
			->setContent(...ContactUi::getUserEmail($e));

		// Le visiteur qui écrit ne devient pas un contact à qui la structure peut envoyer ses e-mails.
		// Les contacts vivent dans base/mail, qui règle lui-même son envoi.
		if(\structure\StructureSetting::hasMailPackage()) {
			self::notify('sendingToVisitor', $libSend);
		}

		$libSend->send();

		\main\MailLib::getInstance()
			->setTo($structureEmail)
			->setReplyTo($e['email'])
			->setContent(...ContactUi::getStructureEmail($e))
			->send();


	}

	public static function clean(): void {

		$until = date('Y-m-d 00:00:00', \Clock::shift(\Clock::date().' - '.WebsiteSetting::KEEP_CONTACT_IN_DAYS.' DAYS'));

		Contact::model()
			->whereCreatedAt('<', $until)
			->delete();

	}

}
?>
