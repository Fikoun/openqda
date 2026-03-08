<script setup>
import { computed } from 'vue';
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
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

const emit = defineEmits(['toggle']);

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
</script>

<template>
  <div>
    <!-- Category header row -->
    <button
      class="flex items-center gap-1.5 w-full text-left py-1.5 px-2 rounded-md hover:bg-foreground/5 transition-colors"
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
        @toggle="(id) => $emit('toggle', id)"
      />
    </div>
  </div>
</template>
