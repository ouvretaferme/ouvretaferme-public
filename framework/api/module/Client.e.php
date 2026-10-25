<?php
namespace api;

class Client extends ClientElement {

	public function canRead(): bool {
		return TRUE;
	}

	public function acceptDisable(): bool {
		return $this['isPersonal'] === FALSE and $this['status'] === Client::ACTIVE;
	}

	public function acceptEnable(): bool {
		return $this['isPersonal'] === FALSE and $this['status'] === Client::DISABLED;
	}

	public static function properties(\PropertyList $l): void {

		$l->property('redirectUris')
			->replaceCast(function(mixed &$value): bool {

				if(is_string($value) === FALSE) {
					return FALSE;
				}

				$value = array_values(array_unique(array_filter(
					array_map(trim(...), preg_split('/\R/', $value)),
					fn(string $redirectUri) => $redirectUri !== ''
				)));

				return TRUE;

			})
			->addAfterCheck('empty', fn(array $redirectUris): bool => $redirectUris !== [])
			// RFC 6749, 3.1.2 et RFC 8252, 7.3 : une adresse de retour est absolue et sans fragment, en
			// HTTPS, sauf la boucle locale du poste de développement, que league accepte quel que soit le port.
			->addAfterCheck('url', function(array $redirectUris): bool {

				foreach($redirectUris as $redirectUri) {

					if(filter_var($redirectUri, FILTER_VALIDATE_URL) === FALSE) {
						return FALSE;
					}

					$url = parse_url($redirectUri);

					if(isset($url['fragment'])) {
						return FALSE;
					}

					$isSecure = ($url['scheme'] === 'https');
					$isLoopback = ($url['scheme'] === 'http' and in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], TRUE));

					if($isSecure === FALSE and $isLoopback === FALSE) {
						return FALSE;
					}

				}

				return TRUE;

			});

		$l->property('cScope')
			->replaceCast(function(mixed &$value): bool {

				$names = is_array($value) ? $value : [];

				// Un accès en écriture donne aussi les routes en lecture, comme sur une clé personnelle
				foreach($names as $name) {
					if(is_string($name) and str_ends_with($name, ':write')) {
						$names[] = substr($name, 0, -strlen(':write')).':read';
					}
				}

				$names = array_values(array_unique($names));
				$value = ScopeLib::getByNames($names);

				return $value->count() === count($names);

			})
			->addAfterCheck('empty', fn(\Collection $cScope): bool => $cScope->notEmpty())
			->replaceSet(function(\Collection $cScope): void {
				$this['cScope'] = $cScope;
			});

	}

}
?>
