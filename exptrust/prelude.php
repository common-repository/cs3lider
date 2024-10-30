<?php declare (strict_types = 1);

namespace exptrust;

// id :: a -> a
function id() : callable { return function ($x) { return $x; }; }

// pair :: a -> (a, a)
function pair() : callable { return function ($x) : array { return [$x, $x]; }; }

// curry :: ((a, b) -> c) -> a -> b -> c
function curry() : callable {
    return function (callable $f) : callable {
        return function ($x) use ($f) : callable {
            return function ($y) use ($f, $x) {
                return $f ($x, $y); }; }; }; }

// uncurry :: (a -> b -> c) -> (a, b) -> c
function uncurry() : callable {
    return function (callable $f) : callable {
        return function ($x, $y) use ($f) { return $f ($x) ($y); }; }; }

// curry3 :: ((a, b, c) -> d) -> a -> b -> c -> d
function curry3() : callable {
    return function (callable $f) : callable {
        return function ($x) use ($f) : callable {
            return function ($y) use ($f, $x) {
                return function ($z) use ($f, $x, $y) {
                    return $f ($x, $y, $z); }; }; }; }; }

// strcat :: string -> string -> string
function strcat() : callable { return curry() (function (string $xs, string $ys) : string { return $xs . $ys; }); }

// cat :: [a] -> [a] -> [a]
function cat() : callable { return curry() (function (array $xs, array $ys) : array { return array_merge ($xs, $ys); }); }

// concat :: [[a]] -> [a]
function concat() : callable { return function (array $xss) : array { return array_reduce ($xss, 'array_merge', []); }; }

// intercalate :: string -> [string] -> string
function intercalate() : callable { return curry() (function (string $delim, array $xss) : string { return implode ( $xss, $delim ); }); }

// map :: (a -> b) -> [a] -> [b]
function map() : callable { return curry() (function (callable $f, array $xs) : array { return array_map ( $f, $xs ); }); }

// zip_with :: (a -> b -> c) -> [a] -> [b] -> [c]
function zip_with() : callable { return curry3() (function (callable $f, array $xs, array $ys) : array { return array_map ( uncurry() ($f), $xs, $ys ); }); }

// int2str :: int -> string
function int2str() : callable { return function (int $n) : string { return (string) $n; }; }

// float2str :: float -> string
function float2str() : callable { return function (float $x) : string { return (string) $x; }; }

// single :: a -> [a]
function single() : callable { return function ($x) : array { return [$x]; }; }

// cross_product :: [a] -> [b] -> [(a, b)]
function cross_product() : callable { return curry() (function (array $xs, array $ys) : array {
    return concat_map() (function ($x) use ($ys) { return concat_map() (function ($y) use ($x) { return single() ([$x, $y]); })
                                                                       ($ys); })
                        ($xs); }); }

// compose :: (b -> c) -> (a -> b) -> a -> c
function compose() : callable { return curry3() (function (callable $f, callable $g, $x) { return $f ($g ($x)); }); }

// concat_map :: (a -> b) -> [[a]] -> [b]
function concat_map() : callable { return compose() (compose() (concat())) (map()); }

function pack2() : callable { return curry() (function (callable $f, array $xs) { return $f ($xs[0], $xs[1]); }); }

// euclidean_mod :: int -> int -> int
function euclidean_mod() : callable { return curry() (function (int $a, int $b) : int {
    return $a - abs ($b) * (int)(floor ($a / abs ($b))); }); }

// equal_to :: Eq a => a -> a -> bool
function equal_to() : callable { return curry() (function ($a, $b) : bool { return $a == $b; }); }

// not_equal_to :: Eq a => a -> a -> bool
function not_equal_to() : callable { return curry() (function ($a, $b) : bool { return $a != $b; }); }

// not :: bool -> bool
function not() : callable { return function (bool $a) : bool { return !$a; }; }