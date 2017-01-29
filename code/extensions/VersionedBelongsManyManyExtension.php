<?php


class VersionedBelongsManyManyExtension extends DataExtension {

    /*
     * Returns the belongs_many_many relations which will be handled for versioning
     *
     * @return array
     */
    private function getRelationsNames() {
        return Config::inst()->get($this->owner->ClassName, 'versioned_belongs_many_many', Config::EXCLUDE_EXTRA_SOURCES);
    }

	public function onAfterWrite() {

        $readingMode = Versioned::get_reading_mode();
        if($readingMode == 'Stage.Stage'){
            foreach($this->getRelationsNames() as $relationName) {
                foreach($this->owner->getManyManyComponents($relationName) as $relation){
                    $relation->storeRelations();
                    $relation->write(true);
                }
            }
        }

    }

}