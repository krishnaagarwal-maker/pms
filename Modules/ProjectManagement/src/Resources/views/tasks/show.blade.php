@php
    $statusStyles = [
        'pending'     => 'border-slate-200 bg-slate-100 text-slate-600',
        'in_progress' => 'border-sky-200 bg-sky-50 text-sky-700',
        'completed'   => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    ];
    $priorityStyles = [
        'low'    => 'border-slate-200 bg-slate-100 text-slate-600',
        'medium' => 'border-amber-200 bg-amber-50 text-amber-800',
        'high'   => 'border-rose-200 bg-rose-50 text-rose-700',
    ];

    // Due-date state: only meaningful while the task is still open.
    $dueState = null;
    $dueLabel = null;
    if ($task->due_date && $task->status !== 'completed') {
        $days = (int) now()->startOfDay()->diffInDays($task->due_date->copy()->startOfDay(), false);
        if ($days < 0) {
            $dueState = 'overdue';
            $dueLabel = abs($days) . ' ' . Str::plural('day', abs($days)) . ' overdue';
        } elseif ($days === 0) {
            $dueState = 'soon';
            $dueLabel = 'Due today';
        } elseif ($days <= 3) {
            $dueState = 'soon';
            $dueLabel = $days . ' ' . Str::plural('day', $days) . ' left';
        }
    }
    $dueStyles = [
        'overdue' => 'border-rose-200 bg-rose-50 text-rose-700',
        'soon'    => 'border-amber-200 bg-amber-50 text-amber-800',
    ];

    $card = 'rounded-lg bg-white shadow-[0_0_0_1px_#e2e8f0,0_1px_2px_0_rgba(15,23,42,0.04)]';
    $pill = 'inline-flex h-6 items-center gap-1.5 rounded-full border px-2 text-xs font-medium';
    $label = 'text-[11px] font-medium uppercase tracking-wide text-slate-500';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex items-center gap-1.5 text-[13px] text-slate-500" aria-label="Breadcrumb">
                <a href="{{ route('projects.index') }}" class="hover:text-slate-900">Projects</a>
                <span class="text-slate-300">/</span>
                <a href="{{ route('projects.show', $task->project_id) }}" class="hover:text-slate-900">{{ $task->project->name }}</a>
                <span class="text-slate-300">/</span>
                <span class="font-medium text-slate-900">Task</span>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('projects.show', $task->project_id) }}"
                   class="inline-flex h-9 items-center rounded-md border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-900 hover:border-slate-300 hover:bg-slate-50">
                    Back to project
                </a>
                <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                   class="inline-flex h-9 items-center rounded-md border border-transparent bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:ring-offset-2">
                    Edit task
                </a>
            </div>
        </div>
    </x-slot>

    <div class="bg-slate-50 py-8">
        <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">

            {{-- ============ Main column ============ --}}
            <div class="space-y-6 lg:col-span-8">

                <section class="{{ $card }} p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="{{ $pill }} {{ $statusStyles[$task->status] ?? $statusStyles['pending'] }}">
                            {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                        </span>
                        <span class="{{ $pill }} {{ $priorityStyles[$task->priority] ?? $priorityStyles['low'] }}">
                            {{ ucfirst($task->priority) }} priority
                        </span>
                        @if ($dueState)
                            <span class="{{ $pill }} ml-auto {{ $dueStyles[$dueState] }}">{{ $dueLabel }}</span>
                        @endif
                    </div>

                    <h1 class="mt-4 break-words text-3xl font-bold leading-[2.75rem] tracking-[-0.025em] text-slate-900 sm:text-4xl">
                        {{ $task->title }}
                    </h1>

                    <p>

</p>
                    <p class="mt-2 text-sm text-slate-500">
                        Created {{ $task->created_at->format('d M Y') }}
                        <span class="text-slate-300">&middot;</span>
                        Updated {{ $task->updated_at->diffForHumans() }}
                    </p>
                </section>

                <section class="{{ $card }} p-6">
                    <h2 class="text-base font-semibold tracking-tight text-slate-900">Description</h2>
                    @if (filled($task->description))
                        <div class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $task->description }}</div>
                    @else
                        <p class="mt-3 text-sm text-slate-500">No description provided.</p>
                    @endif
                </section>

                {{-- Access: renders only if the task has a role. Data comes from the DB, so custom roles work. --}}
                @if ($task->assignedRole ?? null)
                    <section class="{{ $card }} p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold tracking-tight text-slate-900">Access</h2>
                                <p class="mt-0.5 text-[13px] text-slate-500">Role applied to this task.</p>
                            </div>
                            <span class="{{ $pill }} {{ $task->assignedRole->is_system ? 'border-slate-200 bg-slate-100 text-slate-600' : 'border-indigo-200 bg-indigo-50 text-indigo-700' }}">
                                {{ $task->assignedRole->name }}
                                &middot; {{ $task->assignedRole->is_system ? 'Default' : 'Custom' }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @forelse ($task->assignedRole->permissions as $permission)
                                <span class="{{ $pill }} border-emerald-200 bg-emerald-50 text-emerald-800">{{ $permission->name }}</span>
                            @empty
                                <span class="text-sm text-slate-500">This role has no permissions.</span>
                            @endforelse
                        </div>
                    </section>
                @endif
            </div>

            {{-- ============ Sidebar ============ --}}
            <aside class="lg:col-span-4">
                <section class="{{ $card }} p-6">
                    <h2 class="text-base font-semibold tracking-tight text-slate-900">Details</h2>

                    <dl class="mt-4 divide-y divide-slate-100">
                        <div class="pb-4">
                            <dt class="{{ $label }}">Project</dt>
                            <dd class="mt-1">
                                <a href="{{ route('projects.show', $task->project_id) }}"
                                   class="text-sm font-medium text-indigo-600 hover:text-indigo-700">{{ $task->project->name }}</a>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3 py-4">
                            <div>
                                <dt class="{{ $label }}">Due date</dt>
                                <dd class="mt-1 text-sm font-medium tabular-nums text-slate-900">
                                    {{ $task->due_date?->format('d M Y') ?? 'Not set' }}
                                </dd>
                            </div>
                            @if ($dueState)
                                <span class="{{ $pill }} {{ $dueStyles[$dueState] }}">{{ $dueLabel }}</span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between gap-3 py-4">
                            <dt class="{{ $label }}">Priority</dt>
                            <dd><span class="{{ $pill }} {{ $priorityStyles[$task->priority] ?? $priorityStyles['low'] }}">{{ ucfirst($task->priority) }}</span></dd>
                        </div>

                        <div class="flex items-center justify-between gap-3 py-4">
                            <dt class="{{ $label }}">Status</dt>
                            <dd><span class="{{ $pill }} {{ $statusStyles[$task->status] ?? $statusStyles['pending'] }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span></dd>
                        </div>

                        <div class="pt-4">
                            <dt class="{{ $label }}">Created</dt>
                            <dd class="mt-1 text-sm tabular-nums text-slate-900">{{ $task->created_at->format('d M Y, H:i') }}</dd>
                        </div>
                        <strong>Assigned To:</strong>
                        {{ $task->assignee?->name ?? 'Unassigned' }}
                    </dl>
                </section>
            </aside>
        </div>
    </div>
    
</x-app-layout>