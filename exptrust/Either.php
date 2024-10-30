<?php declare (strict_types = 1);

namespace exptrust;

// data Either a b = Left a | Right b
interface Either {
    function cata (callable $f, callable $g);
    function bind (callable $f) : Either;
    function map (callable $f) : Either;
}
interface Left extends Either { function leftValue (); }
interface Right extends Either { function rightValue (); }

// left :: a -> Either a b
function left() { return function ($value) : Either {
    return new class ($value) implements Left {
        private $value;
        function leftValue () { return $this->value; }
        function __construct ($value) { $this->value = $value; }
        function cata (callable $f, callable $g) { return $f ($this->leftValue ()); }
        function bind (callable $f): Either { return $this; }
        function map (callable $f): Either { return $this; }
    };
}; }

// right :: b -> Either a b
function right() { return function ($value) : Either {
    return new class ($value) implements Right {
        private $value;
        function rightValue () { return $this->value; }
        function __construct ($value) { $this->value = $value; }
        function cata (callable $f, callable $g) { return $g ($this->rightValue ()); }
        function bind (callable $f): Either { return $f ($this->rightValue ()); }
        function map (callable $f): Either { return right ($f ($this->rightValue ())); }
    };
}; }
