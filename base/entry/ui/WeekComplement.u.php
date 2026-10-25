<?php
namespace entry;

class WeekComplementUi {

	/**
	 * « Semaine 44 », d'un seul tenant, et l'année quand ce n'est pas celle de la semaine d'aujourd'hui :
	 * « Semaine 2 de 2027 ». C'est aussi ce que la lecture comprend (`WeekComplementLib::WORD`), l'année en moins.
	 */
	public static function getWeek(string $week): string {

		return (week_year($week) === (int)\Clock::format('o')) ?
			s("Semaine\u{00A0}{value}", week_number($week)) :
			s("Semaine\u{00A0}{number} de {year}", ['number' => week_number($week), 'year' => week_year($week)]);

	}

	public static function getInvalid(): string {
		return s("Ce numéro de semaine n'existe pas.");
	}

}
?>
