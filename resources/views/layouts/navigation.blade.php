{{-- resources/views/layouts/navigation.blade.php --}}
{{--
| Light-blue top bar + a command palette (Ctrl/Cmd + K) for jumping anywhere.
| Needs Alpine.js v3 (ships with Breeze) and the Material Symbols font.
| Give your layout's <main> the id "main-content" so the skip link has a target.
--}}

@php
    $navUser = Auth::user();
    $initial = Str::upper(Str::substr($navUser->name, 0, 1));
    $navRole = method_exists($navUser, 'getRoleNames') ? $navUser->getRoleNames()->first() : null;

    // label, route, active route patterns, permission (null = everyone), icon
    $navLinks = collect([
        ['Dashboard',           'dashboard',            ['dashboard'],                                                          null,            'dashboard'],
        ['Projects',            'projects.index',       ['projects.index', 'projects.show', 'projects.create', 'projects.edit'], 'projects.view', 'folder_open'],
        ['My tasks',            'projects.my-tasks',    ['projects.my-tasks'],                                                  'tasks.view',    'assignment_ind'],
        ['All tasks',           'projects.tasks.index', ['projects.tasks.*'],                                                   'tasks.view',    'checklist'],
        ['Roles & permissions', 'projects.roles.index', ['projects.roles.*', 'projects.users.roles.*'],                         'roles.view',    'admin_panel_settings'],
    ])->filter(fn ($l) => $l[3] === null || $navUser->can($l[3]));

    $hideNewProject = request()->routeIs('dashboard', 'projects.create');

    // Command palette entries
    $paletteItems = $navLinks->map(fn ($l) => [
        'id' => 'page-' . $l[1], 'group' => 'Go to', 'label' => $l[0], 'icon' => $l[4],
        'url' => route($l[1]), 'keywords' => str_replace('.', ' ', $l[1]),
    ])->values()->all();

    if ($navUser->can('projects.create')) {
        $paletteItems[] = ['id' => 'new-project', 'group' => 'Actions', 'label' => 'New project', 'icon' => 'add',
                           'url' => route('projects.create'), 'keywords' => 'create add start'];
    }
    $paletteItems[] = ['id' => 'profile', 'group' => 'Actions', 'label' => 'My profile', 'icon' => 'person',
                       'url' => route('profile.edit'), 'keywords' => 'account settings password'];
    $paletteItems[] = ['id' => 'logout', 'group' => 'Actions', 'label' => 'Log out', 'icon' => 'logout',
                       'url' => '', 'type' => 'logout', 'keywords' => 'sign out exit'];

    $ring     = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 focus-visible:ring-offset-sky-100';
    $ringLite = 'focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2';
@endphp

<style>
    [x-cloak] { display: none !important; }
    @media (prefers-reduced-motion: reduce) { .nav-motion * { transition: none !important; animation: none !important; } }
</style>

<nav x-data="{
        open: false,
        scrolled: false,
        palette: false,
        q: '',
        idx: 0,
        isMac: false,
        items: {{ \Illuminate\Support\Js::from($paletteItems) }},
        get results() {
            const s = this.q.trim().toLowerCase();
            return s ? this.items.filter(i => (i.label + ' ' + i.keywords).toLowerCase().includes(s)) : this.items;
        },
        openPalette() {
            this.open = false;
            this.palette = true;
            this.q = '';
            this.idx = 0;
            this.$nextTick(() => this.$refs.q.focus());
        },
        go(item) {
            if (item.type === 'logout') { document.getElementById('palette-logout').submit(); return; }
            window.location.href = item.url;
        },
        move(step) {
            const n = this.results.length;
            if (!n) return;
            this.idx = (this.idx + step + n) % n;
            this.$nextTick(() => document.getElementById('cmd-' + this.idx)?.scrollIntoView({ block: 'nearest' }));
        }
     }"
     x-init="isMac = /Mac|iPhone|iPad/.test(navigator.platform)"
     x-effect="document.body.classList.toggle('overflow-hidden', palette)"
     x-on:scroll.window="scrolled = window.scrollY > 4"
     x-on:keydown.escape.window="palette = false; open = false"
     x-on:keydown.window="if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); openPalette() }"
     x-on:resize.window="if (window.innerWidth >= 768) open = false"
     x-bind:class="scrolled ? 'shadow-md shadow-sky-900/10' : ''"
     class="nav-motion sticky top-0 z-50 border-b border-sky-200 bg-sky-100/95 backdrop-blur transition-shadow"
     aria-label="Primary">

    <a href="#main-content"
       class="sr-only rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[80] {{ $ring }}">
        Skip to content
    </a>

    <form id="palette-logout" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>

    <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-4">

            {{-- Brand + desktop links --}}
            <div class="flex items-center gap-6 lg:gap-8">
                <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-3 rounded-lg {{ $ring }}">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.25)]">
                        <x-application-logo class="block h-5 w-auto fill-current text-white" />
                    </span>
                
                </a>

                <div class="hidden h-16 items-center gap-1 md:flex">
                    @foreach ($navLinks as [$label, $routeName, $patterns])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <a href="{{ route($routeName) }}"
                           @if ($active) aria-current="page" @endif
                           class="relative rounded-lg px-3 py-2 text-sm font-medium transition {{ $ring }}
                                  {{ $active
                                      ? 'bg-white text-slate-900 shadow-sm after:absolute after:inset-x-3 after:-bottom-[14px] after:h-0.5 after:rounded-full after:bg-indigo-600'
                                      : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Desktop actions --}}
            <div class="hidden items-center gap-3 md:flex">
                {{-- Palette trigger --}}
                <button type="button" x-on:click="openPalette()"
                        class="group inline-flex h-9 items-center gap-2 rounded-lg border border-sky-200 bg-white px-2.5 text-sm text-slate-500 transition hover:border-sky-300 hover:text-slate-700 lg:w-64 {{ $ring }}">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">search</span>
                    <span class="hidden flex-1 text-left lg:inline">Search or jump to…</span>
                    <span class="sr-only lg:hidden">Open search</span>
                    <kbd class="hidden rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-code-sm text-[11px] text-slate-500 lg:inline" x-text="isMac ? '⌘K' : 'Ctrl K'">Ctrl K</kbd>
                </button>

               

                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-white py-1 pl-1 pr-2.5 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 {{ $ring }}">
                            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-600 text-sm font-bold text-white" aria-hidden="true">{{ $initial }}</span>
                            <span class="hidden max-w-32 truncate xl:inline">{{ $navUser->name }}</span>
                            <span class="sr-only xl:hidden">Account menu for {{ $navUser->name }}</span>
                            <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">expand_more</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $navUser->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $navUser->email }}</p>
                            @if ($navRole)
                                <span class="mt-2 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ Str::headline($navRole) }}</span>
                            @endif
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">
                            <span class="flex items-center gap-2.5">
                                <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">person</span>
                                My profile
                            </span>
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm leading-5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:bg-slate-100 focus:outline-none">
                                <span class="material-symbols-outlined text-[18px] text-slate-400" aria-hidden="true">logout</span>
                                Log out
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Mobile buttons --}}
            <div class="flex items-center gap-1 md:hidden">
                <button type="button" x-on:click="openPalette()"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-sky-200/60 hover:text-slate-900 {{ $ring }}">
                    <span class="sr-only">Open search</span>
                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">search</span>
                </button>
                <button type="button"
                        x-on:click="open = !open"
                        x-bind:aria-expanded="open.toString()"
                        aria-controls="mobile-nav"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-sky-200/60 hover:text-slate-900 {{ $ring }}">
                    <span class="sr-only">Toggle navigation</span>
                    <svg x-show="!open" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile navigation --}}
    <div id="mobile-nav"
         x-show="open"
         x-cloak
         x-transition.opacity.duration.150ms
         class="max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-sky-200 bg-sky-50 md:hidden">
        <div class="space-y-1 px-3 py-3">
            @foreach ($navLinks as [$label, $routeName, $patterns, $perm, $icon])
                @php $active = request()->routeIs(...$patterns); @endphp
                <a href="{{ route($routeName) }}"
                   @if ($active) aria-current="page" @endif
                   class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition {{ $ring }}
                          {{ $active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-white/70 hover:text-slate-900' }}">
                    <span class="material-symbols-outlined text-[20px] {{ $active ? 'text-indigo-600' : 'text-slate-500' }}" aria-hidden="true">{{ $icon }}</span>
                    {{ $label }}
                </a>
            @endforeach

            @can('projects.create')
                @unless ($hideNewProject)
                    <a href="{{ route('projects.create') }}"
                       class="mt-2 flex h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 text-sm font-semibold text-white transition hover:bg-indigo-700 {{ $ring }}">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                        New project
                    </a>
                @endunless
            @endcan
        </div>

        <div class="border-t border-sky-200 px-4 py-4">
            <div class="mb-3 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-base font-bold text-white" aria-hidden="true">{{ $initial }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $navUser->name }}</p>
                    <p class="truncate text-sm text-slate-500">{{ $navUser->email }}</p>
                    @if ($navRole)
                        <span class="mt-1 inline-flex items-center rounded-full bg-white px-2 py-0.5 text-xs font-medium text-indigo-700">{{ Str::headline($navRole) }}</span>
                    @endif
                </div>
            </div>

            <div class="space-y-1">
                <a href="{{ route('profile.edit') }}"
                   class="flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-600 transition hover:bg-white/70 hover:text-slate-900 {{ $ring }}">
                    <span class="material-symbols-outlined text-[20px] text-slate-500" aria-hidden="true">person</span>
                    My profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex h-11 w-full items-center gap-3 rounded-lg px-3 text-start text-sm font-medium text-slate-600 transition hover:bg-white/70 hover:text-slate-900 {{ $ring }}">
                        <span class="material-symbols-outlined text-[20px] text-slate-500" aria-hidden="true">logout</span>
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Command palette --}}
    <div x-show="palette" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Search and jump to">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
             x-on:click="palette = false"
             x-show="palette"
             x-transition.opacity.duration.150ms></div>

        <div class="relative w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-900/10"
             x-show="palette"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="flex items-center gap-3 border-b border-slate-200 px-4">
                <span class="material-symbols-outlined text-[20px] text-slate-400" aria-hidden="true">search</span>
                <input x-ref="q" x-model="q" type="text" autocomplete="off" spellcheck="false"
                       placeholder="Search pages and actions…"
                       aria-label="Search pages and actions"
                       x-on:input="idx = 0"
                       x-on:keydown.arrow-down.prevent="move(1)"
                       x-on:keydown.arrow-up.prevent="move(-1)"
                       x-on:keydown.enter.prevent="results[idx] && go(results[idx])"
                       class="h-14 w-full border-0 bg-transparent text-base text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">
                <button type="button" x-on:click="palette = false"
                        class="rounded-md border border-slate-200 px-1.5 py-0.5 font-code-sm text-[11px] text-slate-500 hover:bg-slate-50 {{ $ringLite }}">Esc</button>
            </div>

            <div class="max-h-80 overflow-y-auto p-2" role="listbox">
                <template x-for="(item, i) in results" :key="item.id">
                    <div>
                        <p x-show="i === 0 || results[i - 1].group !== item.group"
                           class="px-3 pb-1 pt-3 text-xs font-semibold text-slate-400" x-text="item.group"></p>
                        <button type="button" role="option"
                                x-bind:id="'cmd-' + i"
                                x-bind:aria-selected="(i === idx).toString()"
                                x-on:click="go(item)"
                                x-on:mouseenter="idx = i"
                                x-bind:class="i === idx ? 'bg-indigo-50 text-slate-900' : 'text-slate-600'"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium focus:outline-none">
                            <span class="material-symbols-outlined text-[20px]" x-bind:class="i === idx ? 'text-indigo-600' : 'text-slate-400'" aria-hidden="true" x-text="item.icon"></span>
                            <span class="flex-1" x-text="item.label"></span>
                            <span x-show="i === idx" class="text-xs text-slate-400">Enter</span>
                        </button>
                    </div>
                </template>

                <p x-show="results.length === 0" class="px-3 py-8 text-center text-sm text-slate-500"
                   x-text="'No matches for “' + q + '”'"></p>
            </div>

            <div class="flex items-center gap-4 border-t border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500">
                <span><kbd class="font-code-sm">↑↓</kbd> to move</span>
                <span><kbd class="font-code-sm">Enter</kbd> to open</span>
                <span><kbd class="font-code-sm">Esc</kbd> to close</span>
            </div>
        </div>
    </div>
</nav>