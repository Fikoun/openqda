<script setup>
import { ref, computed, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/solid/index.js';
import CategoryNodeItem from './CategoryNodeItem.vue';
import { Categories } from '../../../../resources/js/domain/categories/Categories.js';

const props = defineProps({
  codes: { type: Array, default: () => [] },
  api: { type: Object, default: () => ({}) },
  sources: { type: Array, default: () => [] },
  hasSelections: { type: Boolean, default: false },
  checkedCodes: { type: Map, default: () => new Map() },
  checkedSources: { type: Map, default: () => new Map() },
  menu: { type: [Object, null], default: null },
  showMenu: { type: Boolean, default: false },
});

const pageProps = usePage().props;
const projectId = pageProps.project?.id;

// Local reactive categories list
const categories = ref([...(pageProps.categories ?? [])]);

// Build a lookup: codeId -> code object
const codeMap = computed(() => {
  const map = new Map();
  const walk = (list) => {
    for (const code of list) {
      map.set(code.id, code);
      if (code.children?.length) walk(code.children);
    }
  };
  walk(props.codes ?? []);
  return map;
});

// Build top-level tree: categories that have no parent
const rootCategories = computed(() =>
  categories.value.filter((c) => !c.parent_id)
);

// Uncategorized codes: codes not in any category
const categorizedCodeIds = computed(() => {
  const ids = new Set();
  for (const cat of categories.value) {
    for (const code of cat.codes ?? []) {
      ids.add(code.id);
    }
  }
  return ids;
});

const uncategorizedCodes = computed(() =>
  (props.codes ?? []).filter((c) => !categorizedCodeIds.value.has(c.id))
);

// Track expanded state per category
const expanded = ref(new Set());

const toggleExpand = (id) => {
  if (expanded.value.has(id)) {
    expanded.value.delete(id);
  } else {
    expanded.value.add(id);
  }
};

const expandAll = () => {
  for (const cat of categories.value) {
    expanded.value.add(cat.id);
  }
  expanded.value.add('uncategorized');
};

const collapseAll = () => {
  expanded.value = new Set();
};

// ---- Create new category/theme ----
const showCreateForm = ref(false);
const newName = ref('');
const newType = ref('category');
const creating = ref(false);

const openCreateForm = () => {
  showCreateForm.value = true;
  newName.value = '';
  newType.value = 'category';
  nextTick(() => {
    const input = document.getElementById('tree-new-category-input');
    if (input) input.focus();
  });
};

const cancelCreate = () => {
  showCreateForm.value = false;
};

const submitCreate = async () => {
  const name = newName.value.trim();
  if (!name || creating.value) return;
  creating.value = true;

  const { response, error } = await Categories.create({
    projectId,
    name,
    type: newType.value,
  });

  creating.value = false;

  if (!error && response?.status < 400) {
    categories.value.push(response.data.category);
    showCreateForm.value = false;
    pageProps.categories = [...categories.value];
  }
};

// ---- Rename ----
const renamingCategory = ref(null);
const renameValue = ref('');

const startRename = (cat) => {
  renamingCategory.value = cat.id;
  renameValue.value = cat.name;
  nextTick(() => {
    const input = document.getElementById('tree-rename-input');
    if (input) {
      input.focus();
      input.select();
    }
  });
};

const cancelRename = () => {
  renamingCategory.value = null;
};

const submitRename = async () => {
  const name = renameValue.value.trim();
  if (!name || !renamingCategory.value) return;

  const { response, error } = await Categories.update({
    projectId,
    categoryId: renamingCategory.value,
    data: { name },
  });

  if (!error && response?.status < 400) {
    const idx = categories.value.findIndex(
      (c) => c.id === renamingCategory.value
    );
    if (idx !== -1) {
      categories.value[idx] = {
        ...categories.value[idx],
        ...response.data.category,
      };
    }
    pageProps.categories = [...categories.value];
  }
  renamingCategory.value = null;
};

// ---- Remove ----
const removeCategory = async (cat) => {
  const { error } = await Categories.destroy({
    projectId,
    categoryId: cat.id,
  });
  if (!error) {
    categories.value = categories.value.filter((c) => c.id !== cat.id);
    // Reparent children to root
    categories.value = categories.value.map((c) =>
      c.parent_id === cat.id ? { ...c, parent_id: null } : c
    );
    pageProps.categories = [...categories.value];
  }
};

// ---- Change type ----
const changeType = async ({ categoryId, type }) => {
  const { response, error } = await Categories.update({
    projectId,
    categoryId,
    data: { type },
  });

  if (!error && response?.status < 400) {
    const idx = categories.value.findIndex((c) => c.id === categoryId);
    if (idx !== -1) {
      categories.value[idx] = {
        ...categories.value[idx],
        ...response.data.category,
      };
    }
    pageProps.categories = [...categories.value];
  }
};

// ---- Move to parent (group categories) ----
const moveToParent = async ({ categoryId, parentId }) => {
  const { response, error } = await Categories.update({
    projectId,
    categoryId,
    data: { parent_id: parentId },
  });

  if (!error && response?.status < 400) {
    const idx = categories.value.findIndex((c) => c.id === categoryId);
    if (idx !== -1) {
      categories.value[idx] = {
        ...categories.value[idx],
        parent_id: parentId,
      };
    }
    // Auto-expand the target parent so user sees the result
    if (parentId) expanded.value.add(parentId);
    pageProps.categories = [...categories.value];
  }
};

// ---- Detach code from category ----
const detachCode = async ({ categoryId, codeId }) => {
  const { error } = await Categories.detachCodes({
    projectId,
    categoryId,
    codeIds: [codeId],
  });

  if (!error) {
    const idx = categories.value.findIndex((c) => c.id === categoryId);
    if (idx !== -1) {
      categories.value[idx] = {
        ...categories.value[idx],
        codes: (categories.value[idx].codes ?? []).filter(
          (c) => c.id !== codeId
        ),
      };
    }
    pageProps.categories = [...categories.value];
  }
};
</script>

<template>
  <div class="p-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-foreground">
        Category &amp; Theme Tree
      </h2>
      <div class="flex gap-2">
        <button
          @click="openCreateForm"
          class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-md border border-border hover:bg-foreground/5 text-foreground/60 transition-colors"
        >
          <PlusIcon class="w-3.5 h-3.5" />
          New
        </button>
        <button
          @click="expandAll"
          class="text-xs px-2 py-1 rounded-md border border-border hover:bg-foreground/5 text-foreground/60 transition-colors"
        >
          Expand all
        </button>
        <button
          @click="collapseAll"
          class="text-xs px-2 py-1 rounded-md border border-border hover:bg-foreground/5 text-foreground/60 transition-colors"
        >
          Collapse all
        </button>
      </div>
    </div>

    <!-- Inline create form -->
    <div
      v-if="showCreateForm"
      class="mb-4 border border-border rounded-md p-3 bg-background"
    >
      <div class="flex items-center gap-2 mb-2">
        <input
          id="tree-new-category-input"
          v-model="newName"
          placeholder="Name..."
          class="flex-1 text-sm rounded border border-border bg-surface px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-ring"
          @keydown.enter="submitCreate"
          @keydown.escape="cancelCreate"
        />
        <select
          v-model="newType"
          class="text-sm rounded border border-border bg-surface px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <option value="category">Category</option>
          <option value="theme">Theme</option>
        </select>
      </div>
      <div class="flex justify-end gap-2">
        <button
          @click="cancelCreate"
          class="text-xs px-3 py-1 rounded border border-border hover:bg-foreground/5 text-foreground/60 transition-colors"
        >
          Cancel
        </button>
        <button
          @click="submitCreate"
          :disabled="!newName.trim() || creating"
          class="text-xs px-3 py-1 rounded bg-primary text-primary-foreground hover:bg-primary/80 disabled:opacity-50 transition-colors"
        >
          {{ creating ? 'Creating...' : 'Create' }}
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="categories.length === 0 && uncategorizedCodes.length === 0"
      class="text-sm text-foreground/50 py-8 text-center"
    >
      No categories created yet. Click "New" above to get started.
    </div>

    <!-- Tree -->
    <div v-else class="space-y-1">
      <!-- Recursive category nodes -->
      <template v-for="category in rootCategories" :key="category.id">
        <!-- Inline rename overlay -->
        <div
          v-if="renamingCategory === category.id"
          class="flex items-center gap-2 py-1 px-2"
        >
          <FolderIcon class="w-4 h-4 shrink-0" :style="{ color: category.color || undefined }" />
          <input
            id="tree-rename-input"
            v-model="renameValue"
            class="flex-1 text-sm rounded border border-border bg-surface px-2 py-1 focus:outline-none focus:ring-1 focus:ring-ring"
            @keydown.enter="submitRename"
            @keydown.escape="cancelRename"
          />
          <button
            @click="submitRename"
            class="text-xs px-2 py-1 rounded bg-primary text-primary-foreground hover:bg-primary/80 transition-colors"
          >
            Save
          </button>
          <button
            @click="cancelRename"
            class="p-0.5 hover:bg-foreground/10 rounded"
          >
            <XMarkIcon class="w-3.5 h-3.5 text-foreground/40" />
          </button>
        </div>

        <CategoryNodeItem
          v-else
          :category="category"
          :categories="categories"
          :codeMap="codeMap"
          :expanded="expanded"
          :depth="0"
          @toggle="toggleExpand"
          @rename="startRename"
          @remove="removeCategory"
          @changeType="changeType"
          @moveToParent="moveToParent"
          @detachCode="detachCode"
        />
      </template>

      <!-- Uncategorized codes -->
      <div v-if="uncategorizedCodes.length > 0" class="mt-3">
        <button
          @click="toggleExpand('uncategorized')"
          class="flex items-center gap-1.5 w-full text-left py-1.5 px-2 rounded-md hover:bg-foreground/5 transition-colors"
        >
          <ChevronRightIcon
            class="w-3.5 h-3.5 text-foreground/40 transition-transform shrink-0"
            :class="{ 'rotate-90': expanded.has('uncategorized') }"
          />
          <FolderIcon
            v-if="!expanded.has('uncategorized')"
            class="w-4 h-4 text-foreground/30 shrink-0"
          />
          <FolderOpenIcon
            v-else
            class="w-4 h-4 text-foreground/30 shrink-0"
          />
          <span class="text-sm text-foreground/50 font-medium">
            Uncategorized
          </span>
          <span class="text-xs text-foreground/30 ml-auto">
            {{ uncategorizedCodes.length }} codes
          </span>
        </button>

        <div v-if="expanded.has('uncategorized')" class="ml-6 space-y-0.5">
          <div
            v-for="code in uncategorizedCodes"
            :key="code.id"
            class="flex items-center gap-2 py-1 px-2 rounded-md text-sm"
          >
            <span
              class="w-2.5 h-2.5 rounded-full shrink-0"
              :style="{ backgroundColor: code.color }"
            />
            <span class="truncate" :title="code.name">{{ code.name }}</span>
            <span class="text-xs text-foreground/30 ml-auto shrink-0">
              {{ code.text?.length ?? 0 }} sel.
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
