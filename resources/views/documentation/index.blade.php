@extends('layouts.master')

@section('title')
    VidyaSetu Documentation
@endsection

@section('css')
    <style>
        :root {
            --vidyase-tu-primary: #1f5f8b;
            --vidyase-tu-primary-dark: #133f5d;
            --vidyase-tu-accent: #2bb3a7;
            --vidyase-tu-navy: #0f172a;
            --vidyase-tu-surface: #f5f8fb;
            --vidyase-tu-card: #ffffff;
            --vidyase-tu-border: #e3edf7;
            --vidyase-tu-text: #1d2a39;
            --vidyase-tu-muted: #5c7286;
            --vidyase-tu-shadow: 0 18px 40px rgba(19, 63, 93, 0.08);
        }

        .vidya-docs-page {
            padding: 24px 0 48px;
            background: linear-gradient(180deg, #f6fafc 0%, #edf3f9 100%);
            color: var(--vidyase-tu-text);
        }

        .vidya-docs-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 18px;
        }

        .vidya-docs-header {
            background: linear-gradient(135deg, var(--vidyase-tu-primary-dark) 0%, var(--vidyase-tu-primary) 52%, var(--vidyase-tu-accent) 100%);
            border-radius: 28px;
            padding: 32px 36px;
            color: #fff;
            box-shadow: var(--vidyase-tu-shadow);
            position: relative;
            overflow: hidden;
        }

        .vidya-docs-header::after {
            content: '';
            position: absolute;
            inset: auto -80px -100px auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .vidya-docs-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            position: relative;
            z-index: 1;
        }

        .vidya-docs-brand {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .vidya-docs-logo {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .vidya-docs-brand h1 {
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .vidya-docs-brand small {
            display: block;
            margin-top: 6px;
            color: rgba(255,255,255,0.82);
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .vidya-docs-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .vidya-docs-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            font-weight: 600;
            font-size: 0.82rem;
        }

        .vidya-docs-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 22px;
            margin-top: 28px;
        }

        .vidya-docs-sidebar {
            grid-column: span 3;
        }

        .vidya-docs-main {
            grid-column: span 9;
        }

        .vidya-docs-nav,
        .vidya-docs-card,
        .vidya-docs-feature {
            background: var(--vidyase-tu-card);
            border: 1px solid var(--vidyase-tu-border);
            border-radius: 24px;
            box-shadow: var(--vidyase-tu-shadow);
        }

        .vidya-docs-nav {
            padding: 20px 18px;
            position: sticky;
            top: 24px;
        }

        .vidya-docs-nav h4 {
            margin: 0 0 18px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--vidyase-tu-muted);
            font-weight: 800;
        }

        .vidya-docs-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 8px;
        }

        .vidya-docs-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            color: var(--vidyase-tu-text);
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .vidya-docs-nav a:hover,
        .vidya-docs-nav a:focus {
            background: rgba(31,95,139,0.06);
            color: var(--vidyase-tu-primary-dark);
            text-decoration: none;
        }

        .vidya-docs-nav i {
            width: 18px;
            color: var(--vidyase-tu-primary);
        }

        .vidya-docs-feature {
            padding: 26px 22px;
        }

        .vidya-docs-feature + .vidya-docs-feature {
            margin-top: 22px;
        }

        .feature-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }

        .feature-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: rgba(31,95,139,0.08);
            color: var(--vidyase-tu-primary-dark);
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .feature-label i {
            font-size: 0.9rem;
        }

        .feature-head h2 {
            margin: 0;
            font-size: clamp(1.5rem, 2.1vw, 2rem);
            font-weight: 800;
        }

        .feature-body {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: #f8fbff;
            border: 1px solid var(--vidyase-tu-border);
            border-radius: 18px;
            padding: 18px;
        }

        .feature-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .feature-card p {
            margin: 0 0 14px;
            color: var(--vidyase-tu-muted);
            line-height: 1.7;
        }

        .feature-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 9px;
        }

        .feature-card li {
            position: relative;
            padding-left: 18px;
            color: var(--vidyase-tu-text);
        }

        .feature-card li::before {
            content: '•';
            position: absolute;
            left: 0;
            top: 0;
            color: var(--vidyase-tu-accent);
            font-size: 1.2rem;
            line-height: 1;
        }

        .vidya-docs-footer {
            margin-top: 26px;
            background: var(--vidyase-tu-navy);
            border-radius: 22px;
            color: rgba(255,255,255,0.9);
            padding: 20px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .vidya-docs-footer strong {
            color: #fff;
        }

        @media (max-width: 991px) {
            .vidya-docs-sidebar,
            .vidya-docs-main {
                grid-column: 1 / -1;
            }

            .vidya-docs-nav {
                position: relative;
                top: auto;
            }

            .feature-body {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .vidya-docs-header {
                padding: 22px 20px;
            }

            .vidya-docs-header-inner,
            .feature-head,
            .vidya-docs-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .vidya-docs-brand {
                align-items: flex-start;
            }
        }
    </style>
@endsection

@section('content')
    <div class="vidya-docs-page">
        <div class="vidya-docs-shell">
            <header class="vidya-docs-header">
                <div class="vidya-docs-header-inner">
                    <div class="vidya-docs-brand">
                        <div class="vidya-docs-logo">
                            <i class="fa fa-graduation-cap"></i>
                        </div>
                        <div>
                            <small>Documentation</small>
                            <h1>VidyaSetu</h1>
                        </div>
                    </div>
                    <div class="vidya-docs-actions">
                        <span class="vidya-docs-badge"><i class="fa fa-shield-alt"></i> Secure Admin Panel</span>
                        <span class="vidya-docs-badge"><i class="fa fa-project-diagram"></i> Education SaaS</span>
                    </div>
                </div>
            </header>

            <div class="vidya-docs-grid">
                <aside class="vidya-docs-sidebar">
                    <nav class="vidya-docs-nav">
                        <h4>Sections</h4>
                        <ul>
                            <li><a href="#overview"><i class="fa fa-lightbulb"></i> Overview</a></li>
                            <li><a href="#setup"><i class="fa fa-cog"></i> Setup</a></li>
                            <li><a href="#settings"><i class="fa fa-sliders-h"></i> Settings</a></li>
                            <li><a href="#students"><i class="fa fa-user-graduate"></i> Students</a></li>
                            <li><a href="#fees"><i class="fa fa-wallet"></i> Fees</a></li>
                            <li><a href="#queue"><i class="fa fa-layer-group"></i> Queue</a></li>
                            <li><a href="#reverb"><i class="fa fa-wifi"></i> Reverb</a></li>
                            <li><a href="#update"><i class="fa fa-cloud-download-alt"></i> Update</a></li>
                        </ul>
                    </nav>
                </aside>

                <main class="vidya-docs-main">
                    <section class="vidya-docs-feature" id="overview">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-lightbulb"></i> Overview</div>
                            <h2>Professional education administration platform</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>What is VidyaSetu?</h3>
                                <p>VidyaSetu is a modern school and academy management platform designed for efficient academic operations, robust administration, and responsive digital workflows.</p>
                                <ul>
                                    <li>Multi-role admin access</li>
                                    <li>Academic and fee management</li>
                                    <li>Realtime communication tools</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Core capabilities</h3>
                                <p>The platform helps administrators manage students, staff, school settings, reports, and campus automation from a single, unified system.</p>
                                <ul>
                                    <li>School onboarding and setup</li>
                                    <li>Operational dashboards and reporting</li>
                                    <li>Secure maintenance and updates</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="setup">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-cog"></i> Setup</div>
                            <h2>Launch and configuration</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>Initial setup</h3>
                                <p>Complete the academy setup wizard, validate server configuration, and confirm that the required PHP, database, queue, and Reverb services are operational before using the system.</p>
                                <ul>
                                    <li>Verify database access</li>
                                    <li>Confirm app configuration</li>
                                    <li>Use the setup wizard and health checks</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Recommended environment</h3>
                                <p>Use a production-safe PHP stack with a stable database and background worker processes to support long-running tasks and live communication features.</p>
                                <ul>
                                    <li>Laravel application runtime</li>
                                    <li>MySQL database services</li>
                                    <li>Queue worker and Reverb runtime</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="settings">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-sliders-h"></i> Settings</div>
                            <h2>Administration and platform settings</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>Admin control center</h3>
                                <p>Manage core system parameters, language, communication, access rules, and platform behavior in the administration panel.</p>
                                <ul>
                                    <li>Branding and configuration</li>
                                    <li>School-level settings</li>
                                    <li>Notification and security controls</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Best practices</h3>
                                <p>Keep system settings consistent, review health checks, and validate server readiness before major feature or data updates.</p>
                                <ul>
                                    <li>Use maintenance mode for deployments</li>
                                    <li>Review background service health</li>
                                    <li>Back up before system changes</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="students">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-user-graduate"></i> Students</div>
                            <h2>Student lifecycle and records</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>Admissions and profiles</h3>
                                <p>Capture student records, admission forms, academic mapping, classroom assignments, and reporting data in one integrated workflow.</p>
                                <ul>
                                    <li>Admission forms</li>
                                    <li>Academic profile records</li>
                                    <li>Class assignments</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Operational support</h3>
                                <p>Track attendance, performance, promotion, and associated communication events in a consistent, role-aware environment.</p>
                                <ul>
                                    <li>Attendance management</li>
                                    <li>Academic progression</li>
                                    <li>Performance reporting</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="fees">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-wallet"></i> Fees</div>
                            <h2>Payments and financial operations</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>Fee structure management</h3>
                                <p>Configure fee types, receipts, tracking, student payment records, and financial workflows without leaving the admin environment.</p>
                                <ul>
                                    <li>Payment configuration</li>
                                    <li>Fee categories and billing</li>
                                    <li>Financial reports</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Reporting</h3>
                                <p>Use dashboards, filters, and export-based reporting to keep the school’s financial and academic data transparent.</p>
                                <ul>
                                    <li>Fee summaries</li>
                                    <li>School revenue reports</li>
                                    <li>Operational insights</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="queue">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-layer-group"></i> Queue</div>
                            <h2>Background processing</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>Queue worker</h3>
                                <p>Use the database-backed queue worker to process emails, background operations, and scheduled system tasks without blocking the web UI.</p>
                                <ul>
                                    <li>Windows XAMPP local support</li>
                                    <li>Linux production support</li>
                                    <li>Reliable retry behavior</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Operational guidance</h3>
                                <p>Run the worker in a persistent terminal session and monitor the queue health to keep tasks flowing reliably.</p>
                                <ul>
                                    <li>Database queue driver</li>
                                    <li>Queue job verification</li>
                                    <li>Failed job visibility</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="reverb">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-wifi"></i> Reverb</div>
                            <h2>Realtime communications</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>WebSocket server</h3>
                                <p>Laravel Reverb powers live communication features, event broadcasting, and dashboard notifications for a responsive user experience.</p>
                                <ul>
                                    <li>Host and port validation</li>
                                    <li>App key and secret verification</li>
                                    <li>Client and server alignment</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Expected runtime</h3>
                                <p>Ensure the Reverb service is running on the configured host and port so real-time application features remain operational across the platform.</p>
                                <ul>
                                    <li>Local Windows development setup</li>
                                    <li>Production service stability</li>
                                    <li>Secure frontend configuration</li>
                                </ul>
                            </article>
                        </div>
                    </section>

                    <section class="vidya-docs-feature" id="update">
                        <div class="feature-head">
                            <div class="feature-label"><i class="fa fa-cloud-download-alt"></i> Update</div>
                            <h2>System upgrade workflow</h2>
                        </div>
                        <div class="feature-body">
                            <article class="feature-card">
                                <h3>Deployment steps</h3>
                                <p>Use maintenance mode before installation, upload the system update package, and monitor progress through the admin deployment dashboard.</p>
                                <ul>
                                    <li>Enable maintenance mode</li>
                                    <li>Upload a valid update ZIP</li>
                                    <li>Track deployment progress</li>
                                </ul>
                            </article>
                            <article class="feature-card">
                                <h3>Operational safety</h3>
                                <p>Keep the platform stable by validating environment health, monitoring queue processing, and reviewing update logs before re-enabling access.</p>
                                <ul>
                                    <li>Review logs</li>
                                    <li>Check maintenance state</li>
                                    <li>Confirm post-update health</li>
                                </ul>
                            </article>
                        </div>
                    </section>
                </main>
            </div>

            <footer class="vidya-docs-footer">
                <div><strong>VidyaSetu</strong> — Education SaaS documentation</div>
                <div>Admin panel • Updates • Realtime services • School operations</div>
            </footer>
        </div>
    </div>
@endsection
