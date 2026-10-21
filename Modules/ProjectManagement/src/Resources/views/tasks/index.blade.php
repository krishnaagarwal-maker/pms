
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            All Tasks
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 p-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

                @if ($tasks->isEmpty())
                    <div class="p-10 text-center">
                        <h3 class="text-lg font-semibold text-gray-900">
                            No tasks yet
                        </h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Open a project to create its first task.
                        </p>

                        <a href="{{ route('projects.index') }}"
                           class="mt-4 inline-block text-indigo-600 hover:underline">
                            View Projects
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                        Task
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                        Project
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                        Due Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                        Priority
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($tasks as $task)
                                    @php
                                        $isOverdue = $task->due_date
                                            && $task->due_date->isPast()
                                            && $task->status !== 'completed';
                                    @endphp

                                    <tr class="{{ $isOverdue ? 'bg-red-50' : '' }}">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">
                                                {{ $task->title }}
                                            </div>

                                            @if ($isOverdue)
                                                <span class="text-xs font-semibold text-red-600">
                                                    Overdue
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            <a href="{{ route('projects.show', $task->project) }}"
                                               class="text-indigo-600 hover:underline">
                                                {{ $task->project->name }}
                                            </a>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-600">
                                            {{ $task->due_date?->format('d M Y') ?? '—' }}
                                        </td>

                                        <td class="px-6 py-4 text-sm">
                                            {{ ucfirst($task->priority) }}
                                        </td>

                                        <td class="px-6 py-4 text-sm">
                                            {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                        </td>

                                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                                            <a href="{{ route('projects.tasks.show', [$task->project, $task] ) }}"
                                               class="text-indigo-600 hover:underline">
                                                View
                                            </a>

                                            @can('tasks.edit')
                                                <a href="{{ route('projects.tasks.edit', [$task->project, $task]) }}"
                                                   class="ml-3 text-indigo-600 hover:underline">
                                                    Edit
                                                </a>
                                            @endcan

                                            @can('tasks.delete')
                                                <form action="{{ route('projects.tasks.destroy', [$task->project, $task]) }}"
                                                      method="POST"
                                                      class="ml-3 inline"
                                                      onsubmit="return confirm('Delete this task?')">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit"
                                                            class="text-red-600 hover:underline">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $tasks->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
