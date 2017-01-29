# SilverStripe Versioned Relations Module

This module provides the possibility to version silverstripe data object's many-many, has-many and has-one relations.

## Usage

Main class

```php

class MyObject extends DataObject {

    private static $extensions = array(
        'Versioned',
        'VersionedRelationsExtension',
    );

    private static $versioned_many_many = array(
        'MMRelations',
    );
    private static $versioned_has_many = array(
        'HMRelations',
    );
    private static $versioned_has_one = array(
        'HORelation',
    );

    private static $many_many = array(
        'MMRelations' => 'MyRelatedObjectX',
    );
    private static $has_many = array(
        'HMRelations' => 'MyRelatedObjectY',
    );
    private static $has_one = array(
        'HORelation' => 'MyRelatedObjectZ',
    );

    // optionally add extra fields for many-many relations
    private static $many_many_extraFields = array(
        'Relations' => array(
            'MyExtra' => 'Int',
        ),
    );

}
```

MyRelatedObjectX class (many-many)


```php

class MyRelatedObjectX extends DataObject {

    private static $extensions = array(
        'Versioned',
        'VersionedRelationsExtension',
    );

    private static $versioned_belongs_many_many = array(
        'MainClasses',
    );

    private static $belongs_many_many = array(
        'MainClasses' => 'MainClass',
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


MyRelatedObjectY class (has-many)


```php

class MyRelatedObjectY extends DataObject {

    private static $extensions = array(
        'Versioned',
        'VersionedRelationsExtension',
    );

    private static $versioned_belongs_has_many = array(
        'MainClass',
    );

    private static $has_one = array(
        'MainClass' => 'MainClass',
    );

}
```


MyRelatedObjectZ class (has-one)


```php

class MyRelatedObjectY extends DataObject {

    private static $extensions = array(
        'Versioned',
        'VersionedRelationsExtension',
    );

    private static $versioned_belongs_to = array(
        'MainClass',
    );

    private static $belongs_to = array(
        'MainClass' => 'MainClass',
    );

}
```

Getting the versioned relations:

```php

…

$this->getVersionedRelation('Relations');

…

```



## TODO

* Check deletion of relations and main classes
* Check Multiple Relations on same class in dot notations
* Add Silverstripe 4 compatibility
