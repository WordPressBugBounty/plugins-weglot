<?php

namespace Weglot\Parser\Definitions\Shared;

trait AbstractCollectionCountable
{
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->collection);
    }
}
