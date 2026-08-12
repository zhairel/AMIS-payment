<?php

namespace App\Http\Controllers;

use App\Models\ReceiptSubmission;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function show(Request $request, Student $student)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'staff', 'finance'], true), 403);

        $receipts = ReceiptSubmission::query()
            ->where('student_id', $student->id)
            ->orWhereHas('user', fn ($q) => $q->whereHas('students', fn ($sq) => $sq->where('students.id', $student->id)))
            ->latest()
            ->get();

        return view('admin.students.show', compact('student', 'receipts'));
    }
}
