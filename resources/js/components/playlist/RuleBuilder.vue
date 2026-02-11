<script setup lang="ts">
import { computed } from 'vue';
import type {
    SmartPlaylistRule,
    SmartPlaylistFieldValue,
    SmartPlaylistOperatorValue,
} from '@/config/smartPlaylist';
import {
    smartPlaylistFields,
    getFieldType,
    getOperatorsForFieldType,
    operatorRequiresRange,
    operatorIsDateRange,
} from '@/config/smartPlaylist';

interface Props {
    rule: SmartPlaylistRule;
    canRemove?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canRemove: true,
});

const emit = defineEmits<{
    update: [updates: Partial<SmartPlaylistRule>];
    remove: [];
}>();

const fieldType = computed(() => getFieldType(props.rule.field));

const availableOperators = computed(() => {
    return getOperatorsForFieldType(fieldType.value);
});

const showRangeInputs = computed(() => {
    return operatorRequiresRange(props.rule.operator);
});

const isDateRangeOperator = computed(() => {
    return operatorIsDateRange(props.rule.operator);
});

const rangeValue = computed(() => {
    if (Array.isArray(props.rule.value)) {
        return props.rule.value;
    }
    return ['', ''];
});

function handleFieldChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    emit('update', { field: target.value as SmartPlaylistFieldValue });
}

function handleOperatorChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const operator = target.value as SmartPlaylistOperatorValue;

    // Reset value if switching to/from range operator
    if (operatorRequiresRange(operator) !== showRangeInputs.value) {
        emit('update', {
            operator,
            value: operatorRequiresRange(operator) ? ['', ''] : '',
        });
    } else {
        emit('update', { operator });
    }
}

function handleValueChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update', { value: target.value });
}

function handleRangeValueChange(index: 0 | 1, event: Event): void {
    const target = event.target as HTMLInputElement;
    const newRange = [...rangeValue.value] as [string | number, string | number];
    newRange[index] = fieldType.value === 'number' ? Number(target.value) : target.value;
    emit('update', { value: newRange });
}

function getInputType(): string {
    switch (fieldType.value) {
        case 'number':
            return 'number';
        case 'date':
            return isDateRangeOperator.value ? 'number' : 'date';
        default:
            return 'text';
    }
}

function getPlaceholder(): string {
    if (isDateRangeOperator.value) {
        return 'days';
    }
    switch (fieldType.value) {
        case 'number':
            return 'Enter a number';
        case 'date':
            return 'Select a date';
        default:
            return 'Enter a value';
    }
}
</script>

<template>
    <div class="flex items-center gap-2 flex-wrap">
        <!-- Field selector -->
        <select
            :value="rule.field"
            @change="handleFieldChange"
            class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-green-500"
        >
            <option
                v-for="field in smartPlaylistFields"
                :key="field.value"
                :value="field.value"
            >
                {{ field.label }}
            </option>
        </select>

        <!-- Operator selector -->
        <select
            :value="rule.operator"
            @change="handleOperatorChange"
            class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-green-500"
        >
            <option
                v-for="operator in availableOperators"
                :key="operator.value"
                :value="operator.value"
            >
                {{ operator.label }}
            </option>
        </select>

        <!-- Value input(s) -->
        <template v-if="showRangeInputs">
            <!-- Range inputs -->
            <input
                :type="getInputType()"
                :value="rangeValue[0]"
                @input="handleRangeValueChange(0, $event)"
                :placeholder="getPlaceholder()"
                class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-green-500 w-24"
            />
            <span class="text-gray-400 text-sm">and</span>
            <input
                :type="getInputType()"
                :value="rangeValue[1]"
                @input="handleRangeValueChange(1, $event)"
                :placeholder="getPlaceholder()"
                class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-green-500 w-24"
            />
        </template>

        <template v-else>
            <!-- Single value input -->
            <input
                :type="getInputType()"
                :value="rule.value"
                @input="handleValueChange"
                :placeholder="getPlaceholder()"
                class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-green-500 flex-1 min-w-32"
            />
            <span v-if="isDateRangeOperator" class="text-gray-400 text-sm">days</span>
        </template>

        <!-- Remove button -->
        <button
            v-if="canRemove"
            @click="emit('remove')"
            class="p-2 text-gray-500 hover:text-red-400 transition-colors"
            aria-label="Remove rule"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
