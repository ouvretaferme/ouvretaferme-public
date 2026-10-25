<?php
namespace structure;

class ApiKeyUi {

	public function getManage(Structure $eStructure, \user\User $eUserOnline, \Collection $cAccessToken, \Collection $cAccessTokenRevoked, \Collection $cAccessTokenApplication, ?string $jwt, array $limitsReached, int $dayLimit, \Collection $cLog, int $nLog, int $logPage, float $nLogPage): string {

		\Asset::css('structure', 'apiKey.css');
		\Asset::js('structure', 'apiKeyAi.js');

		$h = $this->getWelcome($eStructure);

		$isStart = ($cAccessToken->empty() and $cAccessTokenRevoked->empty() and $cAccessTokenApplication->empty());

		$h .= '<div class="util-action mb-1">';
			$h .= '<h2>'.s("Clés API").'</h2>';

			// Le bloc de démarrage porte déjà ces deux gestes
			if($isStart === FALSE) {
				$h .= '<div>';
					$h .= '<a href="https://ouvretaferme.gitbook.io/ouvretaferme" class="btn btn-outline-primary">'.\Asset::icon('book').' '.s("Documentation").'</a> ';
					$h .= '<a href="/structure/apiKey:create?structure='.$eStructure['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Générer une clé").'</a>';
				$h .= '</div>';
			}

		$h .= '</div>';

		if($jwt !== NULL) {
			$h .= $this->getCreatedBanner($jwt);
		}

		if($isStart) {

			$start = '<p>'.s("{siteName} vous permet de créer des clés d'API personnelles.").'</p>';
			$start .= '<p>'.s("Avec ces clés, vous pouvez communiquer avec {siteName} sur les données de <b>{value}</b>.", encode($eStructure['name'])).'</p>';

			$buttons = '<div class="flex-buttons">';
				$buttons .= '<a href="/structure/apiKey:create?structure='.$eStructure['id'].'" class="btn btn-primary">'.s("Créer ma première clé d'API personnelle").'</a>';
				$buttons .= '<a href="https://ouvretaferme.gitbook.io/ouvretaferme" class="btn btn-outline-primary">'.s("Consulter la documentation de l'API").'</a>';
			$buttons .= '</div>';

			$h .= new \main\DesignUi()->getStart(
				\Asset::icon('plug'),
				s("Les intégrations de {siteName}"),
				$start,
				buttons: $buttons
			);

			return $h;

		}

		if($cAccessToken->empty()) {

			$h .= '<div class="util-empty mb-3">'.s("Aucune clé n'est active sur cette structure.").'</div>';

		} else {

			$h .= '<div class="util-cards util-cards-1 mb-3">';

				foreach($cAccessToken as $eAccessToken) {
					$h .= $this->getItem($eStructure, $eUserOnline, $eAccessToken);
				}

			$h .= '</div>';

		}

		$h .= $this->getRevoked($eUserOnline, $cAccessTokenRevoked);

		$h .= $this->getApplications($eStructure, $cAccessTokenApplication);

		$h .= $this->getQuotas($limitsReached, $dayLimit);

		$h .= $this->getLogs($eStructure, $cLog, $nLog, $logPage, $nLogPage);

		return $h;

	}

	private function getWelcome(Structure $eStructure): string {

		if($eStructure->acceptApiWrite()) {
			$write = s("Votre structure participe à la bêta de l'écriture : les accès en lecture et en écriture lui sont ouverts.");
		} else if($eStructure->acceptApiScope('learning:write')) {
			$write = s("Vos formations s'ouvrent en lecture et en écriture, les autres accès en lecture seule. Leur écriture viendra plus tard.");
		} else {
			$write = s("Seuls les accès en lecture sont disponibles et les accès en écriture seront disponibles ultérieurement.");
		}

		$text = '<p>'.s("Cette fonctionnalité est accessible en bêta.").'<br/>'.$write.'</p>';
		if($eStructure->isApiPaid()) {
			$text .= implode('', StructureUi::getObserverEntries('getApiPaidEntries', $eStructure));
		}

		return \main\DesignUi::getWelcome(
			s("Bienvenue sur l'API !").'<span class="util-stamp util-stamp-new ml-1">'.s("Nouveau").'</span>',
			$text,
			\Asset::svg('main', 'friends/abeille.svg'),
			class: 'mb-2'
		);

	}

	protected function getItem(Structure $eStructure, \user\User $eUserOnline, \api\AccessToken $eAccessToken): string {

		$isExpired = $eAccessToken['isExpired'] ?? FALSE;
		$expiresSoon = $eAccessToken['expiresSoon'] ?? FALSE;

		$created = s("Créée le {value}", \util\DateUi::numeric($eAccessToken['createdAt'], \util\DateUi::DATE));

		if($isExpired) {
			$accent = ' util-deck-accent-danger';
			$state = s("Expirée le {value}", \util\DateUi::numeric($eAccessToken['expiryDateTime'], \util\DateUi::DATE));
		} else if($expiresSoon) {
			$accent = ' util-deck-accent-warning';
			$state = \Asset::icon('exclamation-triangle-fill').' '.s("Expire le {value}", \util\DateUi::numeric($eAccessToken['expiryDateTime'], \util\DateUi::DATE));
		} else {
			$accent = '';
			$state = NULL;
		}

		$h = '<div class="util-deck'.$accent.'">';

			$h .= '<div class="util-deck-head">';

				$h .= '<div class="util-icon util-icon-round '.($isExpired ? 'util-icon-inactive' : 'util-icon-settings').'">'.\Asset::icon('key-fill').'</div>';

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.encode($eAccessToken['name'] ?? s("Clé sans nom")).'</div>';

					$h .= $this->getHolder($eUserOnline, $eAccessToken);

					$scopes = [];

					foreach($eAccessToken['accesses'] ?? [] as $domain => $right) {
						$scopes[] = $this->getScope($domain, $right);
					}

					if($scopes !== []) {
						$h .= '<p>'.implode(' ', $scopes).'</p>';
					}

				$h .= '</div>';

				$h .= '<div class="util-deck-aside">';
					$h .= $created;
					if($state === NULL) {
						$h .= '<br/>'.s("Expire le {value}", \util\DateUi::numeric($eAccessToken['expiryDateTime'], \util\DateUi::DATE));
					}
				$h .= '</div>';

			$h .= '</div>';

			if($state !== NULL) {
				$h .= '<div class="util-deck-band util-deck-band-state">'.$state.'</div>';
			}

			if($eAccessToken->assertRevoke()) {

				$h .= '<div class="util-deck-band util-deck-band-doors">';

					if($isExpired) {
						$h .= '<a href="/structure/apiKey:create?structure='.$eStructure['id'].'&renew='.$eAccessToken['id'].'" class="btn btn-light">'.\Asset::icon('arrow-clockwise').' '.s("Renouveler").'</a>';
					}

					$h .= '<a data-ajax="/structure/apiKey:doRevoke" post-structure="'.$eStructure['id'].'" post-id="'.$eAccessToken['id'].'" class="btn btn-danger-light" data-confirm="'.s("Voulez-vous vraiment révoquer cette clé ? Elle ne pourra plus être utilisée une fois révoquée.").'">'.\Asset::icon('trash').' '.s("Révoquer").'</a>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getHolder(\user\User $eUserOnline, \api\AccessToken $eAccessToken): string {

		if($eAccessToken['user']->is($eUserOnline)) {
			return '';
		}

		return '<p class="util-annotation">'.s("Créée par {value}", \user\UserUi::name($eAccessToken['user'])).'</p>';

	}

	protected function getRevoked(\user\User $eUserOnline, \Collection $cAccessTokenRevoked): string {

		if($cAccessTokenRevoked->empty()) {
			return '';
		}

		$count = $cAccessTokenRevoked->count();

		$h = '<div class="util-deck mb-3">';

			$h .= '<a class="util-deck-head" '.attr('onclick', 'this.closest(".util-deck").classList.toggle("util-deck-open")').'>';

				$h .= '<div class="util-icon util-icon-round util-icon-muted">'.\Asset::icon('key-fill').'</div>';

				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.p("{value} clé révoquée", "{value} clés révoquées", $count).'</div>';
				$h .= '</div>';

				$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';

			$h .= '</a>';

			$h .= '<div class="util-deck-fold">';

				foreach($cAccessTokenRevoked as $eAccessToken) {
					$h .= $this->getRevokedItem($eUserOnline, $eAccessToken);
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getApplications(Structure $eStructure, \Collection $cAccessTokenApplication): string {

		if($cAccessTokenApplication->empty()) {
			return '';
		}

		$h = '<h2>'.s("Applications autorisées").'</h2>';

		$h .= '<div class="util-cards util-cards-1 mb-3">';

			foreach($cAccessTokenApplication as $eAccessToken) {
				$h .= $this->getApplication($eStructure, $eAccessToken);
			}

		$h .= '</div>';

		return $h;

	}

	protected function getApplication(Structure $eStructure, \api\AccessToken $eAccessToken): string {

		$h = '<div class="util-deck">';

			$h .= '<div class="util-deck-head">';

				$h .= '<div class="util-icon util-icon-round util-icon-settings">'.\Asset::icon('plug-fill').'</div>';

				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.encode($eAccessToken['client']['name']).'</div>';
					$h .= '<p class="util-annotation">'.s("Autorisée par {value}", \user\UserUi::name($eAccessToken['user'])).'</p>';

					$scopes = [];

					foreach($eAccessToken['accesses'] as $domain => $right) {
						$scopes[] = $this->getScope($domain, $right);
					}

					if($scopes !== []) {
						$h .= '<p>'.implode(' ', $scopes).'</p>';
					}

				$h .= '</div>';

				$h .= '<div class="util-deck-aside">';

					if($eAccessToken['authorizedAt'] !== NULL) {
						$h .= s("Autorisée le {value}", \util\DateUi::numeric($eAccessToken['authorizedAt'], \util\DateUi::DATE)).'<br/>';
					}

					if($eAccessToken['lastCall'] === NULL) {
						$h .= s("Aucun appel ces {value} derniers jours", \api\ApiSetting::LOG_RETENTION_DAYS);
					} else {
						$h .= s("Dernier appel le {value}", \util\DateUi::numeric($eAccessToken['lastCall']));
					}

				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-deck-band util-deck-band-doors">';
				$h .= '<a data-ajax="/structure/apiKey:doRevokeApplication" post-structure="'.$eStructure['id'].'" post-client="'.$eAccessToken['client']['id'].'" post-user="'.$eAccessToken['user']['id'].'" class="btn btn-danger-light" data-confirm="'.s("Voulez-vous vraiment retirer à {value} l'accès à l'API ? L'application devra demander une nouvelle autorisation pour y accéder.", encode($eAccessToken['client']['name'])).'">'.\Asset::icon('trash').' '.s("Révoquer l'accès").'</a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getRevokedItem(\user\User $eUserOnline, \api\AccessToken $eAccessToken): string {

		$h = '<div class="util-deck-row util-deck-row-top">';

			$h .= '<div class="util-icon util-icon-round util-icon-muted">'.\Asset::icon('key-fill').'</div>';

			$h .= '<div class="util-deck-row-name">';

				$h .= encode($eAccessToken['name'] ?? s("Clé sans nom"));

				$h .= $this->getHolder($eUserOnline, $eAccessToken);

				$scopes = [];

				foreach($eAccessToken['accesses'] as $domain => $right) {
					$scopes[] = $this->getScope($domain, $right);
				}

				if($scopes !== []) {
					$h .= '<p>'.implode(' ', $scopes).'</p>';
				}

			$h .= '</div>';

			$h .= '<div class="util-deck-aside">';

				$h .= s("Créée le {value}", \util\DateUi::numeric($eAccessToken['createdAt'], \util\DateUi::DATE));
				$h .= '<br/>';

				if($eAccessToken['revokedAt'] === NULL) {
					$h .= s("Révoquée");
				} else {
					$h .= s("Révoquée le {value}", \util\DateUi::numeric($eAccessToken['revokedAt'], \util\DateUi::DATE));
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getScope(string $domain, ?string $right = NULL): string {

		[$class, $text, $icon] = $this->getScopeLabel($domain);

		$h = '<span class="badge-split color-'.$class.'">';
			$h .= '<span class="badge badge-solid bg-'.$class.'">'.\Asset::icon($icon).' '.$text.'</span>';
			if($right !== NULL) {
				$h .= '<span class="badge-split-detail">'.$this->getRightLabel($right).'</span>';
			}
		$h .= '</span>';

		return $h;

	}

	protected function getRightLabel(string $right): string {

		return match($right) {
			'read' => s("Lecture"),
			'write' => s("Lecture et écriture")
		};

	}

	protected function getQuotas(array $limitsReached, int $dayLimit): string {

		$h = '<div class="util-action mb-1">';
			$h .= '<h2>'.s("Utilisation de l'API").'</h2>';
			$h .= '<div class="apiKey-quota-updated">'.s("Relevé du {value}", \util\DateUi::numeric(\Clock::datetime())).'</div>';
		$h .= '</div>';

		$h .= '<div class="apiKey-quota-list util-cards util-cards-2">';

			$h .= $this->getQuota(
				s("Dernière minute"),
				s("Appels des 60 dernières secondes"),
				$limitsReached['minute'] ?? 0,
				\api\ApiSetting::RATE_LIMIT_MINUTE
			);

			$h .= $this->getQuota(
				s("Aujourd'hui"),
				s("Appels effectués depuis minuit"),
				$limitsReached['day'] ?? 0,
				$dayLimit
			);

		$h .= '</div>';

		$h .= '<p class="util-info">'.s("En complément de ces quotas, l'API n'accepte pas plus de {value} appels par seconde.", \api\ApiSetting::RATE_LIMIT_SECOND).'</p>';

		return $h;

	}

	protected function getQuota(string $title, string $legend, int $value, int $max): string {

		$class = $this->getRateClass($value, $max);
		$percent = min(100, (int)round($value / $max * 100));

		$h = '<div class="util-card apiKey-quota apiKey-quota-'.$class.'">';

			$h .= '<div class="util-card-title apiKey-quota-title">'.$title.'</div>';

			$h .= '<div class="apiKey-quota-value">';
				$h .= '<span class="apiKey-quota-count">'.$value.'</span>';
				$h .= '<span class="apiKey-quota-max">'.s("/ {value} appels", $max).'</span>';
			$h .= '</div>';

			$h .= '<div class="apiKey-quota-bar">';
				$h .= '<div class="apiKey-quota-bar-value" style="width: '.$percent.'%"></div>';
			$h .= '</div>';

			$h .= '<div class="apiKey-quota-legend">';

				$h .= match(TRUE) {
					$value >= $max => \Asset::icon('exclamation-triangle-fill').' '.s("Quota atteint, les appels sont refusés"),
					default => $legend
				};

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getRateClass(int $value, int $max): string {

		return match(TRUE) {
			$value >= $max => 'danger',
			$value >= $max * 0.8 => 'warning',
			default => 'primary'
		};

	}

	protected function getLogs(Structure $eStructure, \Collection $cLog, int $nLog, int $logPage, float $nLogPage): string {

		$h = '<div class="util-action apiKey-log-header mb-1">';
			$h .= '<h2>'.s("Journal des appels").'</h2>';
			$h .= '<div class="apiKey-log-retention">'.s("{value} appels conservés sur les {days} derniers jours. Le corps d'une réponse réussie se garde {hours} h, celui d'une erreur reste avec son appel.", ['value' => $nLog, 'days' => \api\ApiSetting::LOG_RETENTION_DAYS, 'hours' => \api\ApiSetting::LOG_BODY_RETENTION_HOURS]).'</div>';
		$h .= '</div>';

		if($nLog === 0) {
			return $h.'<div class="util-empty">'.s("Aucun appel n'a encore été effectué sur l'API de cette structure.").'</div>';
		}

		$h .= '<table class="tr-even apiKey-log">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Date").'</th>';
					$h .= '<th>'.s("Appel").'</th>';
					$h .= '<th class="hide-sm-down">'.s("Clé").'</th>';
					$h .= '<th class="hide-sm-down text-end">'.s("Durée").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($cLog as $eLog) {
				$h .= $this->getLog($eStructure, $eLog);
			}

		$h .= '</table>';

		$h .= \util\TextUi::pagination($logPage, $nLogPage);

		return $h;

	}

	protected function getLog(Structure $eStructure, \api\Log $eLog): string {

		// Une clé personnelle a un nom, le jeton d'une application tierce non. `token` peut aussi
		// arriver élément vide, et y lire une propriété lève un warning qui casse le JSON de toute
		// réponse ajax.
		if($eLog['token']->empty()) {
			$key = '<span class="color-muted">'.s("Application tierce").'</span>';
		} else if($eLog['token']['name'] === NULL) {
			$key = encode($eLog['token']['client']['name']);
		} else {
			$key = encode($eLog['token']['name']);
		}

		$time = match($eLog['status']) {
			\api\Log::CREATED => '',
			default => s("{value} s", round($eLog['time'], 3))
		};

		$h = '<tr>';

			$h .= '<td class="td-min-content apiKey-log-date">'.\util\DateUi::numeric($eLog['createdAt']).'</td>';

			$h .= '<td class="apiKey-log-request">';

				$h .= $this->getLogExpand($eStructure, $eLog);

				// La clé et la durée sont redonnées ici parce que leurs colonnes disparaissent sous 768px
				$h .= '<div class="apiKey-log-meta hide-md-up">'.$key.($time === '' ? '' : ' · '.$time).'</div>';

				$h .= '<div id="apiKey-log-response-'.$eLog['id'].'" class="apiKey-log-response hide"></div>';

			$h .= '</td>';

			$h .= '<td class="td-min-content apiKey-log-key hide-sm-down">'.$key.'</td>';

			$h .= '<td class="td-min-content text-end apiKey-log-time hide-sm-down">'.$time.'</td>';

		$h .= '</tr>';

		return $h;

	}

	protected function getLogExpand(Structure $eStructure, \api\Log $eLog): string {

		$line = $this->getLogStatus($eLog);
		$line .= '<span class="apiKey-log-method">'.strtoupper($eLog['requestMethod']).'</span>';
		$line .= '<span class="apiKey-log-url">'.encode($eLog['requestUrl']).'</span>';

		// Un appel interrompu n'a pas de réponse à montrer, et le corps d'une réponse réussie s'efface
		// avant sa ligne
		if($eLog['status'] === \api\Log::CREATED or $eLog['hasResponseBody'] === FALSE) {
			return '<div class="apiKey-log-line">'.$line.'</div>';
		}

		$url = '/structure/apiKey:logResponse?structure='.$eStructure['id'].'&id='.$eLog['id'];

		$h = '<a id="apiKey-log-expand-'.$eLog['id'].'" class="apiKey-log-line" data-action="apiKey-log-expand" data-log="'.$eLog['id'].'" data-url="'.$url.'">';
			$h .= $line;
			$h .= '<span class="apiKey-log-chevron">'.\Asset::icon('chevron-down').'</span>';
		$h .= '</a>';

		return $h;

	}

	public function getLogResponse(\api\Log $eLog): string {

		if($eLog['responseBody'] === []) {
			return '<div class="apiKey-log-response-empty">'.s("Cet appel n'a rien renvoyé.").'</div>';
		}

		// Le corps s'efface avant la ligne, et le cron peut passer entre le rendu de l'écran et le clic
		if($eLog['responseBody'] === NULL) {
			return '<div class="apiKey-log-response-empty">'.s("Le corps de cette réponse a été effacé. Il se garde {hours} h après l'appel, la ligne {days} jours.", ['hours' => \api\ApiSetting::LOG_BODY_RETENTION_HOURS, 'days' => \api\ApiSetting::LOG_RETENTION_DAYS]).'</div>';
		}

		return '<pre class="apiKey-log-response-body">'.encode(json_encode($eLog['responseBody'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'</pre>';

	}

	protected function getLogStatus(\api\Log $eLog): string {

		return match($eLog['status']) {
			\api\Log::SUCCESS => '<span class="badge badge-solid bg-success">'.$eLog['responseStatusCode'].'</span>',
			\api\Log::ERROR => '<span class="badge badge-solid bg-danger">'.$eLog['responseStatusCode'].'</span>',
			\api\Log::RATE_LIMIT => '<span class="badge badge-solid bg-warning">'.$eLog['responseStatusCode'].'</span>',
			\api\Log::CREATED => '<span class="badge badge-solid bg-muted">'.s("Interrompu").'</span>'
		};

	}

	protected function getCreatedBanner(string $jwt): string {

		$title = \Asset::icon('check-circle-fill').' '.s("Votre clé API a été générée");

		$content = '<p class="apiKey-created-warning">'.s("Copiez cette clé maintenant : elle ne sera plus jamais affichée par la suite.").'</p>';

		$content .= '<div class="apiKey-created-value">';
			$content .= '<div class="apiKey-created-token" id="api-key-created-value">'.encode($jwt).'</div>';
			$content .= '<span onclick="doCopy(this)" data-selector="#api-key-created-value" data-message="'.s("Clé copiée !").'" class="btn btn-primary apiKey-created-copy">';
				$content .= \Asset::icon('clipboard').' '.s("Copier");
			$content .= '</span>';
		$content .= '</div>';

		return \main\DesignUi::getAccentCard('success', 'api-key-created', $title, $content);

	}

	public function create(Structure $eStructure, \api\AccessToken $eAccessTokenRenew): \Panel {

		\Asset::css('structure', 'apiKey.css');

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/apiKey:doCreate', ['autocomplete' => 'off']);

			$h .= $form->hidden('structure', $eStructure['id']);

			if($eAccessTokenRenew->notEmpty()) {

				$title = \Asset::icon('arrow-clockwise').' '.s("Renouvellement d'une clé expirée");
				$content = '<p>'.s("Le nom et les accès de <b>{value}</b> sont repris ci-dessous. La clé expirée reste à révoquer une fois la nouvelle en place.", encode($eAccessTokenRenew['name'] ?? '')).'</p>';
				$h .= \main\DesignUi::getAccentCard('info', NULL, $title, $content);

			}

			$h .= $form->group(
				s("Nom de la clé").\util\FormUi::asterisk(),
				$form->text('name', $eAccessTokenRenew['name'] ?? NULL, ['placeholder' => s("Ex. : Synchronisation des ventes"), 'required' => TRUE]).
				$form->help(s("Ce nom vous permet de repérer la clé dans la liste et de savoir quelle application l'utilise."))
			);

			$h .= $form->group(
				s("Quels accès pour cette clé ?").\util\FormUi::asterisk(),
				$this->getScopeGroups($form, $eStructure->getApiScopes(), $eAccessTokenRenew['accesses'] ?? [])
			);

			$h .= $form->group(
				content: '<div class="util-info">'.
					'<p>'.s("La clé est valable un an, puis elle expire automatiquement.").'</p>'.
					'<p>'.s("Elle ne sera affichée qu'une seule fois, au moment de sa création.").'</p>'.
				'</div>'
			);

			$h .= $form->group(
				content: $form->submit(s("Générer la clé"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-api-key-create',
			title: s("Générer une clé API personnelle"),
			body: $h
		);

	}

	protected function getScopeGroups(\util\FormUi $form, array $availableScopes, array $accesses): string {

		// Seuls les domaines proposés à la structure ont leur ligne. Une ligne grisée annoncerait un
		// accès qu'elle n'a pas, et que bien souvent son type n'aura jamais : la production pour un
		// réseau, les formations pour une ferme.
		$domains = array_filter(
			['general', 'production', 'commercialisation', 'accounting', 'learning'],
			fn(string $domain) => in_array($domain.':read', $availableScopes, TRUE)
		);

		$h = '<div class="apiKey-domain-list">';

			foreach($domains as $domain) {

				$h .= '<div class="apiKey-domain">';

					$h .= '<div class="apiKey-domain-name">';
						$h .= $this->getScope($domain);
						$h .= '<span class="apiKey-domain-description">'.$this->getScopeDescription($domain).'</span>';
					$h .= '</div>';

					$selectedScope = isset($accesses[$domain]) ? $domain.':'.$accesses[$domain] : NULL;

					$h .= '<div class="apiKey-domain-access">'.$this->getScopeRadios($form, $domain, in_array($domain.':write', $availableScopes, TRUE), $selectedScope).'</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getScopeRadios(\util\FormUi $form, string $domain, bool $isWritable, ?string $selectedScope): string {

		$values = [
			$domain.':read' => s("Lecture")
		];

		if($isWritable) {
			$values[$domain.':write'] = s("Lecture et écriture");
		}

		return $form->radios('scopes['.$domain.']', $values, $selectedScope, ['columns' => 3, 'placeholder' => s("Aucun accès")]);

	}

	protected function getScopeDescription(string $domain): string {

		return match($domain) {
			'general' => s("Informations générales sur la structure et son équipe"),
			'production' => s("Planning de culture, séries, interventions et récoltes"),
			'commercialisation' => s("Clients, ventes, produits et boutiques"),
			'accounting' => s("Écritures, factures, immobilisations et TVA"),
			'learning' => s("Chapitres, leçons, dates et inscriptions")
		};

	}

	protected function getScopeLabel(string $scope): array {

		[$domain, ] = explode(':', $scope);

		return match($domain) {
			'general' => ['settings', s("Paramètres"), 'gear-fill'],
			'production' => ['production', s("Production"), 'leaf'],
			'commercialisation' => ['commercialisation', s("Vente"), 'basket'],
			'accounting' => ['accounting', s("Gestion"), 'piggy-bank'],
			'learning' => ['primary', s("Formations"), 'book'],
		};

	}

	/**
	 * Le choix de la structure à relier, sur les cartes de la sentinelle « x » : la question est la
	 * même, sur quelle structure ouvrir ce qui a été demandé.
	 */
	public function getAuthorizeChoice(\Collection $cStructure): string {

		if($cStructure->empty()) {
			return '<div class="util-empty">'.s("Vous n'administrez aucune structure qui puisse être reliée à une application.").'</div>';
		}

		return new StructureSentinelAiUi()->getChoice($cStructure);

	}

}
?>
