<?php
new AdaptativeView('/doc/hr', function($data, DocTemplate $t) {

	$t->title = s("Le suivi RH");
	$t->subTitle = s("Les contrats, les absences, le temps de travail et la synthèse mensuelle de votre équipe.");

	$t->menuSelected = 'hr';

	echo '<div class="doc-intro">';
		echo '<p>'.s("Le suivi RH rassemble ce qu'il faut pour préparer la paie de vos salariés. Vous renseignez le contrat de chaque personne, vos salariés demandent leurs absences et saisissent leurs journées, et {siteName} calcule les heures supplémentaires et prépare la synthèse mensuelle.").'</p>';
	echo '</div>';

	echo '<h2>'.s("Activer le suivi RH").'</h2>';
	echo '<p>'.s("Le suivi RH s'active par l'interrupteur <u>RH</u> de la page <u>Fonctionnalités</u> des paramètres de votre structure, sous la production, qui doit être activée. Seul un administrateur de la structure peut l'activer.").'</p>';
	echo Asset::image('hr', 'doc/hr-feature.png');
	echo '<p>'.s("Une fois le suivi RH activé, chaque personne de l'équipe saisit ses journées de travail, même si vous ne suivez pas le temps passé sur les interventions. Si vous le désactivez, les données déjà saisies sont conservées et reviennent quand vous le réactivez.").'</p>';

	echo '<h2>'.s("Les périodes de présence et le contrat").'</h2>';
	echo '<p>'.s("Dans l'onglet <u>Personnes</u> de votre équipe, ajoutez chaque personne avec sa période de présence, du jour de son arrivée au jour de son départ. Laissez le départ vide s'il n'est pas connu. Une personne qui revient chaque saison a une nouvelle période de présence à chaque fois.").'</p>';
	echo '<p>'.s("Chaque période de présence peut porter un contrat, avec son type, le nombre d'heures par semaine et les jours travaillés. Si les jours changent d'une semaine à l'autre, cochez <u>Jours variables</u>. Les heures se comptent à la semaine, ou sur l'année pour un contrat annualisé.").'</p>';
	echo Asset::image('hr', 'doc/hr-presence.png');
	echo '<p>'.s("Une personne qui n'a pas besoin de se connecter s'ajoute par <u>Ajouter une personne sans compte</u>, en haut de l'onglet <u>Personnes</u>. Vous saisissez alors ses journées et ses absences à sa place.").'</p>';

	echo '<h2>'.s("Les absences").'</h2>';
	echo '<p>'.s("Chaque salarié demande ses absences depuis le planning, dans l'onglet <u>Absences</u>, à côté de l'onglet <u>Temps de travail</u>. Il choisit le motif, comme des congés payés, des RTT ou un arrêt maladie, puis les jours ou les heures d'absence et la durée à décompter. Une absence par jour peut commencer l'après-midi et finir le matin.").'</p>';
	echo '<p>'.s("Le même onglet présente ses absences de l'année avec leur état, et les absences de l'équipe dans le mois à venir.").'</p>';
	echo Asset::image('hr', 'doc/hr-absences-planning.png');
	echo '<p>'.s("Un administrateur reçoit par défaut une notification à chaque demande. Il valide ou refuse les demandes dans l'onglet <u>Absences</u> de l'équipe, qui affiche le nombre de demandes en attente. Le salarié peut modifier ou retirer sa demande tant qu'elle n'a pas reçu de réponse, et il voit la réponse dans son onglet <u>Absences</u>.").'</p>';
	echo Asset::image('hr', 'doc/hr-absences.png');
	echo '<p>'.s("Un administrateur peut aussi saisir directement une absence pour une personne de l'équipe, qui est alors validée. Les absences validées s'affichent au planning.").'</p>';
	echo Asset::image('hr', 'doc/hr-absence-form.png');

	echo '<h2>'.s("La journée de travail").'</h2>';
	echo '<p>'.s("Chaque personne saisit sa journée depuis l'onglet <u>Temps de travail</u> du planning, en durée ou, si vous l'avez choisi dans les paramètres de la production, avec ses horaires d'arrivée, de départ et de pause. Un administrateur peut saisir la journée de n'importe quelle personne de l'équipe.").'</p>';
	echo Asset::image('hr', 'doc/hr-time.png');

	echo '<h2>'.s("Les heures supplémentaires").'</h2>';
	echo '<p>'.s("Les heures supplémentaires se calculent sur le contrat de chaque personne et s'affichent dans l'onglet <u>Temps de travail</u> du planning, sur la semaine de l'équipe, puis dans la synthèse mensuelle. Sans contrat ou sans heures, aucune heure supplémentaire n'est calculée.").'</p>';
	echo '<ul>';
		echo '<li>'.s("Pour un contrat compté à la semaine, les heures travaillées au-delà de 35 heures sont des heures supplémentaires, et celles au-delà de 43 heures sont signalées à part.").'</li>';
		echo '<li>'.s("Pour un contrat de moins de 35 heures, les heures travaillées entre l'horaire du contrat et 35 heures sont des heures complémentaires.").'</li>';
		echo '<li>'.s("Un contrat annualisé se saisit en heures par an, 1 607 heures pour un temps plein. Ces heures se répartissent sur les jours du contrat de la période de référence, jours fériés et cinq semaines de congés payés retirés. Le solde fait la différence entre les heures travaillées et les heures attendues des jours saisis ou couverts par une absence, depuis le début de la période ou du contrat. Les jours non saisis restent hors du solde et sont signalés en orange.").'</li>';
		echo '<li>'.s("La période de référence dure douze mois et commence le premier jour du mois que vous choisissez avec le bouton {icon} de la synthèse mensuelle.", ['icon' => '<a class="btn btn-color-primary btn-readonly btn-xs btn-only-icon">'.Asset::icon('gear-fill').'</a>']).'</li>';
	echo '</ul>';
	echo '<p>'.s("{siteName} compte les heures, mais ne calcule pas leur majoration, qui dépend de votre convention collective.").'</p>';

	echo '<h2>'.s("La synthèse mensuelle").'</h2>';
	echo '<p>'.s("L'onglet <u>Synthèse mensuelle</u> de votre équipe présente pour chaque personne les jours travaillés, les heures, les heures supplémentaires ou le solde d'un contrat annualisé, les jours fériés travaillés et les absences par motif. Vous pouvez ouvrir le détail jour par jour d'une personne.").'</p>';
	echo Asset::image('hr', 'doc/hr-recap.png');
	echo '<p>'.s("Un jour passé où le contrat prévoit de travailler, sans journée saisie, sans absence et qui n'est pas férié, est signalé en orange dans le détail de la personne, et la synthèse en donne le nombre à côté de son nom. Les jours d'un contrat aux jours variables ne sont pas signalés.").'</p>';
	echo Asset::image('hr', 'doc/hr-recap-user.png');
	echo '<p>'.s("La synthèse mensuelle s'exporte au format CSV, pour le transmettre à la personne qui établit la paie ou l'ouvrir dans un tableur. Une semaine à cheval sur deux mois compte ses heures supplémentaires dans le mois de son dernier jour.").'</p>';
	echo '<p>'.s("Si votre paie ne s'arrête pas à la fin du mois, vous pouvez régler le premier jour de la période mensuelle avec le bouton {icon} de la synthèse mensuelle. La synthèse et son export suivent alors cette période, par exemple du 26 d'un mois au 25 du mois suivant.", ['icon' => '<a class="btn btn-color-primary btn-readonly btn-xs btn-only-icon">'.Asset::icon('gear-fill').'</a>']).'</p>';

});
?>
