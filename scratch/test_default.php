<?php
class Undefined {}
class Schema {
    public function __construct(
        public mixed $example = new Undefined()
    ) {}
}
$s = new Schema();
var_dump($s->example instanceof Undefined);
$s2 = new Schema(null);
var_dump($s2->example === null);
