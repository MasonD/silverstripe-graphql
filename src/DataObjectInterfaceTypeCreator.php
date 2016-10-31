<?php

namespace SilverStripe\GraphQL;

use SilverStripe\GraphQL\Util\CaseInsensitiveFieldAccessor;
use GraphQL\Type\Definition\Type;

class DataObjectInterfaceTypeCreator extends InterfaceTypeCreator {

    public function attributes()
    {
        return [
            'name' => 'DataObject',
            'description' => 'Base Interface',
        ];
    }

    public function fields() {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
            'created' => [
                'type' => Type::string(),
            ],
            'lastEdited' => [
                'type' => Type::string(),
            ],

        ];
    }

    public function resolveType($object)
    {
        $type = null;

        if($fqnType = $this->manager->getType(get_class($object))) {
            $type = $fqnType;
        }

        if($baseType = $this->manager->getType(get_class($object))) {
            $type = $baseType;
        }

        return $type;
    }

}
