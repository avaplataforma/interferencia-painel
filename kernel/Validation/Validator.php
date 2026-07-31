<?php

declare(strict_types=1);

namespace Interferencia\Kernel\Validation;

use InvalidArgumentException;

final class Validator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>|string> $rules
     * @param array<string, string> $labels
     */
    public function validate(array $input, array $rules, array $labels = []): ValidationResult
    {
        $values = [];
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $input[$field] ?? null;
            $label = $labels[$field] ?? $field;
            $nullable = in_array('nullable', $fieldRules, true);

            if ($nullable && ($value === null || $value === '')) {
                $values[$field] = null;
                continue;
            }

            foreach ($fieldRules as $ruleDefinition) {
                if (!is_string($ruleDefinition)) {
                    throw new InvalidArgumentException(sprintf('Regra inválida para o campo %s.', $field));
                }

                [$rule, $parameter] = array_pad(explode(':', $ruleDefinition, 2), 2, null);
                $message = $this->check($rule, $parameter, $value, $field, $label, $input);

                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }

            $values[$field] = $value;
        }

        return new ValidationResult($values, $errors);
    }

    /** @param array<string, mixed> $input */
    private function check(
        string $rule,
        ?string $parameter,
        mixed $value,
        string $field,
        string $label,
        array $input,
    ): ?string {
        return match ($rule) {
            'nullable' => null,
            'required' => $this->isEmpty($value) ? sprintf('O campo %s é obrigatório.', $label) : null,
            'string' => $value !== null && !is_string($value) ? sprintf('O campo %s deve ser um texto.', $label) : null,
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? sprintf('O campo %s deve conter um e-mail válido.', $label) : null,
            'min' => $this->lengthViolation($value, $parameter, true, $label),
            'max' => $this->lengthViolation($value, $parameter, false, $label),
            'confirmed' => $value !== ($input[$field . '_confirmation'] ?? null)
                ? sprintf('A confirmação de %s não confere.', $label) : null,
            'in' => !in_array((string) $value, explode(',', (string) $parameter), true)
                ? sprintf('O valor informado para %s é inválido.', $label) : null,
            default => throw new InvalidArgumentException(sprintf('Regra de validação desconhecida: %s', $rule)),
        };
    }

    private function lengthViolation(mixed $value, ?string $parameter, bool $minimum, string $label): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if ($parameter === null || preg_match('/^[0-9]+$/', $parameter) !== 1) {
            throw new InvalidArgumentException('Parâmetro de tamanho inválido.');
        }

        $limit = (int) $parameter;
        $violates = $minimum ? mb_strlen($value) < $limit : mb_strlen($value) > $limit;

        if (!$violates) {
            return null;
        }

        return $minimum
            ? sprintf('O campo %s deve ter pelo menos %d caracteres.', $label, $limit)
            : sprintf('O campo %s deve ter no máximo %d caracteres.', $label, $limit);
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
