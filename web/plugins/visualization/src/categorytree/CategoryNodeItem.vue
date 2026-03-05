<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
  EllipsisVerticalIcon,
  PencilIcon,
  TrashIcon,
  ArrowRightIcon,
  XMarkIcon,
} from '@heroicons/vue/24/solid/index.js';

const props = defineProps({
  category: { type: Object, required: true },
  categories: { type: Array, required: true },
  codeMap: { type: Map, required: true },
  expanded: { type: Set, required: true },
  depth: { type: Number, default: 0 },
});

const emit = defineEmits([
  'toggle',
  'rename',
  'remove',
  'changeType',
  'moveToParent',
  'detachCode',
]);

const childCategories = computed(() =>
  props.categories.filter((c) => c.parent_id === props.category.id)
);

const codes = computed(() =>
  (props.category.codes ?? [])
    .map((c) => props.codeMap.get(c.id))
    .filter(Boolean)
);

const hasChildren = computed(
  () => childCategories.value.length > 0 || codes.value.length > 0
);

const isExpanded = computed(() => props.expanded.has(props.category.id));

const totalCodes = (cat) => {
  let count = cat.codes?.length ?? 0;
  for (const child of props.categories.filter(
    (c) => c.parent_id === cat.id
  )) {
    count += totalCodes(child);
  }
  return count;
};

const total = computed(() => totalCodes(props.category));

const typeLabel = computed(() =>
  props.category.type === 'theme' ? 'Theme' : 'Category'
);

const typeBadgeClass = computed(() =>
  props.category.type === 'theme'
    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
    : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
);

// Context menu
const menuOpen = ref(false);
const menuRef = ref(null);
const toggleMenu = (e) => {
  e.stopPropagation();
  menuOpen.value = !menuOpen.value;
};
const closeMenu = () => {
  menuOpen.value = false;
};

// Close menu on outside click via document listener (no overlay needed)
const onDocumentClick = (e) => {
  if (menuRef.value && !menuRef.value.contains(e.target)) {
    closeMenu();
  }
};

watch(menuOpen, (open) => {
  if (open) {
    document.addEventListener('pointerdown', onDocumentClick, true);
  } else {
    document.removeEventListener('pointerdown', onDocumentClick, true);
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocumentClick, true);
});

// Move-to-parent picker
const showMovePicker = ref(false);
const moveSearch = ref('');

const availableParents = computed(() => {
  const self = props.category.id;
  // Exclude self and own descendants
  const descendants = new Set();
  const collectDescendants = (id) => {
    descendants.add(id);
    for (const c of props.categories.filter((c) => c.parent_id === id)) {
      collectDescendants(c.id);
    }
  };
  collectDescendants(self);

  const query = moveSearch.value.toLowerCase().trim();
  return props.categories.filter((c) => {
    if (descendants.has(c.id)) return false;
    if (!query) return true;
    return c.name.toLowerCase().includes(query);
  });
});

const doMove = (parentId) => {
  emit('moveToParent', { categoryId: props.category.id, parentId });
  showMovePicker.value = false;
  moveSearch.value = '';
};

const doMoveToRoot = () => {
  emit('moveToParent', { categoryId: props.category.id, parentId: null });
  showMovePicker.value = false;
  moveSearch.value = '';
};

const handleRename = () => {
  closeMenu();
  emit('rename', props.category);
};

const handleRemove = () => {
  closeMenu();
  emit('remove', props.category);
};

const handleChangeType = () => {
  closeMenu();
  const newType =
    props.category.type === 'theme' ? 'category' : 'theme';
  emit('changeType', { categoryId: props.category.id, type: newType });
};

const handleMoveToParent = () => {
  closeMenu();
  showMovePicker.value = true;
};

const handleDetachCode = (codeId) => {
  emit('detachCode', {
    categoryId: props.category.id,
    codeId,
  });
};
</script>

<template>
  <div>
    <!-- Header row -->
    <div class="group flex items-center gap-1.5 w-full py-1.5 px-2 rounded-md hover:bg-foreground/5 transition-colors">
      <button
        class="flex items-center gap-1.5 flex-1 min-w-0 text-left"
        @click="$emit('toggle', category.id)"
      >
        <ChevronRightIcon
          v-if="hasChildren"
          class="w-3.5 h-3.5 text-foreground/40 transition-transform shrink-0"
          :class="{ 'rotate-90': isExpanded }"
        />
        <span v-else class="w-3.5 h-3.5 shrink-0" />

        <FolderOpenIcon
          v-if="isExpanded && hasChildren"
          class="w-4 h-4 shrink-0"
          :style="{ color: category.color || undefined }"
        />
        <FolderIcon
          v-else
          class="w-4 h-4 shrink-0"
          :style="{ color: category.color || undefined }"
        />

        <span class="text-sm font-medium truncate" :title="category.name">
          {{ category.name }}
        </span>

        <span
          class="text-[10px] px-1.5 py-0.5 rounded-full font-medium shrink-0"
          :class="typeBadgeClass"
        >
          {{ typeLabel }}
        </span>

        <span class="text-xs text-foreground/30 ml-auto shrink-0">
          {{ total }} codes
        </span>
      </button>

      <!-- Actions menu trigger -->
      <div class="relative shrink-0">
        <button
          @click="toggleMenu"
          class="p-0.5 rounded hover:bg-foreground/10 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
          title="Actions"
        >
          <EllipsisVerticalIcon class="w-4 h-4 text-foreground/50" />
        </button>

        <!-- Dropdown menu -->
        <div
          v-if="menuOpen"
          ref="menuRef"
          class="absolute right-0 top-full mt-1 z-50 w-48 bg-background border border-border rounded-md shadow-lg py-1"
        >
          <button
            @click="handleRename"
            class="flex items-center gap-2 w-full text-left px-3 py-1.5 text-sm hover:bg-foreground/5 transition-colors"
          >
            <PencilIcon class="w-3.5 h-3.5 text-foreground/50" />
            Rename
          </button>
          <button
            @click="handleChangeType"
            class="flex items-center gap-2 w-full text-left px-3 py-1.5 text-sm hover:bg-foreground/5 transition-colors"
          >
            <FolderIcon class="w-3.5 h-3.5 text-foreground/50" />
            Switch to {{ category.type === 'theme' ? 'Category' : 'Theme' }}
          </button>
          <button
            @click="handleMoveToParent"
            class="flex items-center gap-2 w-full text-left px-3 py-1.5 text-sm hover:bg-foreground/5 transition-colors"
          >
            <ArrowRightIcon class="w-3.5 h-3.5 text-foreground/50" />
            Move under...
          </button>
          <hr class="my-1 border-border" />
          <button
            @click="handleRemove"
            class="flex items-center gap-2 w-full text-left px-3 py-1.5 text-sm text-destructive hover:bg-destructive/5 transition-colors"
          >
            <TrashIcon class="w-3.5 h-3.5" />
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Move-to-parent picker -->
    <div
      v-if="showMovePicker"
      class="ml-8 my-1 border border-border rounded-md p-2 bg-background shadow-sm"
    >
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-foreground/60">Move under...</span>
        <button
          @click="showMovePicker = false"
          class="p-0.5 hover:bg-foreground/10 rounded"
        >
          <XMarkIcon class="w-3.5 h-3.5 text-foreground/40" />
        </button>
      </div>
      <input
        v-model="moveSearch"
        placeholder="Search categories..."
        class="w-full text-xs rounded border border-border bg-surface px-2 py-1 mb-2 focus:outline-none focus:ring-1 focus:ring-ring"
      />
      <button
        v-if="category.parent_id"
        @click="doMoveToRoot"
        class="w-full text-left text-xs px-2 py-1 rounded hover:bg-foreground/5 text-foreground/60 italic"
      >
        Move to root level
      </button>
      <div class="max-h-32 overflow-y-auto space-y-0.5">
        <button
          v-for="target in availableParents"
          :key="target.id"
          @click="doMove(target.id)"
          class="w-full text-left text-xs px-2 py-1 rounded hover:bg-foreground/5 flex items-center gap-1.5"
        >
          <FolderIcon class="w-3 h-3 shrink-0" :style="{ color: target.color || undefined }" />
          <span class="truncate">{{ target.name }}</span>
          <span class="text-[10px] text-foreground/30 ml-auto">{{ target.type }}</span>
        </button>
        <div
          v-if="availableParents.length === 0"
          class="text-xs text-foreground/40 text-center py-2"
        >
          No available targets
        </div>
      </div>
    </div>

    <!-- Children (expanded) -->
    <div v-if="isExpanded" class="ml-5 space-y-0.5">
      <!-- Child categories (recursive) -->
      <CategoryNodeItem
        v-for="child in childCategories"
        :key="child.id"
        :category="child"
        :categories="categories"
        :codeMap="codeMap"
        :expanded="expanded"
        :depth="depth + 1"
        @toggle="(id) => $emit('toggle', id)"
        @rename="(cat) => $emit('rename', cat)"
        @remove="(cat) => $emit('remove', cat)"
        @changeType="(payload) => $emit('changeType', payload)"
        @moveToParent="(payload) => $emit('moveToParent', payload)"
        @detachCode="(payload) => $emit('detachCode', payload)"
      />

      <!-- Codes in this category -->
      <div
        v-for="code in codes"
        :key="code.id"
        class="group/code flex items-center gap-2 py-1 px-2 rounded-md text-sm hover:bg-foreground/5"
      >
        <span
          class="w-2.5 h-2.5 rounded-full shrink-0"
          :style="{ backgroundColor: code.color }"
        />
        <span class="truncate" :title="code.name">{{ code.name }}</span>
        <span class="text-xs text-foreground/30 ml-auto shrink-0">
          {{ code.text?.length ?? 0 }} sel.
        </span>
        <button
          @click="handleDetachCode(code.id)"
          class="p-0.5 rounded hover:bg-foreground/10 opacity-0 group-hover/code:opacity-100 transition-opacity shrink-0"
          title="Remove from this category"
        >
          <XMarkIcon class="w-3 h-3 text-foreground/40" />
        </button>
      </div>
    </div>
  </div>
</template>
