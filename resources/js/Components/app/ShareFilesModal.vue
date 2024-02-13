<template>
    <Modal :show="props.modelValue" @show="onShow" @close="closeModal" max-width="sm">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Share Files
            </h2>
            <div class="mt-6">
                <InputLabel for="shareEmail" value="Enter Email Address" class="sr-only"/>

                <TextInput type="email"
                           ref="emailInput"
                           id="shareEmail"
                           v-model="form.email"
                           :class="form.errors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                           class="mt-1 block w-full"
                           title="enter one ore more user emails separated with comma"
                           placeholder="enter email address"
                           @keyup.enter="share"
                />
                <InputError class="mt-2" :message="form.errors.email"/>

            </div>
            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton class="ml-3"
                               :class="{ 'opacity-25': form.processing }"
                               @click="share" :disabled="form.processing">
                    Submit
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>

<script setup>
// Imports
import Modal from "@/Components/Modal.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import InputError from "@/Components/InputError.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import {useForm, usePage} from "@inertiajs/vue3";
import {nextTick, ref} from "vue";
import {showSuccessNotification} from "@/event-bus.js";

// Uses
const form = useForm({
    email: '',
    all: false,
    ids: [],
    parent_id: null,
})
const page = usePage();

// Refs
const emailInput = ref(null);

// Props & Emit
const props = defineProps({
    modelValue: {type: Boolean, default: false},
    allSelected: Boolean,
    selectedIds: Array
})
const emit = defineEmits(['update:modelValue']);

// Methods
function onShow() {
    nextTick(() => emailInput.value.focus());
}

function share() {
    form.parent_id = page.props.folder.id;
    if (props.allSelected) {
        form.all = true;
        form.ids = [];
    } else {
        form.all = false;
        form.ids = props.selectedIds;
    }
    form.post(route('file.share'), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccessNotification(`Selected files will be shared to "${form.email}" if this email exists in the system`);
            closeModal();
        },
        onError: () => emailInput.value.focus(),
        //onFinish: () => form.reset(),
    });
}

function closeModal() {
    emit('update:modelValue');
    form.clearErrors()
    form.reset();
}

// Hooks

</script>
