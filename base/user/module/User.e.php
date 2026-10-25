<?php
namespace user;

class User extends UserElement {

	public function getName(): string {

		$this->expects(['firstName', 'lastName']);

		if($this['firstName'] === NULL) {
			return encode($this['lastName']);
		} else {
			return encode($this['firstName']).' '.encode($this['lastName']);
		}

	}

	/** Le prénom seul, pour s'adresser à quelqu'un ou le citer dans une phrase, et le nom à défaut, comme `getName()`. */
	public function getFirstName(): string {

		$this->expects(['firstName', 'lastName']);

		return encode($this['firstName'] ?? $this['lastName']);

	}

	public function isOnline(): bool {

		$eUserOnline = \user\ConnectionLib::getOnline();

		return (
			$eUserOnline->notEmpty() and
			$this->notEmpty() and
			$this['id'] === $eUserOnline['id']
		);
	}

	public function isAdmin(): bool {

		return (
			$this->exists() and
			in_array($this['id'], UserSetting::$admin)
		);

	}

	public function validateAdmin(): bool {

		if($this->isAdmin()) {
			AdminLogAiLib::record($this);
			return TRUE;
		}

		throw new \DisabledPage('admin');

	}

	public function isMember(): bool {

		$this->expects(['roleProducer', 'roleConsultant', 'roleTeacher']);

		return (
			$this->exists() and
			(
				$this['roleProducer'] or
				$this['roleConsultant'] or
				$this['roleTeacher']
			)
		);

	}

	public function isCustomer(): bool {
		return $this->isRole('roleCustomer');
	}

	public function isProducer(): bool {
		return $this->isRole('roleProducer');
	}

	public function isTeacher(): bool {
		return $this->isRole('roleTeacher');
	}

	public function isConsultant(): bool {
		return $this->isRole('roleConsultant');
	}

	protected function isRole(string $role): bool {

		$this->expects([$role]);

		return (
			$this->exists() and
			$this[$role]
		);

	}

	public function canRead(): bool {

		return (
			$this->isOnline() or
			\user\ConnectionLib::getOnline()->isAdmin()
		);

	}

	// Sans ce canCreate(), l'inscription retomberait sur canWrite(), donc sur canRead(), et
	// demanderait d'être déjà connecté.
	public function canCreate(): bool {
		return TRUE;
	}

	public function acceptCreateAmap(): bool {

		return (
			($this->isProducer() or $this->isCustomer()) and
			\structure\StructureLib::isAmapEnabled($this)
		);

	}

	public function acceptCreateShop(): bool {

		return (
			$this->isProducer()
		);

	}

	public function acceptCreateProducer(): bool {

		return (
			$this->isProducer() or
			$this->isConsultant()
		);

	}

	public function acceptCreateCommunityEducation(): bool {

		return (
			$this->isTeacher()
		);

	}

	public function acceptCreateCommunityProducer(): bool {

		return (
			$this->isProducer() or
			$this->isConsultant()
		);

	}

	public function acceptCreateCommunityNetwork(): bool {

		return (
			$this->isConsultant() or
			$this->isTeacher()
		);

	}

	public function active(): bool {
		return ($this['status'] === User::ACTIVE);
	}

	public function isVerified(): bool {

		$this->expects(['email', 'verified']);

		return (
			$this['email'] !== NULL and
			$this['verified'] === TRUE
		);

	}

	public function getDeliveryAddress(): ?string {

		if($this->hasDeliveryAddress() === FALSE) {
			return NULL;
		}

		$address = $this['deliveryStreet1']."\n";
		if($this['deliveryStreet2'] !== NULL) {
			$address .= $this['deliveryStreet2']."\n";
		}
		$address .= $this['deliveryPostcode'].' '.$this['deliveryCity'];

		return $address;

	}

	public function hasDeliveryAddress(): bool {
		return $this['deliveryCity'] !== NULL;
	}

	public function getInvoiceAddress(): ?string {

		if($this->hasInvoiceAddress() === FALSE) {
			return NULL;
		}

		$address = $this['invoiceStreet1']."\n";
		if($this['invoiceStreet2'] !== NULL) {
			$address .= $this['invoiceStreet2']."\n";
		}
		$address .= $this['invoicePostcode'].' '.$this['invoiceCity'];

		return $address;

	}

	public function hasInvoiceAddress(): bool {
		return $this['invoiceCity'] !== NULL;
	}

	public function copyDeliveryAddress(\Element $e, array &$properties = []): void {

		$e->merge([
			'deliveryStreet1' => $this['deliveryStreet1'],
			'deliveryStreet2' => $this['deliveryStreet2'],
			'deliveryPostcode' => $this['deliveryPostcode'],
			'deliveryCity' => $this['deliveryCity'],
			'deliveryCountry' => $this['deliveryCountry'],
		]);

		$properties = array_merge($properties, ['deliveryStreet1', 'deliveryStreet2', 'deliveryPostcode', 'deliveryCity', 'deliveryCountry']);

	}

	public function copyInvoiceAddress(\Element $e, array &$properties = []): void {

		$e->merge([
			'invoiceStreet1' => $this['invoiceStreet1'],
			'invoiceStreet2' => $this['invoiceStreet2'],
			'invoicePostcode' => $this['invoicePostcode'],
			'invoiceCity' => $this['invoiceCity'],
			'invoiceCountry' => $this['invoiceCountry'],
		]);

		$properties = array_merge($properties, ['invoiceStreet1', 'invoiceStreet2', 'invoicePostcode', 'invoiceCity', 'invoiceCountry']);

	}

	public function isPrivate(): bool {
		return ($this['visibility'] === User::PRIVATE);
	}

	public function isPublic(): bool {
		return ($this['visibility'] === User::PUBLIC);
	}

	public static function properties(\PropertyList $l): void {

		$l->property('email')
			->addAfterCast('lowercase', function(?string &$email) {
				if($email !== NULL) {
					$email = mb_strtolower($email);
				}
			})
			->addAfterCheck('empty', function(?string $email) {
				return ($email !== NULL);
			})
			->addAfterCheck('duplicate', function(string $email) {

				// User did not change his email address
				if(
					$this->offsetExists('email') and
					$this->offsetGet('email') === $email
				) {
					return TRUE;
				}

				// Checks that email is not already used
				return (User::model()
						->whereEmail($email)
						->exists() === FALSE);
			});

		$l->property('siret')
			->addAfterCast('format', fn(?string &$siret) => \structure\Structure::prepareSiret($this, $siret))
			->replaceCheck(fn(?string &$siret) => \structure\Structure::checkSiret($siret));

		$l->property('phone')
			->addAfterCheck('empty', function(?string $phone): bool {

				$this->expects(['visibility']);

				return (
					$this['visibility'] === User::PRIVATE or
					$phone !== NULL
				);

			});

		$l->property('deliveryCountry')
			->replaceCheck(function($eCountry): bool {

				return (
					$eCountry->notEmpty() and
					\user\Country::model()
						->select(\user\Country::getSelection())
						->get($eCountry)
				);

			});

		$l->property('invoiceCountry')
			->replaceCheck(function(&$eCountry): bool {

				return (
					$eCountry->notEmpty() and
					\user\Country::model()
						->select(\user\Country::getSelection())
						->get($eCountry)
				);

			});

		$l->property('firstName')
			->addAfterCast('required', function(?string $firstName): bool {

				$this->expects(['visibility']);

				return (
					$this['visibility'] == User::PRIVATE or
					$firstName !== NULL
				);

			});

		$l->property('legalName')
			->addAfterCast('type', function(?string &$legalName): void {

				$this->expects(['type']);

				if($this['type'] === User::PRIVATE) {
					$legalName = NULL;
				}

			})
			->addAfterCheck('required', function(?string &$legalName): bool {

				$this->expects(['type']);

				if($this['type'] === User::PRO) {
					return ($legalName !== NULL);
				} else {
					return TRUE;
				}

			});

		$l->property('invoiceAddress')
			->addAfterCheck('empty', fn() => \user\User::buildAddress('invoice', $this));

		$l->property('deliveryAddress')
			->addAfterCheck('empty', fn() => \user\User::buildAddress('delivery', $this));

		$l->property('deliveryAddressMandatory')
			->replaceCheck(function(): bool {

				return (
					$this['deliveryStreet1'] !== NULL and
					$this['deliveryPostcode'] !== NULL and
					$this['deliveryCity'] !== NULL
				);

			});

		$l->property('roleProducer')
			->replaceCheck(function(bool $status) {

				if(
					$status === FALSE and
					\structure\StructureLib::countByUser($this) > 0 and
					User::model()
						->whereRoleTeacher(FALSE)
						->whereRoleConsultant(FALSE)
						->exists($this)
				) {
					User::fail('role.check');
				}

			});

		$l->property('roleConsultant')
			->replaceCheck(function(bool $status) {

				if(
					$status === FALSE and
					\structure\StructureLib::countByUser($this) > 0 and
					User::model()
						->whereRoleTeacher(FALSE)
						->whereRoleProducer(FALSE)
						->exists($this)
				) {
					User::fail('role.check');
				}

			});

		$l->property('roleTeacher')
			->replaceCheck(function(bool $status) {

				if(
					$status === FALSE and
					\structure\StructureLib::countByUser($this) > 0 and
					User::model()
						->whereRoleProducer(FALSE)
						->whereRoleConsultant(FALSE)
						->exists($this)
				) {
					User::fail('role.check');
				}

			});

	}

	public function build(array $properties, array $input, \PropertyList $l = new \PropertyList()): void {

		$emailKey = array_search('email', $properties);

		if($emailKey !== FALSE) {
			$this->buildEmail($input);
			unset($properties[$emailKey]);
		}

		self::propertyAddress('delivery', $properties);
		self::propertyAddress('invoice', $properties);

		parent::build($properties, $input, $l);

	}

	/**
	 * Construit l'adresse seule, hors du build() courant qui l'a retirée de sa liste : l'adresse
	 * ouvre l'authentification de base, et son identifiant de connexion la suit.
	 */
	public function buildEmail(array $input): bool {

		$this->add([
			'auth' => new UserAuth()
		]);

		$fw = new \FailWatch;

		parent::build(['email'], $input);

		if($fw->ok()) {

			$this['auth']['login'] = $this['email'];
			return TRUE;

		} else {
			return FALSE;
		}

	}

	public static function propertyAddress(string $type, array &$properties): void {

		$address = count(array_intersect($properties, [$type.'Street1', $type.'Street2', $type.'Postcode', $type.'City']));

		if($address === 4) {
			$properties[] = $type.'Address';
		} else if($address > 0) {
			throw new \Exception('Invalid address build');
		}

	}

	public static function buildAddress(string $type, \Element $e, bool $acceptNullable = TRUE): bool {

		if($acceptNullable and $e[$type.'Street1'] === NULL and $e[$type.'Street2'] === NULL and $e[$type.'Postcode'] === NULL and $e[$type.'City'] === NULL) {
			return TRUE;
		}

		$fw = new \FailWatch();

		if($e[$type.'Street1'] === NULL) {
			$e->getModule()::fail($type.'Street1.check');
		}

		if($e[$type.'Postcode'] === NULL) {
			$e->getModule()::fail($type.'Postcode.check');
		}

		if($e[$type.'City'] === NULL) {
			$e->getModule()::fail($type.'City.check');
		}

		return $fw->ok();

	}
	
}
?>
