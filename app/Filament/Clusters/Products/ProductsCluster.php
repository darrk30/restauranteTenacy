<?php

namespace App\Filament\Clusters\Products;

use BackedEnum;
use Filament\Clusters\Cluster;

class ProductsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Productos';

    // protected static ?string $navigationGroup = 'Inventarios';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'gestion';
    
}
