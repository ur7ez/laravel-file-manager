<template>
    <PrimaryButton @click="download" title="click to download selected item(s)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
             class="w-4 h-4 mr-1">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
        </svg>
        Download
    </PrimaryButton>
</template>

<script setup>
// Imports
import PrimaryButton from "@/Components/PrimaryButton.vue";
import {showErrorDialog, showErrorNotification, showSuccessNotification} from "@/event-bus.js";
import {usePage} from "@inertiajs/vue3";
import {httpGet} from "@/Helper/http-helper.js";

// Uses
const page = usePage();

// Refs

// Props & Emit
const props = defineProps({
    all: {
        type: Boolean,
        required: false,
        default: false,
    },
    ids: {
        type: Array,
        required: false,
    },
    sharedWithMe: false,
    sharedByMe: false,
});

// Computed

// Methods
function download() {
    if (!props.all && props.ids.length === 0) {
        showErrorDialog('Please select at least one item to download', 'Download error');
        return;
    }
    const p = new URLSearchParams();
    const existingParams = new URLSearchParams(window.location.search);
    if (page.props.folder?.id) {
        p.append('parent_id', page.props.folder.id);
    }

    if (props.all && !(existingParams.get('search') || existingParams.get('favourites'))) {
        p.append('all', props.all ? 1 : 0);
    } else {
        for (let id of props.ids) {
            p.append('ids[]', id);
        }
    }

    let url = route('file.download');  // download from My Files page only
    if (props.sharedWithMe) {
        url = route('file.downloadSharedWithMe');
    } else if (props.sharedByMe) {
        url = route('file.downloadSharedByMe');
    }
    httpGet(url + '?' + p.toString())
        .then(res => {
            if (!res.url || !res.filename) {
                if (res.message) {
                    showErrorNotification(res.message);
                }
                return;
            }
            if (res.filesAdded) {
                showSuccessNotification(`${res.filesAdded} file(s) to be downloaded`);
            }
            const a = document.createElement('a');
            a.download = res.filename;
            a.href = res.url;
            a.click();
        });

}

// Hooks

</script>
