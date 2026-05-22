import type { ConfigParam } from '../types';

/**
 * Client-side mirror of the backend ConfigValueValidator. Returns a short
 * reason token (used to build the soft-revert toast) or null when the value is
 * acceptable. The backend re-validates on save regardless.
 */
export function validateValue(param: ConfigParam, value: string): string | null {
    const config = param.config;

    switch (param.display_type) {
        case 'number':
        case 'slider': {
            if (value.trim() === '' || !Number.isFinite(Number(value))) {
                return 'number';
            }
            const numeric = Number(value);
            if (config.min !== undefined && numeric < config.min) {
                return 'min';
            }
            if (config.max !== undefined && numeric > config.max) {
                return 'max';
            }
            if (!config.float && value.includes('.')) {
                return 'integer';
            }

            return null;
        }
        case 'select': {
            const values = (config.options ?? []).map((option) => option.value);

            return values.length === 0 || values.includes(value) ? null : 'option';
        }
        case 'multiselect': {
            const separator = config.separator && config.separator !== '' ? config.separator : ',';
            const allowed = (config.options ?? []).map((option) => option.value);
            if (allowed.length === 0) {
                return null;
            }
            for (const item of value.split(separator).map((part) => part.trim()).filter((part) => part !== '')) {
                if (!allowed.includes(item)) {
                    return 'option';
                }
            }

            return null;
        }
        case 'boolean': {
            const trueValue = config.true_value ?? 'true';
            const falseValue = config.false_value ?? 'false';

            return value === trueValue || value === falseValue ? null : 'boolean';
        }
        case 'text': {
            if (config.max_length !== undefined && value.length > config.max_length) {
                return 'length';
            }
            if (config.regex !== undefined && config.regex !== '') {
                try {
                    if (!new RegExp(config.regex).test(value)) {
                        return 'format';
                    }
                } catch {
                    return null;
                }
            }

            return null;
        }
        case 'textarea':
            return config.max_length !== undefined && value.length > config.max_length ? 'length' : null;
        case 'color':
            return /^#?[0-9a-fA-F]{6}$/.test(value) ? null : 'color';
        default:
            return null;
    }
}
