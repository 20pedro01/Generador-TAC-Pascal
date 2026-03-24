<?php
require_once __DIR__ . '/../analizador_semantico/core/errorHandler.php';
require_once __DIR__ . '/../analizador_semantico/core/lexer.php';
require_once __DIR__ . '/../analizador_semantico/core/parser.php';
require_once __DIR__ . '/tac-generator/core/TACGenerator.php';

$code = "program Test;
var a, b, c, x: integer;
begin
  x := a <= b;
end.";

$errorHandler = new ErrorHandler();
$lexer = new Lexer($errorHandler);
$tokens = $lexer->tokenize($code);

$parser = new Parser($errorHandler);
$ast = $parser->parse($tokens);

$generator = new \TACGenerator\Core\TACGenerator();
$tac = $generator->generate($ast);

echo $tac;
