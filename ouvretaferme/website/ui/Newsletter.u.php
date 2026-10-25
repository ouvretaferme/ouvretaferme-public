<?php
namespace website;

class NewsletterUi {

	public function getForm(Website $eWebsite, bool $readOnly = FALSE): string {

		$form = new \util\FormUi();

		$h = $form->openAjax(WebsiteUi::url($eWebsite, '/:doNewsletter'), ['id' => 'website-newsletter']);

			$h .= $form->dynamicGroups(new Contact(), ['email']);

			if($readOnly) {
				$h .= $form->group(content: '<span class="btn btn-primary disabled">'.s("Valider l'inscription").'</span>');
			} else {
				$h .= $form->group(content: $form->submit(s("Valider l'inscription"), ['data-waiter' => s("Inscription en cours...")]));
			}

		$h .= $form->close();

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Contact::model()->describer($property, [
			'email' => s("Votre adresse e-mail"),
		]);

		switch($property) {

		}

		return $d;

	}

}
?>
