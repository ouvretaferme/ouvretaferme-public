<?php
namespace map;

/**
 * L'interrupteur et le contrôle du tracé d'un panneau (`static/js/drawing.js`, MapDrawingEmbedded.changeLater(), refreshSurface()
 * et checkDrawing()), communs à la parcelle, au jardin, aux planches et au parcours : remis à plus tard, le tracé se cache et la
 * surface se saisit ; la surface lue n'a de ligne que quand elle part ; un envoi sans tracé, dessin allumé, est arrêté sur le bloc.
 *
 * Le document du banc ne rend rien : le scénario monte son formulaire, dont les nœuds gardent leurs classes, `disabled` et `value`,
 * et répondent aux sélecteurs d'attributs que posent ces trois méthodes. Un fieldset désactivé porte l'attribut `disabled`, comme
 * dans le document, puisque c'est l'attribut que lit `fieldset[disabled]`.
 *
 * Lancement : dev/test package=map file=DrawingSwitchAi
 */
class DrawingSwitchAiTest extends DrawingBenchAiTest {

	// Le panneau « Ajouter une parcelle » (ZoneUi::create()) : le bloc du tracé et son refus, la surface lue, la surface saisie
	const string OPEN = '
		const node = (tag, attrs = {}, children = [], state = {}) => {

			const classes = new Set(attrs.class ?? []);

			const self = {
				tag, attrs, children,
				name: attrs.name,
				disabled: state.disabled ?? false,
				value: state.value ?? "",
				dataset: state.dataset ?? {},
				classList: {
					toggle: (name, force) => force ? classes.add(name) : classes.delete(name),
					contains: name => classes.has(name)
				},
				matches: selector => {
					const name = selector.match(/^[a-z]+/)?.[0];
					return (name === undefined || name === tag) && [...selector.matchAll(/\[([a-z-]+)(?:="([^"]*)")?\]/g)].every(([, key, value]) => (key in attrs) && (value === undefined || attrs[key] === value));
				},
				descendants: () => children.flatMap(child => [child, ...child.descendants()]),
				qsa: (selector, callback) => {
					const found = self.descendants().filter(child => child.matches(selector));
					if(callback !== undefined) found.forEach(callback);
					return found;
				},
				qs: selector => self.descendants().find(child => child.matches(selector)) ?? null,
				closest: selector => {
					for(let current = self; current !== undefined; current = current.parent) {
						if(current.matches(selector)) return current;
					}
					return null;
				}
			};

			children.forEach(child => child.parent = self);

			return self;

		};

		const coordinates = node("input", { name: "coordinates", type: "hidden" }, [], { disabled: true });
		const read = node("input", { name: "area", type: "hidden" }, [], { disabled: true });
		const typed = node("input", { name: "area" }, [], { disabled: true });

		const trigger = node("div", { "data-drawing-field": "coordinates" }, [], { dataset: { drawingField: "coordinates" } });
		const block = node("div", { "data-drawing-block": "", "data-error-name": "Zone::coordinates.drawing" }, [trigger, coordinates], { dataset: { errorName: "Zone::coordinates.drawing", error: "Dessinez la parcelle" } });
		const lineRead = node("div", { "data-drawing-surface": "drawn", class: ["hide"] }, [read]);
		const lineTyped = node("div", { "data-drawing-surface": "typed", class: ["hide"] }, [typed]);

		const form = node("form", {}, [node("div", { "data-wrapper": "coordinates" }, [block]), lineRead, lineTyped]);

		const MapDrawingEmbedded = vm.runInContext("MapDrawingEmbedded", context);

		const shown = [];
		context.Lime = { Alert: { showErrors: (target, errors) => shown.push(errors), hideErrors: () => {} } };

		const turn = (value) => MapDrawingEmbedded.changeLater({ value, closest: selector => (selector === "form") ? form : null });

		const state = () => ({
			block: block.classList.contains("hide"),
			typed: [lineTyped.classList.contains("hide"), typed.disabled]
		});
	';

	public function testTheSwitchShowsTheSurfaceThatLeaves(): void {

		$result = $this->run(1512, self::OPEN.'
			turn("0");
			MapDrawingEmbedded.refreshSurface(form);
			result.later = state();

			turn("1");
			MapDrawingEmbedded.refreshSurface(form);
			result.now = state();
		');

		$this->assertTrue($result['later']['block'] ?? FALSE, 'remis à plus tard, le tracé se cache');
		$this->assertEquals([FALSE, FALSE], $result['later']['typed'] ?? NULL, 'la surface se saisit et part');

		$this->assertFalse($result['now']['block'] ?? TRUE, 'rallumé, le tracé se montre');
		$this->assertEquals([TRUE, TRUE], $result['now']['typed'] ?? NULL, 'la saisie se cache et ne part plus');

	}

	public function testTheReadSurfaceHasALineOnlyOnceDrawn(): void {

		$result = $this->run(1512, self::OPEN.'
			// Allumé et rien de tracé : la séance laisse la surface vide (MapDrawingEmbedded.refreshArea())
			read.disabled = false;
			MapDrawingEmbedded.refreshSurface(form);
			result.empty = lineRead.classList.contains("hide");

			read.value = "1250";
			MapDrawingEmbedded.refreshSurface(form);
			result.drawn = lineRead.classList.contains("hide");

			// Un lieu retiré au parcours désactive la surface sans séance ouverte
			read.disabled = true;
			MapDrawingEmbedded.refreshSurface(form);
			result.disabled = lineRead.classList.contains("hide");
		');

		$this->assertTrue($result['empty'] ?? FALSE, 'pas de ligne tant que rien n\'est tracé');
		$this->assertFalse($result['drawn'] ?? TRUE, 'une ligne dès que la forme est tracée');
		$this->assertTrue($result['disabled'] ?? FALSE, 'plus de ligne quand la surface ne part plus');

	}

	public function testTheReadSurfaceShowsAsSoonAsTheShapeCloses(): void {

		$result = $this->run(1512, self::OPEN.'
			// La séance d\'un tracé qu\'on vient de refermer, réduite à ce que lit MapDrawingEmbedded.refreshArea()
			coordinates.form = form;
			coordinates.disabled = false;

			const session = { trigger: { dataset: { drawingAreaField: "area" } }, field: coordinates, shape: { getArea: () => 1250 }, getScope: () => form };

			context.Event = class { constructor(type) { this.type = type; } };
			read.dispatchEvent = event => result.dispatched = event.type;

			MapDrawingEmbedded.prototype.refreshArea.call(session);

			result.value = read.value;
			result.hidden = lineRead.classList.contains("hide");
		');

		$this->assertEquals('1250', $result['value'] ?? NULL, 'la surface du tracé part');
		$this->assertFalse($result['hidden'] ?? TRUE, 'et sa ligne se montre dès le premier tracé');
		$this->assertEquals('input', $result['dispatched'] ?? NULL, 'le formulaire la voit changer');

	}

	public function testASubmitWithoutDrawingIsStopped(): void {

		$result = $this->run(1512, self::OPEN.'
			result.undrawn = MapDrawingEmbedded.checkDrawing(form);
			result.undrawnErrors = shown.pop();

			coordinates.disabled = false;
			result.drawn = MapDrawingEmbedded.checkDrawing(form);
			result.drawnErrors = shown.pop();

			coordinates.disabled = true;
			turn("0");
			result.later = MapDrawingEmbedded.checkDrawing(form);
		');

		$this->assertFalse($result['undrawn'] ?? TRUE, 'allumé sans tracé, l\'envoi est arrêté');
		$this->assertEquals([['Zone::coordinates.drawing', 'Dessinez la parcelle', 'coordinates']], $result['undrawnErrors'] ?? NULL, 'sur le bloc du tracé');
		$this->assertTrue($result['drawn'] ?? FALSE, 'tracé, il part');
		$this->assertEquals([], $result['drawnErrors'] ?? NULL, 'et le refus d\'avant tombe');
		$this->assertTrue($result['later'] ?? FALSE, 'remis à plus tard, il part sans tracé');

	}

	public function testABlockSetAsideInADisabledFieldsetIsNotStopped(): void {

		$result = $this->run(1512, self::OPEN.'
			const fieldset = node("fieldset", { disabled: "" }, [node("div", { "data-wrapper": "coordinates" }, [block])]);
			const formSet = node("form", {}, [fieldset]);

			result.setAside = MapDrawingEmbedded.checkDrawing(formSet);
			result.setAsideErrors = shown.pop();

			delete fieldset.attrs.disabled;
			result.active = MapDrawingEmbedded.checkDrawing(formSet);
		');

		$this->assertTrue($result['setAside'] ?? FALSE, 'allumé sans tracé, mais dans un fieldset désactivé, il ne poste rien et part');
		$this->assertEquals([], $result['setAsideErrors'] ?? NULL, 'sans refus');
		$this->assertFalse($result['active'] ?? TRUE, 'le même bloc dans un fieldset actif est arrêté');

	}

}
?>
