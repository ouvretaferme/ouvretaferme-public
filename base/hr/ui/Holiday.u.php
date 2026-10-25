<?php
namespace hr;

class HolidayUi {

	/**
	 * Ce que le temps de travail dit d'un jour férié (`HolidayLib`), comme `AbsenceUi::getAbsent()` d'une absence.
	 */
	public static function getHoliday(): string {
		return s("Férié");
	}

}
?>
