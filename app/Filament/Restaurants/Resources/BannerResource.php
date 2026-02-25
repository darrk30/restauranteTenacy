<?php

namespace App\Filament\Restaurants\Resources;

use App\Filament\Restaurants\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Banners / Promociones';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 130;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuración del Banner')
                    ->description('Sube las imágenes para tu banner promocional.')
                    ->schema([
                        // Ocultamos el selector de tipo y lo forzamos a imagen completa
                        Forms\Components\Hidden::make('type')
                            ->default('full_image'),

                        // Ocultamos el área de texto HTML
                        Textarea::make('title')
                            ->hidden(),

                        // Ocultamos el selector de color
                        ColorPicker::make('bg_color')
                            ->hidden(),

                        FileUpload::make('image')
                            ->label('Imagen para PC / Tablet')
                            ->helperText('Formato recomendado: 1200x400px')
                            ->directory('banners')
                            ->imageEditor()
                            ->required()
                            ->columnSpan(1),

                        FileUpload::make('image_mobile')
                            ->label('Imagen para Móvil (Opcional)')
                            ->helperText('Si se deja vacío, se usará la de PC.')
                            ->directory('banners')
                            ->imageEditor()
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label('¿Banner visible?')
                            ->default(true)
                            ->columnSpanFull(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagen PC')
                    ->circular()
                    ->size(60),

                ImageColumn::make('image_mobile')
                    ->label('Imagen Móvil')
                    ->circular()
                    ->size(60)
                    ->placeholder('Usa PC'),

                ToggleColumn::make('is_active')
                    ->label('Activo'),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
