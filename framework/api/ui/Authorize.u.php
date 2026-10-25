<?php
namespace api;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;

class AuthorizeUi {

	public function consent(AuthorizationRequest $authorizationRequest, int $reference, string $referenceName): string {

		$eClient = $authorizationRequest->getClient();

		$scopeNames = array_map(fn($scope) => $scope->getIdentifier(), $authorizationRequest->getScopes());

		$form = new \util\FormUi();

		\Asset::css('api', 'authorizeAi.css');

		$h = '<div class="api-authorize">';

			$h .= \main\DesignUi::getWeigh(
				'info',
				'plug-fill',
				s("Autoriser l'application {client} à accéder aux données de {reference}", [
					'client' => '<b>'.encode($eClient->getName()).'</b>',
					'reference' => '<b>'.encode($referenceName).'</b>',
				]),
				s("Vous pouvez révoquer cet accès à tout moment dans les paramètres d'intégration de votre structure."),
				array_map(fn($scopeName) => new OauthUi()->getConsequence($scopeName, $eClient->getName()), $scopeNames),
				[s("Aucune autre donnée de {reference} ne pourra être consultée par l'application {client}.", [
					'reference' => '<b>'.encode($referenceName).'</b>',
					'client' => '<b>'.encode($eClient->getName()).'</b>',
				])],
				TRUE
			);

			$queryString = http_build_query([
				'client_id' => $eClient->getIdentifier(),
				'redirect_uri' => $authorizationRequest->getRedirectUri() ?? '',
				'response_type' => 'code',
				'scope' => implode(' ', $scopeNames),
				'state' => $authorizationRequest->getState() ?? '',
				'code_challenge' => $authorizationRequest->getCodeChallenge() ?? '',
				'code_challenge_method' => $authorizationRequest->getCodeChallengeMethod() ?? '',
				'structure' => $reference,
			]);

			$h .= $form->openUrl('/api/oauth/authorize?'.$queryString);

				$h .= '<div class="api-authorize-buttons flex-buttons">';
					$h .= $form->submit(s("Autoriser"), ['name' => 'approved', 'value' => '1', 'class' => 'btn btn-lg btn-primary']);
					$h .= $form->submit(s("Refuser"), ['name' => 'approved', 'value' => '0', 'class' => 'btn btn-lg btn-outline-primary']);
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

}
