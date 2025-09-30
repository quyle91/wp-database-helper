<?php

namespace WpDatabaseHelperV2\Fields;

class WpRepeater {
    protected string $name;
    protected string $label = '';
    /** @var array|WpField[]|WpRepeater[] */
    protected array $fields = [];
    protected array $default = [];

    public static function make(string $name): self {
        $i = new self();
        $i->name = $name;
        return $i;
    }

    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * $fields is an array of WpField or nested WpRepeater instances
     */
    public function fields(array $fields): self {
        $this->fields = $fields;
        return $this;
    }
    public function default(array $data): self {
        $this->default = $data;
        return $this;
    }

    // Render repeater by recursion. $namePrefix used for nested indexes.
    public function render($values = [], string $namePrefix = ''): string {
        $fullBase = $namePrefix ? "{$namePrefix}[{$this->name}]" : $this->name;

        $html = "<div class='wpdh-repeater' data-base='" . esc_attr($fullBase) . "' data-name='" . esc_attr($this->name) . "'>";
        $items = is_array($values) && count($values) ? $values : $this->default;
        if (!count($items)) {
            $items = [[]]; // single empty row
        }

        foreach ($items as $index => $item) {
            $itemPrefix = $namePrefix ? "{$namePrefix}[{$this->name}][{$index}]" : "{$this->name}[{$index}]";
            $html .= "<div class='wpdh-repeater-item' data-index='{$index}'>";
            foreach ($this->fields as $field) {
                if ($field instanceof WpRepeater) {
                    $html .= $field->render($item[$field->getName()] ?? [], $itemPrefix);
                } else {
                    $val = $item[$field->getName()] ?? null;
                    $html .= $field->render($val, $itemPrefix);
                }
            }
            $html .= "<button type='button' class='wpdh-clone'>Clone</button>";
            $html .= "<button type='button' class='wpdh-up'>Up</button>";
            $html .= "<button type='button' class='wpdh-remove'>Remove</button>";
            $html .= "</div>";
        }

        $html .= "</div>"; // bỏ wpdh-add
        return $html;
    }

    // simple toArray for saving
    public function toArray() {
        return [
            'type' => 'repeater',
            'name' => $this->name,
            'label' => $this->label,
            'fields' => array_map(function ($f) {
                return method_exists($f, 'toArray') ? $f->toArray() : (array)$f;
            }, $this->fields),
        ];
    }

    // getters
    public function getFields(): array {
        return $this->fields;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getLabel(): string {
        return $this->label;
    }
}
