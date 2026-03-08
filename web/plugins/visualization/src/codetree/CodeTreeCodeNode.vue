<script setup>
import { ref, computed, inject, watch, onBeforeUnmount } from 'vue';
import {
  ChevronRightIcon,
  CodeBracketIcon,
  EllipsisVerticalIcon,
  FolderIcon,
  XMarkIcon,
} from '@heroicons/vue/24/solid/index.js';

const props = defineProps({
  code: { type: Object, required: true },
  sourceMap: { type: Map, required: true },
  expanded: { type: Set, required: true },
  checkedSources: { type: Map, required: true },
  showEmpty: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  categoryId: { type: [String, null], default: null },
});

const emit = defineEmits(['toggle', 'addToCategory', 'detachCode']);

const API = inject('api');

const codeExpandKey = computed(() => `code-${props.code.id}`);
const isExpanded = computed(() => props.expanded.has(codeExpandKey.value));

// Selections filtered by checked sources
const selections = computed(() => {
  if (!props.code?.text) return [];
  return props.code.text.filter((sel) => props.checkedSources.get(sel.source_id));
});

const hasSelections = computed(() => selections.value.length > 0);
const isVisible = computed(() => hasSelections.value || props.showEmpty);

const getSourceName = (sourceId) => {
  return props.sourceMap.get(sourceId)?.name ?? 'Unknown source';
};

// Context menu
const menuOpen = ref(false);
const menuRef = ref(null);

const toggleMenu = (e) => {
  e.stopPropagation();
  menuOpen.value = !menuOpen.value;
  showCategoryPicker.value = false;
};

const closeMenu = () => {
  menuOpen.value = false;
  showCategoryPicker.value = false;
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

// Category picker
const showCategoryPicker = ref(false);
const categorySearch = ref('');

const availableCategories = computed(() => {
  const query = categorySearch.value.toLowerCase().trim();
  return (props.categories ?? []).filter((c) => {
    if (!query) return true;
    return c.name.toLowerCase().includes(query);
  });
});

const handleAddToCategory = (categoryId) => {
  emit('addToCategory', { codeId: props.code.id, categoryId });
  closeMenu();
  categorySearch.value = '';
};

const handleDetach = () => {
  if (props.categoryId) {
    emit('detachCode', { codeId: props.code.id, categoryId: props.categoryId });
  }
  closeMenu();
};
</script>

<template>
  <div v-if="isVisible">
    <!-- Code header row -->
    <div class="group flex items-center gap-2 w-full py-1.5 px-2 rounded-md hover:bg-foreground/5 transition-colors">
      <button
        class="flex items-center gap-2 flex-1 min-w-0 text-left"
        @click="$emit('toggle', codeExpandKey)"
      >
        <ChevronRightIcon
          v-if="hasSelections"
          class="w-3.5 h-3.5 text-foreground/40 transition-transform shrink-0"
          :class="{ 'rotate-90': isExpanded }"
        />
        <span v-else class="w-3.5 h-3.5 shrink-0" />

        <span class="text-sm truncate" :title="code.name">
          {{ code.name }}
        </span>

        <span class="text-xs text-foreground/30 ml-auto shrink-0">
          {{ selections.length }} sel.
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
            @click="showCategoryPicker = !showCategoryPicker"
            class="flex items-center gap-2 w-full text-left px-3 py-1.5 text-sm hover:bg-foreground/5 transition-colors"
          >
            <FolderIcon class="w-3.5 h-3.5 text-foreground/50" />
            Add to category...
          </button>
          <button
            v-if="categoryId"
            @click="handleDetach"
            class="flex items-center gap-2 w-full text-left px-3 py-1.5 text-sm text-destructive hover:bg-destructive/5 transition-colors"
          >
            <XMarkIcon class="w-3.5 h-3.5" />
            Remove from category
          </button>

          <!-- Inline category picker -->
          <div v-if="showCategoryPicker" class="border-t border-border mt-1 pt-1 px-2 pb-2">
            <input
              v-model="categorySearch"
              placeholder="Search categories..."
              class="w-full text-xs rounded border border-border bg-surface px-2 py-1 mb-1.5 focus:outline-none focus:ring-1 focus:ring-ring"
              @click.stop
            />
            <div class="max-h-32 overflow-y-auto space-y-0.5">
              <button
                v-for="cat in availableCategories"
                :key="cat.id"
                @click.stop="handleAddToCategory(cat.id)"
                class="w-full text-left text-xs px-2 py-1 rounded hover:bg-foreground/5 flex items-center gap-1.5"
              >
                <FolderIcon class="w-3 h-3 shrink-0" :style="{ color: cat.color || undefined }" />
                <span class="truncate">{{ cat.name }}</span>
                <span class="text-[10px] text-foreground/30 ml-auto">{{ cat.type }}</span>
              </button>
              <div
                v-if="availableCategories.length === 0"
                class="text-xs text-foreground/40 text-center py-2"
              >
                No categories found
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Selections (expanded) -->
    <div v-if="isExpanded && hasSelections" class="ml-5 space-y-1 mb-2">
      <div
        v-for="(selection, idx) in selections"
        :key="`${selection.source_id}-${selection.start}-${idx}`"
        class="border-l-2 ml-2 pl-3 py-1.5"
        :style="{ borderColor: code.color }"
      >
        <div class="flex items-center justify-between text-xs text-foreground/50 mb-1">
          <span class="flex items-center gap-1.5">
            <CodeBracketIcon class="w-3 h-3" />
            <span class="font-medium">{{ getSourceName(selection.source_id) }}</span>
            <span class="text-foreground/30">&middot;</span>
            <span>{{ selection.start }}&ndash;{{ selection.end }}</span>
          </span>
          <span class="flex items-center gap-1 shrink-0 ml-2">
            <span>{{ new Date(selection.updatedAt).toLocaleDateString() }}</span>
            <template v-if="API.getMemberBy">
              <span class="text-foreground/30">&middot;</span>
              <span>{{ API.getMemberBy(selection.createdBy)?.name }}</span>
            </template>
          </span>
        </div>
        <p class="text-sm text-foreground/80 leading-relaxed">
          {{ selection.text }}
        </p>
      </div>

      <div
        v-if="!hasSelections"
        class="ml-2 py-1 text-xs text-foreground/40 italic"
      >
        No selections in checked sources
      </div>
    </div>
  </div>
</template>
