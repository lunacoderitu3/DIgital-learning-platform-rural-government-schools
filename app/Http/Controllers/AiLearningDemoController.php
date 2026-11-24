<?php

namespace App\Http\Controllers;

class AiLearningDemoController extends Controller
{
    public function index()
    {
        return redirect()->route('ai-learning.assistant');
    }

    public function assistant()
    {
        return $this->renderModule('assistant', 'AI Learning Assistant', 'Student-friendly answers with demo AI support');
    }

    public function quiz()
    {
        return $this->renderModule('quiz', 'Smart Quiz Generator', 'Generate adaptive practice quizzes');
    }

    public function personalized()
    {
        return $this->renderModule('personalized', 'Personalized Learning', 'ML-inspired learning guidance for students');
    }

    public function multilingual()
    {
        return $this->renderModule('multilingual', 'Multilingual Learning', 'English, Hindi and Kannada demo lessons');
    }

    public function analytics()
    {
        return $this->renderModule('analytics', 'AI Learning Analytics', 'Teacher-ready progress dashboard');
    }

    public function offline()
    {
        return $this->renderModule('offline', 'Offline Learning Support', 'Downloadable sample lessons and saved resources');
    }

    protected function renderModule(string $module, string $title, string $subtitle)
    {
        return view('ai_learning.demo', [
            'module' => $module,
            'pageTitle' => $title,
            'subtitle' => $subtitle,
        ]);
    }
}
