<template>
    <Modal :show="modelValue" @show="onShow" max-width="sm">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Create New Folder
            </h2>
            <div class="mt-6">
                <InputLabel for="folderName" value="Folder Name" class="sr-only"/>

                <TextInput type="text"
                           ref="folderNameInput"
                           id="folderName"
                           v-model="form.name"
                           @input="validateFolderName"
                           class="mt-1 block w-full"
                           :class="form.errors.name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                           pattern="[a-zA-Z0-9\s!_=\)\(+\-]+"
                           title="Only alphanumeric characters, hyphens, and underscores are allowed"
                           placeholder="enter Folder Name"
                           @keyup.enter="createFolder"
                />
                <InputError class="mt-2" :message="form.errors.name"/>

            </div>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton @click="createFolder" :disabled="form.processing"
                               class="ml-3"
                               :class="{ 'opacity-25': form.processing }">
                    Submit
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import {useForm, usePage} from "@inertiajs/vue3";
import {nextTick, ref} from "vue";

// Refs
const form = useForm({
    name: '',
    parent_id: null
})
const page = usePage();

const folderNameInput = ref(null);

// Props & Emit
const {modelValue} = defineProps({
    modelValue: Boolean
})
const emit = defineEmits(['update:modelValue']);

// Methods
function createFolder() {
    form.parent_id = page.props.folder.id;
    form.post(route('folder.create'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            // show success notification
        },
        onError: () => folderNameInput.value.focus(),
        onFinish: () => form.reset(),
    });
}

function closeModal() {
    emit('update:modelValue');
    form.clearErrors().reset();
}

const onShow = (e) => {
    nextTick(() => folderNameInput.value.focus());
}

function validateFolderName() {
    // Remove invalid characters using a regular expression
    form.name = form.name.replace(/[^a-zA-Z0-9\s!_=\)\(+\-]/g, "");
}

// Hooks
</script>
