<?php
namespace website;

class TemplateLib extends TemplateCrud {

	public static function getAutocreate(\structure\Structure $eStructure): \Collection {

		$eStructure->expects(['type']);

		return Template::model()
			->select(Template::getSelection())
			->whereAutocreate(TRUE)
			->or(
				fn() => $this->whereStructureType(NULL),
				fn() => $this->whereStructureType($eStructure['type'])
			)
			->sort('position')
			->getCollection();

	}

}
?>
