# SilverStripe Versioned Relations Module

This module provides the possibility to version a silverstripe data object's many-many relation.

## Usage

```php

class MyObject extends DataObject {

    private static $extensions = array(
        'Versioned',
        'VersionedManyManyExtension',
    );

    private static $versioned_many_many = array(
        'Relations',
    );

    private static $many_many = array(
        'Relations' => 'MyRelatedObject',
    );

    // optionally add extra fields
    private static $many_many_extraFields = array(
        'Relations' => array(
            'MyExtra' => 'Int',
        ),
    );

    /*
     * NOTE:
     * If you use the betterbuttons module,
     * get rid of the versioning buttons like this:
     */
    public function getBetterButtonsActions() {

        $fieldList = FieldList::create(array(
            BetterButton_SaveAndClose::create(),
            BetterButton_Save::create(),
        ));
        return $fieldList;

    }
}
```

```php

class MyRelatedObject extends DataObject {

    private static $extensions = array(
        'Versioned',
        'VersionedBelongsManyManyExtension',
    );

    private static $versioned_belongs_many_many = array(
        'MainObjects',
    );

    private static $belongs_many_many = array(
        'MainObjects' => 'MyObject',
    );

}
```



## TODO

* Versioning of has_many relations
* Silverstripe 4 compatibility tests
