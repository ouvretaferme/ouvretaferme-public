// La photo d'un entretien fait (`toolbox\\MaintenanceDone.photo`), un type sans élément.
class MediaMaintenanceDone extends MediaHash {

	type = 'maintenance-done';

}

Media.classes['maintenance-done'] = MediaMaintenanceDone;
