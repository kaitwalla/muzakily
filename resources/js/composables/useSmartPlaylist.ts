import { ref, computed, type Ref, type ComputedRef } from 'vue';
import type {
    SmartPlaylistRule,
    SmartPlaylistRuleGroup,
    SmartPlaylistFieldValue,
    SmartPlaylistOperatorValue,
    RuleLogic,
} from '@/config/smartPlaylist';
import { getFieldType, getOperatorsForFieldType } from '@/config/smartPlaylist';

export interface UseSmartPlaylistReturn {
    groups: Ref<SmartPlaylistRuleGroup[]>;
    isValid: ComputedRef<boolean>;
    addGroup: () => void;
    removeGroup: (groupId: number) => void;
    setGroupLogic: (groupId: number, logic: RuleLogic) => void;
    addRule: (groupId: number) => void;
    removeRule: (groupId: number, ruleId: string) => void;
    updateRule: (groupId: number, ruleId: string, updates: Partial<SmartPlaylistRule>) => void;
    getDefaultOperatorForField: (field: SmartPlaylistFieldValue) => SmartPlaylistOperatorValue;
    getRulesPayload: () => SmartPlaylistRuleGroup[];
    loadRules: (rules: SmartPlaylistRuleGroup[]) => void;
    clearRules: () => void;
}

function generateRuleId(): string {
    return Math.random().toString(36).substring(2, 11);
}

/**
 * Composable for managing smart playlist rule building.
 */
export function useSmartPlaylist(): UseSmartPlaylistReturn {
    // Keep groupIdCounter per-instance to avoid state leaking between components
    let groupIdCounter = 1;

    function createDefaultRule(): SmartPlaylistRule {
        return {
            id: generateRuleId(),
            field: 'title',
            operator: 'contains',
            value: '',
        };
    }

    function createDefaultGroup(): SmartPlaylistRuleGroup {
        return {
            id: groupIdCounter++,
            logic: 'and',
            rules: [createDefaultRule()],
        };
    }

    const groups = ref<SmartPlaylistRuleGroup[]>([createDefaultGroup()]);

    const isValid = computed(() => {
        if (groups.value.length === 0) return false;

        return groups.value.every((group) => {
            if (group.rules.length === 0) return false;

            return group.rules.every((rule) => {
                if (!rule.field || !rule.operator) return false;
                if (Array.isArray(rule.value)) {
                    return (
                        rule.value.length === 2 &&
                        rule.value[0] !== undefined &&
                        rule.value[0] !== '' &&
                        rule.value[1] !== undefined &&
                        rule.value[1] !== ''
                    );
                }
                return rule.value !== '' && rule.value !== undefined;
            });
        });
    });

    function addGroup(): void {
        groups.value.push(createDefaultGroup());
    }

    function removeGroup(groupId: number): void {
        const index = groups.value.findIndex((g) => g.id === groupId);
        if (index !== -1 && groups.value.length > 1) {
            groups.value.splice(index, 1);
        }
    }

    function setGroupLogic(groupId: number, logic: RuleLogic): void {
        const group = groups.value.find((g) => g.id === groupId);
        if (group) {
            group.logic = logic;
        }
    }

    function addRule(groupId: number): void {
        const group = groups.value.find((g) => g.id === groupId);
        if (group) {
            group.rules.push(createDefaultRule());
        }
    }

    function removeRule(groupId: number, ruleId: string): void {
        const group = groups.value.find((g) => g.id === groupId);
        if (group && group.rules.length > 1) {
            const index = group.rules.findIndex((r) => r.id === ruleId);
            if (index !== -1) {
                group.rules.splice(index, 1);
            }
        }
    }

    function updateRule(groupId: number, ruleId: string, updates: Partial<SmartPlaylistRule>): void {
        const group = groups.value.find((g) => g.id === groupId);
        if (!group) return;

        const rule = group.rules.find((r) => r.id === ruleId);
        if (!rule) return;

        // If field changed, reset operator to a valid one for the new field type
        if (updates.field && updates.field !== rule.field) {
            const newFieldType = getFieldType(updates.field);
            const validOperators = getOperatorsForFieldType(newFieldType);
            const currentOperatorValid = validOperators.some((op) => op.value === rule.operator);

            if (!currentOperatorValid && validOperators.length > 0) {
                updates.operator = validOperators[0].value;
            }

            // Reset value when field changes
            updates.value = '';
        }

        Object.assign(rule, updates);
    }

    function getDefaultOperatorForField(field: SmartPlaylistFieldValue): SmartPlaylistOperatorValue {
        const fieldType = getFieldType(field);
        const operators = getOperatorsForFieldType(fieldType);
        return operators[0]?.value ?? 'is';
    }

    function getRulesPayload(): SmartPlaylistRuleGroup[] {
        return groups.value.map((group) => ({
            id: group.id,
            logic: group.logic,
            rules: group.rules.map((rule) => ({
                id: rule.id,
                field: rule.field,
                operator: rule.operator,
                value: rule.value,
            })),
        }));
    }

    function loadRules(rules: SmartPlaylistRuleGroup[]): void {
        if (rules.length === 0) {
            groups.value = [createDefaultGroup()];
            return;
        }

        // Update groupIdCounter to avoid conflicts
        const maxId = Math.max(...rules.map((g) => g.id), 0);
        groupIdCounter = maxId + 1;

        groups.value = rules.map((group) => ({
            id: group.id,
            logic: group.logic,
            rules: group.rules.map((rule) => ({
                id: rule.id || generateRuleId(),
                field: rule.field,
                operator: rule.operator,
                value: rule.value,
            })),
        }));
    }

    function clearRules(): void {
        groupIdCounter = 1;
        groups.value = [createDefaultGroup()];
    }

    return {
        groups,
        isValid,
        addGroup,
        removeGroup,
        setGroupLogic,
        addRule,
        removeRule,
        updateRule,
        getDefaultOperatorForField,
        getRulesPayload,
        loadRules,
        clearRules,
    };
}
