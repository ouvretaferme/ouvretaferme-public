<?php
namespace website;

class StructureObserverLib {

	public static function closeStructure(\structure\Structure $eStructure) {

		// Une AMAP fermée garde sa boutique et ce qui court (`shop\StructureObserverLib`) : son site garde
		// son domaine, où le widget dit qu'elle est fermée.
		if($eStructure->isAmap()) {
			return;
		}

		$eWebsite = WebsiteLib::getByStructure($eStructure);

		if($eWebsite->notEmpty() and $eWebsite['domain'] !== NULL) {

			$eWebsite['domain'] = NULL;

			WebsiteLib::update($eWebsite, ['domain']);

		}

	}

}
?>
