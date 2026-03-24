<?php
namespace TACGenerator\Core;

use TACGenerator\Nodes\NodeTypes;
use TACGenerator\Managers\TempManager;
use TACGenerator\Managers\LabelManager;
use TACGenerator\Output\InstructionList;

/**
 * ASTWalker — Recorredor DFS PostOrden del AST Pascal
 *
 * NOTA DE INTEGRACIÓN (AST Integration Agent — 2026-03-15):
 * El parser real (Analizador semántico Pascal/core/parser.php) genera
 * ARRAYS ASOCIATIVOS PHP, NO objetos. Por ello este walker usa $node['key']
 * en lugar de $node->property.
 *
 * Mapa de tipos reales (parser → NodeTypes):
 *   'Program'        → NodeTypes::PROGRAM
 *   'Block'          → NodeTypes::BLOCK
 *   'Assignment'     → NodeTypes::ASSIGNMENT        (usa 'identifier' string, no nodo Variable)
 *   'Identifier'     → NodeTypes::IDENTIFIER        (variable de usuario)
 *   'Literal'        → NodeTypes::LITERAL           (constante numérica/booleana/string)
 *   'BinaryOp'       → NodeTypes::BINARY_OP         (aritmético + relacional + lógico unificados)
 *   'UnaryOp'        → NodeTypes::UNARY_OP
 *   'IfStatement'    → NodeTypes::IF_STATEMENT      (If e IfElse según else_branch)
 *   'WhileStatement' → NodeTypes::WHILE_STATEMENT
 *   'ForStatement'   → NodeTypes::FOR_STATEMENT
 *   'FunctionCall'   → NodeTypes::FUNCTION_CALL
 *   'ProcedureCall'  → NodeTypes::PROCEDURE_CALL
 *   'WriteStatement' → NodeTypes::WRITE_STATEMENT
 *   'ReadStatement'  → NodeTypes::READ_STATEMENT
 */
class ASTWalker {
    private array $tempTypes = []; // Registro de tipos para temporales (ID => type)
    private ?string $currentFunctionName = null; // Nombre de la función en procesamiento



    // ─── GENERADOR DE SENTENCIAS ─────────────────────────────────────────────

    /**
     * Recorre y genera TAC para nodos de tipo sentencia (statements).
     * Estos nodos no retornan valor; emiten instrucciones al InstructionList.
     *
     * @param array|null $node Nodo AST (array asociativo del parser).
     * @return void
     */
    public function generateStatement(?array $node): void {
        if ($node === null) {
            return;
        }

        $type = $node['type'] ?? null;

        switch ($type) {

            // ── PROGRAM ──────────────────────────────────────────────────────
            case NodeTypes::PROGRAM:
                // Procesar funciones/procedimientos declarados
                if (isset($node['functions']) && is_array($node['functions'])) {
                    foreach ($node['functions'] as $func) {
                        $this->generateStatement($func);
                    }
                }
                
                // El nodo raíz emite marcadores de inicio y fin del programa principal
                if (isset($node['body'])) {
                    $progLine  = $node['line'] ?? null;
                    $beginLine = $node['body']['line'] ?? $progLine;
                    $endLine   = $node['end_line'] ?? ($node['body']['end_line'] ?? $beginLine);
                    
                    InstructionList::add("main:", '', $progLine, "Punto de entrada principal del programa.");
                    InstructionList::add("BeginFunc", '', $beginLine, "Inicio del bloque ejecutivo principal.");
                    $this->generateStatement($node['body']);
                    InstructionList::add("End", '', $endLine, "Finalización del programa.");
                }
                break;

            case NodeTypes::FUNCTION_DECL:
            case NodeTypes::PROCEDURE_DECL:
                $name = $node['name'] ?? 'subroutine';
                $declLine = $node['line'] ?? null;
                $beginLine = $node['body']['line'] ?? $declLine;
                $endLine = $node['body']['end_line'] ?? $beginLine;
                
                $this->currentFunctionName = $name;
                InstructionList::add("{$name}:", '', $declLine, "Etiqueta de la subrutina.");
                InstructionList::add("BeginFunc", '', $beginLine, "Entrada al bloque de la subrutina.");
                if (isset($node['body'])) {
                    $this->generateStatement($node['body']);
                }
                // Si es una función, retornamos explícitamente el último temporal generado para el resultado
                if ($type === NodeTypes::FUNCTION_DECL) {
                    $lastTemp = "t" . TempManager::getLastId();
                    InstructionList::add("Return {$lastTemp}", '', $endLine);
                }
                InstructionList::add("EndFunc", '', $endLine);
                InstructionList::add("", "Espacio de separación visual entre subprogramas.", null);
                $this->currentFunctionName = null;
                break;

            // ── BLOCK ────────────────────────────────────────────────────────
            case NodeTypes::BLOCK:
                // Recorre secuencialmente cada sentencia del bloque begin...end.
                // Parser genera: ['type' => 'Block', 'statements' => [...]]
                if (isset($node['statements']) && is_array($node['statements'])) {
                    foreach ($node['statements'] as $stmt) {
                        $this->generateStatement($stmt);
                    }
                }
                break;

            // ── ASSIGNMENT ───────────────────────────────────────────────────
            case NodeTypes::ASSIGNMENT:
                // Parser genera: ['type' => 'Assignment', 'identifier' => string, 'expression' => <expr>]
                // NOTA: 'identifier' es un string plano (no un nodo Variable anidado).
                //
                // TAC esperado para  x := a + b * c:
                //   t1 = b * c
                //   t2 = a + t1
                //   x = t2
                $exprResult = $this->generateExpression($node['expression'] ?? null);
                $varName    = $node['identifier'] ?? '??';
                $line       = $node['line'] ?? null;
                
                // Si la asignación es al nombre de la función (resultado en Pascal), la omitimos en el TAC
                // porque ya usamos Return tN al final. Solo la emitimos si es una variable normal.
                if ($varName !== ($this->currentFunctionName ?? '')) {
                    InstructionList::add("{$varName} = {$exprResult}", '', $line);
                }
                break;

            // ── IF / IF-ELSE ─────────────────────────────────────────────────
            case NodeTypes::IF_STATEMENT:
                // El parser unifica IF e IF-ELSE en un solo tipo 'IfStatement'.
                // Si 'else_branch' es null  →  IF simple.
                // Si 'else_branch' != null  →  IF-ELSE.
                //
                // Parser genera:
                // ['type' => 'IfStatement',
                //  'condition'   => <expr>,
                //  'then_branch' => <stmt>,
                //  'else_branch' => <stmt>|null]
                $condTemp = $this->generateExpression($node['condition'] ?? null);
                $line     = $node['line'] ?? null;

                if (isset($node['else_branch']) && $node['else_branch'] !== null) {
                    $labelElse = LabelManager::newLabel();
                    $labelEnd  = LabelManager::newLabel();

                    InstructionList::add("IfZ {$condTemp} goto {$labelElse}", '', $line);
                    $this->generateStatement($node['then_branch']);
                    InstructionList::add("goto {$labelEnd}", '', $line);
                    InstructionList::add("{$labelElse}:", '', $line);
                    $this->generateStatement($node['else_branch']);
                    InstructionList::add("{$labelEnd}:", '', $line);

                } else {
                    $labelOut = LabelManager::newLabel();

                    InstructionList::add("IfZ {$condTemp} goto {$labelOut}", '', $line);
                    $this->generateStatement($node['then_branch']);
                    InstructionList::add("{$labelOut}:", '', $line);
                }
                break;

            // ── WHILE ────────────────────────────────────────────────────────
            case NodeTypes::WHILE_STATEMENT:
                // Parser genera:
                // ['type' => 'WhileStatement', 'condition' => <expr>, 'body' => <stmt>]
                //
                // TAC:
                //   L1:
                //   t1 = a < 10
                //   IfZ t1 goto L2
                //   <body>
                //   goto L1
                //   L2:
                $labelStart = LabelManager::newLabel();
                $labelEnd   = LabelManager::newLabel();
                $line       = $node['line'] ?? null;

                InstructionList::add("{$labelStart}:", '', $line);
                $condTemp = $this->generateExpression($node['condition'] ?? null);
                InstructionList::add("IfZ {$condTemp} goto {$labelEnd}", '', $line);
                $this->generateStatement($node['body'] ?? null);
                InstructionList::add("goto {$labelStart}", '', $line);
                InstructionList::add("{$labelEnd}:", '', $line);
                break;

            // ── FOR ──────────────────────────────────────────────────────────
            case NodeTypes::FOR_STATEMENT:
                // Parser genera:
                // ['type' => 'ForStatement', 'variable' => string, 'start' => <expr>,
                //  'end' => <expr>, 'direction' => 'to'|'downto', 'body' => <stmt>]
                //
                // TAC para  for i := 1 to 10 do <body>:
                //   startVal = generateExpression(start)
                //   i = startVal
                //   L1:
                //   endVal   = generateExpression(end)
                //   t1 = i <= endVal       (o i >= endVal si downto)
                //   IfZ t1 goto L2
                //   <body>
                //   tN = i + 1             (o i - 1 si downto)
                //   i = tN
                //   goto L1
                //   L2:
                $varName    = $node['variable'] ?? 'i';
                $direction  = $node['direction'] ?? 'to';
                $labelStart = LabelManager::newLabel();
                $labelEnd   = LabelManager::newLabel();
                $line       = $node['line'] ?? null;

                // Inicialización: i = startExpr
                $startVal = $this->generateExpression($node['start'] ?? null);
                InstructionList::add("{$varName} = {$startVal}", '', $line);

                // Cabecera del bucle
                InstructionList::add("{$labelStart}:", '', $line);

                // Condición de continuación
                $endVal  = $this->generateExpression($node['end'] ?? null);
                $condOp  = ($direction === 'downto') ? '>=' : '<=';
                $condTemp = TempManager::newTemp();
                InstructionList::add("{$condTemp} = {$varName} {$condOp} {$endVal}", '', $line);
                InstructionList::add("IfZ {$condTemp} goto {$labelEnd}", '', $line);

                // Cuerpo del bucle
                $this->generateStatement($node['body'] ?? null);

                // Incremento / decremento del contador
                $stepOp  = ($direction === 'downto') ? '-' : '+';
                $stepTemp = TempManager::newTemp();
                InstructionList::add("{$stepTemp} = {$varName} {$stepOp} 1", '', $line);
                InstructionList::add("{$varName} = {$stepTemp}", '', $line);

                // Salto de retorno y etiqueta de salida
                InstructionList::add("goto {$labelStart}", '', $line);
                InstructionList::add("{$labelEnd}:", '', $line);
                break;

            // ── PROCEDURE_CALL ───────────────────────────────────────────────
            case NodeTypes::PROCEDURE_CALL:
                // Parser genera:
                // ['type' => 'ProcedureCall', 'name' => string, 'arguments' => [...]]
                //
                // TAC:
                //   PushParam <arg1>
                //   PushParam <arg2>
                //   Call <name>
                //   PopParams <count>
                $args = $node['arguments'] ?? [];
                $numArgs = count($args);
                $line = $node['line'] ?? null;
                // Los parámetros se apilan de Derecha a Izquierda (estándar TAC)
                for ($i = $numArgs - 1; $i >= 0; $i--) {
                    $argVal = $this->generateExpression($args[$i]);
                    InstructionList::add("PushParam {$argVal}", '', $line);
                }
                InstructionList::add("LCall {$node['name']}", '', $line); 
                if ($numArgs > 0) {
                    InstructionList::add("PopParams " . ($numArgs * 4), '', $line);
                }
                break;

            // ── WRITE / WRITELN ──────────────────────────────────────────────
            case NodeTypes::WRITE_STATEMENT:
                // Parser genera:
                // ['type' => 'WriteStatement', 'function' => string, 'arguments' => [...]]
                $funcName = $node['function'] ?? 'write';
                $args     = $node['arguments'] ?? [];
                $numArgs  = count($args);
                $line     = $node['line'] ?? null;
                
                // Determinar el valor del primer argumento para decidir el tipo de impresión
                $firstArgVal = ($numArgs > 0) ? $this->generateExpression($args[0]) : '';
                
                // Estandarización: Derecha a Izquierda
                for ($i = $numArgs - 1; $i >= 0; $i--) {
                    $argVal = ($i === 0) ? $firstArgVal : $this->generateExpression($args[$i]);
                    InstructionList::add("PushParam {$argVal}", '', $line);
                }

                // Selección dinámica de la función de impresión según el manual (Resueltos.md)
                $isString = str_starts_with($firstArgVal, '"') || str_starts_with($firstArgVal, "'");
                
                // Si es un temporal, verificamos si está registrado como string
                if (!$isString && str_starts_with($firstArgVal, 't')) {
                    $tid = (int)substr($firstArgVal, 1);
                    if (isset($this->tempTypes[$tid]) && $this->tempTypes[$tid] === 'string') {
                        $isString = true;
                    }
                }

                $suffix = $isString ? 'String' : 'Int';
                $lcallName = "_Print" . $suffix;
                
                InstructionList::add("LCall {$lcallName}", '', $line);
                if ($numArgs > 0) {
                    InstructionList::add("PopParams " . ($numArgs * 4), '', $line);
                }
                break;

            // ── READ / READLN ─────────────────────────────────────────────────
            case NodeTypes::READ_STATEMENT:
                // Parser genera:
                // ['type' => 'ReadStatement', 'function' => string, 'variables' => [...]]
                $funcName = $node['function'] ?? 'read';
                $vars     = $node['variables'] ?? [];
                $numArgs  = count($vars);
                $line     = $node['line'] ?? null;
                foreach ($vars as $var) {
                    $varName = $var['name'] ?? '??';
                    InstructionList::add("PushParam {$varName}", '', $line);
                }
                InstructionList::add("Call {$funcName}", '', $line);
                if ($numArgs > 0) {
                    InstructionList::add("PopParams " . ($numArgs * 4), '', $line);
                }
                break;

            default:
                // Si llega un nodo que no es sentencia (ej. un bloque Begin suelto),
                // intentar procesarlo como expresión. No emitirá nada si no es reconocible.
                if ($node !== null) {
                    $this->generateExpression($node);
                }
                break;
        }
    }

    // ─── GENERADOR DE EXPRESIONES ────────────────────────────────────────────

    /**
     * Recorre y genera TAC para nodos de tipo expresión (R-Values).
     * Implementa un planificador (scheduler) para respetar la precedencia matemática
     * de las operaciones en todo el árbol de la expresión, resolviendo primero
     * operaciones como * y / antes que + y -, incluso de ramas independientes.
     *
     * @param array|null $node Nodo AST (array asociativo del parser).
     * @return string|null Cadena con el resultado (temporal, nombre o literal).
     */
    public function generateExpression(?array $node): ?string {
        if ($node === null) {
            return null;
        }

        $ops = [];
        $inOrder = 0;
        $globalParenId = 0;
        $scope = ['level' => 0, 'id' => 0];
        $rootRef = $this->flattenExpression($node, $ops, $inOrder, $globalParenId, $scope);

        if (!is_string($rootRef) || !str_starts_with($rootRef, 'op_')) {
            // El nodo raíz era un valor simple (literal, identificador, u otro sin emitir).
            return $rootRef;
        }

        // Si es una operación compleja, planificamos la emisión del TAC respetando jerarquía
        return $this->scheduleAndEmitOps($ops, $rootRef);
    }

    private function flattenExpression(?array $node, array &$ops, int &$inOrder, int &$globalParenId, array $scope): ?string {
        if ($node === null) return null;

        $type = $node['type'] ?? null;

        $myScope = $scope;
        if (!empty($node['paren_level'])) {
            $myScope['level'] += $node['paren_level'];
            $myScope['id'] = ++$globalParenId;
        }

        if ($type === NodeTypes::IDENTIFIER) {
            return $node['name'] ?? null;
        }

        if ($type === NodeTypes::LITERAL) {
            $val = $node['value'];
            $dt  = $node['data_type'] ?? '';
            if ($dt === 'boolean') {
                return ($val === 'true' || $val === '1' || $val === 1) ? '1' : '0';
            }
            // Si es string o char, lo envolvemos en comillas para el TAC
            if ($dt === 'string' || $dt === 'char') {
                return '"' . $val . '"';
            }
            return (string)$val;
        }

        if ($type === NodeTypes::ARRAY_ACCESS) {
            $indexRef = $this->flattenExpression($node['index'] ?? null, $ops, $inOrder, $globalParenId, $myScope);
            $arrName  = $node['name'] ?? '??';
            return "{$arrName}[{$indexRef}]";
        }

        $id = 'op_' . count($ops);
        $ops[$id] = true; // Reserva temprana para evitar colisiones en llamadas recursivas

        $opData = [
            'id' => $id,
            'type' => $type,
            'line' => $node['line'] ?? null,
            'inOrder' => 0,
            'precedence' => 0,
            'parenLevel' => $myScope['level'],
            'parenId' => $myScope['id'],
            'dependencies' => []
        ];

        if ($type === NodeTypes::BINARY_OP) {
            $op = strtolower($node['operator'] ?? '?');

            if ($op === '<=' || $op === '>=' || $op === '<>') {
                // Interceptamos para separar lógicamente según petición explícita
                $leftRef = $this->flattenExpression($node['left'] ?? null, $ops, $inOrder, $globalParenId, $myScope);
                $rightRef = $this->flattenExpression($node['right'] ?? null, $ops, $inOrder, $globalParenId, $myScope);

                $op1 = ($op === '<=') ? '<' : (($op === '>=') ? '>' : '<');
                $op2 = ($op === '<>') ? '>' : '==';

                // Operación 1 (< o >)
                $idOp1 = 'op_' . count($ops);
                $ops[$idOp1] = true;
                $opData1 = [
                    'id' => $idOp1,
                    'type' => NodeTypes::BINARY_OP,
                    'line' => $node['line'] ?? null,
                    'inOrder' => $inOrder++,
                    'operator' => $op1,
                    'left' => $leftRef,
                    'right' => $rightRef,
                    'precedence' => $this->getPrecedence($op1),
                    'parenLevel' => $myScope['level'] + 1, // Fuerza resolución rápida
                    'parenId' => $myScope['id'],
                    'dependencies' => []
                ];
                if (is_string($leftRef) && str_starts_with($leftRef, 'op_')) $opData1['dependencies'][] = $leftRef;
                if (is_string($rightRef) && str_starts_with($rightRef, 'op_')) $opData1['dependencies'][] = $rightRef;
                $ops[$idOp1] = $opData1;

                // Operación 2 (= o >)
                $idOp2 = 'op_' . count($ops);
                $ops[$idOp2] = true;
                $opData2 = [
                    'id' => $idOp2,
                    'type' => NodeTypes::BINARY_OP,
                    'line' => $node['line'] ?? null,
                    'inOrder' => $inOrder++,
                    'operator' => $op2,
                    'left' => $leftRef,
                    'right' => $rightRef,
                    'precedence' => $this->getPrecedence($op2),
                    'parenLevel' => $myScope['level'] + 1, // Fuerza resolución rápida
                    'parenId' => $myScope['id'],
                    'dependencies' => []
                ];
                if (is_string($leftRef) && str_starts_with($leftRef, 'op_')) $opData2['dependencies'][] = $leftRef;
                if (is_string($rightRef) && str_starts_with($rightRef, 'op_')) $opData2['dependencies'][] = $rightRef;
                $ops[$idOp2] = $opData2;

                // El nodo inicial reservado se convierte en el OR unificador
                $opData['inOrder'] = $inOrder++;
                $opData['operator'] = '||';
                $opData['left'] = $idOp1;
                $opData['right'] = $idOp2;
                $opData['precedence'] = $this->getPrecedence('||');
                $opData['dependencies'] = [$idOp1, $idOp2];
                
            } else {
                // Comportamiento normal
                $leftRef = $this->flattenExpression($node['left'] ?? null, $ops, $inOrder, $globalParenId, $myScope);
                $opData['inOrder'] = $inOrder++;
                $rightRef = $this->flattenExpression($node['right'] ?? null, $ops, $inOrder, $globalParenId, $myScope);

                $opData['operator'] = $op;
                $opData['left'] = $leftRef;
                $opData['right'] = $rightRef;
                $opData['precedence'] = $this->getPrecedence($op);

                if (is_string($leftRef) && str_starts_with($leftRef, 'op_')) $opData['dependencies'][] = $leftRef;
                if (is_string($rightRef) && str_starts_with($rightRef, 'op_')) $opData['dependencies'][] = $rightRef;
            }

        } elseif ($type === NodeTypes::UNARY_OP) {
            $opData['inOrder'] = $inOrder++;
            $opData['operator'] = $node['operator'] ?? '?';
            $operandRef = $this->flattenExpression($node['operand'] ?? null, $ops, $inOrder, $globalParenId, $myScope);
            $opData['operand'] = $operandRef;
            $opData['precedence'] = 3;
            if (is_string($operandRef) && str_starts_with($operandRef, 'op_')) $opData['dependencies'][] = $operandRef;

        } elseif ($type === NodeTypes::FUNCTION_CALL) {
            $opData['inOrder'] = $inOrder++;
            $opData['name'] = $node['name'] ?? '??';
            $opData['args'] = [];
            $opData['precedence'] = 4;
            $args = $node['arguments'] ?? [];
            foreach ($args as $arg) {
                $argRef = $this->flattenExpression($arg, $ops, $inOrder, $globalParenId, $myScope);
                $opData['args'][] = $argRef;
                if (is_string($argRef) && str_starts_with($argRef, 'op_')) {
                    $opData['dependencies'][] = $argRef;
                }
            }

        } else {
            unset($ops[$id]); // Si no fue procesado, liberamos el ID reservado
            return null; // Nodo ignorado/no evaluado
        }

        $ops[$id] = $opData;
        return $id;
    }

    private function getPrecedence(string $op): int {
        $op = strtolower($op);
        if ($op === '^' || $op === '**') {
            return 3;
        }
        if (in_array($op, ['*', '/', 'div', 'mod', '%', 'and', '&&'], true)) {
            return 2;
        }
        if (in_array($op, ['+', '-', 'or', '||'], true)) {
            return 1;
        }
        if (in_array($op, ['<', '<=', '>', '>=', '=', '<>', '==', '!='], true)) {
            return 0;
        }
        return 1;
    }

    private function scheduleAndEmitOps(array $ops, string $rootRef): string {
        $evaluated = [];
        $readyOps = [];

        foreach ($ops as $id => $op) {
            if (empty($op['dependencies'])) {
                $readyOps[] = $id;
            }
        }

        while (!empty($readyOps)) {
            usort($readyOps, function($a, $b) use ($ops) {
                $lA = $ops[$a]['parenLevel'] ?? 0;
                $lB = $ops[$b]['parenLevel'] ?? 0;
                if ($lA !== $lB) return $lB <=> $lA; // Nivel mayor primero

                $idA = $ops[$a]['parenId'] ?? 0;
                $idB = $ops[$b]['parenId'] ?? 0;
                if ($idA !== $idB) return $idA <=> $idB; // ID menor (izquierdo) primero

                $pA = $ops[$a]['precedence'] ?? 0;
                $pB = $ops[$b]['precedence'] ?? 0;
                if ($pA !== $pB) {
                    return $pB <=> $pA; // Precedencia mayor primero
                }
                
                return ($ops[$a]['inOrder'] ?? 0) <=> ($ops[$b]['inOrder'] ?? 0); // Menor index inOrder primero
            });

            $currentId = array_shift($readyOps);
            // Marcarlo como evaluado para que no se reintroduzca accidentalmente
            // aunque el flujo actual no lo haría, es más seguro.
            
            $op = $ops[$currentId];

            $resolve = function($ref) use ($evaluated) {
                if (is_string($ref) && str_starts_with($ref, 'op_')) {
                    return $evaluated[$ref];
                }
                return $ref;
            };

            $temp = TempManager::newTemp();
            $line = $op['line'];

            if ($op['type'] === NodeTypes::BINARY_OP) {
                $left = $resolve($op['left']);
                $right = $resolve($op['right']);
                $operator = $op['operator'];

                // Estandarización de operadores de comparación para TAC
                if ($operator === '=' || $operator === '==') $operator = '==';
                if ($operator === '<>' || $operator === '!=') $operator = '!=';
                
                // Estandarización de operadores lógicos
                if ($operator === 'and') $operator = '&&';
                if ($operator === 'or') $operator = '||';

                // Estandarización de operadores aritméticos
                if ($operator === 'mod') $operator = '%';
                if ($operator === '^' || $operator === '**') $operator = '**';

                // Detección de concatenación de cadenas
                $isStringOp = false;
                if ($operator === '+') {
                    $lIsS = str_starts_with($left, '"') || (str_starts_with($left, 't') && ($this->tempTypes[(int)substr($left, 1)] ?? '') === 'string');
                    $rIsS = str_starts_with($right, '"') || (str_starts_with($right, 't') && ($this->tempTypes[(int)substr($right, 1)] ?? '') === 'string');
                    if ($lIsS || $rIsS) $isStringOp = true;
                }
                
                if ($isStringOp) {
                    $tid = (int)substr($temp, 1);
                    $this->tempTypes[$tid] = 'string';
                }

                InstructionList::add("{$temp} = {$left} {$operator} {$right}", '', $line);
            } elseif ($op['type'] === NodeTypes::UNARY_OP) {
                $operand = $resolve($op['operand']);
                $operator = $op['operator'];
                
                // Estandarización de negación (Pascal not -> TAC == 0)
                if ($operator === 'not') {
                    InstructionList::add("{$temp} = {$operand} == 0", '', $line);
                } else {
                    InstructionList::add("{$temp} = {$operator} {$operand}", '', $line);
                }
            } elseif ($op['type'] === NodeTypes::FUNCTION_CALL) {
                $args = [];
                foreach ($op['args'] as $arg) {
                    $args[] = $resolve($arg);
                }
                $numArgs = count($args);
                // Inversión: Derecha a Izquierda
                for ($i = $numArgs - 1; $i >= 0; $i--) {
                    InstructionList::add("PushParam {$args[$i]}", '', $line);
                }
                $name = $op['name'];
                InstructionList::add("{$temp} = LCall {$name}", '', $line);
                if ($numArgs > 0) {
                    InstructionList::add("PopParams " . ($numArgs * 4), '', $line);
                }
            }

            $evaluated[$currentId] = $temp;

            foreach ($ops as $id => $otherOp) {
                if (isset($evaluated[$id]) || in_array($id, $readyOps)) {
                    continue;
                }

                $allReady = true;
                foreach ($otherOp['dependencies'] as $dep) {
                    if (!isset($evaluated[$dep])) {
                        $allReady = false;
                        break;
                    }
                }

                if ($allReady) {
                    $readyOps[] = $id;
                }
            }
        }

        return $evaluated[$rootRef];
    }
}
