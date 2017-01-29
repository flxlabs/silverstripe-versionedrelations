<?php


class VersionedBelongsManyManyExtension extends DataExtension {

    public function setOwner($owner, $ownerBaseClass = null) {

        parent::setOwner($owner, $ownerBaseClass);

        if( $owner ) {
            // check if owner has versioned extension
            if(!$owner->hasExtension('Versioned')) {
                throw new Exception("{$this->owner->ClassName} need to have Versioned Extension");
            }
        }

    }


    /*
     * Returns the belongs_many_many relations which will be handled for versioning
     *
     * @return array
     */
    private function getRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_belongs_many_many', Config::EXCLUDE_EXTRA_SOURCES);
    }



	public function onAfterWrite() {

        foreach($this->getRelationsNames() as $relationName) {
            foreach($this->owner->getManyManyComponents($relationName) as $relation){
                echo $relation->ClassName.";".$relation->ID." ";
                $relation->storeRelations();
                $relation->write(true);
            }
        }

    }

    /*
    public function getBetterButtonsActions() {

        $fieldList = FieldList::create(array(
            BetterButton_SaveAndClose::create(),
            BetterButton_Save::create(),
        ));
        return $fieldList;

    }
    */
}