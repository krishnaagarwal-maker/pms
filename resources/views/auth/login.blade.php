<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign in · {{ config('app.name', 'ProjectMS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,700,800|figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .font-display { font-family: 'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif; }
        .font-body    { font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="font-body antialiased bg-[#F5F6F8] text-[#0E1A2B]">
<div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_1fr]">

    {{-- ───────── Left: brand panel ───────── --}}
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-[#0E1A2B] text-white px-14 py-12">

        {{-- soft glow --}}
        <div class="pointer-events-none absolute -top-32 -right-32 h-96 w-96 rounded-full bg-[#2F9E8F]/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 -left-24 h-96 w-96 rounded-full bg-[#E0A83B]/15 blur-3xl"></div>

        {{-- logo --}}
        <div class="relative flex items-center gap-3">
            </span>
           
        </div>

        {{-- headline + flow lanes --}}
        <div class="relative">
            <h1 class="font-display text-5xl xl:text-6xl font-extrabold leading-[1.02] tracking-tight max-w-md">
                Work moves.<br>You stay in control.
            </h1>
            <p class="mt-5 max-w-sm text-base leading-relaxed text-slate-300">
                Projects, tasks and deadlines in one calm workspace, so nothing slips between people.
            </p>

            {{-- the memorable element: staggered task lanes --}}
            <div class="mt-12 space-y-3 max-w-md" aria-hidden="true">
                <div class="flex items-center gap-3">
                    <span class="w-16 text-xs text-slate-400">To do</span>
                    <div class="flex-1 h-px bg-white/10"></div>
                    <span class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-slate-200">Draft Q4 roadmap</span>
                </div>
                <div class="flex items-center gap-3 pl-10">
                    <span class="w-16 text-xs text-slate-400">Doing</span>
                    <div class="flex-1 h-px bg-[#E0A83B]/40"></div>
                    <span class="flex items-center gap-2 rounded-lg border border-[#E0A83B]/40 bg-[#E0A83B]/10 px-3 py-1.5 text-xs text-[#F3D28A]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#E0A83B]"></span>Client review
                    </span>
                </div>
                <div class="flex items-center gap-3 pl-20">
                    <span class="w-16 text-xs text-slate-400">Done</span>
                    <div class="flex-1 h-px bg-[#2F9E8F]/50"></div>
                    <span class="flex items-center gap-2 rounded-lg border border-[#2F9E8F]/50 bg-[#2F9E8F]/10 px-3 py-1.5 text-xs text-[#8EDCCF]">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        Launch checklist
                    </span>
                </div>
            </div>
        </div>

        <p class="relative text-sm text-slate-400"></p>
    </aside>

    {{-- ───────── Right: form ───────── --}}
    <main class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-10">
        <div class="w-full max-w-md">

            {{-- mobile logo --}}
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#0E1A2B] text-[#E0A83B]">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 7h10M4 12h16M4 17h7"/>
                    </svg>
                </span>
                <span class="font-display text-xl font-bold tracking-tight">{{ config('app.name', 'ProjectMS') }}</span>
            </div>

            <h2 class="font-display text-3xl font-bold tracking-tight">Sign in</h2>
            <p class="mt-2 text-slate-500">Pick up where your team left off.</p>

            <!-- Session Status -->
            <x-auth-session-status class="mt-6" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           required autofocus autocomplete="username"
                           placeholder="you@company.com"
                           class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm transition focus:border-[#0E1A2B] focus:outline-none focus:ring-4 focus:ring-[#E0A83B]/30 @error('email') border-red-400 @enderror">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div x-data="{ show: false }">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="rounded text-sm font-medium text-[#2F9E8F] hover:text-[#23796d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E0A83B]">
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif
                    </div>
                    <div class="relative mt-1.5">
                        <input id="password" name="password" :type="show ? 'text' : 'password'"
                               required autocomplete="current-password"
                               class="block w-full rounded-xl border border-slate-300 bg-white py-3 pl-4 pr-12 text-slate-900 shadow-sm transition focus:border-[#0E1A2B] focus:outline-none focus:ring-4 focus:ring-[#E0A83B]/30 @error('password') border-red-400 @enderror">
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-400 hover:text-slate-700 focus:outline-none focus-visible:text-slate-900"
                                :aria-label="show ? 'Hide password' : 'Show password'">
                            <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 010-.64C3.42 7.51 7.36 4.5 12 4.5s8.58 3.01 9.96 7.18a1 1 0 010 .64C20.58 16.49 16.64 19.5 12 19.5s-8.58-3.01-9.96-7.18z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 001.93 12c1.29 4.06 5.07 7 9.57 7 1.99 0 3.84-.58 5.4-1.58M6.23 6.23A10.45 10.45 0 0112 4.5c4.5 0 8.28 2.94 9.57 7a10.52 10.52 0 01-4.29 5.77M6.23 6.23L3 3m3.23 3.23l3.65 3.65m7.89 7.89L21 21m-3.23-3.23l-3.65-3.65m0 0a3 3 0 10-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Remember Me -->
                <label for="remember_me" class="inline-flex cursor-pointer items-center">
                    <input id="remember_me" type="checkbox" name="remember"
                           class="h-4 w-4 rounded border-slate-300 text-[#0E1A2B] focus:ring-[#E0A83B]">
                    <span class="ms-2 text-sm text-slate-600">{{ __('Remember me') }}</span>
                </label>

                <button type="submit"
                        class="w-full rounded-xl bg-[#0E1A2B] px-4 py-3.5 font-semibold text-white shadow-lg shadow-[#0E1A2B]/20 transition hover:bg-[#16283F] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#E0A83B]/50 active:scale-[0.99]">
                    {{ __('Sign in') }}
                </button>
            </form>

            <p class="mt-10 text-center text-xs text-slate-400">
                Having trouble? Contact your workspace administrator.
            </p>
        </div>
    </main>
</div>
</body>
</html>