<template>
    <MenuItem v-slot="{ active }">
        <a href="#" class="text-gray-700 block px-4 py-2 text-sm relative">
            Upload Folder
            <input type="file" @change="onChange" multiple directory webkitdirectory
                   class="absolute left-0 right-0 top-0 bottom-0 opacity-0 cursor-pointer">
        </a>
    </MenuItem>
</template>

<script setup>
import {MenuItem} from '@headlessui/vue'
import {emitter, FILE_UPLOAD_STARTED} from "@/event-bus.js";

const onChange = (ev) => {
    emitter.emit(FILE_UPLOAD_STARTED, ev.target.files);
}
</script>
