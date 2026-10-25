/**
 * Un contour fermé qu'on trace sur une carte : des sommets posés un à un, que le premier referme. Une parcelle, un jardin ou un abri
 * peut se tracer plutôt en rectangle (`drawingRectangle`, `getRectangle()`), et la paire « Forme libre » / « Rectangle » de la
 * rangée dit laquelle des deux on trace (`drawingFree`). Une parcelle, qu'on la crée ou qu'on la retrace, se reprend aussi des
 * parcelles déclarées à la PAC (`drawingPac`, `getPac()`, MapDrawingPac).
 *
 * C'est une forme de MapDrawing, qui ne lit d'elle que ce contrat : `points`, `closed`, `minimum`, ce qu'elle
 * prend du lien qui ouvre le geste par `begin()`, les gestes `add()`, `move()`, `remove()`, `close()`, `tool()`
 * et, pour une forme qui rend des poignées de milieu d'arête, `insert()`, pour une forme qui se tire d'un bloc,
 * `moveTo()`, pour une forme qui s'aimante aux côtés voisins, `getOutline()`, et ce qu'elle rend à l'écran par
 * `getGeometry()`, `getModes()`, `getHandles(mode)`, `getTools()`, `getPivot()`, `getSummary()` et `getBody()`.
 */
class MapDrawingPolygon {

	/* L'écart à l'angle droit d'un rectangle, comme MapSetting::RECTANGLE_SKEW */
	static RECTANGLE_SKEW = 0.01;

	/* L'écart relatif sous lequel deux rectangles englobants ont la même aire, comme MapSetting::DIMENSIONS_TIE */
	static DIMENSIONS_TIE = 0.000001;

	minimum = 3;

	points = [];
	closed = false;

	/* Les sommets qu'il avait à l'ouverture du geste */
	before = [];

	constructor(coordinates) {

		if(coordinates === null) {
			return;
		}

		this.points = MapDrawingPolygon.getVertices(coordinates);

		this.closed = true;
		this.before = this.points.map(point => [...point]);

	}

	/* La base referme l'anneau, et des contours anciens le sont deux fois : un point qui répète celui d'avant, ou le premier en fin
	   d'anneau, n'est pas un sommet (MapLib::getVertices()) */
	static getVertices(coordinates) {

		const same = (a, b) => (a[0] === b[0] && a[1] === b[1]);

		const points = coordinates.filter((point, index) => index === 0 || same(point, coordinates[index - 1]) === false);

		if(points.length > 1 && same(points[0], points[points.length - 1])) {
			points.pop();
		}

		return points;

	}

	/* Le contour qui reçoit celui qu'on trace, celui de sa parcelle pour un jardin, et rien pour une parcelle */
	contour = null;
	free = false;
	rectangle = false;
	pac = false;

	begin(dataset) {
		this.contour = (dataset.drawingContour === undefined) ? null : new MapDrawingPolygon(JSON.parse(dataset.drawingContour)).points;
		this.free = (dataset.drawingFree !== undefined);
		this.rectangle = (dataset.drawingRectangle !== undefined);
		this.pac = (dataset.drawingPac !== undefined);
	}

	add(point) {
		this.points.push(point);
	}

	move(index, point) {
		this.points[index] = point;
	}

	insert(index, point) {
		this.points.splice(index, 0, point);
	}

	remove(index) {
		this.points.splice(index, 1);
	}

	close() {
		this.closed = true;
	}

	/* Un outil qui agit d'un clic le fait ici et ne rend rien. Un outil qui se tire rend la fonction à rappeler
	   à chaque mouvement du pointeur, qui rend à son tour ce que la cote en dit, et ne change rien avant elle. */
	tool(name, from, pivot) {

		switch(name) {

			case 'remove' :
				this.points = [];
				this.closed = false;
				return null;

			case 'rotate' : {

				const points = this.points;
				const center = this.getPivot();
				const plane = this.getPlane();
				const bearing = (point) => Math.atan2(point.x - pivot.x, pivot.y - point.y) * 180 / Math.PI;

				return (to) => {

					const angle = Math.round(bearing(to) - bearing(from));

					this.points = MapDrawingCopy.getRotated(points, plane, center, angle);

					return ((angle % 360 + 360) % 360) +'°';

				};

			}

		}

	}

	/* Le point autour duquel tourne la forme, et où se pose la cote d'un outil qu'on tire */
	getPivot() {
		return turf.centroid(turf.polygon([this.getRing()])).geometry.coordinates;
	}

	getRing() {
		return [...this.points, this.points[0]];
	}

	getGeometry() {

		if(this.points.length < 2) {
			return null;
		}

		if(this.closed) {
			return {type: 'Polygon', coordinates: [this.getRing()]};
		} else {
			return {type: 'LineString', coordinates: this.points};
		}

	}

	/* Un contour se trace point par point avant de se modifier, de se déplacer ou de tourner */
	getModes() {
		return this.closed ? ['edit', 'move', 'rotate'] : ['edit'];
	}

	/* Une poignée par sommet, et une par milieu de côté une fois le contour refermé. Déplacé ou tourné, il ne se prend que
	   d'un bloc : ses poignées laisseraient croire qu'un point se tire */
	getHandles(mode) {

		if(mode !== 'edit') {
			return [];
		}

		const handles = this.points.map((point, index) => ({type: 'point', index: index, at: point}));

		if(this.closed) {

			this.points.forEach((point, index) => {

				const next = this.points[(index + 1) % this.points.length];

				handles.push({type: 'middle', index: index + 1, at: [(point[0] + next[0]) / 2, (point[1] + next[1]) / 2]});

			});

		}

		return handles;

	}

	/* Les formes que le lien propose restent dans la rangée quel que soit l'état du contour, les outils qui le règlent
	   n'y entrent qu'une fois refermé */
	getTools() {

		const shapes = MapDrawingPolygon.getShapes(this);

		return this.closed ? [...shapes, 'rotate', 'remove'] : shapes;

	}

	static getShapes(shape) {
		return [...(shape.free ? ['free'] : []), ...(shape.rectangle ? ['rectangle'] : []), ...(shape.pac ? ['pac'] : [])];
	}

	/* Le rectangle se trace à neuf, d'un glisser : un contour ne se redresse pas sans le geste de l'utilisateur. Refermé, il lui est
	   confié jusqu'à ce glisser, pour que revenir en « Forme libre » le rende intact ; à moitié posé, il n'a rien à rendre */
	getRectangle() {

		const rectangle = new MapDrawingRectangle(null, this.closed ? this.points : null);
		rectangle.before = this.before;

		return rectangle;

	}

	/* Le même contour, que les parcelles PAC agrandissent ou réduisent : à moitié posé, il n'a rien à leur ajouter */
	getPac() {

		const pac = new MapDrawingPac(this.closed ? this.getRing() : null);
		pac.before = this.before;

		return pac;

	}

	/* Le contour tiré d'un bloc : son centre vient au point donné */
	moveTo(point) {

		const pivot = this.getPivot();

		this.points = this.points.map(([longitude, latitude]) => [longitude + point[0] - pivot[0], latitude + point[1] - pivot[1]]);

	}

	/* Un contour refermé s'aimante par tous ses côtés */
	getOutline() {
		return this.closed ? this.points : null;
	}

	getLabels() {
		return [];
	}

	isReady() {
		return (this.points.length >= this.minimum);
	}

	getArea() {
		return (this.points.length < this.minimum) ? null : Math.round(turf.area(turf.polygon([this.getRing()])));
	}

	/* Les dimensions d'un contour d'abri, du même calcul que MapLib::getDimensions() côté serveur, qui les enregistre : un rectangle
	   se mesure sur deux côtés consécutifs, un autre contour sur le plus petit rectangle qui l'englobe */
	getDimensions() {

		const points = MapDrawingPolygon.getVertices(this.points);

		const longitudes = points.map(point => point[0]);
		const latitudes = points.map(point => point[1]);
		const origin = [(Math.min(...longitudes) + Math.max(...longitudes)) / 2, (Math.min(...latitudes) + Math.max(...latitudes)) / 2];

		const plane = points.map(point => MapDrawingRectangle.toPlane(origin, point));

		const sides = MapDrawingPolygon.isRectangle(plane) ?
			[turf.distance(points[0], points[1], {units: 'meters'}), turf.distance(points[1], points[2], {units: 'meters'})] :
			MapDrawingPolygon.getBoundingSides(plane);

		const rounded = sides.map(side => Math.round(side * 10) / 10);

		return [Math.max(...rounded), Math.min(...rounded)];

	}

	static isRectangle(plane) {

		if(plane.length !== 4) {
			return false;
		}

		return plane.every((point, index) => {

			const next = plane[(index + 1) % 4];
			const after = plane[(index + 2) % 4];

			const a = [next[0] - point[0], next[1] - point[1]];
			const b = [after[0] - next[0], after[1] - next[1]];

			const norms = Math.hypot(...a) * Math.hypot(...b);

			return (norms !== 0 && Math.abs(a[0] * b[0] + a[1] * b[1]) / norms <= MapDrawingPolygon.RECTANGLE_SKEW);

		});

	}

	static getBoundingSides(plane) {

		const hull = MapDrawingPolygon.getHull(plane);

		let best = null;

		hull.forEach((point, index) => {

			const next = hull[(index + 1) % hull.length];
			const length = Math.hypot(next[0] - point[0], next[1] - point[1]);

			if(length === 0) {
				return;
			}

			const u = [(next[0] - point[0]) / length, (next[1] - point[1]) / length];

			const alongs = hull.map(vertex => vertex[0] * u[0] + vertex[1] * u[1]);
			const acrosses = hull.map(vertex => - vertex[0] * u[1] + vertex[1] * u[0]);

			const sides = [Math.max(...alongs) - Math.min(...alongs), Math.max(...acrosses) - Math.min(...acrosses)];

			if(best === null || MapDrawingPolygon.isSmaller(sides, best)) {
				best = sides;
			}

		});

		return best ?? [0, 0];

	}

	static isSmaller(sides, best) {

		const area = sides[0] * sides[1];
		const areaBest = best[0] * best[1];

		if(Math.abs(area - areaBest) > areaBest * MapDrawingPolygon.DIMENSIONS_TIE) {
			return (area < areaBest);
		}

		return (Math.max(...sides) < Math.max(...best));

	}

	static getHull(plane) {

		const sorted = [...plane].sort((a, b) => (a[0] - b[0]) || (a[1] - b[1]));
		const cross = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);

		const chain = points => {

			const kept = [];

			points.forEach(point => {
				while(kept.length >= 2 && cross(kept[kept.length - 2], kept[kept.length - 1], point) <= 0) {
					kept.pop();
				}
				kept.push(point);
			});

			kept.pop();

			return kept;

		};

		return [...chain(sorted), ...chain([...sorted].reverse())];

	}

	/* Les deux écritures d'une surface sont celles de Zone::getArea(), que la fiche porte en gabarits */
	getSummary(labels) {

		const area = this.getArea();

		if(area === null) {
			return '';
		}

		return MapDrawingPolygon.getAreaText(area, labels);

	}

	static getAreaText(area, labels) {

		if(area > 1000) {
			return labels.areaLarge.replace('{value}', (area / 10000).toLocaleString(document.documentElement.lang || 'fr', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
		} else {
			return labels.areaSmall.replace('{value}', area);
		}

	}

	getBody() {

		return {
			coordinates: JSON.stringify(this.points),
			area: this.getArea()
		};

	}

	/* Le plan où le contour tourne : celui de son contour d'ouverture, où le serveur relit le mouvement (MapLib::getMotion()). Un
	   contour neuf n'en a pas, et tourne dans le sien */
	getPlane() {
		return MapDrawingCopy.getOrigin((this.before.length === 0) ? this.points : this.before);
	}

	/* Le mouvement rigide qui porte le contour d'ouverture sur celui-ci, que ses planches suivent à l'écran par la même règle que le
	   serveur les déplace : il manque dès qu'un sommet a bougé seul */
	getMotion() {
		return MapDrawingCopy.getMotion(this.before, this.points);
	}

}


/**
 * Un abri qu'on crée sur une carte, ou la parcelle et le jardin qu'on choisit de tracer ainsi plutôt qu'en contour : un
 * rectangle que rien ne pose d'office, tracé d'un glisser d'un coin au coin opposé (`span()`), et qui ne se règle qu'une
 * fois lâché.
 *
 * Il se tire d'un bloc, chacun de ses coins l'étire en gardant le coin opposé, et « Tourner » l'oriente. Les cotes
 * disent ses deux côtés. Une parcelle ou un jardin repasse en « Forme libre » (`drawingFree`), où il devient un contour à
 * reprendre point par point (`getFree()`). Un abri reste un rectangle : il porte ses dimensions avec sa forme
 * (`drawingDimensions`).
 */
class MapDrawingRectangle {

	/* Un côté qu'on rétrécit s'arrête à ce nombre de mètres, sous lequel le rectangle perdrait son sens */
	static SIDE_MINIMUM = 1;

	/* Les mètres d'un degré de latitude, et d'un degré de longitude à l'équateur : à l'échelle d'un jardin, la Terre est plate */
	static METERS = 111320;

	minimum = 4;

	points = [];
	closed = false;

	contour = null;
	free = false;
	rectangle = false;
	pac = false;
	dimensions = false;

	/* Le contour refermé qu'il remplace, qui reste à l'écran tant que le rectangle n'est pas commencé, par un glisser ou une saisie de
	   ses dimensions (getRectangle()) */
	previous = null;

	/* Les sommets qu'il avait à l'ouverture du geste, ceux du contour qu'il remplace compris (MapDrawingPolygon.getRectangle()) */
	before = [];

	constructor(coordinates, previous = null) {

		if(coordinates !== null) {
			this.points = coordinates;
			this.closed = true;
			this.before = coordinates.map(point => [...point]);
		}

		this.previous = previous;

	}

	begin(dataset) {

		this.free = (dataset.drawingFree !== undefined);
		this.rectangle = (dataset.drawingRectangle !== undefined);
		this.pac = (dataset.drawingPac !== undefined);
		this.dimensions = (dataset.drawingDimensions !== undefined);
		this.contour = (dataset.drawingContour === undefined) ? null : JSON.parse(dataset.drawingContour);

	}

	/* Le rectangle qu'on trace, du coin où l'appui a commencé à celui du pointeur, droit sur le nord */
	span(from, to) {

		this.previous = null;

		const [west, east] = [Math.min(from[0], to[0]), Math.max(from[0], to[0])];
		const [south, north] = [Math.min(from[1], to[1]), Math.max(from[1], to[1])];

		this.points = [[west, north], [east, north], [east, south], [west, south]];

	}

	/* Un point en mètres autour d'une origine, et l'inverse */
	static toPlane(origin, point) {

		const scale = MapDrawingRectangle.METERS * Math.cos(origin[1] * Math.PI / 180);

		return [(point[0] - origin[0]) * scale, (point[1] - origin[1]) * MapDrawingRectangle.METERS];

	}

	static fromPlane(origin, [x, y]) {

		const scale = MapDrawingRectangle.METERS * Math.cos(origin[1] * Math.PI / 180);

		return [origin[0] + x / scale, origin[1] + y / MapDrawingRectangle.METERS];

	}

	add(point) {
	}

	/* Un coin tiré garde le coin opposé, et ses deux voisins glissent le long de leurs côtés : le rectangle garde son angle */
	move(index, point) {

		const opposite = this.points[(index + 2) % 4];
		const [x, y] = MapDrawingRectangle.toPlane(opposite, point);

		const slide = (neighbour) => {

			const [dx, dy] = MapDrawingRectangle.toPlane(opposite, this.points[neighbour]);
			const length = Math.hypot(dx, dy);
			const along = Math.max(MapDrawingRectangle.SIDE_MINIMUM, (x * dx + y * dy) / length);

			return [dx / length * along, dy / length * along];

		};

		const next = slide((index + 1) % 4);
		const previous = slide((index + 3) % 4);

		this.points[(index + 1) % 4] = MapDrawingRectangle.fromPlane(opposite, next);
		this.points[(index + 3) % 4] = MapDrawingRectangle.fromPlane(opposite, previous);
		this.points[index] = MapDrawingRectangle.fromPlane(opposite, [next[0] + previous[0], next[1] + previous[1]]);

	}

	/* Le côté qui porte la longueur, le premier (`along`) ou le second : le plus long des deux, et le premier tant que rien n'est tracé */
	isLengthAlong() {

		if(this.closed === false) {
			return true;
		}

		const [first, second, third] = this.points.slice(0, 3).map(point => MapDrawingRectangle.toPlane(this.getPivot(), point));

		return Math.hypot(second[0] - first[0], second[1] - first[1]) >= Math.hypot(third[0] - second[0], third[1] - second[1]);

	}

	/* Des dimensions saisies redimensionnent le rectangle autour de son milieu, dans son orientation, la longueur sur le côté que dit
	   `lengthAlong` (isLengthAlong()). Pas encore tracé, il se pose au milieu de ce que la carte laisse voir (`visible`), droit sur le
	   nord */
	setDimensions(length, width, visible, lengthAlong) {

		this.previous = null;

		[length, width] = [length, width].map(side => Math.max(MapDrawingRectangle.SIDE_MINIMUM, side));

		let center, along, across;

		if(this.closed === false) {

			center = [(visible[0][0] + visible[1][0]) / 2, (visible[0][1] + visible[1][1]) / 2];
			[along, across] = [[1, 0], [0, -1]];

		} else {

			center = this.getPivot();

			const [first, second, third] = this.points.slice(0, 3).map(point => MapDrawingRectangle.toPlane(center, point));

			[along, across] = [[second[0] - first[0], second[1] - first[1]], [third[0] - second[0], third[1] - second[1]]];

		}

		const [alongSide, acrossSide] = lengthAlong ? [length, width] : [width, length];

		const place = (alongMeters, acrossMeters) => {

			const half = (vector, side) => vector.map(value => value / Math.hypot(...vector) * side / 2);
			const [u, v] = [half(along, alongMeters), half(across, acrossMeters)];

			this.points = [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([a, b]) => MapDrawingRectangle.fromPlane(center, [a * u[0] + b * v[0], a * u[1] + b * v[1]]));

		};

		/* Le plan compte METERS mètres au degré, turf et le serveur un peu moins : posé tel quel, le rectangle mesurerait 0,1 % de moins
		   que la saisie, soit un décimètre perdu dès 45 m, et de nouveau à chaque saisie reposée. Il se recale donc sur sa mesure */
		place(alongSide, acrossSide);

		const [alongMeasured, acrossMeasured] = this.getSides().map(side => side.value);

		place(alongSide * alongSide / alongMeasured, acrossSide * acrossSide / acrossMeasured);

		this.closed = true;

	}

	/* Le rectangle tiré d'un bloc : son milieu vient au point donné */
	moveTo(point) {

		const pivot = this.getPivot();

		this.points = this.points.map(([longitude, latitude]) => [longitude + point[0] - pivot[0], latitude + point[1] - pivot[1]]);

	}

	remove(index) {
	}

	close() {
		this.closed = true;
	}

	tool(name, from, pivot) {

		switch(name) {

			case 'remove' :
				this.points = [];
				this.closed = false;
				return null;

			case 'rotate' : {

				const points = this.points;
				const center = this.getPivot();
				const plane = this.getPlane();
				const bearing = (point) => Math.atan2(point.x - pivot.x, pivot.y - point.y) * 180 / Math.PI;

				return (to) => {

					const angle = Math.round(bearing(to) - bearing(from));

					this.points = MapDrawingCopy.getRotated(points, plane, center, angle);

					return ((angle % 360 + 360) % 360) +'°';

				};

			}

		}

	}

	/* Le même contour, à reprendre point par point. Tant que le rectangle n'est pas tracé, c'est le contour qu'il devait remplacer,
	   ou rien s'il n'en remplaçait pas */
	getFree() {

		const polygon = new MapDrawingPolygon(this.closed ? this.getRing() : this.previous);
		polygon.before = this.before;

		return polygon;

	}

	/* Le même contour, que les parcelles PAC agrandissent ou réduisent, comme getFree() */
	getPac() {

		const pac = new MapDrawingPac(this.closed ? this.getRing() : this.previous);
		pac.before = this.before;

		return pac;

	}

	/* Le plan où le contour tourne : celui de son contour d'ouverture, où le serveur relit le mouvement (MapLib::getMotion()). Un
	   contour neuf n'en a pas, et tourne dans le sien */
	getPlane() {
		return MapDrawingCopy.getOrigin((this.before.length === 0) ? this.points : this.before);
	}

	/* Le mouvement rigide qui porte le contour d'ouverture sur celui-ci, que ses planches suivent à l'écran par la même règle que le
	   serveur les déplace : il manque dès qu'un sommet a bougé seul */
	getMotion() {
		return MapDrawingCopy.getMotion(this.before, this.points);
	}

	/* Pendant le glisser qui le trace, le rectangle se voit et se cote, mais n'a ni poignée ni surface avant d'être lâché */
	getPivot() {
		return (this.points.length === 0) ? null : turf.centroid(turf.polygon([this.getRing()])).geometry.coordinates;
	}

	getRing() {
		return [...this.points, this.points[0]];
	}

	getGeometry() {

		if(this.points.length > 0) {
			return {type: 'Polygon', coordinates: [this.getRing()]};
		} else if(this.previous !== null) {
			return {type: 'Polygon', coordinates: [[...this.previous, this.previous[0]]]};
		} else {
			return null;
		}

	}

	/* Les trois modes restent dans la rangée, inactifs tant que le rectangle n'est pas tracé, et absents tant qu'un contour lui est
	   confié (refreshTools()) */
	getModes() {
		return ['edit', 'move', 'rotate'];
	}

	getHandles(mode) {
		return (mode === 'edit' && this.closed) ? this.points.map((point, index) => ({type: 'point', index: index, at: point})) : [];
	}

	/* Comme le contour (MapDrawingPolygon.getTools()) : les formes que le lien propose, puis les outils d'un rectangle tracé */
	getTools() {

		const shapes = MapDrawingPolygon.getShapes(this);

		return this.closed ? [...shapes, 'rotate', 'remove'] : shapes;

	}

	/* Le rectangle s'aimante par ses quatre côtés */
	getOutline() {
		return (this.points.length === 0) ? null : this.points;
	}

	getLabels() {
		return [];
	}

	/* Les deux côtés qui se touchent au premier coin, en mètres, chacun coté en son milieu entre ses deux coins */
	getSides() {

		if(this.points.length === 0) {
			return [];
		}

		return [[0, 1], [1, 2]].map(([from, to]) => ({
			from: this.points[from],
			to: this.points[to],
			at: turf.midpoint(this.points[from], this.points[to]).geometry.coordinates,
			value: turf.distance(this.points[from], this.points[to], {units: 'meters'})
		}));

	}

	/* Tant qu'aucun rectangle n'est commencé, le contour qu'il remplace et qui reste à l'écran est la forme : c'est lui que
	   « Créer » ou « Enregistrer » emportent, avec sa surface et ses dimensions (getRectangle()) */
	getConfided() {
		return (this.closed === false && this.previous !== null) ? this.getFree() : null;
	}

	isReady() {
		return (this.closed || this.previous !== null);
	}

	/* Un abri a la surface que le serveur tire de ses dimensions, que le bloc annonce donc telle qu'elle sera enregistrée */
	getArea() {

		const confided = this.getConfided();

		if(confided !== null) {
			return confided.getArea();
		}

		if(this.closed === false) {
			return null;
		}

		if(this.dimensions) {
			const [length, width] = this.getDimensions();
			return Math.round(length * width);
		}

		return Math.round(turf.area(this.getGeometry()));

	}

	/* La longueur puis la largeur, au décimètre */
	getDimensions() {

		const confided = this.getConfided();

		if(confided !== null) {
			return confided.getDimensions();
		}

		const sides = this.getSides().map(side => Math.round(side.value * 10) / 10);

		return [Math.max(...sides), Math.min(...sides)];

	}

	getSummary(labels) {
		return MapDrawingPolygon.prototype.getSummary.call(this, labels);
	}

	getBody() {

		const confided = this.getConfided();

		if(confided !== null) {
			return confided.getBody();
		}

		const body = {
			coordinates: JSON.stringify(this.points),
			area: this.getArea()
		};

		if(this.dimensions) {
			[body.length, body.width] = this.getDimensions();
		}

		return body;

	}

}

/**
 * Une parcelle reprise des parcelles déclarées à la PAC : un clic dans une parcelle déclarée pose son contour, un clic dans une
 * voisine qui le touche l'y ajoute, un clic dans une parcelle déjà prise l'en retire (`getTaken()`). La séance lit les parcelles
 * autour du clic (MapDrawing.pickPac()), la forme ne fait que les assembler. Le contour reste celui d'un MapDrawingPolygon, qui se
 * retouche point par point et repasse en « Forme libre » ou en « Rectangle » tel quel.
 */
class MapDrawingPac extends MapDrawingPolygon {

	/* Une parcelle dont le contour couvre plus que cette part est déjà prise : un clic l'en retire au lieu de l'ajouter */
	static TAKEN = 0.5;

	/* Deux sommets plus proches que cette distance, en mètres, n'en font qu'un, comme MapSetting::PAC_MERGE : deux parcelles voisines
	   ne partagent pas leur bord au centimètre près, et leur union en garderait les deux tracés */
	static MERGE = 1;

	/* Le plafond de sommets d'un contour, comme MapSetting::SHAPE_POINTS_MAX, et les écarts en mètres sous lesquels un contour
	   qui le dépasse se simplifie, du plus fidèle au plus grossier, avant d'être refusé */
	static POINTS_MAX = 500;
	static SIMPLIFY = [0.5, 1, 2];

	/* Une parcelle à cette distance du contour ou moins, en mètres, s'y recolle (`getUnion()`), et SEAM est l'élargissement qui
	   referme la couture quand les sommets recollés n'ont pas suffi */
	static SNAP = 5;
	static SEAM = 0.05;

	/* Ce dont le contour est fait : le contour tracé ou retouché avant la première parcelle prise (`base`), puis les parcelles prises,
	   dans l'ordre, et le contour qu'elles ont fait (`built`), que toute retouche périme */
	base = null;
	parcels = [];
	built = null;

	/* Un clic pose ou retire une parcelle : il n'ajoute pas de point */
	add() {
	}

	getFree() {

		const polygon = new MapDrawingPolygon(this.closed ? this.getRing() : null);
		polygon.before = this.before;

		return polygon;

	}

	/* Ce que deviendrait le contour avec cette parcelle : `points` et l'écart de surface `area`, signé, ou le refus (`refusal`). Un
	   contour n'a qu'un anneau : une parcelle à plus de SNAP de lui, ou dont le retrait le couperait ou le percerait, est refusée.
	   `base` et `parcels` disent de quoi le contour est fait, pour qu'un retrait le refasse sans la parcelle retirée */
	getTaken(parcel) {

		const polygon = turf.polygon([[...parcel, parcel[0]]]);

		if(this.closed === false) {
			return {...MapDrawingPac.getPoints(parcel, Math.round(turf.area(polygon))), base: null, parcels: [parcel]};
		}

		const contour = turf.polygon([this.getRing()]);
		const inside = turf.intersect(turf.featureCollection([contour, polygon]));
		const removing = (inside !== null && turf.area(inside) > turf.area(polygon) * MapDrawingPac.TAKEN);
		const built = this.isBuilt();

		if(removing === false) {

			const union = MapDrawingPac.getUnion(this.points, parcel);

			if(union.refusal !== undefined) {
				return union;
			}

			return {
				...MapDrawingPac.getPoints(union.ring, Math.round(turf.area(turf.polygon([union.ring])) - turf.area(contour))),
				base: built ? this.base : this.points,
				parcels: built ? [...this.parcels, parcel] : [parcel]
			};

		}

		if(built && this.parcels.some(taken => MapDrawingPac.isSame(taken, parcel))) {
			return this.getRebuilt(parcel, contour);
		}

		const result = turf.difference(turf.featureCollection([contour, polygon]));

		/* La parcelle retirée était tout le contour */
		if(result === null) {
			return {points: [], area: - Math.round(turf.area(contour)), base: null, parcels: []};
		}

		if(result.geometry.type === 'MultiPolygon') {
			return {refusal: 'split'};
		}

		if(result.geometry.coordinates.length > 1) {
			return {refusal: 'hole'};
		}

		const ring = result.geometry.coordinates[0];
		const taken = MapDrawingPac.getPoints(ring, Math.round(turf.area(turf.polygon([ring])) - turf.area(contour)));

		return {...taken, base: taken.points ?? null, parcels: []};

	}

	/* Le contour refait sans la parcelle retirée, depuis ce dont il est fait, dans l'ordre où les parcelles ont été prises : retirer
	   celle qu'on vient d'ajouter rend le contour d'avant au sommet près, ce que la différence ne sait pas faire d'une parcelle
	   recollée. Une parcelle qui ne touche plus rien se reprend après les suivantes, et reste à part si rien ne la rejoint */
	getRebuilt(removed, contour) {

		let left = this.parcels.filter(parcel => MapDrawingPac.isSame(parcel, removed) === false);
		let points = this.base;

		while(left.length > 0) {

			const kept = [];

			left.forEach(parcel => {

				if(points === null) {
					points = MapDrawingPac.getPoints(parcel, 0).points ?? null;
					return;
				}

				const union = MapDrawingPac.getUnion(points, parcel);
				const taken = (union.refusal === undefined) ? MapDrawingPac.getPoints(union.ring, 0) : union;

				if(taken.refusal === undefined) {
					points = taken.points;
				} else {
					kept.push(parcel);
				}

			});

			if(kept.length === left.length) {
				return {refusal: 'split'};
			}

			left = kept;

		}

		if(points === null) {
			return {points: [], area: - Math.round(turf.area(contour)), base: null, parcels: []};
		}

		const polygon = turf.polygon([[...points, points[0]]]);

		/* Les parcelles qui l'entourent referment le contour sur la parcelle retirée */
		const inside = turf.intersect(turf.featureCollection([polygon, turf.polygon([[...removed, removed[0]]])]));

		if(inside !== null && turf.area(inside) > turf.area(turf.polygon([[...removed, removed[0]]])) * MapDrawingPac.TAKEN) {
			return {refusal: 'hole'};
		}

		return {
			points: points,
			area: Math.round(turf.area(polygon) - turf.area(contour)),
			base: this.base,
			parcels: this.parcels.filter(parcel => MapDrawingPac.isSame(parcel, removed) === false)
		};

	}

	/* L'anneau extérieur de l'union d'un contour et d'une parcelle, recollés l'un à l'autre : chaque sommet de l'un à SNAP ou moins
	   du bord de l'autre se pose sur ce bord. Les contours du registre laissent souvent un interstice de quelques mètres entre deux
	   parcelles qui se touchent sur le terrain, et deux points qui s'écartent du bord de quelques millionièmes de degré suffisent à
	   séparer l'union : elle se refait alors avec la parcelle élargie de SEAM. Ce que l'union laisse de l'interstice à l'intérieur
	   tombe avec l'anneau extérieur */
	static getUnion(points, parcel) {

		/* La parcelle se recolle d'abord, et le contour sur la parcelle recollée : deux sommets qui se font face ne se croisent pas */
		const snapped = MapDrawingPac.getSnapped(parcel, points);
		const recolled = MapDrawingPac.getSnapped(points, snapped);
		const contour = turf.polygon([[...recolled, recolled[0]]]);
		const polygon = turf.polygon([[...snapped, snapped[0]]]);

		let result = turf.union(turf.featureCollection([contour, polygon]));

		if(result !== null && result.geometry.type === 'MultiPolygon') {
			result = turf.union(turf.featureCollection([contour, turf.buffer(polygon, MapDrawingPac.SEAM, {units: 'meters', steps: 1})]));
		}

		if(result === null || result.geometry.type === 'MultiPolygon') {
			return {refusal: 'apart'};
		}

		return {ring: result.geometry.coordinates[0]};

	}

	/* Les sommets à SNAP ou moins du bord de l'anneau posés sur ce bord, au point le plus proche, ou sur le sommet du bord qui en
	   est à moins de MERGE : deux parcelles recollées partagent alors leurs coins au bit près. Le point se calcule sur le segment
	   plan, comme turf assemble les polygones, et non sur la sphère, dont l'arc s'écarte du segment assez pour laisser un jour. Un
	   sommet déjà à SEAM du bord ou moins ne bouge pas */
	static getSnapped(points, ring) {

		const metres = MapDrawingRectangle.METERS;
		const scale = Math.cos(ring[0][1] * Math.PI / 180);

		return points.map(point => {

			let nearest = null;

			ring.forEach((start, index) => {

				const end = ring[(index + 1) % ring.length];
				const dx = (end[0] - start[0]) * scale * metres;
				const dy = (end[1] - start[1]) * metres;
				const length = Math.hypot(dx, dy);

				if(length === 0) {
					return;
				}

				const along = Math.max(0, Math.min(length, ((point[0] - start[0]) * scale * metres * dx + (point[1] - start[1]) * metres * dy) / length));
				const target = (along < MapDrawingPac.MERGE) ? start : ((length - along < MapDrawingPac.MERGE) ? end : [start[0] + (end[0] - start[0]) * along / length, start[1] + (end[1] - start[1]) * along / length]);
				const distance = Math.hypot((point[0] - target[0]) * scale * metres, (point[1] - target[1]) * metres);

				if(nearest === null || distance < nearest.distance) {
					nearest = {target, distance};
				}

			});

			return (nearest !== null && nearest.distance > MapDrawingPac.SEAM && nearest.distance <= MapDrawingPac.SNAP) ? nearest.target : point;

		});

	}

	static isSame(parcel, other) {
		return (JSON.stringify(parcel) === JSON.stringify(other));
	}

	/* Le contour est encore celui que les parcelles ont fait : aucune retouche ne l'a changé depuis */
	isBuilt() {
		return (this.built === JSON.stringify(this.points));
	}

	/* Le contour en sommets distincts, simplifié s'il en a trop pour s'enregistrer */
	static getPoints(ring, area) {

		let points = MapDrawingPac.getUnspiked(MapDrawingPac.getMerged(MapDrawingPolygon.getVertices(ring)));

		for(const tolerance of MapDrawingPac.SIMPLIFY) {

			if(points.length <= MapDrawingPac.POINTS_MAX) {
				break;
			}

			const simplified = turf.simplify(turf.polygon([[...points, points[0]]]), {tolerance: tolerance / MapDrawingRectangle.METERS, highQuality: true});

			points = MapDrawingPolygon.getVertices(simplified.geometry.coordinates[0]);

		}

		if(points.length > MapDrawingPac.POINTS_MAX) {
			return {refusal: 'detailed'};
		}

		return {points: points, area: area};

	}

	/* Les sommets à moins de MERGE du sommet gardé avant eux se fondent en lui, comme PacLib::getRing() */
	static getMerged(points) {

		const merged = [];

		points.forEach(point => {
			if(merged.length === 0 || turf.distance(merged[merged.length - 1], point, {units: 'meters'}) >= MapDrawingPac.MERGE) {
				merged.push(point);
			}
		});

		while(merged.length > 1 && turf.distance(merged[merged.length - 1], merged[0], {units: 'meters'}) < MapDrawingPac.MERGE) {
			merged.pop();
		}

		return merged;

	}

	/* Les pointes de moins de MERGE de large retirées. Fondre deux sommets voisins déplace un bord de moins de MERGE : l'union de deux
	   parcelles dont les coins ne tombent pas l'un sur l'autre couvre alors une lanière en plus, que le retrait de la voisine laisse
	   en aller-retour le long de son ancien bord. Une pointe est un sommet où le contour repart en arrière, plus d'un quart de tour,
	   dans un triangle avec ses voisins moins haut que MERGE : la retirer peut en découvrir une autre, d'où la boucle */
	static getUnspiked(points) {

		let spike = 0;

		while(points.length > 3 && spike < points.length) {

			const before = points[(spike + points.length - 1) % points.length];
			const point = points[spike];
			const after = points[(spike + 1) % points.length];

			const turn = Math.abs(((turf.bearing(point, after) - turf.bearing(before, point)) % 360 + 540) % 360 - 180);
			const side = Math.max(turf.distance(before, point, {units: 'meters'}), turf.distance(point, after, {units: 'meters'}), turf.distance(before, after, {units: 'meters'}));
			const height = 2 * turf.area(turf.polygon([[before, point, after, before]])) / side;

			if(turn > 90 && height < MapDrawingPac.MERGE) {
				points = MapDrawingPac.getMerged(points.filter((_, index) => index !== spike));
				spike = 0;
			} else {
				spike++;
			}

		}

		return points;

	}

	take(taken) {

		this.points = taken.points;
		this.closed = (taken.points.length > 0);
		this.base = taken.base ?? null;
		this.parcels = taken.parcels ?? [];
		this.built = JSON.stringify(taken.points);

	}

	/* Une parcelle est prise quand un de ses points intérieurs est dans le contour */
	isTaken(parcel) {
		return (this.closed && turf.booleanPointInPolygon(turf.pointOnFeature(turf.polygon([[...parcel, parcel[0]]])), turf.polygon([this.getRing()])));
	}

}


/**
 * Des planches qu'on pose sur une carte, dans un cadre de trois points : les deux premiers en travers des planches
 * et le troisième dans leur sens. Le cadre se déplace, tourne et se retourne d'un bloc, et les planches le suivent.
 *
 * Des planches toutes tracées s'ouvrent à leur place, chacune gardant la sienne dans le cadre (`placed`). Sinon,
 * elles forment un rang neuf posé d'office, côte à côte à allées égales (`MapCartography.getBeds()`). Les planches
 * sont celles que la liste coche : en cocher ou en décocher une pendant le geste refait la forme (`setBeds()`).
 */
class MapDrawingLine {

	/* L'écart, en centimètres, sous lequel le bord tiré se pose au passe-pied du plan */
	static MAGNET = 10;

	/* La part du plus grand côté d'un contour à partir de laquelle un autre côté le vaut pour poser le rang */
	static SIDE_EQUAL = 0.95;

	/* Le plus petit pas, en kilomètres, dont un rang posé d'office glisse le long du plus grand côté, et le nombre de pas dans
	   chaque sens : un rang qui ne trouve rien dans un jardin immense se pose à la main */
	static SLIDE_MIN = 0.001;
	static SLIDES = 200;

	/* Le nombre de rangs qu'un rang posé d'office essaie en tout avant de se poser contre le plus grand côté : la pose se refait
	   à chaque saisie du formulaire, et un jardin de 300 m plein de planches ne doit pas figer la page */
	static CANDIDATES = 2000;

	minimum = 3;

	points = [];
	closed = false;

	plot = null;
	form = null;
	contour = null;
	last = null;
	visible = null;
	beds = [];

	/* Le rang de planches à créer, que le formulaire décrit au lieu des cases cochées (`data-drawing-create`) */
	create = false;

	/* Les champs qui décrivent le rang à créer, ceux du panneau d'ajout de planches sauf quand le lien en nomme d'autres
	   (`data-drawing-fields`). Sans champ de nom, les planches se nomment de 1 à leur nombre */
	fields = {number: 'number', length: 'length', width: 'width', alleyWidth: 'alleyWidth', name: 'name[]'};

	/* La place de chaque planche d'une sélection toute tracée, dans l'ordre de `beds` : son coin le long du cadre et en travers
	   de lui, en kilomètres depuis son premier point, et l'écart de son cap à celui du cadre, en degrés. NULL pour un rang neuf */
	placed = null;

	/* Les planches posées hors du geste, que MapDrawing donne à la forme : un rang posé d'office ne tombe pas sur elles */
	neighbours = () => [];

	/* Le rang est-il encore tel que la pose d'office l'a mis : chaque geste le lève, et tant qu'il tient, le rang se repose
	   d'office quand les planches changent */
	laid = false;

	rows = null;
	rowsKey = null;

	constructor(coordinates) {

		if(coordinates !== null) {
			this.points = coordinates;
			this.closed = true;
		}

	}

	/* Le lien porte le contour qui reçoit les planches et le trait d'où se déduit la pose d'office */
	begin(dataset, visible) {

		this.visible = visible;
		this.plot = dataset.drawingId;
		this.form = qs('#'+ dataset.drawingForm);
		this.contour = JSON.parse(dataset.drawingContour);
		this.last = JSON.parse(dataset.drawingLast);
		this.create = (dataset.drawingCreate !== undefined);

		if(dataset.drawingFields !== undefined) {
			this.fields = {...this.fields, ...JSON.parse(dataset.drawingFields)};
		}

		this.setBeds(this.getChecked());

	}

	/* Des planches toutes tracées, qui s'ouvrent à leur place plutôt qu'en rang neuf */
	isPlacedAll(beds) {
		return (this.create === false && beds.length > 0 && beds.every(bed => bed.corner !== null));
	}

	/* Le rang se pose d'office dès qu'il a des planches, à l'ouverture ou, pour des planches à créer, à la première saisie qui les décrit */
	putDefault() {

		if(this.closed === false && this.points.length === 0 && this.beds.length > 0) {
			this.points = this.getDefaultInView();
			this.closed = true;
			this.laid = true;
		}

	}

	/* La carte ne bouge pas pour montrer le rang : sorti de la vue, il repart de ce que la vue montre du jardin, comme d'un jardin à
	   lui seul */
	getDefaultInView() {

		const others = this.neighbours();
		const row = MapDrawingLine.getDefault(this.contour, this.last, this.beds, others);
		const [[west, south], [east, north]] = this.visible;
		const view = turf.bboxPolygon([west, south, east, north]);

		const corners = MapDrawingLine.getCorners(row);

		if(turf.booleanWithin(turf.polygon([[...corners, corners[0]]]), view)) {
			return row;
		}

		const seen = turf.intersect(turf.featureCollection([turf.polygon([this.contour]), view]));

		if(seen === null || seen.geometry.type !== 'Polygon') {
			return row;
		}

		return MapDrawingLine.getDefault(seen.geometry.coordinates[0], null, this.beds, others);

	}

	/* Les planches cochées, dans l'ordre de leur nom, qui est celui où un tracé enregistré les range */
	getChecked() {

		if(this.create) {
			return MapDrawingLine.getCreated(this.form, this.fields);
		}

		return [...this.form.qsa('[name="ids[]"]:checked')]
			.map(input => ({
				id: input.value,
				name: input.dataset.name,
				length: parseFloat(input.dataset.length),
				width: parseFloat(input.dataset.width),
				/* Le passe-pied du plan, absent quand il n'est pas renseigné : la cote se montre alors sans aimant */
				alley: (input.dataset.alley === undefined) ? null : parseFloat(input.dataset.alley),
				/* La place d'une planche tracée, absente pour une planche qui ne l'est pas */
				corner: (input.dataset.corner === undefined) ? null : JSON.parse(input.dataset.corner),
				angle: (input.dataset.angle === undefined) ? null : JSON.parse(input.dataset.angle)
			}))
			.sort((a, b) => a.name.localeCompare(b.name, undefined, {numeric: true}));

	}

	/* Les noms des planches que le formulaire décrit, un par planche (MapBed.changeNumber()), ou de 1 à leur nombre sans champ de nom */
	static getNames(form, fields) {

		if(fields.name === null) {
			const number = parseInt(form.qs('[name="'+ fields.number +'"]').value);
			return isNaN(number) ? [] : Array.from({length: Math.max(0, number)}, (_, position) => String(position + 1));
		}

		return [...form.qsa('[name="'+ fields.name +'"]')].map(input => input.value);

	}

	/**
	 * Les planches du rang à dessiner, sans aucune tant que le formulaire ne dit pas leur nombre et leurs dimensions, et jamais
	 * plus que le plafond. Leur position dans le rang leur tient lieu d'identifiant.
	 */
	static getCreated(form, fields) {

		const field = property => form.qs('[name="'+ fields[property] +'"]');

		const length = parseFloat(field('length').value);
		const width = parseFloat(field('width').value);
		const alley = parseFloat(field('alleyWidth').value);

		if(isNaN(length) || length <= 0 || isNaN(width) || width <= 0) {
			return [];
		}

		return MapDrawingLine.getNames(form, fields)
			.slice(0, parseInt(field('number').max))
			.map((name, position) => ({
				id: position,
				name: name,
				length: length,
				width: width,
				/* Sans passe-pied, les planches se posent côte à côte */
				alley: isNaN(alley) ? null : alley
			}));

	}

	/* La largeur du rang en kilomètres, planches et passe-pieds, et sa plus longue planche */
	static getSize(beds) {

		return {
			/* Sans passe-pied au plan, les planches se posent côte à côte */
			width: beds.reduce((total, bed, index) => total + (bed.width + ((index < beds.length - 1) ? (bed.alley ?? 0) : 0)) / 100 / 1000, 0),
			length: Math.max(...beds.map(bed => bed.length)) / 1000
		};

	}

	/* Les trois points d'un rang qui part de `start`, large de `size.width` vers `across` et long de `size.length` vers `along` */
	static getRow(start, across, along, size) {

		const end = turf.destination(start, size.width, across).geometry.coordinates;

		return [start, end, turf.destination(end, size.length, along).geometry.coordinates];

	}

	/* Les quatre coins du rang, le quatrième en face du deuxième */
	static getCorners(points) {
		return [...points, [points[0][0] + points[2][0] - points[1][0], points[0][1] + points[2][1] - points[1][1]]];
	}

	/* Un rang posé contre un côté a deux coins sur le contour même, que l'arrondi peut faire tomber dehors :
	   chaque coin se teste un peu rentré vers le milieu du rang */
	static isInside(points, contour) {

		const polygon = turf.polygon([contour]);
		const corners = MapDrawingLine.getCorners(points);
		const center = [(points[0][0] + points[2][0]) / 2, (points[0][1] + points[2][1]) / 2];

		return corners.every(corner => turf.booleanPointInPolygon([corner[0] + (center[0] - corner[0]) / 50, corner[1] + (center[1] - corner[1]) / 50], polygon));

	}

	/* Un rang qui ne recouvre aucune des planches `others` : posé contre l'une d'elles sans passe-pied, il la touche sans la
	   recouvrir, d'où le test un peu rentré vers le milieu du rang, comme isInside() */
	static isFree(points, others) {

		const corners = MapDrawingLine.getCorners(points);
		const center = [(points[0][0] + points[2][0]) / 2, (points[0][1] + points[2][1]) / 2];
		const inner = corners.map(corner => [corner[0] + (center[0] - corner[0]) / 50, corner[1] + (center[1] - corner[1]) / 50]);
		const polygon = turf.polygon([[...inner, inner[0]]]);
		const [west, south, east, north] = turf.bbox(polygon);

		/* Une planche dont l'emprise ne touche pas celle du rang ne peut pas le recouvrir, et se juge sans calcul de forme */
		return others.every(other => {
			const [otherWest, otherSouth, otherEast, otherNorth] = turf.bbox(other);
			return (otherEast < west || otherWest > east || otherNorth < south || otherSouth > north || turf.booleanIntersects(polygon, other) === false);
		});

	}

	/**
	 * Où le rang se pose avant qu'on y touche. Il continue le dernier trait, dans son sens, un passe-pied plus loin.
	 * Quand cette suite sort du jardin ou tombe sur des planches posées (`others`), il repart du coin d'où part un jardin
	 * vierge en gardant le sens du dernier trait, tourné du côté qui le fait tenir dans le contour. Dans un jardin vierge,
	 * ou quand aucun de ces sens ne tient dans le contour, les planches s'allongent le long de son plus grand côté, la
	 * première contre lui, et le rang avance vers l'intérieur. Il part du coin le plus en haut à gauche de ce côté, d'où se
	 * lit le plan, et de deux côtés presque égaux, de celui qui a ce coin. Un rang qui repart d'un coin et tombe sur des
	 * planches posées avance d'un rang et d'un passe-pied à la fois, tant qu'il reste dans le contour. Contre le plus grand
	 * côté, il glisse aussi le long de lui, d'un quart de sa longueur à chaque pas, pour trouver la place libre qu'un jardin
	 * déjà garni laisse ailleurs que dans son coin. Quand rien ne tient, ou après MapDrawingLine.CANDIDATES rangs essayés, il se
	 * pose contre le plus grand côté malgré tout : c'est à la main qu'il trouvera sa place.
	 */
	static getDefault(contour, last, beds, others = []) {

		const size = MapDrawingLine.getSize(beds);

		/* Sans passe-pied au plan, le rang se pose contre le dernier trait ou contre les planches qu'il évite */
		const alley = (beds[0].alley ?? 0) / 100 / 1000;

		const sides = contour.slice(0, -1).map((point, index) => [point, contour[index + 1]]);
		const longest = Math.max(...sides.map(side => turf.distance(...side)));

		/* Plus un coin est haut et à gauche, plus il compte, au même pas sur les deux axes : le degré de longitude raccourcit avec la latitude */
		const getTopLeft = point => point[1] - point[0] * Math.cos(point[1] * Math.PI / 180);

		const side = sides
			.filter(side => turf.distance(...side) >= longest * MapDrawingLine.SIDE_EQUAL)
			.flatMap(([from, to]) => [[from, to], [to, from]])
			.reduce((best, side) => (best === null || getTopLeft(side[0]) > getTopLeft(best[0])) ? side : best, null);

		let candidates = 0;

		const advance = points => {

			const across = turf.bearing(points[0], points[1]);

			for(let row = points; candidates++ < MapDrawingLine.CANDIDATES && MapDrawingLine.isInside(row, contour); row = row.map(point => turf.destination(point, size.width + alley, across).geometry.coordinates)) {
				if(MapDrawingLine.isFree(row, others)) {
					return row;
				}
			}

			return null;

		};

		if(last !== null) {

			const across = turf.bearing(last[0], last[1]);
			const along = turf.bearing(last[1], last[2]);

			const next = MapDrawingLine.getRow(turf.destination(last[1], alley, across).geometry.coordinates, across, along, size);

			if(MapDrawingLine.isInside(next, contour) && MapDrawingLine.isFree(next, others)) {
				return next;
			}

			for(const [turnAcross, turnAlong] of [[0, 0], [180, 0], [0, 180], [180, 180]]) {

				const row = advance(MapDrawingLine.getRow(side[0], across + turnAcross, along + turnAlong, size));

				if(row !== null) {
					return row;
				}

			}

		}

		const along = turf.bearing(...side);
		const middle = turf.midpoint(...side).geometry.coordinates;

		/* Des deux perpendiculaires au côté, celle qui entre dans le contour */
		const inward = turf.booleanPointInPolygon(turf.destination(middle, longest / 100, along + 90), turf.polygon([contour])) ? along + 90 : along - 90;

		const row = MapDrawingLine.getRow(side[0], inward, along, size);

		/* Les pas le long du côté, d'un bout à l'autre de ce que le contour couvre dans ce sens, le coin d'abord, puis en avant et
		   en arrière de lui : un contour qui n'est pas convexe peut déborder des deux côtés */
		const extent = contour.map(point => turf.distance(side[0], point) * Math.cos((turf.bearing(side[0], point) - along) * Math.PI / 180));
		const step = Math.max(size.length / 4, MapDrawingLine.SLIDE_MIN);
		const forward = Math.min(Math.ceil(Math.max(...extent) / step), MapDrawingLine.SLIDES);
		const backward = Math.min(Math.ceil(-Math.min(...extent) / step), MapDrawingLine.SLIDES);

		for(const slide of [...Array(forward + 1).keys(), ...[...Array(backward).keys()].map(index => -index - 1)]) {

			const free = advance(row.map(point => turf.destination(point, slide * step, along).geometry.coordinates));

			if(free !== null) {
				return free;
			}

		}

		return row;

	}

	/**
	 * Des planches toutes tracées s'ouvrent à leur place. Un rang neuf auquel on n'a pas touché se repose d'office : agrandi
	 * depuis son premier point, il recouvrirait ce que sa première pose évitait. Un rang neuf qu'on a déplacé garde son premier
	 * point et ses deux directions, et sa largeur et sa longueur suivent les planches cochées. Une sélection posée qui gagne
	 * ou perd une planche tracée garde le geste en cours : chaque planche qui reste repart de la place où on vient de la
	 * mettre, et non de celle qui est enregistrée. Une sélection posée qui gagne une planche non tracée devient un rang
	 * neuf, posé d'office comme à l'ouverture.
	 */
	setBeds(beds) {

		const wasPlaced = (this.placed !== null);

		if(wasPlaced) {

			const current = new Map(this.getRows().polygons.map((polygon, position) => [String(this.beds[position].id), MapDrawingLine.getCanonical(polygon)]));

			beds = beds.map(bed => current.has(String(bed.id)) ? {...bed, ...current.get(String(bed.id))} : bed);

		}

		this.beds = beds;

		if(this.isPlacedAll(beds)) {
			[this.points, this.placed] = MapDrawingLine.getPlaced(beds);
			this.closed = true;
			return;
		}

		this.placed = null;

		if(wasPlaced || this.laid) {
			this.points = [];
			this.closed = false;
		}

		this.putDefault();

		/* Un rang qu'on vient de poser d'office a déjà la taille de ses planches */
		if(this.closed === false || beds.length === 0 || this.laid) {
			return;
		}

		this.points = MapDrawingLine.getRow(this.points[0], turf.bearing(this.points[0], this.points[1]), turf.bearing(this.points[1], this.points[2]), MapDrawingLine.getSize(beds));

	}

	/**
	 * Le cadre de planches tracées et la place de chacune dans lui. Le cadre suit le cap de la première planche et les
	 * enveloppe toutes : son premier point est le coin d'où se comptent les places.
	 */
	static getPlaced(beds) {

		const origin = beds[0].corner;
		const across = beds[0].angle * 180 / Math.PI;
		const along = across + 90;

		/* Un point lu en deux mesures depuis le coin de la première planche : en travers du cadre et le long de lui */
		const project = point => {
			const distance = turf.distance(origin, point);
			const bearing = turf.bearing(origin, point);
			return [distance * Math.cos((bearing - across) * Math.PI / 180), distance * Math.sin((bearing - across) * Math.PI / 180)];
		};

		const corners = beds.map(bed => project(bed.corner));
		const extent = beds.flatMap(bed => MapCartography.getBed(bed, bed.corner, bed.angle).polygon.geometry.coordinates[0]).map(project);

		const [minX, minY] = [0, 1].map(axis => Math.min(...extent.map(point => point[axis])));
		const [maxX, maxY] = [0, 1].map(axis => Math.max(...extent.map(point => point[axis])));

		const start = turf.destination(turf.destination(origin, minX, across).geometry.coordinates, minY, along).geometry.coordinates;

		return [
			MapDrawingLine.getRow(start, across, along, {width: maxX - minX, length: maxY - minY}),
			beds.map((bed, position) => ({
				x: corners[position][0] - minX,
				y: corners[position][1] - minY,
				turn: bed.angle * 180 / Math.PI - across
			}))
		];

	}

	/* Des planches parallèles, à un demi-degré près, que deux caps opposés le soient aussi : un rang neuf l'est toujours */
	isParallel() {

		if(this.placed === null) {
			return true;
		}

		return this.placed.every(place => Math.abs(Math.sin((place.turn - this.placed[0].turn) * Math.PI / 180)) < Math.sin(0.5 * Math.PI / 180));

	}

	/**
	 * Les places de planches parallèles réparties à allées de `gap` centimètres en travers du cadre, dans l'ordre où elles s'y
	 * suivent : chacune garde sa place le long du cadre, et une planche tournée d'un demi-tour part de l'autre bord de sa largeur.
	 * Rend aussi les planches de ce bout-ci et de ce bout-là, qui bordent les deux bords du cadre.
	 */
	static spread(placed, beds, gap) {

		const footprints = placed.map((place, position) => {
			const width = beds[position].width / 100 / 1000;
			return {position: position, width: width, left: Math.min(place.x, place.x + width * Math.cos(place.turn * Math.PI / 180))};
		});

		const order = [...footprints].sort((a, b) => a.left - b.left);

		const spread = [...placed];
		let left = 0;

		order.forEach(footprint => {
			spread[footprint.position] = {...placed[footprint.position], x: placed[footprint.position].x + left - footprint.left};
			left += footprint.width + gap / 100 / 1000;
		});

		return {placed: spread, first: beds[order[0].position], last: beds[order[order.length - 1].position]};

	}

	/* Un rang ne se pose pas au clic : il arrive posé d'office (putDefault()) dès qu'il a des planches */
	add(point) {
	}

	/* La seule poignée de point d'un rang posé est son milieu : le tirer déplace le rang */
	move(index, point) {
		this.moveTo(point);
	}

	/* Un rang posé se déplace d'un bloc : son milieu vient au point donné, et ses trois points le suivent */
	moveTo(point) {

		const pivot = this.getPivot();

		this.points = this.points.map(([longitude, latitude]) => [longitude + point[0] - pivot[0], latitude + point[1] - pivot[1]]);
		this.laid = false;

	}

	remove(index) {
		this.points.splice(index, 1);
	}

	close() {
	}

	tool(name, from, pivot) {

		this.laid = false;

		switch(name) {

			case 'rotate' : {

				const before = turf.lineString(this.points);
				const center = this.getPivot();
				const bearing = (point) => Math.atan2(point.x - pivot.x, pivot.y - point.y) * 180 / Math.PI;

				return (to) => {

					const angle = Math.round(bearing(to) - bearing(from));

					this.points = turf.transformRotate(before, angle, {pivot: center}).geometry.coordinates;

					return ((angle % 360 + 360) % 360) +'°';

				};

			}

			/* Le rang retourné sur place : sa première planche passe au bout, d'un clic */
			case 'flip' :
				this.points = turf.transformRotate(turf.lineString(this.points), 180, {pivot: this.getPivot()}).geometry.coordinates;
				return null;

		}

	}

	getPivot() {
		return [(this.points[0][0] + this.points[2][0]) / 2, (this.points[0][1] + this.points[2][1]) / 2];
	}

	/* Le rang s'aimante par ses quatre côtés, ceux de ses deux planches du bout et ses deux bouts */
	getOutline() {
		return this.isReady() ? MapDrawingLine.getCorners(this.points) : null;
	}

	/* Les deux bords du rang parallèles aux planches : celui de la première, puis celui de la dernière */
	getEdges() {

		const corners = MapDrawingLine.getCorners(this.points);

		return [[corners[0], corners[3]], [corners[1], corners[2]]];

	}

	/* Le passe-pied que le plan donne au rang, quand toutes ses planches ont le même : le cran du bord tiré n'a pas de valeur à prendre sinon */
	static getAlley(beds) {

		const alleys = [...new Set(beds.map(bed => bed.alley))];

		return (alleys.length === 1) ? alleys[0] : null;

	}

	/* Un écart à moins de MAGNET du passe-pied du plan s'y pose, et un écart égal au centimètre près est conforme */
	static getGap(gap, alley) {

		if(alley !== null && Math.abs(gap - alley) < MapDrawingLine.MAGNET) {
			gap = alley;
		}

		return {gap: gap, ok: (alley !== null && Math.round(gap) === Math.round(alley))};

	}

	/* Là où se pose la cote d'un écart contre le bord `index` : aux trois quarts de la plus courte planche, la poignée tenant
	   le milieu du bord, à `offset` centimètres du bord vers l'extérieur */
	getSayAt(index, offset) {

		const across = turf.bearing(this.points[0], this.points[1]) + ((index === 0) ? 180 : 0);
		const shortest = Math.min(...this.beds.map(bed => bed.length)) / 1000;

		return turf.destination(turf.along(turf.lineString(this.getEdges()[index]), shortest * 3 / 4).geometry.coordinates, offset / 100 / 1000, across).geometry.coordinates;

	}

	/**
	 * Tire le bord `index` d'un rang de plusieurs planches (getEdges()) : rend la fonction qui le pose d'après le
	 * pointeur, et rend la cote de l'allée voisine. Le bord d'en face ne bouge pas, et toutes les allées s'élargissent
	 * du même pas, comme MapCartography.getBeds() les répartit.
	 */
	pull(index) {

		this.laid = false;

		const before = this.points;
		const across = turf.bearing(before[0], before[1]) + ((index === 0) ? 180 : 0);
		const alley = MapDrawingLine.getAlley(this.beds);

		/* Le chemin du pointeur le long de la largeur du rang, en kilomètres, compté vers l'extérieur du bord tiré */
		const along = (from, to) => turf.distance(from, to) * Math.cos((turf.bearing(from, to) - across) * Math.PI / 180);

		const beds = this.beds.reduce((total, bed) => total + bed.width, 0);
		const fixed = (index === 0) ? before[1] : before[0];
		const placed = this.placed;

		return (to) => {

			const width = Math.max(beds / 100 / 1000, along(fixed, to));
			const {gap, ok} = MapDrawingLine.getGap(Math.max(0, (width * 100 * 1000 - beds) / (this.beds.length - 1)), alley);
			const size = {width: (beds + gap * (this.beds.length - 1)) / 100 / 1000, length: turf.distance(before[1], before[2])};

			if(index === 0) {
				const start = turf.destination(before[1], size.width, across).geometry.coordinates;
				this.points = [start, before[1], before[2]];
			} else {
				this.points = MapDrawingLine.getRow(before[0], across, turf.bearing(before[1], before[2]), size);
			}

			/* L'allée voisine du bord tiré, entre ses deux dernières planches de ce côté */
			let next = (index === 0) ? this.beds[0] : this.beds[this.beds.length - 1];

			if(this.placed !== null) {
				const spread = MapDrawingLine.spread(placed, this.beds, gap);
				this.placed = spread.placed;
				next = (index === 0) ? spread.first : spread.last;
			}

			return {at: this.getSayAt(index, -(next.width + gap / 2)), value: gap, ok: ok};

		};

	}

	getGeometry() {

		if(this.closed) {

			if(this.beds.length === 0) {
				return null;
			}

			return {type: 'MultiPolygon', coordinates: this.getRows().polygons.map(polygon => polygon.geometry.coordinates)};

		}

		if(this.points.length < 2) {
			return null;
		}

		return {type: 'LineString', coordinates: this.points};

	}

	/* Un rang se pose point par point. Posé, il se déplace et tourne d'un bloc, et ce qui s'y modifie, ce sont les allées
	   entre ses planches : un rang d'une seule planche n'a rien à modifier. Arrivé posé d'office, c'est d'abord sa place qu'on
	   règle : il ouvre sur « Déplacer », et ses allées viennent en dernier, dans l'ordre de PlanUi::getMapModes() */
	getModes() {

		if(this.isReady() === false) {
			return ['edit'];
		}

		return (this.beds.length === 1 || this.isParallel() === false) ? ['move', 'rotate'] : ['move', 'rotate', 'edit'];

	}

	/* Un rang sans planche ne montre rien : ni poignée ni outil ne se posent sur ce qui ne se voit pas. Un rang qu'on déplace
	   montre sa poignée en son milieu, qui n'est que le repère de toute sa surface, et un rang qu'on modifie les deux bords de
	   chaque allée, parallèles aux planches */
	getHandles(mode) {

		if(this.closed === false) {
			return this.points.map((point, index) => ({type: 'point', index: index, at: point}));
		}

		if(this.beds.length === 0) {
			return [];
		}

		switch(mode) {

			case 'move' :
				return [{type: 'point', index: 0, at: this.getPivot()}];

			case 'edit' : {
				/* Une poignée de bord au milieu de son bord serait à une demi-largeur de rang du milieu, qu'un rang long et étroit
				   ne laisse voir qu'à fort zoom (refreshEdges()) : elle se pose là où se pose la cote de son allée, aux trois quarts de
				   la plus courte planche */
				const shortest = Math.min(...this.beds.map(bed => bed.length)) / 1000;
				return this.getEdges().map((edge, index) => ({type: 'edge', look: 'middle', index: index, at: turf.along(turf.lineString(edge), shortest * 3 / 4).geometry.coordinates}));
			}

			case 'rotate' :
				return [];

		}

	}

	getTools() {

		if(this.isReady() === false) {
			return [];
		}

		return ['rotate', 'flip'];

	}

	/* Le nom de chaque planche, au quart de sa longueur : le milieu du rang est à la poignée, qui couvrirait les noms voisins.
	   La première et la dernière planche (`end`) disent le sens du rang : la carte écrit leur nom à tout cadrage */
	getLabels() {

		if(this.isReady() === false) {
			return [];
		}

		const rows = this.getRows();
		const last = rows.labels.length - 1;

		return rows.labels.map((label, position) => {

			const [p1, p2, p3, p4] = rows.polygons[position].geometry.coordinates[0];
			const at = [0, 1].map(axis => (p1[axis] + p2[axis]) * 0.375 + (p3[axis] + p4[axis]) * 0.125);

			return turf.point(at, (position === 0 || position === last) ? {...label.properties, end: true} : label.properties);

		});

	}

	/* Les planches du rang et leurs noms se calculent une fois par position : la carte les demande deux fois par image */
	getRows() {

		/* Des planches à créer changent de nom et de mesure sans changer de position dans le rang */
		const key = JSON.stringify(this.points) + JSON.stringify(this.beds) + JSON.stringify(this.placed);

		if(this.rowsKey !== key) {
			this.rows = (this.placed === null) ? MapCartography.getBeds(this.beds, this.points) : this.getRowsPlaced();
			this.rowsKey = key;
		}

		return this.rows;

	}

	/* Chaque planche à sa place dans le cadre, qui l'emporte quand il se déplace ou tourne */
	getRowsPlaced() {

		const across = turf.bearing(this.points[0], this.points[1]);
		const along = turf.bearing(this.points[1], this.points[2]);

		const beds = this.beds.map((bed, position) => {
			const place = this.placed[position];
			const corner = turf.destination(turf.destination(this.points[0], place.x, across).geometry.coordinates, place.y, along).geometry.coordinates;
			return MapCartography.getBed(bed, corner, (across + place.turn) * Math.PI / 180);
		});

		return {polygons: beds.map(bed => bed.polygon), labels: beds.map(bed => bed.label)};

	}

	isReady() {
		return (this.closed && this.beds.length > 0);
	}

	/* Un rang de planches à créer se cote sur ses propres bords, sa largeur sur son premier bord, sa longueur aux trois quarts
	   de son dernier bord, que sa poignée tient en son milieu */
	getSides() {

		if(this.create === false || this.isReady() === false) {
			return [];
		}

		const [start, end, far] = this.points;

		return [
			{from: start, to: end, at: turf.midpoint(start, end).geometry.coordinates, value: turf.distance(start, end, {units: 'meters'})},
			{from: end, to: far, at: turf.destination(end, turf.distance(end, far) * 0.75, turf.bearing(end, far)).geometry.coordinates, value: turf.distance(end, far, {units: 'meters'})}
		];

	}

	getSummary(labels) {

		return ((this.beds.length === 1) ? labels.bedsOne : labels.bedsMany).replace('{value}', this.beds.length);

	}

	/**
	 * La place d'une planche sous sa seule écriture (`map.yml`, `Draw`), comme DrawLib::getCanonical() : le coin d'où part la
	 * largeur au cap, la longueur à cap + 90°, et le cap ramené à [0°, 180°[, en radians. Une planche dont la longueur part de
	 * l'autre côté se lit depuis l'autre bout de sa largeur, et au-delà d'un demi-tour depuis le coin opposé.
	 */
	static getCanonical(polygon) {

		const [p1, p2, p3, p4] = polygon.geometry.coordinates[0];

		const width = turf.bearing(p1, p2);
		const clockwise = (Math.sin((turf.bearing(p2, p3) - width) * Math.PI / 180) > 0);

		let [corner, opposite, bearing] = clockwise ? [p1, p3, width] : [p2, p4, width + 180];

		bearing = (bearing % 360 + 360) % 360;

		if(bearing >= 180) {
			[corner, bearing] = [opposite, bearing - 180];
		}

		return {corner: corner, angle: bearing * Math.PI / 180};

	}

	/* Chaque planche part à sa place, son coin et son angle sous son id ; une planche à créer n'en a pas encore, sa position dans
	   le rang en tient lieu (getCreated()), et sa place part dans le seul champ que le formulaire du panneau lui réserve */
	getBody() {

		const corner = {};
		const angle = {};

		this.getRows().polygons.forEach((polygon, position) => {
			const canonical = MapDrawingLine.getCanonical(polygon);
			/* Un `point` se lit en JSON, comme le cadriciel le reçoit de tout formulaire */
			corner[this.beds[position].id] = JSON.stringify(canonical.corner);
			angle[this.beds[position].id] = canonical.angle;
		});

		if(this.create) {

			return {
				draw: JSON.stringify({corner: corner, angle: angle})
			};

		}

		return {
			plot: this.plot,
			ids: this.beds.map(bed => bed.id),
			corner: corner,
			angle: angle
		};

	}

}

/**
 * La copie d'un jardin à poser à côté de lui (plot:duplicate) : son contour et ses planches tracées de la saison, qui se
 * déplacent et tournent ensemble sans se retailler. Elle ne part pas en coordonnées mais en intention, un décalage en mètres
 * [est, nord] et un angle dans le sens horaire autour du milieu de la boite du contour, que le serveur applique au contour et aux
 * planches de toutes les saisons : transform() fait le calcul de MapLib::getTransformed(), pour que l'aperçu soit ce qui s'enregistre.
 */
class MapDrawingCopy {

	minimum = 0;

	points = [];
	closed = true;

	source;
	bedsDrawn = [];
	origin = null;
	shift = [0, 0];
	angle = 0;

	constructor(coordinates) {
		this.source = coordinates;
	}

	/* La copie se pose d'abord à droite de l'original, à l'écart que son lien porte (`drawingGap`, en mètres) de son bord est */
	begin(dataset) {

		this.bedsDrawn = JSON.parse(dataset.drawingBeds);
		this.origin = MapDrawingCopy.getOrigin(this.source);

		const longitudes = this.source.map(point => point[0]);
		const [east] = MapDrawingRectangle.toPlane(this.origin, [Math.max(...longitudes), this.origin[1]]);

		this.shift = [2 * east + parseFloat(dataset.drawingGap), 0];
		this.points = this.transform(this.source);

	}

	/* Le centimètre de MapSetting::MOTION_TOLERANCE, en mètres, sous lequel un sommet est à la place qu'un mouvement lui donne */
	static MOTION_TOLERANCE = 0.01;

	/* Le milieu de la boite d'un contour, comme MapLib::getOrigin() */
	static getOrigin(points) {

		const longitudes = points.map(point => point[0]);
		const latitudes = points.map(point => point[1]);

		return [(Math.min(...longitudes) + Math.max(...longitudes)) / 2, (Math.min(...latitudes) + Math.max(...latitudes)) / 2];

	}

	/* Des points tournés de `angle` degrés dans le sens horaire autour de `origin`, puis décalés de `shift` mètres, dans le plan
	   métrique de `origin`, comme MapLib::getTransformed() */
	static getTransformed(points, origin, shift, angle) {

		const cos = Math.cos(angle * Math.PI / 180);
		const sin = Math.sin(angle * Math.PI / 180);

		return points.map(point => {
			const [x, y] = MapDrawingRectangle.toPlane(origin, point);
			return MapDrawingRectangle.fromPlane(origin, [x * cos + y * sin + shift[0], - x * sin + y * cos + shift[1]]);
		});

	}

	/* Des points tournés de `angle` degrés dans le sens horaire autour de `center`, dans le plan métrique de `origin` : une suite de
	   rotations et de glissers y reste un seul mouvement rigide, que getMotion() retrouve */
	static getRotated(points, origin, center, angle) {

		const cos = Math.cos(angle * Math.PI / 180);
		const sin = Math.sin(angle * Math.PI / 180);
		const [centerX, centerY] = MapDrawingRectangle.toPlane(origin, center);

		return points.map(point => {
			const [x, y] = MapDrawingRectangle.toPlane(origin, point);
			const [dx, dy] = [x - centerX, y - centerY];
			return MapDrawingRectangle.fromPlane(origin, [centerX + dx * cos + dy * sin, centerY - dx * sin + dy * cos]);
		});

	}

	/* Le mouvement rigide qui porte les sommets `before` sur `after`, comme MapLib::getMotion() : null quand un sommet a bougé seul,
	   que leur nombre a changé, ou que rien n'a bougé */
	static getMotion(before, after) {

		if(before.length === 0 || before.length !== after.length) {
			return null;
		}

		const origin = MapDrawingCopy.getOrigin(before);
		const plane = point => MapDrawingRectangle.toPlane(origin, point);
		const gap = (from, to) => Math.hypot(plane(to)[0] - plane(from)[0], plane(to)[1] - plane(from)[1]);

		const distances = before.map(point => gap(before[0], point));
		const farthest = distances.indexOf(Math.max(...distances));

		// Un contour réduit à un point n'a pas d'angle
		if(farthest === 0) {
			return null;
		}

		const heading = (from, to) => Math.atan2(plane(to)[1] - plane(from)[1], plane(to)[0] - plane(from)[0]);

		// getTransformed() tourne dans le sens horaire
		const degrees = (heading(before[0], before[farthest]) - heading(after[0], after[farthest])) * 180 / Math.PI;
		// Une rotation qui ne déplace aucun sommet du centimètre n'en est pas une, comme dans MapLib::getMotion()
		const turn = ((degrees % 360) + 540) % 360 - 180;
		const angle = (Math.abs(turn) * Math.PI / 180 * Math.max(...distances) <= MapDrawingCopy.MOTION_TOLERANCE) ? 0 : turn;

		const [turned] = MapDrawingCopy.getTransformed([before[0]], origin, [0, 0], angle);
		const shift = [plane(after[0])[0] - plane(turned)[0], plane(after[0])[1] - plane(turned)[1]];

		const moved = MapDrawingCopy.getTransformed(before, origin, shift, angle);

		if(moved.some((point, index) => gap(point, after[index]) > MapDrawingCopy.MOTION_TOLERANCE)) {
			return null;
		}

		if(before.every((point, index) => gap(point, after[index]) <= MapDrawingCopy.MOTION_TOLERANCE)) {
			return null;
		}

		return {origin, shift, angle};

	}

	transform(points) {
		return MapDrawingCopy.getTransformed(points, this.origin, this.shift, this.angle);
	}

	add(point) {
	}

	move(index, point) {
		this.moveTo(point);
	}

	/* La copie tirée d'un bloc : le milieu de sa boite vient au point donné */
	moveTo(point) {
		this.shift = MapDrawingRectangle.toPlane(this.origin, point);
		this.points = this.transform(this.source);
	}

	remove(index) {
	}

	close() {
	}

	/* « Rotation » tourne la copie autour du milieu de sa boite, l'angle restant dans le tour que le serveur accepte */
	tool(name, from, pivot) {

		const angle = this.angle;
		const bearing = (point) => Math.atan2(point.x - pivot.x, pivot.y - point.y) * 180 / Math.PI;

		return (to) => {

			this.angle = ((angle + Math.round(bearing(to) - bearing(from))) % 360 + 360) % 360;
			this.points = this.transform(this.source);

			return this.angle +'°';

		};

	}

	getPivot() {
		return MapDrawingRectangle.fromPlane(this.origin, this.shift);
	}

	/* Les planches de la copie, leur coin transformé et leur angle tourné comme DrawLib::duplicateByPlot() les enregistre */
	getBeds() {
		return this.bedsDrawn.map(bedDrawn => MapCartography.getBed(bedDrawn.bed, this.transform([bedDrawn.corner])[0], bedDrawn.angle + this.angle * Math.PI / 180));
	}

	getGeometry() {
		return {type: 'MultiPolygon', coordinates: [[[...this.points, this.points[0]]], ...this.getBeds().map(bed => bed.polygon.geometry.coordinates)]};
	}

	/* Une copie ne se règle pas : elle se pose, déplacée et tournée d'un bloc */
	getModes() {
		return ['move', 'rotate'];
	}

	getHandles() {
		return [];
	}

	getTools() {
		return ['rotate'];
	}

	getLabels() {
		return this.getBeds().map(bed => bed.label);
	}

	isReady() {
		return true;
	}

	getSummary(labels) {
		return '';
	}

	/* Les deux contours, l'original et sa copie, que la carte cadre ensemble à l'ouverture */
	getFrame() {
		return [...this.source, ...this.points];
	}

	getBody() {
		return {
			placement: JSON.stringify({shift: this.shift, angle: this.angle})
		};
	}

}

/**
 * Le mode de tracé d'une carte : une séance ouverte par un lien `[data-drawing]`, qui pose une forme sur la
 * carte avec les outils de l'éditeur de décor de la vue 3D et n'écrit rien avant « Terminer ».
 *
 * Il tient la séance, les poignées, la fiche, le clavier et l'envoi. Tout ce qui tient à la géométrie est
 * dans la forme (`MapDrawing.shapes`), et ce qu'une forme enregistrée change sur la carte dans sa cible
 * (`MapDrawing.targets`) : une forme ou une cible de plus s'ajoute à ces deux tables, sans toucher au mode.
 */
class MapDrawing {

	static current = null;

	static shapes = {
		polygon: MapDrawingPolygon,
		rectangle: MapDrawingRectangle,
		line: MapDrawingLine,
		copy: MapDrawingCopy
	};

	static targets = {
		zone: (carto, json) => carto.addZone(json.zone, json.name, json.coordinates).loadZone(json.zone),
		plot: (carto, json) => carto.addPlot(json.plot, json.name, json.zone, json.coordinates, true, json.mode, json.undrawn).refreshSelection(),
		plotCreated: (carto, json) => carto.addPlot(json.plot, json.name, json.zone, json.coordinates, true, json.mode, json.undrawn).selectPlot(json.plot),
		beds: (carto, json) => {
			carto.removeBedsById(json.plot, json.beds);
			json.bedsDrawn.forEach(bedDrawn => carto.addBed(json.plot, bedDrawn.id, bedDrawn.bed, bedDrawn.corner, bedDrawn.angle));
		}
	};

	/* Un pointeur qui bouge de moins que cela a cliqué, il n'a pas tiré */
	static CLICK = 4;

	/* La croix du point choisi : son écart au point sur chaque axe, et la moitié de sa cible de 44 px */
	static UNPOINT_GAP = 30;
	static UNPOINT_HALF = 22;

	/* L'écart entre une fiche posée sur la carte et le contour au-dessus duquel elle se tient */
	static CARD_GAP = 12;

	/* Une fiche posée garde ses boutons : leur clic remonte jusqu'aux écouteurs délégués du document */
	static CARD_EVENTS = ['mousedown', 'touchstart', 'dblclick'];

	/* La cible d'une poignée */
	static HANDLE = 44;

	/* Un côté de forme rigide qui passe à moins de ces pixels d'un côté voisin, et de ces degrés de son angle, en prend l'angle
	   et s'y colle */
	static SNAP_PIXELS = 12;
	static SNAP_DEGREES = 5;

	/* Le côté à l'écran sous lequel une cote passerait sous les poignées de ses deux coins, chacune à une demi-cible du coin */
	static SIDES_MINIMUM = 2 * MapDrawing.HANDLE;

	/* L'écart d'une fiche calée contre un bord de la carte, celui de Mapbox autour de ses boutons */
	static VIEW_MARGIN = 10;

	trigger;
	carto;
	map;
	shape;
	before;

	view;
	card;
	level = null;
	levelsBefore = [];
	creating = false;
	cardSource;
	cardPosed = null;
	cardMarker = null;
	cardObserver = null;
	cardPlace = null;

	handles = [];
	bar = null;
	/* La fiche d'une forme qu'on crée tient dans la rangée du tracé, en haut de la carte (placeCard()) */
	cardBar = false;
	issues = null;
	unpoint = null;
	say = null;
	sides = [];

	selected = null;
	drag = null;
	dragged = false;
	changed = false;
	width = 0;
	cardWidth = 0;
	cardHeight = 0;
	height = 0;
	cardTop = null;

	/* Ce que fait la séance de la forme : en tirer les poignées (`edit`), la tirer d'un bloc (`move`) ou la tourner (`rotate`),
	   parmi les modes qu'elle a (getModes()) */
	mode = 'edit';
	/* Le nombre de modes de la forme au dernier refresh() : une forme qui en gagne rouvre sur le premier */
	modesCount = null;

	/* Les outils et les modes que montre la rangée du tracé, et ce qu'elle couvre de la carte, mesuré quand ils changent */
	barNames = null;
	barBox = null;

	/* Les parcelles PAC du dernier carré lu (`box`, ouest, sud, est, nord), et l'année de leur déclaration. `pacYear` est celle
	   dont vient le contour : la retouche la garde, « Rectangle » et « Recommencer » l'effacent. `pacLoading` est la lecture en
	   cours, `pacFailed` dit que la dernière a échoué, `pacPointer` est le dernier point survolé */
	pac = {box: null, parcels: [], crops: new Map(), year: null};
	pacYear = null;
	pacLoading = null;
	pacFailed = false;
	pacPicking = false;
	pacPointer = null;
	pacHover = null;
	pacShown = null;

	listeners;

	constructor(trigger) {

		this.trigger = trigger;
		this.carto = MapCartography.get(trigger.dataset.drawingMap);
		this.map = this.carto.mapbox;

		this.shape = new MapDrawing.shapes[trigger.dataset.drawing](JSON.parse(trigger.dataset.drawingCoordinates));
		this.before = this.getSnapshot();
		this.typed = false;
		this.refused = false;
		this.lengthAlong = true;

		this.view = qs('#'+ trigger.dataset.drawingMap).parentElement;
		/* Chaque geste a sa fiche, que son lien nomme quand la forme ne suffit pas : le contour d'une parcelle, celui d'un jardin, le rang
		   d'un jardin. placeCard() la met en place */
		this.cardSource = this.view.qs('[data-drawing-card="'+ (trigger.dataset.drawingSheet ?? trigger.dataset.drawing) +'"]');
		this.card = this.cardSource;

		/* Une forme qu'on crée se nomme dans le bloc, dont le niveau attend caché le temps du geste */
		if(trigger.dataset.drawingLevel !== undefined) {
			this.level = qs('#'+ trigger.dataset.drawingLevel);
		}

		/* Les boutons d'une fiche posée laissent passer leur clic, que Mapbox sert aussi à la carte : il n'y pose pas de point */
		this.listeners = {
			click: (e) => {
				if(this.carto.isMarkerClick(e) === false) {
					this.clickMap(e);
				}
			},
			dblclick: () => this.close(),
			mousemove: (e) => this.moveMap(e),
			mousedown: (e) => this.startBody(e, e.originalEvent),
			touchstart: (e) => {
				// Deux doigts zooment la carte, ils ne prennent pas la forme
				if(e.originalEvent.touches.length === 1) {
					this.startBody(e, {button: 0, clientX: e.originalEvent.touches[0].clientX, clientY: e.originalEvent.touches[0].clientY, preventDefault: () => e.originalEvent.preventDefault()});
				}
			},
			move: () => {
				this.refreshAround();
				this.readPac(this.map.getCenter().toArray());
			},
			resize: () => this.resize()
		};

	}

	static start(trigger) {

		if(MapDrawing.current !== null) {
			return;
		}

		/* Le bloc se rend avant la carte, que Mapbox charge ensuite : un geste lancé entre les deux n'a rien où se poser */
		if((MapCartography.get(trigger.dataset.drawingMap)?.mapbox ?? null) === null) {
			return;
		}

		MapDrawing.current = new MapDrawing(trigger);
		MapDrawing.current.open();

	}

	open() {

		this.carto.locked = true;

		if(this.trigger.dataset.drawingMask !== undefined) {
			this.carto.setMask(this.trigger.dataset.drawingMask.split(' '), parseInt(this.trigger.dataset.drawingId));
		}

		this.carto.refreshSelection();

		this.beginShape();

		/* Une forme qu'on crée se nomme dans un bloc, et un rang de planches se crée quand il les décrit ou qu'aucune n'a encore
		   sa place. Les cases cochées pendant le geste n'en font pas une modification */
		this.creating = (this.level !== null || (this.shape instanceof MapDrawingLine && (this.shape.create || this.shape.beds.every(bed => bed.corner === null))));

		/* Des planches tracées rouvertes à leur place n'ont rien à enregistrer tant qu'on n'y touche pas */
		if(this.shape instanceof MapDrawingLine && this.shape.placed !== null) {
			this.before = this.getSnapshot();
		}

		this.maskBeds();

		if(this.level !== null) {
			// Comme MapPlan.showLevel() : le menu d'où part le geste vise un niveau qui s'efface
			qsa('.plan-mapview [data-dropdown-display]', button => Lime.Dropdown.close(button));
			this.levelsBefore = [...this.level.parentElement.qsa('.plan-console-level:not(.hide)')];
			this.levelsBefore.forEach(level => level.classList.add('hide'));
			this.level.classList.remove('hide');
		}

		this.map.doubleClickZoom.disable();
		Object.entries(this.listeners).forEach(([type, listener]) => this.map.on(type, listener));

		this.view.classList.add('is-drawing');

		this.placeCard();

		/* La seule lecture de la mise en page du geste, une fois la fiche à sa place et avant tout ce qui se pose sur la carte */
		this.measure();

		const kit = this.view.qs('[data-drawing-kit="'+ this.trigger.dataset.drawing +'"]');

		/* La rangée du tracé tient le haut de la carte, hors de ses marqueurs : plan.css la place, le geste n'en montre que les cases */
		this.bar = kit.qs('[data-drawing-tools]').cloneNode(true);
		this.view.append(this.bar);
		this.placeBar();

		/* Les issues d'une forme qu'on crée sont sous son nom : la rangée ne garde que les outils */
		if(this.issues !== null) {
			this.bar.qsa('[data-drawing-action]', node => node.remove());
		}

		this.unpoint = this.pose(kit.qs('[data-drawing-unpoint]').cloneNode(true));
		this.say = this.pose(MapDrawing.getNode('util-maptool-say'));

		this.selectMode(this.shape.getModes()[0]);
		this.refresh(true);

		if(this.carto.drawingHandler !== null) {
			this.carto.drawingHandler(false);
		}

		/* Sous la carte d'un téléphone, la fiche du geste peut s'ouvrir sous la barre du bas : la page descend de ce qu'il
		   faut pour la montrer, avec la marge que plan.css lui donne, issues d'une modification comprises. Déjà en vue, rien ne bouge. */
		this.card.scrollIntoView({block: 'nearest'});

	}

	/* La forme lui donne ses voisines avant de se poser, pour qu'un rang posé d'office ne tombe pas sur elles. Les dimensions du
	   formulaire ne se saisissent que pour une forme qui sait les prendre (setDimensions()) : un contour d'abri qui n'est pas un
	   rectangle les écrit sans les laisser saisir, jusqu'à ce qu'un rectangle le remplace */
	beginShape() {

		if(this.shape instanceof MapDrawingLine) {
			this.shape.neighbours = () => this.getNeighbours().map(feature => feature.geometry);
		}

		this.shape.begin(this.trigger.dataset, this.getVisible());

		this.getScope()?.qsa('[data-drawing-dimension]', node => node.readOnly = (this.shape.setDimensions === undefined));

	}

	/* Ce qu'un geste enregistre : les points de la forme, et pour des planches tracées la place de chacune dans leur cadre, que
	   l'étalement des allées change sans toujours changer le cadre */
	getSnapshot() {
		return JSON.stringify([this.getDrawn().points, this.shape.placed ?? null]);
	}

	/* La forme que le geste enregistrerait, sur laquelle se lisent la place de la fiche, la surface qu'elle annonce et ce qui change :
	   un contour confié à un rectangle pas encore commencé, qui reste à l'écran, l'est tant qu'aucun rectangle ne le remplace
	   (MapDrawingRectangle.getConfided()) */
	getDrawn() {
		return this.shape.getConfided?.() ?? this.shape;
	}

	/* Le contour est-il encore celui qu'on a ouvert : il n'y a alors rien à enregistrer, et sa surface reste celle qui a été saisie */
	isSame() {
		return (this.getSnapshot() === this.before);
	}

	/* Une fiche posée se tient au-dessus du contour que son lien nomme (`drawingAnchor`), sinon au-dessus de la forme qu'on crée,
	   qu'elle suit. Tant que cette forme n'a pas de point, elle se tient en haut de la carte, sous la marge que sa hauteur lui donne */
	getCardAt() {

		if(this.trigger.dataset.drawingAnchor !== undefined) {
			return MapDrawing.getTop(JSON.parse(this.trigger.dataset.drawingAnchor));
		}

		const points = this.getDrawn().points;

		if(points.length > 0) {
			return MapDrawing.getTop(points);
		}

		/* Son écart n'est pas encore lu à l'ouverture, où la fiche se pose au centre pour être mesurée */
		if(this.cardTop === null) {
			return this.map.getCenter().toArray();
		}

		return this.map.unproject([this.width / 2, this.cardTop]).toArray();

	}

	/* Ce que la carte laisse voir, en deux coins : un geste ne la déplace jamais, ce qu'il pose neuf se pose dans la vue. L'encart que
	   la page mesure retire le bloc et les boutons posés sur la carte */
	getVisible() {

		const inset = this.carto.inset ?? null;

		if(inset === null) {
			return this.map.getBounds().toArray();
		}

		return this.getBounds([[inset.left, inset.top], [inset.right, inset.top], [inset.right, inset.bottom], [inset.left, inset.bottom]].map(point => this.map.unproject(point).toArray()));

	}

	getBounds(points = this.shape.points) {

		const longitudes = points.map(point => point[0]);
		const latitudes = points.map(point => point[1]);

		return [[Math.min(...longitudes), Math.min(...latitudes)], [Math.max(...longitudes), Math.max(...latitudes)]];

	}

	/* Rend la carte et la fiche à ce qu'elles étaient. Un tracé enregistré ne remet pas la forme d'avant, que sa cible redessine. */
	shut(saved) {

		MapDrawing.current = null;

		Object.entries(this.listeners).forEach(([type, listener]) => this.map.off(type, listener));
		this.map.doubleClickZoom.enable();
		this.map.getCanvas().style.cursor = '';

		[...this.handles.map(handle => handle.marker), ...this.sides, this.bar, this.unpoint, this.say, this.cardMarker, this.issues].forEach(marker => marker?.remove());
		this.cardSource.qs('.plan-mapview-actions-issues').classList.remove('hide');
		this.cardObserver?.disconnect();

		this.carto.setFeature('drawing', 'shape', null);
		this.carto.setFeature('drawing', 'labels', null);
		this.carto.setFeature('drawing', 'ghost', null);
		this.carto.setFeature('pac', 'parcels', null);
		this.carto.setFeature('pac', 'hover', null);
		this.carto.clearMask(saved === false);
		this.carto.locked = false;
		this.carto.refreshSelection();

		this.view.classList.remove('is-drawing');
		this.card.classList.add('hide');
		Lime.Alert.hideErrors(this.card);

		/* Le refus d'une forme qui n'a pas de champ où se poser tombe au bandeau de la page (finish()), qui ne part sinon qu'à sa croix
		   ou à une navigation : comme à la fermeture d'un panneau, il ne survit pas au geste qu'il refusait */
		Lime.Alert.hideStaticErrors();

		/* Un niveau que la réponse a remplacé n'est plus dans la page : le rendre ne change rien */
		if(this.level !== null) {
			this.level.classList.add('hide');
			Lime.Alert.hideErrors(this.level);
			this.levelsBefore.forEach(level => level.classList.remove('hide'));
		}

		if(this.carto.drawingHandler !== null) {
			this.carto.drawingHandler(true, saved);
		}

	}

	/* Au-dessus de 767 px, une fiche qui se pose sur la carte s'y pose en copie, où arrivent ses messages, et part avec son marqueur.
	   En dessous, la carte n'a pas la hauteur de la porter au-dessus de la forme : elle reste sous la carte, et les issues quittent la
	   rangée pour elle (getMapIssues()). Une forme qu'on crée sous le nom saisi dans le bloc les prend sous ce nom, à toute largeur :
	   une copie, que la réponse peut emporter avec le niveau sans rien retirer à la fiche. Cette forme n'a aucun contour à désigner :
	   sa fiche ne la suit pas, elle tient le haut de la carte dans la rangée du tracé (`cardBar`). La largeur peut franchir le seuil
	   pendant le geste, et la fiche change alors de place : rend vrai quand elle a bougé */
	placeCard() {

		const posed = (this.cardSource.dataset.drawingPose !== undefined && window.innerWidth > 767);
		const form = (this.level !== null) ? this.level.qs('form') : null;

		if(posed === this.cardPosed && (form !== null) === (this.issues !== null)) {
			return false;
		}

		if(this.cardBar) {
			this.card.remove();
			this.cardBar = false;
		} else if(this.cardPosed) {
			this.cardMarker.remove();
			this.cardMarker = null;
			this.cardObserver.disconnect();
			this.cardObserver = null;
		} else if(this.cardPosed === false) {
			this.cardSource.classList.add('hide');
		}

		this.issues?.remove();
		this.issues = null;
		this.cardSource.qs('.plan-mapview-actions-issues').classList.remove('hide');

		this.cardPosed = posed;
		this.card = posed ? this.cardSource.cloneNode(true) : this.cardSource;
		this.card.classList.remove('hide');

		if(posed && form !== null) {

			this.cardBar = true;

		/* Une fiche posée prend la place des actions qui ont ouvert le geste, au-dessus du contour qu'elles tenaient */
		} else if(posed) {

			this.cardMarker = this.pose(this.card, {anchor: 'bottom', offset: [0, -MapDrawing.CARD_GAP]}, MapDrawing.CARD_EVENTS);
			this.show(this.cardMarker, this.getCardAt());

			/* La fiche grandit après sa mesure, quand sa surface ou son message s'écrit : sa taille se tient à jour sans relire le
			   document, pour qu'elle reste dans la carte à chaque mouvement */
			this.cardObserver = new ResizeObserver(entries => {

				const box = entries[0].borderBoxSize[0];

				this.cardWidth = box.inlineSize;
				this.cardHeight = box.blockSize;

				this.refreshCard();

			});

			this.cardObserver.observe(this.card);

		}

		if(form !== null) {
			const issues = this.cardSource.qs('.plan-mapview-actions-issues');
			this.issues = issues.cloneNode(true);
			this.issues.classList.remove('hide-md-up');
			this.issues.classList.add('plan-console-buttons');
			form.append(this.issues);
			issues.classList.add('hide');
		}

		this.placeBar();

		this.refreshName();

		return true;

	}

	/* La fiche d'une forme qu'on crée entre en tête de la rangée, que plan.css ancre alors à droite du bloc. Avant open(), qui clone la
	   rangée, il n'y a rien à placer */
	placeBar() {

		if(this.bar === null) {
			return;
		}

		this.bar.classList.toggle('plan-mapview-bar-card', this.cardBar);

		if(this.cardBar) {
			this.bar.prepend(this.card);
		}

	}

	/* La fiche se replace avant la mesure, qui lit la place qu'elle prend, et la forme se redessine ensuite autour d'elle */
	resize() {

		const moved = this.placeCard();

		this.measure();

		if(moved) {
			this.refresh();
		}

	}

	/* La hauteur de la carte et la place que la fiche y prend ne se lisent qu'ici, hors de tout rendu */
	measure() {

		const container = this.map.getContainer().getBoundingClientRect();
		const card = this.card.getBoundingClientRect();

		/* La fiche du geste couvre le bas de la carte, où rien ne se pose, ou se tient au-dessus de la forme, à son écart du haut de la
		   carte tant que la forme n'a pas de point */
		if(this.cardBar) {
			this.cardTop = 0;
			this.height = container.height;
		} else if(this.cardMarker === null) {
			this.cardTop = 0;
			this.height = container.height - Math.max(0, container.bottom - card.top);
		} else {
			this.cardTop = card.height + this.getCardSayReserve() + 2 * MapDrawing.CARD_GAP;
			this.height = container.height;
		}

		this.width = container.width;

		this.cardWidth = card.width;
		this.cardHeight = card.height;

		/* Le geste se mesure avant de poser sa rangée, puis à chaque taille de la carte, où plan.css la déplace */
		if(this.bar !== null) {
			this.measureBar();
		}

	}

	/* Le message d'une fiche posée n'a sa ligne qu'une fois écrit, après le cadrage : la carte la réserve d'avance, pour que la fiche grandie ne sorte pas par le haut */
	getCardSayReserve() {

		const say = this.card.qs('.plan-mapview-actions-say');

		if(say === null || say.offsetHeight > 0) {
			return 0;
		}

		const style = getComputedStyle(this.card);

		return parseFloat(getComputedStyle(say).lineHeight) + parseFloat(style.rowGap);

	}

	/* Le haut d'un contour, au milieu de sa largeur : une fiche s'y pose au-dessus de la forme entière, pas d'un de ses coins */
	static getTop(ring) {

		const longitudes = ring.map(point => point[0]);

		return [(Math.min(...longitudes) + Math.max(...longitudes)) / 2, Math.max(...ring.map(point => point[1]))];

	}

	/* Où se pose une rangée de la taille donnée au-dessus d'une forme, ou au-dessous (`below`) : à sa place quand elle tient dans
	   ce que la carte laisse voir (`inset`), sinon contre le bord que la forme a passé, au milieu de ce qu'il en reste à l'écran.
	   Contre le haut, elle laisse la colonne des boutons de la carte. Rend le haut et le milieu de la rangée, en pixels de la carte,
	   sans rien lire du document : la taille et l'encart sont mesurés d'avance, pour que chaque mouvement de la carte n'écrive qu'une position */
	static placeInView(map, ring, size, inset, gap, below = false) {

		const points = ring.map(point => map.project(point));
		const xs = points.map(point => point.x);
		const ys = points.map(point => point.y);
		const clamp = (value, low, high) => Math.max(low, Math.min(Math.max(low, high), value));

		const y = below ?
			clamp(Math.max(...ys) + gap, inset.top, inset.bottom - size.height) :
			clamp(Math.min(...ys) - gap - size.height, inset.top, inset.bottom - size.height);

		const right = (inset.controls !== null && y < inset.controls.bottom) ? inset.controls.left : inset.right;
		const west = Math.max(Math.min(...xs), inset.left);
		const east = Math.min(Math.max(...xs), right);

		/* Une forme sortie par un côté laisse la rangée contre ce côté */
		const middle = (west <= east) ? (west + east) / 2 : ((Math.min(...xs) > right) ? right : inset.left);

		return {
			x: clamp(middle, inset.left + size.width / 2, right - size.width / 2),
			y: y
		};

	}

	/* Ce que la carte laisse voir pour y caler une fiche : l'encart que la page a mesuré, sinon la carte entière, borné en bas
	   par la fiche du geste quand elle couvre le bas de la carte. La rangée du tracé prolonge vers la gauche la colonne des boutons
	   de la carte, en haut : une fiche qui monte jusqu'à elles s'arrête à leur gauche */
	getInset() {

		const margin = MapDrawing.VIEW_MARGIN;
		const inset = this.carto.inset ?? {left: margin, top: margin, right: this.width - margin, bottom: this.height - margin, controls: null};

		let controls = inset.controls;

		if(this.barBox !== null) {
			controls = (controls === null) ?
				{left: this.barBox.left, bottom: this.barBox.bottom} :
				{left: Math.min(controls.left, this.barBox.left), bottom: Math.max(controls.bottom, this.barBox.bottom)};
		}

		return {...inset, bottom: Math.min(inset.bottom, this.height - margin), controls: controls};

	}

	static getNode(className, attributes = {}) {

		const node = document.createElement('div');
		node.className = className;

		Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));

		return node;

	}

	/* Un marqueur qui ne doit rien à la carte : ce qu'on y fait ne la déplace pas, n'y pose pas de point et ne
	   lève pas le rappel des deux doigts. Les événements de pointeur, eux, remontent jusqu'aux écouteurs du mode. */
	pose(node, options = {}, events = ['mousedown', 'touchstart', 'click', 'dblclick']) {

		events.forEach(type => node.addEventListener(type, e => e.stopPropagation()));

		node.classList.add('hide');

		return new mapboxgl.Marker({element: node, ...options}).setLngLat([0, 0]).addTo(this.map);

	}

	show(marker, at) {

		marker.getElement().classList.toggle('hide', at === null);

		if(at !== null) {
			marker.setLngLat(at);
		}

	}

	/* Tout ce que la séance montre, réécrit d'un coup et sans rien lire du document : les positions viennent de la carte */
	refresh(rebuild = false) {

		/* Une forme qui change perd parfois le mode en cours, un rang retombé à une planche n'ayant plus rien à modifier. Une forme
		   qui gagne ses modes, un contour refermé ou un rang qui reçoit ses planches, ouvre sur le premier, comme à l'ouverture.
		   Une forme à tracer, un rectangle recommencé compris, se trace sur le premier */
		const modes = this.shape.getModes();

		if(modes.includes(this.mode) === false || (this.modesCount === 1 && modes.length > 1) || (this.shape.closed === false && this.mode !== modes[0])) {
			this.selectMode(modes[0]);
		}

		this.modesCount = modes.length;

		const handles = this.shape.getHandles(this.mode);
		const geometry = this.shape.getGeometry();

		this.carto.setFeature('drawing', 'shape', (geometry === null) ? null : {type: 'Feature', properties: {}, geometry: geometry});
		this.carto.setFeature('drawing', 'labels', this.shape.getLabels());

		/* Ce que porte un contour le suit tant qu'il n'est que tiré d'un bloc ou tourné, comme l'enregistrement le déplace : les
		   planches d'un jardin, les jardins d'une parcelle avec leurs planches */
		if(this.shape.getMotion !== undefined) {

			const motion = this.shape.getMotion();

			if(this.trigger.dataset.drawingTarget === 'plot') {
				this.carto.moveBeds(this.trigger.dataset.drawingId, motion);
			} else if(this.trigger.dataset.drawingTarget === 'zone') {
				this.carto.moveZone(this.trigger.dataset.drawingId, motion);
			}

		}

		if(this.shape.closed || this.shape.points.length === 0) {
			this.carto.setFeature('drawing', 'ghost', null);
		}

		if(rebuild || handles.length !== this.handles.length) {

			this.handles.forEach(handle => handle.marker.remove());

			this.handles = handles.map(handle => ({
				...handle,
				/* La poignée prend l'allure de ce qu'elle tient (`look`), et le geste qu'elle commence se lit sur son type */
				marker: this.pose(MapDrawing.getNode('util-maptool-handle', {'data-handle': handle.look ?? handle.type, 'data-drag': handle.type, 'data-index': handle.index}))
			}));

			this.handles.forEach(handle => handle.marker.getElement().appendChild(document.createElement('span')));

		}

		this.handles.forEach((handle, position) => {
			this.show(handle.marker, handles[position].at);
			handle.marker.getElement().classList.toggle('selected', (handle.type === 'point' && handle.index === this.selected) || (handle.type === 'edge' && this.drag?.type === 'edge' && this.drag.index === handle.index));
			if(handle.type === 'edge') {
				handle.marker.getElement().style.cursor = this.getEdgeCursor(handle.index);
			}
		});

		this.refreshTools();
		this.refreshAround();
		this.refreshRefusal();
		this.refreshPac();

		this.map.getCanvas().style.cursor = this.shape.closed ? '' : 'crosshair';

		this.refreshDimensions();
		this.refreshIssues();

	}

	/* Le formulaire qui part avec la forme : celui du bloc d'une forme qu'on crée, aucun pour une forme qu'on modifie */
	getForm() {
		return this.level?.qs('form') ?? null;
	}

	/* Ce que la forme lit et écrit autour d'elle : le formulaire du geste, le bloc de son tracé dans un panneau (MapDrawingEmbedded) */
	getScope() {
		return this.getForm();
	}

	/* Une forme remise à plus tard ne part pas : seul un panneau le propose (MapDrawingEmbedded) */
	isLater() {
		return false;
	}

	/* Les dimensions d'une forme qui en porte (`drawingDimensions`) se lisent sur elle dans les champs marqués
	   `data-drawing-dimension="length"` et `"width"`, quel que soit leur nom, qui les saisissent aussi (changeDimensions()). La
	   forme n'y écrit que tracée et partante, au pas du champ, et seulement depuis un geste sur la carte, qui la tire ou lui
	   applique un outil (moveDrag(), useTool()) : elle prend son grand côté pour longueur, et une saisie réécrite échangerait les
	   deux champs sous la main, « 2 » en route vers « 20 » ou une longueur tapée plus courte que la largeur d'avant. L'envoi les remet d'aplomb, le serveur mesurant le contour */
	refreshDimensions() {

		const scope = this.getScope();

		if(scope === null || this.typed || this.trigger.dataset.drawingDimensions === undefined || this.shape.getDimensions === undefined || this.shape.isReady() === false || this.isLater()) {
			return;
		}

		const [length, width] = this.shape.getDimensions();

		scope.qsa('[data-drawing-dimension]', node => {

			const value = (node.dataset.drawingDimension === 'length') ? length : width;
			const step = parseFloat(node.step);

			node.value = String(isNaN(step) ? value : parseFloat((Math.round(value / step) * step).toFixed((node.step.split('.')[1] ?? '').length)));

		});

	}

	/* Saisies dans le formulaire du geste, les deux dimensions redimensionnent la forme, ou la posent si elle n'est pas encore tracée.
	   Le côté de la longueur se prend au début de la saisie et s'y tient jusqu'au geste suivant : relu à chaque frappe, il passerait
	   à l'autre côté dès qu'une valeur en route est plus courte que la largeur, « 1 » en route vers « 15 », et la forme tournerait
	   d'un quart de tour */
	changeDimensions(scope) {

		if(scope !== this.getScope() || this.trigger.dataset.drawingDimensions === undefined || this.shape.setDimensions === undefined || this.isLater()) {
			return;
		}

		if(this.typed === false) {
			this.lengthAlong = this.shape.isLengthAlong();
			this.typed = true;
		}

		const [length, width] = ['length', 'width'].map(dimension => parseFloat(scope.qs('[data-drawing-dimension="'+ dimension +'"]').value));

		if(isNaN(length) || isNaN(width)) {
			return;
		}

		this.shape.setDimensions(length, width, this.getVisible(), this.lengthAlong);

		this.refresh(true);

	}

	/* Ce que la fiche et le bloc disent de la forme, et le nom de ses boutons « Enregistrer » */
	refreshIssues() {

		/* Un contour rouvert tel quel garde la surface saisie à la main, que le lien porte : une forme sans surface saisie dit toujours la sienne */
		let summary = (this.trigger.dataset.drawingArea !== undefined && this.getDrawn().points.length > 0 && this.isSame()) ? this.trigger.dataset.drawingArea : this.shape.getSummary(this.card.dataset);

		if(summary !== '' && this.pacYear !== null) {
			summary += ' · '+ this.card.dataset.pacYear.replace('{value}', this.pacYear);
		}

		[this.card, this.level].forEach(node => node?.qsa('[data-drawing-summary]', summaryNode => summaryNode.textContent = summary));

		/* « Enregistrer » prend le nom du geste qui crée */
		const finish = this.card.dataset.finish ?? null;

		this.getFinishButtons().forEach(button => {

			button.dataset.label ??= button.qs('[data-drawing-finish]').textContent;
			button.qs('[data-drawing-finish]').textContent = finish ?? button.dataset.label;

		});

	}

	/* Les cotes d'une forme qui dit ses côtés, chacune au milieu du sien, au décimètre. Un côté trop court à l'écran
	   pour porter la sienne entre ses deux poignées n'en montre pas : la surface reste dans le bloc */
	refreshSides() {

		const sides = this.shape.getSides?.() ?? [];

		while(this.sides.length > sides.length) {
			this.sides.pop().remove();
		}

		while(this.sides.length < sides.length) {
			this.sides.push(this.pose(MapDrawing.getNode('util-maptool-say')));
		}

		sides.forEach((side, position) => {

			const from = this.map.project(side.from);
			const to = this.map.project(side.to);
			const shown = (Math.hypot(to.x - from.x, to.y - from.y) >= MapDrawing.SIDES_MINIMUM);

			this.sides[position].getElement().textContent = this.card.dataset.length.replace('{value}', Math.round(side.value * 10) / 10);
			this.show(this.sides[position], shown ? side.at : null);

		});

	}

	/* Ce qui se pose autour de la forme d'après la place que la carte lui laisse, et se replace donc quand elle bouge */
	refreshAround() {
		this.refreshCard();
		this.refreshUnpoint();
		this.refreshSides();
		this.refreshEdges();
	}

	/* Les cibles des trois poignées d'un rang petit à l'écran s'empilent, et la dernière posée, un bord, couvre le milieu et toute
	   la surface : l'appui écarte les allées au lieu de tirer le rang. Une poignée de bord à moins d'une cible du milieu se cache donc
	   jusqu'à ce que le rang lui laisse sa place, sauf pendant qu'on la tire. Deux bords à moins d'une cible l'un de l'autre n'en
	   gardent qu'un, le dernier : tirer l'un ou l'autre écarte les mêmes allées */
	refreshEdges() {

		const pivot = this.handles.find(handle => handle.type === 'point') ?? null;

		if(pivot === null) {
			return;
		}

		const getDistance = (from, to) => Math.hypot(to.x - from.x, to.y - from.y);

		const middle = this.map.project(pivot.marker.getLngLat());
		const edges = this.handles.filter(handle => handle.type === 'edge');
		const points = edges.map(handle => this.map.project(handle.marker.getLngLat()));
		const close = (points.length === 2 && getDistance(...points) < MapDrawing.HANDLE);

		edges.forEach((handle, position) => {
			const hidden = (getDistance(points[position], middle) < MapDrawing.HANDLE || (close && position === 0));
			handle.marker.getElement().classList.toggle('hide', this.drag?.type !== 'edge' && hidden);
		});

	}

	/* Une fiche posée se tient au-dessus de son contour, et reste dans la carte quand il en sort. Avant le premier point, elle garde
	   la place que getCardAt() lui donne en haut de la carte */
	refreshCard() {

		if(this.cardMarker === null) {
			return;
		}

		const points = this.getDrawn().points;

		const ring = (this.trigger.dataset.drawingAnchor !== undefined) ?
			JSON.parse(this.trigger.dataset.drawingAnchor) :
			((points.length > 0) ? points : null);

		if(ring === null || this.cardTop === null) {
			this.cardPlace = null;
			this.show(this.cardMarker, this.getCardAt());
			return;
		}

		this.cardPlace = MapDrawing.placeInView(this.map, ring, {width: this.cardWidth, height: this.cardHeight}, this.getInset(), MapDrawing.CARD_GAP);

		this.show(this.cardMarker, this.map.unproject([this.cardPlace.x, this.cardPlace.y + this.cardHeight + MapDrawing.CARD_GAP]).toArray());

	}

	/* La croix se pose en diagonale du point choisi, en haut à droite, et passe de l'autre côté quand le bord de la carte la couperait */
	refreshUnpoint() {

		const at = (this.selected !== null && this.shape.points.length > this.shape.minimum) ? this.shape.points[this.selected] : null;

		if(at !== null) {

			const point = this.map.project(at);
			const reach = MapDrawing.UNPOINT_GAP + MapDrawing.UNPOINT_HALF;

			this.unpoint.setOffset([
				(point.x + reach > this.width) ? -MapDrawing.UNPOINT_GAP : MapDrawing.UNPOINT_GAP,
				(point.y - reach < 0) ? MapDrawing.UNPOINT_GAP : -MapDrawing.UNPOINT_GAP
			]);

		}

		this.show(this.unpoint, at);

	}

	/* Le refus d'une forme qui n'était pas prête (finish()) part au premier point posé, comme le message d'un champ part à la
	   saisie qui le corrige : sur la carte, il tombe au bandeau de la page, qui ne s'efface sinon qu'à sa croix */
	refreshRefusal() {

		if(this.refused === false || (this.shape.points.length === 0 && this.shape.isReady() === false)) {
			return;
		}

		this.refused = false;

		Lime.Alert.hideErrors(this.level ?? this.card);
		Lime.Alert.hideStaticErrors();

	}

	/* La rangée du tracé montre les outils dont la forme a l'usage, et ses modes quand elle en a plusieurs à choisir. Elle ne se mesure que quand ses cases changent, rarement : une fiche posée sur la carte s'écarte de ce qu'elle
	   couvre comme de la colonne des boutons de Mapbox (getInset()) */
	refreshTools() {

		const names = this.shape.getTools();
		const modes = this.shape.getModes();

		/* Les formes de la rangée désignent celle qu'on trace */
		let shape = 'free';

		if(this.shape instanceof MapDrawingRectangle) {
			shape = 'rectangle';
		} else if(this.shape instanceof MapDrawingPac) {
			shape = 'pac';
		}

		/* Un contour confié au rectangle n'a aucun geste à offrir : ses modes annonceraient des poignées qu'il n'a pas */
		const confided = ((this.shape.getConfided?.() ?? null) !== null);

		const key = shape +'|'+ names.join(' ') +'|'+ modes.join(' ') +'|'+ this.shape.closed +'|'+ confided;

		if(key === this.barNames) {
			return;
		}

		this.barNames = key;

		this.bar.qsa('[data-tool]', tool => {
			tool.classList.toggle('hide', names.includes(tool.dataset.tool) === false);
			tool.classList.toggle('selected', tool.dataset.tool === shape);
		});
		this.bar.qsa('[data-drawing-mode]', mode => {
			mode.classList.toggle('hide', modes.length === 1 || confided || modes.includes(mode.dataset.drawingMode) === false);
			mode.classList.toggle('disabled', this.shape.closed === false && mode.dataset.drawingMode !== modes[0]);
		});

		/* Dès que la rangée des gestes montre une case, la forme qu'on trace s'y colle en tête et les autres passent dans son menu
		   (PlanUi::getMapShapeChoice()), qui n'a rien à dérouler quand le lien n'en propose pas d'autre */
		const shapes = [];
		this.bar.qsa('[data-drawing-choice]', choice => shapes.push(choice.dataset.drawingChoice));

		const joined = (
			names.some(name => shapes.includes(name) === false) ||
			(modes.length > 1 && confided === false) ||
			this.bar.qsa('[data-drawing-action]').length > 0
		);
		const alone = (names.some(name => shapes.includes(name) && name !== shape) === false);

		this.bar.qsa('[data-drawing-shapes]', row => row.classList.toggle('hide', joined));
		this.bar.qsa('[data-drawing-choice]', choice => {
			choice.classList.toggle('hide', joined === false || choice.dataset.drawingChoice !== shape);
			choice.classList.toggle('util-mapbar-choice-alone', alone);
		});

		this.measureBar();

	}

	/* Une rangée que plan.css cache, sans outil sous 768 px, ne couvre rien */
	measureBar() {

		const container = this.map.getContainer().getBoundingClientRect();
		const bar = this.bar.getBoundingClientRect();

		this.barBox = (bar.width === 0) ? null : {left: bar.left - container.left, bottom: bar.bottom - container.top};

	}

	/* La cote d'une allée, et sa coche quand l'écart est celui du plan */
	refreshSay(said) {

		const node = this.say.getElement();

		node.classList.remove('util-maptool-say-above');

		if(said !== null) {
			const value = this.card.dataset.alley.replace('{value}', Math.round(said.value));
			node.innerHTML = said.ok ? Lime.Asset.icon('check-lg') +' '+ value : value;
			node.classList.toggle('util-maptool-say-ok', said.ok);
		}

		this.show(this.say, (said === null) ? null : said.at);

	}

	/* Les planches dessinées du jardin, hors celles qu'on pose : un rang neuf se pose à l'écart d'elles */
	getNeighbours() {

		/* Les identifiants viennent du document d'un côté, du JSON de l'autre : ils se comparent en texte. Des planches à créer n'ont
		   pour identifiant que leur position dans le rang, qu'aucune planche dessinée ne doit leur disputer */
		const ids = this.shape.create ? [] : this.shape.beds.map(bed => String(bed.id));

		return [...this.carto.features.beds.values()]
			.flat()
			.filter(polygon => String(polygon.properties.plot) === String(this.shape.plot) && ids.includes(String(polygon.properties.id)) === false);

	}

	/* Les côtés auxquels une forme rigide s'aimante : ceux du contour qui la reçoit, et pour un jardin ou une parcelle ceux des
	   autres jardins ou parcelles. Une planche ne se colle pas aux autres planches, qu'elle recouvrirait. Un contour enregistré
	   se referme sur son premier point : ce côté nul ne compte pas */
	getReferences() {

		const sides = ring => ring
			.map((point, index) => ({from: point, to: ring[(index + 1) % ring.length]}))
			.filter(side => side.from[0] !== side.to[0] || side.from[1] !== side.to[1]);

		if(this.shape instanceof MapDrawingLine) {
			return sides(this.shape.contour);
		}

		/* Une forme ne s'aimante pas à elle-même, ce qu'elle était avant le geste restant sur la carte sous le masque */
		const features = (this.trigger.dataset.drawingTarget === 'zone') ? this.carto.features.zones : this.carto.features.plots;
		const others = [...features.entries()]
			.filter(([id, feature]) => String(id) !== this.trigger.dataset.drawingId && feature.geometry.type === 'Polygon')
			.flatMap(([id, feature]) => sides(feature.geometry.coordinates[0]));

		return [...((this.shape.contour === null) ? [] : sides(this.shape.contour)), ...others];

	}

	/**
	 * Colle la forme au côté voisin le plus proche qu'un de ses côtés longe (SNAP_PIXELS, SNAP_DEGREES), de l'autre côté de lui
	 * que celui où elle se tient : elle en prend l'angle autour de son milieu, puis s'en approche ou s'en écarte jusqu'à le toucher.
	 * La mesure se fait à l'écran, où se lit le seuil, et chaque mouvement repart du pointeur : la forme se lâche d'elle-même
	 * dès qu'on l'éloigne. Sans `turning`, elle ne fait que glisser jusqu'à toucher le côté par le plus proche des deux bouts du sien.
	 */
	snap(references, turning = true) {

		const outline = this.shape.getOutline();

		if(outline === null) {
			return;
		}

		const project = point => this.map.project(point);
		const corners = outline.map(project);
		const center = {x: corners.reduce((total, corner) => total + corner.x, 0) / corners.length, y: corners.reduce((total, corner) => total + corner.y, 0) / corners.length};

		const dot = (u, v) => u.x * v.x + u.y * v.y;
		const minus = (u, v) => ({x: u.x - v.x, y: u.y - v.y});

		let best = null;

		references.forEach(reference => {

			const from = project(reference.from);
			const to = project(reference.to);
			const length = Math.hypot(to.x - from.x, to.y - from.y);

			if(length < 1) {
				return;
			}

			const along = {x: (to.x - from.x) / length, y: (to.y - from.y) / length};
			const facing = {x: -along.y, y: along.x};
			const normal = (dot(minus(center, from), facing) < 0) ? {x: -facing.x, y: -facing.y} : facing;

			corners.forEach((corner, index) => {

				const next = corners[(index + 1) % corners.length];
				const degrees = (Math.atan2(next.y - corner.y, next.x - corner.x) - Math.atan2(along.y, along.x)) * 180 / Math.PI;
				const turn = ((degrees % 180) + 270) % 180 - 90;

				if(Math.abs(turn) >= MapDrawing.SNAP_DEGREES) {
					return;
				}

				const middle = {x: (corner.x + next.x) / 2, y: (corner.y + next.y) / 2};
				const distance = dot(minus(middle, from), normal);
				const lengthwise = dot(minus(middle, from), along);
				const half = Math.hypot(next.x - corner.x, next.y - corner.y) / 2;

				/* Le côté qui longe la référence est celui de la forme qui en est le plus près, en face d'elle sur sa longueur */
				if(distance > dot(minus(center, from), normal) || lengthwise < -half || lengthwise > length + half) {
					return;
				}

				const miss = Math.abs(distance);

				if(miss >= MapDrawing.SNAP_PIXELS || (best !== null && miss + Math.abs(turn) >= best.score)) {
					return;
				}

				best = {score: miss + Math.abs(turn), index: index, normal: normal, from: from, reference: reference};

			});

		});

		if(best === null) {
			return;
		}

		/* L'écran a choisi le côté, la forme tourne autour de son milieu dans le plan où le serveur relit le mouvement d'un contour
		   (getPlane()), et l'angle s'y mesure, pour qu'elle y longe le côté. Un rang n'a pas de mouvement à relire, et tourne dans
		   le plan de son milieu */
		if(turning) {

			const pivot = this.shape.getPivot();
			const plane = (this.shape.getPlane === undefined) ? pivot : this.shape.getPlane();
			const heading = (from, to) => {
				const [[fromX, fromY], [toX, toY]] = [from, to].map(point => MapDrawingRectangle.toPlane(plane, point));
				return Math.atan2(toY - fromY, toX - fromX) * 180 / Math.PI;
			};

			// getRotated() tourne dans le sens horaire, qui fait baisser le cap mesuré depuis l'est
			const degrees = heading(outline[best.index], outline[(best.index + 1) % outline.length]) - heading(best.reference.from, best.reference.to);

			this.shape.points = MapDrawingCopy.getRotated(this.shape.points, plane, pivot, ((degrees % 180) + 270) % 180 - 90);

		}

		const turned = this.shape.getOutline().map(project);
		const corner = turned[best.index];
		const next = turned[(best.index + 1) % turned.length];
		const middle = {x: (corner.x + next.x) / 2, y: (corner.y + next.y) / 2};
		const distance = turning ? dot(minus(middle, best.from), best.normal) : Math.min(dot(minus(corner, best.from), best.normal), dot(minus(next, best.from), best.normal));
		const shift = -distance;

		const before = this.map.unproject([middle.x, middle.y]);
		const after = this.map.unproject([middle.x + best.normal.x * shift, middle.y + best.normal.y * shift]);

		this.shape.points = this.shape.points.map(([longitude, latitude]) => [longitude + after.lng - before.lng, latitude + after.lat - before.lat]);

	}

	/**
	 * Le sommet d'un contour qu'on pose ou qu'on tire, au point de l'écran donné : à moins de SNAP_PIXELS d'un sommet voisin il s'y
	 * pose, sinon d'un côté voisin il se pose dessus, au plus près. Un sommet passe avant un côté, pour que deux contours qui se
	 * touchent partagent leurs coins.
	 */
	snapPoint(point, references) {

		const project = at => this.map.project(at);
		const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

		let best = null;

		references.forEach(reference => {

			const from = project(reference.from);

			if(distance(point, from) < MapDrawing.SNAP_PIXELS && (best === null || best.corner === false || distance(point, from) < best.distance)) {
				best = {corner: true, distance: distance(point, from), at: reference.from};
			}

		});

		if(best !== null) {
			return best.at;
		}

		references.forEach(reference => {

			const from = project(reference.from);
			const to = project(reference.to);
			const length = distance(from, to);

			if(length < 1) {
				return;
			}

			const along = Math.max(0, Math.min(1, ((point.x - from.x) * (to.x - from.x) + (point.y - from.y) * (to.y - from.y)) / (length * length)));
			const foot = {x: from.x + along * (to.x - from.x), y: from.y + along * (to.y - from.y)};

			if(distance(point, foot) < MapDrawing.SNAP_PIXELS && (best === null || distance(point, foot) < best.distance)) {
				best = {distance: distance(point, foot), at: foot};
			}

		});

		return (best === null) ? this.map.unproject([point.x, point.y]).toArray() : this.map.unproject([best.at.x, best.at.y]).toArray();

	}

	/* Le côté à venir, du dernier point posé au pointeur */
	refreshGhost(to) {

		/* Un rectangle se trace d'un coin à l'autre, sans côté à venir */
		if(this.shape.closed || this.shape.points.length === 0 || this.shape instanceof MapDrawingRectangle) {
			return;
		}

		this.carto.setFeature('drawing', 'ghost', {
			type: 'Feature',
			properties: {ghost: true},
			geometry: {type: 'LineString', coordinates: [this.shape.points[this.shape.points.length - 1], to]}
		});

	}

	/* Le pointeur est-il sur la surface d'une forme qui se prend d'un bloc : modifiée, elle laisse la carte glisser sous elle */
	isOverBody(e) {
		return (this.mode !== 'edit' && this.isOverShape(e));
	}

	/* Une forme rigide se prend par toute son emprise, les allées d'un rang comprises, un contour par sa surface rendue */
	isOverShape(e) {

		const outline = this.shape.getOutline?.() ?? null;

		if(outline === null) {
			return (this.map.queryRenderedFeatures(e.point, {layers: ['drawing-fill', 'drawing-line']}).length > 0);
		}

		return turf.booleanPointInPolygon(e.lngLat.toArray(), turf.polygon([[...outline, outline[0]]]));

	}

	/* La parcelle PAC sous le point pose, agrandit ou réduit le contour. Un clic attend la lecture en cours, puis relit le registre
	   s'il tombe hors du carré lu ; un second clic pendant ce temps ne fait rien, pour ne pas reprendre la parcelle que le premier
	   va poser */
	pickPac(point) {

		if(this.pacPicking) {
			return;
		}

		this.pacPicking = true;
		this.pacFailed = false;

		(this.pacLoading ?? Promise.resolve())
			.then(() => this.isInPac(point) ? null : this.loadPac(point))
			.then(() => {

				if(MapDrawing.current !== this || (this.shape instanceof MapDrawingPac) === false) {
					return;
				}

				const parcel = this.getPacAt(point);

				if(parcel === null) {
					this.sayPac(point, this.card.dataset.pacNone);
					return;
				}

				const taken = this.shape.getTaken(parcel);

				if(taken.refusal !== undefined) {
					this.sayPac(point, this.getPacRefusal(taken.refusal));
					return;
				}

				/* Le contour vient de la déclaration quand il en part, ou qu'il en venait déjà : une parcelle ajoutée à un contour tracé à
				   la main n'en fait pas un contour déclaré */
				const declared = (this.shape.closed === false || this.pacYear !== null);

				this.shape.take(taken);
				this.pacYear = (this.shape.closed && declared) ? this.pac.year : null;

				/* La parcelle prise change de sens sous le pointeur : le prochain mouvement la redit */
				this.pacHover = null;
				this.carto.setFeature('pac', 'hover', null);

				this.changed = true;
				this.sayPac(point, null);
				this.refresh(true);

			})
			.finally(() => this.pacPicking = false);

	}

	/* Les parcelles se lisent dès que l'outil est choisi, autour du centre de la carte, puis de nouveau quand la vue ou le pointeur
	   sort du carré lu, une lecture à la fois. Après un échec, seul un clic relit : le survol ne relance pas un registre en panne,
	   ni un utilisateur qui l'a trop appelé */
	readPac(point) {

		if((this.shape instanceof MapDrawingPac) && this.pacLoading === null && this.pacFailed === false && this.isInPac(point) === false) {
			this.loadPac(point);
		}

	}

	/* Un registre qui ne répond pas, ou un utilisateur qui l'a trop appelé, rend un carré vide : le clic dit alors qu'il n'y a pas de
	   parcelle sous le point. La lecture faite, le dernier point survolé se relit, sans attendre que le pointeur bouge */
	loadPac(point) {

		this.pacLoading = new Ajax.Query()
			.url('/map/zone:pac')
			.method('post')
			.body({
				structure: this.card.dataset.pacStructure,
				season: this.carto.season,
				lng: point[0],
				lat: point[1]
			})
			.fetch()
			.then(json => {
				const parcels = json.parcels.map(parcel => parcel.coordinates);
				this.pac = {box: json.box, parcels: parcels, crops: new Map(json.parcels.map((parcel, position) => [parcels[position], parcel.crop])), year: json.year};
			}, () => {
				this.pac = {box: null, parcels: [], crops: new Map(), year: null};
				this.pacFailed = true;
			})
			.finally(() => {

				this.pacLoading = null;
				this.pacShown = null;
				this.pacHover = null;

				if(MapDrawing.current === this) {
					this.refreshPac();
					this.hoverPac(this.pacPointer);
				}

			});

		return this.pacLoading;

	}

	isInPac(point) {

		const box = this.pac.box;

		return (box !== null && point[0] >= box[0] && point[1] >= box[1] && point[0] <= box[2] && point[1] <= box[3]);

	}

	getPacAt(point) {
		return this.pac.parcels.find(parcel => turf.booleanPointInPolygon(point, turf.polygon([[...parcel, parcel[0]]]))) ?? null;
	}

	getPacRefusal(refusal) {

		switch(refusal) {

			case 'apart' :
				return this.card.dataset.pacApart;

			case 'split' :
				return this.card.dataset.pacSplit;

			case 'hole' :
				return this.card.dataset.pacHole;

			case 'detailed' :
				return this.card.dataset.pacDetailed;

		}

	}

	/* Sous le pointeur, la parcelle qu'un clic prendrait se colore et dit sa surface, ce qu'elle ajoute ou retire au contour, ou
	   pourquoi elle ne peut pas */
	hoverPac(point) {

		if(point === null || (this.shape instanceof MapDrawingPac) === false || this.drag !== null) {
			return;
		}

		this.pacPointer = point;

		if(this.mode === 'edit') {
			this.readPac(point);
		}

		if(this.pacLoading !== null) {
			return;
		}

		/* Hors de la retouche, un clic ne prend rien : le survol ne promet rien */
		const parcel = (this.mode === 'edit' && this.isInPac(point)) ? this.getPacAt(point) : null;

		if(parcel === this.pacHover) {
			return;
		}

		this.pacHover = parcel;

		if(parcel === null) {
			this.carto.setFeature('pac', 'hover', null);
			this.sayPac(point, null);
			return;
		}

		const taken = this.shape.getTaken(parcel);

		this.carto.setFeature('pac', 'hover', (taken.refusal === undefined) ? turf.polygon([[...parcel, parcel[0]]], {hover: true}) : null);

		if(taken.refusal !== undefined) {
			this.sayPac(point, this.getPacRefusal(taken.refusal));
		} else {
			const area = MapDrawingPolygon.getAreaText(Math.abs(taken.area), this.card.dataset);
			const crop = this.pac.crops.get(parcel);

			if(this.shape.closed === false) {
				this.sayPac(point, area, crop);
			} else {
				this.sayPac(point, ((taken.area < 0) ? this.card.dataset.pacRemove : this.card.dataset.pacAdd).replace('{value}', area), crop);
			}
		}

	}

	/* La cote du clic, posée au point, en texte : la surface, et au survol le libellé de culture de la parcelle, lu par le serveur
	   dans la table des cultures du registre (PacLib::getAround()), jamais un texte de la parcelle elle-même */
	sayPac(point, text, detail = null) {

		const node = this.say.getElement();

		node.classList.remove('util-maptool-say-ok');
		node.classList.add('util-maptool-say-above');

		if(text !== null) {

			node.textContent = text;

			if(detail !== null) {
				const line = MapDrawing.getNode('util-maptool-say-detail');
				line.textContent = detail;
				node.append(line);
			}

		}

		this.show(this.say, (text === null) ? null : point);

	}

	/* Les parcelles PAC que le contour n'a pas encore prises, en pointillé, une fois qu'il en a pris une */
	refreshPac() {

		const pac = (this.shape instanceof MapDrawingPac);

		/* Un refresh suit chaque mouvement d'une poignée : la couche ne se refait que quand le contour ou les parcelles lues changent */
		const shown = [pac, this.pac.box, JSON.stringify(this.shape.points)];

		if(this.pacShown !== null && shown.every((value, index) => value === this.pacShown[index])) {
			return;
		}

		this.pacShown = shown;

		const parcels = (pac && this.shape.closed) ? this.pac.parcels.filter(parcel => this.shape.isTaken(parcel) === false) : [];

		this.carto.setFeature('pac', 'parcels', (parcels.length === 0) ? null : parcels.map(parcel => turf.polygon([[...parcel, parcel[0]]])));

		if(pac === false) {
			this.carto.setFeature('pac', 'hover', null);
			this.pacHover = null;
		}

	}

	moveMap(e) {

		this.refreshGhost(e.lngLat.toArray());
		this.hoverPac(e.lngLat.toArray());

		if(this.shape.closed && this.drag === null) {
			this.map.getCanvas().style.cursor = this.isOverBody(e) ? ((this.mode === 'rotate') ? 'grab' : 'move') : '';
		}

	}

	/* Un appui sur la surface du rang le prend là où il est touché : la carte ne glisse pas dessous, et le rang garde
	   l'écart entre son milieu et le pointeur. En rotation, il le tourne autour de son centre */
	startBody(e, pointer) {

		/* Un rectangle à tracer se tire d'un coin au coin opposé : la carte ne glisse pas dessous. Le geste garde ce que la forme
		   avait changé avant lui, qu'un glisser trop court pour tracer lui rend */
		if(this.shape instanceof MapDrawingRectangle && this.shape.closed === false) {
			e.preventDefault();
			this.startDrag(pointer, {type: 'span', from: e.lngLat.toArray(), changed: this.changed});
			return;
		}

		if(this.isOverBody(e) === false) {
			return;
		}

		e.preventDefault();

		if(this.mode === 'rotate') {
			this.startDrag(pointer, {type: 'tool', name: 'rotate', change: null});
			return;
		}

		const pivot = this.shape.getPivot();

		this.startDrag(pointer, {type: 'body', offset: [pivot[0] - e.lngLat.lng, pivot[1] - e.lngLat.lat]});

	}

	clickMap(e) {

		/* Le second clic d'un double-clic referme le contour, il ne pose pas un point sur le précédent */
		if(e.originalEvent.detail > 1 || this.dragged) {
			return;
		}

		/* Un rectangle à tracer ne prend pas de point : un clic sans glisser ne trace rien */
		if(this.shape instanceof MapDrawingRectangle && this.shape.closed === false) {
			return;
		}

		/* Avec « Parcelle PAC », un clic pose, ajoute ou retire la parcelle déclarée qu'il touche. Un point choisi se lâche d'abord.
		   En déplacement ou en rotation, un clic manqué ne doit jamais retirer une parcelle : il ne fait rien */
		if(this.shape instanceof MapDrawingPac && this.selected === null) {

			if(this.mode === 'edit') {
				this.pickPac(e.lngLat.toArray());
			}

			return;

		}

		if(this.shape.closed === false) {
			this.shape.add(this.snapPoint(e.point, this.getReferences()));
			this.changed = true;
		} else if(this.selected !== null) {
			this.selected = null;
		} else if(this.isOverShape(e) === false) {
			this.finishAt(e);
			return;
		}

		this.refresh();

	}

	/* Un clic à côté de la forme vaut « Terminer », et sur un autre jardin le désigne une fois la forme enregistrée. Une forme
	   qu'on crée ne s'enregistre que par son bouton : le clic à côté n'y fait rien. Mapbox n'envoie pas de clic au bout d'un
	   glisser de la carte */
	finishAt(e) {

		/* Un clic à côté d'une forme qui ne peut pas s'enregistrer n'est pas une demande d'enregistrer : il n'appelle pas son refus */
		if(this.creating || this.shape.isReady() === false) {
			return;
		}

		const plotId = this.map.queryRenderedFeatures(e.point, {layers: ['plot-fill']})
			.map(feature => feature.properties.id)
			.find(id => String(id) !== this.trigger.dataset.drawingId);

		this.finish().then(saved => {
			if(saved && plotId !== undefined) {
				this.carto.clickPlot(plotId, this.carto.plotsZones[plotId], false);
			}
		});

	}

	/* « Enregistrer » est dans la rangée du tracé, sous 768 px dans la fiche collée sous la carte, et sous le nom d'une forme qu'on crée */
	getFinishButtons() {
		return [...new Set([this.bar, this.card, this.issues].flatMap(node => (node === null) ? [] : [...node.qsa('[data-drawing-action="finish"]')]))];
	}

	/* La fiche rappelle qui on dessine : la forme que le lien nomme, ou celle qu'on crée sous le nom saisi dans le bloc */
	refreshName() {

		const name = this.trigger.dataset.drawingName ?? this.level?.qs('form [name="name"]')?.value ?? '';

		this.card.qs('[data-drawing-name]', node => node.textContent = name);

		// Dans la rangée, la fiche ne porte que ce nom : sans lui, elle n'aurait rien à montrer
		this.card.classList.toggle('hide', this.cardBar && name === '');

		/* Elle s'élargit aussi avec le nom et peut faire passer la rangée sur deux lignes, sous lesquelles rien ne se pose.
		   open() place la fiche avant de poser la rangée, qui se mesure alors avec ses outils */
		if(this.cardBar && this.bar !== null) {
			this.measureBar();
		}

	}

	act(node) {

		switch(node.dataset.drawingAction) {

			case 'cancel' :
				this.cancel();
				break;

			case 'finish' :
				this.finish();
				break;

		}

	}

	setMode(mode) {

		this.selectMode(mode);
		this.selected = null;

		this.refresh(true);

	}

	selectMode(mode) {

		this.mode = mode;

		this.bar.qsa('[data-drawing-mode]', node => node.classList.toggle('selected', node.dataset.drawingMode === mode));

	}

	/* Une case de la rangée agit d'un clic : la forme change */
	useTool(node) {

		/* La forme qu'on trace déjà ne se remplace pas par elle-même */
		if(node.classList.contains('selected')) {
			return;
		}

		switch(node.dataset.tool) {

			/* La forme qui en remplace une autre relit le lien, qui lui dit où s'aimanter et vers quelle forme revenir */
			case 'free' :
				this.shape = this.shape.getFree();
				this.beginShape();
				break;

			case 'rectangle' :
				this.shape = this.shape.getRectangle();
				this.beginShape();
				this.pacYear = null;
				break;

			case 'pac' :
				this.shape = this.shape.getPac();
				this.beginShape();
				this.readPac(this.map.getCenter().toArray());
				break;

			default :
				this.shape.tool(node.dataset.tool, null, null);
				break;

		}

		if(node.dataset.tool === 'remove') {
			this.pacYear = null;
		}

		this.selected = null;
		this.changed = true;
		this.typed = false;

		this.refresh(true);

	}

	close() {

		if(this.shape.closed || this.shape.points.length < this.shape.minimum) {
			return;
		}

		this.shape.close();
		this.changed = true;

		this.refresh(true);

	}

	/* Le point sous le pointeur, en pixels de la carte puis en coordonnées : le cadre de la carte est lu une fois, au début du geste */
	getPointer(e) {
		return {x: e.clientX - this.drag.left, y: e.clientY - this.drag.top};
	}

	startDrag(e, drag) {

		if(e.button !== 0 || this.drag !== null) {
			return;
		}

		e.preventDefault();

		const rect = this.map.getContainer().getBoundingClientRect();

		this.drag = {...drag, left: rect.left, top: rect.top, x: e.clientX, y: e.clientY, moved: false};

	}

	startHandle(e, node) {

		/* Tant qu'on pose des points, une poignée ne se tire pas : seule la première se clique, pour refermer */
		this.startDrag(e, {
			type: this.shape.closed ? node.dataset.drag : 'still',
			index: parseInt(node.dataset.index)
		});

		/* Le pointeur devance la poignée qu'il tire : la carte garde son curseur jusqu'au lâcher, que moveMap() efface */
		if(this.drag?.type === 'edge') {
			this.map.getCanvas().style.cursor = this.getEdgeCursor(this.drag.index);
		}

	}

	/* Un bord se tire en travers de lui : le curseur de redimensionnement le plus proche de cette direction à l'écran,
	   qui change avec l'angle du rang et celui de la carte */
	getEdgeCursor(index) {

		const [from, to] = this.shape.getEdges()[index].map(point => this.map.project(point));
		const across = (Math.atan2(to.y - from.y, to.x - from.x) * 180 / Math.PI + 90 + 360) % 180;

		return ['ew-resize', 'nwse-resize', 'ns-resize', 'nesw-resize'][Math.round(across / 45) % 4];

	}

	/* La croix n'est pas un outil de la forme : elle retire le point choisi, quel que soit ce qu'on trace */
	startUnpoint(e) {
		this.startDrag(e, {type: 'unpoint'});
	}

	moveDrag(e) {

		const drag = this.drag;

		if(drag === null || drag.type === 'still' || drag.type === 'unpoint') {
			return;
		}

		if(drag.moved === false) {

			if(Math.hypot(e.clientX - drag.x, e.clientY - drag.y) < MapDrawing.CLICK) {
				return;
			}

			drag.moved = true;
			this.changed = true;
			this.typed = false;

			switch(drag.type) {

				/* Un milieu de côté qu'on tire devient un sommet */
				case 'middle' :
					this.shape.insert(drag.index, this.map.unproject(this.getPointer(e)).toArray());
					drag.type = 'point';
					this.selected = drag.index;
					this.refresh(true);
					break;

				case 'edge' :
					drag.change = this.shape.pull(drag.index);
					break;

				case 'tool' :
					drag.change = this.shape.tool(drag.name, {x: drag.x - drag.left, y: drag.y - drag.top}, this.map.project(this.shape.getPivot()));
					if(drag.change === null) {
						this.selected = null;
						this.refresh(true);
					}
					break;

			}

		}

		const pointer = this.getPointer(e);

		switch(drag.type) {

			case 'point' :
				if(this.shape instanceof MapDrawingPolygon) {
					drag.references ??= this.getReferences();
					this.shape.move(drag.index, this.snapPoint(pointer, drag.references));
				} else {
					this.shape.move(drag.index, this.map.unproject(pointer).toArray());
				}
				break;

			case 'body' : {
				const at = this.map.unproject(pointer);
				this.shape.moveTo([at.lng + drag.offset[0], at.lat + drag.offset[1]]);
				break;
			}

			case 'span' :
				this.shape.span(drag.from, this.map.unproject(pointer).toArray());
				break;

			case 'edge' :
				this.refreshSay(drag.change(this.map.unproject(pointer).toArray()));
				break;

			case 'tool' :
				if(drag.change !== null) {
					this.say.getElement().classList.remove('util-maptool-say-above');
					this.say.getElement().textContent = drag.change(pointer);
					this.show(this.say, this.shape.getPivot());
				}
				break;

		}

		/* Une forme rigide qu'on déplace ou qu'on tourne se colle au côté qu'elle longe : seul le milieu d'un rang le déplace, le coin
		   d'un rectangle le redimensionne */
		const moved = (drag.type === 'body' || (drag.type === 'point' && this.shape instanceof MapDrawingLine));

		if(this.shape.getOutline !== undefined && (moved || (drag.type === 'tool' && drag.change !== null))) {
			drag.references ??= this.getReferences();

			/* Un contour tiré d'un bloc garde son angle : seul l'outil de rotation le tourne */
			this.snap(drag.references, (this.shape instanceof MapDrawingPolygon && drag.type === 'body') === false);
		}

		this.refresh();

	}

	endDrag() {

		const drag = this.drag;

		if(drag === null) {
			return;
		}

		this.drag = null;
		this.show(this.say, null);
		this.say.getElement().classList.remove('util-maptool-say-ok');

		if(drag.moved) {

			/* Le navigateur clique sur la carte quand un glissé y finit : ce clic n'y pose rien et n'y lâche pas le point choisi */
			this.dragged = true;
			setTimeout(() => this.dragged = false, 0);

			/* Le rectangle tracé se ferme au lâcher, sauf s'il est trop petit pour être un jardin : il repart vide, sans rien avoir changé */
			if(drag.type === 'span') {
				if(this.shape.getSides().every(side => side.value >= MapDrawingRectangle.SIDE_MINIMUM)) {
					this.shape.close();
				} else {
					this.shape.tool('remove', null, null);
					this.changed = drag.changed;
				}
			}

			this.refresh(true);
			return;

		}

		switch(drag.type) {

			/* Le premier point referme le contour, et le dernier aussi : c'est sur lui que tombe le second clic d'un double-clic */
			case 'still' :
				if(drag.index === 0 || drag.index === this.shape.points.length - 1) {
					this.close();
				}
				break;

			case 'unpoint' :
				this.removeSelected();
				break;

			case 'point' :
				this.selected = (this.selected === drag.index) ? null : drag.index;
				this.refresh();
				break;

			/* Un milieu de côté qu'on clique devient un sommet là où il est, déjà choisi pour être tiré ou retiré */
			case 'middle' :
				this.shape.insert(drag.index, this.handles.find(handle => handle.type === 'middle' && handle.index === drag.index).at);
				this.selected = drag.index;
				this.changed = true;
				this.refresh(true);
				break;

			/* Un outil qui se tire ne fait rien d'un clic : il rend sa fonction, que personne n'appelle */
			case 'tool' :
				if(this.shape.tool(drag.name, null, null) === null) {
					this.selected = null;
					this.changed = true;
					this.typed = false;
					this.refresh(true);
				}
				break;

		}

	}

	removeSelected() {

		if(this.selected === null || this.shape.points.length <= this.shape.minimum) {
			return;
		}

		this.shape.remove(this.selected);
		this.selected = null;
		this.changed = true;

		this.refresh(true);

	}

	pressKey(e) {

		/* Le tracé ne prend que les touches frappées sur le canevas de la carte ou sur rien : un lien, un champ ou un bouton qui
		   tient le focus garde les siennes, ceux de la carte compris */
		const free = (e.target === document.body || e.target === this.map.getCanvas());

		/* Échap sort du geste d'où qu'il soit frappé, une case de la liste des planches comprise : seul un champ où l'on écrit
		   ou un menu ouvert le garde pour lui */
		if(e.key === 'Escape') {

			if(free === false && e.target.matches('input:not([type="checkbox"]), textarea, select, [data-dropdown]')) {
				return;
			}

			/* La case de la forme est un lien sans adresse, qui ne prend pas le focus : la touche part du body, et c'est son menu
			   ouvert qu'elle referme, le tracé restant tel quel */
			const choice = this.bar.qs('[data-drawing-choice][data-dropdown-display]');

			if(choice !== null) {
				Lime.Dropdown.close(choice);
				e.preventDefault();
				return;
			}

			this.pressEscape();
			e.preventDefault();
			return;

		}

		if(free === false) {
			return;
		}

		switch(e.key) {

			case 'Enter' :
				this.close();
				break;

			case 'Delete' :
			case 'Backspace' :
				this.removeSelected();
				break;

			default :
				return;

		}

		e.preventDefault();

	}

	/* Échap défait d'abord ce qui est en cours, le point choisi puis le dernier point posé, et quitte le geste quand il ne reste rien à défaire */
	pressEscape() {

		if(this.selected !== null) {
			this.selected = null;
			this.refresh();
		} else if(this.shape.closed === false && this.shape.points.length > 0) {
			this.shape.remove(this.shape.points.length - 1);
			this.refresh();
		} else {
			this.cancel();
		}

	}

	/* Quitte le geste sans rien enregistrer */
	cancel() {
		this.shut(false);
	}

	/* Un tracé commencé n'est écrit qu'à « Terminer » */
	isUnsaved() {
		return this.changed;
	}

	/* Une navigation ajax remplace la carte, et la fiche du geste avec elle */
	isAttached() {
		return document.body.contains(this.card);
	}

	/* Un rang neuf peut retracer des planches déjà tracées : elles quittent leur place le temps du geste, et les autres
	   planches du jardin restent en vue */
	maskBeds() {

		if((this.shape instanceof MapDrawingLine) === false || this.shape.create) {
			return;
		}

		this.carto.clearMask(true).setMaskBeds(this.shape.plot, this.shape.beds.map(bed => String(bed.id)));

	}

	/* La liste d'où un rang tire ses planches a changé pendant le geste */
	changeBeds() {

		this.shape.visible = this.getVisible();
		this.shape.setBeds(this.shape.getChecked());
		this.changed = true;

		this.maskBeds();

		this.refresh(true);

	}

	/* Rend une promesse de ce qu'est devenu le geste : vraie quand il est quitté, enregistré ou sans rien à enregistrer, fausse
	   quand il reste ouvert, sur un refus. Une forme qui ne peut pas s'enregistrer est refusée sans requête, où le serait celle du
	   serveur, par le message que porte le lien : lui seul sait ce qu'on dessine, une parcelle, un jardin ou un abri */
	finish() {

		if(this.shape.isReady() === false) {
			this.refused = true;
			Lime.Alert.showErrors(this.level ?? this.card, [[this.trigger.dataset.drawingErrorName, this.trigger.dataset.drawingError, 'coordinates']]);
			return Promise.resolve(false);
		}

		this.close();

		/* Un contour rendu tel qu'il a été ouvert n'a rien à enregistrer, et la surface saisie à la main n'est pas recalculée */
		if(this.isSame()) {
			this.cancel();
			return Promise.resolve(true);
		}

		/* Une forme qu'on crée part avec le formulaire du bloc, dont les refus s'affichent sous ses champs. Le bloc tient aussi
		   les formulaires des autres créations, qui ont les mêmes champs : la réponse ne cherche que dans le sien, et un refus
		   du contour passe au bandeau */
		const form = this.level?.qs('form') ?? null;
		const body = (form === null) ? {id: this.trigger.dataset.drawingId} : Object.fromEntries(new FormData(form));

		/* Un contour tiré depuis les planches ouvertes demande que la réponse les rende ouvertes */
		if(this.trigger.dataset.drawingBeds !== undefined) {
			body.beds = 1;
		}

		return new Ajax.Query(this.level ?? this.card)
			.url(this.trigger.dataset.drawingUrl)
			.method('post')
			.body({
				...body,
				season: this.carto.season,
				...this.shape.getBody()
			})
			.fetch()
			.then(json => {

				if(MapDrawing.current !== this) {
					return false;
				}

				/* Une parcelle créée rouvre la carte, par une navigation qui remplace la séance */
				if(json.__redirect !== undefined) {
					this.shut(true);
					return true;
				}

				/* Un refus répond aussi, par son message dans la fiche : seule une forme enregistrée revient avec ses coordonnées, et des
				   planches enregistrées avec celles que le jardin a tracées */
				if(json.coordinates === undefined && json.bedsDrawn === undefined) {
					return false;
				}

				this.shut(true);

				MapDrawing.targets[this.trigger.dataset.drawingTarget](this.carto, json);

				return true;

			}, () => false);

	}

}

/**
 * La séance d'une carte posée dans le formulaire d'un panneau (MapUi::getDrawingEmbedded()) : la forme s'y place, mais n'a ni fiche,
 * ni niveau du bloc, ni « Annuler » ni « Terminer ». Elle écrit son corps dans le champ caché que son lien nomme (`drawingField`),
 * la propriété du même nom, ou celle que nomme `drawingProperty` quand le champ porte un indice (`plotCoordinates[0]`), et le
 * formulaire part par son propre envoi. Fermer le panneau la quitte.
 */
class MapDrawingEmbedded extends MapDrawing {

	/* La rangée des outils couvre le haut de la carte, sous laquelle le cadrage laisse la forme */
	static PADDING = {top: 80, right: 40, bottom: 40, left: 40};

	field;

	constructor(trigger) {

		super(trigger);

		/* Les gabarits que la forme lit d'une fiche, la cote d'une allée ou d'un côté, sont sur le lien */
		this.card = this.trigger;
		this.cardSource = this.trigger;

		this.field = qs('#'+ trigger.dataset.drawingForm).qs('[name="'+ trigger.dataset.drawingField +'"]');

	}

	/* La carte se crée avec le panneau (`create`). Une séance de la Carte en cours n'a rien écrit avant « Terminer » : le panneau
	   qui s'ouvre par-dessus la quitte, comme « Annuler » */
	static start(trigger, create) {

		MapDrawing.current?.cancel();

		/* Un panneau rouvert sitôt fermé reçoit sa carte avant que la fermeture ne libère la précédente, sous le même conteneur */
		MapCartography.get(trigger.dataset.drawingMap)?.dispose();

		create();

		const session = new MapDrawingEmbedded(trigger);
		MapDrawing.current = session;

		session.open();

		return session;

	}

	/* Le panneau qui se ferme emporte la séance. Rouvert avant d'être retiré, il reste le même nœud : chaque séance n'écoute que sa
	   propre fermeture */
	static startPanel(trigger, create) {

		const session = this.start(trigger, create);

		trigger.closest('.panel').addEventListener('panelClose', () => session.dispose(), {once: true});

	}

	/* Une forme remise à plus tard ne part pas : la carte se cache et le champ se désactive. Rendue, la carte reprend la taille de son
	   conteneur, que Mapbox n'a pas pu mesurer caché. Un refus du mode qu'on quitte ne vaut plus pour l'autre, où il tomberait sur un
	   champ qui ne part pas. Remise à plus tard, la surface se saisit, et seule celle qui se voit part (MapUi::getDrawingArea()) */
	static changeLater(input) {

		const later = (input.value === '0');
		const scope = MapDrawingEmbedded.getScope(input);
		const session = MapDrawing.current;

		Lime.Alert.hideErrors(scope);

		/* Remise à plus tard, la surface saisie reprend celle du tracé qu'on quitte, que la séance efface en désactivant la forme. Le
		   champ caché ne part qu'avec une forme prête (refresh()) : sans forme, un tracé effacé ne justifie plus la surface reprise, et
		   le champ rend ce qu'il portait avant, sauf si on l'a saisi depuis */
		const trigger = scope.qs('[data-drawing-area-field]');

		if(later && trigger !== null) {

			const drawn = scope.qs('input[type="hidden"][name="'+ trigger.dataset.drawingAreaField +'"]');
			const typed = scope.qs('input[name="'+ trigger.dataset.drawingAreaField +'"]:not([type="hidden"])');

			if(typed !== null) {

				const before = typed.value;

				if(drawn.disabled === false) {
					if(typed.value !== typed.dataset.drawingAreaCopied) {
						typed.dataset.drawingAreaKept = typed.value;
					}
					typed.dataset.drawingAreaCopied = drawn.value;
					typed.value = drawn.value;
				} else if(typed.dataset.drawingAreaCopied !== undefined) {
					if(typed.value === typed.dataset.drawingAreaCopied) {
						typed.value = typed.dataset.drawingAreaKept;
					}
					delete typed.dataset.drawingAreaKept;
					delete typed.dataset.drawingAreaCopied;
				}

				if(typed.value !== before) {
					typed.dispatchEvent(new Event('input', {bubbles: true}));
				}

			}

		}

		scope.qs('[data-drawing-block]').classList.toggle('hide', later);

		scope.qsa('[data-drawing-surface="typed"]', node => {
			node.classList.toggle('hide', later === false);
			node.qsa('input', field => field.disabled = (later === false));
		});

		if(session instanceof MapDrawingEmbedded === false || session.getScope() !== scope) {
			return;
		}

		/* Revenue au dessin, une forme qui porte ses dimensions prend celles qu'on a saisies entre-temps, lues avant que refresh()
		   ne puisse écrire dans les champs celles de la forme */
		if(later) {
			session.field.disabled = true;
			session.refreshArea();
		} else {
			session.map.resize();
			session.changeDimensions(scope);
			session.refresh();
		}

	}

	/* Un formulaire porte un tracé, ou plusieurs quand chacun a son bloc (`data-drawing-scope`) : l'interrupteur, la carte, la surface
	   lue et la phrase de la PAC se cherchent dans le bloc du tracé, qui vaut le formulaire quand il est seul */
	static getScope(node) {
		return node.closest('[data-drawing-scope]') ?? node.closest('form');
	}

	/* La surface lue sur le tracé n'a pas de ligne tant qu'elle ne part pas : un tracé pas encore refermé la laisse vide, un tracé
	   remis à plus tard la désactive. L'état se lit sur le formulaire, qu'une séance soit ouverte ou non */
	static refreshSurface(form) {

		form.qsa('[data-drawing-surface="drawn"]', node => {
			const area = node.qs('input[type="hidden"]');
			node.classList.toggle('hide', area.disabled || area.value === '');
		});

	}

	/* Une forme à dessiner maintenant et encore sans tracé n'a rien à envoyer : le refus du serveur tomberait sur des champs que
	   l'écran lit sur le dessin, alors c'est le dessin qu'on désigne. Seul un bloc qui porte son refus arrête l'envoi
	   (MapUi::getDrawingGroup()) */
	static checkDrawing(form) {

		const errors = this.getDrawingErrors(form);

		Lime.Alert.showErrors(form, errors);

		return (errors.length === 0);

	}

	/* Les refus de checkDrawing(), qu'un parcours qui contrôle aussi ses champs montre avec les siens, showErrors() effaçant ceux
	   qu'il a déjà posés */
	static getDrawingErrors(form) {

		const errors = [];

		form.qsa('[data-drawing-block][data-error-name]', block => {

			// Un bloc dans un fieldset désactivé ne poste rien, il n'a donc rien à refuser
			if(block.closest('fieldset[disabled]') !== null) {
				return;
			}

			const field = form.qs('[name="'+ block.qs('[data-drawing-field]').dataset.drawingField +'"]');

			if(block.classList.contains('hide') === false && field.disabled) {
				errors.push([block.dataset.errorName, block.dataset.error, field.name]);
			}

		});

		return errors;

	}

	open() {

		this.carto.locked = true;

		if(this.trigger.dataset.drawingMask !== undefined) {
			this.carto.setMask(this.trigger.dataset.drawingMask.split(' '), parseInt(this.trigger.dataset.drawingId));
		}

		this.beginShape();

		if(this.shape.getFrame !== undefined) {
			this.map.fitBounds(this.getBounds(this.shape.getFrame()), {padding: MapDrawingEmbedded.PADDING, duration: 0});
		}

		this.map.doubleClickZoom.disable();
		Object.entries(this.listeners).forEach(([type, listener]) => this.map.on(type, listener));

		this.measure();

		this.bar = this.view.qs('[data-drawing-tools]');
		this.say = this.pose(MapDrawing.getNode('util-maptool-say'));

		this.selectMode(this.shape.getModes()[0]);
		this.refresh(true);

	}

	/* Le panneau qui se ferme emporte sa carte : la séance la quitte avec lui */
	dispose() {

		if(MapDrawing.current === this) {
			this.shut(false);
		}

		this.carto.dispose();

	}

	shut(saved) {

		MapDrawing.current = null;

		Object.entries(this.listeners).forEach(([type, listener]) => this.map.off(type, listener));

		[...this.handles.map(handle => handle.marker), ...this.sides, this.say].forEach(marker => marker.remove());

		this.carto.locked = false;

	}

	placeCard() {
		return false;
	}

	measure() {

		const container = this.map.getContainer().getBoundingClientRect();

		this.width = container.width;
		this.height = container.height;
		this.cardTop = 0;

	}

	/* Le formulaire du panneau emporte la forme telle qu'elle est à l'écran, et rien tant qu'elle n'est pas prête, ni quand le
	   formulaire la remet à plus tard (son interrupteur `map-drawing-switch` éteint) */
	refresh(rebuild = false) {

		/* Le champ dit si la forme part avant que la séance n'y lise ses dimensions (refreshDimensions()) */
		this.field.disabled = (this.shape.isReady() === false || this.isLater());
		this.field.value = this.shape.isReady() ? this.shape.getBody()[this.trigger.dataset.drawingProperty ?? this.trigger.dataset.drawingField] : '';

		super.refresh(rebuild);

		/* Un refus porté par le dessin date de la forme d'avant : celle qui part maintenant ne l'a pas encore reçu. hideErrors() ne
		   nettoie que ce que le wrapper contient, sa propre marque d'erreur se retire à part */
		if(this.field.disabled === false) {

			this.field.form.qsa('[data-wrapper~="'+ this.field.name +'"]', wrapper => {
				wrapper.classList.remove('form-error-wrapper');
				Lime.Alert.hideErrors(wrapper);
			});

		}

		this.refreshArea();

	}

	getForm() {
		return this.field.form;
	}

	getScope() {
		return MapDrawingEmbedded.getScope(this.trigger);
	}

	isLater() {
		return (this.getScope().qs('.map-drawing-switch.field-switch-off') !== null);
	}

	// La carte se cadre sur le contour fermé, d'où qu'il vienne, et ne bouge pas sans contour
	frameShape() {

		if(this.shape.closed) {
			this.map.fitBounds(this.getBounds(this.shape.points), {padding: MapDrawingEmbedded.PADDING, duration: 0});
		}

	}

	/* Une séance rouverte sur un contour déclaré à la PAC le garde déclaré : l'outil et l'année reviennent avec lui, et les parcelles
	   que la séance fermée avait lues, que le registre n'a pas à rendre une seconde fois pour la même vue. La carte se cadre sur le
	   contour avant que l'outil lise les parcelles autour du centre */
	resumePac(year, pac) {

		this.pac = pac;
		this.pacYear = year;

		this.frameShape();

		this.useTool(this.bar.qs('[data-tool="pac"]'));

	}

	/* Sous la carte du panneau, la phrase dit de quelle déclaration vient le contour */
	refreshPac() {

		super.refreshPac();

		this.getScope().qsa('[data-drawing-pac-info]', node => {
			node.classList.toggle('hide', this.pacYear === null);
			node.textContent = (this.pacYear === null) ? '' : node.dataset.drawingPacInfo.replace('{value}', this.pacYear);
		});

	}

	/* La surface d'une forme qui part est celle de son tracé : elle part dans le champ caché que nomme le lien (`drawingAreaField`), se
	   lit en texte au gabarit de son unité (`drawingAreaText`), et ne part pas quand la forme ne part pas */
	refreshArea() {

		if(this.trigger.dataset.drawingAreaField === undefined) {
			return;
		}

		const area = this.field.form.qs('input[type="hidden"][name="'+ this.trigger.dataset.drawingAreaField +'"]');

		const value = this.field.disabled ? '' : String(this.shape.getArea());

		area.disabled = this.field.disabled;

		this.getScope().qsa('[data-drawing-area-text]', node => {
			node.innerHTML = (value === '') ? '' : node.dataset.drawingAreaText.replace('{value}', parseInt(value).toLocaleString());
		});

		// Le formulaire voit la surface changer comme une saisie, pour ce qui la reprend ailleurs à l'écran
		const changed = (area.value !== value);

		area.value = value;

		MapDrawingEmbedded.refreshSurface(this.field.form);

		if(changed) {
			area.dispatchEvent(new Event('input', {bubbles: true}));
		}

	}

	/* Le bouton du formulaire porte le geste : la séance n'a ni fiche à remplir ni « Enregistrer » à nommer */
	refreshIssues() {
	}

	/* Une forme rigide n'a pas de point à retirer */
	refreshUnpoint() {
	}

	/* Un clic à côté de la forme ne la lâche pas : le formulaire l'enregistre */
	finishAt(e) {
	}

	/* Échap ferme le panneau (Lime.Panel.closeEscape()) : la séance ne quitte rien d'elle-même */
	pressEscape() {

		this.selected = null;
		this.refresh();

	}

	isUnsaved() {
		return false;
	}

	isAttached() {
		return document.body.contains(this.view);
	}

}

document.delegateEventListener('click', '[data-drawing]', function() {
	if(this.classList.contains('disabled') === false) {
		MapDrawing.start(this);
	}
});

/* Les issues du geste, dans la rangée du tracé, la fiche sous la carte ou le bloc */
document.delegateEventListener('click', '[data-drawing-action]', function() {
	MapDrawing.current?.act(this);
});

document.delegateEventListener('click', '[data-drawing-tools] [data-drawing-mode]', function() {

	if(this.classList.contains('disabled')) {
		return;
	}

	MapDrawing.current?.setMode(this.dataset.drawingMode);

});

/* L'envoi du formulaire part d'un écouteur du cadriciel qui arrête la propagation (form.js) : le contrôle se pose sur le clic du bouton,
   que l'Entrée d'un champ déclenche aussi. Le sélecteur ne retient que les formulaires dont le bloc porte son refus, la seule forme
   que checkDrawing() sait lire : les planches et le parcours ont une carte sans refus à opposer */
document.delegateEventListener('click', 'form:has([data-drawing-block][data-error-name]) [type="submit"]', function(e) {

	if(MapDrawingEmbedded.checkDrawing(this.form) === false) {
		e.preventDefault();
	}

});

/* Le menu de la forme qu'on trace s'ouvre déplacé dans le body (Lime.Dropdown.open()) : ses entrées ne sont plus dans la rangée */
document.delegateEventListener('click', '[data-drawing-tools] [data-tool], [data-drawing-choices] [data-tool]', function() {
	MapDrawing.current?.useTool(this);
});

/* Le nom saisi dans le bloc d'une forme qu'on crée se lit à mesure dans la fiche du geste */
document.delegateEventListener('input', '.plan-console-level[data-level="create"] form [name="name"]', function() {
	MapDrawing.current?.refreshName();
});

document.delegateEventListener('input', '[data-drawing-dimension]', function() {
	MapDrawing.current?.changeDimensions(MapDrawingEmbedded.getScope(this));
});

/* Le retour du bloc reste offert pendant un geste : il enregistre d'abord, comme « Terminer », puis fait son propre chemin. Une
   forme qu'on crée ne s'enregistre que par son bouton : le retour la quitte, comme « Annuler ». Écouté en capture, il passe avant
   le onclick du lien, que le geste quitté rejoue */
document.addEventListener('click', e => {

	const up = e.target.closest('.plan-console-up a');

	/* La séance d'un panneau ne tient pas le bloc de la Carte */
	if(MapDrawing.current === null || up === null || MapDrawing.current instanceof MapDrawingEmbedded) {
		return;
	}

	e.preventDefault();
	e.stopImmediatePropagation();

	// Une forme qui ne peut pas s'enregistrer, une parcelle sans point par exemple, n'a rien à enregistrer : le retour la quitte
	if(MapDrawing.current.creating || MapDrawing.current.shape.isReady() === false) {
		MapDrawing.current.cancel();
		up.click();
		return;
	}

	MapDrawing.current.finish().then(saved => {
		if(saved && document.body.contains(up)) {
			up.click();
		}
	});

}, true);

/* Le rang de planches à créer suit le formulaire qui les décrit, à chaque saisie (MapBed.changeBeds(), MapOnboarding) */
document.delegateEventListener('bedsChange', 'form', function() {
	if(MapDrawing.current !== null && MapDrawing.current.shape.create === true && MapDrawing.current.shape.form === this) {
		MapDrawing.current.changeBeds();
	}
});

/* Entrée dans le nom d'une forme qu'on crée vaut « Créer » : le formulaire du bloc n'a pas d'autre envoi que le geste */
document.delegateEventListener('submit', '.plan-console-level[data-level="create"] form', function(e) {

	e.preventDefault();

	if(MapDrawing.current !== null) {
		MapDrawing.current.finish();
	}

});

document.delegateEventListener('pointerdown', '.util-maptool-handle', function(e) {
	if(MapDrawing.current !== null) {
		MapDrawing.current.startHandle(e, this);
	}
});

document.delegateEventListener('pointerdown', '[data-drawing-unpoint]', function(e) {
	if(MapDrawing.current !== null) {
		MapDrawing.current.startUnpoint(e);
	}
});

document.addEventListener('pointermove', e => {
	if(MapDrawing.current !== null) {
		MapDrawing.current.moveDrag(e);
	}
});

['pointerup', 'pointercancel'].forEach(type => document.addEventListener(type, () => {
	if(MapDrawing.current !== null) {
		MapDrawing.current.endDrag();
	}
}));

document.addEventListener('keydown', e => {
	if(MapDrawing.current !== null) {
		MapDrawing.current.pressKey(e);
	}
});

/* Rien n'est écrit avant « Terminer » : le navigateur demande avant de quitter la page sur un tracé commencé */
window.addEventListener('beforeunload', e => {
	if(MapDrawing.current !== null && MapDrawing.current.isUnsaved()) {
		e.preventDefault();
	}
});

/* Une navigation ajax remplace la carte sans décharger la page : la séance qui la tenait n'a plus rien à rendre */
document.addEventListener('navigation.wakeup', () => {
	if(MapDrawing.current !== null && MapDrawing.current.isAttached() === false) {
		MapDrawing.current = null;
	}
});

/* La séance d'un formulaire hors d'un panneau n'a pas de fermeture qui l'emporte : la navigation qui quitte l'écran libère sa carte
   et ses écouteurs */
document.addEventListener('navigation.sleep', () => {
	if(MapDrawing.current instanceof MapDrawingEmbedded) {
		MapDrawing.current.dispose();
	}
});
