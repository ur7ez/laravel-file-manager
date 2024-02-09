<template>
    <AuthenticatedLayout>
        <nav class="flex items-center justify-between p-1 mb-3">
            <ol class="inline-flex items-center space-x-1 md:space-x-2">
                <li v-for="ans of ancestors.data" :key="ans.id" class="inline-flex items-center">
                    <Link v-if="!ans.parent_id" :href="route('myFiles')"
                          class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                        <HomeIcon class="w-4 h-4 mr-2"/>
                        My Files
                    </Link>
                    <div v-else class="flex items-center">
                        <ChevronRightIcon class="w-6 h-6 text-gray-400"/>
                        <Link :href="route('myFiles', {folder: ans.path})"
                              class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 dark:text-gray-400 dark:hover:text-white">
                            {{ ans.name }}
                        </Link>
                    </div>
                </li>
            </ol>
        </nav>
        <div class="flex-1 overflow-auto">
            <table class="min-w-full relative" id="MyFilesTable">
                <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left w-[25px] max-w-[25px]">
                        <Checkbox @change="onSelectAllChange" v-model:checked="allSelected" class="focus:ring-0"/>
                    </th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Name</th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Owner</th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Last Modified</th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Size</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="file of allFiles.data" :key="file.id"
                    @click="toggleFileSelect(file)"
                    @dblclick="openFolder(file)"
                    class="border-b transition duration-300 ease-in-out hover:bg-blue-100 cursor-pointer"
                    :class="(selected[file.id] || allSelected) ? 'bg-blue-50' : 'bg-white'"
                >
                    <td class="text-sm font-medium text-gray-900 w-[25px] max-w-[25px]">
                        <Checkbox @change="onSelectCheckboxChange(file)" v-model="selected[file.id]"
                                  :checked="selected[file.id] || allSelected" class="focus:ring-0"/>
                    </td>
                    <td class="text-sm font-medium text-gray-900 flex items-center">
                        <FileIcon :file="file"/>
                        {{ file.name }}
                    </td>
                    <td class="whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ file.owner }}
                    </td>
                    <td class="whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ file.updated_at }}
                    </td>
                    <td class="whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ file.size }}
                    </td>
                </tr>
                </tbody>
            </table>
            <div v-if="!allFiles.data.length" class="py-8 text-center text-sm text-gray-400">
                There is no data in this folder
            </div>
            <div ref="loadMoreIntersect"></div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FileIcon from "@/Components/app/FileIcon.vue";
import Checkbox from "@/Components/Checkbox.vue";
import {Link, router} from "@inertiajs/vue3";
import {HomeIcon, ChevronRightIcon} from "@heroicons/vue/20/solid/index.js";
import {onMounted, onUpdated, ref} from "vue";
import {httpGet} from "@/Helper/http-helper.js";

// Props & Emit
const props = defineProps({
    files: Object,
    folder: Object,
    ancestors: Object,
})

// Refs
const allSelected = ref(false);
const selected = ref({});
const loadMoreIntersect = ref(null);

const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next
});

// Methods
function onSelectAllChange() {
    allFiles.value.data.forEach((f) => {
        selected.value[f.id] = allSelected.value;
    })
}

const openFolder = (file) => {
    if (!file.is_folder) {
        return;
    }
    router.visit(route('myFiles', {folder: file.path}));
}

function loadMore() {
    if (allFiles.value.next === null) {
        return;
    }
    httpGet(allFiles.value.next)
        .then(res => {
            allFiles.value.data = [...allFiles.value.data, ...res.data];
            allFiles.value.next = res.links.next;
            if (allSelected.value) {
                onSelectAllChange();
            }
        });

}

function onSelectCheckboxChange(file) {
    if (!selected.value[file.id]) {
        allSelected.value = false;
    }
}

function toggleFileSelect(file) {
    selected.value[file.id] = !selected.value[file.id];
    onSelectCheckboxChange(file);
}

// Hooks
onUpdated(() => {
    allFiles.value = {
        data: props.files.data,
        next: props.files.links.next,
    }
})

onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => entry.isIntersecting && loadMore())
    }, {
        rootMargin: '-250px 0px 0px 0px'
    })
    observer.observe(loadMoreIntersect.value);
})
</script>

<style scoped>
th {
    position: sticky;
    position: -webkit-sticky;
    top: 0;
    background-color: rgb(243 244 246 / 1);
    border-bottom-width: 1px;
}

table#MyFilesTable > tbody > tr > td {
    padding: 0.7rem 1rem;
}
</style>
