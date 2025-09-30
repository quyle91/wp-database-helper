<?php

namespace WpDatabaseHelperV2\Database;

class DbColumn {
    protected string $name;
    protected string $type;
    protected ?int $length = null;
    protected array $flags = [];

    public static function string(string $name, int $len = 255): self {
        $c = new self();
        $c->name = $name;
        $c->type = 'VARCHAR';
        $c->length = $len;
        return $c;
    }
    public static function integer(string $name): self {
        $c = new self();
        $c->name = $name;
        $c->type = 'INT';
        return $c;
    }
    public static function id(string $name = 'id'): self {
        $c = self::integer($name);
        $c->flags[] = 'AUTO_INCREMENT';
        return $c;
    }
    public function notNull(): self {
        $this->flags[] = 'NOT NULL';
        return $this;
    }
    public function default($v): self {
        $this->flags[] = "DEFAULT {$v}";
        return $this;
    }

    public function toSql(): string {
        $len = $this->length ? "({$this->length})" : "";
        $flags = $this->flags ? ' ' . implode(' ', $this->flags) : '';
        return sprintf("%s %s%s %s", $this->name, $this->type, $len, trim($flags));
    }
}
