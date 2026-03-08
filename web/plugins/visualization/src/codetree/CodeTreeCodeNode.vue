<script setup>
import { computed, inject } from 'vue';
import {
  ChevronRightIcon,
  CodeBracketIcon,
} from '@heroicons/vue/24/solid/index.js';

const props = defineProps({
  code: { type: Object, required: true },
  sourceMap: { type: Map, required: true },
  expanded: { type: Set, required: true },
  checkedSources: { type: Map, required: true },
  showEmpty: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle']);

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
</script>

<template>
  <div v-if="isVisible">
    <!-- Code header row -->
    <button
      class="flex items-center gap-2 w-full text-left py-1.5 px-2 rounded-md hover:bg-foreground/5 transition-colors"
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
