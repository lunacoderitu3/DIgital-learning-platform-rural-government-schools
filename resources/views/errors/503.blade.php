<!DOCTYPE html>
<html lang="en" class="antialiased">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Upgrade In Progress | {{ env('APP_NAME') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', "Liberation Mono", "Courier New", 'monospace'],
                    },
                    colors: {
                        brand: {
                            indigo: '#6366F1',
                            purple: '#8B5CF6',
                            cyan: '#06B6D4',
                            green: '#22C55E',
                            dark: '#0F172A',
                            darker: '#111827',
                            darkest: '#1E1B4B',
                        }
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'glass': 'linear-gradient(to bottom right, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.01))',
                    },
                    animation: {
                        'spin-slow': 'spin 8s linear infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: #0F172A;
            color: #F8FAFC;
            overflow-x: hidden;
            /* Premium dark gradient background */
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.15), transparent 40%),
                radial-gradient(circle at bottom left, rgba(139, 92, 246, 0.15), transparent 40%),
                linear-gradient(to bottom, #0F172A, #111827, #1E1B4B);
            background-attachment: fixed;
        }

        /* Glassmorphism utility classes */
        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .glass-panel:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.3), 0 0 20px rgba(99, 102, 241, 0.1);
        }

        .glass-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Text Gradients */
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-image: linear-gradient(to right, #6366F1, #8B5CF6);
        }

        .text-gradient-cyan {
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-image: linear-gradient(to right, #06B6D4, #3B82F6);
        }

        /* Aurora Background Blobs */
        .aurora-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.4;
            animation: float-blob 20s ease-in-out infinite alternate;
        }

        @keyframes float-blob {
            0% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(30px, -50px) scale(1.1);
            }

            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }

            100% {
                transform: translate(0, 0) scale(1);
            }
        }

        /* Particles */
        .particles-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        /* Typing Animation */
        .typing-container {
            display: inline-block;
        }

        .typing-text {
            overflow: hidden;
            white-space: nowrap;
            border-right: 2px solid #8B5CF6;
            margin: 0 auto;
            letter-spacing: 0.05em;
            animation:
                typing 3.5s steps(40, end),
                blink-caret .75s step-end infinite;
        }

        @keyframes typing {
            from {
                width: 0
            }

            to {
                width: 100%
            }
        }

        @keyframes blink-caret {

            from,
            to {
                border-color: transparent
            }

            50% {
                border-color: #8B5CF6;
            }
        }

        /* Floating Animation */
        .float {
            animation: float 6s ease-in-out infinite;
        }

        .float-delayed {
            animation: float 6s ease-in-out 3s infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-15px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        /* SVG Line Drawing */
        .path-draw {
            stroke-dasharray: 1000;
            stroke-dashoffset: 1000;
            animation: draw 3s ease forwards infinite alternate;
        }

        @keyframes draw {
            to {
                stroke-dashoffset: 0;
            }
        }

        /* Node Pulse */
        .node-pulse {
            animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        /* Progress Bar Animation */
        .progress-bar-fill {
            position: relative;
            overflow: hidden;
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
            transform: translateX(-100%);
        }

        @keyframes shimmer {
            100% {
                transform: translateX(100%);
            }
        }

        /* Ensure content sits above background */
        .content-wrapper {
            position: relative;
            z-index: 10;
        }
    </style>
</head>

<body
    class="h-screen w-screen overflow-hidden flex flex-col items-center justify-center font-sans selection:bg-brand-indigo selection:bg-opacity-30">

    <!-- Background Effects -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="aurora-blob bg-brand-indigo w-96 h-96 top-[-10%] left-[-10%]"></div>
        <div class="aurora-blob bg-brand-purple w-[30rem] h-[30rem] bottom-[-20%] right-[-10%]"
            style="animation-delay: -5s;"></div>
        <div class="aurora-blob bg-brand-cyan w-80 h-80 top-[40%] left-[60%]"
            style="animation-delay: -10s; opacity: 0.2;"></div>
        <div class="aurora-blob bg-brand-darkest w-full h-full bottom-0 left-0 mix-blend-overlay"></div>
    </div>

    <!-- Particle Canvas Container -->
    <canvas id="particleCanvas" class="particles-container"></canvas>

    <main class="content-wrapper w-full max-w-5xl mx-auto px-4 sm:px-6 flex flex-col items-center justify-center">

        <!-- Logo Section -->
        <div class="mb-5 sm:mb-8 flex flex-col items-center float-delayed">
            <div class="relative group cursor-pointer mb-3 sm:mb-4">
                <!-- Glowing effect behind logo -->
                <div
                    class="absolute -inset-2 bg-gradient-to-r from-brand-indigo to-brand-purple rounded-full blur opacity-40 group-hover:opacity-75 transition duration-500">
                </div>
                <!-- Logo Circle -->
                <div
                    class="relative w-14 h-14 sm:w-16 sm:h-16 bg-brand-darker rounded-2xl flex items-center justify-center border border-gray-800 shadow-2xl glass-panel">
                    <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 14l9-5-9-5-9 5 9 5z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222">
                        </path>
                    </svg>
                </div>
            </div>

            <!-- App Name - Subtle Branding -->
            <p class="text-xs sm:text-sm font-semibold tracking-[0.2em] uppercase text-gradient opacity-60 mb-2">
                {{ env('APP_NAME', 'eSchool') }}
            </p>

            <!-- Typing Text -->
            <div class="typing-container inline-block">
                <p class="typing-text text-[11px] sm:text-xs md:text-sm font-mono text-gray-400">
                    Building Something Better For You...
                </p>
            </div>
        </div>

        <!-- Hero Content -->
        <div class="text-center max-w-2xl mb-8 sm:mb-10 relative z-20">
            <!-- Badge -->
            <div
                class="inline-flex items-center space-x-2 bg-brand-darker/80 border border-brand-indigo/30 rounded-full px-3 py-1 sm:py-1.5 mb-4 sm:mb-6 backdrop-blur-md">
                <span class="relative flex h-1.5 w-1.5 sm:h-2 sm:w-2">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-cyan opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 sm:h-2 sm:w-2 bg-brand-cyan"></span>
                </span>
                <span class="text-[10px] sm:text-xs font-medium text-brand-cyan uppercase tracking-wider">Scheduled
                    Maintenance</span>
            </div>

            <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight mb-3 sm:mb-4">
                System Upgrade <span class="text-gradient">In Progress</span>
            </h1>
            <p class="text-sm sm:text-base text-gray-400 leading-relaxed max-w-xl mx-auto hidden sm:block">
                We are enhancing your <span class="text-white font-medium">{{ env('APP_NAME') }}</span> experience with
                performance improvements, critical security updates, and exciting new features.
            </p>
        </div>

        <!-- Animated Illustration Container (Removed bottom margin for perfect centering) -->
        <div class="w-full max-w-xs sm:max-w-sm md:max-w-md mx-auto relative h-32 sm:h-48 md:h-56 float">
            <!-- Central Server/Cloud SVG -->
            <div class="absolute inset-0 flex items-center justify-center">
                <svg viewBox="0 0 400 300" class="w-full h-full drop-shadow-2xl" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <!-- Glow behind illustration -->
                    <circle cx="200" cy="150" r="100" fill="url(#paint0_radial)" opacity="0.3" />

                    <!-- Network Lines -->
                    <path class="path-draw"
                        d="M100 150 L150 150 M250 150 L300 150 M200 80 L200 110 M200 190 L200 220 M130 90 L160 120 M270 90 L240 120 M130 210 L160 180 M270 210 L240 180"
                        stroke="url(#paint1_linear)" stroke-width="2" stroke-linecap="round" />

                    <!-- Nodes -->
                    <circle cx="100" cy="150" r="4" fill="#06B6D4" class="node-pulse" style="animation-delay: 0s;" />
                    <circle cx="300" cy="150" r="4" fill="#8B5CF6" class="node-pulse" style="animation-delay: 0.5s;" />
                    <circle cx="200" cy="80" r="4" fill="#6366F1" class="node-pulse" style="animation-delay: 1s;" />
                    <circle cx="200" cy="220" r="4" fill="#22C55E" class="node-pulse" style="animation-delay: 1.5s;" />

                    <!-- Central Server Base -->
                    <rect x="160" y="110" width="80" height="80" rx="16" fill="url(#paint2_linear)" fill-opacity="0.1"
                        stroke="url(#paint3_linear)" stroke-width="2" />
                    <rect x="170" y="125" width="60" height="12" rx="4" fill="#1E293B" stroke="#334155" />
                    <rect x="170" y="145" width="60" height="12" rx="4" fill="#1E293B" stroke="#334155" />
                    <rect x="170" y="165" width="60" height="12" rx="4" fill="#1E293B" stroke="#334155" />

                    <!-- Server Lights (Blinking) -->
                    <circle cx="180" cy="131" r="2" fill="#22C55E" class="animate-pulse" />
                    <circle cx="180" cy="151" r="2" fill="#22C55E" class="animate-pulse"
                        style="animation-delay: 0.3s;" />
                    <circle cx="180" cy="171" r="2" fill="#06B6D4" class="animate-pulse"
                        style="animation-delay: 0.6s;" />

                    <!-- Orbiting Elements -->
                    <g class="animate-spin-slow" style="transform-origin: 200px 150px;">
                        <circle cx="200" cy="60" r="6" fill="url(#paint4_linear)" />
                        <circle cx="200" cy="240" r="8" fill="url(#paint4_linear)" />
                    </g>

                    <!-- Gears -->
                    <g class="animate-spin-slow" style="transform-origin: 140px 180px;">
                        <path
                            d="M140 165 C142 165 144 167 144 169 L147 170 L149 167 L152 169 L150 172 L152 175 L155 174 L155 178 L152 179 C153 181 153 183 151 185 L154 187 L152 190 L149 188 L147 191 L144 189 L142 192 L138 192 L136 189 L133 191 L131 188 L133 185 L130 183 L127 184 L126 180 L129 178 L128 175 L125 173 L128 171 L130 168 L133 170 C135 168 137 167 139 166 L138 163 Z"
                            fill="#475569" opacity="0.6" />
                        <circle cx="140" cy="180" r="4" fill="#1E293B" />
                    </g>

                    <!-- Defs for Gradients -->
                    <defs>
                        <radialGradient id="paint0_radial" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse"
                            gradientTransform="translate(200 150) rotate(90) scale(100)">
                            <stop stop-color="#6366F1" />
                            <stop offset="1" stop-color="#6366F1" stop-opacity="0" />
                        </radialGradient>
                        <linearGradient id="paint1_linear" x1="100" y1="150" x2="300" y2="150"
                            gradientUnits="userSpaceOnUse">
                            <stop stop-color="#6366F1" stop-opacity="0.2" />
                            <stop offset="0.5" stop-color="#8B5CF6" />
                            <stop offset="1" stop-color="#06B6D4" stop-opacity="0.2" />
                        </linearGradient>
                        <linearGradient id="paint2_linear" x1="160" y1="110" x2="240" y2="190"
                            gradientUnits="userSpaceOnUse">
                            <stop stop-color="#F8FAFC" />
                            <stop offset="1" stop-color="#94A3B8" />
                        </linearGradient>
                        <linearGradient id="paint3_linear" x1="160" y1="110" x2="240" y2="190"
                            gradientUnits="userSpaceOnUse">
                            <stop stop-color="#8B5CF6" />
                            <stop offset="1" stop-color="#6366F1" />
                        </linearGradient>
                        <linearGradient id="paint4_linear" x1="195" y1="55" x2="205" y2="65"
                            gradientUnits="userSpaceOnUse">
                            <stop stop-color="#06B6D4" />
                            <stop offset="1" stop-color="#3B82F6" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

    </main>

    <script>
        // Particle System Configuration
        const canvas = document.getElementById('particleCanvas');
        const ctx = canvas.getContext('2d');
        let particlesArray;

        // Resize canvas to fill window
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Create Particle class
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2 + 0.1;
                // Slow random movement
                this.speedX = (Math.random() * 0.4) - 0.2;
                this.speedY = (Math.random() * 0.4) - 0.2;
                // Opacity variation
                this.baseOpacity = Math.random() * 0.5 + 0.1;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                // Bounce off edges gently
                if (this.x > canvas.width || this.x < 0) this.speedX = -this.speedX;
                if (this.y > canvas.height || this.y < 0) this.speedY = -this.speedY;
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(139, 92, 246, ${this.baseOpacity})`; // Purple tint
                ctx.fill();
            }
        }

        // Initialize particles
        function init() {
            particlesArray = [];
            let numberOfParticles = (canvas.width * canvas.height) / 15000; // Density
            for (let i = 0; i < numberOfParticles; i++) {
                particlesArray.push(new Particle());
            }
        }

        // Animation loop for particles
        function animate() {
            requestAnimationFrame(animate);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (let i = 0; i < particlesArray.length; i++) {
                particlesArray[i].update();
                particlesArray[i].draw();
            }
        }

        // Start particle system
        init();
        animate();
    </script>
</body>

</html>