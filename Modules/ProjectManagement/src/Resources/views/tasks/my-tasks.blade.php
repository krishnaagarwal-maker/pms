
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Tasks
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @forelse ($tasks as $task)
                <div class="mb-4 rounded-lg bg-white p-5 shadow">
                    <h3 class="text-lg font-semibold">
                        {{ $task->title }}
                    </h3>

                    <p class="text-sm text-gray-600">
                        Project: {{ $task->project->name ?? 'Unknown project' }}
                    </p>

                    <p class="text-sm text-gray-600">
                        Status: {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                    </p>

                    <p class="text-sm text-gray-600">
                        Due date: {{ $task->due_date?->format('d M Y') ?? 'No due date' }}
                    </p>

                    <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}"
                       class="mt-3 inline-block text-indigo-600 hover:underline">
                        View Task
                    </a>
                </div>
            @empty
                <div class="rounded-lg bg-white p-8 text-center shadow">
                    <p class="text-gray-600">
                        No tasks have been assigned to you yet.
                    </p>
                </div>
            @endforelse

            {{ $tasks->links() }}
        </div>
    </div>
</x-app-layout>
