<?php
/**
 * Le mode d'emploi du suivi RH, servi sous `/doc/hr`. L'adresse est absolue pour que le nom du fichier,
 * qui porte la marque de son auteur, ne devienne pas une adresse publique.
 */
new Page()
	->get('/doc/hr', fn($data) => throw new ViewAction($data));
?>
