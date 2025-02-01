<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestStudents extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Student::query()
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('class.name')->searchable()->sortable()->badge(),
                TextColumn::make('section.name')->searchable()->sortable()->badge(),
            ]);
    }
}
