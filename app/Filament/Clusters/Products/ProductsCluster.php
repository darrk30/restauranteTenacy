<?php

namespace App\Filament\Clusters\Products;

use Filament\Clusters\Cluster;

class ProductsCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $navigationGroup = 'Inventarios';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'gestion';
    
}
