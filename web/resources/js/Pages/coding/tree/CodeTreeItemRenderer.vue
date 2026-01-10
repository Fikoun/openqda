<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { cn } from '../../../utils/css/cn';
import { useCodeTree } from './useCodeTree';
import { useCodes } from '../../../domain/codes/useCodes';
import {
  ArrowPathIcon,
  BarsArrowDownIcon,
  ChevronRightIcon,
  EllipsisVerticalIcon,
  EyeIcon,
  EyeSlashIcon,
  PencilIcon,
  PlusIcon,
} from '@heroicons/vue/24/solid';
import Button from '../../../Components/interactive/Button.vue';
import { TrashIcon } from '@heroicons/vue/24/outline';
import DropdownLink from '../../../Components/DropdownLink.vue';
import Dropdown from '../../../Components/Dropdown.vue';
import { changeOpacity } from '../../../utils/color/changeOpacity';
import { useUsers } from '../../../domain/teams/useUsers';
import SelectionList from './SelectionList.vue';
import { Collapse } from 'vue-collapsed';
import { asyncTimeout } from '../../../utils/asyncTimeout';
import { attemptAsync } from '../../../Components/notification/attemptAsync';
import { useDeleteDialog } from '../../../dialogs/useDeleteDialog';
import { useRenameDialog } from '../../../dialogs/useRenameDialog';
import ContrastText from '../../../Components/text/ContrastText.vue';
import { useRange } from '../useRange';
import { rgbToHex } from '../../../utils/color/toHex';
import { useSelections } from '../selections/useSelections';
import FormDialog from '../../../dialogs/FormDialog.vue';

const { createCode, toggleCode, createCodeSchema, getCodebook, addCodeToParent } = useCodes();
const { collapsed, toggleCollapse } = useCodeTree();
const { getMemberBy } = useUsers();
const Selections = useSelections();

const props = defineProps({
  code: Object,
  class: String,
  sorting: Boolean,
  showDetails: Boolean,
});

//------------------------------------------------------------------------
// Collapse
//------------------------------------------------------------------------
const open = computed(() => props.sorting || collapsed.value[props.code.id]);
const toggle = () => {
  const newState = toggleCollapse(props.code.id);
  // if collapse closed then also close
  // text segments
  if (!newState) {
    closeTexts();
  }
};

//------------------------------------------------------------------------
// TEXTS (SELECTIONS)
//------------------------------------------------------------------------
const showTexts = ref(false);
const textCount = ref(0);
const textSelectionsCount = (code) => {
  let count = code.text?.length ?? 0;
  if (code.children?.length) {
    code.children.forEach((child) => {
      count += textSelectionsCount(child);
    });
  }

  return count;
};
textCount.value = computed(() => {
  return textSelectionsCount(props.code);
});
const openTexts = () => {
  showTexts.value = true;
};
const closeTexts = () => {
  showTexts.value = false;
};
const sortedTexts = computed(() => {
  if (!props.code.text?.length) return [];
  return props.code.text
    .toSorted((a, b) => a.start - b.start)
    .map((txt) => {
      txt.user = getMemberBy(txt.createdBy);
      return txt;
    });
});

//------------------------------------------------------------------------
// VISIBILITY
//------------------------------------------------------------------------
const toggling = reactive({});
const handleCodeToggle = async (code) => {
  toggling[code.id] = true;
  await asyncTimeout(100);
  await attemptAsync(() => toggleCode(code));
  toggling[code.id] = false;
};

//------------------------------------------------------------------------
// DIALOGS
//------------------------------------------------------------------------
const { open: openDeleteDialog } = useDeleteDialog();
const { open: openRenameDialog } = useRenameDialog();
const editCode = (target) => {
  const schema = createCodeSchema({
    title: target.name,
    description: target.description,
    color: rgbToHex(target.color),
  });
  schema.id = {
    type: String,
    label: null,
    formType: 'hidden',
    defaultValue: target.id,
  };
  delete schema.codebookId;
  openRenameDialog({ id: 'edit-code', target, schema });
};

// CRATE SUBCODE
const createNewCodeSchema = ref();
const openCreateSubcodeDialog = (parent) => {
  const schema = createCodeSchema({
    codebooks: [getCodebook(parent.codebook)],
    codes: [parent],
    parent,
  });
  schema.color.defaultValue = rgbToHex(parent.color);
  createNewCodeSchema.value = schema;
};

// CHANGE PARENT
const changeParentSchema = ref();
const openChangeParentDialog = (code) => {
  // Flatten all codes from the same codebook except the code itself and its children
  const flattenCodes = (codeArray, excludeIds = new Set()) => {
    const result = [];
    codeArray.forEach(c => {
      if (!excludeIds.has(c.id) && c.codebook === code.codebook) {
        result.push(c);
        if (c.children && c.children.length > 0) {
          result.push(...flattenCodes(c.children, excludeIds));
        }
      }
    });
    return result;
  };
  
  // Get all descendant IDs to exclude
  const getDescendantIds = (c) => {
    const ids = new Set([c.id]);
    if (c.children && c.children.length > 0) {
      c.children.forEach(child => {
        getDescendantIds(child).forEach(id => ids.add(id));
      });
    }
    return ids;
  };
  
  const excludeIds = getDescendantIds(code);
  const codebook = getCodebook(code.codebook);
  
  // Get all codes from store and filter
  const { codes } = useCodes();
  const availableCodes = flattenCodes(codes.value, excludeIds);
  
  const schema = {
    parentId: {
      type: String,
      optional: true,
      label: 'Parent code',
      defaultValue: code.parent?.id ?? '',
      options: [
        { value: '', label: '-- No parent (make root code) --' },
        ...availableCodes.map((c) => ({
          value: c.id,
          label: c.name,
        }))
      ],
    },
  };
  
  changeParentSchema.value = schema;
};

const handleChangeParent = async (formData) => {
  const parentId = formData.parentId || null;
  await attemptAsync(() => addCodeToParent({ 
    codeId: props.code.id, 
    parentId 
  }));
  changeParentSchema.value = null;
  
  // Force a re-render by triggering parent component update
  // The code tree will re-render when it detects changes to the parent/children structure
  return { response: { status: 200 } };
};

//------------------------------------------------------------------------
// Range
//------------------------------------------------------------------------
const { range } = useRange();
</script>

<template>
  <div class="w-full">
    <div class="flex items-center w-auto">
      <!-- collapse button -->
      <Button
        v-if="code.children?.length"
        :title="open ? 'Hide children' : 'Show children'"
        variant="default"
        size="sm"
        :disabled="sorting"
        class="bg-transparent text-foreground! hover:text-background w-4 p-0! rounded"
        @click="toggle()"
      >
        <ChevronRightIcon
          :class="
            cn(
              'w-4 h-4 transition-all duration-300 transform',
              open && 'rotate-90'
            )
          "
        />
      </Button>
      <span class="w-4 h-4" v-else></span>

      <!-- code name -->
      <div
        :class="
          cn(
            'w-full tracking-wide rounded-md px-2 py-1 text-sm text-foreground dark:text-background group hover:shadow-sm',
            sorting && 'cursor-grab'
          )
        "
        :style="`background: ${changeOpacity(code.color ?? 'rgba(0,0,0,1)', 1)};`"
      >
        <button
          v-if="!sorting && range?.length"
          @click.prevent="Selections.select({ code })"
          :title="`Assign ${code.name} to selection ${range.start}:${range.end}`"
          :class="
            cn(
              'w-full h-full text-left flex',
              code.active
                ? 'hover:font-semibold'
                : 'cursor-not-allowed text-foreground/20'
            )
          "
        >
          <ContrastText class="line-clamp-1 grow">{{ code.name }}</ContrastText>
          <ContrastText
            class="text-xs ms-auto font-normal hidden group-hover:inline"
            >Assign to {{ range.start }}:{{ range.end }}</ContrastText
          >
        </button>
        <ContrastText v-else class="line-clamp-1 grow items-center"
          >{{ code.name }}
        </ContrastText>
        <ContrastText
          v-if="props.showDetails && code.description"
          class="text-xs block"
          >{{ code.description }}</ContrastText
        >
      </div>

      <div class="flex justify-between items-center gap-2">
        <!-- show texts -->
        <Button
          :title="showTexts ? 'Hide selections list' : 'Show selections list'"
          variant="ghost"
          size="sm"
          :class="
            cn(
              'px-1! py-1! my-0! w-8 text-xs hover:text-secondary',
              showTexts && 'text-secondary'
            )
          "
          :disabled="!code.text?.length"
          @click.prevent="showTexts ? closeTexts() : openTexts()"
        >
          <BarsArrowDownIcon class="w-4 -h-4" />
          <span class="text-xs">{{
            open ? (code.text?.length ?? 0) : textCount
          }}</span>
        </Button>

        <!-- visibility -->
        <button
          class="p-0 m-0 text-foreground/80"
          @click.prevent="handleCodeToggle(code)"
          :title="
            code.active
              ? 'Code visible, click to hide'
              : 'Code hidden, click to show'
          "
        >
          <ArrowPathIcon
            v-if="toggling[code.id]"
            class="w-4 h-4 animate-spin text-foreground/50"
          />
          <EyeSlashIcon
            v-else-if="code.active === false"
            class="w-4 h-4 text-foreground/50"
          />
          <EyeIcon v-else class="w-4 h-4" />
        </button>

        <!-- code menu -->
        <Dropdown :disabled="sorting">
          <template #trigger>
            <button
              :disabled="sorting"
              :class="
                cn(
                  'p-2 md:p-1 lg:p-0 m-0',
                  sorting && 'cursor-not-allowed text-foreground/50'
                )
              "
            >
              <EllipsisVerticalIcon class="w-4 h-4" />
            </button>
          </template>
          <template #content>
            <DropdownLink as="button" @click.prevent="editCode(code)">
              <div class="flex items-center">
                <PencilIcon class="w-4 h-4 me-2" />
                <span>Edit code</span>
              </div>
            </DropdownLink>
            <DropdownLink as="button">
              <FormDialog
                :schema="createNewCodeSchema"
                :title="`Create a subcode for ${code.name}`"
                :submit="createCode"
              >
                <template #trigger="{ trigger }">
                  <div
                    @click="trigger(() => openCreateSubcodeDialog(code))"
                    class="flex items-center"
                  >
                    <PlusIcon class="w-4 h-4 me-2" />
                    <span>Add subcode</span>
                  </div>
                </template>
              </FormDialog>
            </DropdownLink>
            <DropdownLink as="button">
              <FormDialog
                :schema="changeParentSchema"
                :title="`Change parent of ${code.name}`"
                button-title="Update parent"
                :submit="handleChangeParent"
              >
                <template #trigger="{ trigger }">
                  <div
                    @click="trigger(() => openChangeParentDialog(code))"
                    class="flex items-center"
                  >
                    <BarsArrowDownIcon class="w-4 h-4 me-2" />
                    <span>Change parent</span>
                  </div>
                </template>
              </FormDialog>
            </DropdownLink>
            <DropdownLink
              as="button"
              @click.prevent="
                openDeleteDialog({
                  target: code,
                  challenge: 'name',
                  message:
                    'This will also delete ALL selections in ALL sources within this project that are related to this code!',
                })
              "
            >
              <div class="flex">
                <TrashIcon class="w-4 h-4 me-2 text-destructive" />
                <span>Delete this code</span>
              </div>
            </DropdownLink>
          </template>
        </Dropdown>
      </div>
    </div>

    <!-- TEXT Selections -->
    <Collapse :when="code && textCount && showTexts">
      <div
        :style="`border-color: ${changeOpacity(code.color ?? 'rgba(0,0,0,1)', 1)};`"
        class="bg-surface border text-sm ms-4 me-16 my-1 rounded"
      >
        <SelectionList
          :texts="sortedTexts"
          :color="code.color ?? 'rgba(0,0,0,1)'"
        />
      </div>
    </Collapse>
  </div>
</template>

<style scoped></style>
