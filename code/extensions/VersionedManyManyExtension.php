<?php

class VersionedManyManyExtension extends DataExtension {


    /*
     * create store for historical versions, duplicate relation for live
     *
     * @return array
     */
    public function extraStatics($class = null, $extension = null) {

        $statics = array(
            'db' => array(),
        );

        $versionedRels = Config::inst()->get($class, 'versioned_many_many', Config::EXCLUDE_EXTRA_SOURCES);
        if(!isset($versionedRels)) {
            throw new Exception("{$class} needs to define a static \$versioned_many_many array");
        }

        $origRels = Config::inst()->get($class, 'many_many', Config::EXCLUDE_EXTRA_SOURCES);
        foreach($versionedRels as $relationName) {

            if(!isset($origRels[$relationName])) {
                throw new Exception("{$class} needs to define a many_many relation $relationName");
            }

            // textfield to store historical values for relation
            $statics['db'][$relationName . '_Store'] = 'Text';
        }

        return $statics;

    }

    /*
     * Returns the many_many relations which will be handled for versioning
     *
     * @return array
     */
    private function getRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_many_many', Config::EXCLUDE_EXTRA_SOURCES);
    }


    /*
     * store relations as json in corresponding field
     */
    public function storeRelations(){
        //
        $readingMode = Versioned::get_reading_mode( );
        Versioned::set_reading_mode( 'Stage.Stage' );
        foreach($this->getRelationsNames() as $relationName) {
            $store = array();

            foreach($this->owner->getManyManyComponents($relationName) as $relation){
                // store basics
                $entryStore = array(
                    'ClassName'=>$relation->ClassName,
                    'ID'=>$relation->ID,
                );
                if(isset($relation->Version)) {
                    $entryStore['Version'] = $relation->Version;
                }
                // store extraFields
                foreach($this->getExtraFieldsArray( $relationName ) as $k=>$v) {
                    $entryStore[$k] = $relation->$k;
                }

                $store[] = $entryStore;
            }
            $json = Convert::array2json($store);
            $this->owner->setField($relationName . '_Store', $json);
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
     * empty relation list when rolling back page
     * then fill it with stored historical relation list
     */
	public function onBeforeRollback( $version ){

        foreach($this->getRelationsNames() as $relationName) {
            $this->rollbackRelation($relationName, $version);
        }

    }


    private function getVersionedObj(array $entry) {
        if($entry['Version'] === 0) {
            return DataObject::get( $entry['ClassName'] )->byID($entry['ID']);
        } else {
            return Versioned::get_version( $entry['ClassName'], $entry['ID'], $entry['Version'] );
        }
    }


    private function rollbackRelation($relationName, $version) {
        $storeFieldName = $relationName . '_Store';
        $list = $this->owner->getManyManyComponents($relationName);
        $list->removeAll();


        if($version == Versioned::get_live_stage()) {
            // rolls back to published version
            $versionNum = Versioned::get_versionnumber_by_stage( $this->owner->ClassName, Versioned::get_live_stage(), $this->owner->ID );
        } else {
            $versionNum = $version;
        }

        // rolls back to a past version
        $versionedOwner = $this->getVersionedObj(array( 'ID'=>$this->owner->ID, 'ClassName'=>$this->owner->ClassName, 'Version'=>$versionNum ));

        // fill relation
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


    /*
     *
     */
    /*
	public function updateCMSFields( FieldList $fields ) {

        foreach($this->getRelationsNames() as $relationName) {
            $fields->addFieldToTab('Root.Sections',TextareaField::create($relationName . '_Store'));
        }

    }
    */

    /*
     *
     * @return ArrayList
     */
	private function getStoredRelation( $relationName ){
        $json = $this->owner->getField( $relationName . '_Store' );

        $ret = ArrayList::create();
        if( $entries = Convert::json2Array( $json ) ) {
            foreach( $entries as $entry ) {
                if( $versionedObj = $this->getVersionedObj($entry) ){
                    // additional extra fields
                    foreach($this->getExtraFieldsArray( $relationName ) as $k=>$v) {
                        if($k)
                            $versionedObj->$k = $entry[$k];
                    }
                    $ret->add( $versionedObj );
                }
            }
        }
        return $ret;
    }

    /*
     *
     * @return ArrayList
     */
    public function getVersionedRelation( $relationName ){

        $readingMode = Versioned::get_reading_mode();
        if($readingMode == 'Stage.Stage'){
            return $this->owner->getManyManyComponents($relationName);
        }
        return $this->getStoredRelation($relationName);
    }


    /*
     *
     * @return array
     */
    private function getExtraFieldsArray( $relationName ){

        if($extra = $this->owner->manyManyExtraFieldsForComponent($relationName)) return $extra;

        return array();

    }


}