<template>
    <AuthenticatedLayout>
        <nav class="flex items-center justify-end p-1 mb-2">
            <div>
                <DeleteForeverButton :all-selected="allSelected" :selected-ids="selectedIds" @delete="resetForm"/>
                <RestoreFilesButton :all-selected="allSelected" :selected-ids="selectedIds" @restore="resetForm"/>
            </div>
        </nav>
        <div class="flex-1 overflow-auto">
            <table class="min-w-full relative" id="MyTrashTable">
                <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left w-[25px] max-w-[25px]">
                        <Checkbox @change="onSelectAllChange" v-model:checked="allSelected" class="focus:ring-0 cursor-pointer"/>
                    </th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Name</th>
                    <th class="text-sm font-medium text-gray-900 py-4 px-4 text-left">Path</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="file of allFiles.data" :key="file.id"
                    @click="toggleFileSelect(file)"
                    class="border-b transition duration-300 ease-in-out hover:bg-blue-100 cursor-pointer"
                    :class="(selected[file.id] || allSelected) ? 'bg-blue-50' : 'bg-white'"
                >
                    <td class="text-sm font-medium text-gray-900 w-[25px] max-w-[25px]">
                        <Checkbox @change="onSelectCheckboxChange(file)" v-model="selected[file.id]"
                                  :checked="selected[file.id] || allSelected" class="focus:ring-0 cursor-pointer"/>
                    </td>
                    <td class="text-sm font-medium text-gray-900 flex items-center">
                        <FileIcon :file="file"/>
                        {{ file.name }}
                    </td>
                    <td class="text-sm font-medium text-gray-900">
                        {{ file.path }}
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
import RestoreFilesButton from "@/Components/app/RestoreFilesButton.vue";
import FileIcon from "@/Components/app/FileIcon.vue";
import Checkbox from "@/Components/Checkbox.vue";
import {computed, onMounted, onUpdated, ref} from "vue";
import {httpGet} from "@/Helper/http-helper.js";
import DeleteForeverButton from "@/Components/app/DeleteForeverButton.vue";

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

// Computed
const selectedIds = computed(() => Object.entries(selected.value).filter(a => a[1]).map(a => a[0]));

// Methods
function loadMore() {
    if (allFiles.value.next === null) {
        return;
    }
    let urlParams = new URLSearchParams(window.location.search);
    let extraUrlParams = urlParams.toString();
    if (extraUrlParams) {
        extraUrlParams = "&" + extraUrlParams;
    }
    httpGet(allFiles.value.next + extraUrlParams)
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

function resetForm() {
    allSelected.value = false;
    selected.value = {};
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
table#MyTrashTable > tbody > tr > td {
    padding: 0.5rem 1rem;
}

table#MyTrashTable th {
    background-color: rgb(243 244 246 / 1);
    border-bottom-width: 1px;
}

th {
    position: sticky;
    position: -webkit-sticky;
    top: 0;
}
</style>
