{{-- Partial: attachments._form-upload --}}
@php
    $existing = $existingAttachments ?? collect();
@endphp

<div class="flex flex-col gap-space-sm"
     x-data="{
         isDragging: false,
         files: [],
         onFileChange(e) {
             this.files = Array.from(e.target.files || []);
         },
         onDrop(e) {
             this.isDragging = false;
             if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                 this.$refs.input.files = e.dataTransfer.files;
                 this.files = Array.from(e.dataTransfer.files);
             }
         },
         clearFiles() {
             this.files = [];
             if (this.$refs.input) {
                 this.$refs.input.value = '';
             }
         },
         formatBytes(bytes) {
             if (!bytes || bytes === 0) return '0 B';
             const k = 1024;
             const sizes = ['B', 'KB', 'MB', 'GB'];
             const i = Math.floor(Math.log(bytes) / Math.log(k));
             return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
         }
     }">

    <div class="flex items-center justify-between">
        <label class="flex items-center gap-1.5 text-label-md font-medium text-on-surface dark:text-neutral-200">
            <span class="material-symbols-outlined text-[18px] text-primary dark:text-primary-fixed" aria-hidden="true">attachment</span>
            {{ $label ?? 'Attachments' }}
            <span class="text-body-sm font-normal text-on-surface-variant dark:text-neutral-400">(Optional)</span>
        </label>
        <span class="text-label-sm text-on-surface-variant dark:text-neutral-400">Max 25 MB each</span>
    </div>

    {{-- Dropzone Area --}}
    <div @dragover.prevent="isDragging = true"
         @dragleave.prevent="isDragging = false"
         @drop.prevent="onDrop($event)"
         :class="isDragging ? 'border-primary ring-4 ring-primary/10 bg-primary/5 dark:border-primary-fixed dark:bg-primary-fixed/10' : 'border-outline-variant/60 hover:border-primary/50 dark:border-neutral-700 dark:hover:border-neutral-500'"
         class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed bg-surface-container-low/30 p-space-lg text-center transition-all cursor-pointer dark:bg-neutral-900/40"
         @click="$refs.input.click()">

        <input type="file"
               name="attachments[]"
               multiple
               x-ref="input"
               @change="onFileChange($event)"
               class="sr-only"
               aria-label="Upload attachments">

        <span class="material-symbols-outlined text-[28px] text-on-surface-variant/70 dark:text-neutral-400" aria-hidden="true">upload_file</span>
        <p class="mt-2 text-body-md font-medium text-on-surface dark:text-neutral-200">
            <span class="text-primary hover:underline dark:text-primary-fixed">Choose files</span> or drag and drop
        </p>
        <p class="mt-0.5 text-body-sm text-on-surface-variant dark:text-neutral-400">
            Images, PDFs, documents, spreadsheets, code, or archives
        </p>
    </div>

    {{-- Staged Files List (client-side) --}}
    <div x-show="files.length > 0" x-cloak class="mt-2 flex flex-col gap-2 rounded-xl bg-surface-container-low p-3 dark:bg-neutral-900">
        <div class="flex items-center justify-between">
            <span class="text-label-sm font-semibold text-on-surface dark:text-neutral-200">
                Selected files (<span x-text="files.length"></span>)
            </span>
            <button type="button" @click="clearFiles()" class="text-label-sm text-on-surface-variant hover:text-error dark:text-neutral-400">
                Remove all
            </button>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <template x-for="(file, i) in files" :key="i">
                <div class="flex items-center justify-between gap-2 rounded-lg bg-surface-container-lowest px-3 py-1.5 text-body-sm shadow-sm dark:bg-neutral-950">
                    <div class="flex min-w-0 items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] text-primary" aria-hidden="true">check_circle</span>
                        <span class="truncate font-medium text-on-surface dark:text-neutral-200" x-text="file.name"></span>
                    </div>
                    <span class="shrink-0 text-label-sm text-on-surface-variant dark:text-neutral-400" x-text="formatBytes(file.size)"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- Existing Attachments (if editing) --}}
    @if ($existing->isNotEmpty())
        <div class="mt-3 flex flex-col gap-2">
            <span class="text-label-md font-medium text-on-surface dark:text-neutral-200">
                Existing Attachments ({{ $existing->count() }})
            </span>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($existing as $attachment)
                    <div class="flex items-center justify-between gap-2 rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-2.5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">{{ $attachment->icon }}</span>
                            <div class="min-w-0">
                                <span class="block truncate text-body-sm font-medium text-on-surface dark:text-neutral-200">{{ $attachment->file_name }}</span>
                                <span class="text-label-sm text-on-surface-variant dark:text-neutral-400">{{ $attachment->human_size }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('projects.attachments.download', $attachment) }}"
                               class="rounded p-1 text-on-surface-variant hover:text-primary dark:text-neutral-400 dark:hover:text-primary-fixed"
                               title="Download file">
                                <span class="material-symbols-outlined text-[18px]">download</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @error('attachments')
        <p class="mt-1 text-body-sm text-error" role="alert">{{ $message }}</p>
    @enderror
    @error('attachments.*')
        <p class="mt-1 text-body-sm text-error" role="alert">{{ $message }}</p>
    @enderror
</div>
