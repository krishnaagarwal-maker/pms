{{-- Partial: attachments._section --}}
@php
    $attachments = $attachable->attachments ?? collect();
    $totalBytes  = $attachments->sum('file_size');
    $humanTotal  = $totalBytes >= 1048576
        ? number_format($totalBytes / 1048576, 1) . ' MB'
        : ($totalBytes >= 1024 ? number_format($totalBytes / 1024, 1) . ' KB' : $totalBytes . ' B');
    $user = auth()->user();
@endphp

<section class="task-view-enter overflow-hidden rounded-xl bg-surface-container-lowest shadow-sm ring-1 ring-black/5 dark:bg-neutral-950 dark:shadow-none dark:ring-white/10"
         style="animation-delay: 180ms"
         x-data="{
             uploadOpen: false,
             isDragging: false,
             selectedFiles: [],
             previewUrl: null,
             previewTitle: '',
             previewIsImage: false,
             previewIsPdf: false,
             openPreview(url, title, isImg, isPdf) {
                 this.previewUrl = url;
                 this.previewTitle = title;
                 this.previewIsImage = isImg;
                 this.previewIsPdf = isPdf;
                 document.body.classList.add('overflow-hidden');
             },
             closePreview() {
                 this.previewUrl = null;
                 this.previewTitle = '';
                 this.previewIsImage = false;
                 this.previewIsPdf = false;
                 document.body.classList.remove('overflow-hidden');
             },
             onFileChange(e) {
                 this.selectedFiles = Array.from(e.target.files || []);
                 if (this.selectedFiles.length > 0) {
                     this.uploadOpen = true;
                 }
             },
             onDrop(e) {
                 this.isDragging = false;
                 if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                     this.$refs.fileInput.files = e.dataTransfer.files;
                     this.selectedFiles = Array.from(e.dataTransfer.files);
                     this.uploadOpen = true;
                 }
             },
             clearFiles() {
                 this.selectedFiles = [];
                 if (this.$refs.fileInput) {
                     this.$refs.fileInput.value = '';
                 }
             },
             formatBytes(bytes) {
                 if (!bytes || bytes === 0) return '0 B';
                 const k = 1024;
                 const sizes = ['B', 'KB', 'MB', 'GB'];
                 const i = Math.floor(Math.log(bytes) / Math.log(k));
                 return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
             }
         }"
         @keydown.escape.window="closePreview()">

    {{-- Header Banner --}}
    <div class="flex flex-wrap items-center justify-between gap-space-md border-b border-outline-variant/30 px-space-lg py-space-md dark:border-neutral-800">
        <div class="flex items-center gap-space-sm">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary dark:bg-primary-fixed/20 dark:text-primary-fixed">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">attach_file</span>
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-title-md font-semibold text-on-surface dark:text-neutral-100">
                        {{ $title ?? 'Attachments & Files' }}
                    </h2>
                    <span class="rounded-full bg-surface-container-high px-2.5 py-0.5 text-label-sm font-medium text-on-surface-variant dark:bg-neutral-800 dark:text-neutral-300">
                        {{ $attachments->count() }}
                    </span>
                    @if ($attachments->isNotEmpty() && $totalBytes > 0)
                        <span class="hidden text-body-sm text-on-surface-variant dark:text-neutral-400 sm:inline">
                            · {{ $humanTotal }} total
                        </span>
                    @endif
                </div>
                <p class="text-body-sm text-on-surface-variant dark:text-neutral-400">
                    {{ $subtitle ?? 'Documents, specifications, design assets, and deliverable files' }}
                </p>
            </div>
        </div>

        @if ($canUpload ?? true)
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="uploadOpen = !uploadOpen"
                        :class="uploadOpen ? 'bg-surface-container-high text-on-surface dark:bg-neutral-800' : 'bg-primary-container text-on-primary hover:bg-primary dark:bg-primary-fixed dark:text-on-primary-fixed'"
                        class="flex h-9 items-center gap-1.5 rounded-xl px-space-md text-label-md font-medium shadow-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <span class="material-symbols-outlined text-[18px]" :class="uploadOpen ? 'rotate-45' : ''" aria-hidden="true" style="transition: transform .2s ease">add</span>
                    <span x-text="uploadOpen ? 'Cancel' : 'Upload File'">Upload File</span>
                </button>
            </div>
        @endif
    </div>

    {{-- Expandable Upload Zone --}}
    @if ($canUpload ?? true)
        <div x-show="uploadOpen"
             x-cloak
             x-collapse
             class="border-b border-outline-variant/30 bg-surface-container-low/60 p-space-lg dark:border-neutral-800 dark:bg-neutral-900/50">

            <form action="{{ $uploadRoute }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="flex flex-col gap-space-md">
                @csrf

                {{-- Drop Area --}}
                <div @dragover.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="onDrop($event)"
                     :class="isDragging ? 'border-primary ring-4 ring-primary/10 bg-primary/5 dark:border-primary-fixed dark:bg-primary-fixed/10' : 'border-outline-variant/60 hover:border-primary/50 dark:border-neutral-700 dark:hover:border-neutral-500'"
                     class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-space-xl text-center transition-all cursor-pointer"
                     @click="$refs.fileInput.click()">

                    <input type="file"
                           name="attachments[]"
                           multiple
                           x-ref="fileInput"
                           @change="onFileChange($event)"
                           class="sr-only"
                           aria-label="Select files to upload">

                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-container-lowest text-primary shadow-sm dark:bg-neutral-800 dark:text-primary-fixed">
                        <span class="material-symbols-outlined text-[32px]" aria-hidden="true">cloud_upload</span>
                    </div>

                    <p class="mt-3 text-title-sm font-medium text-on-surface dark:text-neutral-100">
                        <span class="text-primary hover:underline dark:text-primary-fixed">Choose files</span> or drag and drop here
                    </p>
                    <p class="mt-1 text-body-sm text-on-surface-variant dark:text-neutral-400">
                        PNG, JPG, PDF, DOCX, XLSX, ZIP, code, or any document up to 25 MB per file
                    </p>
                </div>

                {{-- Staged Files List (before submitting) --}}
                <div x-show="selectedFiles.length > 0" x-cloak class="flex flex-col gap-space-sm rounded-xl bg-surface-container-lowest p-space-md shadow-sm ring-1 ring-black/5 dark:bg-neutral-950 dark:ring-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-label-md font-semibold text-on-surface dark:text-neutral-200">
                            Files ready to upload (<span x-text="selectedFiles.length"></span>)
                        </span>
                        <button type="button" @click="clearFiles()" class="text-label-sm text-on-surface-variant hover:text-error dark:text-neutral-400">
                            Clear all
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="(file, index) in selectedFiles" :key="index">
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-surface-container-low px-3 py-2 text-body-sm dark:bg-neutral-900">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">draft</span>
                                    <span class="truncate font-medium text-on-surface dark:text-neutral-200" x-text="file.name"></span>
                                </div>
                                <span class="shrink-0 text-label-sm text-on-surface-variant dark:text-neutral-400" x-text="formatBytes(file.size)"></span>
                            </div>
                        </template>
                    </div>

                    <div class="mt-2 flex items-center justify-end gap-2">
                        <button type="button" @click="clearFiles(); uploadOpen = false;"
                                class="rounded-xl px-4 py-2 text-label-md text-on-surface-variant hover:bg-surface-container-high dark:text-neutral-400 dark:hover:bg-neutral-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex items-center gap-1.5 rounded-xl bg-primary-container px-space-lg py-2 text-label-md font-medium text-on-primary shadow-sm hover:bg-primary transition-all">
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload</span>
                            <span>Upload <span x-text="selectedFiles.length > 1 ? selectedFiles.length + ' files' : 'file'"></span></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Attachments Content --}}
    <div class="p-space-lg">
        @if ($attachments->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-space-xl text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-surface-container-low text-on-surface-variant/60 ring-1 ring-black/5 dark:bg-neutral-900 dark:text-neutral-500 dark:ring-white/5">
                    <span class="material-symbols-outlined text-[36px]" aria-hidden="true">folder_open</span>
                </div>
                <h3 class="mt-3 text-title-md font-medium text-on-surface dark:text-neutral-100">No attachments yet</h3>
                <p class="mt-1 max-w-md text-body-md text-on-surface-variant dark:text-neutral-400">
                    Upload specifications, documentation, design mockups, wireframes, or reference files for this {{ $attachable instanceof \Modules\ProjectManagement\Models\Project ? 'project' : 'task' }}.
                </p>
                @if ($canUpload ?? true)
                    <button type="button"
                            @click="uploadOpen = true; $nextTick(() => $refs.fileInput.click())"
                            class="mt-space-md inline-flex items-center gap-1.5 rounded-xl bg-surface-container-high px-space-md py-2 text-label-md font-medium text-on-surface transition-all hover:bg-surface-container hover:shadow-sm dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                        Add First Attachment
                    </button>
                @endif
            </div>
        @else
            {{-- Attachments Grid --}}
            <div class="grid grid-cols-1 gap-space-md md:grid-cols-2">
                @foreach ($attachments as $att)
                    @php
                        $color = $att->color_classes;
                        $previewUrl = route('projects.attachments.preview', $att);
                        $downloadUrl = route('projects.attachments.download', $att);
                        $canDelete = $user && $user->can('attachments.delete') && (
                            (int) $att->uploaded_by === (int) $user->id ||
                            ($attachable instanceof \Modules\ProjectManagement\Models\Project && ($user->can('projects.delete') || $user->can('projects.edit'))) ||
                            ($attachable instanceof \Modules\ProjectManagement\Models\Task && ($user->can('tasks.delete') || $user->can('tasks.edit')))
                        );
                    @endphp

                    <div class="group relative flex flex-col justify-between overflow-hidden rounded-xl border {{ $color['border'] }} bg-surface-container-low/40 p-4 transition-all hover:bg-surface-container-low hover:shadow-md dark:bg-neutral-900/70 dark:hover:bg-neutral-900">
                        <div class="flex items-start gap-3">
                            {{-- Icon / Thumbnail --}}
                            @if ($att->is_image)
                                <div class="relative flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-neutral-200 shadow-sm ring-1 ring-black/5 dark:bg-neutral-800 dark:ring-white/10"
                                     @click="openPreview(@js($previewUrl), @js($att->file_name), true, false)"
                                     title="Click to preview image">
                                    <img src="{{ $previewUrl }}"
                                         alt="{{ $att->file_name }}"
                                         loading="lazy"
                                         class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-110">
                                    <span class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100 text-white">
                                        <span class="material-symbols-outlined text-[18px]">zoom_in</span>
                                    </span>
                                </div>
                            @else
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $color['bg'] }} {{ $color['text'] }} ring-1 ring-black/5 dark:ring-white/5">
                                    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ $att->icon }}</span>
                                </div>
                            @endif

                            {{-- File Info --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <a href="{{ $att->is_image || $att->is_pdf ? '#' : $downloadUrl }}"
                                       @if ($att->is_image || $att->is_pdf)
                                           @click.prevent="openPreview(@js($previewUrl), @js($att->file_name), @js($att->is_image), @js($att->is_pdf))"
                                       @endif
                                       class="truncate text-title-sm font-semibold text-on-surface transition-colors hover:text-primary dark:text-neutral-100 dark:hover:text-primary-fixed"
                                       title="{{ $att->file_name }}">
                                        {{ $att->file_name }}
                                    </a>
                                </div>

                                <div class="mt-1 flex flex-wrap items-center gap-2 text-label-sm text-on-surface-variant dark:text-neutral-400">
                                    <span class="rounded bg-surface-container-high px-1.5 py-0.5 font-code-sm uppercase tracking-wide dark:bg-neutral-800 dark:text-neutral-300">
                                        {{ $att->extension ?: 'file' }}
                                    </span>
                                    <span class="tabular-nums">{{ $att->human_size }}</span>
                                    <span class="text-outline-variant dark:text-neutral-600" aria-hidden="true">·</span>
                                    <span>{{ $att->created_at->diffForHumans() }}</span>
                                </div>

                                @if ($att->uploader)
                                    <div class="mt-2 flex items-center gap-1.5 text-body-sm text-on-surface-variant dark:text-neutral-400">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-primary-fixed text-[9px] font-bold text-on-primary-fixed">
                                            {{ Str::substr($att->uploader->name, 0, 1) }}
                                        </span>
                                        <span class="truncate">{{ $att->uploader->name }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="mt-3 flex items-center justify-end gap-1.5 border-t border-outline-variant/20 pt-2.5 dark:border-neutral-800">
                            @if ($att->is_image || $att->is_pdf)
                                <button type="button"
                                        @click="openPreview(@js($previewUrl), @js($att->file_name), @js($att->is_image), @js($att->is_pdf))"
                                        class="flex items-center gap-1 rounded-lg px-2.5 py-1 text-label-sm font-medium text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface dark:text-neutral-300 dark:hover:bg-neutral-800 transition-colors"
                                        title="Preview in browser">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">visibility</span>
                                    <span>Preview</span>
                                </button>
                            @endif

                            <a href="{{ $downloadUrl }}"
                               class="flex items-center gap-1 rounded-lg px-2.5 py-1 text-label-sm font-medium text-primary hover:bg-primary/10 dark:text-primary-fixed dark:hover:bg-primary-fixed/10 transition-colors"
                               title="Download file">
                                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">download</span>
                                <span>Download</span>
                            </a>

                            @if ($canDelete)
                                <form action="{{ route('projects.attachments.destroy', $att) }}"
                                      method="POST"
                                      onsubmit="return confirm(@js('Delete attachment “' . $att->file_name . '”? This cannot be undone.'))"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="flex items-center gap-1 rounded-lg p-1 text-on-surface-variant hover:bg-error-container hover:text-error dark:text-neutral-400 dark:hover:bg-error/20 dark:hover:text-error-container transition-colors"
                                            title="Delete attachment">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                        <span class="sr-only">Delete</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Interactive Lightbox Preview Modal --}}
    <template x-teleport="body">
        <div x-show="previewUrl"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm sm:p-6"
             @click.self="closePreview()">

            <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl ring-1 ring-white/10 dark:bg-neutral-950">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between border-b border-outline-variant/30 px-6 py-4 dark:border-neutral-800">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary dark:text-primary-fixed" aria-hidden="true">
                            <span x-text="previewIsImage ? 'image' : (previewIsPdf ? 'picture_as_pdf' : 'description')"></span>
                        </span>
                        <h3 class="truncate text-title-md font-semibold text-on-surface dark:text-neutral-100" x-text="previewTitle"></h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="previewUrl"
                           target="_blank"
                           class="flex h-9 items-center gap-1 rounded-xl bg-surface-container-high px-3 text-label-md text-on-surface hover:bg-surface-container dark:bg-neutral-800 dark:text-neutral-200 transition-colors"
                           title="Open in new window">
                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                            <span class="hidden sm:inline">New tab</span>
                        </a>
                        <button type="button"
                                @click="closePreview()"
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface-container-high text-on-surface-variant hover:bg-surface-container hover:text-on-surface dark:bg-neutral-800 dark:text-neutral-300 transition-colors"
                                aria-label="Close preview">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="flex flex-1 items-center justify-center overflow-auto bg-black/5 p-4 dark:bg-black/40">
                    <template x-if="previewIsImage">
                        <img :src="previewUrl"
                             :alt="previewTitle"
                             class="max-h-[70vh] w-auto max-w-full rounded-lg object-contain shadow-md">
                    </template>

                    <template x-if="previewIsPdf">
                        <iframe :src="previewUrl" class="h-[70vh] w-full rounded-lg border-0 bg-white"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </template>
</section>
