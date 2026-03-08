<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
  EllipsisVerticalIcon,
  PencilIcon,
  TrashIcon,
  XMarkIcon,
} from '@heroicons/vue/24/solid/index.js';
import CodeTreeCodeNode from './CodeTreeCodeNode.vue';

const props = defineProps({
  category: { type: Object, required: true },
  categories: { type: Array, required: true },
  codeMap: { type: Map, required: true },
  sourceMap: { type: Map, required: true },
  expanded: { type: Set, required: true },
  checkedCodes: { type: Map, required: true },
  checkedSources: { type: Map, required: true },
  showEmpty: { type: Boolean, default: false },
  depth: { type: Number, default: 0 },
});

const emit = defineEmits([
  'toggle',
  'rename',
  'remove',
  'changeType',
  'addToCategory',
  'detachCode',
]);

const childCategories = computed(() =>
  props.categories.filter((c) => c.parent_id === props.category.id)
);

// Only show checked codes
const codes = computed(() =>
  (props.category.codes ?? [])
    .map((c) => props.codeMap.get(c.id))
    .filter((c) => c && props.checkedCodes.get(c.id))
);

const hasChildren = computed(
  () => childCategories.value.length > 0 || codes.value.length > 0
);

const isExpanded = computed(() => props.expanded.has(props.category.id));

const totalCodes = (cat) => {
  let count = (cat.codes ?? []).filter((c) => props.checkedCodes.get(c.id)).length;
  for (const child of props.categories.filter((c) => c.parent_id === cat.id)) {
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

const handleRename = () => {
  closeMenu();
  emit('rename', props.category);
};

const handleChangeType = () => {
  closeMenu();
  const newType = props.category.type === 'theme' ? 'category' : 'theme';
  emit('changeType', { categoryId: props.category.id, type: newType });
};

const handleRemove = () => {
  closeMenu();
  emit('remove', props.category);
};
</script>

<template>
  <div>
    <!-- Category header row -->
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

      <!-- Three-dot menu -->
      <div class="relative shrink-0">
        <button
          @click="toggleMenu"
          class="p-0.5 rounded hover:bg-foreground/10 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
          title="Actions"
        >
          <EllipsisVerticalIcon class="w-4 h-4 text-foreground/50" />
        </button>

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

    <!-- Children (expanded) -->
    <div v-if="isExpanded" class="ml-5 space-y-0.5">
      <!-- Child categories (recursive) -->
      <CodeTreeCategoryNode
        v-for="child in childCategories"
        :key="child.id"
        :category="child"
        :categories="categories"
        :codeMap="codeMap"
        :sourceMap="sourceMap"
        :expanded="expanded"
        :checkedCodes="checkedCodes"
        :checkedSources="checkedSources"
        :showEmpty="showEmpty"
        :depth="depth + 1"
        @toggle="(id) => $emit('toggle', id)"
        @rename="(cat) => $emit('rename', cat)"
        @remove="(cat) => $emit('remove', cat)"
        @changeType="(payload) => $emit('changeType', payload)"
        @addToCategory="(payload) => $emit('addToCategory', payload)"
        @detachCode="(payload) => $emit('detachCode', payload)"
      />

      <!-- Codes in this category -->
      <CodeTreeCodeNode
        v-for="code in codes"
        :key="code.id"
        :code="code"
        :sourceMap="sourceMap"
        :expanded="expanded"
        :checkedSources="checkedSources"
        :showEmpty="showEmpty"
        :categories="categories"
        :categoryId="category.id"
        @toggle="(id) => $emit('toggle', id)"
        @addToCategory="(payload) => $emit('addToCategory', payload)"
        @detachCode="(payload) => $emit('detachCode', payload)"
      />
    </div>
  </div>
</template>
