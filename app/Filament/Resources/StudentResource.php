<?php

namespace App\Filament\Resources;

use App\Exports\StudentsExport;
use App\Filament\Resources\StudentResource\Pages;
use App\Models\Classes;
use App\Models\Section;
use App\Models\Student;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Maatwebsite\Excel\Facades\Excel;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationGroup = 'Academic management';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function getNavigationBadge(): ?string
    {
        return static::$model::query()->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->autofocus()
                    ->required(),
                TextInput::make('email')
                    ->unique(ignoreRecord: true)
                    ->autofocus()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->hiddenOn(['edit'])
                    ->required(),
                Select::make('class_id')
                    ->live()
                    ->relationship(name: 'class', titleAttribute: 'name'),
                Select::make('section_id')
                    ->options(function (Get $get) {
                        $classId = $get('class_id');

                        return Section::query()
                            ->where('class_id', $classId)
                            ->pluck('name', 'id')
                            ->toArray();
                })
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('class.name')->searchable()->sortable()->badge(),
                TextColumn::make('section.name')->searchable()->sortable()->badge(),
            ])
            ->filters([
                Filter::make('class-section-filter')->form([
                    Select::make('class_id')
                        ->label('Class')
                        ->options(function () {
                            return Classes::query()
                                ->pluck('name', 'id')
                                ->toArray();
                        }),
                    Select::make('section_id')
                        ->label('Section')
                        ->options(function (Get $get) {
                            $classId = $get('class_id');

                            return Section::query()
                                ->where('class_id', $classId)
                                ->pluck('name', 'id')
                                ->toArray();
                        }),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query->when($data['class_id'], function (Builder $query, $classId) {
                        $query->where('class_id', $classId);
                    })->when($data['section_id'], function (Builder $query, $sectionId) {
                        $query->where('section_id', $sectionId);
                    });
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Invoice')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (Student $record): string => route('student.invoice.generate', $record))
                    ->openUrlInNewTab(),
                Action::make('qrCode')
                    ->url(fn (Student $record): string => static::getUrl('generateQr', ['record' => $record]) )
                    ->openUrlInNewTab()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    BulkAction::make('export')
                        ->label('Export')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (Collection $records) {
                            return Excel::download(new StudentsExport($records), 'students.xlsx');
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
            'generateQr' => Pages\GenerateQrCode::route('/{record}/qrcode'),
        ];
    }
}
