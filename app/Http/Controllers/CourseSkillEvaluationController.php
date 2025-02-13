<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Result;
use App\Models\ResultType;
use App\Models\Skills\SkillEvaluation;
use App\Models\Skills\SkillScale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseSkillEvaluationController extends Controller
{
    /**
     * Show the skills overview for all students in the course.
     */
    public function index(Course $course)
    {
        if (Gate::forUser(backpack_user())->denies('view-course', $course)) {
            abort(403);
        }

        return view('skills.students', [
            'course' => $course,
            'skills' => $course->skills->groupBy('skill_type_id'),
            'enrollments' => $course->enrollments()->with('skillEvaluations')->get(),
        ]);
    }

    /**
     * Store a skill evaluation record for a student.
     */
    public function store(Request $request)
    {
        $skill = $request->input('skill');
        $status = $request->input('status');
        $enrollment = Enrollment::findOrFail($request->input('enrollment_id'));

        if (Gate::forUser(backpack_user())->denies('view-course', $enrollment->course)) {
            abort(403);
        }

        $new_skill = SkillEvaluation::firstOrNew([
            'enrollment_id' => $enrollment->id,
            'skill_id' => $skill,
        ]);

        $new_skill->skill_scale_id = $status;
        $new_skill->save();

        return $new_skill->skill_scale_id;
    }

    /**
     * Show the form for editing a specific student's skills for the specified course.
     */
    public function edit(Enrollment $enrollment)
    {
        if (Gate::forUser(backpack_user())->denies('view-enrollment', $enrollment)) {
            abort(403);
        }

        $student_skills = $enrollment->skillEvaluations;

        $course = Course::with('evaluationType')->find($enrollment->course_id);

        $skills = $course->skills->map(function ($skill, $key) use ($student_skills) {
            $skill['status'] = $student_skills->where('skill_id', $skill->id)->first()->skill_scale_id ?? null;
            $skill['skill_type_name'] = $skill->skillType->name;

            return $skill;
        })->groupBy('skill_type_id');

        $result = Result::where(['enrollment_id' => $enrollment->id])->with('result_name')->first();

        $results = ResultType::all();
        $skillScales = SkillScale::orderBy('value')->get();
        $writeaccess = config('settings.teachers_can_edit_result') || backpack_user()->can('enrollments.edit') ?? 0;

        return view('skills.student', ['enrollment' => $enrollment, 'skills' => $skills, 'skillScales' => $skillScales, 'result' => $result, 'enrollment' => $enrollment, 'results' => $results, 'writeaccess' => $writeaccess]);
    }
}
