<?php

namespace App\Services\Forms;

use Illuminate\Support\Arr;

class FormCatalog
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return (array) config('forms.catalog', []);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $formKey): ?array
    {
        $key = $this->normalizeKey($formKey);
        $definition = Arr::get($this->all(), $key);

        return is_array($definition) ? $definition : null;
    }

    public function exists(string $formKey): bool
    {
        return $this->get($formKey) !== null;
    }

    public function expectedContext(string $formKey): ?string
    {
        $definition = $this->get($formKey);
        $context = $definition['source_context'] ?? null;

        return is_string($context) && $context !== '' ? $context : null;
    }

    public function title(string $formKey): string
    {
        $definition = $this->get($formKey);
        $title = $definition['title'] ?? 'Contact';

        return is_string($title) && trim($title) !== '' ? $title : 'Contact';
    }

    public function submitLabel(string $formKey): string
    {
        $definition = $this->get($formKey);
        $label = $definition['submit_label'] ?? 'Submit';

        return is_string($label) && trim($label) !== '' ? $label : 'Submit';
    }

    public function normalizeKey(string $formKey): string
    {
        $key = strtolower(trim($formKey));

        return preg_match('/^[a-z0-9_]+$/', $key) === 1 ? $key : '';
    }
}
