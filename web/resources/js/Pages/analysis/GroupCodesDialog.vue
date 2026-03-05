<script setup>
import { ref, computed } from 'vue';
import DialogBase from '../../dialogs/DialogBase.vue';
import Button from '../../Components/interactive/Button.vue';
import InputField from '../../form/InputField.vue';
import ActionMessage from '../../Components/ActionMessage.vue';
import ContrastText from '../../Components/text/ContrastText.vue';

/**
 * GroupCodesDialog - allows users to select or create a category
 * and group selected codes into it.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  selectedCodeIds: { type: Array, default: () => [] },
  codes: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  submit: { type: Function, required: true },
});

const emit = defineEmits(['close', 'created']);

const mode = ref('create'); // 'create' or 'existing'
const newCategoryName = ref('');
const newCategoryDescription = ref('');
const newCategoryType = ref('category');
const newCategoryColor = ref(generateDarkColor());
const selectedCategoryId = ref(null);
const categorySearch = ref('');
const submitting = ref(false);
const error = ref(null);
const complete = ref(false);

/**
 * Generate a random dark-ish hex color that is visible on a light UI.
 */
function generateDarkColor() {
  const c = () => Math.floor(Math.random() * 120 + 40); // 40–159
  const hex = (n) => n.toString(16).padStart(2, '0');
  return `#${hex(c())}${hex(c())}${hex(c())}`;
}

const filteredCategories = computed(() => {
  const query = categorySearch.value.toLowerCase().trim();
  if (!query) return props.categories;
  return props.categories.filter((c) =>
    c.name.toLowerCase().includes(query)
  );
});

const selectedCodes = computed(() => {
  return props.codes.filter((c) => props.selectedCodeIds.includes(c.id));
});

const canSubmit = computed(() => {
  if (mode.value === 'create') {
    return newCategoryName.value.trim().length > 0;
  }
  return selectedCategoryId.value !== null;
});

const handleSubmit = async () => {
  if (!canSubmit.value) return;
  error.value = null;
  complete.value = false;
  submitting.value = true;

  try {
    const payload = {
      mode: mode.value,
      codeIds: props.selectedCodeIds,
    };

    if (mode.value === 'create') {
      payload.name = newCategoryName.value.trim();
      payload.description = newCategoryDescription.value.trim() || null;
      payload.type = newCategoryType.value;
      payload.color = newCategoryColor.value;
    } else {
      payload.categoryId = selectedCategoryId.value;
    }

    await props.submit(payload);
    complete.value = true;

    setTimeout(() => {
      resetAndClose();
      emit('created');
    }, 300);
  } catch (e) {
    error.value = e.message ?? 'An error occurred';
  } finally {
    submitting.value = false;
  }
};

const resetAndClose = () => {
  newCategoryName.value = '';
  newCategoryDescription.value = '';
  newCategoryType.value = 'category';
  newCategoryColor.value = generateDarkColor();
  selectedCategoryId.value = null;
  categorySearch.value = '';
  mode.value = 'create';
  error.value = null;
  complete.value = false;
  submitting.value = false;
  emit('close');
};

const cancel = () => {
  resetAndClose();
};

const keyDownHandler = (e) => {
  if (e.key === 'Escape') {
    cancel();
  }
};
</script>

<template>
  <DialogBase
    title="Group Codes into Category"
    :show="show"
    :show-close-button="true"
    @close="cancel"
    @keydown="keyDownHandler"
  >
    <template #body>
      <div class="space-y-4 w-full min-w-[24rem]">
        <!-- Show selected codes -->
        <div>
          <p class="text-sm font-medium text-foreground/70 mb-2">
            Selected codes ({{ selectedCodes.length }}):
          </p>
          <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
            <span
              v-for="code in selectedCodes"
              :key="code.id"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs"
              :style="{ backgroundColor: code.color }"
            >
              <ContrastText>{{ code.name }}</ContrastText>
            </span>
          </div>
        </div>

        <!-- Mode selector -->
        <div class="flex gap-2 border-b border-border pb-2">
          <button
            @click="mode = 'create'"
            class="px-3 py-1.5 text-sm rounded-md transition-colors"
            :class="
              mode === 'create'
                ? 'bg-primary text-primary-foreground'
                : 'text-foreground/60 hover:text-foreground'
            "
          >
            Create new
          </button>
          <button
            @click="mode = 'existing'"
            class="px-3 py-1.5 text-sm rounded-md transition-colors"
            :class="
              mode === 'existing'
                ? 'bg-primary text-primary-foreground'
                : 'text-foreground/60 hover:text-foreground'
            "
          >
            Add to existing
          </button>
        </div>

        <!-- Create new category -->
        <div v-if="mode === 'create'" class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-foreground/70 mb-1">
              Name
            </label>
            <InputField
              v-model="newCategoryName"
              placeholder="Category name"
              class="w-full"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-foreground/70 mb-1">
              Description (optional)
            </label>
            <textarea
              v-model="newCategoryDescription"
              placeholder="Category description..."
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              rows="2"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-foreground/70 mb-1">
              Type
            </label>
            <select
              v-model="newCategoryType"
              class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="category">Category</option>
              <option value="theme">Theme</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-foreground/70 mb-1">
              Color
            </label>
            <div class="flex items-center gap-3">
              <input
                type="color"
                v-model="newCategoryColor"
                class="w-10 h-10 rounded border border-input cursor-pointer p-0.5"
              />
              <span class="text-sm text-foreground/50">{{ newCategoryColor }}</span>
              <button
                type="button"
                @click="newCategoryColor = generateDarkColor()"
                class="text-xs px-2 py-1 rounded border border-border hover:bg-foreground/5 text-foreground/60 transition-colors"
              >
                Randomize
              </button>
            </div>
          </div>
        </div>

        <!-- Select existing category -->
        <div v-else class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-foreground/70 mb-1">
              Search categories
            </label>
            <InputField
              v-model="categorySearch"
              placeholder="Search by name..."
              class="w-full"
            />
          </div>
          <div
            v-if="filteredCategories.length === 0"
            class="text-sm text-foreground/50 py-4 text-center"
          >
            No categories found. Create one first.
          </div>
          <div
            v-else
            class="max-h-48 overflow-y-auto space-y-1 border border-border rounded-md p-2"
          >
            <button
              v-for="category in filteredCategories"
              :key="category.id"
              @click="selectedCategoryId = category.id"
              class="w-full text-left px-3 py-2 rounded-md text-sm transition-colors flex items-center justify-between"
              :class="
                selectedCategoryId === category.id
                  ? 'bg-primary/10 border border-primary'
                  : 'hover:bg-foreground/5 border border-transparent'
              "
            >
              <div>
                <span class="font-medium">{{ category.name }}</span>
                <span class="ml-2 text-xs text-foreground/50">
                  {{ category.type }}
                </span>
              </div>
              <span class="text-xs text-foreground/40">
                {{ category.codes?.length ?? 0 }} codes
              </span>
            </button>
          </div>
        </div>
      </div>
    </template>
    <template #footer>
      <div class="flex justify-between items-center w-full">
        <Button variant="outline" @click="cancel">Cancel</Button>
        <span class="grow text-right mx-1">
          <ActionMessage
            v-if="!complete && !error"
            :on="submitting"
            class="text-secondary"
          >
            Saving
          </ActionMessage>
          <ActionMessage :on="complete" class="text-secondary">
            Saved
          </ActionMessage>
          <ActionMessage :on="!!error" class="text-destructive">
            {{ error }}
          </ActionMessage>
        </span>
        <Button @click="handleSubmit" :disabled="!canSubmit || submitting">
          {{ mode === 'create' ? 'Create & Group' : 'Add to Category' }}
        </Button>
      </div>
    </template>
  </DialogBase>
</template>

<style scoped></style>
