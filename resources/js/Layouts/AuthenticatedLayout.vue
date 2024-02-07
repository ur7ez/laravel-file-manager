<template>
    <div class="h-screen bg-gray-50 flex w-full gap-4">
        <Navigation/>

        <main @drop.prevent="handleDrop"
              @dragover.prevent="onDragOver"
              @dragleave.prevent="onDragLeave"
              class="flex flex-col flex-1 px-4 overflow-hidden"
              :class="dragOver ? 'dropzone' : ''">

            <template v-if="dragOver" class="text-gray-500 text-center py-8">
                Drop files here to upload
            </template>
            <template v-else>
                <div class="flex items-center justify-between w-full">
                    <SearchForm/>
                    <UserSettingsDropdown/>
                </div>
                <div class="flex flex-1 flex-col overflow-hidden">
                    <slot/>
                </div>
            </template>
        </main>
    </div>
</template>

<script setup>
import Navigation from "@/Components/app/Navigation.vue";
import SearchForm from "@/Components/app/SearchForm.vue";
import UserSettingsDropdown from "@/Components/app/UserSettingsDropdown.vue";
import {onMounted, ref} from "vue";
import {emitter, FILE_UPLOAD_STARTED} from "@/event-bus.js";
import {useForm, usePage} from "@inertiajs/vue3";

// Uses
const page = usePage();
const fileUploadForm = useForm({
    files: [],
    relative_paths: [],
    parent_id: null,
})

// Refs
const dragOver = ref(false);

// Methods
const onDragOver = () => {
    dragOver.value = true;
}
const onDragLeave = () => {
    dragOver.value = false;
}
const handleDrop = (ev) => {
    dragOver.value = false;
    const files = ev.dataTransfer.files;
    if (!files.length) {
        return;
    }
    uploadFiles(files);
}

const uploadFiles = (files) => {
    fileUploadForm.files = files;
    fileUploadForm.parent_id = page.props.folder.id;
    fileUploadForm.relative_paths = [...files].map(f => f.webkitRelativePath);
    fileUploadForm.post(route('file.store'));
}

// Hooks
onMounted(() => {
    emitter.on(FILE_UPLOAD_STARTED, uploadFiles)
})
</script>

<style scoped>
.dropzone {
    width: 100%;
    height: 100%;
    color: #8d8d8d;
    border: 2px dashed gray;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
