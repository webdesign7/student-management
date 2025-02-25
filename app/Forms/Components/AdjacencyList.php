<?php

namespace App\Forms\Components;

use Saade\FilamentAdjacencyList\Forms\Components\AdjacencyList as BaseAdjacencyList;

class AdjacencyList extends BaseAdjacencyList
{
  use \App\Forms\Components\Concerns\HasRelationship;
}
