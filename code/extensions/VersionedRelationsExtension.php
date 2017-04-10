<?php
class VersionedRelationsExtension extends DataExtension {

    public function __construct() {
        parent::__construct();
        Requirements::javascript("versionedrelations/javascript/versionedrelation.js");
    }
    
    /*
     * create store for historical versions, duplicate relation for live
     *
     * @return array
     */
    public function extraStatics($class = null, $extensionClass = null) {
        $db = array();
        $has_one = array();
        $has_many = array();
        $many_many = array();
        $belongs_to = array();
        $belongs_many_many = array();

        // Generate forward relations
        $versionedHasOneRels = Config::inst()->get($class, "versioned_has_one", Config::EXCLUDE_EXTRA_SOURCES);
        $versionedHasManyRels = Config::inst()->get($class, "versioned_has_many", Config::EXCLUDE_EXTRA_SOURCES);
        $versionedManyManyRels = Config::inst()->get($class, "versioned_many_many", Config::EXCLUDE_EXTRA_SOURCES);

        if (is_array($versionedHasOneRels)) {
            foreach ($versionedHasOneRels as $relationName => $relationClass) {

                if (!$relationClass) {
                    throw new Exception("{$class} needs to define a class type for the versioned_has_one relation $relationName");
                }

                // Create original relation
                $has_one[$relationName] = $relationClass;

                // Store historical values for relation
                $db[$relationName . "_Version"] = "Int";
            }
        }

        if (is_array($versionedHasManyRels)) {
            foreach($versionedHasManyRels as $relationName => $relationClass) {

                if (!$relationClass) {
                    throw new Exception("{$class} needs to define a class type for the versioned_has_many relation $relationName");
                }

                // Create original relation
                $has_many[$relationName] = $relationClass;

                // Store historical values for relation
                $db[$relationName . "_Store"] = "Text";
            }
        }

        if (is_array($versionedManyManyRels)) {
            foreach ($versionedManyManyRels as $relationName => $relationClass) {

                if (!$relationClass) {
                    throw new Exception("{$class} needs to define a class type for the versioned_many_many relation $relationName");
                }

                // Create original relation
                $many_many[$relationName] = $relationClass;

                // Store historical values for relation
                $db[$relationName . "_Store"] = "Text";
            }
        }

        // Generate backwards relations
        $versionedBelongsToRels = Config::inst()->get($class, "versioned_belongs_to", Config::EXCLUDE_EXTRA_SOURCES);
        $versionedBelongsManyRels = Config::inst()->get($class, "versioned_belongs_has_many", Config::EXCLUDE_EXTRA_SOURCES);
        $versionedBelongsManyManyRels = Config::inst()->get($class, "versioned_belongs_many_many", Config::EXCLUDE_EXTRA_SOURCES);

        if (is_array($versionedBelongsToRels)) {
            foreach ($versionedBelongsToRels as $relationName => $relationClass) {

                if (!$relationClass) {
                    throw new Exception("{$class} needs to define a class type for the versioned_belongs_to relation $relationName");
                }

                // Create original relation
                $belongs_to[$relationName] = $relationClass;
            }
        }

        if (is_array($versionedBelongsManyRels)) {
            foreach($versionedBelongsManyRels as $relationName => $relationClass) {

                if (!$relationClass) {
                    throw new Exception("{$class} needs to define a class type for the versioned_belongs_has_many relation $relationName");
                }

                // Create original relation
                $has_one[$relationName] = $relationClass;
            }
        }

        if (is_array($versionedBelongsManyManyRels)) {
            foreach ($versionedBelongsManyManyRels as $relationName => $relationClass) {

                if (!$relationClass) {
                    throw new Exception("{$class} needs to define a class type for the versioned_belongs_many_many relation $relationName");
                }

                // Create original relation
                $belongs_many_many[$relationName] = $relationClass;
            }
        }

        Config::inst()->update($class, "__versioned", true);

        return array(
            "db" => $db,
            "has_one" => $has_one,
            "has_many" => $has_many,
            "many_many" => $many_many,
            "belongs_to" => $belongs_to,
            "belongs_many_many" => $belongs_many_many
        );
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
    private function getBelongsHasManyRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, "versioned_belongs_has_many", Config::EXCLUDE_EXTRA_SOURCES) ?: array();
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
     * create array for corresponding field
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
     * store relations as json in corresponding field
     *
     */
    private function storeRelation($relationName, $list) {
        $json = Convert::array2json($this->relationToArray($relationName, $list));
        $this->owner->setField($relationName . "_Store", $json);
    }

    /*
     * store relations as json in corresponding field
     */
    public function storeRelations() {
        $readingMode = Versioned::get_reading_mode();
        Versioned::set_reading_mode("Stage.Stage");

        foreach ($this->getManyManyRelationsNames() as $relationName => $relationClass) {
            $this->storeRelation($relationName, $this->owner->getManyManyComponents($relationName));
        }

        foreach ($this->getHasManyRelationsNames() as $relationName => $relationClass) {
            $this->storeRelation($relationName, $this->owner->getComponents($relationName));
        }

        foreach ($this->getHasOneRelationsNames() as $relationName => $relationClass) {
            // get version of relation
            if ($obj = $this->owner->getComponent($relationName)) {
                if (isset($obj->Version)) $this->owner->setField($relationName . "_Version", $obj->Version);
            }
        }

        Versioned::set_reading_mode($readingMode);
    }

    /*
     * store current setup for relations
     */
    public function onBeforeWrite() {
        $this->storeRelations();
    }

    /*
     * notify other end of relation about change
     */
    public function onAfterWrite() {
        $readingMode = Versioned::get_reading_mode();

        if ($readingMode == "Stage.Stage") {

            foreach ($this->getBelongsManyManyRelationsNames() as $relationName => $relationClass) {
                if ($relations = $this->owner->getManyManyComponents($relationName)) {
                    foreach ($relations as $relation) {
                        if ($relation->ID) {
                            $relation->storeRelations();
                            $relation->write(true);
                        }
                    }
                }
            }

            foreach ($this->getBelongsHasManyRelationsNames() as $relationName => $relationClass) {
                if ($relation = $this->owner->getComponent($relationName)) {
                    if ($relation->ID) {
                        $relation->storeRelations();
                        $relation->write(true);
                    }
                }
            }

            foreach ($this->getBelongsToRelationsNames() as $relationName => $relationClass) {
                if ($relation = $this->owner->getComponent($relationName)) {
                    if ($relation->ID) {
                        $relation->storeRelations();
                        $relation->write(true);
                    }
                }
            }
        }

        parent::onAfterWrite();
    }

    private function getVersionedObj(array $entry) {
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
        foreach ($this->getManyManyRelationsNames() as $relationName => $relationClass) {
            $this->rollbackRelation($relationName, $this->owner->getManyManyComponents($relationName), $version);
        }

        foreach ($this->getHasManyRelationsNames() as $relationName => $relationClass) {
            $this->rollbackRelation($relationName, $this->owner->getComponents($relationName), $version);
        }

        foreach ($this->getHasOneRelationsNames() as $relationName => $relationClass) {
            if ($versionedOwner = $this->getRolledbackOwner($version)) {
                $objID = $versionedOwner->{$relationName . "ID"};

                // dont roll back if it was not existing then
                if ($objID) {
                    $objVersion = $versionedOwner->{$relationName . "_Version"};
                    $foreignObj = $this->owner->getComponent($relationName);

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
            $versionNum = Versioned::get_versionnumber_by_stage($this->owner->ClassName, Versioned::get_live_stage(), $this->owner->ID);
        } else {
            $versionNum = $version;
        }

        // rolls back to a past version
        return $this->getVersionedObj(array("ID"=>$this->owner->ID, "ClassName"=>$this->owner->ClassName, "Version"=>$versionNum));
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


    public function updateCMSFields(FieldList $fields) {

        foreach ($this->getManyManyRelationsNames() as $relationName => $relationClass) {
            $fields->removeByName($relationName . "_Store");
        }

        foreach ($this->getHasManyRelationsNames() as $relationName => $relationClass) {
            $fields->removeByName($relationName . "_Store");
        }
    }


    /*
     *
     * @return ArrayList
     */
    private function getStoredRelation($relationName) {
        $hasManyRelations = $this->getHasManyRelationsNames();
        $manyManyRelations = $this->getManyManyRelationsNames();
        $hasOneRelations = $this->getHasOneRelationsNames();

        if (array_key_exists($relationName, $hasManyRelations) || 
            array_key_exists($relationName, $manyManyRelations)) {
            $json = $this->owner->getField($relationName . "_Store");

            $ret = ArrayList::create();
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

    /*
    * 
    */
    public function checkForChanges() {
        $readingMode = Versioned::get_reading_mode();

        if ($readingMode == "Stage.Stage" && $owner){
            $versionedManyManyRels = Config::inst()->get($this->ownerBaseClass, "versioned_many_many", Config::EXCLUDE_EXTRA_SOURCES);

            foreach($versionedManyManyRels as $relName => $className) {
                $allStoredArr = $this->relationToArray($relName, $this->getStoredRelation($relName));
                $allStageArr = $this->relationToArray($relName, $this->getVersionedRelation($relName));
                
                $stored = array();
                foreach($allStoredArr as $storedArr){
                    $stored[] = implode(", ", $storedArr);
                }
                
                $stage = array();
                foreach($allStageArr as $stageArr){
                    $stage[] = implode(", ", $stageArr);
                }
                
                if(Count(array_diff($stored, $stage))){
//                    echo "there is something different";
//                    die;
                }
            }
        }
    }
    
    /*
     *
     * @return ArrayList
     */
    public function getVersionedRelation($relationName) {

        $readingMode = Versioned::get_reading_mode();

        if ($readingMode == "Stage.Stage") {

            $hasManyRelations = $this->getHasManyRelationsNames();
            $manyManyRelations = $this->getManyManyRelationsNames();
            $hasOneRelations = $this->getHasOneRelationsNames();

            if (array_key_exists($relationName, $hasManyRelations)) {
                return $this->owner->getComponents($relationName);
            }
            else if (array_key_exists($relationName, $manyManyRelations)) {
                return $this->owner->getManyManyComponents($relationName);
            }
            else if (array_key_exists($relationName, $hasOneRelations)) {
                return $this->owner->getComponent($relationName);
            }
        }
        return $this->getStoredRelation($relationName);
    }

    /*
     * @return array
     */
    private function getExtraFieldsArray($relationName) {
        if ($extra = $this->owner->manyManyExtraFieldsForComponent($relationName)) return $extra;
        return array();
    }
}
