<?php
/**
 * Groups of elements
 *
 * Un seul curseur, celui d'ArrayIterator : dans un foreach, un second parcours de la même collection
 * (foreach, getIds(), map(), sum()) arrête la boucle, et first() la relance sans fin. Un clone partage
 * le stockage de l'original. Un __clone() qui copierait changerait le sens de chaque clone du dépôt :
 * il se pose avec la revue de ses appelants, jamais en passant.
 */
class Collection extends ArrayIterator {

	public int $depth = 1;

	private array $properties = [];
	private ?int $found = NULL;

	/**
	 * Checks if the collection is not empty
	 *
	 * @return bool
	 */
	public function notEmpty(): bool {
		return ($this->count() > 0);
	}

	/**
	 * Checks if the collection is empty
	 *
	 * @return bool
	 */
	public function empty(): bool {
		return ($this->count() === 0);
	}

	/**
	 * Set the depth of the group
	 */
	public function setDepth(int $depth): Collection {
		$this->depth = $depth;
		return $this;
	}

	/**
	 * Returns the depth of the group
	 */
	public function getDepth(): int {
		return $this->depth;
	}

	/**
	 * Set the found of the group
	 */
	public function setFound(?int $found): Collection {
		$this->found = $found;
		return $this;
	}

	/**
	 * Returns the found of the group
	 */
	public function getFound(): ?int {
		return $this->found;
	}

	/**
	 * Set a property to the collection
	 */
	public function setProperty(string $name, mixed $value): void {
		$this->properties[$name] = $value;
	}

	/**
	 * Returns a property of the collection
	 */
	public function getProperty(string $name): mixed {
		if(array_key_exists($name, $this->properties)) {
			return $this->properties[$name];
		} else {
			throw new Exception('Property \''.$name.'\' does not exist');
		}
	}

	public function slice(int $offset, ?int $length = NULL, bool $preserveKeys = FALSE) {

		$c = new Collection();
		$length ??= $this->count() - $offset;

		if($length <= 0 or $offset < 0) {
			return new Collection();
		}

		for($position = 0; $position < $length; $position++) {

			$this->seek($offset + $position);

			if($preserveKeys) {
				$c[$this->key()] = $this->current();
			} else {
				$c[] = $this->current();
			}

		}

		return $c;

	}

	public function reverse(bool $preserveKeys = FALSE) {

		$c = new Collection();

		for($position = $this->count() - 1; $position >= 0; $position--) {

			$this->seek($position);

			if($preserveKeys) {
				$c[$this->key()] = $this->current();
			} else {
				$c[] = $this->current();
			}

		}

		return $c;

	}

	public function __toString(): string {
		return $this->toString(4);
	}

	/**
	 * Convert element group as a string
	 *
	 */
	public function toString(?int $recursion = NULL): string {

		$output = 'Collection ('."\n";

		foreach($this as $key => $value) {

			if($value instanceof Element === FALSE and $value instanceof Collection === FALSE) {
				$output .= '   ['.$key.'] => '.gettype($value)."\n";
			} else {
				if($recursion > 0) {
					$string = trim($value->toString($recursion === NULL ? NULL : $recursion - 1));
				} else {
					$string = get_class($value).' [...]';
				}
				$output .= '   ['.$key.'] => '.str_replace("\n", "\n   ", $string)."\n";
			}

		}

		$output .= ')'."\n";

		return $output;

	}

	/**
	 * Push a new key
	 *
	 */
	public function push($keys, Element $e) {

		$keys = (array)$keys;

		if(count($keys) !== $this->depth) {
			throw new Exception('Number of $keys ('.count($keys).') should be equal to Collection depth ('.$this->depth.')');
		}

		$key = array_shift($keys);

		if($this->depth === 1) {

			if($key === NULL) {
				$this->append($e);
			} else {
				$this->offsetSet($key, $e);
			}

		} else {

			if($key === NULL) {
				throw new Exception('Key must not be NULL when Collection depth is greater than 1');
			}

			if($this->offsetExists($key) === FALSE) {
				$this->offsetSet($key, new Collection()->setDepth($this->depth - 1));
			}

			$this[$key]->push($keys, $e);

		}

	}

	/**
	 * Get value for a key
	 *
	 * @param mixed $index
	 * @param mixed $default
	 */
	public function get($index, $default = NULL) {

		if($index === NULL) {
			$index = '';
		}

		if($this->offsetExists($index)) {
			return $this->offsetGet($index);
		} else {
			return $default;
		}

	}

	/**
	 * Get an array copy of the element group
	 *
	 * @return array
	 */
	public function getArrayCopy(): array {

		$values = [];

		foreach($this as $key => $entry) {
			$values[$key] = $entry->getArrayCopy();
		}

		return $values;

	}

	/**
	 * Remove one depth
	 *
	 * @return array
	 */
	public function linearize(bool $reindex = TRUE): Collection {

		if($this->depth === 1) {
			throw new Exception('Invalid depth');
		}

		$c = new Collection();

		foreach($this as $subC) {
			$c->mergeCollection($subC, $reindex);
		}

		return $c;

	}

	/**
	 * Format a Collection as a array using the given rules
	 * $rules is an associative array. Each value can be either:
	 * - 'propertyName' (put as is in the returned array)
	 * - 'propertyName' => 'newPropertyName' (put using the new namle is the returned array)
	 * - 'propertyName' => function($eElement) { ... } (custom property)
	 *
	 * @return array $rules
	 */
	public function format(array $rules): array {

		if($this->depth > 1) {

			$values = [];

			foreach($this as $key => $entry) {
				$values[$key] = $entry->format($rules);
			}

			return $values;

		} else {

			$values = [];

			foreach($this as $eElement) {

				$value = [];

				foreach($rules as $destination => $source) {

					if(is_closure($source)) {
						$value[$destination] = $source($eElement);
					} else {

						if(is_string($destination)) {
							$value[$destination] = $eElement[$source];
						} else {
							$value[$source] = $eElement[$source];
						}

					}

				}

				$values[] = $value;

			}

			return $values;

		}

	}

	/**
	 * Add a property with the given value for all elements present in the element group
	 *
	 * @param string $property Property name
	 * @param mixed $value Property value (may be a scalar value or a callack function taking the element in parameter)
	 */
	public function setColumn(string $property, $value): \Collection {

		foreach($this as $key => $entry) {

			if($this->depth > 1) {
				$entry->addColumn($property, $value);
			} else {

				if(is_closure($value)) {
					$this[$key][$property] = $value($entry);
				} else {
					$this[$key][$property] = $value;
				}

			}

		}

		return $this;

	}

	/**
	 * Drop a property
	 *
	 * @param string $property Property name
	 */
	public function removeColumn(string $property): \Collection {

		foreach($this as $key => $entry) {

			if($this->depth > 1) {
				$entry->removeColumn($property);
			} else {
				unset($this[$key][$property]);
			}

		}

		return $this;

	}

	/*
	 * Get collection keys
	 *
	 * @return array
	 */
	public function getKeys(): array {

		$keys = [];

		foreach($this as $key => $value) {
			$keys[] = $key;
		}

		return $keys;

	}

	/*
	 * Get IDs in the element group
	 *
	 * @return array
	 */
	public function getIds(): array {
		return $this->getColumn('id');
	}

	/*
	 * Reindex a collection
	 *
	 * @return array
	 */
	public function reindex(mixed $index): Collection {

		if($this->depth > 1) {
			throw new Exception('Can not reindex collection with depth > 1');
		}

		if($index instanceof Closure) {
			$callback = $index;
		} else {

			$index = (array)$index;
			$lastProperty = last($index);

			$callback = function(Element $e) use($index, $lastProperty): mixed {

				$currentElement = $e;

				foreach($index as $property) {

					if($currentElement->offsetExists($property) === FALSE) {
						throw new Exception('Invalid property');
					}

					if($currentElement[$property] instanceof Element) {
						$key = $currentElement[$property]['id'] ?? NULL;
					} else {
						$key = $currentElement[$property];
					}

					if($key === NULL) {
						$key = '';
					}

					$currentElement = $currentElement[$property];

					if($property === $lastProperty) {
						return $key;
					}

				}

			};

		}


		$c = new Collection();
		$c->setDepth(2);

		foreach($this as $e) {

			$key = $callback($e) ?? '';

			$c[$key] ??= new Collection();
			$c[$key][] = $e;

		}

		return $c;

	}

	/*
	 * Get values of a property in the element group
	 * Option can be a boolean (returns references) or an int (array level)
	 *
	 * @return array
	 */
	public function getColumn(string $property, ?string $index = NULL): array {

		return $this->getRecursiveColumn($property, $index);

	}

	/**
	 * Idéalement :
	 * $cUser = user\User::newCollection();
	 * Produit un objet du type UserCollection extends Collection
	 * Du coup on peut supprimer cette méthode et rendre getColumn() plus intelligent pour fabriquer automatiquement une collection pertinente
	 */
	public function getColumnCollection(string $property, ?string $index = NULL, int $depth = 1): \Collection {

		$values = $this->getRecursiveColumn($property, $index);

		$collection = new Collection($values);

		if($depth > 1) {
			$collection->setDepth($depth);
		}

		return $collection;

	}

	private function getRecursiveColumn(string $property, ?string $index): array {

		if($this->depth > 1) {

			$values = [];

			foreach($this as $entry) {

				if($index !== NULL) {

					$values += $entry->getRecursiveColumn($property, $index);

				} else {

					$values = array_merge(
						$values,
						$entry->getRecursiveColumn($property, $index)
					);

				}

			}

			return $values;

		} else {

			if($index !== NULL) {

				$values = [];

				foreach($this as $e) {

					if(isset($e[$property])) {

						if(isset($e[$index]))  {
							if($e[$index] instanceof Element) {
								$key = $e[$index]['id'];
							} else {
								$key = $e[$index];
							}
						} else {
							$key = NULL;
						}

						$values[$key] = $e[$property];

					}

				}

				return $values;

			} else {

				$values = [];

				foreach($this as $e) {
					if(isset($e[$property])) {
						$values[] = $e[$property];
					}
				}

				return $values;

			}

		}

	}

	/**
	 * Applies the diff between two Collections
	 * according to IDs from the elements
	 *
	 * @param Collection $c
	 * @param bool $preserveKeys Keep the keys if asked. Otherwise, keys are reset
	 * @return \Collection
	 */
	public function diff(Collection $c, bool $preserveKeys = TRUE): Collection {

		$keys1 = $this->getIds();
		$keys2 = $c->getIds();

		$diff = array_diff($keys1, $keys2);

		$newC = new Collection();

		foreach($this as $key => $eElement) {
			if(in_array($eElement['id'], $diff)) {
				if($preserveKeys) {
					$newC[$key] = $eElement;
				} else {
					$newC->append($eElement);
				}
			}
		}

		return $newC;

	}

	/**
	 * Keep only $number elements in the collection
	 * DO NOT USE WITH LARGE COLLECTIONS
	 *
	 * @return bool TRUE if elements have been removed, FALSE otherwise
	 */
	public function cut(int $number, int $depth = 1): bool {

		$cut = FALSE;
		$this->recursiveCut($this, $number, $depth, $cut);

		return $cut;

	}

	protected function recursiveCut(\Collection $c, int &$number, int $depth, bool &$cut): void {

		if($depth > 1) {
			foreach($c as $offset => $newC) {
				if($number === 0) {
					$c->offsetUnset($offset);
					$cut = TRUE;
				} else {
					$this->recursiveCut($newC, $number, $depth - 1, $cut);
				}
			}
			return;
		}

		if($c->count() <= $number) {
			$number .= $c->count();
			return;
		}

		for($c->rewind(); $c->valid(); ) {

			if($number === 0) {
				$c->offsetUnset($c->key());
				$cut = TRUE;
			} else {
				$number--;
				$c->next();
			}

		}

	}

	/**
	 * Apply a closure to each element of the collection
	 *
	 * @param callable $closure
	 */
	public function map(callable $closure, int $depth = 1): Collection {

		$this->recursiveMap($this, $closure, $depth);

		return $this;

	}

	protected function recursiveMap(\Collection $c, callable $closure, int $depth): void {

		if($depth > 1) {
			foreach($c as $newC) {
				$this->recursiveMap($newC, $closure, $depth - 1);
			}
			return;
		}

		foreach($c as $e) {
			$closure($e);
		}

	}

	/**
	 * Apply a closure to each element of the collection
	 *
	 * @param callable $closure
	 */
	public function expects(array $keys, ?callable $callback = NULL): Collection {

		return $this->map(function(Element $e) use($keys, $callback) {
			$e->expects($keys, $callback);
		});

	}

	/**
	 * Filter collection
	 *
	 * Retire sur place, y compris à travers un clone dont le stockage est partagé : pour garder
	 * l'original, find() rend une collection neuve.
	 */
	public function filter(Closure $filter, int $depth = 1): Collection {

		if($depth > 1) {

			for($this->rewind(); $this->valid(); ) {

				$c = $this
					->current()
					->filter($filter, $depth - 1);

				if($c->empty()) {
					$this->offsetUnset($this->key());
				} else {
					$this->next();
				}

			}

		} else {

			for($this->rewind(); $this->valid(); ) {

				if($filter($this->current()) === FALSE) {
					$this->offsetUnset($this->key());
				} else {
					$this->next();
				}

			}

		}

		return $this;

	}

	public function contains(?Closure $filter = NULL, int $depth = 1): bool {
		return $this->find($filter, limit: 1, depth: $depth)->notEmpty();
	}

	public function findByKeys(array|Collection $keys, bool $preserveKeys = TRUE): Collection|Element {

		if($keys instanceof Collection) {
			$keys = $keys->getIds();
		}

		$c = new Collection();

		foreach($keys as $key) {

			if($preserveKeys) {
				$c[$key] = $this[$key];
			} else {
				$c[] = $this[$key];
			}

		}

		return $c;

	}

	/**
	 * Find from collection with filter
	 *
	 * @param mixed $filter Filter can be a callable function (works just as array_filter do) or a property name (delete entries which do not define the given property)
	 */
	public function find(?Closure $filter = NULL, bool $preserveKeys = TRUE, ?string $indexByKey = NULL, ?int $limit = NULL, int $depth = 1, bool $clone = TRUE, Closure|Element $default = new AnonymousElement()): Collection|Element {

		if($depth > 1) {

			$object = new Collection();

			$this->map(fn($e) => $object[] = $e, depth: $depth);

		} else {

			$object = clone $this;

		}

		$c = new Collection();
		$found = 0;

		foreach($object as $key => $e) {

			if($filter === NULL or $filter($e)) {

				if($indexByKey) {

					if($e[$indexByKey] instanceof Element) {
						$keyIndex = $e[$indexByKey]->notEmpty() ? $e[$indexByKey]['id'] : NULL;
					} else {
						$keyIndex = $e[$indexByKey];
					}

					$c[$keyIndex] = $clone ? clone $e : $e;

				} else if($preserveKeys) {
					$c[$key] = $clone ? clone $e : $e;
				} else {
					$c[] = $clone ? clone $e : $e;
				}
				$found++;
				if($limit === 1 and $found === 1) {
					return $clone ? clone $e : $e;
				} else if($limit !== NULL and $found >= $limit) {
					return $c;
				}

			}

		}

		if($c->empty() and $limit === 1) {
			return is_closure($default) ? $default() : $default;
		}

		return $c;

	}

	public function findById(int|Element $id, int $depth = 1, Closure|Element $default = new AnonymousElement()): Element {

		if($id instanceof Element) {
			if($id->exists()) {
				$id = $id['id'];
			} else {
				return is_closure($default) ? $default() : $default;
			}
		}

		return $this->find(fn($e) => $e['id'] === $id, limit: 1, depth: $depth, default: $default);

	}

	/**
	 * Reduce collection
	 *
	 */
	public function reduce(Closure $callback, mixed $value): mixed {

		for($this->rewind(); $this->valid(); ) {
			$value = $callback($this->current(), $value);
			$this->next();
		}

		return $value;

	}

	/**
	 * Sum values of a property
	 *
	 */
	public function sum(string|Closure $property, int $depth = 1): mixed {

		if($depth > 1) {
			return $this->reduce(fn($c, $n) => $c->sum($property, $depth - 1) + $n, 0);
		} else {
			if(is_string($property)) {
				return $this->reduce(fn($e, $n) => $n + $e[$property], 0);
			} else {
				return $this->reduce(fn($e, $n) => $n + $property($e), 0);
			}
		}

	}

	/**
	 * Count value by depth
	 *
	 */
	public function countByDepth(?int $depth = NULL): int {

		if($this->empty()) {
			return 0;
		}

		$depth ??= $this->getDepth();

		if($depth > 1) {
			$n = 0;
			foreach($this as $c) {
				$n += $c->countByDepth($depth - 1);
			}
			return $n;
		} else {
			return $this->count();
		}

	}

	/**
	 * Returns TRUE if at least one $number matches the callback
	 *
	 */
	public function match(Closure $callback, int $number = 1): bool {

		$count = 0;

		for($this->rewind(); $this->valid(); ) {

			$match = $callback($this->current());

			if($match === TRUE and ++$count >= $number) {
				return TRUE;
			}

			$this->next();

		}

		return FALSE;

	}

	/**
	 * @param Element $e
	 * @return bool
	 */
	public function search(Element $e, Element $default = new AnonymousElement()): Element {

		if($e->empty()) {
			return $default;
		}

		$e->expects(['id']);

		foreach($this as $eTested) {

			if($eTested->empty()) {
				continue;
			}

			$eTested->expects(['id']);

			if($eTested['id'] === $e['id']) {
				return $eTested;
			}

		}

		return $default;

	}

	/**
	 * Transform to string
	 *
	 */
	public function makeString(Closure $callback): string {

		$output = '';

		for($this->rewind(); $this->valid(); ) {
			$output .= $callback($this->current(), $this->key());
			$this->next();
		}

		return $output;

	}

	/**
	 * Transform to array
	 *
	 */
	public function makeArray(Closure $callback, bool $preserveKeys = FALSE): array {
		return self::makeIterator($callback, [], $preserveKeys);
	}

	/**
	 * Transform to collection
	 *
	 */
	public function makeCollection(Closure $callback, bool $preserveKeys = FALSE): Collection {
		return self::makeIterator($callback, new Collection(), $preserveKeys);
	}

	protected function makeIterator(Closure $callback, mixed $output, bool $preserveKeys): mixed {

		for($this->rewind(); $this->valid(); ) {

			$key = $preserveKeys ? $this->key() : NULL;
			$value = $callback($this->current(), $key);

			if($key === NULL) {
				$output[] = $value;
			} else {
				$output[$key] = $value;
			}

			$this->next();
		}

		return $output;

	}

	/**
	 * Returns first element of a collection
	 */
	public function first(): Element|Collection|null {

		$this->rewind();
		return $this->current();

	}

	/**
	 * Returns last element of a collection
	 */
	public function last(): Element|Collection|null {

		$this->seek($this->count() - 1);
		return $this->current();

	}

	/*
	 * Sort the collection using a property
	 */
	public function sort(Closure|array|string $properties, bool $binary = FALSE, bool $natural = FALSE, int $depth = 1): Collection {

		if($depth > 1) {
			foreach($this as $c) {
				$c->sort($properties, $binary, $natural, $depth - 1);
			}
		} else {
			$this->applySorting($properties, $binary, $natural);
		}

		return $this;

	}

	private function applySorting(Closure|array|string $properties, bool $binary = FALSE, bool $natural = FALSE): void {

		if(is_closure($properties) === FALSE) {

			$properties = (array)$properties;
			$order = [];

			foreach($properties as $key => $value) {

				// ['id' => SORT_ASC|SORT_DESC]
				if($value === SORT_ASC or $value === SORT_DESC) {

					$order[] = [
						(array)$key,
						$value
					];


				// ['id']
				} else if(is_string($value)) {

					$order[] = [
						(array)$value,
						SORT_ASC
					];

				// [fn() =>]
				} else if(is_closure($value)) {

					$order[] = [
						$value,
						NULL
					];

				// ['user' => ['login' => SORT_ASC|SORT_DESC]]
				} else if(is_array($value)) {

					$list = [$key];
					$sort = SORT_ASC;

					while($value) {

						$subValue = first($value);

						if($subValue === SORT_ASC or $subValue === SORT_DESC) {

							$list[] = array_key_first($value);
							$sort = $subValue;
							break;

						} if(is_string($subValue)) {

							$list[] = $subValue;
							break;

						} else if(is_array($subValue)) {

							// La clé du niveau qu'on quitte, et non celle du suivant, que le tour d'après lira.
							$list[] = array_key_first($value);
							$value = $subValue;

						} else {
							break;
						}

					}

					$order[] = [
						$list,
						$sort
					];
				}

			}

			if($binary) {
				if($natural) {
					$compare = fn($value1, $value2) => strnatcmp(mb_strtolower($value1), mb_strtolower($value2));
				} else {
					$compare = fn($value1, $value2) => strcmp($value1, $value2);
				}
			} else {
				if($natural) {
					$compare = fn($value1, $value2) => L::getCollator(TRUE)->compare($value1, $value2);
				} else {
					$compare = fn($value1, $value2) => strcmp($value1, $value2);
				}
			}

			$callback = function($eElement1, $eElement2) use($order, $compare) {

				foreach($order as [$list, $sort]) {

					if($list instanceof Closure) {

						$result = $list->call($this, $eElement1, $eElement2);

						if($result === 0) {
							continue;
						} else {
							return $result;
						}

					} else {

						$value1 = $eElement1;
						$value2 = $eElement2;

						foreach($list as $property) {
							$value1 = $value1[$property] ?? NULL;
							$value2 = $value2[$property] ?? NULL;
						}

						if(
							($value1 instanceof Element === FALSE and $value1 === $value2) or
							($value1 instanceof Element and $value1->is($value2))
						) {
							continue;
						}

						$mul = ($sort === SORT_ASC) ? 1 : -1;

						if($value1 === NULL) {
							return -1 * $mul;
						} else if($value2 === NULL) {
							return 1 * $mul;
						} else if(is_string($value1)) {
							return $compare($value1, $value2) * $mul;
						} else if(is_scalar($value1)) {
							return ($value1 < $value2 ? -1 : 1) * $mul;
						} else if($value1 instanceof Element) {
							$id1 = $value1->exists() ? $value1['id'] : NULL;
							$id2 = $value2->exists() ? $value2['id'] : NULL;
							return ($id1 < $id2 ? -1 : 1) * $mul;
						}

					}

				}

				return 0;

			};

		} else {
			$callback = $properties;
		}

		$this->uasort($callback);

	}

	/**
	 * Validate tests
	 */
	public function validate(...$tests): \Collection {

		if($this->empty()) {
			throw new NotExistsAction('Empty collection');
		}

		$this->map(function(Element $e) use($tests) {
			$e->validate(...$tests);
		});

		return $this;

	}

	public function validateOffset(mixed $offset): Collection {

		if($offset instanceof Element) {
			if($offset->exists()) {
				$value = $offset['id'];
			} else {
				$value = NULL;
			}
		} else {
			$value = $offset;
		}

		if($this->offsetExists($value) === FALSE) {
			throw new NotExistsAction('Offset does not exist');
		}

		return $this;

	}

	public function validateProperty(string $property, mixed $value): Collection {

		foreach($this as $e) {
			$e->validateProperty($property, $value);
		}

		return $this;

	}

	/**
	 * Same as array + operator
	 * @return Collection the union of the current Collection with the Collection sent as parameter
	 */
	public function appendCollection(Collection $cElement): Collection {

		foreach($cElement as $key => $eElement) {
			if($this->offsetExists($key) === FALSE) {
				$this[$key] = $eElement;
			}
		}

		return $this;

	}

	public function mergeCollection(Collection $cElement, bool $reindex = TRUE): Collection {

		foreach($cElement as $key => $eElement) {

			if($reindex) {
				$this[] = $eElement;
			} else {
				$this[$key] = $eElement;
			}
		}

		return $this;

	}

	/**
	 * Chunk group
	 *
	 * @param int $size
	 */
	public function chunk($size): array {

		$splits = [];

		for($i = 0; $i < $size; $i++) {
			$splits[$i] = new Collection();
		}

		$position = 0;

		foreach($this as $eElement) {

			$splits[$position % $size][] = $eElement;
			$position++;

		}

		return $splits;

	}

	/**
	 * Creates a new collection from an array of values
	 *
	 * @param array $values Array of IDs
	 * @param string $element Element name
	 */
	public static function fromArray(array $values, string $element): Collection {

		$cElement = new Collection();

		foreach($values as $key => $value) {

			$eElement = cast($value, $element);

			if($eElement->notEmpty()) {
				$cElement[$key] = $eElement;
			}

		}

		return $cElement;

	}

}

/**
 * Handle set fields
 */
class Set {

	protected int $values;

	/**
	 * Build Set with initial values
	 *
	 */
	public function __construct($values = 0) {

		if(is_array($values)) {
			$this->values = 0;
			foreach($values as $value) {
				$this->values |= (int)$value;
			}
		} else {
			$this->values = (int)$values;
		}

	}

	public static function fromArray(array $values): Set {

		$set = new Set();
		foreach($values as $value) {
			$set->value($value, 1);
		}

		return $set;

	}

	/**
	 * Manipulate content of Set using bit value
	 *
	 * @param int $bit Bit value (1, 2, 4, 8, 16, ...)
	 * @param bool $newValue New value (TRUE/FALSE) or NULL to get current value
	 * @return \Set
	 */
	public function value(int $bit, ?bool $newValue = NULL): Set|bool {

		if($newValue === TRUE) {

			$this->values = $this->values | $bit;
			return $this;

		} else if($newValue === FALSE) {

			$this->values = $this->values & ~$bit;
			return $this;

		} else {
			return (bool)($this->values & $bit);
		}

	}

	/**
	 * Manipulate content of Set using bit position
	 *
	 * @param int $position Bit position (0, 1, 2, 3, 4, ...)
	 * @param bool $newValue New value (TRUE/FALSE) or NULL to get current value
	 * @return \Set
	 */
	public function position(int $position, ?bool $newValue = NULL): Set|bool {

		$bit = pow(2, $position);

		return $this->value($bit, $newValue);

	}

	/**
	 * Set all Set values to 0
	 */
	public function reset(): void {
		$this->values = 0;
	}

	/**
	 * Get Set full value
	 *
	 * @return int
	 */
	public function get(): int {
		return $this->values;
	}

	/**
	 * Get all values
	 *
	 * @return int
	 */
	public function values(): array {

		if($this->values === 0) {
			return [];
		}

		$values = [];
		$bits = log($this->values, 2);

		for($i = 0; $i <= $bits; $i++) {
			if($this->position($i)) {
				$values[] = pow(2, $i);
			}
		}

		return $values;
	}

	public function __toString(): string {
		return (string)$this->values;
	}

	public function __sleep(): array {
		return ['values'];
	}

}


/**
 * Describe Element
 */
abstract class Element extends ArrayObject {

	private mixed $ghost = NULL;

	private bool $quick = FALSE;
	private static array $quickAttributes = [];

	public function setGhost(mixed $value) {
		$this->ghost = $value;
	}

	public function add(array $properties): Element {

		foreach($properties as $key => $value) {
			if($this->offsetExists($key) === FALSE) {
				$this->offsetSet($key, $value);
			}
		}

		return $this;

	}

	public function merge(array|ArrayObject $properties): Element {

		foreach($properties as $key => $value) {
			$this->offsetSet($key, $value);
		}

		return $this;

	}

	public function is(Element $e): bool {

		return (
			$e instanceof $this and (
				($e->empty() and $this->empty()) or
				($this['id'] ?? NULL) === ($e['id'] ?? NULL)
			)
		);

	}

	public function setQuick(bool $quick): void {
		$this->quick = $quick;
	}

	public function isQuick(): bool {
		return $this->quick;
	}

	public function quick(string $property, string $html, string $class = 'util-quick', array $validate = ['assertUpdate']): string {

		foreach($validate as $check) {
			if($this->$check() === FALSE) {
				return $html;
			}
		}

		$h = '<div class="'.$class.'" '.$this->getQuickAttributes($property).'>';
			$h .= $html;
		$h .= '</div>';

		return $h;

	}

	public static function setQuickAttribute(string $argument, mixed $value): void {
		self::$quickAttributes[$argument] = $value;
	}

	public function getQuickAttributes(string $property): string {

		Asset::js('util', 'form.js');
		Asset::css('util', 'form.css');

		$attributes = [
			'onclick' => 'Lime.Quick.start("'.str_replace('\\', '/', $this->getModule()).'", this)',
			'post-id' => $this['id'],
			'post-property' => $property
		];

		if(self::$quickAttributes) {

			foreach(self::$quickAttributes as $argument => $value) {
				$attributes['post-'.$argument] = $value;
			}

			$attributes['post-list'] = implode(',', array_keys(self::$quickAttributes));

		}

		return attrs($attributes);

	}

	public function getModule(): string {
		return get_class($this);
	}

	// Un délégué est chargé par la clé étrangère de sa ligne parente : il porte toujours son id, et
	// notEmpty() ne dit rien de ses autres colonnes, que seule la sélection charge.
	public function notEmpty(): bool {
		return ($this->count() > 0);
	}

	public function empty(): bool {
		return ($this->count() === 0);
	}

	public function exists(): bool {

		return (
			$this->offsetExists('id') and
			$this['id'] !== NULL
		);

	}

	/**
	 * Check validity of an element
	 *
	 * Ne joue que les tests nommés. ModulePage nomme la paire CRUD (['canUpdate', 'acceptUpdate']), mais
	 * une page qui nomme sa propre liste, validate('canWrite', 'acceptStatusCanceled'), ne passe par aucun
	 * autre accept* : une règle d'état ne protège ce chemin que si sa liste la nomme.
	 */
	public function validate(...$tests): Element {

		if(array_key_exists('onFail', $tests)) {

			$onFail = $tests['onFail'];
			unset($tests['onFail']);

		} else {
			$onFail = NULL;
		}

		if($this->empty()) {

			if($onFail === NULL) {

				if($this->ghost !== NULL) {
					throw new NotExistsAction($this->getModule().' #'.$this->ghost);
				} else {
					throw new NotExistsAction($this->getModule());
				}

			} else {
				$onFail(NULL);
			}

		}

		$onFail ??= function(?string $test) {

			if($test !== NULL and str_starts_with($test, 'can')) {
				throw new NotAllowedAction($this, error: 'can not validate '.$test);
			} else {
				throw new NotExpectedAction($this, error: 'can not validate '.$test);
			}

		};

		foreach($tests as $test) {

			if(is_closure($test)) {

				if($test($this) === FALSE) {
					$onFail('closure');
				}

			} else if(is_bool($test)) {

				if($test === FALSE) {
					$onFail('bool');
				}

			} else {

				if(method_exists($this, $test) === FALSE) {
					throw new Exception('Invalid test \''.$test.'\' for class \''.get_class($this).'\'');
				}

				if($this->$test() === FALSE) {
					$onFail($test);
				}

			}

		}

		return $this;

	}

	/**
	 * Check validity of a property of an element
	 */
	public function validateProperty(string $property, mixed $value): Element {

		if($this->empty()) {

			if($this->ghost !== NULL) {
				throw new NotExistsAction($this->getModule().' #'.$this->ghost);
			} else {
				throw new NotExistsAction($this->getModule());
			}

		}

		$this->expects($property);

		if($this->model()->getPropertyToModule($property)) {

			if(
				$this[$property] instanceof Element === FALSE or
				$value instanceof Element === FALSE or
				$this[$property]->empty() !== $value->empty() or
				(
					$this[$property]->notEmpty() and
					(($this[$property]['id'] ?? NULL) !== ($value['id'] ?? NULL))
				)
			) {
				throw new NotExpectedAction($this, error: 'can not validate property '.$property);
			}

		} else {

			if($this[$property] !== $value) {
				throw new NotExpectedAction($this, error: 'can not validate property '.$property);
			}

		}

		return $this;

	}

	/**
	 * Get properties for further selection
	 */
	public static function getSelection(): array {
		return [];
	}

	/**
	 * Une clé absente a deux causes que le message ne distingue pas : aucune sélection ne l'a chargée
	 * (colonne hors de la sélection, ou hors du modèle quand le .yml est en avance), ou c'est une
	 * propriété d'exécution que la page pose et que l'appelant n'a pas posée. Chercher qui pose la clé
	 * avant de régénérer un modèle.
	 */
	public function expects(string|array $keys, ?callable $callback = NULL): Element {

		$lacks = $this->checkExpected($this, (array)$keys);

		if($lacks) {

			if($callback !== NULL) {
				$callback($this);
			} else {
				throw new ElementException(p(
					'Property '.implode(', ', $lacks).' of Element '.get_class($this).' is not set',
					'Properties '.implode(', ', $lacks).' of Element '.get_class($this).' are not set',
					count($lacks)
				));
			}
		}

		return $this;

	}

	public function extracts($keys, ?Closure $callback = NULL): array {

		$output = [];

		foreach($keys as $key) {
			if($this->offsetExists($key)) {
				$value = $this->offsetGet($key);
				$output[$key] = ($callback !== NULL) ? $callback($value) : $value;
			}
		}

		return $output;

	}

	public function getCopy($keys, ?Closure $callback = NULL): self {

		$output = new $this;

		foreach($keys as $key) {
			if($this->offsetExists($key)) {
				$value = $this->offsetGet($key);
				$output[$key] = ($callback !== NULL) ? $callback($value) : $value;
			}
		}

		return $output;

	}

	private function checkExpected(Element $e, array $keys): array {

		$lacks = [];

		foreach($keys as $key => $value) {

			if(is_string($key)) {
				$property = $key;
				$value = (array)$value;
			} else if(is_string($value)) {
				$property = $value;
			} else {
				throw new Exception('Invalid keys');
			}

			if($e->offsetExists($property) === FALSE) {
				$lacks[] = $property;
			} else if(is_array($value)) {

				if($e[$key] instanceof Element) {
					$result = $this->checkExpected($e[$key], $value);
				} else if(is_array($e[$key])) {
					$result = array_expects($e[$key], $value, function(array $result) {
						return $result;
					});
				} else {
					$result = ['Invalid type'];
				}

				if($result) {
					$lacks[] = $property.'['.implode(', ', $result).']';
				}

			}

		}

		return $lacks;

	}

	public static function fail(string|FailException $failName, array $arguments = [], ?string $wrapper = NULL): bool {
		throw new Exception('Invalid call of Element::fail()');
	}

	/**
	 * Create an element with properties $properties using $input (ie: $_POST)
	 *
	 * @param array $properties List of properties (ie: ['email', 'sex' (in $input) => 'gender' (element property)]
	 * @param array $input List of values indexed by property name
	 * @param \PropertyList $l
	 */
	private static array $declaredByClass = [];

	private static function declaredFor(string $class): array {

		if(array_key_exists($class, self::$declaredByClass) === FALSE) {

			if(method_exists($class, 'properties')) {

				$l = new \PropertyList();
				$class::properties($l);
				self::$declaredByClass[$class] = $l->getDeclared();

			} else {
				self::$declaredByClass[$class] = [];
			}

		}

		return self::$declaredByClass[$class];

	}

	/**
	 * $properties dit ce qui s'écrit, pas ce que l'écran affiche : une propriété de la liste que le
	 * formulaire ne poste pas (un média téléversé par sa propre action, un champ masqué derrière un
	 * drapeau) est lue à NULL puis convertie dans le type de la colonne. Elle est effacée si la colonne
	 * est nullable, sinon ramenée sans erreur à la valeur vide de son type (FALSE, 0, '', []), à moins
	 * que le schéma (un enum, un élément, une date, une borne) ou une étape de properties() la refuse.
	 */
	public function build(array $properties, array $input, \PropertyList $l = new \PropertyList()): void {

		$l->adoptDeclared(self::declaredFor(static::class));
		$l->input = $input;

		$model = $this->model();

		$callbackWrapper = $l->getWrapper() ?? fn($property) => $property;

		$l->setProperties($properties);

		foreach($properties as $property) {

			// Une propriété sans description reçoit la chaîne par défaut du modèle, et rien si elle
			// n'est pas dans le modèle : c'est ce que compile() rend d'une Property vide.
			$declared = $l->getDeclared($property) ?? new Property($property);

			// L'argument est l'étape 0 : il choisit la clé lue dans l'entrée, et ne tourne nulle part ailleurs.
			$argument = ($declared->argument === NULL) ? $property : ($declared->argument)($property);

			// Un champ absent de l'entrée vaut NULL et toutes les étapes tournent quand même : une étape qui
			// ne doit pas juger l'absence teste $value === NULL, la clé lue n'étant pas toujours $property.
			$value = $input[$argument] ?? NULL;
			$l->addNew($property);

			$hasModel = $model->hasProperty($property);

			// Un nombre écrit à la française (« 6,5 », « 1 000 ») se réécrit avant la première étape :
			// celles que properties() déclare jugent alors le nombre que le cast et le contrôle liront.
			if($hasModel) {
				\Filter::normalize($model->getProperty($property), $value);
			}

			// Une valeur qui ne se convertit pas dans le type de la colonne est un refus du schéma : il
			// porte le nom du contrôle, `propriete.check`, dont le message existe déjà. Sous une clé d'API,
			// un texte sans balise qu'un champ editor convertirait à NULL est refusé en `propriete.html` :
			// fromHtml() ne garde que du HTML, et le texte serait perdu sans rien dire à l'appelant.
			$castRefused = NULL;

			$defaultCast = function(&$value) use($model, $property, $l, &$castRefused): bool {

				if($model->cast($property, $value) === FALSE) {
					$castRefused = 'check';
					return FALSE;
				}

				if(strpos($model->getPropertyType($property), 'editor') === 0) {

					$html = $value;
					$value = new \editor\XmlLib()->fromHtml($html);

					if(
						$l->origin === 'api' and
						$value === NULL and
						$html !== NULL and
						trim(strip_tags($html)) !== ''
					) {
						$castRefused = 'html';
						return FALSE;
					}

				}

				return TRUE;

			};

			$defaultCheck = function(&$value) use($model, $property): bool {

				if(
					$model->isPropertyNull($property) and
					$value === ''
				) {
					$value = NULL;
				}

				return $model->check($property, $value);

			};

			$defaultSet = function($value) use($property) {
				$this[$property] = $value;
			};

			$defaultEnd = function($value) { };

			// L'ordre est celui que Property::compile() a fixé, et chaque emplacement standard laissé
			// vide reçoit son défaut. `prepare` n'est pas une étape : son défaut (la conversion de
			// l'éditeur) est absorbé par celui de cast.
			$callbacksSelected = [];

			foreach($declared->compile($hasModel) as $name => $callback) {

				$callbacksSelected[$name] = $callback ?? match($name) {
					$property.'.cast' => $defaultCast,
					$property.'.check' => $defaultCheck,
					$property.'.set' => $defaultSet,
					$property.'.end' => $defaultEnd,
				};

			}

			// Check callback function
			$wrapper = $callbackWrapper($property);
			$success = TRUE;

			foreach($callbacksSelected as $name => $callback) {

				$onError = function() use($name, $property, $value, $wrapper, &$success, &$castRefused) {

					$class = $this->getModule();
					$error = $castRefused ?? explode('.', $name)[1];

					$class::fail($property.'.'.$error, [$this, $value], wrapper: $wrapper);

					$success = FALSE;

				};

				// Une closure d'une déclaration statique n'a pas de $this : on le lui donne ici, par bind()
				// et non call(), qui ne passe pas la référence de $value. Elle GARDE sa propre portée de
				// classe : un `self::` écrit dans un Lib doit rester le Lib, et la porter sur l'élément
				// rendait « Call to undefined method ». Une closure déjà liée (héritée d'un build()
				// d'instance) n'est pas touchée.
				$reflection = new ReflectionFunction($callback);

				if($reflection->getClosureThis() === NULL) {
					$callback = Closure::bind($callback, $this, $reflection->getClosureScopeClass()?->getName() ?? 'static') ?? $callback;
				}

				try {

					if($callback($value, $l, $wrapper) === FALSE) {
						if($l->hasStop($property)) {
							throw new BuildElementError();
						} else {
							throw new BuildPropertyError();
						}
					}

				} catch(PropertySkip) {
					// L'abandon précède le set : la propriété n'a pas reçu sa valeur
					$success = FALSE;
					break;
				} catch(PropertySuccess) {
				} catch(FailException $e) {
					$l->addInvalid($property, $e->getMessage());
					Fail::log($e, wrapper: $wrapper);
					$success = FALSE;
					break;
				} catch(BuildPropertyError) {
					$l->addInvalid($property, $this->getModule().'::'.$name);
					$onError();
					break;
				} catch(BuildElementError) {
					$l->addInvalid($property, $this->getModule().'::'.$name);
					$onError();
					return;
				}

			}

			if($success) {
				$l->addBuilt($property);
			}

		}

	}

	// Le champ se nomme d'après la propriété (value[12]). Si le parent poste aussi value à plat, PHP garde
	// le dernier des deux dans l'ordre du formulaire : le build() du parent reçoit un tableau, ou rien ici.
	public function buildIndex(array $properties, array $input, $index, \PropertyList $l = new \PropertyList()): void {

		$l->setWrapper(function(string $property) use($index) {
			return $property.'['.$index.']';
		});

		$values = [];

		foreach($input as $inputProperty => $inputValues) {
			if(is_array($inputValues) and array_key_exists($index, $inputValues)) {
				$values[$inputProperty] = $inputValues[$index];
			}
		}
		
		$this->build(
			$properties,
			$values,
			$l
		);

	}

	public function buildProperty(string $property, $value, \PropertyList $l = new \PropertyList()): void {

		$this->build(
			[$property],
			[$property => $value],
			$l
		);

	}

	public function format(string $property, array $options = []): ?string {
		return '';
	}

	public function canCreate(): bool {
		return $this->canWrite();
	}

	// Abstraite pour qu'aucun module n'hérite d'un droit de lecture, et donc d'écriture, par défaut.
	abstract public function canRead(): bool;

	public function canWrite(): bool {
		return $this->canRead();
	}

	public function canUpdate(): bool {
		return $this->canWrite();
	}

	public function canDelete(): bool {
		return $this->canWrite();
	}

	public function acceptCreate(): bool {
		return $this->acceptWrite();
	}

	public function acceptRead(): bool {
		return TRUE;
	}

	// Aussi interrogé sur l'élément neuf d'une création, par acceptCreate() : une règle qui y lit une
	// propriété que cet élément n'a pas encore casse la création, sauf acceptCreate() redéfini.
	public function acceptWrite(): bool {
		return $this->acceptRead();
	}

	public function acceptUpdate(): bool {
		return $this->acceptWrite();
	}

	public function acceptDelete(): bool {
		return $this->acceptWrite();
	}

	// Pour décider d'un affichage : ModulePage valide la paire can/accept, et un écran qui n'interroge
	// que can* propose un geste que l'action refusera.
	public function assertCreate(): bool {
		return $this->acceptCreate() and $this->canCreate();
	}

	public function assertAll(): bool {
		return TRUE;
	}

	public function assertRead(): bool {
		return $this->assertAll() and $this->acceptRead() and $this->canRead();
	}

	public function assertWrite(): bool {
		return $this->assertAll() and $this->acceptWrite() and $this->canWrite();
	}

	public function assertUpdate(): bool {
		return $this->assertAll() and $this->acceptUpdate() and $this->canUpdate();
	}

	public function assertDelete(): bool {
		return $this->assertAll() and $this->acceptDelete() and $this->canDelete();
	}

	public function __toString(): string {
		return $this->toString(3);
	}

	public function toString(?int $recursion = NULL): string {

		$output = 'Element::'.get_class($this).' ('."\n";

		foreach($this as $key => $value) {

			if($value instanceof Element or $value instanceof Collection) {
				if($recursion > 0) {
					$string = trim($value->toString($recursion === NULL ? NULL : $recursion - 1));
				} else {
					$string = get_class($value).' [...]';
				}
				$output .= '   ['.$key.'] => '.str_replace("\n", "\n   ", $string)."\n";
			} else {

				switch(gettype($value)) {

					case 'integer' :
					case 'double' :
						$displayValue = $value."\n";
						break;

					case 'boolean' :
						$displayValue = ($value ? 'TRUE' : 'FALSE')."\n";
						break;

					case 'NULL' :
						$displayValue = 'NULL'."\n";
						break;

					case 'string' :
						$displayValue = '"'.$value.'"'."\n";
						break;

					default :
						ob_start();
						var_dump($value);
						$displayValue = ob_get_clean();

				}
				$output .= '   ['.$key.'] => '.$displayValue;
			}

		}

		$output .= ')'."\n";

		return $output;

	}

}

/**
 * Élément qui n'appartient à aucun module : le défaut d'une recherche infructueuse, celui d'une
 * propriété de type element, et les tests qui simulent ce qu'un Lib rendrait. Sans module, il n'a
 * aucune règle de lecture à offrir et la refuse.
 */
final class AnonymousElement extends Element {

	public function canRead(): bool {
		return FALSE;
	}

}

/**
 * Le pipeline d'une seule propriété, déclaré par `$l->property('nom', fn(Property $p) => $p->…)`.
 *
 * Chaque méthode dit à la fois la position et l'intention : `replace*` prend la place d'une étape
 * qui a un défaut (argument, cast, check, set, end), `add*` insère une étape nommée à une position
 * précise, sans rien éteindre. Le nom d'une étape ajoutée devient la clé de son échec
 * (`Module::propriete.nom` dans Alert.u.php). L'argument est l'étape 0, il choisit la clé lue dans
 * l'entrée et ne tourne nulle part ailleurs.
 */
class Property {

	const RESERVED = ['argument', 'raw', 'cast', 'prepare', 'check', 'set', 'end'];

	public ?Closure $argument = NULL;
	public ?Closure $cast = NULL;
	public ?Closure $check = NULL;
	public ?Closure $set = NULL;
	public ?Closure $end = NULL;
	public ?Closure $required = NULL;

	public array $beforeCast = [];
	public array $afterCast = [];
	public array $afterCheck = [];

	public function __construct(
		public readonly string $name
	) {
	}

	public function replaceArgument(Closure $callback): self {
		$this->argument = $this->once('argument', $this->argument, $callback);
		return $this;
	}

	public function replaceCast(Closure $callback): self {
		$this->cast = $this->once('cast', $this->cast, $callback);
		return $this;
	}

	// Retire le contrôle du schéma (nullabilité, bornes, valeurs d'un enum) au lieu de s'y ajouter :
	// une règle qui ne le couvre pas laisse passer ce qu'il refusait. addAfterCheck() le garde devant elle.
	public function replaceCheck(Closure $callback): self {
		$this->check = $this->once('check', $this->check, $callback);
		return $this;
	}

	public function replaceSet(Closure $callback): self {
		$this->set = $this->once('set', $this->set, $callback);
		return $this;
	}

	public function replaceEnd(Closure $callback): self {
		$this->end = $this->once('end', $this->end, $callback);
		return $this;
	}

	/**
	 * Refuse sous `propriete.required` une entrée absente, NULL ou faite d'espaces, avant toute
	 * conversion : le cast ramène un champ absent d'une colonne non nullable à la valeur vide de son
	 * type, qu'une étape posée après lui ne distingue plus d'une saisie. Les espaces sont jugés comme
	 * le cast d'une chaîne les jugera, puisqu'il les retire. $when reçoit la liste du build en cours
	 * et dit si la propriété est requise pour lui : `fn(\PropertyList $l) => $l->for === 'return'`.
	 */
	public function required(?Closure $when = NULL): self {

		$this->accept('required');
		$this->required = self::requiredStep($when);

		return $this;

	}

	// Écrite dans une méthode statique pour naitre sans $this : build() la lie alors à l'élément comme
	// toute étape, et $when, déclarée dans un properties() statique, reçoit le même élément
	private static function requiredStep(?Closure $when): Closure {

		return function(mixed $value, \PropertyList $l) use($when): bool {

			if($when !== NULL) {

				$whenBound = new ReflectionFunction($when)->getClosureThis() === NULL ? $when->bindTo($this) : $when;

				if($whenBound($l) === FALSE) {
					return TRUE;
				}

			}

			return (
				$value !== NULL and
				(is_string($value) === FALSE or trim($value) !== '')
			);

		};

	}

	public function addBeforeCast(string $name, Closure $callback): self {
		$this->beforeCast[$this->accept($name)] = $callback;
		return $this;
	}

	public function addAfterCast(string $name, Closure $callback): self {
		$this->afterCast[$this->accept($name)] = $callback;
		return $this;
	}

	public function addAfterCheck(string $name, Closure $callback): self {
		$this->afterCheck[$this->accept($name)] = $callback;
		return $this;
	}

	private function once(string $slot, ?Closure $current, Closure $callback): Closure {

		if($current !== NULL) {
			throw new Exception('Property \''.$this->name.'\': '.$slot.' is replaced twice');
		}

		return $callback;

	}

	private function accept(string $name): string {

		if(
			in_array($name, self::RESERVED, TRUE) or
			str_contains($name, '.') or
			$name === ''
		) {
			throw new Exception('Property \''.$this->name.'\': \''.$name.'\' is reserved or invalid as a step name');
		}

		if(
			isset($this->beforeCast[$name]) or
			isset($this->afterCast[$name]) or
			isset($this->afterCheck[$name]) or
			($name === 'required' and $this->required !== NULL)
		) {
			throw new Exception('Property \''.$this->name.'\': step \''.$name.'\' is declared twice');
		}

		return $name;

	}

	/**
	 * La chaîne dans l'ordre d'exécution, clé par clé. NULL à un emplacement standard veut dire
	 * « le défaut du cadriciel » ; build() le remplit. Sur une pseudo-propriété (absente du modèle),
	 * seuls les emplacements explicitement déclarés existent.
	 */
	public function compile(bool $hasModel): array {

		$chain = [];

		if($this->required !== NULL) {
			$chain[$this->name.'.required'] = $this->required;
		}

		foreach($this->beforeCast as $name => $callback) {
			$chain[$this->name.'.'.$name] = $callback;
		}

		if($hasModel or $this->cast !== NULL) {
			$chain[$this->name.'.cast'] = $this->cast;
		}

		foreach($this->afterCast as $name => $callback) {
			$chain[$this->name.'.'.$name] = $callback;
		}

		if($hasModel or $this->check !== NULL) {
			$chain[$this->name.'.check'] = $this->check;
		}

		foreach($this->afterCheck as $name => $callback) {
			$chain[$this->name.'.'.$name] = $callback;
		}

		if($hasModel or $this->set !== NULL) {
			$chain[$this->name.'.set'] = $this->set;
		}

		if($hasModel or $this->end !== NULL) {
			$chain[$this->name.'.end'] = $this->end;
		}

		return $chain;

	}

	/**
	 * Les clés de la chaîne, dans l'ordre, avec pour chacune « défaut » ou « déclaré », ce qui
	 * distingue un contrôle du schéma qui tourne d'un contrôle remplacé.
	 */
	public function chain(bool $hasModel): array {

		$chain = [];

		foreach($this->compile($hasModel) as $name => $callback) {
			$chain[$name] = ($callback === NULL) ? 'default' : 'declared';
		}

		return $chain;

	}

}

class PropertyList extends ArrayIterator {

	private ?Closure $wrapper = NULL;


	/**
	 * Les propriétés déclarées par property(), ou adoptées du mémo de la classe par build().
	 */
	private array $declared = [];

	// L'entrée du build en cours, posée par build() : une étape qui doit lire une autre clé
	// que la sienne (une propriété construite après elle) la lit ici, jamais par une capture.
	public array $input = [];

	private array $properties = [];
	private array $stops = [];
	private array $built = [];
	private array $invalid = [];
	private array $invalidMessages = [];
	private array $new = [];

	public function __construct(
		public readonly ?string $for = NULL,
		public readonly ?string $origin = NULL
	) {
	}

	public function setProperties(array $properties): void {
		$this->properties = $properties;
	}

	public function hasProperty(string $property): bool {
		return in_array($property, $this->properties);
	}

	public function setWrapper(Closure $wrapper): PropertyList {
		$this->wrapper = $wrapper;
		return $this;
	}

	public function getWrapper(): ?Closure {
		return $this->wrapper;
	}

	/**
	 * Rend la description d'une propriété, à compléter : `$l->property('gender')->replaceCheck(…)`.
	 * Un second ->property() sur le même nom rouvre la même description au lieu de lever : une
	 * classe qui pose la même étape sur une liste de propriétés (une boucle sur une constante) puis
	 * décrit chacune peut le faire en deux endroits. Ce qui ne peut pas se poser deux fois, c'est
	 * une étape : Property le refuse, par son nom pour add*(), par son emplacement pour replace*().
	 * Une étape lit le build en cours par son deuxième paramètre ($value, $l, $wrapper) : la liste
	 * de properties() ne sert qu'à déclarer, et un use($l) en capture une qu'aucun build ne remplit.
	 */
	public function property(string $name): Property {
		return $this->declared[$name] ??= new Property($name);
	}

	public function getDeclared(?string $name = NULL): Property|array|null {

		if($name === NULL) {
			return $this->declared;
		}

		return $this->declared[$name] ?? NULL;

	}

	/**
	 * Reçoit les déclarations mémoïsées de la classe sans écraser celles posées sur cette liste.
	 */
	public function adoptDeclared(array $declared): void {

		foreach($declared as $name => $property) {
			$this->declared[$name] ??= $property;
		}

	}

	public function addStop(string $property): self {
		$this->stops[] = $property;
		return $this;
	}

	public function hasStop(string $property): bool {
		return in_array($property, $this->stops);
	}

	public function addBuilt(string $property): void {
		$this->built[] = $property;
	}

	public function getBuilt(): array {
		return $this->built;
	}

	public function isBuilt(string|array $properties) {

		$properties = (array)$properties;
		return array_intersect($properties, $this->built) === $properties;

	}

	public function skipIfNotBuilt(string|array $properties) {

		$properties = (array)$properties;

		if(array_intersect($properties, $this->built) === []) {
			throw new PropertySkip();
		}

	}

	public function expectsBuilt(string|array $properties) {

		$properties = (array)$properties;

		if(array_intersect($properties, $this->built) === []) {
			throw new BuildPropertyError();
		}

	}

	public function addInvalid(string $property, string $message): void {
		$this->invalid[] = $property;
		$this->invalidMessages[] = $message;
	}

	public function getInvalid(): array {
		return $this->invalid;
	}

	public function getInvalidMessages(): array {
		return $this->invalidMessages;
	}

	public function isInvalid(string|array $properties) {

		$properties = (array)$properties;
		return array_intersect($properties, $this->invalid) === $properties;

	}

	public function skipIfNotInvalid(string|array $properties) {

		$properties = (array)$properties;

		if(array_intersect($properties, $this->invalid) === []) {
			throw new PropertySkip();
		}

	}

	public function skipIfInvalid(string|array $properties) {

		$properties = (array)$properties;

		if(array_intersect($properties, $this->invalid)) {
			throw new PropertySkip();
		}

	}

	public function addNew(string $property): void {
		$this->new[] = $property;
	}

	public function getNew(): array {
		return $this->new;
	}

	public function isNew(string|array $properties) {

		$properties = (array)$properties;
		return array_intersect($properties, $this->new) === $properties;

	}

	public function expectsNew(string|array $properties) {

		$properties = (array)$properties;

		if(array_intersect($properties, $this->new) === []) {
			throw new BuildPropertyError();
		}

	}

}

class PropertySkip extends Exception {

}

class PropertySuccess extends Exception {

}

class BuildPropertyError extends Exception {

}

class BuildElementError extends Exception {

}

trait FilterElement {

	public static function POST(mixed $value, string $property, $default = NULL): mixed {
		return self::filter($_POST[$value] ?? NULL, $property, $default);
	}

	public static function GET(mixed $value, string $property, $default = NULL): mixed {
		return self::filter($_GET[$value] ?? NULL, $property, $default);
	}

	public static function REQUEST(mixed $value, string $property, $default = NULL): mixed {
		return self::filter($_REQUEST[$value] ?? NULL, $property, $default);
	}

	public static function INPUT(mixed $value, string $property, $default = NULL): mixed {

		return match(Route::getRequestMethod()) {
			'GET' => self::GET($value, $property, $default),
			'POST' => self::POST($value, $property, $default),
			default => throw new Exception('Unhandled method '.Route::getRequestMethod())
		};

	}

	public static function filter(mixed $value, string $property, $default = NULL): mixed {

		if(str_starts_with($property, '?')) {
			$allowNull = TRUE;
			$property = substr($property, 1);
		} else {
			$allowNull = FALSE;
		}

		if(
			$allowNull === FALSE and
			$value === NULL
		) {
			return is_closure($default) ? $default($value) : $default;
		}

		// Le type de la colonne distingue ici l'identifiant de la quantité, comme dans build(). Une
		// propriété temporaire n'a pas de colonne, donc pas de type à lire.
		if(self::model()->hasProperty($property)) {
			\Filter::normalize(self::model()->getProperty($property), $value);
		}

		self::model()->cast($property, $value);

		if(self::model()->check($property, $value)) {
			return $value;
		} else {
			return is_closure($default) ? $default($value) : $default;
		}

	}

}


class ElementException extends Exception {

}

/**
 * Db value
 */
class Sql {

	protected string $value;
	protected string $type;

	public function __construct(string $value, string $type = 'text') {
		$this->value = $value;
		$this->type = $type;
	}

	public function __toString(): string {
		return (string)$this->value;
	}

	public function getType(): string {
		return $this->type;
	}

}

class Deferred {

	protected string $value;
	protected string $type;

	public function __construct(string $value, string $type = 'text') {
		$this->value = $value;
		$this->type = $type;
	}

	public function __toString(): string {
		return (string)$this->value;
	}

	public function getType(): string {
		return $this->type;
	}

}

class Search {

	private bool $sortStatus = TRUE;
	private null|string|Sql $defaultSort = NULL;

	public function __construct(
		public array $properties = [],
		public null|string|Sql $sort = NULL
	) {

	}

	public function sort(?string $sort): self {

		$this->sort = $sort;
		return $this;

	}

	public function defaultSort(null|string|Sql $defaultSort): self {

		$this->defaultSort = $defaultSort;
		return $this;

	}

	public function validateSort(array $possibleProperties, ?string $default = NULL): self {

		if(empty($this->sort)) {
			$this->sort = $default ?? $this->defaultSort ?? first($possibleProperties);
		} else if($this->sort instanceof Sql) {
		} else {

			[$property] = self::splitSort($this->sort);

			if(in_array($property, $possibleProperties, TRUE) === FALSE) {
				$this->sort = $default;
			}

		}

		return $this;

	}

	public function buildSort(array $override = []): array|Sql|null {

		if($this->sort === NULL) {
			return NULL;
		} else if($this->sort instanceof Sql) {
			return $this->sort;
		} else {

			[$property, $direction] = self::splitSort($this->sort);

			if(isset($override[$property])) {
				return $override[$property]($direction);
			} else {

				return [
					$property => $direction
				];

			}

		}

	}
	
	private static function splitSort(string $sort): array {
		
		if(str_ends_with($sort, '-')) {
			$direction = SORT_DESC;
			$property = substr($sort, 0, -1);
		} else if(str_ends_with($sort, '+')) {
			$direction = SORT_ASC;
			$property = substr($sort, 0, -1);
		} else {
			$direction = SORT_ASC;
			$property = $sort;
		}

		return [$property, $direction];
		
	}

	public function setSortStatus(bool $status): void {
		$this->sortStatus = $status;
	}

	/**
	 * `$url` remplace l'adresse de la requête, pour un en-tête rendu dans la réponse d'une action ajax.
	 */
	public function linkSort(string $property, string $label, int $defaultDirection = SORT_ASC, ?string $url = NULL): string {

		if($this->sortStatus === FALSE) {
			return $label;
		}

		if($this->sort !== NULL and str_ends_with($this->sort, '-')) {
			$currentDirection = '-';
			$currentProperty = substr($this->sort, 0, -1);
		} else if($this->sort !== NULL and str_ends_with($this->sort, '+')) {
			$currentDirection = '+';
			$currentProperty = substr($this->sort, 0, -1);
		} else {
			$currentDirection = '';
			$currentProperty = $this->sort;
		}

		if($property === $currentProperty) {
			$direction = ($currentDirection === '') ? '-' : '';
		} else {
			$direction = ($defaultDirection === SORT_ASC) ? '' : '-';
		}

		$request = \util\HttpUi::setArgument($url ?? LIME_REQUEST, 'sort', $property.$direction);

		$h = '<span style="white-space: nowrap">';

			$h .= '<a href="'.$request.'" style="white-space: initial">';
				$h .= $label;
			$h .= '</a>';

			if($property === $currentProperty) {
				$h .= '&nbsp;'.\Asset::icon('sort-alpha-down'.($currentDirection === '' ? '' : '-alt'));
			}

		$h .= '</span>';

		return $h;

	}

	public function notEmpty(array $exclude = []): bool {
		return ($this->getFiltered($exclude) !== []);
	}

	public function empty(array $exclude = []): bool {
		return ($this->getFiltered($exclude) === []);
	}

	public function count(): int {
		return count($this->getFiltered());
	}

	public function getFiltered(array $exclude = []): array {
		return array_filter($this->properties, fn($value, $key) => (
			in_array($key, $exclude) === FALSE and $this->isFiltered($key)
		),  ARRAY_FILTER_USE_BOTH);
	}

	public function isFiltered(string $property): bool {

		return $this->has($property) and !(
			($this->properties[$property] instanceof Element and $this->properties[$property]->empty()) or
			($this->properties[$property] instanceof Collection and $this->properties[$property]->empty()) or
			empty($this->properties[$property])
		);

	}

	public function filter(string $property, Closure $callback): bool {

		if($this->isFiltered($property)) {
			$callback($this->properties[$property]);
			return TRUE;
		} else {
			return FALSE;
		}

	}

	public function toArray(array $exclude = []): array {

		$export = [];

		foreach($this->getFiltered($exclude) as $key => $value) {
			$export[$key] = ($value instanceof Element) ? $value['id'] : $value;
		}

		return $export;

	}

	public function toQuery(array $exclude = [], string $prefix = ''): string {

		$query = http_build_query(self::toArray($exclude));

		return $query ? $prefix.$query : '';

	}

	public function has(string $property): bool {
		return array_key_exists($property, $this->properties);
	}

	public function get(string $property, mixed $defaultValue = NULL): mixed {
		return $this->properties[$property] ?? $defaultValue;
	}

	public function getSort(): mixed {
		return $this->sort;
	}

	public function set(string $property, mixed $value): void {
		$this->properties[$property] = $value;
	}

}

class PropertyDescriber {

	/**
	 * Property name
	 */
	public ?string $property = NULL;

	/**
	 * Property label
	 */
	public string|Closure|null $label = NULL;

	/**
	 * Property type
	 */
	public ?string $type = NULL;

	/**
	 * Property range
	 */
	public ?array $range = NULL;

	/**
	 * Property enum
	 */
	public ?array $enum = NULL;

	/**
	 * Property attributes
	 */
	public array|Closure $attributes = [];

	/**
	 * Property set
	 */
	public ?array $set = NULL;

	/**
	 * Property module
	 */
	public ?string $module = NULL;

	/**
	 * Property default value
	 */
	public $default = NULL;

	/**
	 * List of values for the type
	 */
	public array|Collection|Closure $values = [];

	/**
	 * User-defined values
	 */
	private array $magic = [];

	/**
	 * Wrapper
	 */
	public string|Closure|null $wrapper = NULL;

	/**
	 * Attributes for dynamic groups
	 */
	public array|Closure $group = [];

	/**
	 * Attributes for dynamic input groups
	 */
	public array|Closure $inputGroup = [];

	/**
	 * Override field type
	 */
	public $field = 'default';

	/**
	 * Display as mandatory
	 */
	public $asterisk = FALSE;

	/**
	 * Required field
	 */
	public bool|Closure $required = FALSE;

	public function __construct(string $property, array $values = []) {

		$this->property = $property;

		foreach($values as $key => $value) {
			$this->$key = $value;
		}

	}

	public function __get(string $key) {
		return $this->magic[$key] ?? NULL;
	}

	public function __set(string $key, $value): void {
		$this->magic[$key] = $value;
	}

	public function __toString(): string {
		return (string)$this->label;
	}

}

class PropertyValidator extends Validator {

	public function __construct(
		protected ModuleModel $m,
		protected string $property
	) {
	}

	public function validate(string $name, mixed &$value): void {

		if(
			$this->m->cast($this->property, $value) === FALSE or
			$this->m->check($this->property, $value) === FALSE
		) {
			($this->m->getModule())::fail($name.'.check');
		}

	}

}
?>
