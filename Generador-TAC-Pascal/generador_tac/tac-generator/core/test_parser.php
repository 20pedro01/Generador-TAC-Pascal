<?php
require '../../../analizador_semantico/core/lexer.php';
require '../../../analizador_semantico/core/parser.php';
class DummyError { public function addError() {} }
$code = 'program t; begin x := (a+b) * c - (d/e); end.';
$lexer = new Lexer();
$tokens = $lexer->tokenize($code);
$parser = new Parser(new DummyError());
print_r($parser->parse($tokens));
