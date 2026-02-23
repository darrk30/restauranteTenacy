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
use Filament\Forms\Components\Group;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\ToggleColumn;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Banners / Promociones';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Configuración del Banner')
                    ->description('Define si el banner será una imagen completa o un diseño con texto.')
                    ->schema([
                        Select::make('type')
                            ->label('Tipo de Diseño')
                            ->options([
                                'mixed' => 'Diseño Mixto (Texto + Imagen lateral)',
                                'full_image' => 'Imagen Completa (Borde a borde)',
                                'only_text' => 'Solo Texto (Fondo de color)',
                            ])
                            ->default('mixed')
                            ->live() // Permite ocultar/mostrar campos dinámicamente
                            ->required()
                            ->columnSpanFull(),

                        // Grupo dinámico: Se oculta si es Imagen Completa
                        Group::make([
                            Textarea::make('title')
                                ->label('Contenido HTML del Banner')
                                ->helperText('Usa clases de Tailwind. Ej: <h2 class="text-4xl font-bold">Título</h2>')
                                ->rows(8)
                                ->autosize()
                                ->extraInputAttributes([
                                    'style' => 'font-family: monospace; font-size: 14px; line-height: 1.5;',
                                ])
                                ->placeholder('<h2 class="text-white text-3xl md:text-5xl font-black">...</h2>')
                                ->columnSpanFull(),

                            ColorPicker::make('bg_color')
                                ->label('Color de fondo')
                                ->default('#0f643b')
                                ->required()
                                ->dehydrated(fn($state, Forms\Get $get) => $get('type') !== 'full_image'),
                        ])
                            ->columnSpanFull()
                            ->hidden(fn(Forms\Get $get) => $get('type') === 'full_image'),

                        FileUpload::make('image')
                            ->label('Imagen para PC')
                            ->directory('banners')
                            ->imageEditor()
                            ->required(),

                        FileUpload::make('image_mobile')
                            ->label('Imagen para Móvil (Opcional)')
                            ->directory('banners')
                            ->imageEditor()
                            ->helperText('Si no se sube, se usará la de PC en móviles.'),

                        Toggle::make('is_active')
                            ->label('¿Mostrar este banner?')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagen')
                    ->circular()
                    ->size(50),

                TextColumn::make('title')
                    ->label('Contenido')
                    ->words(8)
                    ->html()
                    ->searchable()
                    ->placeholder('Sin contenido (Imagen full)'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),

                ColorColumn::make('bg_color')
                    ->label('Color'),

                ToggleColumn::make('is_active')
                    ->label('Activo'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
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
