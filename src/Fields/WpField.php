<?php

namespace WpDatabaseHelperV2\Fields;

class WpField {
    protected string $type;
    protected string $name;
    protected string $label = '';
    protected array $attributes = [];
    protected $default = null;

    public static function make(string $type, string $name): self {
        $inst = new self();
        $inst->type = $type;
        $inst->name = $name;
        return $inst;
    }

    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }
    public function attribute(array $attrs): self {
        $this->attributes = array_merge($this->attributes, $attrs);
        return $this;
    }
    public function default($v): self {
        $this->default = $v;
        return $this;
    }

    // render input; namePrefix is like "my_repeater[0]" for nested usage
    public function render($value = null, string $namePrefix = ''): string {
        $name = $namePrefix ? "{$namePrefix}[{$this->name}]" : $this->name;
        $val = $value ?? $this->default ?? '';
        $attrStr = '';
        foreach ($this->attributes as $k => $v) {
            $attrStr .= sprintf(' %s="%s"', esc_attr($k), esc_attr($v));
        }

        if ($this->type === 'textarea') {
            return "<label>{$this->label}<textarea name=\"{$name}\"{$attrStr}>" . esc_textarea($val) . "</textarea></label>";
        }

        $inputType = esc_attr($this->type === 'text' ? 'text' : $this->type);
        return "<label>{$this->label}<input type=\"{$inputType}\" name=\"{$name}\" value=\"" . esc_attr($val) . "\"{$attrStr}></label>";
    }

    // simple toArray for saving
    public function toArray() {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label,
            'attributes' => $this->attributes,
            'default' => $this->default,
        ];
    }

    // getters
    public function getType(): string {
        return $this->type;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getLabel(): string {
        return $this->label;
    }
}
