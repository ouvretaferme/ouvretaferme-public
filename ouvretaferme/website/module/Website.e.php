<?php
namespace website;

class Website extends WebsiteElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			// `status` pour le widget d'adhésion, qui se tait sur une AMAP fermée (`amap\WidgetUi::getMembership()`).
			'structure' => ['legalEmail', 'type', 'status'],
			'customDesign' => Design::getSelection(),
		];

	}

	public function canRead(): bool {

		$this->expects(['status', 'structure']);
		return ($this['status'] === Website::ACTIVE or $this->canWrite());

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canCommunication();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('internalDomain')
			->addAfterCast('lowercase', function(string &$domain): bool {
				$domain = mb_strtolower($domain);
				return TRUE;
			})
			->addAfterCheck('format', function(string $domain): bool {
				return preg_match('/^[a-z0-9\_-]+$/s', $domain) > 0;
			});

		$l->property('domain')
			->addAfterCheck('format', function(?string $domain): bool {

				return (
					$domain === NULL or (
						preg_match('/^[a-z0-9\-\.]+$/s', $domain) > 0 and
						str_starts_with($domain, '.') === FALSE and
						str_ends_with($domain, '.') === FALSE and
						str_contains($domain, '..') === FALSE and
						str_contains($domain, '.') === TRUE
					)
				);
			})
			->addAfterCheck('prefix', function(?string $domain): bool {

				return (
					$domain === NULL or
					preg_match('/^[a-z0-9\-]+\.([a-z0-9\-]+\.)+[a-z0-9]+$/s', $domain) > 0
				);
			});

		$l->property('footer')
			->replaceCast(function(mixed &$value): bool {
				Website::model()->cast('footer', $value);
				$value = new \editor\XmlLib()->fromHtml($value, ['acceptFigure' => TRUE]);
				return TRUE;
			});

		$l->property('customFont')
			->replaceCheck(function(string $customFont): bool {
				return \main\CustomizeUi::isCustomFont($customFont, \main\CustomizeUi::CUSTOM_FONTS);
			});

		$l->property('customTitleFont')
			->replaceCheck(function(string $customFont): bool {
				return \main\CustomizeUi::isCustomFont($customFont, \main\CustomizeUi::CUSTOM_TITLE_FONTS);
			});

	}

}
?>
