<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function index(): View
    {
        if (!Auth::check() || !Auth::user()->hasRole('Super Admin')) {
            abort(403);
        }

        $categories = [
            [
                'title' => 'Getting Started',
                'icon' => 'fa-rocket',
                'description' => 'Launch your school administration workflow and configure core platform settings.',
                'links' => [
                    ['label' => 'System Overview', 'href' => '#overview'],
                    ['label' => 'Admin Setup', 'href' => '#setup'],
                ],
            ],
            [
                'title' => 'Admin Panel',
                'icon' => 'fa-user-shield',
                'description' => 'Manage users, permissions, settings, and application-wide configuration.',
                'links' => [
                    ['label' => 'System Settings', 'href' => '#settings'],
                    ['label' => 'User Roles', 'href' => '#roles'],
                ],
            ],
            [
                'title' => 'School Operations',
                'icon' => 'fa-school',
                'description' => 'Configure academic structures, students, staff, fees, classrooms, and reporting.',
                'links' => [
                    ['label' => 'Student Management', 'href' => '#students'],
                    ['label' => 'Fees & Reports', 'href' => '#fees'],
                ],
            ],
            [
                'title' => 'Realtime & Automation',
                'icon' => 'fa-wifi',
                'description' => 'Use queue processing, Reverb, notifications, and live updates across the application.',
                'links' => [
                    ['label' => 'Queue Worker', 'href' => '#queue'],
                    ['label' => 'Realtime WebSocket', 'href' => '#reverb'],
                ],
            ],
            [
                'title' => 'System Maintenance',
                'icon' => 'fa-cogs',
                'description' => 'Keep the installation healthy with upgrades, maintenance mode, backups, and diagnostics.',
                'links' => [
                    ['label' => 'System Update', 'href' => '#update'],
                    ['label' => 'Diagnostics', 'href' => '#diagnostics'],
                ],
            ],
        ];

        return view('documentation.index', [
            'productName' => 'VidyaSetu',
            'categories' => $categories,
        ]);
    }
}
