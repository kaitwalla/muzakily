<script setup lang="ts">
import { watch } from 'vue';
import { useSmartPlaylist } from '@/composables';
import RuleBuilder from './RuleBuilder.vue';
import type { SmartPlaylistRuleGroup, SmartFolder, RuleLogic } from '@/config/smartPlaylist';

interface Props {
    initialRules?: SmartPlaylistRuleGroup[];
    smartFolders?: SmartFolder[];
}

const props = withDefaults(defineProps<Props>(), {
    initialRules: () => [],
    smartFolders: () => [],
});

const emit = defineEmits<{
    'update:rules': [rules: SmartPlaylistRuleGroup[]];
    save: [rules: SmartPlaylistRuleGroup[]];
    cancel: [];
}>();

const {
    groups,
    isValid,
    addGroup,
    removeGroup,
    setGroupLogic,
    addRule,
    removeRule,
    updateRule,
    getRulesPayload,
    loadRules,
    clearRules,
} = useSmartPlaylist();

// Load initial rules if provided
if (props.initialRules.length > 0) {
    loadRules(props.initialRules);
}

// Emit updates when rules change
watch(
    groups,
    () => {
        emit('update:rules', getRulesPayload());
    },
    { deep: true }
);

function handleSave(): void {
    if (isValid.value) {
        emit('save', getRulesPayload());
    }
}

function handleClear(): void {
    clearRules();
}

function handleLogicChange(groupId: number, event: Event): void {
    const target = event.target as HTMLSelectElement;
    setGroupLogic(groupId, target.value as RuleLogic);
}
</script>

<template>
    <div class="space-y-6">
        <!-- Rule groups -->
        <div
            v-for="(group, groupIndex) in groups"
            :key="group.id"
            class="bg-gray-800/50 rounded-lg p-4"
        >
            <!-- Group header -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-400">Match</span>
                    <select
                        :value="group.logic"
                        @change="handleLogicChange(group.id, $event)"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-3 py-1 text-white text-sm focus:outline-none focus:border-green-500"
                    >
                        <option value="and">all</option>
                        <option value="or">any</option>
                    </select>
                    <span class="text-sm text-gray-400">of the following rules</span>
                </div>

                <button
                    v-if="groups.length > 1"
                    @click="removeGroup(group.id)"
                    class="text-sm text-gray-500 hover:text-red-400 transition-colors"
                >
                    Remove group
                </button>
            </div>

            <!-- Rules in this group -->
            <div class="space-y-3">
                <RuleBuilder
                    v-for="rule in group.rules"
                    :key="rule.id"
                    :rule="rule"
                    :can-remove="group.rules.length > 1"
                    :smart-folders="smartFolders"
                    @update="updateRule(group.id, rule.id, $event)"
                    @remove="removeRule(group.id, rule.id)"
                />
            </div>

            <!-- Add rule button -->
            <button
                @click="addRule(group.id)"
                class="mt-3 flex items-center gap-2 text-sm text-gray-400 hover:text-white transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add rule
            </button>

            <!-- OR separator between groups -->
            <div
                v-if="groupIndex < groups.length - 1"
                class="flex items-center gap-4 mt-4 -mb-6"
            >
                <div class="flex-1 h-px bg-gray-700" />
                <span class="text-sm text-gray-500 font-medium">OR</span>
                <div class="flex-1 h-px bg-gray-700" />
            </div>
        </div>

        <!-- Add group button -->
        <button
            @click="addGroup"
            class="w-full py-3 border-2 border-dashed border-gray-700 rounded-lg text-gray-400 hover:text-white hover:border-gray-600 transition-colors flex items-center justify-center gap-2"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add rule group
        </button>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-700">
            <div class="flex items-center gap-2">
                <button
                    @click="handleClear"
                    class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                >
                    Clear all
                </button>
            </div>

            <div class="flex items-center gap-3">
                <button
                    @click="emit('cancel')"
                    class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="handleSave"
                    :disabled="!isValid"
                    class="px-6 py-2 bg-green-500 hover:bg-green-400 disabled:bg-green-500/50 disabled:cursor-not-allowed text-black font-semibold rounded-lg transition-colors"
                >
                    Save
                </button>
            </div>
        </div>

        <!-- Validation message -->
        <p v-if="!isValid" class="text-sm text-yellow-500">
            Please fill in all rule values before saving.
        </p>
    </div>
</template>
