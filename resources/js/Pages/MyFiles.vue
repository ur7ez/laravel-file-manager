<template>
    <AuthenticatedLayout>
        <nav class="flex items-center justify-between p-1 mb-2">
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
            <div class="flex items-center">
                <label class="cursor-pointer mr-3" title="(un)check to (un)filter for favourite items">
                    <Checkbox @change="showOnlyFavourites" v-model:checked="onlyFavourites"
                              class="cursor-pointer mr-1"/>
                    Only Favourites
                </label>
                <ShareFilesButton :all-selected="allSelected" :selected-ids="selectedIds"/>
                <DownloadFilesButton :all="allSelected" :ids="selectedIds" class="mr-2 capitalize"/>
                <DeleteFilesButton :delete-all="allSelected" :delete-ids="selectedIds" @delete="onDelete"/>
            </div>
        </nav>
        <div class="flex-1 overflow-auto">
            <table class="min-w-full relative" id="MyFilesTable">
                <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left w-[25px] max-w-[25px]">
                        <Checkbox @change="onSelectAllChange" v-model:checked="allSelected"
                                  class="focus:ring-0 cursor-pointer"/>
                    </th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left"></th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Name</th>
                    <th v-if="search" class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Folder</th>
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
                                  :checked="selected[file.id] || allSelected" class="focus:ring-0 cursor-pointer"/>
                    </td>
                    <td class="text-sm font-medium text-yellow-500 max-w-[30px]">
                        <div @click.stop.prevent="addRemoveFavourite(file)" title="click to toggle item in favourites ">
                            <svg v-if="file.is_favourite" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="currentColor"
                                 class="w-5 h-5">
                                <path fill-rule="evenodd"
                                      d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"
                                      clip-rule="evenodd"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none"
                                 viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
                            </svg>
                        </div>
                    </td>
                    <td class="text-sm font-medium text-gray-900 flex items-center">
                        <FileIcon :file="file"/>
                        {{ file.name }}
                    </td>
                    <td v-if="search" class="text-sm font-medium text-gray-900">
                        {{ file.folder }}
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
// Imports
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FileIcon from "@/Components/app/FileIcon.vue";
import Checkbox from "@/Components/Checkbox.vue";
import DeleteFilesButton from "@/Components/app/DeleteFilesButton.vue";
import DownloadFilesButton from "@/Components/app/DownloadFilesButton.vue";
import ShareFilesButton from "@/Components/app/ShareFilesButton.vue";
import {Link, router} from "@inertiajs/vue3";
import {HomeIcon, ChevronRightIcon} from "@heroicons/vue/20/solid/index.js";
import {computed, onBeforeMount, onMounted, onUpdated, ref} from "vue";
import {httpGet, httpPost} from "@/Helper/http-helper.js";
import {emitter, ON_SEARCH, showErrorNotification, showSuccessNotification} from "@/event-bus.js";

// Props & Emit
const props = defineProps({
    files: Object,
    folder: Object,
    ancestors: Object,
})

// Refs
const allSelected = ref(false);
const onlyFavourites = ref(false);
const selected = ref({});
const loadMoreIntersect = ref(null);
let search = ref('');

const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next
});

let urlParams = null;

// Computed
const selectedIds = computed(() => Object.entries(selected.value).filter(a => a[1]).map(a => a[0]));

// Methods
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

function onSelectAllChange() {
    allFiles.value.data.forEach((f) => {
        selected.value[f.id] = allSelected.value;
    })
}

function onSelectCheckboxChange(file) {
    if (!selected.value[file.id]) {
        allSelected.value = false;
    } else {
        let checked = true;
        for (let file of allFiles.value.data) {
            if (!selected.value[file.id]) {
                checked = false;
                break;
            }
        }
        allSelected.value = checked;
    }
}

function toggleFileSelect(file) {
    selected.value[file.id] = !selected.value[file.id];
    onSelectCheckboxChange(file);
}

function onDelete() {
    allSelected.value = false;
    selected.value = {};
}

function addRemoveFavourite(file) {
    httpPost(route('file.addToFavourites'), {id: file.id})
        .then(res => {
            file.is_favourite = !file.is_favourite;
            showSuccessNotification(file.is_favourite
                ? 'Selected item have been added to favourites'
                : 'Selected item have been removed from favourites');
        })
        .catch(async (err) => {
            showErrorNotification(err.error.message);
        });
}

function showOnlyFavourites() {
    urlParams = new URLSearchParams(window.location.search);
    if (onlyFavourites.value) {
        urlParams.set('favourites', '1');
    } else {
        urlParams.delete('favourites');
    }
    router.get(window.location.pathname, urlParams, {preserveState: true});
}

// Hooks
onUpdated(() => {
    allFiles.value = {
        data: props.files.data,
        next: props.files.links.next,
    }
})

onMounted(() => {
    urlParams = new URLSearchParams(window.location.search);
    onlyFavourites.value = urlParams.get('favourites') === '1';
    search.value = urlParams.get('search');
    emitter.on(ON_SEARCH, (value) => {
        search.value = value;
    });

    // in case of a larger table row vertical padding observer seem to fail checking for intersection
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            entry.isIntersecting && loadMore();
        })
    }, {
        rootMargin: '-250px 0px 0px 0px'
    })
    observer.observe(loadMoreIntersect.value);
})
</script>

<style scoped>
table#MyFilesTable > tbody > tr > td {
    padding: 0.5rem 1rem;
}
table#MyFilesTable th {
    background-color: rgb(243 244 246 / 1);
    border-bottom-width: 1px;
}

th {
    position: sticky;
    position: -webkit-sticky;
    top: 0;
}
</style>
