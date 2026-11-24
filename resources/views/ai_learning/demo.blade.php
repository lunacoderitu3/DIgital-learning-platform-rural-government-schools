@extends('layouts.master')

@section('title')
    VidyaSetu | AI Learning
@endsection

@section('css')
<style>
    :root {
        --vs-primary: #22577A;
        --vs-primary-soft: #eaf5fb;
        --vs-primary-strong: #174a68;
        --vs-green: #22a06b;
        --vs-green-soft: #eafaf3;
        --vs-orange: #f59e0b;
        --vs-orange-soft: #fff7e8;
        --vs-surface: #f7fafc;
        --vs-card: #ffffff;
        --vs-border: #dfeaf3;
        --vs-text: #1f2d3d;
        --vs-muted: #5c6d7e;
        --vs-shadow: 0 12px 28px rgba(34, 87, 122, 0.08);
    }

    .ai-demo-page {
        padding: 24px 18px 40px;
        background: linear-gradient(180deg, #f6fbff 0%, #edf5fb 100%);
        color: var(--vs-text);
    }

    .ai-demo-shell {
        max-width: 1320px;
        margin: 0 auto;
    }

    .ai-demo-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 22px;
        flex-wrap: wrap;
    }

    .ai-demo-heading {
        margin: 0;
        font-size: clamp(1.8rem, 2.8vw, 2.6rem);
        font-weight: 800;
        color: var(--vs-primary-strong);
    }

    .ai-demo-subheading {
        margin: 6px 0 0;
        color: var(--vs-muted);
        font-size: 0.97rem;
    }

    .demo-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--vs-green-soft);
        color: var(--vs-green);
        border: 1px solid rgba(34,160,107,0.2);
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .demo-module-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 22px;
    }

    .ai-demo-card {
        background: var(--vs-card);
        border: 1px solid var(--vs-border);
        border-radius: 22px;
        box-shadow: var(--vs-shadow);
    }

    .ai-demo-sidebar {
        grid-column: span 4;
    }

    .ai-demo-main {
        grid-column: span 8;
    }

    .module-nav {
        padding: 18px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .module-nav-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        width: 100%;
        padding: 14px 14px;
        border-radius: 14px;
        border: 1px solid var(--vs-border);
        background: #fbfdff;
        text-decoration: none;
        color: var(--vs-text);
        transition: all 0.2s ease;
    }

    .module-nav-item:hover {
        text-decoration: none;
        border-color: rgba(34, 87, 122, 0.35);
        transform: translateY(-1px);
    }

    .module-nav-item.active {
        background: var(--vs-primary-soft);
        border-color: rgba(34, 87, 122, 0.2);
    }

    .module-nav-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(34, 87, 122, 0.08);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--vs-primary);
        font-size: 1.1rem;
    }

    .module-nav-title {
        flex: 1;
        font-weight: 700;
    }

    .module-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1ab36d, #3bc386);
        color: #fff;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .module-panel {
        padding: 22px;
    }

    .module-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .module-header h3 {
        margin: 0;
        font-size: 1.28rem;
        font-weight: 800;
        color: var(--vs-primary-strong);
    }

    .mini-stat {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 12px;
        background: var(--vs-primary-soft);
        color: var(--vs-primary);
        font-weight: 700;
        font-size: 0.82rem;
    }

    .chat-shell {
        display: flex;
        flex-direction: column;
        gap: 16px;
        min-height: 520px;
    }

    .chat-history {
        border: 1px solid var(--vs-border);
        border-radius: 18px;
        background: #f9fbfe;
        padding: 16px;
        min-height: 360px;
        max-height: 420px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .message {
        display: flex;
        max-width: 80%;
        gap: 10px;
        align-items: flex-start;
    }

    .message.user {
        margin-left: auto;
        justify-content: flex-end;
    }

    .message-bubble {
        padding: 12px 14px;
        border-radius: 14px;
        line-height: 1.5;
        font-size: 0.96rem;
    }

    .message.user .message-bubble {
        background: var(--vs-primary);
        color: white;
        border-bottom-right-radius: 6px;
    }

    .message.bot .message-bubble {
        background: #ffffff;
        border: 1px solid var(--vs-border);
        color: var(--vs-text);
        border-bottom-left-radius: 6px;
    }

    .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .avatar.bot {
        background: #ddf5ea;
        color: var(--vs-green);
    }

    .avatar.user {
        background: rgba(34, 87, 122, 0.1);
        color: var(--vs-primary);
    }

    .typing {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid var(--vs-border);
    }

    .typing span {
        width: 6px;
        height: 6px;
        background: var(--vs-primary);
        border-radius: 50%;
        display: inline-block;
        animation: bounce 1.2s infinite ease-in-out;
    }

    .typing span:nth-child(2) { animation-delay: 0.15s; }
    .typing span:nth-child(3) { animation-delay: 0.3s; }

    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0.8); opacity: 0.4; }
        40% { transform: scale(1.1); opacity: 1; }
    }

    .chat-input-row {
        display: flex;
        gap: 10px;
        align-items: stretch;
    }

    .chat-input-row input {
        flex: 1;
        border: 1px solid var(--vs-border);
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 0.96rem;
    }

    .chat-input-row button,
    .action-button,
    .quiz-submit,
    .quiz-retry,
    .download-button {
        border: none;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .chat-input-row button,
    .action-button,
    .quiz-submit,
    .download-button {
        background: var(--vs-primary);
        color: white;
        padding: 12px 18px;
    }

    .chat-input-row button:hover,
    .action-button:hover,
    .quiz-submit:hover,
    .download-button:hover {
        background: var(--vs-primary-strong);
    }

    .quiz-retry {
        background: var(--vs-orange-soft);
        color: #9a6700;
        padding: 10px 16px;
    }

    .suggestion-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 8px;
    }

    .suggestion-chip {
        border: 1px solid rgba(34, 87, 122, 0.15);
        background: #fff;
        color: var(--vs-primary-strong);
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .suggestion-chip:hover {
        background: var(--vs-primary-soft);
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid var(--vs-border);
        border-radius: 18px;
        padding: 18px;
        box-shadow: var(--vs-shadow);
    }

    .stat-label {
        color: var(--vs-muted);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 8px;
        font-weight: 700;
    }

    .stat-value {
        font-size: clamp(1.3rem, 3vw, 2rem);
        font-weight: 800;
        color: var(--vs-primary-strong);
        margin: 0;
    }

    .progress-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-top: 18px;
    }

    .subject-list,
    .recommendation-list,
    .topic-list,
    .lesson-list,
    .resource-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .subject-item,
    .recommendation-item,
    .topic-item,
    .lesson-item,
    .resource-item {
        background: #fff;
        border: 1px solid var(--vs-border);
        border-radius: 14px;
        padding: 14px 16px;
    }

    .subject-header,
    .recommendation-header,
    .topic-header,
    .lesson-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .subject-name,
    .topic-name,
    .lesson-name {
        font-weight: 700;
        color: var(--vs-text);
    }

    .subject-score {
        font-weight: 800;
        color: var(--vs-primary-strong);
    }

    .bar-track {
        height: 10px;
        background: #edf3f8;
        border-radius: 999px;
        overflow: hidden;
    }

    .bar-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--vs-primary), #6db0d5);
    }

    .tag {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 9px;
        font-size: 0.74rem;
        font-weight: 700;
    }

    .tag.good { background: #eafaf3; color: #188f5d; }
    .tag.avg { background: #fff6dd; color: #bb7d09; }
    .tag.need { background: #fff1ed; color: #c55b2f; }

    .quiz-layout {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(140px, 1fr);
        gap: 18px;
    }

    .quiz-panel {
        padding: 18px;
        border: 1px solid var(--vs-border);
        border-radius: 18px;
        background: #fff;
    }

    .question-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .question-number {
        font-weight: 800;
        color: var(--vs-primary);
    }

    .question-text {
        font-size: 1.08rem;
        font-weight: 700;
        margin-bottom: 18px;
        line-height: 1.6;
    }

    .option-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .option-item {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid var(--vs-border);
        background: #f9fbfd;
        border-radius: 12px;
        padding: 12px 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .option-item:hover {
        border-color: rgba(34, 87, 122, 0.4);
    }

    .option-item input { margin: 0; }

    .option-label {
        font-weight: 600;
        color: var(--vs-text);
    }

    .quiz-feedback {
        margin-top: 18px;
        padding: 12px 14px;
        border-radius: 12px;
        font-weight: 600;
        display: none;
    }

    .quiz-feedback.success { display: block; background: #eafaf3; color: #166c4d; }
    .quiz-feedback.error { display: block; background: #fff1ee; color: #b55635; }

    .quiz-score-box {
        padding: 18px;
        border: 1px solid var(--vs-border);
        background: linear-gradient(180deg, #f5fbff 0%, #fff 100%);
        border-radius: 18px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 12px;
    }

    .quiz-score-value {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--vs-primary-strong);
    }

    .language-selector {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }

    .language-selector select {
        border: 1px solid var(--vs-border);
        border-radius: 10px;
        padding: 10px 12px;
        background: #fff;
    }

    .lesson-box {
        background: #fff;
        border: 1px solid var(--vs-border);
        border-radius: 18px;
        padding: 18px;
        margin-bottom: 18px;
    }

    .lesson-box h4 {
        margin: 0 0 12px;
        color: var(--vs-primary-strong);
    }

    .lesson-box p {
        color: var(--vs-text);
        margin-bottom: 0;
        line-height: 1.7;
    }

    .teacher-metric {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin: 10px 0;
        color: var(--vs-text);
    }

    .teacher-chart {
        background: #fff;
        border-radius: 18px;
        border: 1px solid var(--vs-border);
        padding: 18px;
    }

    .chart-bars {
        display: flex;
        align-items: end;
        gap: 12px;
        height: 190px;
        margin-top: 18px;
    }

    .chart-bar-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: end;
        gap: 8px;
        color: var(--vs-muted);
        font-size: 0.72rem;
    }

    .chart-bar {
        width: 100%;
        max-width: 48px;
        border-radius: 12px 12px 0 0;
        background: linear-gradient(180deg, #5aa5cf 0%, var(--vs-primary) 100%);
    }

    .resource-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        background: #fff;
        border: 1px solid var(--vs-border);
        border-radius: 14px;
        padding: 14px 16px;
    }

    .offline-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.76rem;
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eafaf3;
        color: #1b8d5f;
    }

    @media (max-width: 991px) {
        .ai-demo-sidebar,
        .ai-demo-main {
            grid-column: span 12;
        }
    }

    @media (max-width: 640px) {
        .stat-grid,
        .progress-row,
        .quiz-layout {
            grid-template-columns: 1fr;
        }

        .chat-input-row {
            flex-direction: column;
        }
    }
</style>
@endsection

@section('content')
<div class="ai-demo-page">
    <div class="ai-demo-shell">
        <div class="ai-demo-topbar">
            <div>
                <h1 class="ai-demo-heading">AI Powered Smart Learning</h1>
                <p class="ai-demo-subheading">Demo prototype for VidyaSetu classrooms • Realistic sample educational data only</p>
            </div>
            <span class="demo-pill"><i class="fa fa-robot"></i> DEMO MODE</span>
        </div>

        <div class="demo-module-grid">
            <aside class="ai-demo-card ai-demo-sidebar">
                <nav class="module-nav">
                    <a class="module-nav-item {{ $module === 'assistant' ? 'active' : '' }}" href="{{ route('ai-learning.assistant') }}">
                        <span class="module-nav-icon"><i class="fa fa-robot"></i></span>
                        <span class="module-nav-title">AI Learning Assistant</span>
                        <span class="module-badge">NEW</span>
                    </a>
                    <a class="module-nav-item {{ $module === 'quiz' ? 'active' : '' }}" href="{{ route('ai-learning.quiz') }}">
                        <span class="module-nav-icon"><i class="fa fa-question-circle"></i></span>
                        <span class="module-nav-title">Smart Quiz Generator</span>
                        <span class="module-badge">NEW</span>
                    </a>
                    <a class="module-nav-item {{ $module === 'personalized' ? 'active' : '' }}" href="{{ route('ai-learning.personalized') }}">
                        <span class="module-nav-icon"><i class="fa fa-user-graduate"></i></span>
                        <span class="module-nav-title">Personalized Learning</span>
                        <span class="module-badge">NEW</span>
                    </a>
                    <a class="module-nav-item {{ $module === 'multilingual' ? 'active' : '' }}" href="{{ route('ai-learning.multilingual') }}">
                        <span class="module-nav-icon"><i class="fa fa-language"></i></span>
                        <span class="module-nav-title">Multilingual Learning</span>
                        <span class="module-badge">NEW</span>
                    </a>
                    <a class="module-nav-item {{ $module === 'analytics' ? 'active' : '' }}" href="{{ route('ai-learning.analytics') }}">
                        <span class="module-nav-icon"><i class="fa fa-chart-line"></i></span>
                        <span class="module-nav-title">Learning Analytics</span>
                        <span class="module-badge">NEW</span>
                    </a>
                    <a class="module-nav-item {{ $module === 'offline' ? 'active' : '' }}" href="{{ route('ai-learning.offline') }}">
                        <span class="module-nav-icon"><i class="fa fa-download"></i></span>
                        <span class="module-nav-title">Offline Learning Support</span>
                        <span class="module-badge">NEW</span>
                    </a>
                </nav>
            </aside>

            <main class="ai-demo-card ai-demo-main">
                <div class="module-panel">
                    <div class="module-header">
                        <h3>{{ $pageTitle }}</h3>
                        <span class="mini-stat"><i class="fa fa-circle-dot"></i> {{ $subtitle }}</span>
                    </div>

                    @if ($module === 'assistant')
                        <div class="chat-shell">
                            <div class="chat-history" id="chatHistory">
                                <div class="message bot">
                                    <span class="avatar bot">AI</span>
                                    <div class="message-bubble">Hi! I am your learning assistant. Ask me any school question in English or Hindi.</div>
                                </div>
                            </div>

                            <div class="suggestion-row">
                                <button type="button" class="suggestion-chip" data-question="Explain photosynthesis.">Explain photosynthesis.</button>
                                <button type="button" class="suggestion-chip" data-question="What is the water cycle?">What is the water cycle?</button>
                                <button type="button" class="suggestion-chip" data-question="Explain fractions with examples.">Explain fractions with examples.</button>
                                <button type="button" class="suggestion-chip" data-question="What is the solar system?">What is the solar system?</button>
                            </div>

                            <div class="chat-input-row">
                                <input id="chatInput" type="text" placeholder="Ask a learning question..." aria-label="Ask a learning question">
                                <button id="sendChatBtn" type="button">Send</button>
                                <button id="clearChatBtn" type="button" class="action-button" style="background:#f1f5f9; color:var(--vs-primary);">Clear</button>
                            </div>
                        </div>
                    @elseif ($module === 'quiz')
                        <div class="quiz-layout">
                            <div class="quiz-panel" id="quizPanel">
                                <div class="question-meta">
                                    <span class="question-number" id="questionNumber">Question 1</span>
                                    <span class="mini-stat" id="difficultyBadge">Medium</span>
                                </div>
                                <div class="question-text" id="questionText"></div>
                                <div class="option-list" id="answerOptions"></div>
                                <div class="quiz-feedback" id="quizFeedback"></div>
                                <div class="chat-input-row" style="margin-top:18px;">
                                    <button class="quiz-submit" id="nextQuestionBtn" type="button">Submit Answer</button>
                                    <button class="quiz-retry" id="retryQuizBtn" type="button">Retry Quiz</button>
                                </div>
                            </div>
                            <div class="quiz-score-box">
                                <div>
                                    <div class="stat-label">Current score</div>
                                    <div class="quiz-score-value" id="scoreValue">0/0</div>
                                </div>
                                <div>
                                    <div class="stat-label">Quiz status</div>
                                    <div class="tag avg" id="quizStatus">Ready</div>
                                </div>
                            </div>
                        </div>
                    @elseif ($module === 'personalized')
                        <div class="stat-grid">
                            <div class="stat-card">
                                <div class="stat-label">Mathematics</div>
                                <p class="stat-value">45%</p>
                                <span class="tag need">Needs Improvement</span>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Science</div>
                                <p class="stat-value">85%</p>
                                <span class="tag good">Good Performance</span>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">English</div>
                                <p class="stat-value">72%</p>
                                <span class="tag avg">Average Performance</span>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Overall</div>
                                <p class="stat-value">68%</p>
                                <span class="tag avg">Steady Progress</span>
                            </div>
                        </div>

                        <div class="progress-row">
                            <div class="subject-list">
                                <div class="subject-item">
                                    <div class="subject-header"><span class="subject-name">Mathematics</span><span class="subject-score">45%</span></div>
                                    <div class="bar-track"><div class="bar-fill" style="width:45%"></div></div>
                                </div>
                                <div class="subject-item">
                                    <div class="subject-header"><span class="subject-name">Science</span><span class="subject-score">85%</span></div>
                                    <div class="bar-track"><div class="bar-fill" style="width:85%"></div></div>
                                </div>
                                <div class="subject-item">
                                    <div class="subject-header"><span class="subject-name">English</span><span class="subject-score">72%</span></div>
                                    <div class="bar-track"><div class="bar-fill" style="width:72%"></div></div>
                                </div>
                            </div>

                            <div class="recommendation-list">
                                <div class="recommendation-item">
                                    <div class="recommendation-header"><span class="subject-name">Recommendation</span><span class="tag need">Priority</span></div>
                                    <p style="margin:0; line-height:1.6; color:var(--vs-text);">Practice algebra and fractions for better results. Use short revision tests and flashcards.</p>
                                </div>
                                <div class="recommendation-item">
                                    <div class="recommendation-header"><span class="subject-name">Improvement</span><span class="tag good">Goal</span></div>
                                    <p style="margin:0; line-height:1.6; color:var(--vs-text);">Aim to reach 75% in mathematics by solving 3 practice sets per week.</p>
                                </div>
                            </div>
                        </div>
                    @elseif ($module === 'multilingual')
                        <div class="language-selector">
                            <label for="lessonLanguage" style="font-weight:700; color:var(--vs-primary-strong);">Language:</label>
                            <select id="lessonLanguage">
                                <option value="en" selected>English</option>
                                <option value="hi">Hindi</option>
                                <option value="kn">Kannada</option>
                            </select>
                        </div>

                        <div class="lesson-box">
                            <h4 id="lessonTitle">Lesson: The Water Cycle</h4>
                            <p id="lessonText">Water from the earth rises into the air, forms clouds, and comes back as rain. This natural process is called the water cycle. It helps keep water moving across the land, oceans and sky.</p>
                        </div>

                        <div class="lesson-box">
                            <h4>Ask a question</h4>
                            <div class="chat-input-row">
                                <input id="langQuestionInput" type="text" placeholder="What do you want to understand?" value="What is the solar system?">
                                <button id="askLanguageBtn" type="button">Explain</button>
                            </div>
                            <div id="languageAnswer" style="margin-top:16px; color:var(--vs-text); line-height:1.7; font-weight:600;">The solar system is made up of the Sun and the planets that move around it.</div>
                        </div>
                    @elseif ($module === 'analytics')
                        <div class="stat-grid">
                            <div class="stat-card">
                                <div class="stat-label">Total students</div>
                                <p class="stat-value">1,284</p>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Average quiz score</div>
                                <p class="stat-value">76%</p>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Support needed</div>
                                <p class="stat-value">143</p>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Completion rate</div>
                                <p class="stat-value">84%</p>
                            </div>
                        </div>

                        <div class="teacher-metric">
                            <strong>Subject-wise performance</strong>
                            <span class="mini-stat">Class 6-8</span>
                        </div>
                        <div class="teacher-chart">
                            <div class="chart-bars">
                                <div class="chart-bar-col"><div class="chart-bar" style="height:58%"></div><span>Math</span></div>
                                <div class="chart-bar-col"><div class="chart-bar" style="height:74%"></div><span>Science</span></div>
                                <div class="chart-bar-col"><div class="chart-bar" style="height:81%"></div><span>English</span></div>
                                <div class="chart-bar-col"><div class="chart-bar" style="height:68%"></div><span>EVS</span></div>
                                <div class="chart-bar-col"><div class="chart-bar" style="height:61%"></div><span>Social</span></div>
                            </div>
                        </div>

                        <div class="progress-row" style="margin-top:20px;">
                            <div class="topic-list">
                                <div class="topic-item">
                                    <div class="topic-header"><span class="topic-name">Weak topics</span><span class="tag need">Needs support</span></div>
                                    <p style="margin:0; line-height:1.6; color:var(--vs-text);">Fractions, basic algebra, and reading comprehension.</p>
                                </div>
                            </div>
                            <div class="topic-list">
                                <div class="topic-item">
                                    <div class="topic-header"><span class="topic-name">Students requiring attention</span><span class="tag avg">143</span></div>
                                    <p style="margin:0; line-height:1.6; color:var(--vs-text);">Students in need of revision support and guided practice.</p>
                                </div>
                            </div>
                        </div>
                    @elseif ($module === 'offline')
                        <div class="resource-list">
                            <div class="resource-item">
                                <div>
                                    <div class="lesson-name">Photosynthesis Lesson.pdf</div>
                                    <div style="color:var(--vs-muted); font-size:0.8rem; margin-top:4px;">Size: 2.4 MB • Downloaded on 12 Nov 2026</div>
                                </div>
                                <div class="offline-status"><i class="fa fa-check-circle"></i> Ready</div>
                            </div>
                            <div class="resource-item">
                                <div>
                                    <div class="lesson-name">Water Cycle Activity Sheet</div>
                                    <div style="color:var(--vs-muted); font-size:0.8rem; margin-top:4px;">Saved offline resource • Available in classroom tablet</div>
                                </div>
                                <button type="button" class="download-button">Download</button>
                            </div>
                            <div class="resource-item">
                                <div>
                                    <div class="lesson-name">Math Practice Set</div>
                                    <div style="color:var(--vs-muted); font-size:0.8rem; margin-top:4px;">Last sync: 2 hours ago</div>
                                </div>
                                <div class="offline-status"><i class="fa fa-cloud-download"></i> Offline</div>
                            </div>
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    const demoAnswers = {
        'Explain photosynthesis.': 'Photosynthesis is the process by which green plants make their own food using sunlight, water, and carbon dioxide. This process helps plants grow and releases oxygen into the air.',
        'What is the water cycle?': 'The water cycle is the continuous movement of water from the earth to the sky and back again. It includes evaporation, condensation, and precipitation.',
        'Explain fractions with examples.': 'A fraction shows a part of a whole. For example, 1/2 means one part out of two equal parts, and 3/4 means three parts out of four equal parts.',
        'What is the solar system?': 'The solar system is made up of the Sun and all the objects that move around it, including planets, moons, asteroids, and comets.'
    };

    const quizData = [
        {
            question: 'Which part of the plant absorbs water from the soil?',
            options: ['Leaves', 'Roots', 'Stem', 'Flower'],
            answer: 'Roots',
            difficulty: 'Easy'
        },
        {
            question: 'What is 3/4 + 1/4?',
            options: ['1/2', '1', '3/8', '4/4'],
            answer: '1',
            difficulty: 'Medium'
        },
        {
            question: 'Which planet is known as the Red Planet?',
            options: ['Earth', 'Mars', 'Venus', 'Jupiter'],
            answer: 'Mars',
            difficulty: 'Medium'
        },
        {
            question: 'Which gas do plants take from the air during photosynthesis?',
            options: ['Oxygen', 'Carbon dioxide', 'Nitrogen', 'Hydrogen'],
            answer: 'Carbon dioxide',
            difficulty: 'Hard'
        }
    ];

    const multilingualContent = {
        en: {
            title: 'Lesson: The Water Cycle',
            text: 'Water from the earth rises into the air, forms clouds, and comes back as rain. This natural process is called the water cycle. It helps keep water moving across the land, oceans and sky.',
            answer: 'The solar system is made up of the Sun and the planets that move around it.'
        },
        hi: {
            title: 'पाठ: जल चक्र',
            text: 'पृथ्वी का पानी हवा में उठता है, बादल बनता है, और फिर वर्षा के रूप में वापस आता है। इस प्राकृतिक प्रक्रिया को जल चक्र कहते हैं। यह भूमि, महासागर और आकाश के बीच पानी के चलने में मदद करता है।',
            answer: 'सौरमंडल में सूर्य और उसके चारों ओर घूमने वाले ग्रह, उपग्रह, क्षुद्रग्रह और धूमकेतु शामिल होते हैं।'
        },
        kn: {
            title: 'ಪಾಠ: ಜಲ ಚಕ್ರ',
            text: 'ಭೂಮಿಯಿಂದ ನೀರು ಗಾಳಿಗೆ ಏರುತ್ತದೆ, ಮಬ್ಬು ರೂಪದಲ್ಲಿ ಒಮ್ಮುಖವಾಗುತ್ತದೆ ಮತ್ತು ಮತ್ತೆ ಮಳೆಯಾಗಿ ಬೀಳುತ್ತದೆ. ಈ ಸ್ವಾಭಾವಿಕ ಪ್ರಕ್ರಿಯೆಯನ್ನು ಜಲಚಕ್ರ ಎಂದು ಹೇಳುತ್ತಾರೆ. ಇದು ಭೂಮಿ, ಸಮುದ್ರ ಮತ್ತು ಆಕಾಶದ ನಡುವೆ ನೀರಿನ ಚಲನವಲನವನ್ನು ಸಹಾಯ ಮಾಡುತ್ತದೆ.',
            answer: 'ಸೌರವ್ಯೂಹವು ಸೂರ್ಯ ಮತ್ತು ಅದರ ಸುತ್ತ ಸುತ್ತುವ ಗ್ರಹಗಳು, ಚಂದ್ರರು, ಕ್ಷುದ್ರಗ್ರಹಗಳು ಮತ್ತು ಧೂಮಕೇತುಗಳಿಂದ ಕೂಡಿದೆ.'
        }
    };

    @if ($module === 'assistant')
    const chatHistory = document.getElementById('chatHistory');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendChatBtn');

    function addChatMessage(role, text) {
        const wrapper = document.createElement('div');
        wrapper.className = `message ${role}`;
        const avatar = document.createElement('span');
        avatar.className = `avatar ${role}`;
        avatar.textContent = role === 'bot' ? 'AI' : 'You';
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = text;
        wrapper.appendChild(avatar);
        wrapper.appendChild(bubble);
        chatHistory.appendChild(wrapper);
        chatHistory.scrollTop = chatHistory.scrollHeight;
    }

    function getBotReply(question) {
        const normalized = question.trim();
        if (demoAnswers[normalized]) {
            return demoAnswers[normalized];
        }

        if (/photosynthesis|प्रकाश संश्लेषण|ಪರಿಸರ|ಸೂರ್ಯ/i.test(normalized)) {
            return 'Photosynthesis is the process where green plants make food using sunlight, water, and carbon dioxide.';
        }

        if (/fraction|fractions|भिन्न|ಭಿನ್ನರಾಶಿ/i.test(normalized)) {
            return 'A fraction shows a part of a whole. For example, 1/2 means one out of two equal parts.';
        }

        return 'Demo response: Learning is most effective when you break the idea into simple steps. Try asking about a science topic, math concept, or a basic question from your textbook.';
    }

    function simulateBotTyping(question) {
        const typingWrap = document.createElement('div');
        typingWrap.className = 'message bot';
        const avatar = document.createElement('span');
        avatar.className = 'avatar bot';
        avatar.textContent = 'AI';
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        const typing = document.createElement('div');
        typing.className = 'typing';
        typing.innerHTML = '<span></span><span></span><span></span>';
        bubble.appendChild(typing);
        typingWrap.appendChild(avatar);
        typingWrap.appendChild(bubble);
        chatHistory.appendChild(typingWrap);
        chatHistory.scrollTop = chatHistory.scrollHeight;

        setTimeout(() => {
            typingWrap.remove();
            addChatMessage('bot', getBotReply(question));
        }, 900);
    }

    sendBtn.addEventListener('click', function() {
        const question = chatInput.value.trim();
        if (!question) return;
        addChatMessage('user', question);
        chatInput.value = '';
        simulateBotTyping(question);
    });

    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            sendBtn.click();
        }
    });

    document.querySelectorAll('.suggestion-chip').forEach((chip) => {
        chip.addEventListener('click', function() {
            const question = this.dataset.question;
            chatInput.value = question;
            sendBtn.click();
        });
    });

    document.getElementById('clearChatBtn').addEventListener('click', function() {
        chatHistory.innerHTML = `
            <div class="message bot">
                <span class="avatar bot">AI</span>
                <div class="message-bubble">Hi! I am your learning assistant. Ask me any school question in English or Hindi.</div>
            </div>
        `;
    });
    @endif

    @if ($module === 'quiz')
    let quizIndex = 0;
    let score = 0;
    let answered = false;

    const questionNumber = document.getElementById('questionNumber');
    const questionText = document.getElementById('questionText');
    const answerOptions = document.getElementById('answerOptions');
    const quizFeedback = document.getElementById('quizFeedback');
    const nextQuestionBtn = document.getElementById('nextQuestionBtn');
    const retryQuizBtn = document.getElementById('retryQuizBtn');
    const scoreValue = document.getElementById('scoreValue');
    const difficultyBadge = document.getElementById('difficultyBadge');
    const quizStatus = document.getElementById('quizStatus');

    function renderQuestion() {
        const q = quizData[quizIndex];
        answerOptions.innerHTML = '';
        questionNumber.textContent = `Question ${quizIndex + 1}`;
        questionText.textContent = q.question;
        difficultyBadge.textContent = q.difficulty;
        quizFeedback.className = 'quiz-feedback';
        answered = false;

        q.options.forEach((option) => {
            const item = document.createElement('label');
            item.className = 'option-item';
            item.innerHTML = `
                <input type="radio" name="answer" value="${option}">
                <span class="option-label">${option}</span>
            `;
            answerOptions.appendChild(item);
        });

        nextQuestionBtn.textContent = quizIndex === quizData.length - 1 ? 'Finish Quiz' : 'Submit Answer';
        scoreValue.textContent = `${score}/${quizData.length}`;
    }

    function submitAnswer() {
        if (answered) return;

        const selected = document.querySelector('input[name="answer"]:checked');
        if (!selected) {
            quizFeedback.textContent = 'Please select an answer before submitting.';
            quizFeedback.className = 'quiz-feedback error';
            return;
        }

        answered = true;
        const isCorrect = selected.value === quizData[quizIndex].answer;
        if (isCorrect) {
            score += 1;
            quizFeedback.textContent = 'Correct! Great job.';
            quizFeedback.className = 'quiz-feedback success';
            quizStatus.textContent = 'Correct';
            quizStatus.className = 'tag good';
        } else {
            quizFeedback.textContent = `Incorrect. The correct answer is: ${quizData[quizIndex].answer}`;
            quizFeedback.className = 'quiz-feedback error';
            quizStatus.textContent = 'Try again';
            quizStatus.className = 'tag need';
        }

        scoreValue.textContent = `${score}/${quizData.length}`;
    }

    nextQuestionBtn.addEventListener('click', function() {
        if (!answered) {
            submitAnswer();
            return;
        }

        if (quizIndex < quizData.length - 1) {
            quizIndex += 1;
            renderQuestion();
        } else {
            const percentage = Math.round((score / quizData.length) * 100);
            const resultText = percentage >= 75 ? 'Excellent work! You have strong understanding.' : percentage >= 50 ? 'Good effort. Review a few topics and try again.' : 'Keep practicing. You are improving.';
            quizFeedback.textContent = `${resultText} Final score: ${percentage}%`;
            quizFeedback.className = 'quiz-feedback success';
            quizStatus.textContent = `${percentage}%`;
            quizStatus.className = 'tag avg';
            questionText.textContent = 'Quiz complete!';
            answerOptions.innerHTML = '<div class="option-item"><span class="option-label">You can retry to improve your score.</span></div>';
            nextQuestionBtn.textContent = 'Completed';
        }
    });

    retryQuizBtn.addEventListener('click', function() {
        quizIndex = 0;
        score = 0;
        answered = false;
        quizStatus.textContent = 'Ready';
        quizStatus.className = 'tag avg';
        nextQuestionBtn.textContent = 'Submit Answer';
        renderQuestion();
    });

    renderQuestion();
    @endif

    @if ($module === 'multilingual')
    const lessonLanguage = document.getElementById('lessonLanguage');
    const lessonTitle = document.getElementById('lessonTitle');
    const lessonText = document.getElementById('lessonText');
    const langQuestionInput = document.getElementById('langQuestionInput');
    const languageAnswer = document.getElementById('languageAnswer');

    lessonLanguage.addEventListener('change', function() {
        const value = this.value;
        const content = multilingualContent[value];
        lessonTitle.textContent = content.title;
        lessonText.textContent = content.text;
        languageAnswer.textContent = content.answer;
    });

    document.getElementById('askLanguageBtn').addEventListener('click', function() {
        const question = langQuestionInput.value.trim();
        if (!question) return;
        const current = multilingualContent[lessonLanguage.value];
        languageAnswer.textContent = current.answer;
    });
    @endif
</script>
@endsection
