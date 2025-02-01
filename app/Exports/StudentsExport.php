<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithMapping,WithHeadings
{
    public function __construct(public Collection $records)
    {
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->records;
    }

    public function map($row): array
    {
        return [
            $row->name,
            $row->email,
            $row->section->name,
            $row->class->name,
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Section',
            'Class',
        ];
    }
}
