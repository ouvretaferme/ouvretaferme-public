<?php
namespace website;

class News extends NewsElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'isPublished' => new \Sql('NOW() >= publishedAt', 'bool')
		];

	}

	public function canRead(): bool {

		$this->expects(['status', 'structure']);

		return (
			$this['status'] === News::READY or
			$this->canWrite()
		);

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canCommunication();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('content')
			->replaceCast(function(mixed &$value): bool {
				News::model()->cast('content', $value);
				$value = new \editor\XmlLib()->fromHtml($value, ['acceptFigure' => TRUE]);
				return TRUE;
			});

	}

}
?>