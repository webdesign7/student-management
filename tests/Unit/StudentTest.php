<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Student;
use App\Models\Section;
use App\Models\Classes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $student = new Student();
        
        $this->assertEquals(
            ['name', 'section_id', 'class_id', 'email', 'password'],
            $student->getFillable()
        );
    }

    /** @test */
    public function it_uses_student_guard()
    {
        $student = new Student();
        
        $this->assertEquals('student', $student->getAttributeValue('guard'));
    }

    /** @test */
    public function it_belongs_to_a_section()
    {
        $student = new Student();
        
        $this->assertInstanceOf(
            BelongsTo::class,
            $student->section()
        );
    }

    /** @test */
    public function it_belongs_to_a_class()
    {
        $student = new Student();
        
        $this->assertInstanceOf(
            BelongsTo::class,
            $student->class()
        );
    }

    /** @test */
    public function password_is_hashed()
    {
        $student = new Student();
        
        $this->assertEquals(
            ['password' => 'hashed'],
            $student->getCasts()
        );
    }
} 