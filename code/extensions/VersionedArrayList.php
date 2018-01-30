<?php

// This class is used when returning a many many relation on a "live" DataObject,
// because we load the relations from the _Store and have to trick SilverStripe
// into thinking that we actually have a ManyManyList
class VersionedArrayList extends ArrayList {
	
	public function forForeignID() {
		return $this;
	}

	public function where() {
		return $this;
	}

	public function sort() {
		return parent::sort(func_get_args());
	}
}
