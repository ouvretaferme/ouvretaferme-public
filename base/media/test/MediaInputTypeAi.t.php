<?php
namespace media;

/**
 * Seuls les formats de `MediaSetting::IMAGES_INPUT_TYPE` atteignent Imagick (`MediaLib::putFile()`).
 *
 * Un SVG reconnu part chez librsvg, isolé (`SvgLib::rasterize()`). Celui que `SvgLib::isSvg()` ne
 * reconnaît pas, parce que sa balise racine vient après les octets examinés, partait chez Imagick,
 * qui devine le format sur le contenu et suit les ressources externes d'un SVG. Il est maintenant
 * refusé avant, faute d'être une image d'un format admis.
 *
 * Aucune écriture : un fichier refusé s'arrête avant tout enregistrement.
 *
 * Lancement : dev/test package=media file=MediaInputTypeAi
 */
class MediaInputTypeAiTest extends \Test {

	public function testAnUnrecognizedSvgIsRefusedBeforeImagick(): void {

		$file = tempnam(sys_get_temp_dir(), 'media');
		file_put_contents($file, '<?xml version="1.0"?><!-- '.str_repeat('a', SvgLib::SNIFF_LENGTH).' --><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>');

		$this->assertFalse(SvgLib::isSvg($file), 'le décor est un SVG que isSvg() ne reconnaît pas');

		$libMedia = new EditorLib();
		$hash = NULL;

		$this->assertFalse($libMedia->putFile($libMedia->buildElement(), $hash, $file, []), 'le SVG non reconnu est refusé');
		$this->assertEquals(NULL, $hash, 'aucun média n\'est créé');

		unlink($file);

	}

}
?>
