<?php
namespace work;

/**
 * Ce qu'une note montre d'une intervention à laquelle elle est rattachée (`NoteTargetLib`).
 */
class NoteTargetUi {

	public static function getGroup(): string {
		return s("Intervention");
	}

	public static function getLabel(Action $eAction): string {
		return $eAction['name'];
	}

	public static function getIcon(Action $eAction): ?string {
		return NULL;
	}

	public static function getDetail(Action $eAction): ?string {
		return NULL;
	}

}
?>
