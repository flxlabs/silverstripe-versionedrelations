<?php
class VersionedRelationsExtension extends DataExtension {

	public function __construct() {
		parent::__construct();

		if (is_subclass_of(Controller::curr(), "LeftAndMain")) {
			Requirements::javascript("versionedrelations/javascript/versionedrelation.js");
		}
	}

	/*
	 * create store for historical versions, duplicate relation for live
	 *
	 * @return array
	 */
	public static function get_extra_config($class, $extension, $args) {
		// Add this extension to all subclasses of the current class
		// TODO: Add a config option to turn this off
		foreach (ClassInfo::subclassesFor($class) as $c) {
			if ($c !== $class) {
				Object::add_extension($c, "VersionedRelationsExtension");
			}
		}

		$db = array();
		$has_one = array();
		$has_many = array();
		$many_many = array();
		$many_many_extraFields = array();
		$belongs_to = array();
		$belongs_many_many = array();

		// Generate forward relations
		$versionedHasOneRels = Config::inst()->get($class, "versioned_has_one", Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);
		$versionedHasManyRels = Config::inst()->get($class, "versioned_has_many", Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);
		$versionedManyManyRels = Config::inst()->get($class, "versioned_many_many", Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);

		if (is_array($versionedHasOneRels)) {
			foreach ($versionedHasOneRels as $relName => $relationClass) {

				if (!$relationClass) {
					throw new Exception("{$class} needs to define a class type for the versioned_has_one relation $relName");
				}

				// Create original relation
				$has_one[$relName] = $relationClass;

				// Store historical values for relation
				$db[$relName . "_Version"] = "Int";
			}
		}

		if (is_array($versionedHasManyRels)) {
			foreach($versionedHasManyRels as $relName => $relationClass) {

				if (!$relationClass) {
					throw new Exception("{$class} needs to define a class type for the versioned_has_many relation $relName");
				}

				// Create original relation
				$has_many[$relName] = $relationClass;

				// Store historical values for relation
				$db[$relName . "_Store"] = "Text";

				//$many_many_extraFields[$relName] = array("Version" => "Int");
			}
		}

		if (is_array($versionedManyManyRels)) {
			foreach ($versionedManyManyRels as $relName => $relClass) {
				if (!$relClass) {
					throw new Exception("{$class} needs to define a class type for the versioned_many_many relation $relName");
				}

				// Create original relation
				$many_many[$relName] = $relClass;

				// Store historical values for relation
				$db[$relName . "_Store"] = "Text";

				// Create extra fields
				/*$many_many_extraFields[$relName] = array(
					Versioned::get_live_stage() . "Version" => "Int",
				);*/
			}
		}

		// Generate backwards relations
		$versionedBelongsToRels = Config::inst()->get($class, "versioned_belongs_to", Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);
		//$versionedBelongsManyRels = Config::inst()->get($class, "versioned_belongs_has_many", Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);
		$versionedBelongsManyManyRels = Config::inst()->get($class, "versioned_belongs_many_many", Config::EXCLUDE_EXTRA_SOURCES | Config::UNINHERITED);

		if (is_array($versionedBelongsToRels)) {
			foreach ($versionedBelongsToRels as $relName => $relationClass) {

				if (!$relationClass) {
					throw new Exception("{$class} needs to define a class type for the versioned_belongs_to relation $relName");
				}

				// Create original relation
				$belongs_to[$relName] = $relationClass;
			}
		}

		/*if (is_array($versionedBelongsManyRels)) {
			foreach($versionedBelongsManyRels as $relName => $relationClass) {

				if (!$relationClass) {
					throw new Exception("{$class} needs to define a class type for the versioned_belongs_has_many relation $relName");
				}

				// Create original relation
				$has_one[$relName] = $relationClass;
			}
		}*/

		if (is_array($versionedBelongsManyManyRels)) {
			foreach ($versionedBelongsManyManyRels as $relName => $relationClass) {

				if (!$relationClass) {
					throw new Exception("{$class} needs to define a class type for the versioned_belongs_many_many relation $relName");
				}

				// Create original relation
				$belongs_many_many[$relName] = $relationClass;
			}
		}

		Config::inst()->update($class, "__versioned", true);

		return array(
			"db" => $db,
			"has_one" => $has_one,
			"has_many" => $has_many,
			"many_many" => $many_many,
			"many_many_extraFields" => $many_many_extraFields,
			"belongs_to" => $belongs_to,
			"belongs_many_many" => $belongs_many_many
		);
	}

	/*
	 * Create array for corresponding field
	 *
	 */
	private function relationToArray($relationName, $list) {
		$store = array();

		foreach ($list as $relation) {
			// store basics
			$entryStore = array(
				"ClassName" => $relation->ClassName,
				"ID" => $relation->ID,
			);
			if (isset($relation->Version)) {
				$entryStore["Version"] = $relation->Version;
			}

			// store extraFields
			foreach ($this->getExtraFieldsArray($relationName) as $k => $v) {
				$entryStore[$k] = $relation->$k;
			}

			$store[] = $entryStore;
		}
		return $store;
	}

	/*
	 * Store relations as json in corresponding field
	 *
	 */
	private function storeRelation($relationName, $list) {
		$json = Convert::array2json($this->relationToArray($relationName, $list));
		$this->owner->setField($relationName . "_Store", $json);
	}

	/*
	 * Store relations as json in corresponding field
	 */
	public function storeRelations() {
		$readingMode = Versioned::get_reading_mode();
		Versioned::set_reading_mode("Stage.Stage");

		foreach ($this->getManyManyRelationsNames() as $relName => $relClass) {
			$this->storeRelation($relName, $this->owner->$relName());
		}

		foreach ($this->getHasManyRelationsNames() as $relName => $relClass) {
			$this->storeRelation($relName, $this->owner->$relName());
		}

		foreach ($this->getHasOneRelationsNames() as $relName => $relClass) {
			if ($obj = $this->owner->$relName()) {
				if (isset($obj->Version)) {
					$this->owner->setField($relName . "_Version", $obj->Version);
				}
			}
		}

		Versioned::set_reading_mode($readingMode);
	}

	/*
	 * store current setup for relations
	 */
	public function onBeforeWrite() {
		$this->storeRelations();
		parent::onBeforeWrite();
	}

	/*
	 * notify other end of relation about change
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();
		$this->owner->flushCache(true);
		$this->saveOthers();
	}

	private function saveOthers() {
		if (Versioned::get_reading_mode() == "Stage.Stage") {
			foreach ($this->getBelongsManyManyRelationsNames() as $relName => $relClass) {
				if ($relations = $this->owner->$relName()) {
					foreach ($relations as $relation) {
						if ($relation->ID) {
							$relation->storeRelations();
							$relation->write();
						}
					}
				}
			}

			foreach ($this->getHasOneRelationsNames() as $relName => $relClass) {
				if ($relation = $this->owner->$relName()) {
					if ($relation->ID) {
						$relation->storeRelations();
						$relation->write();
					}
				}
			}

			foreach ($this->getBelongsToRelationsNames() as $relName => $relClass) {
				if ($relation = $this->owner->$relName()) {
					if ($relation->ID) {
						$relation->storeRelations();
						$relation->write();
					}
				}
			}
		}
	}

	public function onBeforeVersionedPublish($fromStage, $toStage, $createNewVersion) {
		// We need to "publish" the has_many relations, so that we can use the live table
		// when looking up has_many relations backwards. So we just publish everything
		// (because recursion)

		foreach ($this->getHasManyRelationsNames() as $relName => $relClass) {
			foreach ($this->owner->$relName() as $child) {
				$child->publish($fromStage, $toStage);
			}
		}

		foreach ($this->getManyManyRelationsNames() as $relName => $relClass) {
			foreach ($this->owner->$relName() as $child) {
				$child->publish($fromStage, $toStage);
			}
		}
	}

	public function updateCMSFields(FieldList $fields) {
		foreach ($this->getHasOneRelationsNames() as $relName => $relClass) {
			$fields->removeByName($relName . "_Version");
		}

		foreach ($this->getManyManyRelationsNames() as $relName => $relClass) {
			$fields->removeByName($relName . "_Store");
		}

		foreach ($this->getHasManyRelationsNames() as $relName => $relClass) {
			$fields->removeByName($relName . "_Store");
		}

		return $fields;
	}

	public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null) {
		if (!$dataQuery) return;
		if (Versioned::current_stage() != Versioned::get_live_stage()) return;

		$stage = Versioned::current_stage();
		$class = $dataQuery->dataClass();

		// Check all relations that reference us, and see if one of those is
		// in the query as a join. If yes then add a filter to stage and version
		/*foreach ($this->getBelongsManyManyRelationsNames() as $relName => $relClass) {
			$names = Config::inst()->get($relClass, "versioned_many_many",
				Config::EXCLUDE_EXTRA_SOURCES) ?: array();
			foreach ($names as $meName => $meClass) {
				$key = $relClass . "_" . $meName;
				if (array_key_exists($key, $query->from)) {
					//$query->renameTable("PageElement_Live", "PageElement");
					//$query->addFilterToJoin($key, "`$key`.`{$stage}Version` = `$class`.`Version`");
					//var_dump($query->sql());
					//var_dump($this->getStoredRelation($meName));
				}
			}
		}*/

		// Get all has_one relations which we have
		foreach ($this->getHasOneRelationsNames() as $relName => $relClass) {
			$names = Config::inst()->get($relClass, "versioned_has_many",
				Config::EXCLUDE_EXTRA_SOURCES) ?: array();
			// Get all has_many relations that the other object has, which might be
			// pointing to us.
			foreach ($names as $meName => $meClass) {
				// Check if that other relation references us
				if ($class !== $meClass) continue;
				$key = $relClass . "_" . $meName;

				//var_dump($this->owner);
				$qClass = ClassInfo::table_for_object_field($relClass, "Version");
				//$query->addWhere("\"$qClass\".\"Version\" = \"$meClass\".\"{$relName}_Version\"");
				//$query->renameTable($class . "_Live", $class . "_Versions");

				//var_dump($query->sql());

				if (array_key_exists($key, $query->from)) {
					//$query->renameTable("PageElement_Live", "PageElement");
					//$query->addFilterToJoin($key, "`$key`.`{$stage}Version` = `$class`.`Version`");
					//var_dump($query->sql());
					//var_dump($this->getStoredRelation($meName));
				}
			}
		}
	}

	// Manipulate any many many components if we're in the live environment
	// so that we only get elements that belong to our version
	public function updateManyManyComponents(&$result) {
		if (Versioned::current_stage() != Versioned::get_live_stage()) return;

		foreach ($this->getManyManyRelationsNames() as $relName => $relClass) {
			// Get the table name according to the class name that the field is defined on
			// (could be an ancestor) and the field name
			$key = $this->owner->manyManyComponent($relName)[0] . "_" . $relName;
			if ($key !== $result->getJoinTable()) continue;

			$result = $this->getStoredRelation($relName);
			return;
		}
	}

	/*
	 *
	 * @return ArrayList
	 */
	public function getStoredRelation($relationName) {
		$hasManyRelations = $this->getHasManyRelationsNames();
		$manyManyRelations = $this->getManyManyRelationsNames();
		$hasOneRelations = $this->getHasOneRelationsNames();

		if (array_key_exists($relationName, $hasManyRelations) ||
			array_key_exists($relationName, $manyManyRelations)) {
			$json = $this->owner->getField($relationName . "_Store");

			$ret = VersionedArrayList::create();
			if ($entries = Convert::json2Array($json)) {
				foreach ($entries as $entry) {
					if ($versionedObj = $this->getVersionedObj($entry)) {
						// additional extra fields
						foreach ($this->getExtraFieldsArray($relationName) as $k => $v) {
							$versionedObj->$k = $entry[$k];
						}
						$ret->add($versionedObj);
					}
				}
			}
			return $ret;
		} else if (array_key_exists($relationName, $hasOneRelations)) {
			$arr = array(
				"ID" => $this->owner->{$relationName . "ID"},
				"ClassName" => $this->owner->getComponent($relationName),
				"Version" => $this->owner->{$relationName . "_Version"}
			);

			if ($versionedObj = $this->getVersionedObj($arr)) {
				return $versionedObj;
			}
		}
	}

	public function getVersionedObj(array $entry) {
		if (!isset($entry["Version"]) || $entry["Version"] === 0) {
			return DataObject::get( $entry["ClassName"] )->byID($entry["ID"]);
		} else {
			return Versioned::get_version( $entry["ClassName"], $entry["ID"], $entry["Version"] );
		}
	}

	/*
	 * empty relation list when rolling back page
	 * then fill it with stored historical relation list
	 */
	public function onBeforeRollback($version) {
		foreach ($this->getManyManyRelationsNames() as $relName => $relClass) {
			$this->rollbackRelation($relName, $this->owner->$relName(), $version);
		}

		foreach ($this->getHasManyRelationsNames() as $relName => $relClass) {
			$this->rollbackRelation($relName, $this->owner->$relName(), $version);
		}

		foreach ($this->getHasOneRelationsNames() as $relName => $relClass) {
			if ($versionedOwner = $this->getRolledbackOwner($version)) {
				$objID = $versionedOwner->{$relName . "ID"};

				// dont roll back if it was not existing then
				if ($objID) {
					$objVersion = $versionedOwner->{$relName . "_Version"};
					$foreignObj = $this->owner->$relName();

					// rollback versionable related object if existing
					if ($foreignObj) {
						if ($foreignObj->hasExtension("Versioned")) {
							$foreignObj->doRollbackTo($objVersion);
						}
					}
				}
			}
		}
	}

	private function getRolledbackOwner($version) {
		if ($version == Versioned::get_live_stage()) {
			// rolls back to published version
			$versionNum = Versioned::get_versionnumber_by_stage(
				$this->owner->ClassName,
				Versioned::get_live_stage(),
				$this->owner->ID
			);
		} else {
			$versionNum = $version;
		}

		// rolls back to a past version
		return $this->getVersionedObj(array(
			"ID" => $this->owner->ID,
			"ClassName" => $this->owner->ClassName,
			"Version" => $versionNum
		));
	}

	private function rollbackRelation($relationName, $list, $version) {
		$storeFieldName = $relationName . "_Store";
		$list->removeAll();

		$versionedOwner = $this->getRolledbackOwner($version);

		// fill relations
		if ($versionedOwner && $versionedOwner->$storeFieldName) {
			if ($store = Convert::json2Array($versionedOwner->$storeFieldName)) {
				foreach ($store as $entry) {

					if ($foreignObj = DataObject::get($entry["ClassName"])->byID($entry["ID"])) {

						// rollback versionable related object
						if ($foreignObj->hasExtension("Versioned") && $this->getVersionedObj($entry)) {
								$foreignObj->doRollbackTo($entry["Version"]);
						}
						// extraFields
						$extras = array();
						foreach ($this->getExtraFieldsArray($relationName) as $k => $v) {
								if (isset($entry[$k])) $extras[$k] = $entry[$k];
						}
						// add
						$list->add($foreignObj, $extras);
					}
				}
			}
		}
	}

	/*
	 * Returns the has_many relations which will be handled for versioning
	 *
	 * @return array
	 */
	private function getBelongsManyManyRelationsNames() {
		return Config::inst()->get($this->owner->ClassName, "versioned_belongs_many_many", Config::EXCLUDE_EXTRA_SOURCES) ?: array();
	}

	/*
	 * Returns the many_many relations which will be handled for versioning
	 *
	 * @return array
	 */
	private function getManyManyRelationsNames() {
		return Config::inst()->get($this->owner->ClassName, "versioned_many_many", Config::EXCLUDE_EXTRA_SOURCES) ?: array();
	}

	/*
	 * Returns the has_many relations which will be handled for versioning
	 *
	 * @return array
	 */
	private function getHasManyRelationsNames() {
		return Config::inst()->get($this->owner->ClassName, "versioned_has_many", Config::EXCLUDE_EXTRA_SOURCES) ?: array();
	}

	/*
	 * Returns the has_one relations which will be handled for versioning
	 *
	 * @return array
	 */
	private function getHasOneRelationsNames() {
		return Config::inst()->get($this->owner->ClassName, "versioned_has_one", Config::EXCLUDE_EXTRA_SOURCES) ?: array();
	}

	/*
	 * Returns the belongs_to relations which will be handled for versioning
	 *
	 * @return array
	 */
	private function getBelongsToRelationsNames() {
		return Config::inst()->get($this->owner->ClassName, "versioned_belongs_to", Config::EXCLUDE_EXTRA_SOURCES) ?: array();
	}

	/*
	 * @return array
	 */
	public function getExtraFieldsArray($relationName) {
		if ($extra = $this->owner->manyManyExtraFieldsForComponent($relationName))
			return $extra;
		return array();
	}
}
