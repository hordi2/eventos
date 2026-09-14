import InputLabel from '../InputLabel';
import Select from '../Select';
import TextInput from '../TextInput';
import { OPERATORS, RULE_ACTIONS, TYPES_WITH_OPTIONS, withOptionValues } from './logic';
import { type FieldData, type RuleCondition, type RuleData } from './types';

interface RuleEditorProps {
    rule: RuleData;
    sources: FieldData[];
    onChange: (rule: RuleData) => void;
}

export default function RuleEditor({ rule, sources, onChange }: RuleEditorProps) {
    const source = sources.find((field) => field.key === rule.condition.field_key) ?? null;
    const needsValue = rule.condition.operator !== 'is_empty' && rule.condition.operator !== 'is_not_empty';

    let choices: { value: string; label: string }[] | null = null;

    if (source?.type === 'yes_no') {
        choices = [
            { value: '1', label: 'Oui' },
            { value: '0', label: 'Non' },
        ];
    } else if (source && TYPES_WITH_OPTIONS.includes(source.type)) {
        choices = withOptionValues(source.options).map((option) => ({ value: option.value, label: option.label }));
    }

    function setCondition(patch: Partial<RuleCondition>) {
        onChange({ ...rule, condition: { ...rule.condition, ...patch } });
    }

    return (
        <div className="mt-3 space-y-4 rounded-control bg-bg-alt p-4">
            <div>
                <InputLabel htmlFor="rule_action">Action</InputLabel>
                <Select id="rule_action" value={rule.action} onChange={(event) => onChange({ ...rule, action: event.target.value })}>
                    {RULE_ACTIONS.map((action) => (
                        <option key={action.value} value={action.value}>
                            {action.label}
                        </option>
                    ))}
                </Select>
            </div>
            <div>
                <InputLabel htmlFor="rule_source">Si la réponse à</InputLabel>
                <Select id="rule_source" value={rule.condition.field_key} onChange={(event) => setCondition({ field_key: event.target.value, value: '' })}>
                    {sources.map((field) => (
                        <option key={field.key} value={field.key}>
                            {field.label || field.key}
                        </option>
                    ))}
                </Select>
            </div>
            <div>
                <InputLabel htmlFor="rule_operator">Condition</InputLabel>
                <Select id="rule_operator" value={rule.condition.operator} onChange={(event) => setCondition({ operator: event.target.value })}>
                    {OPERATORS.map((operator) => (
                        <option key={operator.value} value={operator.value}>
                            {operator.label}
                        </option>
                    ))}
                </Select>
            </div>
            {needsValue && (
                <div>
                    <InputLabel htmlFor="rule_value">Valeur</InputLabel>
                    {choices ? (
                        <Select id="rule_value" value={rule.condition.value} onChange={(event) => setCondition({ value: event.target.value })}>
                            <option value="">Choisissez…</option>
                            {choices.map((choice) => (
                                <option key={choice.value} value={choice.value}>
                                    {choice.label}
                                </option>
                            ))}
                        </Select>
                    ) : (
                        <TextInput id="rule_value" type="text" value={rule.condition.value} onChange={(event) => setCondition({ value: event.target.value })} />
                    )}
                </div>
            )}
        </div>
    );
}
