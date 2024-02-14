<template>
    <div class="w-[600px] h-[80px] flex items-center">
        <TextInput type="text" class="block w-full mr-2"
                   v-model="search"
                   ref="searchInput"
                   @keyup.enter.prevent="onSearch"
                   autocomplete
                   placeholder="Search for files and folders"
        />
    </div>
</template>

<script setup>
//Imports
import TextInput from "@/Components/TextInput.vue";
import {router, useForm} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";

// Refs
const search = ref('');
const searchInput = ref(null);
let params = null;

// Methods
function onSearch() {
    if (search.value) {
        params.set('search', search.value)
    } else {
        params.delete('search');
    }
    searchInput.value.focus();
    router.get(window.location.pathname, params, { preserveState: true });
}

// Hooks
onMounted(() => {
    params = new URLSearchParams(window.location.search);
    search.value = params.get('search') ?? '';
})

</script>
