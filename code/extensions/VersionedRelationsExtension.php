<?php
class VersionedRelationsExtension extends DataExtension {
    /*
     * create store for historical versions, duplicate relation for live
     *
     * @return array
     */
    public function extraStatics($class = null, $extension = null) {
        $statics = array(
            'db' => array(),
        );

        $versionedHasManyRels = Config::inst()->get($class, 'versioned_has_many', Config::EXCLUDE_EXTRA_SOURCES);
        $versionedManyManyRels = Config::inst()->get($class, 'versioned_many_many', Config::EXCLUDE_EXTRA_SOURCES);
        //$versionedBelongsManyManyRels = Config::inst()->get($class, 'versioned_belongs_many_many', Config::EXCLUDE_EXTRA_SOURCES);
        $versionedHasOneRels = Config::inst()->get($class, 'versioned_has_one', Config::EXCLUDE_EXTRA_SOURCES);

        $origHasManyRels = Config::inst()->get($class, 'has_many', Config::EXCLUDE_EXTRA_SOURCES);
        $origManyManyRels = Config::inst()->get($class, 'many_many', Config::EXCLUDE_EXTRA_SOURCES);
        $origHasOneRels = Config::inst()->get($class, 'has_one', Config::EXCLUDE_EXTRA_SOURCES);

        if( is_array($versionedHasManyRels) ) {
            foreach($versionedHasManyRels as $relationName) {

                if(!isset($origHasManyRels[$relationName])) {
                    throw new Exception("{$class} needs to define a has_many relation $relationName");
                }

                // textfield to store historical values for relation
                $statics['db'][$relationName . '_Store'] = 'Text';
            }
        }

        if( is_array($versionedManyManyRels) ) {
            foreach($versionedManyManyRels as $relationName) {

                if(!isset($origManyManyRels[$relationName])) {
                    throw new Exception("{$class} needs to define a many_many relation $relationName");
                }

                // textfield to store historical values for relation
                $statics['db'][$relationName . '_Store'] = 'Text';
            }
        }

        if( is_array($versionedHasOneRels) ) {
            foreach($versionedHasOneRels as $relationName) {

                if(!isset($origHasOneRels[$relationName])) {
                    throw new Exception("{$class} needs to define a has_one relation $relationName");
                }

                // textfield to store historical values for relation
                $statics['db'][$relationName . '_Version'] = 'Int';
            }
        }
        return $statics;
    }

    /*
     * Returns the has_many relations which will be handled for versioning
     *
     * @return array
     */
    private function getBelongsManyManyRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_belongs_many_many', Config::EXCLUDE_EXTRA_SOURCES) ?: array();
    }

    /*
     * Returns the many_many relations which will be handled for versioning
     *
     * @return array
     */
    private function getManyManyRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_many_many', Config::EXCLUDE_EXTRA_SOURCES) ?: array();
    }

    /*
     * Returns the has_many relations which will be handled for versioning
     *
     * @return array
     */
    private function getHasManyRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_has_many', Config::EXCLUDE_EXTRA_SOURCES) ?: array();
    }

    /*
     * Returns the has_one relations which will be handled for versioning
     *
     * @return array
     */
    private function getBelongsHasManyRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_belongs_has_many', Config::EXCLUDE_EXTRA_SOURCES) ?: array();
    }

    /*
     * Returns the has_one relations which will be handled for versioning
     *
     * @return array
     */
    private function getHasOneRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_has_one', Config::EXCLUDE_EXTRA_SOURCES) ?: array();
    }

    /*
     * Returns the belongs_to relations which will be handled for versioning
     *
     * @return array
     */
    private function getBelongsToRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_belongs_to', Config::EXCLUDE_EXTRA_SOURCES) ?: array();
    }

    /*
     * store relations as json in corresponding field
     *
     */
    private function storeRelation($relationName, $list){
        $store = array();

        foreach($list as $relation) {
            // store basics
            $entryStore = array(
                'ClassName'=>$relation->ClassName,
                'ID'=>$relation->ID,
            );
            if(isset($relation->Version)) {
                $entryStore['Version'] = $relation->Version;
            }

            // store extraFields
            //if($type == 'many_many') {
            foreach($this->getExtraFieldsArray( $relationName ) as $k=>$v) {
                $entryStore[$k] = $relation->$k;
            }
            //}

            $store[] = $entryStore;
        }

        $json = Convert::array2json($store);
        $this->owner->setField($relationName . '_Store', $json);
    }

    /*
     * store relations as json in corresponding field
     */
    public function storeRelations(){
        $readingMode = Versioned::get_reading_mode( );
        Versioned::set_reading_mode( 'Stage.Stage' );

        foreach($this->getManyManyRelationsNames() as $relationName) {
            $this->storeRelation($relationName, $this->owner->getManyManyComponents($relationName));
        }

        foreach($this->getHasManyRelationsNames() as $relationName) {
            $this->storeRelation($relationName, $this->owner->getComponents($relationName));
        }

        foreach($this->getHasOneRelationsNames() as $relationName) {
            // get version of relation
            if( $obj = $this->owner->getComponent($relationName) ) {
                if( isset($obj->Version) ) $this->owner->setField($relationName . '_Version', $obj->Version);
            }
        }

        Versioned::set_reading_mode( $readingMode );
    }

    /*
     * store current setup for relations
     */
    public function onBeforeWrite(){
        $this->storeRelations();
    }

    /*
     * notify other end of relation about change
     */
    public function onAfterWrite() {
        $readingMode = Versioned::get_reading_mode();
        if($readingMode == 'Stage.Stage'){

            foreach($this->getBelongsManyManyRelationsNames() as $relationName) {
                if( $relations = $this->owner->getManyManyComponents($relationName)) {
                    foreach($relations as $relation){
                        if($relation->ID) {
                            $relation->storeRelations();
                            $relation->write(true);
                        }
                    }
                }
            }

            foreach($this->getBelongsHasManyRelationsNames() as $relationName) {
                if( $relation = $this->owner->getComponent($relationName)) {
                    if($relation->ID) {
                        $relation->storeRelations();
                        $relation->write(true);
                    }
                }
            }

            foreach($this->getBelongsToRelationsNames() as $relationName) {
                //if( $relation = $this->owner->belongsToComponent($relationName)) {
                if( $relation = $this->owner->getComponent($relationName)) {
                    if($relation->ID) {
                        $relation->storeRelations();
                        $relation->write(true);
                    }
                }
            }
        }
    }

    private function getVersionedObj(array $entry) {
        if(!isset($entry['Version']) || $entry['Version'] === 0) {
            return DataObject::get( $entry['ClassName'] )->byID($entry['ID']);
        } else {
            return Versioned::get_version( $entry['ClassName'], $entry['ID'], $entry['Version'] );
        }
    }

    /*
     * empty relation list when rolling back page
     * then fill it with stored historical relation list
     */
    public function onBeforeRollback( $version ){
        foreach($this->getManyManyRelationsNames() as $relationName) {
            $this->rollbackRelation($relationName, $this->owner->getManyManyComponents($relationName), $version);
        }

        foreach($this->getHasManyRelationsNames() as $relationName) {
            $this->rollbackRelation($relationName, $this->owner->getComponents($relationName), $version);
        }

        foreach($this->getHasOneRelationsNames() as $relationName) {
            if($versionedOwner = $this->getRolledbackOwner( $version )) {

                //$objClassName = $this->owner->getComponent($relationName);
                $objID = $versionedOwner->{$relationName . 'ID'};
                if( $objID ) { // dont roll back if it was not existing then
                    $objVersion = $versionedOwner->{$relationName . '_Version'};
                    //$foreignObj = DataObject::get( $objClassName )->byID( $objID );
                    $foreignObj = $this->owner->getComponent($relationName);

                    if( $foreignObj ) {
                        // rollback versionable related object
                        if( $foreignObj->hasExtension('Versioned') ) {
                            $foreignObj->doRollbackTo( $objVersion );
                        }
                    }
                }
            }
        }
    }

    private function getRolledbackOwner( $version ) {
        if($version == Versioned::get_live_stage()) {
            // rolls back to published version
            $versionNum = Versioned::get_versionnumber_by_stage( $this->owner->ClassName, Versioned::get_live_stage(), $this->owner->ID );
        } else {
            $versionNum = $version;
        }

        // rolls back to a past version
        return $this->getVersionedObj(array( 'ID'=>$this->owner->ID, 'ClassName'=>$this->owner->ClassName, 'Version'=>$versionNum ));
    }

    private function rollbackRelation($relationName, $list, $version) {
        $storeFieldName = $relationName . '_Store';
        //$list = $this->owner->getComponents($relationName);
        $list->removeAll();

        $versionedOwner = $this->getRolledbackOwner( $version );

        // fill relations
        if( $versionedOwner && $versionedOwner->$storeFieldName ) {
            if($store = Convert::json2Array( $versionedOwner->$storeFieldName )) {
                foreach( $store as $entry ) {

                    if( $foreignObj = DataObject::get( $entry['ClassName'] )->byID( $entry['ID'] ) ) {

                        // rollback versionable related object
                        if( $foreignObj->hasExtension('Versioned') && $this->getVersionedObj($entry) ) {
                            $foreignObj->doRollbackTo( $entry['Version'] );
                        }
                        // extraFields
                        $extras = array();
                        foreach($this->getExtraFieldsArray( $relationName ) as $k=>$v) {
                            if( isset($entry[$k]) ) $extras[$k] = $entry[$k];
                        }
                        // add
                        $list->add($foreignObj, $extras);

                    }
                }
            }
        }
    }

    
    public function updateCMSFields( FieldList $fields ) {
        foreach($this->getManyManyRelationsNames() as $relationName) {
            $fields->removeByName($relationName . '_Store');
//            $fields->addFieldToTab('Root.Sections',TextareaField::create($relationName . '_Store'));
        }

        foreach($this->getHasManyRelationsNames() as $relationName) {
            $fields->removeByName($relationName . '_Store');
//            $fields->addFieldToTab('Root.Sections',TextareaField::create($relationName . '_Store'));
        }
    }
    

    /*
     *
     * @return ArrayList
     */
    private function getStoredRelation( $relationName ) {
        $hasManyRelations = $this->getHasManyRelationsNames();
        $manyManyRelations = $this->getManyManyRelationsNames();
        $hasOneRelations = $this->getHasOneRelationsNames();

        if( in_array($relationName, $hasManyRelations) || in_array($relationName, $manyManyRelations) ) {
            $json = $this->owner->getField( $relationName . '_Store' );

            $ret = ArrayList::create();
            if( $entries = Convert::json2Array( $json ) ) {
                foreach( $entries as $entry ) {
                    if( $versionedObj = $this->getVersionedObj($entry) ){
                        // additional extra fields
                        foreach($this->getExtraFieldsArray( $relationName ) as $k=>$v) {
                            $versionedObj->$k = $entry[$k];
                        }
                        $ret->add( $versionedObj );
                    }
                }
            }
            return $ret;
        }

        else if( in_array($relationName, $hasOneRelations) ) {
            if( $versionedObj = $this->getVersionedObj(
                array(
                    'ID' => $this->owner->{$relationName . 'ID'},
                    'ClassName' => $this->owner->getComponent($relationName),
                    'Version' => $this->owner->{$relationName . '_Version'}
                )
            ) ) {
                return $versionedObj;
            }
        }
    }

    /*
     *
     * @return ArrayList
     */
    public function getVersionedRelation( $relationName ) {

        $readingMode = Versioned::get_reading_mode();
        if($readingMode == 'Stage.Stage'){

            $hasManyRelations = $this->getHasManyRelationsNames();
            $manyManyRelations = $this->getManyManyRelationsNames();
            $hasOneRelations = $this->getHasOneRelationsNames();

            if( in_array($relationName, $hasManyRelations) ) {
                return $this->owner->getComponents($relationName);
            }
            else if( in_array($relationName, $manyManyRelations) ) {
                return $this->owner->getManyManyComponents($relationName);
            }
            else if( in_array($relationName, $hasOneRelations) ) {
                return $this->owner->getComponent($relationName);
            }
        }
        return $this->getStoredRelation($relationName);
    }

    /*
     * @return array
     */
    private function getExtraFieldsArray( $relationName ) {
        if($extra = $this->owner->manyManyExtraFieldsForComponent($relationName)) return $extra;
        return array();
    }
}
