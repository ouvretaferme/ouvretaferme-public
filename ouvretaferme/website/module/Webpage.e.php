<?php
namespace website;

class Webpage extends WebpageElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'template' => ['fqn'],
		];

	}

	public function canRead(): bool {

		$this->expects(['status', 'structure']);

		return (
			$this['status'] === Webpage::ACTIVE or
			$this->canWrite()
		);

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canCommunication();

	}

	public function acceptDelete(): bool {

		$this->expects(['template' => ['fqn']]);

		return (
			$this['template']['fqn'] !== 'homepage' and
			$this['template']['fqn'] !== 'news'
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('url')
			->addAfterCast('lowercase', function(string &$url): bool {
				$url = mb_strtolower($url);
				return TRUE;
			})
			->addAfterCheck('format', function(string $url): bool {
				return $url !== NULL and preg_match('/^[a-z0-9\_-]*$/s', $url) > 0;
			});

		$l->property('content')
			->replaceCast(function(mixed &$value): bool {
				Webpage::model()->cast('content', $value);
				$value = new \editor\XmlLib()->fromHtml($value, ['acceptFigure' => TRUE]);
				return TRUE;
			});

	}

}
?>