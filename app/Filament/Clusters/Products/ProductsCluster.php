<?php

namespace App\Filament\Clusters\Products;

use Filament\Clusters\Cluster;

class ProductsCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'gestion';
}
