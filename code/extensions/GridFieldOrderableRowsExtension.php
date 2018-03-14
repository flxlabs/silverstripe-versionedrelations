<?php
class GridFieldOrderableRowsExtension extends DataExtension {
	public function onAfterReorderItems($items) {
		if($items instanceof ManyManyList) {
			$className =explode("_", $items->getJoinTable())[0];
			$object = DataObject::get($className)->byID($items->getForeignID());

			if($object && $object->hasExtension("VersionedRelationsExtension")) {
				echo "<div id='reorder-happened' data-object-id='{$object->ID}'></div>";
				$object->write();
			}
		}
	}
}
