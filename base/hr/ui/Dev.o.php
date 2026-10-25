<?php
namespace hr;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'hr' => [
				'title' => s('Équipe et temps de travail'),
				'description' => s("La vie de votre équipe au fil du temps : les périodes de présence de chaque membre, ses absences et les heures qu'il a travaillées.")
			],
			'hr\Absence' => [
				'title' => s('Absences'),
				'plural' => s('absences'),
				'singular' => s('absence'),
				'gender' => 'f',
				'description' => s('Les absences des membres de votre équipe : congés, RTT, récupération ou autre, avec leur durée.')
			],
			'hr\Contract' => [
				'title' => s('Contrats'),
				'plural' => s('contrats'),
				'singular' => s('contrat'),
				'gender' => 'm',
				'description' => s('Le contrat d\'une période de présence : son type, l\'horaire hebdomadaire et les jours travaillés. Une période porte au plus un contrat. Les contrats se lisent depuis l\'API, ils se saisissent avec leur période depuis l\'onglet Personnes.')
			],
			'hr\Presence' => [
				'title' => s('Présences'),
				'plural' => s('présences'),
				'singular' => s('présence'),
				'gender' => 'f',
				'description' => s('Les périodes de présence de chaque membre dans votre structure. Une présence sans date de fin est toujours en cours, et deux périodes ne peuvent pas se chevaucher pour un même utilisateur.')
			],
			'hr\Teammate' => [
				'title' => s('Équipe'),
				'plural' => s('personnes de l\'équipe'),
				'singular' => s('personne de l\'équipe'),
				'gender' => 'f',
				'description' => s('Les personnes qui travaillent dans votre structure, avec ou sans accès à Ouvretaferme. Une présence ne se pose qu\'à une personne de l\'équipe.')
			],
			'hr\WorkingTime' => [
				'title' => s('Temps de travail'),
				'plural' => s('temps de travail'),
				'singular' => s('temps de travail'),
				'gender' => 'm',
				'description' => s('Le temps travaillé par un membre de l\'équipe sur une journée. Une seule ligne par utilisateur et par date : renvoyer un temps pour une journée déjà saisie l\'écrase. Le temps se pose et se corrige, il ne se supprime pas depuis l\'API, et ne peut pas être nul.')
			]
		];

	}

}
?>
