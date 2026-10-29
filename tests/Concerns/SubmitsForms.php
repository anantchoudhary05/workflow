<?php

namespace Tests\Concerns;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Testing\TestResponse;

/**
 * Drives real HTML forms in feature tests: renders the page, reads the form's own fields
 * (hidden inputs, defaults, selected options), applies the values a user would type and submits it.
 * Every value the test "types" must exist as a field in the rendered form, so a renamed or
 * missing input fails the test instead of silently being ignored.
 */
trait SubmitsForms
{
    /**
     * @param  array<string, mixed>  $values  field name => value (use "members[]" => [1, 2] for multi-selects)
     */
    protected function submitForm(string $pageUrl, string $action, array $values = []): TestResponse
    {
        $page = $this->get($pageUrl)->assertOk();
        $form = $this->findForm($page->getContent(), $action);
        $fields = $this->formFields($form);

        foreach ($values as $name => $value) {
            $this->assertArrayHasKey($name, $fields, "The form posting to {$action} has no field named \"{$name}\". Fields: ".implode(', ', array_keys($fields)));
            $fields[$name] = $value;
        }

        $method = strtoupper($fields['_method'] ?? $form->getAttribute('method') ?: 'GET');

        return $this->call($method === 'GET' ? 'GET' : 'POST', $action, $this->toRequestData($fields));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formFieldsFor(string $pageUrl, string $action): array
    {
        return $this->formFields($this->findForm($this->get($pageUrl)->assertOk()->getContent(), $action));
    }

    private function findForm(string $html, string $action): DOMElement
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        foreach ((new DOMXPath($doc))->query('//form') as $form) {
            /** @var DOMElement $form */
            if (rtrim($form->getAttribute('action'), '/') === rtrim($action, '/')) {
                return $form;
            }
        }

        $this->fail("No <form action=\"{$action}\"> found on the page.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formFields(DOMElement $form): array
    {
        $fields = [];
        $xpath = new DOMXPath($form->ownerDocument);

        foreach ($xpath->query('.//input|.//select|.//textarea', $form) as $el) {
            /** @var DOMElement $el */
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')) {
                continue;
            }
            $tag = strtolower($el->tagName);
            $type = strtolower($el->getAttribute('type') ?: 'text');

            if ($tag === 'input' && in_array($type, ['submit', 'button', 'reset', 'file', 'image'], true)) {
                continue;
            }

            $value = match (true) {
                $tag === 'textarea' => $el->textContent,
                $tag === 'select' => $this->selectedValues($xpath, $el),
                in_array($type, ['checkbox', 'radio'], true) => $el->hasAttribute('checked') ? $el->getAttribute('value') : null,
                default => $el->getAttribute('value'),
            };

            if (str_ends_with($name, '[]')) {
                $fields[$name] = array_merge($fields[$name] ?? [], (array) ($value ?? []));
            } elseif ($value !== null || ! array_key_exists($name, $fields)) {
                $fields[$name] = $value ?? ($fields[$name] ?? null);
            }
        }

        return $fields;
    }

    /**
     * @return string|list<string>
     */
    private function selectedValues(DOMXPath $xpath, DOMElement $select): string|array
    {
        $selected = [];
        foreach ($xpath->query('.//option', $select) as $option) {
            /** @var DOMElement $option */
            if ($option->hasAttribute('selected')) {
                $selected[] = $option->getAttribute('value');
            }
        }

        if ($select->hasAttribute('multiple')) {
            return $selected;
        }

        $first = $xpath->query('.//option', $select)->item(0);

        return $selected[0] ?? ($first instanceof DOMElement ? $first->getAttribute('value') : '');
    }

    /**
     * Turn "items[0][title]" / "members[]" style names into the nested array PHP would receive.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function toRequestData(array $fields): array
    {
        $query = [];
        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                $base = substr($name, 0, -2);
                foreach (array_values($value) as $v) {
                    $query[] = urlencode($base).'[]='.urlencode((string) $v);
                }

                continue;
            }
            $query[] = urlencode($name).'='.urlencode((string) $value);
        }
        parse_str(implode('&', $query), $data);

        return $data;
    }
}
