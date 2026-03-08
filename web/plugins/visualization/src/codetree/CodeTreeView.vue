<script setup>
import { ref, computed, inject, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
} from '@heroicons/vue/24/solid/index.js';
import CodeTreeCategoryNode from './CodeTreeCategoryNode.vue';
import CodeTreeCodeNode from './CodeTreeCodeNode.vue';

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

const API = inject('api');

const pageProps = usePage().props;
const categories = computed(() => pageProps.categories ?? []);

// Build lookup: codeId -> code object (with selections in .text)
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

// Source lookup for displaying source names
const sourceMap = computed(() => {
  const map = new Map();
  for (const s of props.sources ?? []) {
    map.set(s.id, s);
  }
  return map;
});

// Root categories (no parent)
const rootCategories = computed(() =>
  categories.value.filter((c) => !c.parent_id)
);

// Uncategorized codes
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
  (props.codes ?? []).filter(
    (c) =>
      !categorizedCodeIds.value.has(c.id) &&
      props.checkedCodes.get(c.id)
  )
);

// Expanded state
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
  // Also expand all codes
  for (const code of props.codes ?? []) {
    if (props.checkedCodes.get(code.id)) {
      expanded.value.add(`code-${code.id}`);
    }
  }
  expanded.value.add('uncategorized');
};

const collapseAll = () => {
  expanded.value = new Set();
};

// Options
const options = ref({
  showEmpty: false,
});

// Get filtered selections for a code (only from checked sources)
const getSelectionsForCode = (code) => {
  if (!code?.text) return [];
  return code.text.filter((sel) => props.checkedSources.get(sel.source_id));
};
</script>

<template>
  <div>
    <component
      :is="props.menu"
      title="Code Tree Options"
      :show="props.showMenu"
      @close="API.setShowMenu(false)"
    >
      <ul class="p-4 flex flex-col gap-4">
        <li class="flex justify-between items-center">
          <label class="text-left text-xs font-medium uppercase">
            Show Codes with no Selections
          </label>
          <input type="checkbox" v-model="options.showEmpty" />
        </li>
      </ul>
    </component>

    <div class="p-4">
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-foreground">
          Category &amp; Code Tree
        </h2>
        <div class="flex gap-2">
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

      <!-- Empty state -->
      <div
        v-if="categories.length === 0 && uncategorizedCodes.length === 0"
        class="text-sm text-foreground/50 py-8 text-center"
      >
        No categories or checked codes available.
      </div>

      <!-- Tree -->
      <div v-else class="space-y-1">
        <!-- Recursive category nodes -->
        <CodeTreeCategoryNode
          v-for="category in rootCategories"
          :key="category.id"
          :category="category"
          :categories="categories"
          :codeMap="codeMap"
          :sourceMap="sourceMap"
          :expanded="expanded"
          :checkedCodes="checkedCodes"
          :checkedSources="checkedSources"
          :showEmpty="options.showEmpty"
          :depth="0"
          @toggle="toggleExpand"
        />

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

          <div v-if="expanded.has('uncategorized')" class="ml-5 space-y-0.5">
            <CodeTreeCodeNode
              v-for="code in uncategorizedCodes"
              :key="code.id"
              :code="code"
              :sourceMap="sourceMap"
              :expanded="expanded"
              :checkedSources="checkedSources"
              :showEmpty="options.showEmpty"
              @toggle="toggleExpand"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
