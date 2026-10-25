<?php
namespace website;

class ContactUi {

	public function getForm(Website $eWebsite, bool $readOnly = FALSE): string {

		$form = new \util\FormUi();

		$h = $form->openAjax(WebsiteUi::url($eWebsite, '/:doContact'), ['id' => 'website-contact']);

			$h .= $form->dynamicGroups(new Contact(), ['name', 'email', 'title', 'content']);

			if($readOnly) {
				$h .= $form->group(content: '<span class="btn btn-primary disabled">'.s("Envoyer le message").'</span>');
			} else {
				$h .= $form->group(content: $form->submit(s("Envoyer le message")));
			}

		$h .= $form->close();

		return $h;

	}

	public static function getUserEmail(Contact $eContact): array {

		$title = s("Votre message pour {structure} a bien été envoyé !", ['structure' => $eContact['structure']['name']]);

		$before = s("Bonjour,")."\n\n";
		$before .= s("Votre message a bien été transmis à {structure}, qui vous répondra directement à cette adresse.", ['structure' => $eContact['structure']['name']]);

		$text = \main\MailStructureUi::toText($before);

		$rows = \main\MailStructureUi::getText(nl2br($before));

		return \main\MailStructureUi::formatRows($eContact['structure'], $title, $text, $rows);

	}

	public static function getStructureEmail(Contact $eContact): array {

		$content = '<p>'.s("Bonjour,").'</p>';
		$content .= '<p>'.s("<b>{name}</b> a laissé un message sur votre site internet. Répondez à cet e-mail pour lui écrire directement.", ['name' => encode($eContact['name'])]).'</p>';

		return \main\MailSoftwareAiUi::format(
			s("Vous avez reçu un message de {value} sur votre site internet", $eContact['name']),
			$content,
			facts: [
				s("Adresse e-mail") => encode($eContact['email']),
				s("Objet") => encode($eContact['title']),
			],
			quote: nl2br(encode($eContact['content'])),
			footer: FALSE
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Contact::model()->describer($property, [
			'title' => s("Objet de votre message"),
			'name' => s("Votre nom"),
			'email' => s("Votre adresse e-mail"),
			'content' => s("Votre message"),
		]);

		switch($property) {

		}

		return $d;

	}

}
?>
